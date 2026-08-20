<?php

namespace App\Http\Controllers;

use App\Helpers\PhoneNormalizer;
use App\Http\Requests\SendMessageRequest;
use App\Services\GoWaApiService;
use Illuminate\Http\Request;

/**
 * Controller untuk endpoint Whacenter:
 * - POST/GET /api/send
 *
 * Mendukung:
 * - Pesan teks saja
 * - Pesan dengan gambar (via URL)
 * - Pesan dengan file/dokumen (via URL)
 */
class MessageController extends Controller
{
    private GoWaApiService $gowa;

    /**
     * Ekstensi gambar yang dikenali.
     */
    private const IMAGE_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg',
    ];

    public function __construct(GoWaApiService $gowa)
    {
        $this->gowa = $gowa;
    }

    /**
     * POST/GET /api/send
     *
     * Mapping ke GoWA:
     * - Text only       -> POST /send/message
     * - With file (gambar) -> POST /send/image
     * - With file (lain)   -> POST /send/file
     */
    public function send(Request $request): \Illuminate\Http\JsonResponse
    {
        // Ambil device_id
        $deviceId = $request->input('device_id');

        if (empty($deviceId)) {
            return $this->errorResponse('device not connected or not found');
        }

        // Ambil nomor tujuan (accepts 'number' or 'phone')
        $rawPhone = $request->input('number') ?? $request->input('phone');

        if (empty($rawPhone)) {
            return $this->errorResponse('device not connected or not found');
        }

        // Normalisasi nomor HP
        $normalizedPhone = PhoneNormalizer::normalize($rawPhone);

        if (empty($normalizedPhone)) {
            return $this->errorResponse('device not connected or not found');
        }

        // Format JID untuk GoWA
        $jid = $normalizedPhone . '@s.whatsapp.net';

        // Cek apakah ada file
        $fileUrl = $request->input('file');
        $message = $request->input('message') ?? '';
        $caption = $message;

        if (! empty($fileUrl)) {
            // Ada file -> kirim sebagai gambar atau dokumen
            return $this->sendWithFile($deviceId, $jid, $fileUrl, $caption);
        }

        // Text only
        return $this->sendText($deviceId, $jid, $message, $request);
    }

    /**
     * Kirim pesan teks.
     *
     * @param  string  $deviceId
     * @param  string  $jid     Format: 628xxx@s.whatsapp.net
     * @param  string  $message
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    private function sendText(string $deviceId, string $jid, string $message, Request $request): \Illuminate\Http\JsonResponse
    {
        // Ekstrak opsi tambahan
        $options = $this->extractOptions($request);

        $result = $this->gowa->sendMessage($deviceId, $jid, $message, $options);

        if ($result['error'] === null) {
            // Generate pseudo-random id seperti Whacenter
            $messageId = $result['message_id'] ?? $this->generateMessageId();

            return response()->json([
                'status' => true,
                'message' => 'message sent',
                'data' => [
                    'id' => $messageId,
                ],
            ]);
        }

        return $this->mapErrorResponse($result['error'], $result['httpStatus']);
    }

    /**
     * Kirim pesan dengan gambar atau file.
     *
     * @param  string  $deviceId
     * @param  string  $jid
     * @param  string  $fileUrl
     * @param  string  $caption
     * @return \Illuminate\Http\JsonResponse
     */
    private function sendWithFile(string $deviceId, string $jid, string $fileUrl, string $caption): \Illuminate\Http\JsonResponse
    {
        // Deteksi tipe file
        $isImage = $this->isImageUrl($fileUrl);

        if ($isImage) {
            $result = $this->gowa->sendImage($deviceId, $jid, $fileUrl, $caption);
        } else {
            $result = $this->gowa->sendFile($deviceId, $jid, $fileUrl, $caption);
        }

        if ($result['error'] === null) {
            $messageId = $result['message_id'] ?? $this->generateMessageId();

            return response()->json([
                'status' => true,
                'message' => 'message sent',
                'data' => [
                    'id' => $messageId,
                ],
            ]);
        }

        return $this->mapErrorResponse($result['error'], $result['httpStatus']);
    }

    /**
     * Deteksi apakah URL adalah gambar.
     *
     * @param  string  $url
     * @return bool
     */
    private function isImageUrl(string $url): bool
    {
        $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));

        return in_array($ext, self::IMAGE_EXTENSIONS, true);
    }

    /**
     * Extract opsi tambahan dari request.
     *
     * @param  Request  $request
     * @return array
     */
    private function extractOptions(Request $request): array
    {
        $options = [];

        if ($request->has('reply_message_id')) {
            $options['reply_message_id'] = $request->input('reply_message_id');
        }

        if ($request->has('mentions')) {
            $mentions = $request->input('mentions');
            if (is_string($mentions)) {
                $mentions = explode(',', $mentions);
            }
            $options['mentions'] = $mentions;
        }

        if ($request->has('duration')) {
            $options['duration'] = (int) $request->input('duration');
        }

        if ($request->has('is_forwarded')) {
            $options['is_forwarded'] = filter_var(
                $request->input('is_forwarded'),
                FILTER_VALIDATE_BOOLEAN
            );
        }

        if ($request->has('view_once')) {
            $options['view_once'] = filter_var(
                $request->input('view_once'),
                FILTER_VALIDATE_BOOLEAN
            );
        }

        if ($request->has('compress')) {
            $options['compress'] = filter_var(
                $request->input('compress'),
                FILTER_VALIDATE_BOOLEAN
            );
        }

        return $options;
    }

    /**
     * Map error dari GoWA ke response Whacenter.
     *
     * @param  string|null  $error
     * @param  int  $httpStatus
     * @return \Illuminate\Http\JsonResponse
     */
    private function mapErrorResponse(?string $error, int $httpStatus): \Illuminate\Http\JsonResponse
    {
        // not_found, disconnected, connection_error -> sama response
        if (GoWaApiService::isNotFoundError($error)
            || GoWaApiService::isDisconnectedError($error)
            || $error === 'connection_error') {
            return $this->errorResponse('device not connected or not found');
        }

        // Default error
        return $this->errorResponse('device not connected or not found');
    }

    /**
     * Generate error response standar Whacenter.
     *
     * @param  string  $message
     * @return \Illuminate\Http\JsonResponse
     */
    private function errorResponse(string $message): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'status' => false,
            'message' => $message,
            'data' => [],
        ], 200);
    }

    /**
     * Generate pseudo message ID (tanpa dependensi WhatsApp).
     *
     * @return int
     */
    private function generateMessageId(): int
    {
        return random_int(100000000, 999999999);
    }
}
