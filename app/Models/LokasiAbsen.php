<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class LokasiAbsen extends Model { protected $table = 'lokasi_absens'; protected $guarded = ['id']; protected $casts = ['latitude' => 'float', 'longitude' => 'float', 'aktif' => 'boolean']; }
