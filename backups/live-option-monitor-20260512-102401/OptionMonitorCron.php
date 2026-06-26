<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ContractSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use App\Services\SchwabTraderAuthService;

class OptionMonitorCron extends Command
{
    protected $signature = 'option:monitor-cron
                            {--underlying=SPX : Underlying symbol to monitor}
                            {--max-dte=5 : Maximum DTE to monitor}
                            {--min-volume=50 : Minimum volume filter}
                            {--atm-range=10 : Number of strikes around ATM}';

    protected $description = 'Continuously monitor option contracts and store snapshots';

    protected $baseUrl = 'https://api.schwabapi.com';

    public function __construct()
    {
        parent::__construct();
    }

    protected function getAccessToken()
    {
        $authService = SchwabTraderAuthService::make();
        return $authService->getAccessToken();
    }

    public function handle()
    {
        $underlying = $this->option('underlying');
        $maxDTE = (int) $this->option('max-dte');
        $minVolume = (int) $this->option('min-volume');
        $atmRange = (int) $this->option('atm-range');

        $this->info("Starting option monitor for {$underlying}");
        $this->info("Max DTE: {$maxDTE}, Min Volume: {$minVolume}, ATM Range: ±{$atmRange}");

        try {
            $token = $this->getAccessToken();
            if (!$token) {
                $this->error("No Schwab access token found. Run schwab:refresh-token first.");
                return 1;
            }

            // Get underlying price from quote endpoint
            $quoteResponse = Http::withToken($token)
                ->get("{$this->baseUrl}/marketdata/v1/\$SPX/quotes");

            if (!$quoteResponse->successful()) {
                $this->error("Failed to get underlying price");
                return 1;
            }

            $quoteData = $quoteResponse->json();
            $underlyingPrice = $quoteData['$SPX']['quote']['lastPrice'] ??
                             $quoteData['$SPX']['quote']['mark'] ?? null;

            if (!$underlyingPrice) {
                $this->error("Could not extract underlying price");
                return 1;
            }

            $this->info("Underlying price: \${$underlyingPrice}");

            // Fetch option chain
            $fromDate = now()->format('Y-m-d');
            $toDate = now()->addDays($maxDTE)->format('Y-m-d');

            $apiSymbol = '$' . $underlying;
            $chainResponse = Http::withToken($token)
                ->get("{$this->baseUrl}/marketdata/v1/chains", [
                    'symbol' => $apiSymbol,
                    'fromDate' => $fromDate,
                    'toDate' => $toDate,
                    'includeUnderlyingQuote' => 'true',
                ]);

            if (!$chainResponse->successful()) {
                $this->error("Failed to fetch option chain: " . $chainResponse->body());
                return 1;
            }

            $chainData = $chainResponse->json();

            $snapshotTime = now();
            $contractsProcessed = 0;
            $contractsStored = 0;

            // Process both calls and puts
            foreach (['callExpDateMap' => 'C', 'putExpDateMap' => 'P'] as $mapKey => $type) {
                if (!isset($chainData[$mapKey])) continue;

                foreach ($chainData[$mapKey] as $expDate => $strikeMap) {
                    $dateOnly = explode(':', $expDate)[0];
                    $expirationDate = Carbon::parse($dateOnly);
                    $dte = now()->startOfDay()->diffInDays($expirationDate->startOfDay());

                    if ($dte > $maxDTE) continue;

                    // Determine ATM range based on DTE
                    $currentATMRange = $this->getATMRangeForDTE($dte, $atmRange);

                    // Get strikes around ATM
                    $strikes = array_keys($strikeMap);
                    sort($strikes);

                    $atmStrike = $this->findClosestStrike($strikes, $underlyingPrice);
                    $atmIndex = array_search($atmStrike, $strikes);

                    $startIndex = max(0, $atmIndex - $currentATMRange);
                    $endIndex = min(count($strikes) - 1, $atmIndex + $currentATMRange);

                    for ($i = $startIndex; $i <= $endIndex; $i++) {
                        $strike = $strikes[$i];
                        $contracts = $strikeMap[$strike] ?? [];

                        foreach ($contracts as $contract) {
                            $contractsProcessed++;

                            $volume = $contract['totalVolume'] ?? 0;

                            // Apply volume filter
                            if ($volume < $minVolume) continue;

                            // Get previous snapshot to calculate changes
                            $previousSnapshot = ContractSnapshot::getLatest(
                                $contract['symbol'],
                                $snapshotTime->copy()->subHours(2)
                            );

                            $volumeChange = $previousSnapshot
                                ? $volume - $previousSnapshot->total_volume
                                : 0;

                            // Only store if there's activity or it's a new contract
                            if ($volumeChange > 0 || !$previousSnapshot) {
                                ContractSnapshot::create([
                                    'symbol' => $contract['symbol'],
                                    'underlying' => $underlying,
                                    'expiration_date' => $expirationDate,
                                    'dte' => $dte,
                                    'strike' => $strike,
                                    'type' => $type,
                                    'total_volume' => $volume,
                                    'volume_change' => $volumeChange,
                                    'total_premium' => 0, // Will be updated by live monitor
                                    'buy_premium' => 0,
                                    'sell_premium' => 0,
                                    'net_premium' => 0,
                                    'last_price' => $contract['last'] ?? null,
                                    'bid' => $contract['bid'] ?? null,
                                    'ask' => $contract['ask'] ?? null,
                                    'mark' => $contract['mark'] ?? null,
                                    'delta' => $contract['delta'] ?? null,
                                    'gamma' => $contract['gamma'] ?? null,
                                    'theta' => $contract['theta'] ?? null,
                                    'vega' => $contract['vega'] ?? null,
                                    'iv' => $contract['volatility'] ?? null,
                                    'total_trades' => 0,
                                    'snapshot_at' => $snapshotTime,
                                ]);

                                $contractsStored++;
                            }
                        }
                    }
                }
            }

            $this->info("Processed {$contractsProcessed} contracts, stored {$contractsStored} snapshots");

            // Clean old snapshots
            $deleted = ContractSnapshot::cleanOld(7);
            if ($deleted > 0) {
                $this->info("Cleaned {$deleted} old snapshots (>7 days)");
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Error: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return 1;
        }
    }

    protected function getATMRangeForDTE(int $dte, int $baseRange): int
    {
        if ($dte <= 2) {
            return $baseRange; // Full range for 0-2 DTE
        } elseif ($dte <= 5) {
            return (int) ($baseRange * 0.7); // 70% range for 3-5 DTE
        } else {
            return (int) ($baseRange * 0.5); // 50% range for 6+ DTE
        }
    }

    protected function findClosestStrike(array $strikes, float $price): float
    {
        $closest = $strikes[0];
        $minDiff = abs($price - $closest);

        foreach ($strikes as $strike) {
            $diff = abs($price - $strike);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $strike;
            }
        }

        return $closest;
    }
}
