<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>ICX WhatsApp Gateway API Adapter Documentation</title>
    <meta name="description" content="Dokumentasi API Adapter Stateless Whacenter ke GoWA WhatsApp Gateway." />
    <link rel="icon" type="image/png" href="https://scalar.com/favicon.png" />
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #0f172a;
            color: #f8fafc;
        }
    </style>
</head>
<body>
    <script id="api-reference" type="application/json">
    {
        "openapi": "3.0.3",
        "info": {
            "title": "ICX WhatsApp Gateway API Adapter",
            "version": "1.0.0",
            "description": "## 🚀 Pengenalan & Alur Adapter\n\nAplikasi ini berfungsi sebagai **Stateless API Adapter / Wrapper** murni untuk memetakan request berformat gateway lama (**Whacenter**) ke server **GoWA (self-hosted)** di VPS secara otomatis.\n\n### 🔄 Cara Migrasi (Mengubah Base URL Client)\nUntuk mengalihkan lalu lintas WhatsApp dari vendor lama ke gateway baru ini, Anda **TIDAK PERLU** mengubah logika kodenya. Cukup ubah `base_url` di konfigurasi sistem utama Anda:\n\n- **Base URL Lama (Whacenter)**: `https://app.whacenter.com`\n- **Base URL Baru (Adapter)**: `http://vps-anda:8000` (atau domain adapter Anda)\n\n---\n\n### 🔐 Format Autentikasi & Header\n- Client utama cukup mengirimkan parameter `device_id` di setiap request (dapat berupa JSON body, form-data, atau query string).\n- **Normalisasi Nomor Telepon**: Nomor tujuan yang berawalan `08xxx` atau menggunakan tanda baca (`+`, `-`, spasi) akan secara otomatis dinormalisasi menjadi format internasional `628xxx` oleh Adapter.\n- **Respon Identik**: Struktur JSON respon dari adapter ini **100% identik** dengan respon standar Whacenter (`status`, `message`, `data`).\n\n---\n"
        },
        "servers": [
            {
                "url": "/api",
                "description": "API Adapter Endpoint Root"
            }
        ],
        "tags": [
            {
                "name": "Device Management",
                "description": "Endpoint untuk pemeriksaan status koneksi device, relog, dan pengambilan QR Code."
            },
            {
                "name": "Message Sending",
                "description": "Endpoint untuk pengiriman pesan WhatsApp (Teks, Gambar, dan Dokumen PDF)."
            },
            {
                "name": "System Health",
                "description": "Endpoint pemeriksaan kesehatan aplikasi adapter."
            }
        ],
        "paths": {
            "/statusDevice": {
                "get": {
                    "tags": ["Device Management"],
                    "summary": "Info Status Device",
                    "description": "Memeriksa status koneksi device WhatsApp ke server GoWA.",
                    "operationId": "getStatusDeviceGet",
                    "parameters": [
                        {
                            "name": "device_id",
                            "in": "query",
                            "required": true,
                            "description": "ID Device WhatsApp yang terdaftar di GoWA",
                            "schema": {
                                "type": "string",
                                "example": "xxxx"
                            }
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Response Berhasil (Connected atau Not Connected)",
                            "content": {
                                "application/json": {
                                    "schema": {
                                        "type": "object",
                                        "properties": {
                                            "status": { "type": "boolean", "example": true },
                                            "message": { "type": "string", "example": "success get device status" },
                                            "data": {
                                                "type": "object",
                                                "properties": {
                                                    "status": { "type": "string", "example": "CONNECTED" },
                                                    "nomor": { "type": "string", "example": "6288801008000" },
                                                    "nama": { "type": "string", "example": "ICONIX support" },
                                                    "qr": { "type": "string", "example": "done" }
                                                }
                                            }
                                        }
                                    },
                                    "examples": {
                                        "Connected": {
                                            "summary": "Status Device Terhubung",
                                            "value": {
                                                "status": true,
                                                "message": "success get device status",
                                                "data": {
                                                    "status": "CONNECTED",
                                                    "nomor": "6288801008000",
                                                    "nama": "ICONIX support",
                                                    "qr": "done"
                                                }
                                            }
                                        },
                                        "NotConnected": {
                                            "summary": "Status Device Belum Terhubung",
                                            "value": {
                                                "status": true,
                                                "message": "success get device status",
                                                "data": {
                                                    "status": "NOT CONNECTED",
                                                    "nomor": "",
                                                    "nama": "ICONIX support",
                                                    "qr": "timeout"
                                                }
                                            }
                                        },
                                        "NotFoundOrError": {
                                            "summary": "Device Tidak Ditemukan / Error",
                                            "value": {
                                                "status": false,
                                                "message": "device not connected or not found",
                                                "data": []
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                },
                "post": {
                    "tags": ["Device Management"],
                    "summary": "Info Status Device (POST)",
                    "description": "Memeriksa status koneksi device via metode POST.",
                    "operationId": "getStatusDevicePost",
                    "requestBody": {
                        "required": true,
                        "content": {
                            "application/x-www-form-urlencoded": {
                                "schema": {
                                    "type": "object",
                                    "required": ["device_id"],
                                    "properties": {
                                        "device_id": { "type": "string", "example": "xxxx" }
                                    }
                                }
                            },
                            "application/json": {
                                "schema": {
                                    "type": "object",
                                    "required": ["device_id"],
                                    "properties": {
                                        "device_id": { "type": "string", "example": "xxxx" }
                                    }
                                }
                            }
                        }
                    },
                    "responses": {
                        "200": {
                            "description": "Response Status Device",
                            "content": {
                                "application/json": {
                                    "example": {
                                        "status": true,
                                        "message": "success get device status",
                                        "data": {
                                            "status": "CONNECTED",
                                            "nomor": "6288801008000",
                                            "nama": "ICONIX support",
                                            "qr": "done"
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            },
            "/relogDevice": {
                "post": {
                    "tags": ["Device Management"],
                    "summary": "Relog Device",
                    "description": "Melakukan reconnect ulang jika device terhubung, atau generate sesi baru jika belum terhubung.",
                    "operationId": "relogDevicePost",
                    "requestBody": {
                        "required": true,
                        "content": {
                            "application/x-www-form-urlencoded": {
                                "schema": {
                                    "type": "object",
                                    "required": ["device_id"],
                                    "properties": {
                                        "device_id": { "type": "string", "example": "xxxx" }
                                    }
                                }
                            },
                            "application/json": {
                                "schema": {
                                    "type": "object",
                                    "required": ["device_id"],
                                    "properties": {
                                        "device_id": { "type": "string", "example": "xxxx" }
                                    }
                                }
                            }
                        }
                    },
                    "responses": {
                        "200": {
                            "description": "Response Relog",
                            "content": {
                                "application/json": {
                                    "examples": {
                                        "Success": {
                                            "summary": "Berhasil Relog",
                                            "value": {
                                                "status": true,
                                                "message": "berhasil relog device",
                                                "data": []
                                            }
                                        },
                                        "Fail": {
                                            "summary": "Gagal / Device Not Found",
                                            "value": {
                                                "status": false,
                                                "message": "device not connected or not found",
                                                "data": []
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                },
                "get": {
                    "tags": ["Device Management"],
                    "summary": "Relog Device (GET)",
                    "description": "Relog device via metode GET.",
                    "operationId": "relogDeviceGet",
                    "parameters": [
                        {
                            "name": "device_id",
                            "in": "query",
                            "required": true,
                            "schema": { "type": "string", "example": "xxxx" }
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Response Relog",
                            "content": {
                                "application/json": {
                                    "example": {
                                        "status": true,
                                        "message": "berhasil relog device",
                                        "data": []
                                    }
                                }
                            }
                        }
                    }
                }
            },
            "/qr": {
                "get": {
                    "tags": ["Device Management"],
                    "summary": "Scan / Download QR Code",
                    "description": "Mengembalikan stream file gambar PNG langsung (`image/png`). Jika device belum terhubung, menampilkan QR Code aktif. Jika sudah terhubung atau tidak ditemukan, mengembalikan gambar fallback bertuliskan 'QR TIDAK TERSEDIA'.",
                    "operationId": "getQrCode",
                    "parameters": [
                        {
                            "name": "device_id",
                            "in": "query",
                            "required": true,
                            "schema": { "type": "string", "example": "xxxx" }
                        }
                    ],
                    "responses": {
                        "200": {
                            "description": "Binary Image PNG QR Code",
                            "content": {
                                "image/png": {
                                    "schema": {
                                        "type": "string",
                                        "format": "binary"
                                    }
                                }
                            }
                        }
                    }
                }
            },
            "/send": {
                "post": {
                    "tags": ["Message Sending"],
                    "summary": "Kirim Pesan WhatsApp (Teks / Gambar / Dokumen)",
                    "description": "Endpoint terpadu untuk pengiriman pesan WhatsApp:\n- **Pesan Teks**: Kosongkan parameter `file`.\n- **Pesan Gambar**: Isi parameter `file` dengan URL Gambar (`.jpg`, `.png`, `.webp`) atau upload file fisik. Parameter `message` / `caption` akan dikirim sebagai caption gambar.\n- **Pesan Dokumen PDF**: Isi parameter `file` dengan URL PDF (`.pdf`) atau upload file PDF. **Aturan Khusus**: Dokumen dikirim tanpa caption sesuai standar API GoWA.\n\n*Nomor telepon otomatis dinormalisasi (`0812xxx` -> `62812xxx`).*",
                    "operationId": "sendMessagePost",
                    "requestBody": {
                        "required": true,
                        "content": {
                            "multipart/form-data": {
                                "schema": {
                                    "type": "object",
                                    "required": ["device_id", "number"],
                                    "properties": {
                                        "device_id": {
                                            "type": "string",
                                            "example": "xxxx",
                                            "description": "ID Device WhatsApp"
                                        },
                                        "number": {
                                            "type": "string",
                                            "example": "085640206067",
                                            "description": "Nomor tujuan (format 08xx atau 628xx)"
                                        },
                                        "message": {
                                            "type": "string",
                                            "example": "Halo, ini pesan konfirmasi dari sistem.",
                                            "description": "Isi pesan teks / caption gambar"
                                        },
                                        "file": {
                                            "type": "string",
                                            "example": "https://i.ibb.co/S5GYRNL/bird-thumbnail.jpg",
                                            "description": "URL file/gambar atau upload file fisik multipart (opsional)"
                                        }
                                    }
                                }
                            },
                            "application/json": {
                                "schema": {
                                    "type": "object",
                                    "required": ["device_id", "number"],
                                    "properties": {
                                        "device_id": { "type": "string", "example": "xxxx" },
                                        "number": { "type": "string", "example": "085640206067" },
                                        "message": { "type": "string", "example": "Halo, ini pesan konfirmasi dari sistem." },
                                        "file": { "type": "string", "example": "https://i.ibb.co/S5GYRNL/bird-thumbnail.jpg" }
                                    }
                                },
                                "examples": {
                                    "PesanTeks": {
                                        "summary": "1. Contoh Request Kirim Pesan Teks",
                                        "value": {
                                            "device_id": "xxxx",
                                            "number": "085640206067",
                                            "message": "Halo, ini pesan teks dari sistem."
                                        }
                                    },
                                    "PesanGambar": {
                                        "summary": "2. Contoh Request Kirim Gambar (dengan Caption)",
                                        "value": {
                                            "device_id": "xxxx",
                                            "number": "085640206067",
                                            "message": "Berikut adalah foto bukti transaksi.",
                                            "file": "https://i.ibb.co/S5GYRNL/bird-thumbnail.jpg"
                                        }
                                    },
                                    "PesanDokumenPDF": {
                                        "summary": "3. Contoh Request Kirim Dokumen PDF (Tanpa Caption)",
                                        "value": {
                                            "device_id": "xxxx",
                                            "number": "085640206067",
                                            "file": "https://www.w3.org/WAI/ER/tests/xhtml/testfiles/resources/pdf/dummy.pdf"
                                        }
                                    }
                                }
                            }
                        }
                    },
                    "responses": {
                        "200": {
                            "description": "Response Pengiriman",
                            "content": {
                                "application/json": {
                                    "examples": {
                                        "SentSuccess": {
                                            "summary": "Pesan / File Berhasil Terkirim",
                                            "value": {
                                                "status": true,
                                                "message": "message sent",
                                                "data": {
                                                    "id": 110864596
                                                }
                                            }
                                        },
                                        "SentFailed": {
                                            "summary": "Pesan Gagal / Device Disconnected / Invalid",
                                            "value": {
                                                "status": false,
                                                "message": "device not connected or not found",
                                                "data": []
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                },
                "get": {
                    "tags": ["Message Sending"],
                    "summary": "Kirim Pesan WhatsApp (GET Legacy Support)",
                    "description": "Pengiriman pesan via metode GET untuk mendukung legacy client.",
                    "operationId": "sendMessageGet",
                    "parameters": [
                        { "name": "device_id", "in": "query", "required": true, "schema": { "type": "string", "example": "xxxx" } },
                        { "name": "number", "in": "query", "required": true, "schema": { "type": "string", "example": "085640206067" } },
                        { "name": "message", "in": "query", "required": false, "schema": { "type": "string", "example": "Halo dari GET request" } },
                        { "name": "file", "in": "query", "required": false, "schema": { "type": "string", "example": "https://i.ibb.co/S5GYRNL/bird-thumbnail.jpg" } }
                    ],
                    "responses": {
                        "200": {
                            "description": "Response Pengiriman",
                            "content": {
                                "application/json": {
                                    "example": {
                                        "status": true,
                                        "message": "message sent",
                                        "data": {
                                            "id": 110864596
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            },
            "/health": {
                "get": {
                    "tags": ["System Health"],
                    "summary": "Health Check Adapter",
                    "description": "Memeriksa status kesehatan servis Laravel Adapter.",
                    "operationId": "getHealth",
                    "responses": {
                        "200": {
                            "description": "System Healthy",
                            "content": {
                                "application/json": {
                                    "example": {
                                        "status": true,
                                        "message": "ICX WhatsApp Gateway Adapter is running",
                                        "data": []
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    </script>
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
</body>
</html>
