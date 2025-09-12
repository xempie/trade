<?php
// Test script to check the limit order prices API
echo "Testing limit order prices API...\n\n";

$url = "https://[REDACTED_HOST]/ta/api/get_limit_order_prices.php";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
echo "cURL Error: $error\n";
echo "Response:\n";
echo $response;
echo "\n\n";

if ($response) {
    $data = json_decode($response, true);
    if ($data) {
        echo "Decoded JSON:\n";
        print_r($data);
    } else {
        echo "Failed to decode JSON\n";
        echo "Raw response: " . substr($response, 0, 1000) . "\n";
    }
}
?>