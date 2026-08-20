<?php

use App\Http\Controllers\DeviceController;
use App\Http\Controllers\MessageController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - Whacenter API Adapter
|--------------------------------------------------------------------------
|
| Endpoint ini adalah proxy/peladapter dari API Whacenter lama ke server GoWA.
| Request dan Response diformat identik dengan API Whacenter sehingga
| client yang sudah ada tidak perlu perubahan payload.
|
| Stateless & No Database:
| - Tidak menggunakan session, cookie, atau database
| - Semua device state dikelola oleh server GoWA di VPS
|
*/

/*
|--------------------------------------------------------------------------
| Device Management Endpoints
|--------------------------------------------------------------------------
*/

// GET/POST /api/statusDevice
Route::match(['get', 'post'], '/statusDevice', [DeviceController::class, 'statusDevice']);

// GET/POST /api/relogDevice
Route::match(['get', 'post'], '/relogDevice', [DeviceController::class, 'relogDevice']);

// GET /api/qr
Route::get('/qr', [DeviceController::class, 'qr']);

/*
|--------------------------------------------------------------------------
| Message Sending Endpoints
|--------------------------------------------------------------------------
*/

// POST/GET /api/send (text, image, file)
Route::match(['get', 'post'], '/send', [MessageController::class, 'send']);

/*
|--------------------------------------------------------------------------
| Health Check
|--------------------------------------------------------------------------
*/

Route::get('/health', function () {
    return response()->json([
        'status' => true,
        'message' => 'ICX WhatsApp Gateway Adapter is running',
        'data' => [],
    ]);
});
