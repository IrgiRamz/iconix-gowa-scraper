<?php

namespace App\Http\Controllers;

use App\Helpers\QrFallbackGenerator;
use App\Http\Requests\DeviceStatusRequest;
use App\Services\GoWaApiService;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Controller untuk endpoint Whacenter:
 * - GET/POST /api/statusDevice
 * - GET/POST /api/relogDevice
 * - GET /api/qr
 */
class DeviceController extends Controller
{
    private GoWaApiService $gowa;

    public function __construct(GoWaApiService $gowa)
    {
        $this->gowa = $gowa;
    }

    /**
     * GET/POST /api/statusDevice
     *
     * Mapping ke GoWA: GET /devices/{device_id}
     *
     * GoWA response structure:
     * {
     *   "code": "SUCCESS",
     *   "message": "Device info",
     *   "results": {
     *     "id": "...",
     *     "display_name": "...",
     *     "state": "logged_in" | "disconnected",
     *     "jid": "628xxx@s.whatsapp.net"   // <-- hanya ada saat logged_in
     *   }
     * }
     */
    public function statusDevice(DeviceStatusRequest $request): \Illuminate\Http\JsonResponse
    {
        $deviceId = $request->validated('device_id');

        if (empty($deviceId)) {
            $deviceId = $request->input('device_id');
        }

        Log::info("[DeviceController] statusDevice called with device_id={$deviceId}");

        $result = $this->gowa->getDevice($deviceId);

        Log::info("[DeviceController] getDevice result: " . json_encode([
            'error' => $result['error'],
            'httpStatus' => $result['httpStatus'],
            'results' => $result['results'],
        ]));

        // Device tidak ditemukan
        if ($result['error'] === 'not_found') {
            Log::warning("[DeviceController] Device not found: {$deviceId}");

            return response()->json([
                'status' => false,
                'message' => 'device not connected or not found',
                'data' => [],
            ], 200);
        }

        // Koneksi gagal / error server
        if ($result['error'] === 'connection_error') {
            Log::error("[DeviceController] Connection error to GoWA: " . ($result['exception'] ?? ''));

            return response()->json([
                'status' => false,
                'message' => 'device not connected or not found',
                'data' => [],
            ], 200);
        }

        // ──────────────────────────────────────────────────────────────
        // FIX: Key yang benar adalah $result['results'], BUKAN $result['data']
        // GoWA mengembalikan: { code, message, results: { state, jid, ... } }
        // ──────────────────────────────────────────────────────────────
        $results = $result['results'] ?? [];
        $state = $results['state'] ?? 'disconnected';
        $jid = $results['jid'] ?? null;
        $displayName = $results['display_name'] ?? '';
        $id = $results['id'] ?? $deviceId;

        Log::info("[DeviceController] Parsed state={$state}, jid={$jid}, displayName={$displayName}");

        if ($state === 'logged_in') {
            // Connected
            $nomor = '';
            if ($jid) {
                // JID format: 6288801008000@s.whatsapp.net
                $nomor = preg_replace('/@.+$/', '', $jid);
            }

            Log::info("[DeviceController] Device {$deviceId} -> CONNECTED, nomor={$nomor}");

            return response()->json([
                'status' => true,
                'message' => 'success get device status',
                'data' => [
                    'status' => 'CONNECTED',
                    'nomor' => $nomor,
                    'nama' => $displayName,
                    'qr' => 'done',
                ],
            ]);
        }

        // Disconnected / not connected
        Log::info("[DeviceController] Device {$deviceId} -> NOT CONNECTED");

        return response()->json([
            'status' => true,
            'message' => 'success get device status',
            'data' => [
                'status' => 'NOT CONNECTED',
                'nomor' => '',
                'nama' => $displayName,
                'qr' => 'timeout',
            ],
        ]);
    }

