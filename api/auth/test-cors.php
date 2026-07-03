<?php
require_once '../../config/cors.php';
header('Content-Type: application/json');

// Get all response headers that have been sent
$response_headers = headers_list();

echo json_encode([
    'status' => 'OK',
    'origin_received' => $_SERVER['HTTP_ORIGIN'] ?? 'no origin (direct browser request)',
    'method' => $_SERVER['REQUEST_METHOD'],
    'headers_sent' => headers_sent(),
    'response_headers' => $response_headers,
    'message' => 'Check response_headers array for Access-Control-Allow-Origin'
], JSON_PRETTY_PRINT);
?>