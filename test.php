<?php

// WebSocket test script for Schwab streaming (Native PHP)
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Configuration
$contractSymbol = 'SPXW  260506C07260000'; // Symbol to monitor

// Get Trader API token (needed for streaming)
$traderToken = Cache::get('schwab_trader_access_token');
if (!$traderToken) {
    die("Error: No trader access token found. Please authenticate with Trader API first.\n");
}

// Get streaming credentials
$streamingCreds = Cache::get('schwab_streaming_credentials');
if (!$streamingCreds) {
    die("Error: No streaming credentials found. Please load them first.\n");
}

echo "=== Schwab WebSocket Test ===\n";
echo "Contract: $contractSymbol\n";
echo "Connecting to WebSocket...\n\n";

// Parse WebSocket URL
$url = parse_url($streamingCreds['streamerSocketUrl']);
$host = $url['host'];
$port = $url['port'] ?? ($url['scheme'] === 'wss' ? 443 : 80);
$path = $url['path'] ?? '/';

// Create WebSocket connection
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ]
]);

$socket = stream_socket_client(
    "ssl://{$host}:{$port}",
    $errno,
    $errstr,
    30,
    STREAM_CLIENT_CONNECT,
    $context
);

if (!$socket) {
    die("Error: Could not connect to WebSocket: $errstr ($errno)\n");
}

// Send WebSocket handshake
$key = base64_encode(random_bytes(16));
$handshake = "GET {$path} HTTP/1.1\r\n" .
    "Host: {$host}\r\n" .
    "Upgrade: websocket\r\n" .
    "Connection: Upgrade\r\n" .
    "Sec-WebSocket-Key: {$key}\r\n" .
    "Sec-WebSocket-Version: 13\r\n\r\n";

fwrite($socket, $handshake);

// Read handshake response
$response = '';
while (strpos($response, "\r\n\r\n") === false) {
    $response .= fread($socket, 1024);
}

if (strpos($response, '101 Switching Protocols') === false) {
    die("Error: WebSocket handshake failed\n$response\n");
}

echo "✓ Connected to WebSocket\n";

// Helper function to send WebSocket frame
function sendWebSocketFrame($socket, $data) {
    $frame = chr(0x81); // Text frame, FIN bit set
    $len = strlen($data);
    
    if ($len <= 125) {
        $frame .= chr($len | 0x80); // Masked
    } elseif ($len <= 65535) {
        $frame .= chr(126 | 0x80) . pack('n', $len);
    } else {
        $frame .= chr(127 | 0x80) . pack('J', $len);
    }
    
    // Masking key
    $mask = random_bytes(4);
    $frame .= $mask;
    
    // Mask the data
    for ($i = 0; $i < $len; $i++) {
        $frame .= $data[$i] ^ $mask[$i % 4];
    }
    
    fwrite($socket, $frame);
}

// Helper function to read WebSocket frame
function readWebSocketFrame($socket) {
    $header = fread($socket, 2);
    if (strlen($header) < 2) return null;
    
    $byte1 = ord($header[0]);
    $byte2 = ord($header[1]);
    
    $len = $byte2 & 0x7F;
    
    if ($len === 126) {
        $len = unpack('n', fread($socket, 2))[1];
    } elseif ($len === 127) {
        $len = unpack('J', fread($socket, 8))[1];
    }
    
    $data = fread($socket, $len);
    return $data;
}

