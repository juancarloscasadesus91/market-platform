<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TradingJournalEntry;
use Carbon\Carbon;

class TradingJournalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Clear existing entries
        TradingJournalEntry::truncate();

        $startDate = Carbon::parse('2026-05-02'); // Hoy
        $endDate = Carbon::parse('2026-12-31');
        $capital = 230.00; // Capital inicial
        $numTrades = 2; // 2 trades por día

        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            // Solo días de semana (lunes a viernes)
            if ($currentDate->isWeekday()) {
                // Generar profit percent aleatorio entre -2% y 5%
                // Más probabilidad de ganancias pequeñas
                $profitPercent = $this->generateRealisticProfit();

                $entry = new TradingJournalEntry([
                    'fecha' => $currentDate->format('Y-m-d'),
                    'capital_inicial' => $capital,
                    'num_trades' => $numTrades,
                    'profit_percent' => $profitPercent,
                ]);

                // Calcular profit usando la fórmula
                $entry->calculateProfit();
                
                // Capital real es igual al capital final (sin ajustes)
                $entry->capital_real = $entry->capital_final;
                
                $entry->save();

                // El capital del siguiente día es el capital final de hoy
                $capital = $entry->capital_final;
            }

            $currentDate->addDay();
        }

        $this->command->info('Trading journal seeded successfully!');
        $this->command->info('Generated entries from 2026-05-02 to 2026-12-31 (weekdays only)');
        $this->command->info('Starting capital: $230.00');
        $this->command->info('Trades per day: 2');
        $this->command->info('Total entries: ' . TradingJournalEntry::count());
    }

    /**
     * Generate realistic profit percentage
     * More probability of small gains, occasional losses
     */
    private function generateRealisticProfit(): float
    {
        $random = mt_rand(1, 100);

        if ($random <= 15) {
            // 15% chance of loss (-2% to -0.1%)
            return round(mt_rand(-200, -10) / 100, 2);
        } elseif ($random <= 50) {
            // 35% chance of small gain (0.1% to 1%)
            return round(mt_rand(10, 100) / 100, 2);
        } elseif ($random <= 85) {
            // 35% chance of medium gain (1% to 3%)
            return round(mt_rand(100, 300) / 100, 2);
        } else {
            // 15% chance of big gain (3% to 5%)
            return round(mt_rand(300, 500) / 100, 2);
        }
    }
}
