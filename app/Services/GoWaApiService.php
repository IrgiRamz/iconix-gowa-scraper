<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Client\RequestException;

/**
 * Service untuk berkomunikasi dengan server GoWA.
 *
 * Menangani:
 * - Autentikasi Basic Auth
 * - Header X-Device-Id
 * - Error mapping
 * - Timeout handling
 */
class GoWaApiService
{
    private string $baseUrl;
    private string $user;
    private string $pass;
    private int $timeout;
    private int $connectTimeout;

    public function __construct()
    {
        $this->baseUrl = config('gowa.base_url', 'http://127.0.0.1:3000');
        $this->user = config('gowa.basic_auth_user', '');
        $this->pass = config('gowa.basic_auth_pass', '');
        $this->timeout = (int) config('gowa.timeout', 15);
        $this->connectTimeout = (int) config('gowa.connect_timeout', 5);
    }

    /**
     * HTTP client untuk request GET.
     * Hanya Accept header, TANPA Content-Type.
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    private function clientGet()
    {
        $client = Http::timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->acceptJson();

        if (! empty($this->user)) {
            $client = $client->withBasicAuth($this->user, $this->pass);
        }

        return $client;
    }

    /**
     * HTTP client untuk request POST JSON-only (tanpa file upload).
     * Content-Type: application/json.
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    private function clientJson()
    {
        $client = Http::timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->acceptJson()
            ->withHeaders([
                'Content-Type' => 'application/json',
            ]);

        if (! empty($this->user)) {
            $client = $client->withBasicAuth($this->user, $this->pass);
        }

        return $client;
    }

    /**
     * HTTP client untuk request multipart (file upload / URL forwarding).
     * JANGAN set Content-Type manual — biarkan Laravel/Guzzle auto-detect.
     * Saat body mengandung array/string untuk form fields, Laravel auto-detect
     * sebagai multipart dan set boundary yang benar.
     *
     * @return \Illuminate\Http\Client\PendingRequest
     */
    private function clientMultipart()
    {
        $client = Http::timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->acceptJson();
            // TIDAK ada withHeaders('Content-Type') di sini
            // Laravel/Guzzle auto-detect: array -> multipart/form-data

        if (! empty($this->user)) {
            $client = $client->withBasicAuth($this->user, $this->pass);
        }

        return $client;
    }

    /**
     * Get device info dari GoWA.
     *
     * GoWA response format:
     * {
     *   "code": "SUCCESS",
     *   "message": "Device info",
     *   "results": {
     *     "id": "...",
     *     "display_name": "...",
     *     "state": "logged_in" | "disconnected",
     *     "jid": "628xxx@s.whatsapp.net",
     *     "created_at": "..."
     *   }
     * }
     *
     * @param  string  $deviceId
     * @return array{results: array|null, error: string|null, httpStatus: int, rawBody: array|null}
     */
    public function getDevice(string $deviceId): array
    {
        $url = "{$this->baseUrl}/devices/{$deviceId}";

        Log::info("[GoWaApiService] GET {$url}");

        try {
            $response = $this->clientGet()->get($url);

            $rawBody = $response->json();

            Log::info("[GoWaApiService] Raw response: " . json_encode($rawBody));

            if (! $response->successful()) {
                $message = $rawBody['message'] ?? 'Unknown error';

                if (str_contains(strtolower($message), 'not found')) {
                    Log::warning("[GoWaApiService] Device not found: {$deviceId}");

                    return [
                        'results' => null,
                        'error' => 'not_found',
                        'httpStatus' => $response->status(),
                        'rawBody' => $rawBody,
                    ];
                }

                Log::error("[GoWaApiService] GoWA error {$response->status()}: {$message}");

                return [
                    'results' => null,
                    'error' => $message,
                    'httpStatus' => $response->status(),
                    'rawBody' => $rawBody,
                ];
            }

            $results = $rawBody['results'] ?? null;
            $state = $results['state'] ?? 'disconnected';

            Log::info("[GoWaApiService] Device {$deviceId} state: {$state}");

            return [
                'results' => $results,
                'error' => null,
                'httpStatus' => $response->status(),
                'rawBody' => $rawBody,
            ];
        } catch (RequestException $e) {
            Log::error("[GoWaApiService] Connection error to {$url}: " . $e->getMessage());

            return [
                'results' => null,
                'error' => 'connection_error',
                'httpStatus' => 0,
                'exception' => $e->getMessage(),
                'rawBody' => null,
            ];
        }
    }

