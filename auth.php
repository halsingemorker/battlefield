<?php
require_once __DIR__ . '/config.php';
session_name(SESSION_NAME);
session_start();

if (empty($_SESSION['authenticated'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}
