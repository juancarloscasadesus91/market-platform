<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\OptionContractData;
use App\Models\StrategyBot;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Selects the best option contract for a given signal and bot configuration.
 *
 * Strategy:
 *  1. Fetch the option chain for the bot's symbol from Schwab API
 *  2. Filter by DTE range (min_dte ≤ DTE ≤ max_dte)
 *  3. Pick the expiry with the lowest DTE that has liquid contracts
 *  4. From that expiry, find the contract whose |delta| is closest to the target
 *  5. Apply delta tolerance filter (discard if difference > tolerance)
 */
class OptionContractSelector
{
    private const BASE = 'https://api.schwabapi.com/marketdata/v1';

    public function __construct(
        private readonly SchwabAuthService $auth,
    ) {}

    public static function make(): self
    {
        return new self(SchwabAuthService::make());
    }

    /**
     * Find the best contract for the given signal direction.
     *
     * @param  StrategyBot $bot
     * @param  string      $direction  CALL | PUT
     * @return OptionContractData|null
     */
    public function selectContract(StrategyBot $bot, string $direction): ?OptionContractData
    {
        $contractType = in_array($direction, ['CALL', 'LONG']) ? 'CALL' : 'PUT';
        $deltaTarget  = (float) ($bot->option_delta_target ?? 0.40);
        $deltaTol     = (float) ($bot->option_delta_tolerance ?? 0.05);
        $maxDte       = (int) ($bot->option_max_dte ?? 7);
        $minDte       = (int) ($bot->option_min_dte ?? 1);

        $chain = $this->fetchChain($bot->symbol, $contractType, $maxDte);
        if (empty($chain)) {
            Log::warning("OptionContractSelector: empty chain for {$bot->symbol}");
            return null;
        }

        // Group contracts by expiry, filter by DTE range
        $today    = now()->startOfDay();
        $byExpiry = collect($chain)
            ->groupBy(fn($c) => $c['expiry'])
            ->filter(function ($contracts, $expiry) use ($today, $minDte, $maxDte) {
                $dte = (int) Carbon::parse($expiry)->startOfDay()->diffInDays($today);
                return $dte >= $minDte && $dte <= $maxDte;
            })
            ->sortKeys();  // earliest expiry first

        if ($byExpiry->isEmpty()) {
            Log::warning("OptionContractSelector: no expiry in DTE range [{$minDte},{$maxDte}] for {$bot->symbol}");
            return null;
        }

        // Pick the nearest expiry that has at least one liquid contract
        foreach ($byExpiry as $expiry => $contracts) {
            $best = $this->pickByDelta(
                contracts:   $contracts,
                deltaTarget: $deltaTarget,
                deltaTol:    $deltaTol,
                contractType: $contractType,
            );
            if ($best !== null) {
                Log::info("OptionContractSelector: selected {$best->contractSymbol}", [
                    'delta'  => $best->delta,
                    'target' => $deltaTarget,
                    'expiry' => $expiry,
                    'mark'   => $best->mark,
                ]);
                return $best;
            }
        }

        Log::warning("OptionContractSelector: no contract found within delta tolerance", [
            'symbol' => $bot->symbol,
            'target' => $deltaTarget,
            'tol'    => $deltaTol,
        ]);
        return null;
    }

