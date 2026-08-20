API Whacenter yang di pakai Kecantikan :
1./statusDevice?device_id= 
2./relogDevice?device_id= (ada api ini karena whacenter ini qr nya bisa timeout)
3./qr?device_id=
4./send biasa
5./send gambar atau file


HASIL API DARI WHACENTER
1./statusDevice?device_id= (GET)
A.Jika Status NOT CONNECTED Dan Qr Timeout
{"status":true,"message":"success get device status","data":{"status":"NOT
CONNECTED","nomor":"08118678980","nama":"TEST","qr":"timeout"}}

B.Jika Status not CONNECTED Dan Qr Ready
{"status":true,"message":"success get device status","data":{"status":"NOT
CONNECTED","nomor":"08118678980","nama":"TEST","qr":"2@V\/5MxzjhccRCNsQLh7GJBeAdH+i6noyIpqpRnLUiRJ8yeZb53A26buM1WLWdDE4vFQwDggt0ze+Bv\/9MCifnFpZ4BWBly2AmaEA=,KGNpmKvD57l2\/annDbOFzcNtmsosEtO6LDDCcSslIQI=,+07ql2DPIzeZs3A6i\/REpbFyM4yx2uTF9wNbZL+40BI=,qPaJlmZ7lacM\/Vxo2MrO7EqI5ayP+3TIIqmcKz1mkMk=,1"}}

C.Jika Connected
{"status":true,"message":"success get device
status","data":{"status":"CONNECTED","nomor":"08118678980","nama":"TEST","qr":"done"}}

D.Jika device belum didaftarkan atau tidak ditemukan
{"status":false,"message":"device not connected or not found","data":[]}


2./relogDevice?device_id= (GET)
A.Jika Status nya awalnya CONNECTED lalu melakukan relog tidak akan logout, hanya RELOG saja
{"status":true,"message":"berhasil relog device","data":[]}

B.Jika Statusnya awalnya NOT CONNECTED dan QR Timeout kalo melakukan relog maka QR akan ready 
{"status":true,"message":"berhasil relog device","data":[]}
Hasilnya sama saja untuk responsenya

C.Jika device belum didaftarkan atau tidak ditemukan
{"status":false,"message":"device not connected or not found","data":[]}

3./qr?device_id= (GET)
A.Kalo QR TIMEOUT dia akan menampilkan base64 image gitu deh kayanya terus ada tulisan QR TIDAK TERSEDIA
B.Kalo QR Ready di akan menampilkan base64 image ada qr codenya bisa di scan
C.Kalo Device tidak ditemukan atau tidak terdaftar maka tampil image QR TIDAK TERSEDIA

4./send biasa Text only (POST)
Body
formdata

device_id :
xxxx

number :
085640206067

message :
hai
A.JIKA BERHASIL KIRIM 
{"status":true,"message":"message sent","data":{"id":110864596}}

B.Jika device belum didaftarkan atau tidak ditemukan
{"status":false,"message":"device not connected or not found","data":[]}

5./send gambar atau file (POST)
Body
formdata

device_id :
xxxx

number :
6285640206067

message :
tes kirim gambar

file :
upload_filenya (url file / image)

A.Jika Berhasil Kirim
{"status":true,"message":"message sent","data":{"id":110864676}}

B.Jika device belum didaftarkan atau tidak ditemukan
{"status":false,"message":"device not connected or not found","data":[]}

======================================================================

API Gowa yang kemungkinan di pakai :
1./devices/{device_id}
2./devices/{device_id}/reconnect
3./devices/{device_id}/status
4./devices/{device_id}/login
5./send/message
6./send/image
7./send/file

HASIL API DARI GOWA
1./devices/{device_id} (GET)
Deskripsi :Get detailed information about a specific device

A.JIKA DEVICE DISCONNECTED ATAU BELUM CONNECT
{
  "code": "SUCCESS",
  "message": "Device info",
  "results": {
    "id": "107391db-0dc2-4e4f-b619-897fb30a57a1",
    "display_name": "ICONIX support",
    "state": "disconnected",
    "created_at": "2026-08-19T06:45:27.533876068Z"
  }
}

B.JIKA DEVICE SUDAH CONNECTED
{
  "code": "SUCCESS",
  "message": "Device info",
  "results": {
    "id": "ffc0778d-7651-4b56-b799-e1acf4e238ec",
    "display_name": "Netblazzer",
    "state": "logged_in",
    "jid": "6288801008000@s.whatsapp.net",
    "created_at": "2026-08-19T06:45:27.540436562Z"
  }
}

C.JIKA DEVICE TIDAK ditemukan atau Device id yang di tulis salah/belum didaftarkan
{
  "code": "INTERNAL_SERVER_ERROR",
  "message": "device ffc0778d-7651-4b56-b799-e1acf4e238e not found"
}

2./devices/{device_id}/reconnect (POST)
Deskripsi : Reconnect a specific device to WhatsApp

A.JIKA DEVICE BELUM CONNECT
{
  "code": "INTERNAL_SERVER_ERROR",
  "message": "device 107391db-0dc2-4e4f-b619-897fb30a57a1 is not logged in (session deleted)"
}

B.JIKA DEVICE SUDAH CONNECT
{
  "code": "SUCCESS",
  "message": "Reconnect requested"
}

C.JIKA DEVICE TIDAK DITEMUKAN
{
  "code": "INTERNAL_SERVER_ERROR",
  "message": "device ffc0778d-7651-4b56-b799-e1acf4e238e not found"
}


3./devices/{device_id}/status (GET)
Deskripsi : Get the current connection status of a specific device
A.JIKA NOT CONNECTED
{
  "code": "SUCCESS",
  "message": "Device status",
  "results": {
    "device_id": "107391db-0dc2-4e4f-b619-897fb30a57a1",
    "is_connected": false,
    "is_logged_in": false
  }
}

B.JIKA CONNECTED
{
  "code": "SUCCESS",
  "message": "Device status",
  "results": {
    "device_id": "ffc0778d-7651-4b56-b799-e1acf4e238ec",
    "is_connected": true,
    "is_logged_in": true
  }
}

C.JIKA TIDAK DITEMUKAN
{
  "code": "INTERNAL_SERVER_ERROR",
  "message": "device ffc0778d-7651-24b56-b799-e1acf4e238ec not found"
}


4./devices/{device_id}/login (GET)
Deskripsi : Start QR pairing for a specific device slot and return a link to the QR image to scan.

A.JIKA DEVICE NOT CONNECTED
{
  "code": "SUCCESS",
  "message": "Login success",
  "results": {
    "device_id": "%20107391db-0dc2-4e4f-b619-897fb30a57a1",
    "qr_duration": 30,
    "qr_link": "http://wa.iconix.id/statics/qrcode/scan-qr-71475a17-78f6-4882-9a1c-5494ecd68caa.png"
  }
}

B.JIKA DEVICE CONNECTED
{
  "code": "ALREADY_LOGGED_IN",
  "message": "you are already logged in."
}

C.JIKA DEVICE TIDAK DITEMUKAN
Ini bahaya karena device_id tidak ditemukan malah membuat device baru di gowa nya ini tidak boleh dilakukan jika device tidak ditemukan


5./send/message (POST)
Deskripsi : Untuk Mengirim pesan text only
A.JIKA BERHASIL MENGIRIM
{
  "code": "SUCCESS",
  "message": "Message sent to 628889924110@s.whatsapp.net (server timestamp: 2026-08-20 06:32:27 +0000 UTC)",
  "results": {
    "message_id": "3EB0E306BDFA82E564C83C",
    "status": "Message sent to 628889924110@s.whatsapp.net (server timestamp: 2026-08-20 06:32:27 +0000 UTC)"
  }
}

6./send/image (POST)
Deskripsi : Untuk Mengirim pesan gambar (catatan bisa kirim lewat upload file atau url link)
A.JIKA BERHASIL MENGIRIM
{
  "code": "SUCCESS",
  "message": "Message sent to 628889924110@s.whatsapp.net (server timestamp: 2026-08-20 06:44:55 +0000 UTC)",
  "results": {
    "message_id": "3EB01ED4CB64117E8A27CA",
    "status": "Message sent to 628889924110@s.whatsapp.net (server timestamp: 2026-08-20 06:44:55 +0000 UTC)"
  }
}

7./send/file (POST)
Deskripsi mengirim file (biasanya sih pdf)  (catatan bisa kirim lewat upload file atau url link)
A.JIKA BERHASIL MENGIRIM
{
  "code": "SUCCESS",
  "message": "Document sent to 628889924110@s.whatsapp.net (server timestamp: 2026-08-20 06:50:20 +0000 UTC)",
  "results": {
    "message_id": "3EB00FA7E422A95DD88600",
    "status": "Document sent to 628889924110@s.whatsapp.net (server timestamp: 2026-08-20 06:50:20 +0000 UTC)"
  }
}


Catatan Atau Kesimpulan
1.Karena Fungsi whacenter relogDevice adalah memperbarui qr code timeout, sementara GOWA tidak ada qr code timeout, kemungkinan adalah untuk api scrappernya yang api gowa harus pakai kombinasi yaitu
Cek Status Device CONNECTED Atau NOT CONNECTED
If CONNECTED = Hanya Menjalankan fungsi /devices/{device_id}/reconnect
IF NOT CONNECTED = Menjalankan fungsi /devices/{device_id}/login (agar qr terbuat baru)

Jika Device tidak ditemukan yang bilang aja tidak ditemukan

2.Untuk /qr?device_id= ini kan hasil responsenya dia langsung image yaa, maka untuk yang api scrapper ini juga kalo bisa langsung image saja, ini berarti langsung menjalankan ini tapi alurnya gini dlu
Cek Status Device CONNECTED Atau NOT CONNECTED
If CONNECTED = Karena sudah connect berarti gambar yang tampil QR TIDAK TERSEDIA SAJA
IF NOT CONNECTED = Menjalankan fungsi /devices/{device_id}/login (agar qr terbuat baru) dan menampilkan gambarnya
IF not found = gambar yang tampil QR TIDAK TERSEDIA SAJA