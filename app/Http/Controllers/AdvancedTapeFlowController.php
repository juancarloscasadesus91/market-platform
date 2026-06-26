<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TapeFlow\TapeFlowProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdvancedTapeFlowController extends Controller
{
    private TapeFlowProcessor $processor;

    public function __construct()
    {
        $this->processor = new TapeFlowProcessor();
    }

    /**
     * Get dashboard summary
     */
    public function getDashboardSummary(Request $request): JsonResponse
    {
        $window = $request->get('window', 'day');

        try {
            $data = [
                'global' => $this->processor->getCurrentFlow($window),
                'top_bullish' => $this->processor->getTopBullish($window, 10),
                'top_bearish' => $this->processor->getTopBearish($window, 10),
                'most_aggressive' => $this->processor->getMostAggressive($window, 10),
                'high_mid_noise' => $this->processor->getHighMidNoise($window, 10),
                'recent_tape' => $this->processor->getRecentTape(100),
                'timestamp' => now()->toISOString(),
                'window' => $window,
            ];

            return response()->json($data);
        } catch (\Exception $e) {
            \Log::error('Error getting dashboard summary', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => 'Failed to load dashboard data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process incoming trade from WebSocket
     */
    public function processTrade(Request $request): JsonResponse
    {
        try {
            $tradeData = $request->all();
            $processedTrade = $this->processor->processTrade($tradeData);

            return response()->json([
                'success' => true,
                'trade' => $processedTrade,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error processing trade', [
                'error' => $e->getMessage(),
                'trade' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get active positions
     */
    public function getActivePositions(Request $request): JsonResponse
    {
        $minPremium = (float)$request->get('min_remaining', 10000);

        try {
            $positions = $this->processor->getActivePositions($minPremium);

            return response()->json([
                'positions' => $positions,
                'count' => count($positions),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting active positions', ['error' => $e->getMessage()]);

            return response()->json([
                'positions' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get building positions
     */
    public function getBuildingPositions(Request $request): JsonResponse
    {
        try {
            $positions = $this->processor->getBuildingPositions(10);

            return response()->json([
                'positions' => $positions,
                'count' => count($positions),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting building positions', ['error' => $e->getMessage()]);

            return response()->json([
                'positions' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get exiting positions
     */
    public function getExitingPositions(Request $request): JsonResponse
    {
        try {
            $positions = $this->processor->getExitingPositions(10);

            return response()->json([
                'positions' => $positions,
                'count' => count($positions),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting exiting positions', ['error' => $e->getMessage()]);

            return response()->json([
                'positions' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recent tape
     */
    public function getRecentTape(Request $request): JsonResponse
    {
        $limit = (int)$request->get('limit', 100);

        try {
            $tape = $this->processor->getRecentTape($limit);

            return response()->json([
                'tape' => $tape,
                'count' => count($tape),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting recent tape', ['error' => $e->getMessage()]);

            return response()->json([
                'tape' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reset window data
     */
    public function resetWindow(Request $request): JsonResponse
    {
        $window = $request->get('window', 'day');

        try {
            $this->processor->resetWindow($window);

            return response()->json([
                'success' => true,
                'message' => "Window '{$window}' reset successfully",
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error resetting window', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create snapshot
     */
    public function createSnapshot(Request $request): JsonResponse
    {
        $window = $request->get('window', 'day');

        try {
            $snapshot = $this->processor->createSnapshot($window);

            return response()->json([
                'success' => true,
                'snapshot' => $snapshot,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error creating snapshot', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get current flow data
     */
    public function getCurrentFlow(Request $request): JsonResponse
    {
        $window = $request->get('window', 'day');

        try {
            $flow = $this->processor->getCurrentFlow($window);

            return response()->json([
                'flow' => $flow,
                'window' => $window,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting current flow', ['error' => $e->getMessage()]);

            return response()->json([
                'flow' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all contracts data
     */
    public function getContracts(Request $request): JsonResponse
    {
        $window = $request->get('window', 'day');
        $limit = (int)$request->get('limit', 50);

        try {
            $contracts = [
                'top_bullish' => $this->processor->getTopBullish($window, $limit),
                'top_bearish' => $this->processor->getTopBearish($window, $limit),
                'most_aggressive' => $this->processor->getMostAggressive($window, $limit),
                'high_mid_noise' => $this->processor->getHighMidNoise($window, $limit),
            ];

            return response()->json([
                'contracts' => $contracts,
                'window' => $window,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting contracts', ['error' => $e->getMessage()]);

            return response()->json([
                'contracts' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific contract flow
     */
    public function getContractFlow(Request $request, string $contractKey): JsonResponse
    {
        try {
            // Parse contract key to extract symbol, strike, type, expiration
            $parts = explode('_', $contractKey);
            if (count($parts) < 4) {
                return response()->json([
                    'error' => 'Invalid contract key format'
                ], 400);
            }

            $contract = [
                'symbol' => $parts[0],
                'strike' => (float)$parts[1],
                'type' => $parts[2],
                'expiration' => $parts[3],
            ];

            // For now, return basic contract info
            // In a full implementation, you'd fetch detailed flow data for this contract
            return response()->json([
                'contract' => $contract,
                'contract_key' => $contractKey,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting contract flow', ['error' => $e->getMessage()]);

            return response()->json([
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all positions
     */
    public function getPositions(Request $request): JsonResponse
    {
        $minPremium = (float)$request->get('min_remaining', 10000);

        try {
            $positions = $this->processor->getActivePositions($minPremium);

            return response()->json([
                'positions' => $positions,
                'count' => count($positions),
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting positions', ['error' => $e->getMessage()]);

            return response()->json([
                'positions' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get top bullish contracts
     */
    public function getTopBullishContracts(Request $request): JsonResponse
    {
        $window = $request->get('window', 'day');
        $limit = (int)$request->get('limit', 10);

        try {
            $contracts = $this->processor->getTopBullish($window, $limit);

            return response()->json([
                'contracts' => $contracts,
                'count' => count($contracts),
                'window' => $window,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting top bullish contracts', ['error' => $e->getMessage()]);

            return response()->json([
                'contracts' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get top bearish contracts
     */
    public function getTopBearishContracts(Request $request): JsonResponse
    {
        $window = $request->get('window', 'day');
        $limit = (int)$request->get('limit', 10);

        try {
            $contracts = $this->processor->getTopBearish($window, $limit);

            return response()->json([
                'contracts' => $contracts,
                'count' => count($contracts),
                'window' => $window,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting top bearish contracts', ['error' => $e->getMessage()]);

            return response()->json([
                'contracts' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get most aggressive contracts
     */
    public function getMostAggressiveContracts(Request $request): JsonResponse
    {
        $window = $request->get('window', 'day');
        $limit = (int)$request->get('limit', 10);

        try {
            $contracts = $this->processor->getMostAggressive($window, $limit);

            return response()->json([
                'contracts' => $contracts,
                'count' => count($contracts),
                'window' => $window,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting most aggressive contracts', ['error' => $e->getMessage()]);

            return response()->json([
                'contracts' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get high MID noise contracts
     */
    public function getHighMidNoiseContracts(Request $request): JsonResponse
    {
        $window = $request->get('window', 'day');
        $limit = (int)$request->get('limit', 10);

        try {
            $contracts = $this->processor->getHighMidNoise($window, $limit);

            return response()->json([
                'contracts' => $contracts,
                'count' => count($contracts),
                'window' => $window,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error getting high MID noise contracts', ['error' => $e->getMessage()]);

            return response()->json([
                'contracts' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create snapshots (batch)
     */
    public function createSnapshots(Request $request): JsonResponse
    {
        $windows = $request->get('windows', ['1m', '5m', '15m', 'day']);

        try {
            $snapshots = [];
            foreach ($windows as $window) {
                $snapshots[$window] = $this->processor->createSnapshot($window);
            }

            return response()->json([
                'success' => true,
                'snapshots' => $snapshots,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error creating snapshots', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
