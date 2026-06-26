<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ContractSnapshot;
use Carbon\Carbon;

class RecalculateSnapshotPremiums extends Command
{
    protected $signature = 'snapshot:recalculate-premiums
                            {--date= : Date to recalculate (Y-m-d format, default: today)}';

    protected $description = 'Recalculate buy/sell premiums for snapshots with zero premiums';

    public function handle()
    {
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::now();

        $startOfDay = $date->copy()->startOfDay();
        $endOfDay = $date->copy()->endOfDay();

        $this->info("Recalculating premiums for snapshots on {$date->toDateString()}");

        // Get all snapshots for the day grouped by symbol
        $snapshots = ContractSnapshot::whereBetween('snapshot_at', [$startOfDay, $endOfDay])
            ->orderBy('symbol')
            ->orderBy('snapshot_at')
            ->get()
            ->groupBy('symbol');

        $totalSymbols = $snapshots->count();
        $this->info("Processing {$totalSymbols} symbols...");

        $bar = $this->output->createProgressBar($totalSymbols);
        $bar->start();

        $updated = 0;

        foreach ($snapshots as $symbol => $symbolSnapshots) {
            $cumulativeBuy = 0;
            $cumulativeSell = 0;
            $cumulativeTotal = 0;
            $previousVolume = 0;

            foreach ($symbolSnapshots as $snapshot) {
                // Calculate volume change
                $volumeChange = $snapshot->total_volume - $previousVolume;

                if ($volumeChange > 0 && $snapshot->last_price > 0) {
                    // Calculate aggressiveness using hybrid approach
                    $bid = $snapshot->bid ?? 0;
                    $ask = $snapshot->ask ?? 0;
                    $spread = $ask - $bid;

                    // Start with price-based aggressiveness
                    $priceAggressiveness = 0.5;
                    if ($spread > 0) {
                        $priceAggressiveness = ($snapshot->last_price - $bid) / $spread;
                        $priceAggressiveness = max(0, min(1, $priceAggressiveness));
                    }

                    // Add delta-based bias for more realistic flow estimation
                    $delta = abs($snapshot->delta ?? 0.5);

                    // For CALLS: Higher delta (ITM) = more likely to be bought
                    // For PUTS: Higher delta (ITM) = more likely to be bought
                    $deltaAggressiveness = $snapshot->type === 'C'
                        ? (0.3 + $delta * 0.4)  // CALLS: 0.3 to 0.7 range
                        : (0.7 - $delta * 0.4); // PUTS: 0.7 to 0.3 range

                    // Blend: 70% price-based, 30% delta-based
                    $aggressiveness = ($priceAggressiveness * 0.7) + ($deltaAggressiveness * 0.3);

                    // Calculate incremental premium using weighted approach
                    $incrementalPremium = round($snapshot->last_price * $volumeChange * 100);
                    $incrementalBuy = round($incrementalPremium * $aggressiveness);
                    $incrementalSell = round($incrementalPremium * (1 - $aggressiveness));

                    // Accumulate
                    $cumulativeTotal += $incrementalPremium;
                    $cumulativeBuy += $incrementalBuy;
                    $cumulativeSell += $incrementalSell;
                }

                // Update snapshot
                $snapshot->update([
                    'total_premium' => $cumulativeTotal,
                    'buy_premium' => $cumulativeBuy,
                    'sell_premium' => $cumulativeSell,
                    'net_premium' => $cumulativeBuy - $cumulativeSell,
                ]);

                $previousVolume = $snapshot->total_volume;
                $updated++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("✅ Updated {$updated} snapshots across {$totalSymbols} symbols");

        return 0;
    }
}
