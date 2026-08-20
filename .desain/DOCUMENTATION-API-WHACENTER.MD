GET
Info Device
https://app.whacenter.com/api/statusDevice?device_id=xxxx
PARAMS
device_id
xxxx

<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://app.whacenter.com/api/statusDevice?device_id=xxx',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;


if false

{"status":false,"message":"device not connected or not found","data":[]}

if true
{
  "status": true,
  "message": "success get device status",
  "data": {
    "status": "NOT CONNECTED",
    "nomor": "628xxx",
    "nama": "namadevice",
    "qr": "timeout"
  }
}


GET
Scan QR Code
https://app.whacenter.com/api/qr?device_id=xxx
PARAMS
device_id
xxx


<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://app.whacenter.com/api/qr?device_id=xxx',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;


POST
Send Message (POST)
https://app.whacenter.com/api/send
Body
formdata
device_id
xxxx

number
085640206067

message
hai

<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://app.whacenter.com/api/send',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => array('device_id' => 'xxxx','number' => '085640206067','message' => 'hai'),
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;


if false 
{"status":false,"message":"device not connected or not found","data":[]}

if true
{
  "status": true,
  "message": "message sent",
  "data": {
    "id": xxx
  }
}


GET
Send Message (GET)
https://app.whacenter.com/api/send
Body
formdata
device_id
xxxx

number
085640206067

message
hai

<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://app.whacenter.com/api/send',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_POSTFIELDS => array('device_id' => 'xxx','number' => '085640206067','message' => 'hai'),
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;


if false 
{"status":false,"message":"device not connected or not found","data":[]}

if true
{
  "status": true,
  "message": "message sent",
  "data": {
    "id": xxx
  }
}

POST
Relog Device
https://app.whacenter.com/api/relogDevice
Body
formdata
device_id
xxxx

<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://app.whacenter.com/api/relogDevice',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => array('device_id' => 'xxxx'),
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;


if false
{"status":false,"message":"device not connected or not found","data":[]}

if true
{
  "status": true,
  "message": "berhasil relog device",
  "data": [
    
  ]
}

POST
Send Message Image or File
https://app.whacenter.com/api/send
Body
formdata
device_id
xxxx

number
6285640206067

message
tes kirim gambar

file
https://i.ibb.co/S5GYRNL/bird-thumbnail.jpg

url file / image

<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://app.whacenter.com/api/send',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS => array('device_id' => 'xxxx','number' => '6285640206067','message' => 'tes kirim gambar','file' => 'https://i.ibb.co/S5GYRNL/bird-thumbnail.jpg'),
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;


if false 
{"status":false,"message":"device not connected or not found","data":[]}

if true
{
  "status": true,
  "message": "message sent",
  "data": {
    "id": xxx
  }
}

