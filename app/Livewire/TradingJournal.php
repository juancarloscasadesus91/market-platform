<?php

declare(strict_types=1);

namespace App\Livewire;

use Livewire\Component;
use Livewire\WithPagination;
use App\Models\TradingJournalEntry;
use App\Models\JournalTrade;

class TradingJournal extends Component
{
    use WithPagination;

    public int $perPage = 10;
    public string $sortField = 'fecha';
    public string $sortDirection = 'asc';

    // Filtros
    public string $filterType = 'all'; // all, date_range, week
    public ?string $startDate = null;
    public ?string $endDate = null;
    public array $selectedWeeks = [];

    // Expanded rows
    public array $expandedRows = [];

    // Expanded row tabs (plan or trades)
    public array $expandedRowTabs = []; // ['entry_id' => 'plan' or 'trades']

    // Delete confirmation
    public bool $showDeleteModal = false;
    public ?int $entryToDelete = null;
    
    // Trade editing
    public array $editingTrades = []; // ['trade_id' => true/false]

    public function mount(): void
    {
        // Check if we need to seed data
        if (TradingJournalEntry::count() === 0) {
            \Artisan::call('db:seed', ['--class' => 'TradingJournalSeeder']);
        }
    }

    public function updatedFilterType(): void
    {
        // Reset filters when changing filter type
        $this->startDate = null;
        $this->endDate = null;
        $this->selectedWeeks = [];
        $this->resetPage();
    }

    public function updatedStartDate(): void
    {
        $this->resetPage();
    }

    public function updatedEndDate(): void
    {
        $this->resetPage();
    }

    public function addWeek(string $week): void
    {
        if (!in_array($week, $this->selectedWeeks)) {
            $this->selectedWeeks[] = $week;
            $this->resetPage();
        }
    }

    public function removeWeek(string $week): void
    {
        $this->selectedWeeks = array_values(array_filter($this->selectedWeeks, fn($w) => $w !== $week));
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->filterType = 'all';
        $this->startDate = null;
        $this->endDate = null;
        $this->selectedWeeks = [];
        $this->resetPage();
    }

    public function toggleRow(int $id): void
    {
        if (in_array($id, $this->expandedRows)) {
            $this->expandedRows = array_values(array_filter($this->expandedRows, fn($rowId) => $rowId !== $id));
            unset($this->expandedRowTabs[$id]);
        } else {
            $this->expandedRows[] = $id;
            $this->expandedRowTabs[$id] = 'plan'; // Default to plan tab
        }
    }

    public function setExpandedTab(int $id, string $tab): void
    {
        $this->expandedRowTabs[$id] = $tab;
    }

    public function updateCell(int $id, string $field, $value): void
    {
        $entry = TradingJournalEntry::find($id);

        if ($entry) {
            // Guardar el profit anterior para actualizar portfolio
            $oldProfitReal = $entry->profit_diario_real ?? 0;
            
            $entry->$field = is_numeric($value) ? (float)$value : $value;

            // Recalculate dependent fields for REAL
            if (in_array($field, ['capital_inicial_real', 'profit_percent_real', 'num_trades_real'])) {
                $entry->calculateRealProfit();
                
                // Actualizar portfolio: restar el profit anterior y sumar el nuevo
                if ($oldProfitReal != 0) {
                    \App\Models\PortfolioSetting::updatePortfolio((float)(-$oldProfitReal));
                }
                if ($entry->profit_diario_real != 0) {
                    \App\Models\PortfolioSetting::updatePortfolio((float)$entry->profit_diario_real);
                }
            }
            
            // Si se edita profit_diario_real manualmente, recalcular capital final y portfolio
            if ($field === 'profit_diario_real') {
                $entry->capital_final_real = $entry->capital_inicial_real + $entry->profit_diario_real;
                $entry->capital_real = $entry->capital_final_real;
                
                // Recalcular % profit basado en el nuevo profit diario
                if ($entry->capital_inicial_real > 0) {
                    $entry->profit_percent_real = ($entry->profit_diario_real / $entry->capital_inicial_real) * 100;
                }
                
                // Actualizar portfolio
                if ($oldProfitReal != 0) {
                    \App\Models\PortfolioSetting::updatePortfolio((float)(-$oldProfitReal));
                }
                if ($entry->profit_diario_real != 0) {
                    \App\Models\PortfolioSetting::updatePortfolio((float)$entry->profit_diario_real);
                }
            }

            // Recalculate dependent fields for PLAN
            if (in_array($field, ['capital_inicial_plan', 'profit_percent_plan', 'num_trades_plan'])) {
                $entry->calculatePlanProfit();
            }

            $entry->save();
        }
    }

