<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Wishlist extends Model
{
    use HasFactory;
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $table = 'wishlists';
    protected $fillable = ['barang_id', 'user_id'];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = ['created_at', 'updated_at'];
    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */

     public function barang()
     {
        return $this->belongsTo(Barang::class, 'barang_id', 'id');
     }
}
