<?php

namespace App\Http\Controllers;

use App\Helpers\PhoneNormalizer;
use App\Services\GoWaApiService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Laravel 12 Adapter — Whacenter API Wrapper ke GoWA
 *
 * REQUEST YANG DITERIMA (dari sistem lama / Postman):
 *   - JSON body:          { device_id, number, message, file }
 *   - multipart/form-data (Postman): device_id, number, message, file=url-atau-binary
 *
 * LARAVEL $request->input(...) SECARA OTOMATIS MEMBACA:
 *   - JSON body field         → WORK
 *   - multipart/form-data     → WORK
 *
 * ALUR PENGIRIMAN:
 *   file = null / kosong  → /send/message  (teks saja)
 *   file = gambar URL     → /send/image    (image_url + caption)
 *   file = dokumen URL    → /send/file     (file_url, tanpa caption)
 *   file = upload fisik   → /send/image   (binary via attach())
 *                           → /send/file   (binary via attach())
 *
 * RESPONSE: selalu format Whacenter
 *   Sukses: { status: true,  message: "message sent", data: { id: ... } }
 *   Gagal:  { status: false, message: "device not connected or not found", data: [] }
 */
class MessageController extends Controller
{
    private GoWaApiService $gowa;

    /** Ekstensi gambar. */
    private const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'webp'];

    /** Pattern host/path yang menandakan image. */
    private const IMAGE_HOST_PATTERNS = [
        'i.ibb.co', 'imgur.com', 'imgbb.com', 'cloudinary.com',
        'prnt.sc', 'prntscr.com', 'laptop-media.net',
        'media.', 'images.', 'img.',
    ];

    public function __construct(GoWaApiService $gowa)
    {
        $this->gowa = $gowa;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // ENTRY POINT
    // ─────────────────────────────────────────────────────────────────────────

    public function send(Request $request): \Illuminate\Http\JsonResponse
    {
        // 1. device_id
        $deviceId = $request->input('device_id');

        if (empty(trim($deviceId ?? ''))) {
            Log::warning('[MessageController] send: missing device_id');

            return $this->fail();
        }

        // 2. Nomor tujuan
        $rawPhone = $request->input('number') ?? $request->input('phone');

        if (empty(trim($rawPhone ?? ''))) {
            Log::warning('[MessageController] send: missing number/phone');

            return $this->fail();
        }

        $normalized = PhoneNormalizer::normalize($rawPhone);

        if (empty($normalized)) {
            Log::warning("[MessageController] send: invalid phone '{$rawPhone}'");

            return $this->fail();
        }

        $jid = $normalized . '@s.whatsapp.net';

        // 3. Pesan & caption
        $message  = trim($request->input('message') ?? '');
        $caption  = trim($request->input('caption') ?? $message);

        // 4. Deteksi media
        $uploadedFile = $this->fileFromRequest($request);
        $urlString    = $this->urlFromRequest($request);
        $hasFile      = $uploadedFile !== null || ! empty($urlString);

        Log::info('[MessageController] send: device=' . $deviceId
            . ' jid=' . $jid
            . ' message_len=' . strlen($message)
            . ' uploaded=' . ($uploadedFile ? $uploadedFile->getClientOriginalName() : 'null')
            . ' url=' . ($urlString ?: 'null')
            . ' has_file=' . ($hasFile ? 'true' : 'false'));

        // 5. Routing
        if (! $hasFile) {
            return $this->sendText($deviceId, $jid, $message);
        }

        if ($uploadedFile !== null) {
            return $this->sendPhysicalFile($deviceId, $jid, $message, $uploadedFile);
        }

        return $this->sendUrlFile($deviceId, $jid, $message, $caption, $urlString);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HANDLER: TEKS SAJA
    // ─────────────────────────────────────────────────────────────────────────

    private function sendText(string $deviceId, string $jid, string $message): \Illuminate\Http\JsonResponse
    {
        $result = $this->gowa->sendMessage($deviceId, $jid, $message);

        Log::info('[MessageController] sendText result: error=' . ($result['error'] ?? 'null')
            . ' msg_id=' . ($result['message_id'] ?? 'null'));

        return $this->map($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HANDLER: URL STRING
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Kirim file via URL string.
     *
     *   .pdf / dokumen  → /send/file  (file_url, tanpa caption)
     *   gambar          → /send/image  (image_url + caption)
     */
    private function sendUrlFile(
        string $deviceId,
        string $jid,
        string $message,
        string $caption,
        string $urlString
    ): \Illuminate\Http\JsonResponse {
        $type = $this->detectType($urlString);

        Log::info('[MessageController] sendUrlFile: url=' . $urlString . ' type=' . $type);

        if ($type === 'pdf') {
            // Dokumen PDF: kirim tanpa caption
            $result = $this->gowa->sendFile($deviceId, $jid, $urlString, null);

            Log::info('[MessageController] sendUrlFile PDF result: error=' . ($result['error'] ?? 'null')
                . ' msg_id=' . ($result['message_id'] ?? 'null'));

            return $this->map($result);
        }

        // Gambar: kirim dengan caption
        $result = $this->gowa->sendImage($deviceId, $jid, $urlString, $caption);

        Log::info('[MessageController] sendUrlFile image result: error=' . ($result['error'] ?? 'null')
            . ' msg_id=' . ($result['message_id'] ?? 'null'));

        return $this->map($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HANDLER: FILE FISIK (UPLOAD BINARY)
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Kirim file fisik via Http::attach().
     *
     *   gambar  → /send/image  (binary + caption)
     *   dokumen → /send/file   (binary, tanpa caption)
     */
    private function sendPhysicalFile(
        string $deviceId,
        string $jid,
        string $message,
        UploadedFile $file
    ): \Illuminate\Http\JsonResponse {
        $ext    = strtolower($file->getClientOriginalExtension());
        $isImage = in_array($ext, self::IMAGE_EXTS, true);

        Log::info('[MessageController] sendPhysicalFile: name=' . $file->getClientOriginalName()
            . ' ext=' . $ext . ' isImage=' . ($isImage ? 'true' : 'false'));

        $endpoint = $isImage ? '/send/image' : '/send/file';
        $fileKey  = $isImage ? 'image'        : 'file';
        $baseUrl  = config('gowa.base_url');
        $user     = config('gowa.basic_auth_user');
        $pass     = config('gowa.basic_auth_pass');

        try {
            $client = Http::timeout(60)
                ->acceptJson()
                ->withHeaders(['X-Device-Id' => $deviceId]);

            if (! empty($user)) {
                $client = $client->withBasicAuth($user, $pass);
            }

            $fields = array_filter([
                'phone'   => $jid,
                'caption' => $isImage ? $message : null,
            ], fn ($v) => $v !== null);

            $response = $client
                ->attach(
                    $fileKey,
                    file_get_contents($file->getRealPath()),
                    $file->getClientOriginalName()
                )
                ->post("{$baseUrl}{$endpoint}", $fields);

            $status = $response->status();

            Log::info('[MessageController] sendPhysicalFile HTTP ' . $status);

            if (! $response->successful()) {
                $body = $response->json();

                Log::error('[MessageController] sendPhysicalFile FAILED: HTTP ' . $status
                    . ' — ' . json_encode($body));

                return $this->fail();
            }

            $body = $response->json();

            Log::info('[MessageController] sendPhysicalFile SUCCESS: ' . json_encode($body));

            return $this->ok($body['results']['message_id'] ?? null);

        } catch (\Illuminate\Http\Client\RequestException $e) {
            Log::error('[MessageController] sendPhysicalFile connection error: ' . $e->getMessage());

            return $this->fail();
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPER: DETEKSI TIPE FILE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Deteksi apakah URL menunjuk ke gambar atau dokumen.
     *
     *   gambar → .jpg .jpeg .png .webp  ATAU  image host pattern
     *   dokumen → .pdf  ATAU  fallback
     */
    private function detectType(string $url): string
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        if (in_array($ext, self::IMAGE_EXTS, true)) {
            return 'image';
        }

        if ($ext === 'pdf') {
            return 'pdf';
        }

        // Image host pattern
        foreach (self::IMAGE_HOST_PATTERNS as $p) {
            if (str_contains(strtolower($url), $p)) {
                return 'image';
            }
        }

        return 'pdf'; // default ke dokumen
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPER: BACA REQUEST
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Ambil file fisik dari request.
     * Compatible: Postman form-data upload binary.
     *
     * @return UploadedFile|null
     */
    private function fileFromRequest(Request $request): ?UploadedFile
    {
        foreach (['file', 'image', 'document'] as $key) {
            if ($request->hasFile($key)) {
                $f = $request->file($key);

                if (is_array($f)) {
                    $f = $f[0] ?? null;
                }

                if ($f instanceof UploadedFile && $f->isValid()) {
                    return $f;
                }
            }
        }

        return null;
    }

    /**
     * Ambil URL string dari request.
     * Compatible: JSON body, Postman form-data field string.
     *
     * @return string|null
     */
    private function urlFromRequest(Request $request): ?string
    {
        foreach (['file', 'url', 'file_url', 'image_url'] as $key) {
            $val = $request->input($key);

            if (is_string($val) && trim($val) !== '' && filter_var(trim($val), FILTER_VALIDATE_URL)) {
                return trim($val);
            }
        }

        return null;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPER: RESPONSE
    // ─────────────────────────────────────────────────────────────────────────

    /** Map result GoWA ke format Whacenter. */
    private function map(array $result): \Illuminate\Http\JsonResponse
    {
        if ($result['error'] === null) {
            return $this->ok($result['message_id'] ?? null);
        }

        Log::error('[MessageController] GoWA error: ' . json_encode($result));

        return $this->fail();
    }

    /** Response sukses — format Whacenter. */
    private function ok(?string $messageId): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status'  => true,
            'message' => 'message sent',
            'data'    => ['id' => $messageId ?? random_int(100000000, 999999999)],
        ]);
    }

    /** Response gagal — format Whacenter. */
    private function fail(): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status'  => false,
            'message' => 'device not connected or not found',
            'data'    => [],
        ]);
    }
}
