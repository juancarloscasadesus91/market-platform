<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SchwabAuthService;
use Illuminate\Console\Command;

class RefreshSchwabToken extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'schwab:refresh-token';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh Schwab API access token using refresh token';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Refreshing Schwab API token...');

        $authService = SchwabAuthService::make();

        if (!$authService->hasRefreshToken()) {
            $this->error('No refresh token found. Please authenticate first.');
            $this->info('Visit: ' . $authService->getAuthorizationUrl());
            return self::FAILURE;
        }

        $token = $authService->getAccessToken();

        if ($token) {
            $this->info('✓ Token refreshed successfully!');
            return self::SUCCESS;
        }

        $this->error('✗ Failed to refresh token. Please re-authenticate.');
        $this->info('Visit: ' . $authService->getAuthorizationUrl());
        return self::FAILURE;
    }
}
