<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\OptionContractData;
use App\Models\StrategyBot;
use App\Models\StrategyBotTrade;
use App\Support\Enums\OptionType;
use Carbon\Carbon;

class PaperOptionPricingService
{
    public function makeSyntheticContract(StrategyBot $bot, string $direction, float $underlyingPrice): OptionContractData
    {
        $optionType = in_array($direction, ['CALL', 'LONG'], true)
            ? OptionType::CALL
            : OptionType::PUT;

        $delta = max(0.01, min(0.99, (float) ($bot->option_delta_target ?? 0.40)));
        $expiration = now('America/New_York')->startOfDay()->addDays(max(1, (int) ($bot->option_min_dte ?? 1)));
        $strike = $this->estimateStrike($underlyingPrice, $delta, $optionType);
        $mark = $this->estimateEntryMark($underlyingPrice, $strike, $delta, $optionType);

        return new OptionContractData(
            contractSymbol: $this->syntheticSymbol($bot->symbol, $expiration, $optionType, $strike),
            optionType: $optionType,
            strike: $strike,
            expirationDate: $expiration,
            bid: round(max(0.01, $mark - 0.05), 2),
            ask: round($mark + 0.05, 2),
            last: $mark,
            mark: $mark,
            volume: 0,
            openInterest: 0,
            delta: $optionType === OptionType::PUT ? -$delta : $delta,
            gamma: 0.0,
            theta: 0.0,
            vega: 0.0,
            impliedVolatility: 0.0,
            inTheMoney: $optionType === OptionType::CALL
                ? $underlyingPrice > $strike
                : $underlyingPrice < $strike,
        );
    }

    public function estimateExitMark(StrategyBotTrade $trade, float $underlyingPrice): ?float
    {
        $entryMark = (float) ($trade->option_entry_price ?? 0);
        $entryUnderlying = (float) ($trade->entry_price ?? 0);

        if ($entryMark <= 0 || $entryUnderlying <= 0) {
            return null;
        }

        $delta = abs((float) ($trade->option_delta ?? 0.40));
        $directionMultiplier = in_array($trade->direction, ['CALL', 'LONG'], true) ? 1 : -1;
        $underlyingMove = ($underlyingPrice - $entryUnderlying) * $directionMultiplier;
        $estimatedMark = $entryMark + ($underlyingMove * $delta);

        return round(max(0.01, $estimatedMark), 2);
    }

    private function estimateStrike(float $underlyingPrice, float $delta, OptionType $optionType): float
    {
        $moneynessOffsetPct = max(0.0, 0.50 - $delta) * 0.20;
        $rawStrike = $optionType === OptionType::CALL
            ? $underlyingPrice * (1 + $moneynessOffsetPct)
            : $underlyingPrice * (1 - $moneynessOffsetPct);

        return round($rawStrike);
    }

    private function estimateEntryMark(
        float $underlyingPrice,
        float $strike,
        float $delta,
        OptionType $optionType,
    ): float {
        $intrinsic = $optionType === OptionType::CALL
            ? max(0.0, $underlyingPrice - $strike)
            : max(0.0, $strike - $underlyingPrice);

        $extrinsic = max(0.05, $underlyingPrice * 0.0125 * $delta);

        return round(max(0.05, $intrinsic + $extrinsic), 2);
    }

    private function syntheticSymbol(string $symbol, Carbon $expiration, OptionType $optionType, float $strike): string
    {
        $side = $optionType === OptionType::CALL ? 'C' : 'P';

        return sprintf(
            'PAPER-%s-%s-%s-%s',
            strtoupper($symbol),
            $expiration->format('ymd'),
            $side,
            number_format($strike, 0, '', '')
        );
    }
}
