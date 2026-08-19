<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Absensi extends \Illuminate\Database\Eloquent\Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'tanggal' => 'date:Y-m-d',
        'waktu' => 'datetime',
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'akurasi_lokasi' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