    /**
     * Get QR code link dari GoWA (login endpoint).
     *
     * @param  string  $deviceId
     * @return array{qr_link: string|null, error: string|null, httpStatus: int, rawBody: array|null}
     */
    public function getQrLink(string $deviceId): array
    {
        $url = "{$this->baseUrl}/devices/{$deviceId}/login";

        Log::info("[GoWaApiService] GET {$url}");

        try {
            $response = $this->clientGet()->get($url);
            $rawBody = $response->json();

            Log::info("[GoWaApiService] QR response: " . json_encode($rawBody));

            if (! $response->successful()) {
                $message = $rawBody['message'] ?? 'Unknown error';

                if (str_contains(strtolower($message), 'not found')) {
                    return [
                        'qr_link' => null,
                        'error' => 'not_found',
                        'httpStatus' => $response->status(),
                        'rawBody' => $rawBody,
                    ];
                }

                return [
                    'qr_link' => null,
                    'error' => $message,
                    'httpStatus' => $response->status(),
                    'rawBody' => $rawBody,
                ];
            }

            if (($rawBody['code'] ?? '') === 'ALREADY_LOGGED_IN') {
                return [
                    'qr_link' => null,
                    'error' => 'already_logged_in',
                    'httpStatus' => $response->status(),
                    'rawBody' => $rawBody,
                ];
            }

            $qrLink = $rawBody['results']['qr_link'] ?? null;

            Log::info("[GoWaApiService] QR link for {$deviceId}: " . ($qrLink ? '(present)' : 'null'));

            return [
                'qr_link' => $qrLink,
                'error' => null,
                'httpStatus' => $response->status(),
                'rawBody' => $rawBody,
            ];
        } catch (RequestException $e) {
            Log::error("[GoWaApiService] Connection error to {$url}: " . $e->getMessage());

            return [
                'qr_link' => null,
                'error' => 'connection_error',
                'httpStatus' => 0,
                'exception' => $e->getMessage(),
                'rawBody' => null,
            ];
        }
    }

    /**
     * Reconnect device.
     *
     * @param  string  $deviceId
     * @return array{success: bool, error: string|null, httpStatus: int}
     */
    public function reconnect(string $deviceId): array
    {
        $url = "{$this->baseUrl}/devices/{$deviceId}/reconnect";

        Log::info("[GoWaApiService] POST {$url}");

        try {
            $response = $this->clientJson()->post($url);

            if ($response->successful()) {
                Log::info("[GoWaApiService] Reconnect success for {$deviceId}");

                return [
                    'success' => true,
                    'error' => null,
                    'httpStatus' => $response->status(),
                ];
            }

            $body = $response->json();
            $message = $body['message'] ?? 'Unknown error';

            if (str_contains(strtolower($message), 'not found')) {
                return [
                    'success' => false,
                    'error' => 'not_found',
                    'httpStatus' => $response->status(),
                ];
            }

            if (str_contains(strtolower($message), 'not logged in') ||
                str_contains(strtolower($message), 'is not logged in')) {
                return [
                    'success' => false,
                    'error' => 'not_logged_in',
                    'httpStatus' => $response->status(),
                ];
            }

            Log::error("[GoWaApiService] Reconnect failed for {$deviceId}: {$message}");

            return [
                'success' => false,
                'error' => $message,
                'httpStatus' => $response->status(),
            ];
        } catch (RequestException $e) {
            Log::error("[GoWaApiService] Connection error reconnect {$deviceId}: " . $e->getMessage());

            return [
                'success' => false,
                'error' => 'connection_error',
                'httpStatus' => 0,
                'exception' => $e->getMessage(),
            ];
        }
    }

