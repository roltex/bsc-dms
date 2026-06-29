<?php
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$app->make("Illuminate\Contracts\Console\Kernel")->bootstrap();

$url = rtrim(App\Models\Setting::get("adata_api_url", ""), "/");
$key = App\Models\Setting::get("adata_api_key", "");

// Use BIN from your actual partner
$testBin = "100940005678";
if (isset($argv[1])) $testBin = $argv[1];

echo "BIN: $testBin\n";

// Step 1
$initUrl = "$url/company/info/$key?iinBin=$testBin";
$ch = curl_init($initUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept: application/json"]);
$r1 = curl_exec($ch);
$code1 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Step 1 HTTP: $code1\n";
echo "Step 1 Response: $r1\n";

$j1 = json_decode($r1, true);
$token = $j1['token'] ?? $j1['data']['token'] ?? null;
echo "Token: " . ($token ?? "NULL") . "\n\n";

if (!$token) { echo "STOP: no token\n"; exit(1); }

// Step 2 - poll
$checkUrl = "$url/company/info/check/$key?token=$token";
echo "Check URL: $checkUrl\n\n";

for ($i = 1; $i <= 30; $i++) {
    sleep(2);
    $ch = curl_init($checkUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Accept: application/json"]);
    $r2 = curl_exec($ch);
    $code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $j2 = json_decode($r2, true);
    $msg = $j2['message'] ?? '???';
    $success = $j2['success'] ?? null;
    echo "Attempt $i ({$i}x2s) HTTP=$code2 success=$success message=$msg\n";
    
    if ($msg === 'ready') {
        echo "\nDATA KEYS: " . implode(', ', array_keys($j2)) . "\n";
        if (isset($j2['data'])) {
            echo "DATA sub-keys: " . implode(', ', array_keys($j2['data'])) . "\n";
            if (isset($j2['data']['basic'])) {
                echo "BASIC keys: " . implode(', ', array_keys($j2['data']['basic'])) . "\n";
                echo "company_name: " . ($j2['data']['basic']['name_ru'] ?? 'N/A') . "\n";
            }
        }
        break;
    }
    
    if ($msg !== 'wait' && $msg !== '???') {
        echo "RAW: $r2\n";
        break;
    }
}
