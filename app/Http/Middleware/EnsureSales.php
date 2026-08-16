<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureSales
{
    public function handle(Request $request, Closure $next)
    {
        if (!$request->user() || $request->user()->jabatan !== 'Sales') {
            return response()->json(['success' => false, 'message' => 'Akses khusus sales.'], 403);
        }

        return $next($request);
    }
}
