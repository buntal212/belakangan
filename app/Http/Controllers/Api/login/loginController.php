<?php

namespace App\Http\Controllers\Api\login;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tymon\JWTAuth\Facades\JWTAuth as FacadesJWTAuth;

class loginController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username'  => 'required',
            'password'  => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $credentials = $request->only('username', 'password');

        // Set token expiry to 3 hours
        if (!$token = FacadesJWTAuth::attempt($credentials, ['exp' => Carbon::now()->addHours(3)->timestamp])) {
            return response()->json([
                'success' => false,
                'message' => 'Username atau Password Anda salah'
            ], 401);
        }

        return response()->json([
            'success' => true,
            'user'    => auth()->user(),
            'token'   => $token,
            'expires_in' => 10800 // 3 hours in seconds (3*60*60)
        ], 200);
    }

    public function logout()
    {
        try {
            // Pastikan user terautentikasi sebelum logout
            if (!auth('api')->check()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            auth('api')->logout();

            return response()->json([
                'success' => true,
                'message' => 'Successfully logged out'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to logout: ' . $e->getMessage()
            ], 500);
        }
    }
}



