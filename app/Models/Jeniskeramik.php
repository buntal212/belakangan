<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Jeniskeramik extends Model
{
    use HasFactory;
    protected $table = 'jeniskeramiks';
    protected $guarded = ['id'];
}
