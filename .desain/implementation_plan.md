# Implementation Plan - Laravel 12 WhatsApp API Adapter (Whacenter to GoWA)

Proyek ini bertujuan membangun API Adapter / Wrapper murni (Stateless & No Database) menggunakan **Laravel 12** untuk menggantikan gateway WhatsApp lama (Whacenter) ke server WhatsApp **GoWA** (self-hosted di VPS). Client utama hanya perlu mengubah `base_url` ke server Laravel ini, sementara struktur payload request dan JSON response yang diterima client tetap **100% identik** dengan format Whacenter.

---

## User Review Required

> [!IMPORTANT]
> **Stateless & Database-less Environment**
> Aplikasi ini dikonfigurasi tanpa koneksi ke database MySQL/SQLite. Driver `session` dan `cache` diatur ke `array` / `file`. Modul dan service provider yang memicu koneksi database dinonaktifkan / disesuaikan agar performa maksimal dan tidak memicu QueryException.

> [!NOTE]
> **Metode Autentikasi Ke Server GoWA**
> Aplikasi Laravel akan berkomunikasi dengan GoWA VPS menggunakan `Http::withBasicAuth(...)` (jika diproteksi Basic Auth) dan menambahkan header wajib `X-Device-Id` pada setiap request endpoint device-scoped GoWA.

---

## 1. Ringkasan Endpoint & Response Mapping

### Mapping Endpoint (Whacenter vs GoWA)

| # | Feature / Deskripsi | Whacenter Endpoint (Method) | Flow Proxy Laravel ke GoWA Endpoint |
|---|---|---|---|
| 1 | **Status Device** | `GET /api/statusDevice?device_id={id}` | `GET /devices/{device_id}` |
| 2 | **Relog Device** | `POST / GET /api/relogDevice` | Check status via `GET /devices/{device_id}`:<br>- Jika CONNECTED (`logged_in`) -> `POST /devices/{device_id}/reconnect`<br>- Jika NOT CONNECTED (`disconnected`) -> `GET /devices/{device_id}/login` |
| 3 | **Scan / Get QR Code** | `GET /api/qr?device_id={id}` | Check status via `GET /devices/{device_id}`:<br>- Jika NOT CONNECTED -> Call `GET /devices/{device_id}/login`, ambil `qr_link`, proxy gambar PNG.<br>- Jika CONNECTED / NOT FOUND -> Return gambar PNG fallback ("QR TIDAK TERSEDIA"). |
| 4 | **Send Message (Text)** | `POST / GET /api/send` (tanpa param `file`) | `POST /send/message` dengan Header `X-Device-Id: {device_id}` & JSON Body `{"phone": "{normalized_number}@s.whatsapp.net", "message": "..."}` |
| 5 | **Send Image / File** | `POST / GET /api/send` (dengan param `file`) | Tentukan tipe file (Gambar vs Dokumen):<br>- Jika Image (`.jpg`, `.png`, dsb.) -> `POST /send/image` (`image_url` / upload)<br>- Jika File/PDF -> `POST /send/file` (`file_url` / upload)<br>dengan Header `X-Device-Id: {device_id}` |

---

### Format Auth & Header Wajib

1. **Client -> Laravel API Adapter**:
   - Mendukung Request bertipe `application/x-www-form-urlencoded`, `multipart/form-data`, `application/json`, maupun Query Parameters (GET).
   - Parameter `device_id` wajib dikirimkan di setiap request oleh client.

2. **Laravel API Adapter -> GoWA Server**:
   - **Header Wajib**: `X-Device-Id: {device_id}`
   - **Basic Auth (opsional)**: `Authorization: Basic {base64(user:pass)}`
   - **Accept Header**: `application/json`

---

### Aturan Normalisasi Data

- **Normalisasi Nomor HP**:
  - Menghapus karakter non-digit (seperti `+`, `-`, spasi, kurung).
  - Mengubah awalan `08xx` menjadi `628xx`.
  - Contoh: `085640206067` -> `6285640206067`.
  - Format JID untuk GoWA `/send/message`: `6285640206067@s.whatsapp.net` atau `6285640206067`.

---

### Presisi Format JSON Response (Identik Whacenter)

#### A. Response `/api/statusDevice`

**1. Jika Device Connected:**
```json
{
  "status": true,
  "message": "success get device status",
  "data": {
    "status": "CONNECTED",
    "nomor": "6288801008000",
    "nama": "Netblazzer",
    "qr": "done"
  }
}
```

**2. Jika Device Disconnected / Not Connected:**
```json
{
  "status": true,
  "message": "success get device status",
  "data": {
    "status": "NOT CONNECTED",
    "nomor": "",
    "nama": "ICONIX support",
    "qr": "timeout"
  }
}
```

**3. Jika Device Tidak Ditemukan / Error Server GoWA:**
```json
{
  "status": false,
  "message": "device not connected or not found",
  "data": []
}
```

---

#### B. Response `/api/relogDevice`

**1. Jika Berhasil Relog / Reconnect:**
```json
{
  "status": true,
  "message": "berhasil relog device",
  "data": []
}
```

**2. Jika Device Tidak Ditemukan / Error:**
```json
{
  "status": false,
  "message": "device not connected or not found",
  "data": []
}
```

---

