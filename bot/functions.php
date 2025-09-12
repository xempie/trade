<?php

function time_ms() {
    return round(microtime(true) * 1000);
}

function signature($params, $secret) {
    return hash_hmac('sha256', http_build_query($params), $secret);
}

function bingx_request($endpoint, $params, $apiKey, $baseUrl) {
    $url = $baseUrl . $endpoint . '?' . http_build_query($params);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "X-BX-APIKEY: $apiKey"
        ],
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    curl_close($ch);

    error_log("📥 [$httpCode] API CALL TO $endpoint\nRequest URL: $url\nResponse: $response\nCURL ERROR: $curlErr\n");

    return json_decode($response, true);
}
