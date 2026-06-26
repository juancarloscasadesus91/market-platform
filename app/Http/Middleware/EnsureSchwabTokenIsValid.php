<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\SchwabAuthService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSchwabTokenIsValid
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authService = SchwabAuthService::make();
        
        // Try to get/refresh token
        $token = $authService->getAccessToken();
        
        // If no token and no refresh token, redirect to auth
        if (!$token && !$authService->hasRefreshToken()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Schwab API authentication required',
                    'auth_url' => $authService->getAuthorizationUrl(),
                ], 401);
            }
            
            return redirect()->route('schwab.auth.redirect');
        }
        
        return $next($request);
    }
}
