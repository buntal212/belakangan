<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Admin\AdminMenu;
use App\Models\Barang;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function list()
    {
        $user = auth()->user();
        if ($user->username === 'sa') {
            $data = AdminMenu::with([
                'subs' => function ($query) {
                    $query->oldest('urut'); // Mengurutkan subs
                }
            ])->oldest('urut')->get();
        } else {
            $data = User::with([
                'hakakses.menus' => function ($query) {
                    $query->oldest('urut'); // Mengurutkan menus berdasarkan field urut
                },
                'hakakses.subs' => function ($query) {
                    $query->oldest('urut'); // Mengurutkan subs berdasarkan field urut
                }
            ])->where('id', auth()->user()->id)->first();
        }
        return new JsonResponse($data);
    }
}
