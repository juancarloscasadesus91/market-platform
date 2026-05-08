<?php

// WebSocket test script for Schwab streaming (Native PHP - No external dependencies)
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Configuration
$contractSymbol = 'SPXW  260506C07360000'; // Symbol to monitor

// Get Trader API token
$traderToken = Cache::get('schwab_trader_access_token');
if (!$traderToken) {
    die("Error: No trader access token found.\n");
}

// Get streaming credentials
$streamingCreds = Cache::get('schwab_streaming_credentials');
if (!$streamingCreds) {
    die("Error: No streaming credentials found.\n");
}

// Add access token to streaming credentials
$streamingCreds['accessToken'] = $traderToken;

echo "=== Schwab WebSocket Test ===\n";
echo "Contract: $contractSymbol\n\n";

// Parse WebSocket URL
$url = parse_url($streamingCreds['streamerSocketUrl']);
$host = $url['host'];
$port = $url['port'] ?? 443;
$path = $url['path'] ?? '/';

echo "Connecting to $host:$port...\n";

// Create SSL socket
$context = stream_context_create(['ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
$socket = stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);

if (!$socket) {
    die("Error: $errstr ($errno)\n");
}

// WebSocket handshake
$key = base64_encode(random_bytes(16));
$handshake = "GET {$path} HTTP/1.1\r\nHost: {$host}\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: {$key}\r\nSec-WebSocket-Version: 13\r\n\r\n";
fwrite($socket, $handshake);

// Read handshake response
$response = '';
while (strpos($response, "\r\n\r\n") === false) {
    $response .= fread($socket, 1024);
}

if (strpos($response, '101') === false) {
    die("Handshake failed\n");
}

echo "✓ Connected\n";

// Send WebSocket frame
function wsSend($socket, $data) {
    $frame = chr(0x81);
    $len = strlen($data);
    if ($len <= 125) {
        $frame .= chr($len | 0x80);
    } elseif ($len <= 65535) {
        $frame .= chr(126 | 0x80) . pack('n', $len);
    } else {
        $frame .= chr(127 | 0x80) . pack('J', $len);
    }
    $mask = random_bytes(4);
    $frame .= $mask;
    for ($i = 0; $i < $len; $i++) {
        $frame .= $data[$i] ^ $mask[$i % 4];
    }
    fwrite($socket, $frame);
}

// Read WebSocket frame
function wsRead($socket) {
    $header = fread($socket, 2);
    if (strlen($header) < 2) return null;
    $len = ord($header[1]) & 0x7F;
    if ($len === 126) {
        $len = unpack('n', fread($socket, 2))[1];
    } elseif ($len === 127) {
        $len = unpack('J', fread($socket, 8))[1];
    }
    return fread($socket, $len);
}

// LOGIN
$loginReq = json_encode([
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
]);

echo "→ Logging in...\n";
wsSend($socket, $loginReq);

// Wait for login response
$loginSuccess = false;
while (!$loginSuccess) {
    $msg = wsRead($socket);
    if ($msg) {
        $data = json_decode($msg, true);
        if (isset($data['response'])) {
            foreach ($data['response'] as $resp) {
                if ($resp['command'] === 'LOGIN' && $resp['content']['code'] === 0) {
                    echo "✓ Login successful\n";
                    $loginSuccess = true;
                }
            }
        }
    }
}

// SUBSCRIBE
$subReq = json_encode([
    'requests' => [
//        [
//            'service' => 'LEVELONE_OPTIONS',
//            'requestid' => '1',
//            'command' => 'SUBS',
//            'SchwabClientCustomerId' => $streamingCreds['schwabClientCustomerId'],
//            'SchwabClientCorrelId' => $streamingCreds['schwabClientCorrelId'],
//            'parameters' => [
//                'keys' => $contractSymbol,
//                'fields' => '0,1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41'
//            ]
//        ],
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
]);

echo "→ Subscribing to $contractSymbol...\n\n";
wsSend($socket, $subReq);

echo "Listening for data (Ctrl+C to stop)...\n\n";

// Listen for data
stream_set_blocking($socket, false);
$lastVolume = 0;
$lastPrice = 0;
$tradeCount = 0;

echo "\n=== LIVE MONITORING ===\n";
echo "Note: TIMESALE_OPTIONS not available, detecting trades via volume changes\n\n";

while (true) {
    $msg = wsRead($socket);
    if ($msg) {
        $data = json_decode($msg, true);

        if (isset($data['data'])) {
            foreach ($data['data'] as $item) {
                if ($item['service'] === 'LEVELONE_OPTIONS') {
                    foreach ($item['content'] as $q) {
                        $bid = $q['2'] ?? 0;
                        $ask = $q['3'] ?? 0;
                        $last = $q['4'] ?? 0;
                        $mark = $q['5'] ?? 0;
                        $lastSize = $q['8'] ?? 0; // Last trade size
                        $volume = $q['13'] ?? 0;
                        $tradeTime = $q['15'] ?? 0; // Last trade time in millis
                        $delta = $q['18'] ?? 0;
                        $gamma = $q['19'] ?? 0;
                        $iv = $q['17'] ?? 0;

                        // Detect trade using LAST_SIZE (more accurate than volume diff)
                        if ($lastSize > 0 && $volume > $lastVolume) {
                            $tradeCount++;
                            $time = $tradeTime > 0 ? date('H:i:s', intval($tradeTime / 1000)) : date('H:i:s');

                            // Determine side
                            $side = 'MID';
                            if ($bid > 0 && $ask > 0) {
                                $spread = $ask - $bid;
                                $threshold = $spread * 0.3;
                                if ($last >= $ask - $threshold) {
                                    $side = 'ASK';
                                } elseif ($last <= $bid + $threshold) {
                                    $side = 'BID';
                                }
                            }

                            $premium = round($last * $lastSize * 100);

                            echo "\n🔔 TRADE #$tradeCount\n";
                            echo "Time: $time | Price: $last | Size: $lastSize | Side: $side | Premium: \$$premium\n";
                        }

                        // Show quote update
                        if ($last != $lastPrice || $volume != $lastVolume) {
                            echo "\r📊 Bid: $bid | Ask: $ask | Last: $last | Mark: $mark | Vol: $volume | Δ: $delta | Γ: $gamma | IV: $iv%";
                            $lastPrice = $last;
                        }

                        $lastVolume = $volume;
                    }
                }
            }
        }

        if (isset($data['response'])) {
            foreach ($data['response'] as $resp) {
                if ($resp['content']['code'] !== 0) {
                    echo "\n⚠️  " . $resp['service'] . ": " . $resp['content']['msg'] . "\n";
                }
            }
        }

        if (isset($data['notify'])) {
            // Silent heartbeat
        }
    }
    usleep(100000); // 100ms
}
