<?php
/**
 * Doctor API Endpoint
 * Handles: profile, photo upload, availability, appointments, patients
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../controllers/DoctorController.php';
require_once __DIR__ . '/../controllers/PatientController.php';

function sendResponse($payload) {
    echo json_encode($payload);
    exit;
}

function getJsonBody() {
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);
    return is_array($data) ? $data : [];
}

function getRequiredUserId() {
    $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
    if (!$userId) {
        sendResponse(['success' => false, 'message' => 'User ID required']);
    }

    return $userId;
}

$action = $_GET['action'] ?? '';
$userId = getRequiredUserId();

switch ($action) {
    case 'getProfile':
        sendResponse(handleGetDoctorProfile($userId));
        
    case 'updateProfile':
        sendResponse(handleUpdateDoctorProfile($userId, getJsonBody()));
        
    case 'uploadPhoto':
        sendResponse(handleUploadDoctorPhoto($userId, $_FILES['photo'] ?? []));
        
    case 'setAvailability':
        $data = getJsonBody();
        sendResponse(handleSetAvailability($userId, $data['schedules'] ?? []));
        
    case 'getAvailability':
        $availability = getDoctorAvailability($userId);
        sendResponse(['success' => true, 'availability' => $availability]);
        
    case 'getAppointments':
        $status = $_GET['status'] ?? null;
        sendResponse(handleGetDoctorAppointments($userId, $status));
        
    case 'updateAppointmentStatus':
        sendResponse(handleUpdateAppointmentStatus($userId, getJsonBody()));
        
    case 'getPatients':
        sendResponse(handleGetDoctorPatients($userId));
        
    case 'getDashboard':
        sendResponse(handleGetDoctorDashboard($userId));
        
    case 'getNotifications':
        sendResponse(['success' => true, 'notifications' => getAllNotifications($userId)]);

    case 'markAllRead':
        markAllNotificationsRead($userId);
        sendResponse(['success' => true, 'message' => 'All notifications marked as read']);
        
    default:
        sendResponse(['success' => false, 'message' => 'Invalid action']);
}
