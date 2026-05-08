<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TradingJournalEntry;

class TradingJournalExportController extends Controller
{
    public function exportExcel()
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

    public function exportPDF()
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
}