    /**
     * Kirim pesan text ke GoWA.
     * Gunakan JSON body.
     *
     * @param  string  $deviceId
     * @param  string  $phone   Format: 628xxxx@s.whatsapp.net
     * @param  string  $message
     * @param  array   $options
     * @return array{message_id: string|null, error: string|null, httpStatus: int}
     */
    public function sendMessage(string $deviceId, string $phone, string $message, array $options = []): array
    {
        $url = "{$this->baseUrl}/send/message";

        Log::info("[GoWaApiService] POST {$url} device={$deviceId} phone={$phone}");

        try {
            $response = $this->clientJson()
                ->withHeaders([
                    'X-Device-Id' => $deviceId,
                ])
                ->post($url, array_filter([
                    'phone' => $phone,
                    'message' => $message,
                    'reply_message_id' => $options['reply_message_id'] ?? null,
                    'mentions' => $options['mentions'] ?? null,
                    'duration' => $options['duration'] ?? null,
                    'is_forwarded' => $options['is_forwarded'] ?? null,
                ], fn ($v) => $v !== null));

            if ($response->successful()) {
                $body = $response->json();

                Log::info("[GoWaApiService] Message sent: " . json_encode($body));

                return [
                    'message_id' => $body['results']['message_id'] ?? null,
                    'error' => null,
                    'httpStatus' => $response->status(),
                ];
            }

            $body = $response->json();
            $message = $body['message'] ?? 'Unknown error';

            Log::warning("[GoWaApiService] Send message failed: HTTP {$response->status()} - {$message}");

            return [
                'message_id' => null,
                'error' => $message,
                'httpStatus' => $response->status(),
            ];
        } catch (RequestException $e) {
            Log::error("[GoWaApiService] Connection error send message: " . $e->getMessage());

            return [
                'message_id' => null,
                'error' => 'connection_error',
                'httpStatus' => 0,
                'exception' => $e->getMessage(),
            ];
        }
    }

    /**
     * Kirim gambar via GoWA.
     *
     * Whacenter forward: parameter 'file' berisi URL string.
     * GoWA menerima: 'image_url' sebagai form field string.
     *
     * Gunakan clientMultipart() — tidak set Content-Type manual.
     * Laravel auto-detect body sebagai multipart/form-data.
     *
     * @param  string  $deviceId
     * @param  string  $phone
     * @param  string|null  $imageUrl   URL gambar dari Whacenter
     * @param  string|null  $caption
     * @param  array   $options
     * @return array{message_id: string|null, error: string|null, httpStatus: int}
     */
    public function sendImage(string $deviceId, string $phone, ?string $imageUrl = null, ?string $caption = null, array $options = []): array
    {
        $url = "{$this->baseUrl}/send/image";

        Log::info("[GoWaApiService] POST {$url} device={$deviceId} phone={$phone} imageUrl={$imageUrl}");

        try {
            // Bangun body multipart
            $parts = array_filter([
                'phone' => $phone,
                'caption' => $caption,
                'image_url' => $imageUrl,
                'reply_message_id' => $options['reply_message_id'] ?? null,
                'view_once' => $options['view_once'] ?? null,
                'compress' => $options['compress'] ?? null,
                'duration' => $options['duration'] ?? null,
                'is_forwarded' => $options['is_forwarded'] ?? null,
            ], fn ($v) => $v !== null);

            Log::info("[GoWaApiService] sendImage body keys: " . implode(', ', array_keys($parts)));

            $response = $this->clientMultipart()
                ->withHeaders([
                    'X-Device-Id' => $deviceId,
                ])
                ->timeout(60)
                ->post($url, $parts);

            $statusCode = $response->status();

            Log::info("[GoWaApiService] sendImage HTTP {$statusCode}");

            if (! $response->successful()) {
                $body = $response->json();
                $errorMsg = $body['message'] ?? "HTTP {$statusCode}";

                Log::error("[GoWaApiService] sendImage FAILED: HTTP {$statusCode} - " . json_encode($body));

                return [
                    'message_id' => null,
                    'error' => $errorMsg,
                    'httpStatus' => $statusCode,
                ];
            }

            $body = $response->json();

            Log::info("[GoWaApiService] sendImage SUCCESS: " . json_encode($body));

            return [
                'message_id' => $body['results']['message_id'] ?? null,
                'error' => null,
                'httpStatus' => $statusCode,
            ];
        } catch (RequestException $e) {
            Log::error("[GoWaApiService] sendImage connection exception: " . $e->getMessage());

            return [
                'message_id' => null,
                'error' => 'connection_error',
                'httpStatus' => 0,
                'exception' => $e->getMessage(),
            ];
        }
    }

