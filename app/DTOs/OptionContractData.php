<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Support\Enums\OptionType;
use Carbon\Carbon;

readonly class OptionContractData
{
    public function __construct(
        public string $contractSymbol,
        public OptionType $optionType,
        public float $strike,
        public Carbon $expirationDate,
        public ?float $bid = null,
        public ?float $ask = null,
        public ?float $last = null,
        public ?float $mark = null,
        public ?int $volume = null,
        public ?int $openInterest = null,
        public ?float $delta = null,
        public ?float $gamma = null,
        public ?float $theta = null,
        public ?float $vega = null,
        public ?float $rho = null,
        public ?float $impliedVolatility = null,
        public ?bool $inTheMoney = null,
        public ?float $intrinsicValue = null,
        public ?float $extrinsicValue = null,
    ) {}

    public function toArray(): array
    {
        return [
            'contract_symbol' => $this->contractSymbol,
            'option_type' => $this->optionType,
            'strike' => $this->strike,
            'expiration_date' => $this->expirationDate,
            'bid' => $this->bid,
            'ask' => $this->ask,
            'last' => $this->last,
            'mark' => $this->mark,
            'volume' => $this->volume,
            'open_interest' => $this->openInterest,
            'delta' => $this->delta,
            'gamma' => $this->gamma,
            'theta' => $this->theta,
            'vega' => $this->vega,
            'rho' => $this->rho,
            'implied_volatility' => $this->impliedVolatility,
            'in_the_money' => $this->inTheMoney,
            'intrinsic_value' => $this->intrinsicValue,
            'extrinsic_value' => $this->extrinsicValue,
        ];
    }
}