    public function addEntry(): void
    {
        $lastEntry = TradingJournalEntry::orderBy('fecha', 'desc')->first();
        $lastCapital = $lastEntry ? $lastEntry->capital_final_real : 280;

        $entry = new TradingJournalEntry([
            'fecha' => now()->format('Y-m-d'),
            'capital_inicial_plan' => $lastCapital,
            'num_trades_plan' => 2,
            'profit_percent_plan' => 0,
            'capital_inicial_real' => $lastCapital,
            'num_trades_real' => 2,
            'profit_percent_real' => 0,
        ]);

        $entry->calculatePlanProfit();
        $entry->calculateRealProfit();
        // capital_real se calcula automáticamente en calculateRealProfit()
        $entry->save();
    }

    public function confirmDelete(int $id): void
    {
        $this->entryToDelete = $id;
        $this->showDeleteModal = true;
    }

    public function cancelDelete(): void
    {
        $this->showDeleteModal = false;
        $this->entryToDelete = null;
    }

    public function deleteEntry(): void
    {
        if ($this->entryToDelete) {
            $entry = TradingJournalEntry::find($this->entryToDelete);
            if ($entry) {
                $entry->delete();
            }
            $this->showDeleteModal = false;
            $this->entryToDelete = null;
            $this->resetPage();
        }
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function exportToExcel()
    {
        $entries = TradingJournalEntry::orderBy('fecha', 'asc')->get();
        $csv = $this->generateCSV($entries);
        $filename = 'trading_journal_' . now()->format('Y-m-d') . '.csv';
        
        return response()->streamDownload(function() use ($csv) {
            echo $csv;
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function exportToPDF()
    {
        $entries = TradingJournalEntry::orderBy('fecha', 'asc')->get();
        $html = $this->generatePDFHTML($entries);
        $filename = 'trading_journal_' . now()->format('Y-m-d') . '.html';
        
        return response()->streamDownload(function() use ($html) {
            echo $html;
        }, $filename, [
            'Content-Type' => 'text/html',
        ]);
    }
    
    private function generateCSV($entries): string
    {
        $output = fopen('php://temp', 'r+');
        
        // Headers
        fputcsv($output, [
            'Fecha',
            'Capital Inicial Plan',
            'Num Trades Plan',
            'Profit Diario Plan',
            '% Profit Plan',
            'Capital Final Plan',
            'Capital Inicial Real',
            'Num Trades Real',
            'Profit Diario Real',
            '% Profit Real',
            'Capital Final Real',
            'Capital Real'
        ]);
        
        // Data
        foreach ($entries as $entry) {
            fputcsv($output, [
                $entry->fecha->format('Y-m-d'),
                (float)$entry->capital_inicial_plan,
                (int)$entry->num_trades_plan,
                (float)$entry->profit_diario_plan,
                (float)$entry->profit_percent_plan,
                (float)$entry->capital_final_plan,
                (float)$entry->capital_inicial_real,
                (int)$entry->num_trades_real,
                (float)$entry->profit_diario_real,
                (float)$entry->profit_percent_real,
                (float)$entry->capital_final_real,
                (float)$entry->capital_real,
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    private function generatePDFHTML($entries): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Trading Journal</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #1e293b; }
        h2 { color: #475569; font-size: 1.2em; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 12px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px; text-align: left; }
        th { background-color: #1e293b; color: white; }
        tr:nth-child(even) { background-color: #f1f5f9; }
        .positive { color: #10b981; font-weight: bold; }
        .negative { color: #ef4444; font-weight: bold; }
        .plan-section { background-color: #fef3c7; }
        .real-section { background-color: #dbeafe; }
        @media print {
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <h1>Trading Journal - ' . now()->format('Y-m-d') . '</h1>
    
    <h2>Plan vs Real Comparison</h2>
    <table>
        <thead>
            <tr>
                <th rowspan="2">Fecha</th>
                <th colspan="4" class="plan-section">PLAN</th>
                <th colspan="4" class="real-section">REAL</th>
                <th rowspan="2">Capital Real</th>
            </tr>
            <tr>
                <th class="plan-section">Cap. Inicial</th>
                <th class="plan-section">Trades</th>
                <th class="plan-section">Profit</th>
                <th class="plan-section">%</th>
                <th class="real-section">Cap. Inicial</th>
                <th class="real-section">Trades</th>
                <th class="real-section">Profit</th>
                <th class="real-section">%</th>
            </tr>
        </thead>
        <tbody>';
        
        foreach ($entries as $entry) {
            $profitClassPlan = (float)$entry->profit_diario_plan >= 0 ? 'positive' : 'negative';
            $profitClassReal = (float)$entry->profit_diario_real >= 0 ? 'positive' : 'negative';
            $html .= '<tr>
                <td>' . $entry->fecha->format('Y-m-d') . '</td>
                <td class="plan-section">$' . number_format((float)$entry->capital_inicial_plan, 2) . '</td>
                <td class="plan-section">' . (int)$entry->num_trades_plan . '</td>
                <td class="plan-section ' . $profitClassPlan . '">$' . number_format((float)$entry->profit_diario_plan, 2) . '</td>
                <td class="plan-section ' . $profitClassPlan . '">' . number_format((float)$entry->profit_percent_plan, 2) . '%</td>
                <td class="real-section">$' . number_format((float)$entry->capital_inicial_real, 2) . '</td>
                <td class="real-section">' . (int)$entry->num_trades_real . '</td>
                <td class="real-section ' . $profitClassReal . '">$' . number_format((float)$entry->profit_diario_real, 2) . '</td>
                <td class="real-section ' . $profitClassReal . '">' . number_format((float)$entry->profit_percent_real, 2) . '%</td>
                <td>$' . number_format((float)$entry->capital_real, 2) . '</td>
            </tr>';
        }
        
        $html .= '</tbody>
    </table>
    <script>
        // Auto-print when opened
        window.onload = function() {
            setTimeout(function() {
                window.print();
            }, 500);
        };
    </script>
</body>
</html>';
        
        return $html;
    }
    
    public function addTrade(int $entryId): void
    {
        // Guardar el profit anterior del entry para actualizar portfolio
        $entry = TradingJournalEntry::find($entryId);
        $oldProfitReal = $entry ? ($entry->profit_diario_real ?? 0) : 0;
        
        $trade = JournalTrade::create([
            'trading_journal_entry_id' => $entryId,
            'symbol' => '',
            'strike_price' => 0,
            'capital_usado' => 0,
            'profit_percent' => 0,
            'ganancia' => 0,
            'fee' => 1.80,
        ]);
        
        // Update entry's real values from trades (suma todos los trades)
        if ($entry) {
            $entry->refresh();
            $entry->calculateRealFromTrades();
            $entry->save();
            
            // Actualizar portfolio: restar el profit anterior y sumar el nuevo
            if ($oldProfitReal != 0) {
                \App\Models\PortfolioSetting::updatePortfolio((float)(-$oldProfitReal));
            }
            if ($entry->profit_diario_real != 0) {
                \App\Models\PortfolioSetting::updatePortfolio((float)$entry->profit_diario_real);
            }
        }
    }
    
    public function toggleEditTrade(int $tradeId): void
    {
        if (isset($this->editingTrades[$tradeId]) && $this->editingTrades[$tradeId]) {
            $this->editingTrades[$tradeId] = false;
        } else {
            $this->editingTrades[$tradeId] = true;
        }
    }
    
    public function updateTrade(int $tradeId, string $field, $value): void
    {
        $trade = JournalTrade::find($tradeId);
        if ($trade) {
            // Guardar el profit anterior del entry para actualizar portfolio
            $entry = $trade->journalEntry;
            $oldProfitReal = $entry ? ($entry->profit_diario_real ?? 0) : 0;
            
            $trade->$field = is_numeric($value) ? (float)$value : $value;
            
            // Auto-calculate profit_percent if ganancia and capital_usado are set
            if ($field === 'ganancia' && $trade->capital_usado > 0) {
                $trade->profit_percent = ($trade->ganancia / $trade->capital_usado) * 100;
            }
            
            // Auto-calculate ganancia if capital_usado is changed
            if ($field === 'capital_usado' && $trade->profit_percent > 0) {
                $trade->ganancia = $trade->capital_usado * ($trade->profit_percent / 100);
            }
            
            $trade->save();
            
            // Update entry's real values from trades (suma todos los trades)
            if ($entry) {
                $entry->refresh(); // Refrescar para obtener los trades actualizados
                $entry->calculateRealFromTrades();
                $entry->save();
                
                // Actualizar portfolio: restar el profit anterior y sumar el nuevo
                if ($oldProfitReal != 0) {
                    \App\Models\PortfolioSetting::updatePortfolio((float)(-$oldProfitReal));
                }
                if ($entry->profit_diario_real != 0) {
                    \App\Models\PortfolioSetting::updatePortfolio((float)$entry->profit_diario_real);
                }
                
                // Forzar refresco de Livewire
                $this->dispatch('$refresh');
            }
        }
    }
    
    public function deleteTrade(int $tradeId): void
    {
        $trade = JournalTrade::find($tradeId);
        if ($trade) {
            $entry = $trade->journalEntry;
            $oldProfitReal = $entry ? ($entry->profit_diario_real ?? 0) : 0;
            
            $trade->delete();
            
            // Update entry's real values from remaining trades
            if ($entry) {
                $entry->refresh(); // Refresh to get updated trades relationship
                $entry->calculateRealFromTrades();
                $entry->save();
                
                // Actualizar portfolio: restar el profit anterior y sumar el nuevo
                if ($oldProfitReal != 0) {
                    \App\Models\PortfolioSetting::updatePortfolio((float)(-$oldProfitReal));
                }
                if ($entry->profit_diario_real != 0) {
                    \App\Models\PortfolioSetting::updatePortfolio((float)$entry->profit_diario_real);
                }
            }
        }
    }

    public function render()
    {
        $query = TradingJournalEntry::query();

        // Apply filters
        if ($this->filterType === 'date_range' && $this->startDate && $this->endDate) {
            $query->whereBetween('fecha', [$this->startDate, $this->endDate]);
        } elseif ($this->filterType === 'week' && count($this->selectedWeeks) > 0) {
            // Filter by multiple weeks
            $query->where(function($q) {
                foreach ($this->selectedWeeks as $selectedWeek) {
                    $year = (int) substr($selectedWeek, 0, 4);
                    $week = (int) substr($selectedWeek, 6);
                    $startOfWeek = \Carbon\Carbon::now()->setISODate($year, $week)->startOfWeek();
                    $endOfWeek = \Carbon\Carbon::now()->setISODate($year, $week)->endOfWeek();
                    $q->orWhereBetween('fecha', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')]);
                }
            });
        }

        // Apply sorting
        $query->orderBy($this->sortField, $this->sortDirection);

        // Paginate with trades relationship
        $entries = $query->with('trades')->paginate($this->perPage);
        
        // Get all entries for stats (rebuild query)
        $allEntriesQuery = TradingJournalEntry::query();
        if ($this->filterType === 'date_range' && $this->startDate && $this->endDate) {
            $allEntriesQuery->whereBetween('fecha', [$this->startDate, $this->endDate]);
        } elseif ($this->filterType === 'week' && count($this->selectedWeeks) > 0) {
            $allEntriesQuery->where(function($q) {
                foreach ($this->selectedWeeks as $selectedWeek) {
                    $year = (int) substr($selectedWeek, 0, 4);
                    $week = (int) substr($selectedWeek, 6);
                    $startOfWeek = \Carbon\Carbon::now()->setISODate($year, $week)->startOfWeek();
                    $endOfWeek = \Carbon\Carbon::now()->setISODate($year, $week)->endOfWeek();
                    $q->orWhereBetween('fecha', [$startOfWeek->format('Y-m-d'), $endOfWeek->format('Y-m-d')]);
                }
            });
        }
        $allEntries = $allEntriesQuery->get();
        
        // Get available weeks for dropdown
        $availableWeeks = TradingJournalEntry::selectRaw('DISTINCT YEARWEEK(fecha, 1) as week_num, MIN(fecha) as week_start')
            ->groupBy('week_num')
            ->orderBy('week_start', 'asc')
            ->get()
            ->map(function($item) {
                $date = \Carbon\Carbon::parse($item->week_start);
                return [
                    'value' => $date->format('Y') . '-W' . $date->format('W'),
                    'label' => 'Week ' . $date->format('W') . ', ' . $date->format('Y') . ' (' . $date->startOfWeek()->format('M d') . ' - ' . $date->endOfWeek()->format('M d') . ')',
                ];
            });

        return view('livewire.trading-journal', [
            'entries' => $entries,
            'allEntries' => $allEntries,
            'totalEntries' => $allEntries->count(),
            'availableWeeks' => $availableWeeks,
        ]);
    }
}
