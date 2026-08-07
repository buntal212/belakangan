<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Profil extends Model
{
    use HasFactory;
    protected $table = 'profiltoko';
    protected $guarded = ['id'];

    public function getFotoAttribute($value)
    {
        if (!$value) return null;

        // Kembalikan URL lengkap untuk akses public
        return asset('storage/'.$value);
    }
}
