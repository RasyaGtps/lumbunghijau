<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (!$request->user()) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        if (!in_array($request->user()->role, $roles)) {
            return response()->json([
                'status' => false,
                'message' => 'Anda tidak memiliki akses untuk melakukan aksi ini'
            ], 403);
        }

        return $next($request);
    }
} 