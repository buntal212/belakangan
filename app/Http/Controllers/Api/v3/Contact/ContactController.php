<?php

namespace App\Http\Controllers\Api\v3\Contact;

use App\Http\Controllers\Controller;
use App\Models\Profil;
use App\Models\User;

class ContactController extends Controller
{
    public function index()
    {
        return response()->json([
            'success' => true,
            'data' => [
                'profile' => Profil::query()->select('namatoko', 'alamat', 'telepon', 'email', 'bio', 'foto')->first(),
                'sales' => User::query()
                    ->select('id', 'nama', 'nohp', 'email', 'avatar')
                    ->where('jabatan', 'Sales')
                    ->orderBy('nama')
                    ->get(),
            ],
        ]);
    }
}
