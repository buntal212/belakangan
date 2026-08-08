<?php

namespace App\Http\Controllers\Api\v2\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    /**
     * Dapatkan URL untuk redirect ke provider OAuth dengan scope tambahan.
     *
     * @param string $provider
     * @return \Illuminate\Http\Response
     */
    public function getRedirectUrl($provider)
    {
        try {
            // @var \Laravel\Socialite\Two\AbstractProvider $driver
            $driver = Socialite::driver($provider);
            
            // Tambahkan scope sesuai provider
            if ($provider == 'google') {
                $driver->scopes([
                    'openid', 
                    'profile', 
                    'email'
                ]);
            } elseif ($provider == 'facebook') {
                $driver->scopes(['email', 'public_profile', 'user_location', 'user_birthday']);
            }
            
            $url = $driver->stateless()->redirect()->getTargetUrl();
            
            return response()->json([
                'status' => 'success',
                'redirect_url' => $url
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Provider tidak didukung: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Redirect ke provider OAuth dengan scope tambahan.
     *
     * @param string $provider
     * @return \Illuminate\Http\Response
     */
    public function redirect($provider)
    {
        // @var \Laravel\Socialite\Two\AbstractProvider $driver
        $driver = Socialite::driver($provider);
        
        // Tambahkan scope sesuai provider
        if ($provider == 'google') {
            $driver->scopes([
                'openid', 
                'profile', 
                'email'
            ]);
        } elseif ($provider == 'facebook') {
            $driver->scopes(['email', 'public_profile', 'user_location', 'user_birthday']);
        }
        
        return $driver->stateless()->redirect();
    }

    /**
     * Callback dari provider OAuth.
     *
     * @param string $provider
     * @return \Illuminate\Http\Response
     */
    public function callback($provider)
    {
        try {
            $driver = Socialite::driver($provider);
            $socialUser = $driver->stateless()->user();
            
            // Log semua data yang tersedia dari provider
            Log::info('Social User Data:', [
                'provider' => $provider,
                'id' => $socialUser->getId(),
                'name' => $socialUser->getName(),
                'email' => $socialUser->getEmail(),
                'avatar' => $socialUser->getAvatar(),
                'raw' => $socialUser->getRaw() // Semua data mentah
            ]);
            
            // Cari user berdasarkan provider_id dan provider
            $user = User::where([
                'provider' => $provider,
                'provider_id' => $socialUser->getId()
            ])->first();
            
            // Jika user tidak ditemukan, cari berdasarkan email
            if (!$user) {
                $user = User::where('email', $socialUser->getEmail())->first();
                
                // Jika user ditemukan berdasarkan email, update provider info
                if ($user) {
                    $userData = [
                        'provider' => $provider,
                        'provider_id' => $socialUser->getId(),
                    ];
                    
                    // Update avatar jika tersedia
                    if ($socialUser->getAvatar()) {
                        $userData['avatar'] = $socialUser->getAvatar();
                    }
                    
                    $user->update($userData);
                } else {
                    // Jika user tidak ditemukan sama sekali, buat user baru
                    $userData = [
                        'nama' => $socialUser->getName(),
                        'email' => $socialUser->getEmail(),
                        'username' => $this->generateUsername($socialUser->getEmail()),
                        'provider' => $provider,
                        'provider_id' => $socialUser->getId(),
                        'email_verified_at' => now(),
                    ];
                    
                    // Tambahkan avatar jika tersedia
                    if ($socialUser->getAvatar()) {
                        $userData['avatar'] = $socialUser->getAvatar();
                    }
                    
                    // Ambil data tambahan dari user object
                    $socialUserArray = $socialUser->getRaw();
                    
                    // Google specific data
                    if ($provider == 'google') {
                        // Nomor telepon (jika tersedia dan scope diizinkan)
                        if (isset($socialUserArray['phone_number'])) {
                            $userData['nohp'] = $socialUserArray['phone_number'];
                        }
                        
                        // Alamat (jika tersedia dan scope diizinkan)
                        if (isset($socialUserArray['address'])) {
                            $userData['alamat'] = $socialUserArray['address']['formatted'];
                        }
                    }
                    
                    // Facebook specific data
                    if ($provider == 'facebook') {
                        // Nomor telepon (jika tersedia dan scope diizinkan)
                        if (isset($socialUserArray['phone'])) {
                            $userData['nohp'] = $socialUserArray['phone'];
                        }
                        
                        // Alamat (jika tersedia dan scope diizinkan)
                        if (isset($socialUserArray['location'])) {
                            $userData['alamat'] = $socialUserArray['location']['name'];
                        }
                    }
                    
                    $user = User::create($userData);
                }
            } else {
                // Update avatar jika user sudah ada
                if ($socialUser->getAvatar() && $user->avatar !== $socialUser->getAvatar()) {
                    $user->update(['avatar' => $socialUser->getAvatar()]);
                }
            }
            
            // Login user
            Auth::login($user);
            
            // Generate token untuk API menggunakan Sanctum
            $token = $user->createToken('auth_token')->plainTextToken;
            
            // Redirect ke frontend dengan token
            $redirectUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')) . 
                           '/auth/social-callback?token=' . $token . 
                           '&user=' . urlencode(json_encode($user));
            
            return redirect($redirectUrl);
            
        } catch (\Exception $e) {
            $errorUrl = config('app.frontend_url', env('FRONTEND_URL', 'http://localhost:3000')) . 
                        '/auth/social-callback?error=' . urlencode($e->getMessage());
            
            return redirect($errorUrl);
        }
    }
    
    /**
     * Generate username dari email.
     *
     * @param string $email
     * @return string
     */
    private function generateUsername($email)
    {
        // Ambil bagian sebelum @ dari email
        $username = explode('@', $email)[0];
        
        // Tambahkan random string untuk memastikan keunikan
        $username .= rand(100, 999);
        
        return $username;
    }
}