    /**
     * Kirim file/dokumen via GoWA.
     *
     * Whacenter forward: parameter 'file' berisi URL string.
     * GoWA menerima: 'file_url' sebagai form field string.
     *
     * @param  string  $deviceId
     * @param  string  $phone
     * @param  string|null  $fileUrl
     * @param  string|null  $caption
     * @param  array   $options
     * @return array{message_id: string|null, error: string|null, httpStatus: int}
     */
    public function sendFile(string $deviceId, string $phone, ?string $fileUrl = null, ?string $caption = null, array $options = []): array
    {
        $url = "{$this->baseUrl}/send/file";

        Log::info("[GoWaApiService] POST {$url} device={$deviceId} phone={$phone} fileUrl={$fileUrl}");

        try {
            $parts = array_filter([
                'phone' => $phone,
                'caption' => $caption,
                'file_url' => $fileUrl,
                'reply_message_id' => $options['reply_message_id'] ?? null,
                'duration' => $options['duration'] ?? null,
                'is_forwarded' => $options['is_forwarded'] ?? null,
            ], fn ($v) => $v !== null);

            Log::info("[GoWaApiService] sendFile body keys: " . implode(', ', array_keys($parts)));

            $response = $this->clientMultipart()
                ->withHeaders([
                    'X-Device-Id' => $deviceId,
                ])
                ->timeout(60)
                ->post($url, $parts);

            $statusCode = $response->status();

            Log::info("[GoWaApiService] sendFile HTTP {$statusCode}");

            if (! $response->successful()) {
                $body = $response->json();
                $errorMsg = $body['message'] ?? "HTTP {$statusCode}";

                Log::error("[GoWaApiService] sendFile FAILED: HTTP {$statusCode} - " . json_encode($body));

                return [
                    'message_id' => null,
                    'error' => $errorMsg,
                    'httpStatus' => $statusCode,
                ];
            }

            $body = $response->json();

            Log::info("[GoWaApiService] sendFile SUCCESS: " . json_encode($body));

            return [
                'message_id' => $body['results']['message_id'] ?? null,
                'error' => null,
                'httpStatus' => $statusCode,
            ];
        } catch (RequestException $e) {
            Log::error("[GoWaApiService] sendFile connection exception: " . $e->getMessage());

            return [
                'message_id' => null,
                'error' => 'connection_error',
                'httpStatus' => 0,
                'exception' => $e->getMessage(),
            ];
        }
    }

    /**
     * Ekstrak error message dari response.
     */
    private function extractErrorMessage($response): string
    {
        if ($response instanceof RequestException) {
            try {
                $body = $response->response?->json();
            } catch (\Throwable) {
                return 'request_failed';
            }
        } else {
            $body = $response->json();
        }

        $message = $body['message'] ?? $body['error'] ?? 'Unknown error';

        if (str_contains(strtolower($message), 'not found')) {
            return 'not_found';
        }

        if (str_contains(strtolower($message), 'not logged in') ||
            str_contains(strtolower($message), 'disconnected')) {
            return 'disconnected';
        }

        return $message;
    }

    /**
     * Cek apakah error adalah "device not found".
     */
    public static function isNotFoundError(?string $error): bool
    {
        if ($error === null) {
            return false;
        }

        return $error === 'not_found'
            || str_contains(strtolower($error), 'not found')
            || str_contains(strtolower($error), 'not logged in');
    }

    /**
     * Cek apakah error adalah "device disconnected".
     */
    public static function isDisconnectedError(?string $error): bool
    {
        if ($error === null) {
            return false;
        }

        return $error === 'disconnected'
            || str_contains(strtolower($error), 'disconnected')
            || str_contains(strtolower($error), 'not connected');
    }
}
