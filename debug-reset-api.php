<?php
require_once __DIR__ . '/bootstrap.php';
// Debug script for password reset API using PHP only.
$url = 'http://localhost/IECEP-LSC-MEMSYS/public/api/reset-password.php';

$data = [
    'token' => '27908f80c743521f8330820c4afa5b7bafbf3a77023f6162e9180beb7807b5cf',
    'new_password' => 'NewPassword2026!'
];

$options = [
    'http' => [
        'method'  => 'POST',
        'header'  => "Content-Type: application/json\r\n",
        'content' => json_encode($data),
        'ignore_errors' => true,
    ],
];

$context  = stream_context_create($options);
$response = file_get_contents($url, false, $context);

$statusLine = isset($http_response_header[0]) ? $http_response_header[0] : 'No status line';

echo "URL: $url\n";
echo "REQUEST: " . json_encode($data) . "\n";
echo "STATUS: $statusLine\n";
echo "RESPONSE: \n";
echo $response . "\n";

if (!empty($response)) {
    $decoded = json_decode($response, true);
    echo "DECODED: \n";
    var_dump($decoded);
}
