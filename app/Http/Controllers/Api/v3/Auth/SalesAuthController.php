<?php
namespace App\Http\Controllers\Api\v3\Auth;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class SalesAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate(['username' => ['required', 'string'], 'password' => ['required', 'string']]);
        $user = User::where('username', $credentials['username'])->first();
        if (!$user || !$user->password || !Hash::check($credentials['password'], $user->password)) return response()->json(['success' => false, 'message' => 'Email atau password sales salah.'], 401);
        $user->tokens()->where('name', 'jangur-sales-v3')->delete();
        return response()->json(['success' => true, 'message' => 'Login sales berhasil.', 'token' => $user->createToken('jangur-sales-v3')->plainTextToken, 'user' => $user]);
    }
    public function me(Request $request): JsonResponse { return response()->json(['success' => true, 'user' => $request->user()]); }
    public function logout(Request $request): JsonResponse { $request->user()->currentAccessToken()?->delete(); return response()->json(['success' => true, 'message' => 'Logout berhasil.']); }
}
