<?php
// API Proxy to work around external access restrictions
require_once "auth/config.php";
requireAuth();

header("Content-Type: application/json");

$endpoint = $_GET["endpoint"] ?? "";
$method = $_SERVER["REQUEST_METHOD"];

if (!$endpoint) {
    echo json_encode(["success" => false, "error" => "No endpoint specified"]);
    exit;
}

// Whitelist allowed endpoints
$allowedEndpoints = [
    "debug_order_data.php",
    "place_order.php",
    "get_performance_summary.php",
    "update_risk_free_sl.php"
];

if (!in_array($endpoint, $allowedEndpoints)) {
    echo json_encode(["success" => false, "error" => "Endpoint not allowed"]);
    exit;
}

// Get the request body for POST requests
$requestBody = "";
if ($method === "POST") {
    $requestBody = file_get_contents("php://input");
}

// Make internal request to the API
$apiUrl = "http://localhost/ta/api/" . $endpoint;

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

if ($method === "POST") {
    curl_setopt($ch, CURLOPT_POSTFIELDS, $requestBody);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
}

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

http_response_code($httpCode);
echo $response;
?>