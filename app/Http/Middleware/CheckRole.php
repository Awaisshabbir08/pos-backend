<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = $request->user();

        if (!$user || !$user->hasRole($role)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not have the required role to perform this action.',
                'data'    => null,
            ], 403);
        }

        return $next($request);
    }
}