#### C. Response `/api/qr`
- Mengembalikan **HTTP Binary Content (Image PNG)** secara langsung.
- Jika QR Ready: Gambar QR Code dari `qr_link` GoWA.
- Jika Device Connected / Not Found / Timeout: Gambar PNG fallback bertuliskan **"QR TIDAK TERSEDIA"** (dihasilkan secara otomatis / via GD / SVG-to-PNG / Fallback Asset).

---

#### D. Response `/api/send` (Pesan Teks / Gambar / File)

**1. Jika Berhasil Dikirim:**
```json
{
  "status": true,
  "message": "message sent",
  "data": {
    "id": 110864596
  }
}
```

**2. Jika Gagal / Device Disconnected / Device Not Found:**
```json
{
  "status": false,
  "message": "device not connected or not found",
  "data": []
}
```

---

## 2. Konfigurasi Environment & Framework

### A. Environment File (`.env`)
```ini
APP_NAME="ICX WhatsApp Gateway Adapter"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost

# Force Stateless Drivers
SESSION_DRIVER=array
CACHE_STORE=array
QUEUE_CONNECTION=sync
DB_CONNECTION=

# GoWA VPS Config
GOWA_BASE_URL="http://your-vps-ip:3000"
GOWA_BASIC_AUTH_USER=""
GOWA_BASIC_AUTH_PASS=""
GOWA_TIMEOUT=15
GOWA_CONNECT_TIMEOUT=5
```

### B. Configuration File (`config/gowa.php`)
```php
<?php

return [
    'base_url' => env('GOWA_BASE_URL', 'http://127.0.0.1:3000'),
    'basic_auth_user' => env('GOWA_BASIC_AUTH_USER', ''),
    'basic_auth_pass' => env('GOWA_BASIC_AUTH_PASS', ''),
    'timeout' => env('GOWA_TIMEOUT', 15),
    'connect_timeout' => env('GOWA_CONNECT_TIMEOUT', 5),
];
```

### C. Framework Stateless Setup
- Memastikan tidak ada database migration yang dipanggil.
- Menghapus middleware session/cookie yang tidak diperlukan pada API routes.

---

## 3. Struktur File MVC yang Akan Dibuat

```
app/
├── Http/
│   ├── Controllers/
│   │   ├── DeviceController.php        # Menangani /statusDevice, /relogDevice, /qr
│   │   └── MessageController.php       # Menangani /send (Text, Image, File)
│   ├── Requests/
│   │   ├── DeviceStatusRequest.php    # Validasi parameter device_id
│   │   └── SendMessageRequest.php     # Validasi device_id, number, message, file
│   └── Services/                      # Helpers / Service sederhana jika diperlukan (atau Helper internal Controller)
routes/
└── api.php                             # Pendaftaran Endpoint API Whacenter
config/
└── gowa.php                            # Konfigurasi GoWA API Client
```

### Alur Eksekusi:
`routes/api.php` ➔ `FormRequest (Validasi & Sanitasi)` ➔ `Controller` ➔ `Laravel Http Client (Http::withHeaders()->post())` ➔ `Response Normalizer` ➔ `return response()->json()`

---

## 4. Roadmap Pengerjaan File demi File

1. **Langkah 1: Setup Environment & Config**
   - Buat file `config/gowa.php`.
   - Update `.env` dan `.env.example` untuk pengaturan VPS GoWA dan driver stateless (`array`/`file`).

2. **Langkah 2: Helper Normalisasi & Service Provider**
   - Buat helper/utility class untuk normalisasi nomor telepon (`PhoneNormalizer`) dan generator fallback QR Code image.

3. **Langkah 3: Form Requests & Validasi**
   - Buat `App\Http\Requests\DeviceStatusRequest` (validasi `device_id`).
   - Buat `App\Http\Requests\SendMessageRequest` (validasi `device_id`, `number`, `message`, `file`).

4. **Langkah 4: Controllers & Mapping Logic**
   - Buat `App\Http\Controllers\DeviceController`:
     - Method `statusDevice()`: Panggil GoWA `/devices/{device_id}` -> petakan response Whacenter.
     - Method `relogDevice()`: Cek status -> call reconnect/login -> petakan response Whacenter.
     - Method `qr()`: Cek status -> get QR link -> return HTTP binary PNG image.
   - Buat `App\Http\Controllers\MessageController`:
     - Method `send()`: Cek apakah ada param `file`. Tembak GoWA `/send/message`, `/send/image`, atau `/send/file` -> petakan response Whacenter.

5. **Langkah 5: Routing (`routes/api.php`)**
   - Daftarkan endpoint Whacenter:
     - `GET /api/statusDevice` & `POST /api/statusDevice`
     - `GET /api/relogDevice` & `POST /api/relogDevice`
     - `GET /api/qr`
     - `POST /api/send` & `GET /api/send`

6. **Langkah 6: Testing & Verifikasi Sintaks**
   - Verifikasi route list via `php artisan route:list`.
   - Menjalankan lint / syntax check php.

---

## Verification Plan

### Automated Verification
- Menjalankan `php artisan route:list` untuk meyakinkan seluruh endpoint `api/statusDevice`, `api/relogDevice`, `api/qr`, `api/send` terdaftar tanpa error.
- Menjalankan syntax check pada file controller dan request (`php -l`).

### Manual Verification (Simulation Test)
- Melakukan mock/simulation HTTP request ke endpoint Laravel API untuk menguji skenario:
  - Success response mapping.
  - Fail / Not Found response mapping.
  - Normalisasi nomor HP `08123...` -> `628123...`.
