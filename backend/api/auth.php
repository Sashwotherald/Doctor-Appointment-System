<?php
/**
 * Auth API Endpoint
 * Routes: login, register, profile, forgotPassword, resetPassword, validateToken
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../controllers/AuthController.php';

// ----- Send a JSON response and stop execution -----
function sendResponse($payload)
{
    echo json_encode($payload);
    exit;
}

// ----- Parse the JSON body from a POST request -----
function getJsonBody()
{
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);
    return is_array($data) ? $data : [];
}

// ----- Ensure the request method is POST -----
function requirePostMethod()
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendResponse(['success' => false, 'message' => 'POST method required']);
    }
}

// Read the action query parameter
$action = $_GET['action'] ?? '';

// Route to the correct handler
switch ($action) {
    case 'login':
        requirePostMethod();
        sendResponse(handleLogin(getJsonBody()));
        break;

    case 'logout':
        if (session_status() !== PHP_SESSION_NONE) {
            session_destroy();
            $_SESSION = [];
        }
        sendResponse(['success' => true, 'message' => 'Logged out successfully']);
        break;

    case 'register':
        requirePostMethod();
        sendResponse(handleRegister(getJsonBody()));
        break;

    case 'profile':
        $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
        if (!$userId) {
            sendResponse(['success' => false, 'message' => 'User ID required']);
        }
        sendResponse(handleGetProfile($userId));
        break;

    case 'forgotPassword':
        requirePostMethod();
        sendResponse(handleForgotPassword(getJsonBody()));
        break;

    case 'resetPassword':
        requirePostMethod();
        sendResponse(handleResetPassword(getJsonBody()));
        break;

    case 'validateOtp':
        $otp = $_GET['otp'] ?? '';
        sendResponse(handleValidateOtp($otp));
        break;

    default:
        sendResponse(['success' => false, 'message' => 'Invalid action']);
}
