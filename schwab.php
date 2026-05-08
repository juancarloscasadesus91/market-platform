<?php

// WebSocket test script for Schwab streaming (Native PHP - No external dependencies)
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Configuration
$contractSymbol = 'SPXW  260507C07360000'; // Symbol to monitor

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

echo "=== Schwab LEVELONE_OPTIONS Flow Monitor ===\n";
echo "Contract: $contractSymbol\n\n";

// Parse WebSocket URL
$url = parse_url($streamingCreds['streamerSocketUrl']);
$host = $url['host'];
$port = $url['port'] ?? 443;
$path = $url['path'] ?? '/';

echo "Connecting to $host:$port...\n";

// Create SSL socket
$context = stream_context_create([
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
    ],
]);

$socket = stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);

if (!$socket) {
    die("Error: $errstr ($errno)\n");
}

// WebSocket handshake
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
    $chunk = fread($socket, 1024);
    if ($chunk === false || $chunk === '') {
        break;
    }
    $response .= $chunk;
}

if (strpos($response, '101') === false) {
    die("Handshake failed\n$response\n");
}

echo "✓ Connected\n";

// Send WebSocket frame
function wsSend($socket, string $data): void
{
    $frame = chr(0x81);
    $len = strlen($data);

    if ($len <= 125) {
        $frame .= chr($len | 0x80);
    } elseif ($len <= 65535) {
        $frame .= chr(126 | 0x80) . pack('n', $len);
    } else {
        // Most Schwab requests are small. This is only for completeness.
        $frame .= chr(127 | 0x80) . pack('J', $len);
    }

    $mask = random_bytes(4);
    $frame .= $mask;

    for ($i = 0; $i < $len; $i++) {
        $frame .= $data[$i] ^ $mask[$i % 4];
    }

    fwrite($socket, $frame);
}

// Read exactly N bytes from socket, useful for non-blocking mode too
function wsReadBytes($socket, int $length): ?string
{
    $data = '';
    $attempts = 0;

    while (strlen($data) < $length && $attempts < 50) {
        $chunk = fread($socket, $length - strlen($data));

        if ($chunk === false) {
            return null;
        }

        if ($chunk === '') {
            usleep(10000);
            $attempts++;
            continue;
        }

        $data .= $chunk;
    }

    return strlen($data) === $length ? $data : null;
}

// Read WebSocket frame
function wsRead($socket): ?string
{
    $header = wsReadBytes($socket, 2);
    if (!$header || strlen($header) < 2) {
        return null;
    }

    $firstByte = ord($header[0]);
    $secondByte = ord($header[1]);
    $opcode = $firstByte & 0x0F;
    $masked = ($secondByte & 0x80) !== 0;
    $len = $secondByte & 0x7F;

    if ($opcode === 0x8) {
        return null; // close frame
    }

    if ($len === 126) {
        $extended = wsReadBytes($socket, 2);
        if (!$extended) return null;
        $len = unpack('n', $extended)[1];
    } elseif ($len === 127) {
        $extended = wsReadBytes($socket, 8);
        if (!$extended) return null;
        $parts = unpack('N2', $extended);
        $len = ($parts[1] << 32) + $parts[2];
    }

    $mask = '';
    if ($masked) {
        $mask = wsReadBytes($socket, 4);
        if (!$mask) return null;
    }

    $payload = $len > 0 ? wsReadBytes($socket, $len) : '';
    if ($payload === null) {
        return null;
    }

    if ($masked) {
        $decoded = '';
        for ($i = 0; $i < $len; $i++) {
            $decoded .= $payload[$i] ^ $mask[$i % 4];
        }
        return $decoded;
    }

    return $payload;
}

function money(float $value): string
{
    return '$' . number_format($value, 0);
}

function classifyTradeSide(float $last, float $bid, float $ask): string
{
    if ($last <= 0 || $bid <= 0 || $ask <= 0 || $ask <= $bid) {
        return 'MID';
    }

    $spread = $ask - $bid;
    $askZone = $ask - ($spread * 0.30);
    $bidZone = $bid + ($spread * 0.30);

    if ($last >= $askZone) {
        return 'BUY_ASK';
    }

    if ($last <= $bidZone) {
        return 'SELL_BID';
    }

    return 'MID';
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
            'SchwabClientFunctionId' => $streamingCreds['schwabClientFunctionId'],
        ],
    ]],
], JSON_UNESCAPED_SLASHES);

echo "→ Logging in...\n";
wsSend($socket, $loginReq);

// Wait for login response
$loginSuccess = false;
while (!$loginSuccess) {
    $msg = wsRead($socket);
    if (!$msg) {
        continue;
    }

    $data = json_decode($msg, true);
    if (!isset($data['response'])) {
        continue;
    }

    foreach ($data['response'] as $resp) {
        if (($resp['command'] ?? '') === 'LOGIN' && (int)($resp['content']['code'] ?? -1) === 0) {
            echo "✓ Login successful\n";
            $loginSuccess = true;
            break;
        }

        if (($resp['command'] ?? '') === 'LOGIN') {
            $msgText = $resp['content']['msg'] ?? 'Unknown login error';
            die("Login failed: $msgText\n");
        }
    }
}

