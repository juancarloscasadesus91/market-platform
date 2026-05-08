<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Symbol;
use App\Services\SchwabAuthService;
use Illuminate\Support\Facades\Http;

class UpdateMarketQuotes extends Command
{
    protected $signature = 'market:update-quotes';
    protected $description = 'Update market quotes from Schwab API in real-time';

    public function handle()
    {
        $this->info('Updating market quotes...');
        
        // Map API ticker to DB ticker
        $tickerMap = [
            '$SPX' => 'SPX',
            'SPY' => 'SPY',
            'QQQ' => 'QQQ',
            'NVDA' => 'NVDA',
            'AAPL' => 'AAPL',
        ];
        
        $apiTickers = array_keys($tickerMap);
        
        try {
            $schwabService = SchwabAuthService::make();
            $token = $schwabService->getAccessToken();
            
            if (!$token) {
                $this->error('No Schwab API token available');
                return 1;
            }
            
            $response = Http::withToken($token)
                ->get(config('services.schwab.base_url') . '/marketdata/v1/quotes', [
                    'symbols' => implode(',', $apiTickers),
                    'fields' => 'quote'
                ]);
            
            if (!$response->successful()) {
                $this->error('Failed to fetch quotes from Schwab API');
                return 1;
            }
            
            $data = $response->json();
            
            foreach ($apiTickers as $apiTicker) {
                if (!isset($data[$apiTicker]['quote'])) {
                    $this->warn("No quote data for {$apiTicker}");
                    continue;
                }
                
                $quoteData = $data[$apiTicker]['quote'];
                $dbTicker = $tickerMap[$apiTicker];
                $symbol = Symbol::where('ticker', $dbTicker)->first();
                
                if (!$symbol) {
                    continue;
                }
                
                \App\Models\Quote::updateOrCreate(
                    ['symbol_id' => $symbol->id],
                    [
                        'symbol_id' => $symbol->id,
                        'last_price' => $quoteData['lastPrice'] ?? 0,
                        'bid' => $quoteData['bidPrice'] ?? 0,
                        'ask' => $quoteData['askPrice'] ?? 0,
                        'volume' => $quoteData['totalVolume'] ?? 0,
                        'high' => $quoteData['highPrice'] ?? 0,
                        'low' => $quoteData['lowPrice'] ?? 0,
                        'open' => $quoteData['openPrice'] ?? 0,
                        'close' => $quoteData['closePrice'] ?? 0,
                        'change' => $quoteData['netChange'] ?? 0,
                        'change_percent' => $quoteData['netPercentChange'] ?? 0,
                    ]
                );
                
                $this->info("Updated {$dbTicker}: \${$quoteData['lastPrice']}");
            }
            
            $this->info('Market quotes updated successfully!');
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            return 1;
        }
    }
}
