<?php

namespace App\Http\Middleware;

use App\Models\Courier;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsCourier
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! ($request->user() instanceof Courier)) {
            return response()->json([
                'message' => 'Akses ditolak. Endpoint ini hanya untuk kurir.',
            ], 403);
        }

        return $next($request);
    }
}
