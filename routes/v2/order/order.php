<?php

use Illuminate\Support\Facades\Route;

// Endpoint v2 menerima nominal dan status langsung dari klien sehingga dapat
// melewati validasi stok dan transisi status pada alur v4. Seluruh klien harus
// menggunakan /api/v4/sales/pengorderan sebagai satu-satunya jalur order.
Route::prefix('order')->any('/{path?}', function () {
    return response()->json([
        'message' => 'Endpoint order v2 sudah dihentikan. Gunakan API order v4.',
    ], 410);
})->where('path', '.*');


