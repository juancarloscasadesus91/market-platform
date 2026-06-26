<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class SchwabAuthService
{
    private const TOKEN_CACHE_KEY = 'schwab_market_access_token';
    private const REFRESH_TOKEN_CACHE_KEY = 'schwab_market_refresh_token';
    private const TOKEN_EXPIRY_BUFFER = 300; // 5 minutes

    public function __construct(
        private readonly string $appKey,
        private readonly string $appSecret,
        private readonly string $callbackUrl,
        private readonly string $baseUrl,
    ) {}

    public static function make(): self
    {
        return new self(
            appKey: config('services.schwab.app_key', ''),
            appSecret: config('services.schwab.app_secret', ''),
            callbackUrl: config('services.schwab.callback_url', ''),
            baseUrl: config('services.schwab.base_url', 'https://api.schwabapi.com'),
        );
    }

    /**
     * Get valid access token (from cache or refresh)
     */
    public function getAccessToken(): ?string
    {
        $token = Cache::get(self::TOKEN_CACHE_KEY);
        
        if (!$token) {
            // Try to refresh the token
            $token = $this->refreshAccessToken();
        }
        
        return $token;
    }

    /**
     * Generate authorization URL for OAuth flow (Market Data API only)
     */
    public function getAuthorizationUrl(): string
    {
        $params = http_build_query([
            'response_type' => 'code',
            'client_id' => $this->appKey,
            'redirect_uri' => $this->callbackUrl,
            'scope' => 'readonly', // Market Data scope only
        ]);

        return "https://api.schwabapi.com/v1/oauth/authorize?{$params}";
    }

    /**
     * Exchange authorization code for access token
     */
    public function exchangeCodeForToken(string $code): ?string
    {
        $credentials = base64_encode("{$this->appKey}:{$this->appSecret}");
        
        $response = Http::asForm()
            ->withHeaders([
                'Authorization' => "Basic {$credentials}",
            ])
            ->post("https://api.schwabapi.com/v1/oauth/token", [
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $this->callbackUrl,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            $expiresIn = $data['expires_in'] ?? 1800; // 30 minutes
            $refreshExpiresIn = $data['refresh_token_expires_in'] ?? 604800; // 7 days
            
            // Store in main cache (works for both APIs with multi-scope token)
            Cache::put(
                self::TOKEN_CACHE_KEY,
                $data['access_token'],
                now()->addSeconds($expiresIn - self::TOKEN_EXPIRY_BUFFER)
            );
            
            if (isset($data['refresh_token'])) {
                Cache::put(
                    self::REFRESH_TOKEN_CACHE_KEY,
                    $data['refresh_token'],
                    now()->addSeconds($refreshExpiresIn - self::TOKEN_EXPIRY_BUFFER)
                );
            }

            return $data['access_token'];
        }

        return null;
    }

    /**
     * Refresh access token using refresh token
     */
    private function refreshAccessToken(): ?string
    {
        $refreshToken = Cache::get(self::REFRESH_TOKEN_CACHE_KEY);
        
        if (!$refreshToken) {
            return null;
        }
        
        $credentials = base64_encode("{$this->appKey}:{$this->appSecret}");
        
        $response = Http::asForm()
            ->withHeaders([
                'Authorization' => "Basic {$credentials}",
            ])
            ->post("https://api.schwabapi.com/v1/oauth/token", [
                'grant_type' => 'refresh_token',
                'refresh_token' => $refreshToken,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            $expiresIn = $data['expires_in'] ?? 1800; // 30 minutes
            
            // Store new access token
            Cache::put(
                self::TOKEN_CACHE_KEY,
                $data['access_token'],
                now()->addSeconds($expiresIn - self::TOKEN_EXPIRY_BUFFER)
            );
            
            // Update refresh token if provided
            if (isset($data['refresh_token'])) {
                $refreshExpiresIn = $data['refresh_token_expires_in'] ?? 604800; // 7 days
                Cache::put(
                    self::REFRESH_TOKEN_CACHE_KEY,
                    $data['refresh_token'],
                    now()->addSeconds($refreshExpiresIn - self::TOKEN_EXPIRY_BUFFER)
                );
            }

            return $data['access_token'];
        }

        return null;
    }
    
    /**
     * Clear cached tokens
     */
    public function clearToken(): void
    {
        Cache::forget(self::TOKEN_CACHE_KEY);
        Cache::forget(self::REFRESH_TOKEN_CACHE_KEY);
    }
    
    /**
     * Check if we have a valid refresh token
     */
    public function hasRefreshToken(): bool
    {
        return Cache::has(self::REFRESH_TOKEN_CACHE_KEY);
    }
    
    /**
     * Get token expiry time
     */
    public function getTokenExpiry(): ?int
    {
        $token = Cache::get(self::TOKEN_CACHE_KEY);
        if (!$token) {
            return null;
        }
        
        // Get TTL from cache
        return Cache::get(self::TOKEN_CACHE_KEY . '_expiry');
    }
}
