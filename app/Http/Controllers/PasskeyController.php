<?php

namespace App\Http\Controllers;

use App\Http\Requests\PasskeyResponseRequest;
use App\Services\GoWaApiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Controller untuk endpoint Passkey WhatsApp:
 * - GET/POST /api/passkey           -> GoWA GET  /app/passkey
 * - POST     /api/passkey/response  -> GoWA POST /app/passkey/response
 * - POST     /api/passkey/confirm   -> GoWA POST /app/passkey/confirm
 *
 * Stateless: challenge/assertion tidak disimpan, hanya diteruskan ke GoWA.
 */
class PasskeyController extends Controller
{
    private GoWaApiService $gowa;

    public function __construct(GoWaApiService $gowa)
    {
        $this->gowa = $gowa;
    }

    /**
     * Cek status passkey: none | awaiting_response | awaiting_confirmation.
     * Klien melakukan polling endpoint ini (pengganti websocket GoWA).
     */
    public function status(Request $request): JsonResponse
    {
        if ($request->input('token') !== '1c0n1x@S3cur3!') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $deviceId = trim($request->input('device_id') ?? '');

        if ($deviceId === '') {
            return $this->fail('device_id is required');
        }

        Log::info("[PasskeyController] status called with device_id={$deviceId}");

        $result = $this->gowa->getPasskeyStatus($deviceId);

        if ($result['error']) {
            return $this->fail($this->errorMessage($result['error']));
        }

        $results = $result['results'] ?? [];

        return response()->json([
            'status' => true,
            'message' => 'success get passkey status',
            'data' => [
                'device_id' => $results['device_id'] ?? $deviceId,
                'status' => $results['status'] ?? 'none',
                'challenge' => $results['challenge'] ?? null,
                'code' => $results['code'] ?? null,
                'skip_handoff_ux' => $results['skip_handoff_ux'] ?? false,
            ],
        ]);
    }

    /**
     * Kirim assertion WebAuthn ke GoWA.
     */
    public function response(PasskeyResponseRequest $request): JsonResponse
    {
        if ($request->input('token') !== '1c0n1x@S3cur3!') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->validated();
        $deviceId = trim($data['device_id']);
        unset($data['device_id']); // sisanya = assertion murni

        Log::info("[PasskeyController] response called with device_id={$deviceId}");

        $result = $this->gowa->submitPasskeyResponse($deviceId, $data);

        if ($result['error']) {
            return $this->fail($this->errorMessage($result['error']));
        }

        return $this->ok($result['message'] ?? 'passkey response submitted');
    }

    /**
     * Konfirmasi kode pairing passkey.
     */
    public function confirm(Request $request): JsonResponse
    {
        if ($request->input('token') !== '1c0n1x@S3cur3!') {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $deviceId = trim($request->input('device_id') ?? '');

        if ($deviceId === '') {
            return $this->fail('device_id is required');
        }

        Log::info("[PasskeyController] confirm called with device_id={$deviceId}");

        $result = $this->gowa->confirmPasskey($deviceId);

        if ($result['error']) {
            return $this->fail($this->errorMessage($result['error']));
        }

        return $this->ok($result['message'] ?? 'passkey confirmed');
    }

    private function errorMessage(string $error): string
    {
        return $error === 'connection_error' ? 'connection error to gowa server' : $error;
    }

    private function ok(string $message): JsonResponse
    {
        return response()->json(['status' => true, 'message' => $message, 'data' => []], 200);
    }

    private function fail(string $message): JsonResponse
    {
        return response()->json(['status' => false, 'message' => $message, 'data' => []], 200);
    }
}