    /**
     * GET/POST /api/relogDevice
     *
     * Alur:
     * - Jika CONNECTED (logged_in) -> POST /devices/{device_id}/reconnect
     * - Jika NOT CONNECTED (disconnected) -> GET /devices/{device_id}/login
     * - Jika tidak ditemukan -> error
     */
    public function relogDevice(DeviceStatusRequest $request): \Illuminate\Http\JsonResponse
    {
        $deviceId = $request->validated('device_id');

        if (empty($deviceId)) {
            $deviceId = $request->input('device_id');
        }

        Log::info("[DeviceController] relogDevice called with device_id={$deviceId}");

        // Cek status device terlebih dahulu
        $deviceResult = $this->gowa->getDevice($deviceId);

        // Device tidak ditemukan
        if ($deviceResult['error'] === 'not_found') {
            Log::warning("[DeviceController] relogDevice: device not found {$deviceId}");

            return response()->json([
                'status' => false,
                'message' => 'device not connected or not found',
                'data' => [],
            ], 200);
        }

        // Koneksi gagal
        if ($deviceResult['error'] === 'connection_error') {
            Log::error("[DeviceController] relogDevice: connection error");

            return response()->json([
                'status' => false,
                'message' => 'device not connected or not found',
                'data' => [],
            ], 200);
        }

        // ──────────────────────────────────────────────────────────────
        // FIX: Gunakan $result['results'], BUKAN $result['data']
        // ──────────────────────────────────────────────────────────────
        $results = $deviceResult['results'] ?? [];
        $state = $results['state'] ?? 'disconnected';

        if ($state === 'logged_in') {
            Log::info("[DeviceController] relogDevice: device connected, reconnecting...");

            // CONNECTED -> reconnect
            $reconnectResult = $this->gowa->reconnect($deviceId);

            if ($reconnectResult['success']) {
                Log::info("[DeviceController] relogDevice: reconnect success");

                return response()->json([
                    'status' => true,
                    'message' => 'berhasil relog device',
                    'data' => [],
                ]);
            }

            // Reconnect gagal tapi device masih dikenal — tetap return berhasil
            Log::warning("[DeviceController] relogDevice: reconnect returned error but device known");

            return response()->json([
                'status' => true,
                'message' => 'berhasil relog device',
                'data' => [],
            ]);
        }

        // NOT CONNECTED -> trigger login untuk QR baru
        Log::info("[DeviceController] relogDevice: device disconnected, triggering login for new QR...");

        $qrResult = $this->gowa->getQrLink($deviceId);

        Log::info("[DeviceController] relogDevice: qrResult error=" . ($qrResult['error'] ?? 'null') . ", qr_link=" . ($qrResult['qr_link'] ?? 'null'));

        // Selalu return berhasil (perilaku sama dengan Whacenter)
        return response()->json([
            'status' => true,
            'message' => 'berhasil relog device',
            'data' => [],
        ]);
    }

    /**
     * GET /api/qr
     *
     * Alur:
     * - Jika NOT CONNECTED -> call /devices/{device_id}/login, ambil qr_link, proxy PNG
     * - Jika CONNECTED / NOT FOUND -> return fallback image "QR TIDAK TERSEDIA"
     */
    public function qr(Request $request)
    {
        $deviceId = $request->query('device_id');

        if (empty($deviceId)) {
            $deviceId = $request->input('device_id');
        }

        if (empty($deviceId)) {
            Log::warning("[DeviceController] qr: no device_id provided");

            return QrFallbackGenerator::generate();
        }

        Log::info("[DeviceController] qr called for device_id={$deviceId}");

        // Cek status device
        $deviceResult = $this->gowa->getDevice($deviceId);

        // ──────────────────────────────────────────────────────────────
        // FIX: Gunakan $result['results'], BUKAN $result['data']
        // ──────────────────────────────────────────────────────────────
        $results = $deviceResult['results'] ?? [];
        $state = $results['state'] ?? 'disconnected';

        Log::info("[DeviceController] qr: device state={$state}, error={$deviceResult['error']}");

        // Jika connected -> QR TIDAK TERSEDIA
        if ($state === 'logged_in') {
            Log::info("[DeviceController] qr: device connected, returning fallback QR");

            return QrFallbackGenerator::generate();
        }

        // NOT FOUND / connection error -> QR TIDAK TERSEDIA
        if ($deviceResult['error'] === 'not_found' || $deviceResult['error'] === 'connection_error') {
            Log::warning("[DeviceController] qr: error={$deviceResult['error']}, returning fallback");

            return QrFallbackGenerator::generate();
        }

        // NOT CONNECTED -> trigger login dan ambil qr_link
        $qrResult = $this->gowa->getQrLink($deviceId);

        Log::info("[DeviceController] qr: getQrLink result: " . json_encode([
            'error' => $qrResult['error'],
            'qr_link' => $qrResult['qr_link'] ? '***' : null,
        ]));

        if ($qrResult['error'] === 'not_found') {
            return QrFallbackGenerator::generate();
        }

        if ($qrResult['error'] === 'connection_error') {
            return QrFallbackGenerator::generate();
        }

        if (empty($qrResult['qr_link'])) {
            Log::warning("[DeviceController] qr: qr_link is empty, returning fallback");

            return QrFallbackGenerator::generate();
        }

        // Proxy gambar QR dari GoWA
        return $this->proxyQrImage($qrResult['qr_link']);
    }

    /**
     * Proxy gambar QR dari GoWA qr_link.
     *
     * @param  string  $qrLink
     * @return \Illuminate\Http\Response
     */
    private function proxyQrImage(string $qrLink)
    {
        Log::info("[DeviceController] proxyQrImage: fetching from {$qrLink}");

        try {
            $response = Http::timeout(10)
                ->get($qrLink);

            if ($response->successful()) {
                $contentType = $response->header('Content-Type', 'image/png');

                Log::info("[DeviceController] proxyQrImage: got {$contentType}, size=" . strlen($response->body()));

                return response($response->body(), 200, [
                    'Content-Type' => $contentType,
                    'Cache-Control' => 'no-store, no-cache, must-revalidate',
                ]);
            }

            Log::warning("[DeviceController] proxyQrImage: failed with status {$response->status()}");
        } catch (RequestException $e) {
            Log::error("[DeviceController] proxyQrImage: exception " . $e->getMessage());
        }

        return QrFallbackGenerator::generate();
    }
}
