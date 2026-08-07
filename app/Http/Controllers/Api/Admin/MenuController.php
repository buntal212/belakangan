<?php

namespace App\Http\Controllers\Api\Admin;

use App\Helpers\FormatingHelper;
use App\Http\Controllers\Controller;
use App\Models\Admin\AdminMenu;
use App\Models\Barang;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function list()
    {
        $data = AdminMenu::with('subs')->oldest('urut')->get();

        return new JsonResponse($data);
    }
}
