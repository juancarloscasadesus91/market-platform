<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Services\SchwabAuthService;
use App\Services\SchwabTraderAuthService;
use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class SchwabTokenStatus extends Component
{
    public bool $hasToken = false;
    public bool $hasRefreshToken = false;
    public ?string $authUrl = null;
    public ?string $traderAuthUrl = null;
    public ?string $accessToken = null;
    public ?string $refreshToken = null;
    
    // Trader API Status
    public bool $traderApiConnected = false;
    public ?string $traderApiError = null;
    public ?string $streamerSocketUrl = null;
    public ?string $schwabClientCustomerId = null;
    public ?string $schwabClientCorrelId = null;
    public ?string $schwabClientChannel = null;
    public ?string $schwabClientFunctionId = null;

    public function mount(): void
    {
        $this->checkTokenStatus();
        $this->checkTraderApiStatus();
    }

    public function checkTokenStatus(): void
    {
        $authService = SchwabAuthService::make();
        $traderAuthService = SchwabTraderAuthService::make();
        
        $this->accessToken = $authService->getAccessToken();
        $this->refreshToken = Cache::get('schwab_market_refresh_token');
        
        $this->hasToken = $this->accessToken !== null;
        $this->hasRefreshToken = $authService->hasRefreshToken();
        $this->authUrl = route('schwab.redirect'); // Market Data API
        $this->traderAuthUrl = route('schwab.trader.redirect'); // Trader API
    }

    public function checkTraderApiStatus(): void
    {
        // Use Trader API service
        $traderAuthService = SchwabTraderAuthService::make();
        
        // Check if Trader API is configured
        if (!config('services.schwab_trader.app_key')) {
            $this->traderApiConnected = false;
            $this->traderApiError = 'Trader API not configured. Add SCHWAB_TRADER_APP_KEY to .env file. See SCHWAB_SETUP.md for instructions.';
            return;
        }
        
        $traderToken = $traderAuthService->getAccessToken();
        
        if (!$traderToken) {
            $this->traderApiConnected = false;
            $this->traderApiError = 'No Trader API token available. Please authenticate using the green button below.';
            return;
        }

        try {
            \Log::info('Checking Trader API status', [
                'token_length' => strlen($traderToken),
                'token_prefix' => substr($traderToken, 0, 20) . '...',
            ]);

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $traderToken,
                'Accept' => 'application/json',
            ])->get('https://api.schwabapi.com/trader/v1/userPreference');

            \Log::info('Trader API response', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                $this->traderApiConnected = true;
                $this->traderApiError = null;
                
                // Extract streaming credentials
                $streamerInfo = $data['streamerInfo'][0] ?? null;
                if ($streamerInfo) {
                    $this->streamerSocketUrl = $streamerInfo['streamerSocketUrl'] ?? null;
                    $this->schwabClientCustomerId = $streamerInfo['schwabClientCustomerId'] ?? null;
                    $this->schwabClientCorrelId = $streamerInfo['schwabClientCorrelId'] ?? null;
                    $this->schwabClientChannel = $streamerInfo['schwabClientChannel'] ?? null;
                    $this->schwabClientFunctionId = $streamerInfo['schwabClientFunctionId'] ?? null;
                    
                    // Cache streaming credentials
                    Cache::put('schwab_streaming_credentials', [
                        'streamerSocketUrl' => $this->streamerSocketUrl,
                        'schwabClientCustomerId' => $this->schwabClientCustomerId,
                        'schwabClientCorrelId' => $this->schwabClientCorrelId,
                        'schwabClientChannel' => $this->schwabClientChannel,
                        'schwabClientFunctionId' => $this->schwabClientFunctionId,
                    ], now()->addHours(24));
                }
            } else {
                $this->traderApiConnected = false;
                
                if ($response->status() === 401) {
                    $this->traderApiError = 'Unauthorized: Token expired or invalid. Click "Refresh Token Now" to get a new token.';
                    
                    // Try to refresh automatically
                    $authService = SchwabAuthService::make();
                    $authService->clearToken();
                    $newToken = $authService->getAccessToken();
                    
                    if ($newToken) {
                        $this->checkTokenStatus();
                        $this->checkTraderApiStatus();
                        return;
                    }
                } elseif ($response->status() === 403) {
                    $this->traderApiError = 'Forbidden: Missing required scope for Trader API. Please re-authenticate with the green button below.';
                } else {
                    $this->traderApiError = 'HTTP ' . $response->status() . ': ' . $response->body();
                }
            }
        } catch (\Exception $e) {
            $this->traderApiConnected = false;
            $this->traderApiError = 'Connection error: ' . $e->getMessage();
            \Log::error('Trader API check failed', ['error' => $e->getMessage()]);
        }
    }

    public function refreshToken(): void
    {
        try {
            $authService = SchwabAuthService::make();
            
            // Clear current token
            $authService->clearToken();
            
            // Try to get new token (will use refresh token)
            $newToken = $authService->getAccessToken();
            
            \Log::info('Token refresh attempt', [
                'success' => $newToken !== null,
                'token_length' => $newToken ? strlen($newToken) : 0,
            ]);
            
            $this->checkTokenStatus();
            $this->checkTraderApiStatus();
            
            if ($this->hasToken) {
                session()->flash('message', 'Token refreshed successfully!');
            } else {
                session()->flash('error', 'Failed to refresh token. Please re-authenticate using the green button.');
            }
        } catch (\Exception $e) {
            \Log::error('Token refresh failed', ['error' => $e->getMessage()]);
            session()->flash('error', 'Error refreshing token: ' . $e->getMessage());
        }
    }

    public function render()
    {
        return view('livewire.schwab-token-status');
    }
}
