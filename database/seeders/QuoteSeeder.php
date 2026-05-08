<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Symbol;
use App\Services\SchwabQuoteService;
use Illuminate\Database\Seeder;

class QuoteSeeder extends Seeder
{
    public function __construct(
        private readonly SchwabQuoteService $quoteService,
    ) {}

    public function run(): void
    {
        $symbols = Symbol::active()->get();

        foreach ($symbols as $symbol) {
            $quoteData = $this->quoteService->getQuote($symbol->ticker);
            
            if ($quoteData) {
                $this->quoteService->storeQuote($symbol, $quoteData);
            }
        }
    }
}