// Send LOGIN request
$loginRequest = [
    'requests' => [[
        'service' => 'ADMIN',
        'command' => 'LOGIN',
                'requestid' => '0',
                'SchwabClientCustomerId' => $streamingCreds['schwabClientCustomerId'],
                'SchwabClientCorrelId' => $streamingCreds['schwabClientCorrelId'],
                'parameters' => [
                    'Authorization' => $streamingCreds['accessToken'],
                    'SchwabClientChannel' => $streamingCreds['schwabClientChannel'],
                    'SchwabClientFunctionId' => $streamingCreds['schwabClientFunctionId']
                ]
            ]]
        ];
        
        echo "→ Sending LOGIN request...\n";
        $conn->send(json_encode($loginRequest));
        
        // Handle incoming messages
        $conn->on('message', function($msg) use ($conn, $streamingCreds, $contractSymbol) {
            $data = json_decode($msg, true);
            
            // Handle login response
            if (isset($data['response'])) {
                foreach ($data['response'] as $resp) {
                    if ($resp['command'] === 'LOGIN' && $resp['content']['code'] === 0) {
                        echo "✓ Login successful\n";
                        
                        // Subscribe to LEVELONE_OPTIONS and TIMESALE_OPTIONS
                        $subscribeRequest = [
                            'requests' => [
                                [
                                    'service' => 'LEVELONE_OPTIONS',
                                    'requestid' => '1',
                                    'command' => 'SUBS',
                                    'SchwabClientCustomerId' => $streamingCreds['schwabClientCustomerId'],
                                    'SchwabClientCorrelId' => $streamingCreds['schwabClientCorrelId'],
                                    'parameters' => [
                                        'keys' => $contractSymbol,
                                        'fields' => '0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41'
                                    ]
                                ],
                                [
                                    'service' => 'TIMESALE_OPTIONS',
                                    'requestid' => '2',
                                    'command' => 'SUBS',
                                    'SchwabClientCustomerId' => $streamingCreds['schwabClientCustomerId'],
                                    'SchwabClientCorrelId' => $streamingCreds['schwabClientCorrelId'],
                                    'parameters' => [
                                        'keys' => $contractSymbol,
                                        'fields' => '0,1,2,3,4'
                                    ]
                                ]
                            ]
                        ];
                        
                        echo "→ Subscribing to $contractSymbol...\n\n";
                        $conn->send(json_encode($subscribeRequest));
                    }
                }
            }
            
            // Handle data messages
            if (isset($data['data'])) {
                foreach ($data['data'] as $item) {
                    if ($item['service'] === 'LEVELONE_OPTIONS') {
                        echo "\n=== LEVELONE_OPTIONS (Quote) ===\n";
                        foreach ($item['content'] as $quote) {
                            echo "Bid: " . ($quote['2'] ?? 'N/A') . "\n";
                            echo "Ask: " . ($quote['3'] ?? 'N/A') . "\n";
                            echo "Last: " . ($quote['4'] ?? 'N/A') . "\n";
                            echo "Mark: " . ($quote['5'] ?? 'N/A') . "\n";
                            echo "Volume: " . ($quote['13'] ?? 'N/A') . "\n";
                            echo "Delta: " . ($quote['18'] ?? 'N/A') . "\n";
                            echo "Gamma: " . ($quote['19'] ?? 'N/A') . "\n";
                            echo "IV: " . ($quote['17'] ?? 'N/A') . "\n";
                        }
                    } elseif ($item['service'] === 'TIMESALE_OPTIONS') {
                        echo "\n=== TIMESALE_OPTIONS (Trade) ===\n";
                        foreach ($item['content'] as $trade) {
                            $time = date('H:i:s', intval($trade['1']) / 1000);
                            echo "Time: $time\n";
                            echo "Price: " . ($trade['2'] ?? 'N/A') . "\n";
                            echo "Size: " . ($trade['3'] ?? 'N/A') . "\n";
                            echo "Sequence: " . ($trade['4'] ?? 'N/A') . "\n";
                        }
                    }
                }
            }
            
            // Handle heartbeat
            if (isset($data['notify'])) {
                foreach ($data['notify'] as $notify) {
                    if ($notify['heartbeat']) {
                        echo "."; // Show heartbeat
                    }
                }
            }
        });
        
        $conn->on('close', function($code = null, $reason = null) {
            echo "\n✗ Connection closed: $reason\n";
        });
        
    }, function($e) {
        echo "✗ Could not connect: {$e->getMessage()}\n";
    });

echo "Listening for data... (Press Ctrl+C to stop)\n";
$loop->run();
