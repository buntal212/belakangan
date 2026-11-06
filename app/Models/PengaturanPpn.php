<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PengaturanPpn extends Model
{
    use HasFactory;

    protected $table = 'pengaturan_ppns';

    protected $guarded = ['id'];

    protected $casts = [
        'aktif' => 'boolean',
        'persen' => 'decimal:2',
    ];
}
