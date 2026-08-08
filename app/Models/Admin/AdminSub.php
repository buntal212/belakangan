<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminSub extends Model
{
    use HasFactory;
    protected $table = 'admin_subs';
    protected $guarded = ['id'];

    public function menus()
    {
        return $this->belongsTo(AdminMenu::class, 'menu_id');
    }


}
