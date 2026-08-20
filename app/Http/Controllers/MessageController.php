<?php

namespace App\Http\Controllers;

use App\Helpers\PhoneNormalizer;
use App\Services\GoWaApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Controller untuk endpoint Whacenter:
 *   POST/GET /api/send
 *
 * Dua skenario input file yang didukung:
 *   A) Upload fisik  — $request->file('file') / $request->file('image')
 *   B) URL string  — $request->input('file') / $request->input('url')
 *
 * forwarding ke GoWA:
 *   - /send/image  (gambar)   — via image_url atau attachment 'image'
 *   - /send/file   (dokumen) — via file_url   atau attachment 'file'
 */
class MessageController extends Controller
{
    private GoWaApiService $gowa;

    /** Ekstensi yang diklasifikasikan sebagai gambar. */
    private const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'ico'];

    /** Pattern host/path yang menandakan image hosting. */
    private const IMAGE_HOST_PATTERNS = [
        'i.ibb.co', 'imgur.com', 'imgbb.com', 'cloudinary.com',
        'prnt.sc', 'prntscr.com', 'laptop-media.net',
        'media.', 'images.', 'img.',
    ];

    public function __construct(GoWaApiService $gowa)
    {
        $this->gowa = $gowa;
    }

    /**
     * POST /api/send  |  GET /api/send
     *
     * Whacenter menerima payload sebagai multipart/form-data (Postman form-data).
     * Parameter 'file' bisa berisi:
     *   - Upload fisik:  file=<binary image/document>
     *   - URL string:   file=https://example.com/image.jpg
     */
    public function send(Request $request): \Illuminate\Http\JsonResponse
    {
        // ── 1. device_id ────────────────────────────────────────────────────────
        $deviceId = $request->input('device_id') ?? $request->input('device_id');

        if (empty($deviceId)) {
            Log::warning('[MessageController] send: missing device_id');

            return $this->errorResponse();
        }

        // ── 2. Nomor tujuan ────────────────────────────────────────────────────
        $rawPhone = $request->input('number') ?? $request->input('phone');

        if (empty($rawPhone)) {
            Log::warning('[MessageController] send: missing number/phone');

            return $this->errorResponse();
        }

        $normalized = PhoneNormalizer::normalize($rawPhone);

        if (empty($normalized)) {
            Log::warning("[MessageController] send: invalid phone '{$rawPhone}' after normalization");

            return $this->errorResponse();
        }

        $jid = $normalized . '@s.whatsapp.net';

        // ── 3. Pesan / caption ────────────────────────────────────────────────
        $message  = $request->input('message') ?? '';
        $caption  = $request->input('caption') ?? $message;

        // ── 4. Deteksi media ───────────────────────────────────────────────────
        //    Cek skenario A: upload fisik
        $uploadedFile = $this->getUploadedFile($request);

        //    Cek skenario B: URL string
        $urlString = $this->getMediaUrl($request);

        $hasFile = $uploadedFile !== null || ! empty($urlString);

        Log::info("[MessageController] send: device={$deviceId} jid={$jid} "
            . "message_len=" . strlen($message)
            . " uploaded_file=" . ($uploadedFile ? $uploadedFile->getClientOriginalName() : 'null')
            . " url_string=" . ($urlString ?: 'null'));

        // ── 5. Routing ke handler yang tepat ──────────────────────────────────
        if ($hasFile) {
            return $this->sendMedia($request, $deviceId, $jid, $message, $caption, $uploadedFile, $urlString);
        }

        return $this->sendText($deviceId, $jid, $message, $request);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE HANDLERS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Kirim pesan teks murni.
     */
    private function sendText(string $deviceId, string $jid, string $message, Request $request): \Illuminate\Http\JsonResponse
    {
        $options = $this->extractOptions($request);

        $result = $this->gowa->sendMessage($deviceId, $jid, $message, $options);

        Log::info('[MessageController] sendText result: '
            . json_encode(['error' => $result['error'], 'msg_id' => $result['message_id'] ?? null]));

        if ($result['error'] === null) {
            return $this->successResponse($result['message_id']);
        }

        return $this->errorResponse();
    }

    /**
     * Kirim gambar atau dokumen.
     *
     * @param  Request                 $request
     * @param  string                 $deviceId
     * @param  string                 $jid
     * @param  string                 $message
     * @param  string                 $caption
     * @param  \Illuminate\Http\UploadedFile|null  $uploadedFile   Skenario A: file fisik
     * @param  string|null            $urlString      Skenario B: URL string
     * @return \Illuminate\Http\JsonResponse
     */
    private function sendMedia(
        Request $request,
        string $deviceId,
        string $jid,
        string $message,
        string $caption,
        $uploadedFile,
        ?string $urlString
    ): \Illuminate\Http\JsonResponse {
        $options = $this->extractOptions($request);

        // ── Tentukan tipe media ──────────────────────────────────────────────
        // Prioritas 1: file fisik — deteksi dari extension
        // Prioritas 2: URL string — deteksi dari extension / host pattern
        // Fallback: treat as image (lebih umum)
        $isImage = false;

        if ($uploadedFile !== null) {
            $ext = strtolower($uploadedFile->getClientOriginalExtension());
            $isImage = in_array($ext, self::IMAGE_EXTS, true);

            Log::info("[MessageController] sendMedia: uploaded_file={$uploadedFile->getClientOriginalName()} ext={$ext} isImage={$isImage}");

            $result = $this->sendMediaToGoWa(
                $deviceId, $jid, $caption, $isImage,
                $uploadedFile,   // file fisik
                null,            // tidak perlu URL string
                $options
            );
        } else {
            $isImage = $this->isImageUrl($urlString);

            Log::info("[MessageController] sendMedia: url_string={$urlString} isImage={$isImage}");

            $result = $this->sendMediaToGoWa(
                $deviceId, $jid, $caption, $isImage,
                null,            // tidak ada file fisik
                $urlString,     // gunakan URL string
                $options
            );
        }

        Log::info('[MessageController] sendMedia result: '
            . json_encode(['error' => $result['error'], 'msg_id' => $result['message_id'] ?? null]));

        if ($result['error'] === null) {
            return $this->successResponse($result['message_id']);
        }

        return $this->errorResponse();
    }

    /**
     * Kirim media ke GoWA menggunakan HTTP client multipart.
     *
     * GoWA endpoint:
     *   /send/image  → accepts 'image' (binary) OR 'image_url' (string)
     *   /send/file   → accepts 'file'   (binary) OR 'file_url'   (string)
     *
     * Laravel HTTP Client otomatis mendeteksi payload array sebagai multipart/form-data
     * ketika menggunakan attach() atau UploadedFile instance — sehingga
     * Content-Type: multipart/form-data diset otomatis tanpa perlu manual header.
     *
     * @param  string                 $deviceId
     * @param  string                 $jid
     * @param  string                 $caption
     * @param  bool                   $isImage
     * @param  \Illuminate\Http\UploadedFile|null  $uploadedFile   File fisik dari upload
     * @param  string|null            $urlString      URL string
     * @param  array                  $options
     * @return array{message_id: string|null, error: string|null, httpStatus: int}
     */
    private function sendMediaToGoWa(
        string $deviceId,
        string $jid,
        string $caption,
        bool $isImage,
        $uploadedFile,
        ?string $urlString,
        array $options
    ): array {
        $endpoint = $isImage ? '/send/image' : '/send/file';

        // Key untuk field file sesuai OpenAPI GoWA
        $fileKey  = $isImage ? 'image'    : 'file';
        $urlKey   = $isImage ? 'image_url' : 'file_url';

        $baseUrl  = config('gowa.base_url');
        $userAuth = config('gowa.basic_auth_user');
        $passAuth = config('gowa.basic_auth_pass');

        Log::info("[MessageController] sendMediaToGoWa: POST {$baseUrl}{$endpoint} device={$deviceId}");

        try {
            // ── Bangun HTTP client ─────────────────────────────────────────
            $client = Http::timeout(60)
                ->acceptJson()
                ->withHeaders(['X-Device-Id' => $deviceId]);

            if (! empty($userAuth)) {
                $client = $client->withBasicAuth($userAuth, $passAuth);
            }

            // ── Bangun body multipart ────────────────────────────────────────
            $fields = array_filter([
                'phone'            => $jid,
                'caption'          => $caption,
                $urlKey            => $urlString,   // Skenario B: URL string
                'reply_message_id' => $options['reply_message_id'] ?? null,
                'view_once'        => $options['view_once'] ?? null,
                'compress'         => $options['compress'] ?? null,
                'duration'         => $options['duration'] ?? null,
                'is_forwarded'     => $options['is_forwarded'] ?? null,
            ], fn ($v) => $v !== null);

            // Skenario A: File fisik — gunakan attach() + hapus urlKey dari fields
            if ($uploadedFile !== null) {
                $fields = array_filter($fields, fn ($k) => $k !== $urlKey, ARRAY_FILTER_USE_KEY);

                $response = $client
                    ->attach(
                        $fileKey,                                           // field name
                        file_get_contents($uploadedFile->getRealPath()),   // binary content
                        $uploadedFile->getClientOriginalName()             // filename
                    )
                    ->post("{$baseUrl}{$endpoint}", $fields);
            } else {
                // Skenario B: URL string — kirim sebagai multipart field biasa
                $response = $client->post("{$baseUrl}{$endpoint}", $fields);
            }

            $status = $response->status();

            Log::info("[MessageController] sendMediaToGoWa: HTTP {$status}");

            if (! $response->successful()) {
                $body = $response->json();
                $errorMsg = $body['message'] ?? "HTTP {$status}";

                Log::error("[MessageController] sendMediaToGoWa FAILED: HTTP {$status} — " . json_encode($body));

                return [
                    'message_id' => null,
                    'error' => $errorMsg,
                    'httpStatus' => $status,
                ];
            }

            $body = $response->json();

            Log::info('[MessageController] sendMediaToGoWa SUCCESS: ' . json_encode($body));

            return [
                'message_id' => $body['results']['message_id'] ?? null,
                'error' => null,
                'httpStatus' => $status,
            ];
        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('[MessageController] sendMediaToGoWa connection error: ' . $e->getMessage());

            return [
                'message_id' => null,
                'error' => 'connection_error',
                'httpStatus' => 0,
                'exception' => $e->getMessage(),
            ];
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPER DETEKSI MEDIA
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ambil file fisik dari request (Skenario A).
     *
     * Cek field: file, image, document, media
     *
     * @param  Request $request
     * @return \Illuminate\Http\UploadedFile|null
     */
    private function getUploadedFile(Request $request)
    {
        $keys = ['file', 'image', 'document', 'media'];

        foreach ($keys as $key) {
            if ($request->hasFile($key)) {
                $file = $request->file($key);

                // Jika multiple files, ambil yang pertama
                if (is_array($file)) {
                    $file = $file[0] ?? null;
                }

                if ($file && $file->isValid()) {
                    return $file;
                }
            }
        }

        return null;
    }

    /**
     * Ambil URL string dari request (Skenario B).
     *
     * Cek field: file, url, file_url, image_url, media_url
     *
     * @param  Request $request
     * @return string|null
     */
    private function getMediaUrl(Request $request): ?string
    {
        $keys = ['file', 'url', 'file_url', 'image_url', 'media_url'];

        foreach ($keys as $key) {
            if ($request->filled($key)) {
                $val = $request->input($key);

                if (is_string($val) && filter_var(trim($val), FILTER_VALIDATE_URL)) {
                    return trim($val);
                }
            }
        }

        return null;
    }

    /**
     * Deteksi apakah URL menunjuk ke gambar.
     *
     * Strategi:
     *   1. Extension URL
     *   2. Image host / CDN patterns
     *
     * @param  string  $url
     * @return bool
     */
    private function isImageUrl(string $url): bool
    {
        if (empty($url)) {
            return false;
        }

        // 1. Extension
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        if (! empty($ext) && in_array($ext, self::IMAGE_EXTS, true)) {
            return true;
        }

        // 2. Image host patterns
        $urlLower = strtolower($url);

        foreach (self::IMAGE_HOST_PATTERNS as $pattern) {
            if (str_contains($urlLower, $pattern)) {
                return true;
            }
        }

        return false;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPER OPTION PARSING
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ekstrak opsi tambahan dari request.
     *
     * @param  Request  $request
     * @return array
     */
    private function extractOptions(Request $request): array
    {
        $options = [];

        foreach (['reply_message_id', 'mentions', 'duration', 'is_forwarded', 'view_once', 'compress'] as $key) {
            if ($request->filled($key)) {
                $val = $request->input($key);

                // mentions: split comma-separated string to array
                if ($key === 'mentions' && is_string($val)) {
                    $val = array_map('trim', explode(',', $val));
                }

                // boolean fields
                if (in_array($key, ['is_forwarded', 'view_once', 'compress'], true)) {
                    $val = filter_var($val, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $val;
                }

                // duration: cast to int
                if ($key === 'duration') {
                    $val = (int) $val;
                }

                $options[$key] = $val;
            }
        }

        return $options;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // RESPONSE HELPERS  (identik format Whacenter)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Response sukses — format Whacenter.
     */
    private function successResponse(?string $messageId): \Illuminate\Http\JsonResponse
    {
        $id = $messageId ?? random_int(100000000, 999999999);

        return response()->json([
            'status'  => true,
            'message' => 'message sent',
            'data'    => ['id' => $id],
        ]);
    }

    /**
     * Response gagal — format Whacenter.
     */
    private function errorResponse(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => 'device not connected or not found',
            'data'    => [],
        ]);
    }
}
