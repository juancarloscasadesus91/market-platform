<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Every backtest strategy must implement this interface.
 *
 * detect()  → takes enriched candles + config, returns signal list
 * label()   → human-readable name shown in the UI
 * schema()  → parameter definitions for the UI form (key, type, default, label, min, max, step)
 */
interface StrategyInterface
{
    /**
     * Detect entry signals from an enriched candle array.
     *
     * Each returned signal MUST contain at minimum:
     *   direction    string  'CALL'|'PUT'|'LONG'|'SHORT'
     *   entry_idx    int     index into $candles for the entry bar
     *   entry_time   string  ISO datetime of entry bar
     *   entry_price  float   price at which to enter (usually next bar open)
     *   atr          float   ATR at signal bar (used for stop calc)
     *   ema50        float   EMA50 at signal bar (used by quadrant stop)
     *
     * All other fields are strategy-specific and will be stored in metadata.
     *
     * @param  array $candles  Enriched candles from IndicatorService::compute()
     * @param  array $cfg      Strategy parameters (from StrategySetting or StrategyLabSession)
     * @return array           Array of signal arrays
     */
    public function detect(array $candles, array $cfg): array;

    /**
     * Short human-readable name, e.g. "EMA Pullback", "VWAP Reversion".
     */
    public function label(): string;

    /**
     * Parameter schema for the UI form.
     *
     * Each element:
     * [
     *   'key'     => string,
     *   'label'   => string,
     *   'type'    => 'int'|'float'|'string'|'bool'|'time'|'select',
     *   'default' => mixed,
     *   'min'     => numeric|null,
     *   'max'     => numeric|null,
     *   'step'    => numeric|null,
     *   'options' => array|null,   // for 'select' type: [value => label]
     *   'group'   => string|null,  // grouping in the form UI
     * ]
     *
     * @return array
     */
    public function schema(): array;

    /**
     * Which indicator keys this strategy needs IndicatorService to compute.
     * Return subset of: ['ema', 'rsi', 'atr', 'bb', 'vwap', 'volume']
     * Default: all. Override to skip expensive unneeded indicators.
     *
     * @return array
     */
    public function requiredIndicators(): array;
}
