<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/payment/finish', function () {
    return response(
        '<!doctype html><html lang="id"><head><meta charset="utf-8">'
        .'<meta name="viewport" content="width=device-width, initial-scale=1">'
        .'<title>Pembayaran</title></head>'
        .'<body style="font-family:sans-serif;text-align:center;padding:48px 24px">'
        .'<h2>Pembayaran sedang diproses</h2>'
        .'<p>Silakan kembali ke aplikasi reaple.id.</p></body></html>'
    );
});

// B8 menambahkan route Admin Panel di file ini.