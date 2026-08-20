<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShiftKerja extends Model
{
    use HasFactory;

    protected $table = 'shift_kerjas';
    protected $guarded = ['id'];
    protected $casts = ['aktif' => 'boolean', 'toleransi' => 'integer'];
}
