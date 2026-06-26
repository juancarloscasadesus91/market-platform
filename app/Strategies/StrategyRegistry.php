<?php

declare(strict_types=1);

namespace App\Strategies;

use App\Contracts\StrategyInterface;
use Illuminate\Support\Facades\App;

/**
 * Central registry of all available backtest strategies.
 * To add a new strategy: create the class, implement StrategyInterface, add it here.
 */
class StrategyRegistry
{
    /**
     * Map of strategy_key => FQCN
     * @var array<string, class-string<StrategyInterface>>
     */
    private static array $strategies = [
        'ema_pullback'   => EmaPullbackStrategy::class,
        'bollinger_rsi'  => BollingerRsiStrategy::class,
        'price_trigger'  => PriceTriggerStrategy::class,
    ];

    /**
     * Register a new strategy at runtime (e.g. from a Service Provider).
     */
    public static function register(string $key, string $class): void
    {
        static::$strategies[$key] = $class;
    }

    /**
     * Resolve a strategy instance by key.
     */
    public static function resolve(string $key): StrategyInterface
    {
        $class = static::$strategies[$key] ?? null;

        if ($class === null) {
            throw new \InvalidArgumentException("Unknown strategy: [{$key}]. Available: " . implode(', ', array_keys(static::$strategies)));
        }

        return App::make($class);
    }

    /**
     * All registered strategy keys.
     * @return array<string>
     */
    public static function keys(): array
    {
        return array_keys(static::$strategies);
    }

    /**
     * Key → label map for select dropdowns.
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];
        foreach (static::$strategies as $key => $class) {
            $instance = App::make($class);
            $out[$key] = $instance->label();
        }
        return $out;
    }

    /**
     * Full info array per strategy.
     * @return array<string, array{key: string, label: string, schema: array}>
     */
    public static function all(): array
    {
        $out = [];
        foreach (static::$strategies as $key => $class) {
            $instance = App::make($class);
            $out[$key] = [
                'key'    => $key,
                'label'  => $instance->label(),
                'schema' => $instance->schema(),
            ];
        }
        return $out;
    }
}
