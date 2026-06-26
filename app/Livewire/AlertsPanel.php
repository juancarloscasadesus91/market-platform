<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Alert;
use App\Models\Symbol;
use App\Support\Enums\AlertType;
use Livewire\Component;

class AlertsPanel extends Component
{
    public string $ticker = '';
    public string $alertType = '';
    public string $condition = '';
    public ?float $thresholdValue = null;
    public bool $showCreateForm = false;

    public function toggleCreateForm(): void
    {
        $this->showCreateForm = !$this->showCreateForm;
        $this->reset(['ticker', 'alertType', 'condition', 'thresholdValue']);
    }

    public function createAlert(): void
    {
        $this->validate([
            'ticker' => 'required|exists:symbols,ticker',
            'alertType' => 'required|in:unusual_premium,volume_spike,delta_threshold,price_movement,iv_spike',
            'condition' => 'required|string|max:255',
            'thresholdValue' => 'nullable|numeric',
        ]);

        $symbol = Symbol::where('ticker', $this->ticker)->first();

        if (!$symbol) {
            return;
        }

        Alert::create([
            'symbol_id' => $symbol->id,
            'alert_type' => $this->alertType,
            'condition' => $this->condition,
            'threshold_value' => $this->thresholdValue,
            'is_active' => true,
        ]);

        $this->toggleCreateForm();
        $this->dispatch('alert-created');
    }

    public function toggleAlert(int $alertId): void
    {
        $alert = Alert::find($alertId);
        
        if ($alert) {
            $alert->update(['is_active' => !$alert->is_active]);
        }
    }

    public function deleteAlert(int $alertId): void
    {
        Alert::find($alertId)?->delete();
    }

    public function render()
    {
        $alerts = Alert::with('symbol')
            ->latest()
            ->get();

        $alertTypes = collect(AlertType::cases())
            ->mapWithKeys(fn ($type) => [$type->value => $type->label()]);

        return view('livewire.alerts-panel', [
            'alerts' => $alerts,
            'alertTypes' => $alertTypes,
        ]);
    }
}
