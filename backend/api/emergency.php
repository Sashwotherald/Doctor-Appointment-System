<?php
/**
 * Emergency API Endpoint
 * Handles AI-powered emergency booking requests
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../controllers/EmergencyController.php';

function sendResponse($payload)
{
    echo json_encode($payload);
    exit;
}

function getJsonBody()
{
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);
    return is_array($data) ? $data : [];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(['success' => false, 'message' => 'POST method required']);
}

// Require patient authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'patient') {
    sendResponse(['success' => false, 'message' => 'Unauthorized. Only patients can request emergency booking.']);
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'book':
        sendResponse(handleEmergencyBook(getJsonBody(), $_SESSION['user_id']));
        break;
    default:
        sendResponse(['success' => false, 'message' => 'Invalid action']);
}
