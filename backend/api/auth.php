<?php
/**
 * Auth API Endpoint
 * Handles: login, register, profile
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../controllers/AuthController.php';

function sendResponse($payload) {
    echo json_encode($payload);
    exit;
}

function getJsonBody() {
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);
    return is_array($data) ? $data : [];
}

function requirePostMethod() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(['success' => false, 'message' => 'POST method required']);
    }
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'login':
        requirePostMethod();
        sendResponse(handleLogin(getJsonBody()));
        
    case 'register':
        requirePostMethod();
        sendResponse(handleRegister(getJsonBody()));
        
    case 'profile':
        $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
        if (!$userId) {
            sendResponse(['success' => false, 'message' => 'User ID required']);
        }
        sendResponse(handleGetProfile($userId));
        
    default:
        sendResponse(['success' => false, 'message' => 'Invalid action']);
}
