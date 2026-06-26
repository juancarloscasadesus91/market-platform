<?php

namespace App\Livewire;

use App\Services\SchwabAuthService;
use Illuminate\Support\Facades\Http;
use Livewire\Component;

class OptionChainLive extends Component
{
    public string $ticker;
    public ?string $selectedExpiration = null;
    public ?string $error = null;
    public int $strikeCount = 10;
    public array $visibleColumns = ['strike', 'bid', 'ask', 'last', 'volume', 'openInterest', 'impliedVolatility', 'delta'];
    public bool $showColumnFilter = false;
    
    public array $availableColumns = [
        'strike' => 'Strike',
        'bid' => 'Bid',
        'ask' => 'Ask',
        'last' => 'Last',
        'mark' => 'Mark',
        'volume' => 'Volume',
        'openInterest' => 'Open Interest',
        'impliedVolatility' => 'IV',
        'delta' => 'Delta',
        'gamma' => 'Gamma',
        'theta' => 'Theta',
        'vega' => 'Vega',
        'rho' => 'Rho',
        'intrinsicValue' => 'Intrinsic',
        'extrinsicValue' => 'Extrinsic',
    ];

    public function mount(string $ticker)
    {
        $this->ticker = strtoupper($ticker);
    }
    
    public function setStrikeCount(int $count)
    {
        $this->strikeCount = $count;
    }
    
    public function toggleColumn(string $column)
    {
        if (in_array($column, $this->visibleColumns)) {
            $this->visibleColumns = array_values(array_diff($this->visibleColumns, [$column]));
        } else {
            $this->visibleColumns[] = $column;
        }
    }
    
    public function toggleColumnFilter()
    {
        $this->showColumnFilter = !$this->showColumnFilter;
    }

    public function getOptionChainDataProperty()
    {
        try {
            $authService = app(SchwabAuthService::class);
            $token = $authService->getAccessToken();

            if (!$token) {
                return null;
            }

            // Get API symbol (handle indices with $ prefix)
            $apiSymbol = $this->getApiSymbol();

            $response = Http::withToken($token)
                ->timeout(15)
                ->get(config('services.schwab.base_url') . '/marketdata/v1/chains', [
                    'symbol' => $apiSymbol,
                    'contractType' => 'ALL',
                    'strikeCount' => $this->strikeCount === 999 ? null : $this->strikeCount,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                
                // Set first expiration as selected if not set
                if (!$this->selectedExpiration && isset($data['callExpDateMap'])) {
                    $expirations = array_keys($data['callExpDateMap']);
                    $this->selectedExpiration = $expirations[0] ?? null;
                }
                
                return $data;
            }

            return null;
        } catch (\Exception $e) {
            $this->error = $e->getMessage();
            return null;
        }
    }

    private function getApiSymbol(): string
    {
        $indices = ['SPX', 'DJI', 'COMPX', 'NDX', 'RUT', 'VIX'];
        
        if (in_array($this->ticker, $indices) && !str_starts_with($this->ticker, '$')) {
            return '$' . $this->ticker;
        }
        
        return $this->ticker;
    }

    public function selectExpiration(string $expiration)
    {
        $this->selectedExpiration = $expiration;
    }

    public function render()
    {
        $optionChainData = $this->optionChainData;

        return view('livewire.option-chain-live', [
            'optionChainData' => $optionChainData,
        ]);
    }
}
