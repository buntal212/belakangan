<?php

namespace App\Http\Controllers\Api\v3\Settings;

use App\Http\Controllers\Controller;
use App\Models\LokasiAbsen;
use Illuminate\Http\JsonResponse;

class LokasiAbsenController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => LokasiAbsen::query()
                ->where('aktif', true)
                ->select('nama', 'latitude', 'longitude', 'radius_meter')
                ->first(),
        ]);
    }
}