    /**
     * Fetch the current quote for an option contract (mark price + greeks).
     */
    public function getContractQuote(string $contractSymbol): ?array
    {
        $token = $this->auth->getAccessToken();
        if (!$token) return null;

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ])->get(self::BASE . '/quotes', [
            'symbols' => $contractSymbol,
            'fields'  => 'quote',
        ]);

        if (!$response->successful()) return null;

        $data = $response->json() ?? [];
        $q    = $data[$contractSymbol]['quote'] ?? null;
        if (!$q) return null;

        return [
            'bid'   => (float) ($q['bidPrice'] ?? $q['bid'] ?? 0),
            'ask'   => (float) ($q['askPrice'] ?? $q['ask'] ?? 0),
            'mark'  => (float) ($q['mark'] ?? (((float)($q['bidPrice']??0) + (float)($q['askPrice']??0)) / 2)),
            'last'  => (float) ($q['lastPrice'] ?? $q['last'] ?? 0),
            'delta' => (float) ($q['delta'] ?? 0),
            'gamma' => (float) ($q['gamma'] ?? 0),
            'theta' => (float) ($q['theta'] ?? 0),
            'vega'  => (float) ($q['vega'] ?? 0),
            'iv'    => (float) ($q['volatility'] ?? $q['impliedVolatility'] ?? 0),
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetch option chain from Schwab API (chains endpoint).
     * Returns flat array of contracts with expiry, strike, delta, mark, symbol.
     */
    private function fetchChain(string $symbol, string $contractType, int $maxDte): array
    {
        $token = $this->auth->getAccessToken();
        if (!$token) {
            Log::warning("OptionContractSelector: no market token");
            return [];
        }

        $toDate = now()->addDays($maxDte)->format('Y-m-d');

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$token}",
            'Accept'        => 'application/json',
        ])->get(self::BASE . '/chains', [
            'symbol'       => strtoupper($symbol),
            'contractType' => $contractType,
            'strikeCount'  => 30,
            'range'        => 'ALL',
            'toDate'       => $toDate,
            'optionType'   => 'S',  // Standard
        ]);

        if (!$response->successful()) {
            Log::error("OptionContractSelector: chain fetch failed", [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return [];
        }

        $data = $response->json() ?? [];
        $mapKey = $contractType === 'CALL' ? 'callExpDateMap' : 'putExpDateMap';
        $expMap = $data[$mapKey] ?? [];

        $contracts = [];
        foreach ($expMap as $expDteKey => $strikeMap) {
            // Key format: "2025-06-20:5" (expiry:dte)
            $expiry = explode(':', $expDteKey)[0];

            foreach ($strikeMap as $strikeStr => $contractArr) {
                $c = is_array($contractArr) && isset($contractArr[0]) ? $contractArr[0] : null;
                if (!$c || !isset($c['symbol'])) continue;

                $delta = isset($c['delta']) ? (float) $c['delta'] : null;
                $mark  = isset($c['mark'])  ? (float) $c['mark']  : null;

                // Skip illiquid (no delta data or zero mark)
                if ($delta === null || $mark === null || $mark <= 0) continue;

                $contracts[] = [
                    'symbol'   => $c['symbol'],
                    'expiry'   => $expiry,
                    'strike'   => (float) ($c['strikePrice'] ?? $strikeStr),
                    'delta'    => $delta,
                    'gamma'    => (float) ($c['gamma'] ?? 0),
                    'theta'    => (float) ($c['theta'] ?? 0),
                    'vega'     => (float) ($c['vega']  ?? 0),
                    'iv'       => (float) ($c['volatility'] ?? 0),
                    'bid'      => (float) ($c['bid']  ?? 0),
                    'ask'      => (float) ($c['ask']  ?? 0),
                    'mark'     => $mark,
                    'last'     => (float) ($c['last'] ?? $mark),
                    'volume'   => (int)   ($c['totalVolume'] ?? 0),
                    'oi'       => (int)   ($c['openInterest'] ?? 0),
                    'itm'      => (bool)  ($c['inTheMoney'] ?? false),
                ];
            }
        }

        return $contracts;
    }

    /**
     * From a set of same-expiry contracts, pick the one closest to deltaTarget
     * within the tolerance. Uses absolute delta for comparison.
     */
    private function pickByDelta(
        array|Collection $contracts,
        float            $deltaTarget,
        float            $deltaTol,
        string           $contractType,
    ): ?OptionContractData {
        $best      = null;
        $bestDiff  = PHP_FLOAT_MAX;

        foreach ($contracts as $c) {
            $rawDelta = (float) ($c['delta'] ?? 0);
            // For PUTs delta is negative; we compare absolute value
            $absDelta = abs($rawDelta);
            $diff     = abs($absDelta - $deltaTarget);

            if ($diff > $deltaTol) continue;
            if ($diff >= $bestDiff) continue;

            $bestDiff = $diff;
            $best     = $c;
        }

        if (!$best) return null;

        return new OptionContractData(
            contractSymbol:    $best['symbol'],
            optionType:        \App\Support\Enums\OptionType::from(strtolower($contractType)),
            strike:            $best['strike'],
            expirationDate:    Carbon::parse($best['expiry']),
            bid:               $best['bid'],
            ask:               $best['ask'],
            last:              $best['last'],
            mark:              $best['mark'],
            volume:            $best['volume'],
            openInterest:      $best['oi'],
            delta:             $best['delta'],
            gamma:             $best['gamma'],
            theta:             $best['theta'],
            vega:              $best['vega'],
            impliedVolatility: $best['iv'],
            inTheMoney:        $best['itm'],
        );
    }
}
