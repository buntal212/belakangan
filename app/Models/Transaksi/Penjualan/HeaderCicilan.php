<?php

namespace App\Models\Transaksi\Penjualan;

use App\Models\Pelanggan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class HeaderCicilan extends Model
{
    use HasFactory;
    protected $guarded = ['id'];
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Otomatis mengisi created_by dan updated_by
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = Auth::id();
                $model->updated_by = Auth::id(); // Saat pertama kali buat, dua-duanya sama
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }
    public function cicilan()
    {
        return $this->hasMany(PembayaranCicilan::class, 'header_ciclan_id', 'id');
    }

    public function pelanggan()
    {
        return $this->belongsTo(Pelanggan::class);
    }
    public function sales()
    {
        return $this->belongsTo(User::class, 'sales_id');
    }
}
