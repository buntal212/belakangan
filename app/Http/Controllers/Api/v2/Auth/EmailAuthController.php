<?php

namespace App\Http\Controllers\Api\v2\Auth;

use App\Http\Controllers\Controller;
use App\Models\Profil;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class EmailAuthController extends Controller
{
    /**
     * Login dengan email dan password menggunakan Sanctum
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'     => 'required|email',
            'password'  => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Cek apakah user ada berdasarkan email
        $user = User::where('email', $request->email)->first();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau Password Anda salah'
            ], 401);
        }
        
        // Cek apakah user login dengan social provider dan belum pernah set password
        if ($user->provider && !$user->password) {
            return response()->json([
                'success' => false,
                'message' => 'Akun Anda terdaftar menggunakan ' . ucfirst($user->provider) . '. Silakan login menggunakan ' . ucfirst($user->provider) . ' atau set password terlebih dahulu.',
                'provider' => $user->provider
            ], 401);
        }

        // Coba autentikasi dengan email dan password
        if (!Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau Password Anda salah'
            ], 401);
        }
        
        // Hapus token lama jika ada
        $user->tokens()->delete();
        
        // Buat token baru dengan Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        $maps = Profil::first();

        return response()->json([
            'success' => true,
            'user'    => $user,
            'profil'    => $maps,
            'token'   => $token
        ], 200);
    }

    /**
     * Register user baru dengan email
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nama'      => 'required|string|max:255',
            'email'     => 'required|string|email|max:255|unique:users',
            'password'  => 'required|string|min:6|confirmed',
            'username'  => 'required|string|max:255|unique:users',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::create([
            'nama'      => $request->nama,
            'email'     => $request->email,
            'username'  => $request->username,
            'password'  => Hash::make($request->password),
            'email_verified_at' => now(),
        ]);

        // Buat token dengan Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success'   => true,
            'message'   => 'Registrasi berhasil',
            'user'      => $user,
            'token'     => $token
        ], 201);
    }

    /**
     * Set password untuk akun yang login dengan social provider
     */
    public function setPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email'     => 'required|email|exists:users,email',
            'password'  => 'required|string|min:6|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::where('email', $request->email)->first();
        
        // Update password
        $user->update([
            'password' => Hash::make($request->password)
        ]);
        
        // Login user
        Auth::login($user);
        
        // Hapus token lama jika ada
        $user->tokens()->delete();
        
        // Buat token baru dengan Sanctum
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diatur',
            'user'    => $user,
            'token'   => $token
        ], 200);
    }

    /**
     * Logout user dan hapus token
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            
            // Jika user ada
            if ($user) {
                // Hapus semua token jika ada bearer token
                if ($request->bearerToken()) {
                    // Hapus semua token user
                    $user->tokens()->delete();
                }
                
                // Jika menggunakan session, hapus session
                if ($request->hasSession()) {
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
            }

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
    
    /**
     * Mendapatkan data user yang sedang login
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'user' => $request->user()
        ]);
    }
}
