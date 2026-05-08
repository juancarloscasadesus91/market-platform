<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Symbol;
use Illuminate\Database\Seeder;

class SymbolSeeder extends Seeder
{
    public function run(): void
    {
        $symbols = [
            [
                'ticker' => 'SPX',
                'name' => 'S&P 500 Index',
                'exchange' => 'INDEX',
                'sector' => 'Index',
                'industry' => 'Market Index',
                'market_cap' => 0,
                'is_active' => true,
            ],
            [
                'ticker' => 'SPY',
                'name' => 'SPDR S&P 500 ETF Trust',
                'exchange' => 'NYSE',
                'sector' => 'ETF',
                'industry' => 'Exchange Traded Fund',
                'market_cap' => 450000000000,
                'is_active' => true,
            ],
            [
                'ticker' => 'QQQ',
                'name' => 'Invesco QQQ Trust',
                'exchange' => 'NASDAQ',
                'sector' => 'ETF',
                'industry' => 'Exchange Traded Fund',
                'market_cap' => 200000000000,
                'is_active' => true,
            ],
            [
                'ticker' => 'NVDA',
                'name' => 'NVIDIA Corporation',
                'exchange' => 'NASDAQ',
                'sector' => 'Technology',
                'industry' => 'Semiconductors',
                'market_cap' => 2100000000000,
                'is_active' => true,
            ],
            [
                'ticker' => 'AAPL',
                'name' => 'Apple Inc.',
                'exchange' => 'NASDAQ',
                'sector' => 'Technology',
                'industry' => 'Consumer Electronics',
                'market_cap' => 2900000000000,
                'is_active' => true,
            ],
            [
                'ticker' => 'TSLA',
                'name' => 'Tesla, Inc.',
                'exchange' => 'NASDAQ',
                'sector' => 'Consumer Cyclical',
                'industry' => 'Auto Manufacturers',
                'market_cap' => 780000000000,
                'is_active' => true,
            ],
            [
                'ticker' => 'MSFT',
                'name' => 'Microsoft Corporation',
                'exchange' => 'NASDAQ',
                'sector' => 'Technology',
                'industry' => 'Software',
                'market_cap' => 3100000000000,
                'is_active' => true,
            ],
            [
                'ticker' => 'AMZN',
                'name' => 'Amazon.com, Inc.',
                'exchange' => 'NASDAQ',
                'sector' => 'Consumer Cyclical',
                'industry' => 'Internet Retail',
                'market_cap' => 1800000000000,
                'is_active' => true,
            ],
            [
                'ticker' => 'GOOGL',
                'name' => 'Alphabet Inc.',
                'exchange' => 'NASDAQ',
                'sector' => 'Communication Services',
                'industry' => 'Internet Content & Information',
                'market_cap' => 1900000000000,
                'is_active' => true,
            ],
            [
                'ticker' => 'META',
                'name' => 'Meta Platforms, Inc.',
                'exchange' => 'NASDAQ',
                'sector' => 'Communication Services',
                'industry' => 'Internet Content & Information',
                'market_cap' => 1200000000000,
                'is_active' => true,
            ],
        ];

        foreach ($symbols as $symbolData) {
            Symbol::updateOrCreate(
                ['ticker' => $symbolData['ticker']],
                $symbolData
            );
        }
    }
}
