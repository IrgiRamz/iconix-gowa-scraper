API Whacenter yang di pakai Kecantikan :
1./statusDevice?device_id= 
2./relogDevice?device_id= (ada api ini karena whacenter ini qr nya bisa timeout)
3./qr?device_id=
4./send biasa
5./send gambar atau file


Perbandingan :

1./statusDevice?device_id= (GET)
A.Jika Status NOT CONNECTED Dan Qr Timeout
WHACENTER
{
    "status":true,
    "message":"success get device status",
    "data":{
        "status":"NOT CONNECTED",
        "nomor":"08118678980",
        "nama":"TEST",
        "qr":"timeout"
    }
}

SISTEM
{
  "status": true,
  "message": "success get device status",
  "data": {
    "status": "NOT CONNECTED",
    "nomor": "",
    "nama": "",
    "qr": "timeout"
  }
}


B.Jika Status not CONNECTED Dan Qr Ready
Whacenter
{
  "status": true,
  "message": "success get device status",
  "data": {
    "status": "NOT CONNECTED",
    "nomor": "08118678980",
    "nama": "TEST",
    "qr": "2@V/5MxzjhccRCNsQLh7GJBeAdH+i6noyIpqpRnLUiRJ8yeZb53A26buM1WLWdDE4vFQwDggt0ze+Bv/9MCifnFpZ4BWBly2AmaEA=,KGNpmKvD57l2/annDbOFzcNtmsosEtO6LDDCcSslIQI=,+07ql2DPIzeZs3A6i/REpbFyM4yx2uTF9wNbZL+40BI=,qPaJlmZ7lacM/Vxo2MrO7EqI5ayP+3TIIqmcKz1mkMk=,1"
  }
}

SISTEM
{
  "status": true,
  "message": "success get device status",
  "data": {
    "status": "NOT CONNECTED",
    "nomor": "",
    "nama": "",
    "qr": "timeout"
  }
}

C.Jika Connected
WHACENTER
{
  "status": true,
  "message": "success get device status",
  "data": {
    "status": "CONNECTED",
    "nomor": "08118678980",
    "nama": "TEST",
    "qr": "done"
  }
}

SISTEM
{
  "status": true,
  "message": "success get device status",
  "data": {
    "status": "CONNECTED",
    "nomor": "628118678980",
    "nama": "ICONIX support",
    "qr": "done"
  }
}

D.Jika device belum didaftarkan atau tidak ditemukan
WHACENTER
{
  "status": false,
  "message": "device not connected or not found",
  "data": []
}

SISTEM
{
  "status": false,
  "message": "device not connected or not found",
  "data": []
}


2./relogDevice?device_id= (GET)
A.Jika Status nya awalnya CONNECTED lalu melakukan relog tidak akan logout, hanya RELOG saja
WHACENTER
{
  "status": true,
  "message": "berhasil relog device",
  "data": []
}

SISTEM
{
  "status": true,
  "message": "berhasil relog device",
  "data": []
}

B.Jika Statusnya awalnya NOT CONNECTED dan QR Timeout kalo melakukan relog maka QR akan ready 
WHACENTER
{
  "status": true,
  "message": "berhasil relog device",
  "data": []
}

SISTEM
{
  "status": true,
  "message": "berhasil relog device",
  "data": []
}

Hasilnya sama saja untuk responsenya

C.Jika device belum didaftarkan atau tidak ditemukan
WHACENTER
{
  "status": false,
  "message": "device not connected or not found",
  "data": []
}

SISTEM
{
  "status": false,
  "message": "device not connected or not found",
  "data": []
}

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
WHACENTER
{
  "status": true,
  "message": "message sent",
  "data": {
    "id": 110864596
  }
}

SISTEM
{
    "status": true,
    "message": "message sent",
    "data": {
        "id": "3EB0AAA63901FE0A38BF17"
    }
}

B.Jika device belum didaftarkan atau tidak ditemukan
WHACENTER
{
  "status": false,
  "message": "device not connected or not found",
  "data": []
}

SISTEM
{
    "status": false,
    "message": "device not connected or not found",
    "data": []
}

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
WHACENTER
{
  "status": true,
  "message": "message sent",
  "data": {
    "id": 110864676
  }
}

SISTEM
{
    "status": true,
    "message": "message sent",
    "data": {
        "id": "3EB0C04031EAA1C7E543A9"
    }
}

B.Jika device belum didaftarkan atau tidak ditemukan
WHACENTER
{
  "status": false,
  "message": "device not connected or not found",
  "data": []
}

SISTEM
{
    "status": false,
    "message": "device not connected or not found",
    "data": []
}