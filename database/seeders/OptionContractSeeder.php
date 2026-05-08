<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Symbol;
use App\Services\SchwabOptionChainService;
use Illuminate\Database\Seeder;

class OptionContractSeeder extends Seeder
{
    public function __construct(
        private readonly SchwabOptionChainService $optionChainService,
    ) {}

    public function run(): void
    {
        // Seed options for major symbols
        $tickers = ['SPY', 'QQQ', 'NVDA', 'AAPL', 'TSLA'];

        foreach ($tickers as $ticker) {
            $symbol = Symbol::where('ticker', $ticker)->first();
            
            if (!$symbol) {
                continue;
            }

            // Get next 3 expirations
            $expirations = $this->optionChainService->getExpirationDates($ticker)->take(3);

            foreach ($expirations as $expiration) {
                $chainData = $this->optionChainService->getOptionChain($ticker, $expiration);
                
                if ($chainData) {
                    $this->optionChainService->storeOptionChain($symbol, $chainData);
                }
            }
        }
    }
}