// SUBSCRIBE TO LEVELONE_OPTIONS ONLY
// Fields used here:
// 0=symbol, 2=bid, 3=ask, 4=last, 5=mark, 8=last size,
// 13=total volume, 15=trade time, 17=IV, 18=delta, 19=gamma
$subReq = json_encode([
    'requests' => [[
        'service' => 'LEVELONE_OPTIONS',
        'requestid' => '1',
        'command' => 'SUBS',
        'SchwabClientCustomerId' => $streamingCreds['schwabClientCustomerId'],
        'SchwabClientCorrelId' => $streamingCreds['schwabClientCorrelId'],
        'parameters' => [
            'keys' => $contractSymbol,
            'fields' => '0,2,3,4,5,8,13,15,17,18,19',
        ],
    ]],
], JSON_UNESCAPED_SLASHES);

echo "→ Subscribing to LEVELONE_OPTIONS: $contractSymbol...\n\n";
wsSend($socket, $subReq);

echo "Listening for data (Ctrl+C to stop)...\n";
echo "Note: This infers buy/sell pressure from last price vs bid/ask. It is not true Time & Sales.\n\n";

stream_set_blocking($socket, false);

$lastVolume = null; // null = not initialized yet
$lastPrice = 0.0;
$tradeCount = 0;

$buyVolume = 0;
$sellVolume = 0;
$midVolume = 0;

$buyPremium = 0.0;
$sellPremium = 0.0;
$midPremium = 0.0;

echo "\n=== LIVE FLOW MONITOR ===\n\n";

while (true) {
    $msg = wsRead($socket);

    if ($msg) {
        $data = json_decode($msg, true);
        if (isset($data['data'])) {
            foreach ($data['data'] as $item) {
                if (($item['service'] ?? '') !== 'LEVELONE_OPTIONS') {
                    continue;
                }

                foreach ($item['content'] as $q) {
                    $bid = (float)($q['2'] ?? 0);
                    $ask = (float)($q['3'] ?? 0);
                    $last = (float)($q['4'] ?? 0);
                    $mark = (float)($q['5'] ?? 0);
                    $lastSize = (int)($q['8'] ?? 0);
                    $volume = (int)($q['13'] ?? 0);
                    $tradeTime = $q['15'] ?? 0;
                    $iv = $q['17'] ?? 0;
                    $delta = $q['18'] ?? 0;
                    $gamma = $q['19'] ?? 0;

                    // Initialize lastVolume on first message
                    if ($lastVolume === null && $volume >= 0) {
                        $lastVolume = $volume;
                        echo "📍 Initial volume: {$volume}\n";
                    }

                    $deltaVolume = $volume - $lastVolume;

                    // Detect trade: volume increased AND we have valid price data
                    if ($lastVolume !== null && $deltaVolume > 0 && $last > 0) {
                        $tradeCount++;

                        // If lastSize is not available or looks stale, use volume difference.
                        $size = $lastSize > 0 ? $lastSize : $deltaVolume;
                        if ($size > $deltaVolume && $deltaVolume > 0) {
                            $size = $deltaVolume;
                        }

                        $premium = $last * $size * 100;
                        $side = classifyTradeSide($last, $bid, $ask);

                        if ($side === 'BUY_ASK') {
                            $buyVolume += $size;
                            $buyPremium += $premium;
                        } elseif ($side === 'SELL_BID') {
                            $sellVolume += $size;
                            $sellPremium += $premium;
                        } else {
                            $midVolume += $size;
                            $midPremium += $premium;
                        }

                        $knownPremium = $buyPremium + $sellPremium;
                        $buyPct = $knownPremium > 0 ? ($buyPremium / $knownPremium) * 100 : 0;
                        $sellPct = $knownPremium > 0 ? ($sellPremium / $knownPremium) * 100 : 0;
                        $netPremium = $buyPremium - $sellPremium;

                        $time = $tradeTime > 0 ? date('H:i:s', intval($tradeTime / 1000)) : date('H:i:s');

                        echo "\n\n🔔 TRADE #{$tradeCount}";
                        echo "\nTime: {$time}";
                        echo "\nPrice: {$last} | Size: {$size} | Side: {$side}";
                        echo "\nPremium: " . money($premium);

                        echo "\n\n📊 FLOW SUMMARY";
                        echo "\nBuy @ Ask: " . money($buyPremium) . " | Vol: {$buyVolume} | " . round($buyPct, 1) . "%";
                        echo "\nSell @ Bid: " . money($sellPremium) . " | Vol: {$sellVolume} | " . round($sellPct, 1) . "%";
                        echo "\nMID/Unknown: " . money($midPremium) . " | Vol: {$midVolume}";
                        echo "\nNET: " . money($netPremium);

                        if ($netPremium > 0) {
                            echo " 🟢 BUY PRESSURE";
                        } elseif ($netPremium < 0) {
                            echo " 🔴 SELL PRESSURE";
                        } else {
                            echo " ⚪ NEUTRAL";
                        }

                        echo "\n";
                    }

                    if ($last !== $lastPrice || $volume !== $lastVolume) {
                        echo "\r📊 Bid: {$bid} | Ask: {$ask} | Last: {$last} | Mark: {$mark} | Vol: {$volume} | Δ: {$delta} | Γ: {$gamma} | IV: {$iv}%";
                        $lastPrice = $last;
                    }

                    if ($volume > 0) {
                        $lastVolume = $volume;
                    }
                }
            }
        }

        if (isset($data['response'])) {
            foreach ($data['response'] as $resp) {
                $code = (int)($resp['content']['code'] ?? 0);
                if ($code !== 0) {
                    $service = $resp['service'] ?? 'UNKNOWN_SERVICE';
                    $message = $resp['content']['msg'] ?? 'Unknown error';
                    echo "\n⚠️ {$service}: {$message}\n";
                }
            }
        }
    }

    usleep(100000); // 100ms
}
