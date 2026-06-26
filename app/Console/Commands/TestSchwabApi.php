<?php

namespace App\Console\Commands;

use App\Services\SchwabAuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestSchwabApi extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schwab:test {endpoint=quotes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test Schwab API connection and endpoints';

    /**
     * Execute the console command.
     */
    public function handle(SchwabAuthService $authService)
    {
        $this->info('🔍 Testing Schwab API Connection...');
        $this->newLine();

        // Check configuration
        $this->info('📋 Configuration:');
        $this->line('  App Key: ' . (config('services.schwab.app_key') ? '✓ Set' : '✗ Missing'));
        $this->line('  App Secret: ' . (config('services.schwab.app_secret') ? '✓ Set' : '✗ Missing'));
        $this->line('  Base URL: ' . config('services.schwab.base_url'));
        $this->newLine();

        // Get access token
        $this->info('🔑 Getting Access Token...');
        $token = $authService->getAccessToken();

        if (!$token) {
            $this->error('✗ No access token available');
            $this->warn('Please authenticate first by visiting: /auth/schwab/redirect');
            return 1;
        }

        $this->info('✓ Access token obtained');
        $this->newLine();

        // Test endpoint based on argument
        $endpoint = $this->argument('endpoint');
        
        match($endpoint) {
            'quotes' => $this->testQuotes($token),
            'chains' => $this->testOptionChains($token),
            'movers' => $this->testMovers($token),
            'markets' => $this->testMarkets($token),
            default => $this->testQuotes($token),
        };

        return 0;
    }

    private function testQuotes(string $token)
    {
        $this->info('📊 Testing Quotes Endpoint...');
        $symbols = ['AAPL', 'SPY', 'TSLA'];
        
        foreach ($symbols as $symbol) {
            $this->line("  Fetching quote for {$symbol}...");
            
            $response = Http::withToken($token)
                ->get(config('services.schwab.base_url') . '/marketdata/v1/quotes', [
                    'symbols' => $symbol,
                    'fields' => 'quote,fundamental'
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->info("  ✓ {$symbol}: Success");
                
                if (isset($data[$symbol]['quote'])) {
                    $quote = $data[$symbol]['quote'];
                    $this->line("    Price: \${$quote['lastPrice']}");
                    $this->line("    Change: {$quote['netChange']} ({$quote['netPercentChange']}%)");
                    $this->line("    Volume: " . number_format($quote['totalVolume']));
                }
            } else {
                $this->error("  ✗ {$symbol}: Failed - " . $response->status());
                $this->line("    " . $response->body());
            }
            
            $this->newLine();
        }
    }

    private function testOptionChains(string $token)
    {
        $this->info('📈 Testing Option Chains Endpoint...');
        $symbol = 'SPY';
        
        $this->line("  Fetching option chain for {$symbol}...");
        
        $response = Http::withToken($token)
            ->get(config('services.schwab.base_url') . '/marketdata/v1/chains', [
                'symbol' => $symbol,
                'contractType' => 'ALL',
                'strikeCount' => 10,
            ]);

        if ($response->successful()) {
            $data = $response->json();
            $this->info("  ✓ Success");
            $this->line("    Symbol: {$data['symbol']}");
            $this->line("    Underlying Price: \${$data['underlyingPrice']}");
            $this->line("    Expirations: " . count($data['callExpDateMap'] ?? []));
        } else {
            $this->error("  ✗ Failed - " . $response->status());
            $this->line("    " . $response->body());
        }
        
        $this->newLine();
    }

    private function testMovers(string $token)
    {
        $this->info('🚀 Testing Movers Endpoint...');
        
        $indices = ['$DJI', '$COMPX', '$SPX'];
        
        foreach ($indices as $index) {
            $this->line("  Fetching movers for {$index}...");
            
            $response = Http::withToken($token)
                ->get(config('services.schwab.base_url') . "/marketdata/v1/movers/{$index}", [
                    'sort' => 'PERCENT_CHANGE_UP',
                    'frequency' => 0
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $this->info("  ✓ Success - " . count($data) . " movers found");
                
                if (!empty($data)) {
                    $this->line("    Top Mover: {$data[0]['symbol']} ({$data[0]['netPercentChange']}%)");
                }
            } else {
                $this->error("  ✗ Failed - " . $response->status());
            }
            
            $this->newLine();
        }
    }

    private function testMarkets(string $token)
    {
        $this->info('🏛️ Testing Markets Endpoint...');
        
        $this->line("  Fetching market hours...");
        
        $response = Http::withToken($token)
            ->get(config('services.schwab.base_url') . '/marketdata/v1/markets', [
                'markets' => 'equity,option',
                'date' => now()->format('Y-m-d')
            ]);

        if ($response->successful()) {
            $data = $response->json();
            $this->info("  ✓ Success");
            
            foreach ($data as $market => $info) {
                $this->line("    {$market}: " . ($info['isOpen'] ? 'Open' : 'Closed'));
            }
        } else {
            $this->error("  ✗ Failed - " . $response->status());
            $this->line("    " . $response->body());
        }
        
        $this->newLine();
    }
}
