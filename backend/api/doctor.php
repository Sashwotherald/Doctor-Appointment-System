<?php
/**
 * Doctor API Endpoint
 * Routes for profile, photo upload, availability, appointments, patients, notifications
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../controllers/DoctorController.php';
require_once __DIR__ . '/../controllers/PatientController.php';

// ----- Send JSON response and stop -----
function sendResponse($payload)
{
    echo json_encode($payload);
    exit;
}

// ----- Parse JSON body -----
function getJsonBody()
{
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);
    return is_array($data) ? $data : [];
}

$action = $_GET['action'] ?? '';

// Check Doctor Auth
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(403);
    sendResponse(['success' => false, 'message' => 'Unauthorized']);
}

$userId = $_SESSION['user_id'];

switch ($action) {
    case 'getProfile':
        sendResponse(handleGetDoctorProfile($userId));
        break;

    case 'updateProfile':
        sendResponse(handleUpdateDoctorProfile($userId, getJsonBody()));
        break;

    case 'uploadPhoto':
        sendResponse(handleUploadDoctorPhoto($userId, $_FILES['photo'] ?? []));
        break;

    case 'setAvailability':
        $data = getJsonBody();
        sendResponse(handleSetAvailability($userId, $data['schedules'] ?? []));
        break;

    case 'getAvailability':
        $availability = getDoctorAvailability($userId);
        sendResponse(['success' => true, 'availability' => $availability]);
        break;

    case 'getAppointments':
        $status = $_GET['status'] ?? null;
        sendResponse(handleGetDoctorAppointments($userId, $status));
        break;

    case 'updateAppointmentStatus':
        sendResponse(handleUpdateAppointmentStatus($userId, getJsonBody()));
        break;

    case 'getPatients':
        sendResponse(handleGetDoctorPatients($userId));
        break;

    case 'getDashboard':
        sendResponse(handleGetDoctorDashboard($userId));
        break;

    case 'getNotifications':
        sendResponse(['success' => true, 'notifications' => getAllNotifications($userId)]);
        break;

    case 'markAllRead':
        markAllNotificationsRead($userId);
        sendResponse(['success' => true, 'message' => 'All notifications marked as read']);
        break;

    default:
        sendResponse(['success' => false, 'message' => 'Invalid action']);
}
