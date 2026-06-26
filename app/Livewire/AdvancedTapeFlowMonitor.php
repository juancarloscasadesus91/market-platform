<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Component;
use App\Services\SchwabTraderAuthService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class AdvancedTapeFlowMonitor extends Component
{
    // Streaming credentials (similar to LiveOptionMonitor)
    public ?string $streamerSocketUrl = null;
    public ?string $schwabClientCustomerId = null;
    public ?string $schwabClientCorrelId = null;
    public ?string $schwabClientChannel = null;
    public ?string $schwabClientFunctionId = null;
    public ?string $accessToken = null;

    // Error handling
    public ?string $error = null;
    public bool $hasTraderAccess = false;

    // UI State
    public string $selectedWindow = 'day';
    public string $selectedClassification = 'all';
    public int $tapeLimit = 50;
    public bool $showPositions = true;
    public bool $showTape = true;
    public bool $showAggressive = true;
    public bool $showNoise = true;
    public string $lastUpdate = '';
    public bool $isLoading = false;

    // Data properties
    public array $globalData = [];
    public array $topBullish = [];
    public array $topBearish = [];
    public array $mostAggressive = [];
    public array $highMidNoise = [];
    public array $recentTape = [];
    public array $activePositions = [];
    public array $buildingPositions = [];
    public array $exitingPositions = [];

    public function mount(): void
    {
        $this->loadStreamingCredentials();
        $this->lastUpdate = now()->format('H:i:s');

        // Cargar datos iniciales
        $this->refreshData();
    }

    /**
     * Load streaming credentials (same as LiveOptionMonitor)
     */
    private function loadStreamingCredentials(): void
    {
        // Use Trader API for streaming credentials AND streaming token
        $traderAuthService = SchwabTraderAuthService::make();
        $traderToken = $traderAuthService->getAccessToken();

        // Store the trader token for streaming
        $this->accessToken = $traderToken;

        if (!$traderToken) {
            $this->error = 'No Trader API token available. Please authenticate Trader API first.';
            $this->hasTraderAccess = false;
            return;
        }

        // Check cache first
        $cached = Cache::get('schwab_streaming_credentials');
        if ($cached) {
            $this->streamerSocketUrl = $cached['streamerSocketUrl'] ?? null;
            $this->schwabClientCustomerId = $cached['schwabClientCustomerId'] ?? null;
            $this->schwabClientCorrelId = $cached['schwabClientCorrelId'] ?? null;
            $this->schwabClientChannel = $cached['schwabClientChannel'] ?? null;
            $this->schwabClientFunctionId = $cached['schwabClientFunctionId'] ?? null;
            $this->hasTraderAccess = true;
            return;
        }

        try {
            // Get streaming credentials from Trader API
            $response = Http::timeout(10)->withHeaders([
                'Authorization' => 'Bearer ' . $traderToken,
                'Accept' => 'application/json',
            ])->get('https://api.schwabapi.com/trader/v1/userPreference');

            \Log::info('AdvancedTapeFlowMonitor: userPreference response', [
                'status' => $response->status(),
                'success' => $response->successful(),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $streamerInfo = $data['streamerInfo'][0] ?? null;

                if ($streamerInfo) {
                    $this->streamerSocketUrl = $streamerInfo['streamerSocketUrl'] ?? null;
                    $this->schwabClientCustomerId = $streamerInfo['schwabClientCustomerId'] ?? null;
                    $this->schwabClientCorrelId = $streamerInfo['schwabClientCorrelId'] ?? null;
                    $this->schwabClientChannel = $streamerInfo['schwabClientChannel'] ?? null;
                    $this->schwabClientFunctionId = $streamerInfo['schwabClientFunctionId'] ?? null;

                    // Cache the credentials for 30 minutes
                    Cache::put('schwab_streaming_credentials', [
                        'streamerSocketUrl' => $this->streamerSocketUrl,
                        'schwabClientCustomerId' => $this->schwabClientCustomerId,
                        'schwabClientCorrelId' => $this->schwabClientCorrelId,
                        'schwabClientChannel' => $this->schwabClientChannel,
                        'schwabClientFunctionId' => $this->schwabClientFunctionId,
                    ], now()->addMinutes(30));

                    \Log::info('AdvancedTapeFlowMonitor: Streaming credentials loaded', [
                        'socketUrl' => $this->streamerSocketUrl,
                        'customerId' => $this->schwabClientCustomerId ? 'SET' : 'NULL',
                    ]);
                    $this->hasTraderAccess = true;
                }
            } else {
                if ($response->status() === 401) {
                    $this->error = 'Unauthorized: Token expired or invalid';
                } elseif ($response->status() === 403) {
                    $this->error = 'Forbidden: Missing required scope for Trader API';
                } else {
                    $this->error = 'Failed to load streaming credentials';
                }
            }
        } catch (\Exception $e) {
            \Log::error('AdvancedTapeFlowMonitor: Error loading streaming credentials', [
                'error' => $e->getMessage()
            ]);
            $this->error = 'Error loading streaming credentials: ' . $e->getMessage();
        }
    }

    /**
     * Get streaming credentials for JavaScript
     */
    public function getCredentials(): array
    {
        \Log::info('AdvancedTapeFlowMonitor: getCredentials called', [
            'hasTraderAccess' => $this->hasTraderAccess,
            'streamerSocketUrl' => $this->streamerSocketUrl ? 'SET' : 'NULL',
            'accessToken' => $this->accessToken ? 'SET' : 'NULL'
        ]);

        if (!$this->hasTraderAccess) {
            \Log::warning('AdvancedTapeFlowMonitor: No Trader API access');
            return ['credentials' => null, 'error' => 'No Trader API access'];
        }

        if (!$this->streamerSocketUrl || !$this->accessToken) {
            \Log::warning('AdvancedTapeFlowMonitor: Missing credentials', [
                'streamerSocketUrl' => $this->streamerSocketUrl ? 'SET' : 'NULL',
                'accessToken' => $this->accessToken ? 'SET' : 'NULL'
            ]);
            return ['credentials' => null, 'error' => 'Missing streaming credentials'];
        }

        return [
            'credentials' => [
                'streamerSocketUrl' => $this->streamerSocketUrl,
                'schwabClientCustomerId' => $this->schwabClientCustomerId,
                'schwabClientCorrelId' => $this->schwabClientCorrelId,
                'schwabClientChannel' => $this->schwabClientChannel,
                'schwabClientFunctionId' => $this->schwabClientFunctionId,
                'accessToken' => $this->accessToken
            ],
            'error' => null
        ];
    }

    /**
     * Refresh data from backend
     */
    public function refreshData(): void
    {
        $this->isLoading = true;

        try {
            $response = Http::timeout(10)->get(route('api.advanced-tape-flow.dashboard', [
                'window' => $this->selectedWindow
            ]));

            if ($response->successful()) {
                $data = $response->json();
                $this->globalData = $data['global'] ?? [];
                $this->topBullish = $data['top_bullish'] ?? [];
                $this->topBearish = $data['top_bearish'] ?? [];
                $this->mostAggressive = $data['most_aggressive'] ?? [];
                $this->highMidNoise = $data['high_mid_noise'] ?? [];
                $this->recentTape = $data['recent_tape'] ?? [];
            }

            $this->loadPositions();

        } catch (\Exception $e) {
            \Log::error('Error refreshing data', ['error' => $e->getMessage()]);
        } finally {
            $this->isLoading = false;
            $this->lastUpdate = now()->format('H:i:s');
        }
    }

    /**
     * Load positions data
     */
    private function loadPositions(): void
    {
        try {
            $response = Http::get(route('api.advanced-tape-flow.positions.active'));
            if ($response->successful()) {
                $this->activePositions = $response->json()['positions'] ?? [];
            }

            $response = Http::get(route('api.advanced-tape-flow.positions.building'));
            if ($response->successful()) {
                $this->buildingPositions = $response->json()['positions'] ?? [];
            }

            $response = Http::get(route('api.advanced-tape-flow.positions.exiting'));
            if ($response->successful()) {
                $this->exitingPositions = $response->json()['positions'] ?? [];
            }
        } catch (\Exception $e) {
            \Log::warning('Error loading positions', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Update when window changes
     */
    public function updatedSelectedWindow(): void
    {
        $this->refreshData();
    }

    // Helper methods for formatting
    public function formatPremium($premium): string
    {
        if ($premium >= 1000000) {
            return '$' . number_format($premium / 1000000, 1) . 'M';
        } elseif ($premium >= 1000) {
            return '$' . number_format($premium / 1000, 1) . 'K';
        }
        return '$' . number_format($premium, 0);
    }

    public function formatAggressiveness($aggressiveness): string
    {
        return number_format($aggressiveness * 100, 0) . '%';
    }

    public function getConfidenceColor($confidence): string
    {
        return match ($confidence) {
            'HIGH' => 'text-emerald-400',
            'MEDIUM' => 'text-yellow-400',
            'LOW' => 'text-red-400',
            default => 'text-slate-400'
        };
    }

    public function getClassificationColor($classification): string
    {
        return match ($classification) {
            'BUY' => 'text-emerald-400 bg-emerald-900/20',
            'SELL' => 'text-red-400 bg-red-900/20',
            'MID', 'MID_LEAN_BUY', 'MID_LEAN_SELL' => 'text-yellow-400 bg-yellow-900/20',
            default => 'text-slate-400 bg-slate-900/20'
        };
    }

    public function getAggressivenessBarColor($aggressiveness): string
    {
        if ($aggressiveness >= 0.8) return 'bg-emerald-500';
        if ($aggressiveness >= 0.6) return 'bg-blue-500';
        if ($aggressiveness >= 0.4) return 'bg-yellow-500';
        if ($aggressiveness >= 0.2) return 'bg-orange-500';
        return 'bg-red-500';
    }

    public function render()
    {
        return view('livewire.advanced-tape-flow-monitor');
    }
}
