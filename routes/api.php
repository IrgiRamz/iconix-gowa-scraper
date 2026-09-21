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

// GET/POST /api/createDevice
Route::match(['post'], '/createDevice', [DeviceController::class, 'createDevice']);

// GET/POST/DELETE /api/deleteDevice
Route::match(['delete'], '/deleteDevice', [DeviceController::class, 'deleteDevice']);


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



//Untuk dashboard cek ram,usage
Route::get('/widget-stats.js', function () {
    // 1. Baca Disk
    $diskTotal = disk_total_space('/');
    $diskFree = disk_free_space('/');
    $diskUsed = $diskTotal - $diskFree;

    $diskPct = round(($diskUsed / $diskTotal) * 100);
    $diskText = round($diskUsed / 1073741824, 2) . ' GB / ' . round($diskTotal / 1073741824, 2) . ' GB';

    // 2. Baca RAM
    $ramText = 'N/A';
    $ramPct = 0;
    if (function_exists('shell_exec')) {
        $free = shell_exec('free -m');
        if ($free) {
            $lines = explode("\n", trim($free));
            if (isset($lines[1])) {
                $mem = preg_split('/\s+/', $lines[1]);
                $totalMb = $mem[1] ?? 0;
                $usedMb = $mem[2] ?? 0;
                if ($totalMb > 0) {
                    $ramPct = round(($usedMb / $totalMb) * 100);
                    $ramText = round($usedMb / 1024, 2) . ' GB / ' . round($totalMb / 1024, 2) . ' GB';
                }
            }
        }
    }

    // 3. Rangkai CSS & HTML (JavaScript Code)
    $javascriptCode = "
        (function() {
            var targetDiv = document.getElementById('gowa-server-stats');
            if (!targetDiv) {
                console.error('Div gowa-server-stats tidak ditemukan');
                return;
            }

            // Inject CSS untuk mempercantik chart/bar
            var css = `
                .gowa-box { font-family: sans-serif; background: #fff; padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); max-width: 350px; }
                .gowa-title { font-size: 13px; font-weight: bold; color: #4b5563; margin-bottom: 5px; display: flex; justify-content: space-between; }
                .gowa-bar-bg { background: #e5e7eb; border-radius: 99px; height: 10px; width: 100%; margin-bottom: 12px; overflow: hidden; }
                .gowa-bar-fill { height: 100%; border-radius: 99px; transition: width 0.8s ease; }
                .gowa-disk-fill { background: #3b82f6; width: {$diskPct}%; } /* Warna Biru */
                .gowa-ram-fill { background: #10b981; width: {$ramPct}%; } /* Warna Hijau */
            `;
            var style = document.createElement('style');
            style.innerHTML = css;
            document.head.appendChild(style);

            // Render HTML ke dalam target Div
            targetDiv.innerHTML = `
                <div class=\"gowa-box\">
                    <div class=\"gowa-title\">
                        <span>Storage</span>
                        <span>{$diskText} ({$diskPct}%)</span>
                    </div>
                    <div class=\"gowa-bar-bg\">
                        <div class=\"gowa-bar-fill gowa-disk-fill\"></div>
                    </div>

                    <div class=\"gowa-title\">
                        <span>RAM Usage</span>
                        <span>{$ramText} ({$ramPct}%)</span>
                    </div>
                    <div class=\"gowa-bar-bg\">
                        <div class=\"gowa-bar-fill gowa-ram-fill\"></div>
                    </div>
                </div>
            `;
        })();
    ";

    return response($javascriptCode)->header('Content-Type', 'application/javascript');
});
///END