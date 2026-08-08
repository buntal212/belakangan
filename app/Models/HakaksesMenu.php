<?php

namespace App\Models;

use App\Models\Admin\AdminMenu;
use App\Models\Admin\AdminSub;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HakaksesMenu extends Model
{
    use HasFactory;
    // protected $table = 'hakakses_menus';
    protected $guarded = ['id'];


    public function menus()
    {
        return $this->belongsTo(AdminMenu::class,'menu_id');
    }

    public function subs()
    {
        return $this->belongsTo(AdminSub::class,'submenu_id' );
    }
}
