<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\OptionChainData;
use App\DTOs\OptionContractData;
use App\Models\Symbol;
use App\Support\Enums\OptionType;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class SchwabOptionChainService
{
    public function __construct(
        private readonly SchwabAuthService $authService,
        private readonly string $baseUrl,
    ) {}

    public static function make(): self
    {
        return new self(
            authService: SchwabAuthService::make(),
            baseUrl: config('services.schwab.base_url', 'https://api.schwabapi.com'),
        );
    }

    /**
     * Fetch option chain for a symbol
     */
    public function getOptionChain(string $ticker, ?Carbon $expirationDate = null): ?OptionChainData
    {
        $token = $this->authService->getAccessToken();

        if (!$token) {
            return $this->getFakeOptionChain($ticker, $expirationDate);
        }

        $params = [
            'symbol' => $ticker,
            'contractType' => 'ALL',
            'strikeCount' => 30, // Balanced: enough coverage without overwhelming
            'range' => 'ITM' // Only In-The-Money and near-the-money strikes
        ];

        if ($expirationDate) {
            $params['fromDate'] = $expirationDate->format('Y-m-d');
            $params['toDate'] = $expirationDate->format('Y-m-d');
        }

        $response = Http::withToken($token)
            ->get("{$this->baseUrl}/marketdata/v1/chains", $params);

        if ($response->successful()) {
            $data = $response->json();
            return $this->transformOptionChainData($ticker, $data, $expirationDate);
        }

        return $this->getFakeOptionChain($ticker, $expirationDate);
    }

    /**
     * Get available expiration dates for a symbol
     */
    public function getExpirationDates(string $ticker): Collection
    {
        // In production, fetch from API
        // For now, return next 8 Fridays
        $dates = collect();
        $date = now()->next('Friday');

        for ($i = 0; $i < 8; $i++) {
            $dates->push($date->copy());
            $date->addWeek();
        }

        return $dates;
    }

    /**
     * Store option contracts in database
     */
    public function storeOptionChain(Symbol $symbol, OptionChainData $chainData): void
    {
        $contracts = $chainData->calls->merge($chainData->puts);

        foreach ($contracts as $contractData) {
            $symbol->optionContracts()->updateOrCreate(
                ['contract_symbol' => $contractData->contractSymbol],
                $contractData->toArray()
            );
        }
    }

    /**
     * Transform API response to OptionChainData DTO
     */
    private function transformOptionChainData(string $ticker, array $data, ?Carbon $expirationDate): OptionChainData
    {
        // Flatten the nested structure: expDate -> strike -> [contracts]
        $calls = collect($data['callExpDateMap'] ?? [])
            ->flatten(2) // Flatten 2 levels to get individual contracts
            ->filter(fn($item) => is_array($item) && isset($item['symbol']))
            ->map(fn ($contract) => $this->transformContractData($contract, OptionType::CALL));

        $puts = collect($data['putExpDateMap'] ?? [])
            ->flatten(2) // Flatten 2 levels to get individual contracts
            ->filter(fn($item) => is_array($item) && isset($item['symbol']))
            ->map(fn ($contract) => $this->transformContractData($contract, OptionType::PUT));

        return new OptionChainData(
            ticker: $ticker,
            expirationDate: $expirationDate ?? now()->next('Friday'),
            calls: $calls,
            puts: $puts,
            underlyingPrice: $data['underlyingPrice'] ?? null,
        );
    }

    /**
     * Transform individual contract data
     */
    private function transformContractData(array $data, OptionType $type): OptionContractData
    {
        return new OptionContractData(
            contractSymbol: $data['symbol'] ?? '',
            optionType: $type,
            strike: (float) ($data['strikePrice'] ?? 0),
            expirationDate: Carbon::parse($data['expirationDate'] ?? now()),
            bid: isset($data['bid']) ? (float) $data['bid'] : null,
            ask: isset($data['ask']) ? (float) $data['ask'] : null,
            last: isset($data['last']) ? (float) $data['last'] : null,
            mark: isset($data['mark']) ? (float) $data['mark'] : null,
            volume: isset($data['totalVolume']) ? (int) $data['totalVolume'] : null,
            openInterest: isset($data['openInterest']) ? (int) $data['openInterest'] : null,
            delta: isset($data['delta']) ? (float) $data['delta'] : null,
            gamma: isset($data['gamma']) ? (float) $data['gamma'] : null,
            theta: isset($data['theta']) ? (float) $data['theta'] : null,
            vega: isset($data['vega']) ? (float) $data['vega'] : null,
            rho: isset($data['rho']) ? (float) $data['rho'] : null,
            impliedVolatility: isset($data['volatility']) ? (float) $data['volatility'] : null,
            inTheMoney: $data['inTheMoney'] ?? false,
            intrinsicValue: isset($data['intrinsicValue']) ? (float) $data['intrinsicValue'] : null,
            extrinsicValue: isset($data['extrinsicValue']) ? (float) $data['extrinsicValue'] : null,
        );
    }

    /**
     * Generate fake option chain for development
     */
    private function getFakeOptionChain(string $ticker, ?Carbon $expirationDate = null): OptionChainData
    {
        $expiration = $expirationDate ?? now()->next('Friday');
        $underlyingPrice = match($ticker) {
            'SPX', '$SPX.W' => 5200.00,
            'SPY' => 520.00,
            'QQQ' => 450.00,
            'NVDA' => 875.00,
            'AAPL' => 185.00,
            'TSLA' => 245.00,
            'MSFT' => 420.00,
            'AMZN' => 180.00,
            'GOOGL' => 165.00,
            'META' => 485.00,
            'AMD' => 165.00,
            'NFLX' => 650.00,
            default => 100.00,
        };

        $calls = collect();
        $puts = collect();

        // Generate strikes around underlying price
        $strikeIncrement = $underlyingPrice > 1000 ? 25 : ($underlyingPrice > 200 ? 5 : 2.5);
        $strikeStart = floor($underlyingPrice * 0.95 / $strikeIncrement) * $strikeIncrement;
        $strikeEnd = ceil($underlyingPrice * 1.05 / $strikeIncrement) * $strikeIncrement;

        for ($strike = $strikeStart; $strike <= $strikeEnd; $strike += $strikeIncrement) {
            $calls->push($this->generateFakeContract($ticker, $strike, $expiration, OptionType::CALL, $underlyingPrice));
            $puts->push($this->generateFakeContract($ticker, $strike, $expiration, OptionType::PUT, $underlyingPrice));
        }

        return new OptionChainData(
            ticker: $ticker,
            expirationDate: $expiration,
            calls: $calls,
            puts: $puts,
            underlyingPrice: $underlyingPrice,
        );
    }

    /**
     * Generate fake contract data
     */
    private function generateFakeContract(
        string $ticker,
        float $strike,
        Carbon $expiration,
        OptionType $type,
        float $underlyingPrice
    ): OptionContractData {
        $inTheMoney = $type === OptionType::CALL
            ? $underlyingPrice > $strike
            : $underlyingPrice < $strike;

        $intrinsicValue = max(0, $type === OptionType::CALL
            ? $underlyingPrice - $strike
            : $strike - $underlyingPrice);

        $extrinsicValue = rand(50, 500) / 100;
        $mark = $intrinsicValue + $extrinsicValue;

        $delta = $type === OptionType::CALL
            ? ($inTheMoney ? rand(50, 90) / 100 : rand(10, 50) / 100)
            : ($inTheMoney ? -rand(50, 90) / 100 : -rand(10, 50) / 100);

        return new OptionContractData(
            contractSymbol: "{$ticker}_{$expiration->format('ymd')}_{$type->value}_{$strike}",
            optionType: $type,
            strike: $strike,
            expirationDate: $expiration,
            bid: round($mark - 0.10, 2),
            ask: round($mark + 0.10, 2),
            last: round($mark, 2),
            mark: round($mark, 2),
            volume: rand(100, 10000),
            openInterest: rand(500, 50000),
            delta: round($delta, 4),
            gamma: round(rand(1, 50) / 1000, 4),
            theta: round(-rand(1, 100) / 100, 4),
            vega: round(rand(10, 200) / 100, 4),
            rho: round(rand(1, 50) / 1000, 4),
            impliedVolatility: round(rand(15, 80) / 100, 4),
            inTheMoney: $inTheMoney,
            intrinsicValue: round($intrinsicValue, 2),
            extrinsicValue: round($extrinsicValue, 2),
        );
    }
}
