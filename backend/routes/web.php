<?php

use Illuminate\Support\Facades\Route;

Route::get('/login', fn () => redirect('/'))->name('login');

Route::get('/prices', function () {
    $path = public_path('prices/index.html');
    if (file_exists($path)) {
        return response()->file($path, ['Content-Type' => 'text/html']);
    }
    abort(404);
});

Route::fallback(function () {
    $indexPath = public_path('index.html');

    if (file_exists($indexPath)) {
        return response()->file($indexPath, ['Content-Type' => 'text/html']);
    }

    abort(404);
});
