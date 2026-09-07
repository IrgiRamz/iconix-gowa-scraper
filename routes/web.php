<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Route utama (/) menampilkan dokumentasi API interaktif menggunakan
| Scalar API Reference yang murni render di sisi browser (Stateless / No DB).
|
*/

Route::get('/', function () {
    return view('docs');
});
