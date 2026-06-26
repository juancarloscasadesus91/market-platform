<?php

declare(strict_types=1);

namespace App\Strategies;

use App\Contracts\StrategyInterface;

class PriceTriggerStrategy implements StrategyInterface
{
    public function detect(array $candles, array $cfg): array
    {
        if (count($candles) < 1) {
            return [];
        }

        $target = isset($cfg['trigger_price']) ? (float) $cfg['trigger_price'] : 0.0;
        if ($target <= 0) {
            return [];
        }

        $last = end($candles);
        $prev = count($candles) > 1 ? $candles[array_key_last($candles) - 1] : null;
        $price = (float) ($last['close'] ?? 0.0);
        $previousPrice = $prev ? (float) ($prev['close'] ?? 0.0) : null;

        $operator = (string) ($cfg['trigger_operator'] ?? 'at_or_above');
        $triggered = match ($operator) {
            'at_or_below' => $price <= $target,
            'cross_above' => $price >= $target && ($previousPrice === null || $previousPrice < $target),
            'cross_below' => $price <= $target && ($previousPrice === null || $previousPrice > $target),
            default => $price >= $target,
        };

        if (!$triggered) {
            return [];
        }

        $tradeDirection = strtoupper((string) ($cfg['trade_direction'] ?? 'LONG'));

        return [[
            'direction' => $tradeDirection,
            'entry_idx' => array_key_last($candles),
            'entry_time' => $last['dt'] ?? now('UTC')->toIso8601String(),
            'entry_price' => $price,
            'target_price' => $target,
            'trigger_operator' => $operator,
            'trade_asset' => $cfg['trade_asset'] ?? 'equity',
        ]];
    }

    public function label(): string
    {
        return 'Price Trigger';
    }

    public function requiredIndicators(): array
    {
        return [];
    }

    public function schema(): array
    {
        return [
            ['key' => 'trigger_price', 'label' => 'Trigger Price', 'type' => 'float', 'default' => null, 'min' => 0.01, 'max' => 100000, 'step' => 0.01, 'group' => 'Trigger'],
            ['key' => 'trigger_operator', 'label' => 'Trigger', 'type' => 'select', 'default' => 'at_or_above', 'options' => [
                'at_or_above' => 'Price >= Trigger',
                'at_or_below' => 'Price <= Trigger',
                'cross_above' => 'Cross Above',
                'cross_below' => 'Cross Below',
            ], 'group' => 'Trigger'],
            ['key' => 'trigger_price_source', 'label' => 'Price Source', 'type' => 'select', 'default' => 'auto', 'options' => [
                'auto' => 'Auto',
                'last' => 'Last Trade',
                'mid' => 'Bid/Ask Mid',
                'bid' => 'Bid',
                'ask' => 'Ask',
            ], 'group' => 'Trigger'],
            ['key' => 'trade_direction', 'label' => 'Direction', 'type' => 'select', 'default' => 'LONG', 'options' => [
                'LONG' => 'Buy Equity / Call',
                'SHORT' => 'Short Equity',
                'CALL' => 'Buy Call',
                'PUT' => 'Buy Put',
            ], 'group' => 'Order'],
            ['key' => 'trade_asset', 'label' => 'Asset', 'type' => 'select', 'default' => 'equity', 'options' => [
                'equity' => 'Stock / ETF',
                'option' => 'Option Contract',
            ], 'group' => 'Order'],
            ['key' => 'one_shot', 'label' => 'One Entry Only', 'type' => 'select', 'default' => 'yes', 'options' => [
                'yes' => 'Yes',
                'no' => 'No',
            ], 'group' => 'Order'],
            ['key' => 'option_target_price', 'label' => 'Option Target Price', 'type' => 'float', 'default' => null, 'min' => 0.01, 'max' => 1000, 'step' => 0.01, 'group' => 'Options'],
            ['key' => 'option_target_delta', 'label' => 'Option Target Delta', 'type' => 'float', 'default' => 0.40, 'min' => 0.01, 'max' => 1, 'step' => 0.01, 'group' => 'Options'],
            ['key' => 'option_min_dte', 'label' => 'Min DTE', 'type' => 'int', 'default' => 0, 'min' => 0, 'max' => 730, 'step' => 1, 'group' => 'Options'],
            ['key' => 'option_max_dte', 'label' => 'Max DTE', 'type' => 'int', 'default' => 14, 'min' => 0, 'max' => 730, 'step' => 1, 'group' => 'Options'],
            ['key' => 'option_order_price', 'label' => 'Option Entry Price', 'type' => 'select', 'default' => 'market', 'options' => [
                'market' => 'Market',
                'limit_mid' => 'Limit at Mid',
                'limit_target' => 'Limit at Target Price',
            ], 'group' => 'Options'],
            ['key' => 'stop_loss_value', 'label' => 'Fixed Stop Price', 'type' => 'float', 'default' => null, 'min' => 0.01, 'max' => 100000, 'step' => 0.01, 'group' => 'Risk'],
            ['key' => 'take_profit_value', 'label' => 'Fixed Take Profit Price', 'type' => 'float', 'default' => null, 'min' => 0.01, 'max' => 100000, 'step' => 0.01, 'group' => 'Risk'],
        ];
    }
}
