<?php

namespace App\Console\Commands;

use App\Models\Symbol;
use App\Services\SchwabAuthService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;

class SyncSymbols extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schwab:sync-symbols {--force : Force full sync}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync all available symbols and indices from Schwab API to database';

    private int $synced = 0;
    private int $updated = 0;
    private int $failed = 0;

    /**
     * Execute the console command.
     */
    public function handle(SchwabAuthService $authService)
    {
        $this->info('🔄 Starting Symbol Synchronization...');
        $this->newLine();

        // Get access token
        $token = $authService->getAccessToken();

        if (!$token) {
            $this->error('✗ No access token available');
            $this->warn('Please authenticate first by visiting: /auth/schwab/redirect');
            return 1;
        }

        // Sync major indices
        $this->syncIndices($token);

        // Sync instruments (stocks)
        $this->syncInstruments($token);

        // Summary
        $this->newLine();
        $this->info('✅ Synchronization Complete!');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Synced', $this->synced],
                ['Updated', $this->updated],
                ['Failed', $this->failed],
            ]
        );

        return 0;
    }

    private function syncIndices(string $token)
    {
        $this->info('📊 Syncing Major Indices...');

        $indices = [
            ['symbol' => '$DJI', 'name' => 'Dow Jones Industrial Average', 'exchange' => 'INDEX'],
            ['symbol' => '$COMPX', 'name' => 'NASDAQ Composite', 'exchange' => 'INDEX'],
            ['symbol' => '$SPX', 'name' => 'S&P 500 Index', 'exchange' => 'INDEX'],
            ['symbol' => '$RUT', 'name' => 'Russell 2000 Index', 'exchange' => 'INDEX'],
            ['symbol' => '$VIX', 'name' => 'CBOE Volatility Index', 'exchange' => 'INDEX'],
            ['symbol' => '$NDX', 'name' => 'NASDAQ-100 Index', 'exchange' => 'INDEX'],
        ];

        $bar = $this->output->createProgressBar(count($indices));
        $bar->start();

        foreach ($indices as $indexData) {
            try {
                Symbol::updateOrCreate(
                    ['ticker' => $indexData['symbol']],
                    [
                        'name' => $indexData['name'],
                        'exchange' => $indexData['exchange'],
                        'asset_type' => 'INDEX',
                        'is_active' => true,
                    ]
                );
                $this->synced++;
            } catch (\Exception $e) {
                $this->failed++;
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
    }

    private function syncInstruments(string $token)
    {
        $this->info('🔍 Syncing Instruments from Schwab API...');

        // Comprehensive list of symbols across all sectors and market caps
        $symbolLists = [
            // Mega Cap Tech
            'mega_tech' => ['AAPL', 'MSFT', 'GOOGL', 'GOOG', 'AMZN', 'META', 'NVDA', 'TSLA', 'AVGO', 'ORCL', 'ADBE', 'CRM', 'CSCO', 'ACN', 'AMD', 'INTC', 'IBM', 'QCOM', 'TXN', 'INTU'],
            
            // Large Cap Tech
            'large_tech' => ['NOW', 'AMAT', 'MU', 'ADI', 'LRCX', 'KLAC', 'SNPS', 'CDNS', 'MCHP', 'FTNT', 'PANW', 'CRWD', 'ZS', 'DDOG', 'NET', 'SNOW', 'PLTR', 'COIN', 'SQ', 'SHOP'],
            
            // Semiconductors
            'semiconductors' => ['TSM', 'ASML', 'NXPI', 'MRVL', 'ON', 'MPWR', 'SWKS', 'QRVO', 'WOLF', 'SLAB', 'CRUS', 'SYNA', 'RMBS', 'SITM', 'FORM'],
            
            // Software & Cloud
            'software' => ['MSFT', 'ORCL', 'SAP', 'ADBE', 'CRM', 'NOW', 'WDAY', 'TEAM', 'ZM', 'DOCU', 'TWLO', 'OKTA', 'ZI', 'BILL', 'ESTC', 'MDB', 'CFLT', 'GTLB'],
            
            // Finance - Banks
            'banks' => ['JPM', 'BAC', 'WFC', 'C', 'USB', 'PNC', 'TFC', 'COF', 'BK', 'STT', 'FITB', 'HBAN', 'RF', 'CFG', 'KEY', 'ZION', 'CMA', 'WTFC', 'FHN', 'SNV'],
            
            // Finance - Investment
            'investment' => ['GS', 'MS', 'BLK', 'SCHW', 'SPGI', 'MCO', 'ICE', 'CME', 'NDAQ', 'MSCI', 'MKTX', 'IBKR', 'VIRT', 'LPLA'],
            
            // Finance - Insurance
            'insurance' => ['BRK.B', 'PGR', 'TRV', 'ALL', 'AIG', 'MET', 'PRU', 'AFL', 'AJG', 'MMC', 'AON', 'WTW', 'BRO', 'RYAN'],
            
            // Finance - Payments
            'payments' => ['V', 'MA', 'AXP', 'PYPL', 'FIS', 'FISV', 'GPN', 'SQ', 'COIN', 'AFRM', 'SOFI'],
            
            // Healthcare - Pharma
            'pharma' => ['JNJ', 'PFE', 'ABBV', 'MRK', 'LLY', 'BMY', 'AMGN', 'GILD', 'REGN', 'VRTX', 'BIIB', 'MRNA', 'BNTX', 'SGEN', 'ALNY', 'IONS', 'EXEL', 'INCY', 'JAZZ', 'UTHR'],
            
            // Healthcare - Biotech
            'biotech' => ['AMGN', 'GILD', 'REGN', 'VRTX', 'BIIB', 'ILMN', 'ALXN', 'BMRN', 'SGEN', 'TECH', 'SRPT', 'NBIX', 'RARE', 'FOLD', 'ARWR', 'BLUE', 'CRSP', 'EDIT', 'NTLA'],
            
            // Healthcare - Devices
            'medtech' => ['TMO', 'ABT', 'DHR', 'SYK', 'BSX', 'MDT', 'EW', 'ISRG', 'ZBH', 'BAX', 'BDX', 'HOLX', 'ALGN', 'DXCM', 'PODD', 'TDOC', 'VEEV'],
            
            // Consumer - Retail
            'retail' => ['WMT', 'HD', 'COST', 'TGT', 'LOW', 'TJX', 'ROST', 'DG', 'DLTR', 'BBY', 'ULTA', 'FIVE', 'BURL', 'OLLI', 'AEO', 'ANF', 'URBN', 'GPS'],
            
            // Consumer - Restaurants
            'restaurants' => ['MCD', 'SBUX', 'CMG', 'YUM', 'QSR', 'DPZ', 'DRI', 'TXRH', 'EAT', 'BLMN', 'CAKE', 'DENN', 'JACK', 'PZZA', 'WING', 'SHAK'],
            
            // Consumer - Brands
            'consumer_brands' => ['NKE', 'LULU', 'DECK', 'CROX', 'SKX', 'UAA', 'VFC', 'HBI', 'PVH', 'RL', 'CPRI'],
            
            // Consumer - Discretionary
            'discretionary' => ['AMZN', 'TSLA', 'DIS', 'NFLX', 'BKNG', 'ABNB', 'UBER', 'LYFT', 'DASH', 'SPOT', 'ROKU', 'MTCH', 'BMBL', 'ETSY', 'W', 'CHWY', 'CVNA'],
            
            // Energy - Oil & Gas
            'energy_oil' => ['XOM', 'CVX', 'COP', 'SLB', 'EOG', 'MPC', 'PSX', 'VLO', 'OXY', 'HAL', 'BKR', 'DVN', 'FANG', 'MRO', 'APA', 'HES', 'OVV', 'CTRA'],
            
            // Energy - Renewables
            'energy_renewable' => ['NEE', 'DUK', 'SO', 'D', 'AEP', 'EXC', 'SRE', 'XEL', 'ED', 'ES', 'FE', 'ETR', 'ENPH', 'SEDG', 'RUN', 'NOVA', 'FSLR', 'SPWR'],
            
            // Industrials
            'industrials' => ['BA', 'HON', 'UNP', 'UPS', 'RTX', 'LMT', 'CAT', 'DE', 'GE', 'MMM', 'EMR', 'ETN', 'ITW', 'PH', 'CMI', 'PCAR', 'ROK', 'DOV', 'FTV', 'XYL'],
            
            // Materials
            'materials' => ['LIN', 'APD', 'SHW', 'ECL', 'DD', 'NEM', 'FCX', 'NUE', 'VMC', 'MLM', 'PPG', 'CTVA', 'IFF', 'CE', 'ALB', 'EMN', 'MOS', 'CF'],
            
            // Real Estate
            'reits' => ['AMT', 'PLD', 'CCI', 'EQIX', 'PSA', 'DLR', 'WELL', 'AVB', 'EQR', 'SPG', 'O', 'VICI', 'INVH', 'EXR', 'VTR', 'ARE', 'MAA', 'UDR'],
            
            // Utilities
            'utilities' => ['NEE', 'DUK', 'SO', 'D', 'AEP', 'EXC', 'SRE', 'XEL', 'WEC', 'ED', 'ES', 'FE', 'ETR', 'PPL', 'AES', 'CMS', 'DTE', 'PEG'],
            
            // Communication Services
            'communication' => ['GOOGL', 'META', 'DIS', 'NFLX', 'CMCSA', 'T', 'VZ', 'TMUS', 'CHTR', 'EA', 'TTWO', 'ATVI', 'RBLX', 'U', 'PINS', 'SNAP', 'TWTR'],
            
            // ETFs - Broad Market
            'etf_broad' => ['SPY', 'QQQ', 'IWM', 'DIA', 'VTI', 'VOO', 'VEA', 'VWO', 'IEFA', 'EFA', 'IEMG', 'AGG', 'BND', 'LQD', 'HYG', 'TLT', 'IEF', 'SHY'],
            
            // ETFs - Sector
            'etf_sector' => ['XLK', 'XLF', 'XLV', 'XLE', 'XLI', 'XLP', 'XLY', 'XLU', 'XLB', 'XLRE', 'XLC', 'VGT', 'VFH', 'VHT', 'VDE', 'VIS', 'VDC', 'VCR'],
            
            // ETFs - Thematic
            'etf_thematic' => ['ARK', 'ARKK', 'ARKW', 'ARKG', 'ARKF', 'ARKQ', 'ICLN', 'TAN', 'LIT', 'BOTZ', 'ROBO', 'FINX', 'HACK', 'CIBR', 'CLOU', 'SKYY'],
            
            // Crypto & Blockchain
            'crypto' => ['COIN', 'MSTR', 'RIOT', 'MARA', 'CLSK', 'BITF', 'HUT', 'BITO', 'GBTC', 'ETHE'],
            
            // Chinese ADRs
            'china' => ['BABA', 'JD', 'PDD', 'BIDU', 'NIO', 'XPEV', 'LI', 'BILI', 'TME', 'IQ', 'NTES', 'VIPS', 'ZTO', 'YMM', 'DIDI'],
            
            // Small Cap Growth
            'small_cap' => ['CRWD', 'ZS', 'DDOG', 'NET', 'SNOW', 'PLTR', 'U', 'RBLX', 'DASH', 'ABNB', 'COIN', 'RIVN', 'LCID', 'SOFI', 'HOOD'],
        ];

        $allSymbols = collect($symbolLists)->flatten()->unique()->values();

        $bar = $this->output->createProgressBar($allSymbols->count());
        $bar->start();

        // Process in batches of 10 using quotes endpoint
        $allSymbols->chunk(10)->each(function ($chunk) use ($token, $bar) {
            $symbols = $chunk->join(',');
            
            $response = Http::withToken($token)
                ->timeout(30)
                ->get(config('services.schwab.base_url') . '/marketdata/v1/quotes', [
                    'symbols' => $symbols,
                    'fields' => 'quote,fundamental'
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                foreach ($data as $ticker => $symbolData) {
                    $this->storeSymbolFromQuote($ticker, $symbolData);
                    $bar->advance();
                }
            } else {
                // If batch fails, try individual symbols
                foreach ($chunk as $symbol) {
                    $this->syncSingleSymbol($token, $symbol);
                    $bar->advance();
                }
            }

            // Rate limiting - wait 100ms between batches
            usleep(100000);
        });

        $bar->finish();
        $this->newLine(2);
    }

    private function syncSingleSymbol(string $token, string $ticker)
    {
        try {
            $response = Http::withToken($token)
                ->timeout(10)
                ->get(config('services.schwab.base_url') . '/marketdata/v1/quotes', [
                    'symbols' => $ticker,
                    'fields' => 'quote,fundamental'
                ]);

            if ($response->successful()) {
                $data = $response->json();
                if (!empty($data) && isset($data[$ticker])) {
                    $this->storeSymbolFromQuote($ticker, $data[$ticker]);
                }
            } else {
                $this->failed++;
            }
        } catch (\Exception $e) {
            $this->failed++;
        }
    }

    private function storeSymbolFromQuote(string $ticker, array $data)
    {
        try {
            // Determine exchange from quote data or infer from ticker
            $exchange = $this->determineExchange($ticker, $data);
            
            $symbolData = [
                'ticker' => $ticker,
                'name' => $data['quote']['description'] ?? $data['fundamental']['companyName'] ?? $ticker,
                'exchange' => $exchange,
                'asset_type' => $data['assetMainType'] ?? 'EQUITY',
                'is_active' => true,
            ];

            // Add optional fields if available
            if (isset($data['fundamental'])) {
                $symbolData['sector'] = $data['fundamental']['sector'] ?? null;
                $symbolData['industry'] = $data['fundamental']['subIndustry'] ?? null;
            }

            $symbol = Symbol::updateOrCreate(
                ['ticker' => $symbolData['ticker']],
                $symbolData
            );

            if ($symbol->wasRecentlyCreated) {
                $this->synced++;
            } else {
                $this->updated++;
            }
        } catch (\Exception $e) {
            $this->failed++;
        }
    }

    private function determineExchange(string $ticker, array $data): string
    {
        // Try to get from API response first
        if (isset($data['quote']['exchangeName'])) {
            return $data['quote']['exchangeName'];
        }
        
        if (isset($data['quote']['exchange'])) {
            return $data['quote']['exchange'];
        }

        // Infer from ticker patterns
        if (str_starts_with($ticker, '$')) {
            return 'INDEX';
        }

        // Common NASDAQ stocks
        $nasdaqStocks = ['AAPL', 'MSFT', 'GOOGL', 'GOOG', 'AMZN', 'META', 'NVDA', 'TSLA', 'AVGO', 'ASML', 
                        'COST', 'NFLX', 'AMD', 'INTC', 'QCOM', 'CSCO', 'ADBE', 'TXN', 'INTU', 'CMCSA',
                        'CRWD', 'ZS', 'DDOG', 'NET', 'SNOW', 'PLTR', 'COIN', 'MSTR', 'RIOT', 'MARA'];
        
        if (in_array($ticker, $nasdaqStocks)) {
            return 'NASDAQ';
        }

        // ETFs
        if (in_array($ticker, ['SPY', 'QQQ', 'IWM', 'DIA', 'VTI', 'VOO', 'XLK', 'XLF', 'XLE', 'XLV'])) {
            return in_array($ticker, ['QQQ']) ? 'NASDAQ' : 'NYSE';
        }

        // Default to NYSE for most stocks
        return 'NYSE';
    }
}
