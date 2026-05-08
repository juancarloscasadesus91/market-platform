<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\SchwabAuthService;
use App\Services\SchwabTraderAuthService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SchwabAuthController extends Controller
{
    // No constructor injection - we'll use ::make() instead

    /**
     * Redirect to Schwab OAuth authorization
     */
    public function redirect(): RedirectResponse
    {
        $authService = SchwabAuthService::make();
        $authUrl = $authService->getAuthorizationUrl();
        return redirect()->away($authUrl);
    }

    /**
     * Handle OAuth callback for Market Data API
     */
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->query('code');

        if (!$code) {
            return redirect()->route('dashboard')
                ->with('error', 'Authorization failed: No code received');
        }

        $authService = SchwabAuthService::make();
        $token = $authService->exchangeCodeForToken($code);

        if ($token) {
            return redirect()->route('dashboard')
                ->with('success', 'Successfully connected to Schwab Market Data API');
        }

        return redirect()->route('dashboard')
            ->with('error', 'Failed to obtain Market Data API access token');
    }

    /**
     * Redirect to Trader API OAuth authorization
     */
    public function traderRedirect(): RedirectResponse
    {
        $traderAuthService = SchwabTraderAuthService::make();
        $authUrl = $traderAuthService->getAuthorizationUrl();
        return redirect()->away($authUrl);
    }

    /**
     * Handle OAuth callback for Trader API
     */
    public function traderCallback(Request $request): RedirectResponse
    {
        $code = $request->query('code');

        if (!$code) {
            return redirect()->route('dashboard')
                ->with('error', 'Trader API authorization failed: No code received');
        }

        $traderAuthService = SchwabTraderAuthService::make();
        $token = $traderAuthService->exchangeCodeForToken($code);

        if ($token) {
            return redirect()->route('dashboard')
                ->with('success', 'Successfully connected to Schwab Trader API');
        }

        return redirect()->route('dashboard')
            ->with('error', 'Failed to obtain Trader API access token');
    }

    /**
     * Disconnect from Schwab API
     */
    public function disconnect(): RedirectResponse
    {
        $authService = SchwabAuthService::make();
        $authService->clearToken();

        return redirect()->route('dashboard')
            ->with('success', 'Disconnected from Schwab API');
    }
}
