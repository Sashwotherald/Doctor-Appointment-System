<?php
/**
 * Patient API Endpoint
 * Handles: doctors listing, appointments, profile, notifications
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

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

function getRequiredField($data, $key, $message) {
    if (!isset($data[$key]) || $data[$key] === '') {
        sendResponse(['success' => false, 'message' => $message]);
    }

    return $data[$key];
}

$action = $_GET['action'] ?? '';
$userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);

// Validate user_id for most actions
if ($action !== 'getDoctors' && $action !== 'getSpecializations' && !$userId) {
    sendResponse(['success' => false, 'message' => 'User ID required']);
}

switch ($action) {
    case 'getDoctors':
        $filters = [
            'specialization' => $_GET['specialization'] ?? null,
            'date' => $_GET['date'] ?? null
        ];
        sendResponse(handleGetDoctors($filters));
        
    case 'getSpecializations':
        require_once __DIR__ . '/../config/constants.php';
        sendResponse(['success' => true, 'specializations' => SPECIALIZATIONS]);
        
    case 'bookAppointment':
        sendResponse(handleBookAppointment($userId, getJsonBody()));
        
    case 'cancelAppointment':
        $data = getJsonBody();
        $appointmentId = getRequiredField($data, 'appointment_id', 'Appointment ID is required');
        sendResponse(handleCancelAppointment($userId, $appointmentId));
        
    case 'rescheduleAppointment':
        sendResponse(handleRescheduleAppointment($userId, getJsonBody()));
        
    case 'getAppointments':
        $status = $_GET['status'] ?? null;
        sendResponse(handleGetPatientAppointments($userId, $status));
        
    case 'getProfile':
        sendResponse(handleGetPatientProfile($userId));
        
    case 'updateProfile':
        sendResponse(handleUpdatePatientProfile($userId, getJsonBody()));
        
    case 'getDashboard':
        sendResponse(handleGetPatientDashboard($userId));
        
    case 'getNotifications':
        sendResponse(['success' => true, 'notifications' => getAllNotifications($userId)]);
        
    case 'markNotificationRead':
        $data = getJsonBody();
        $notificationId = getRequiredField($data, 'notification_id', 'Notification ID is required');
        markNotificationRead($notificationId, $userId);
        sendResponse(['success' => true]);
        
    case 'markAllRead':
        markAllNotificationsRead($userId);
        sendResponse(['success' => true]);
    
    case 'getDoctorAvailability':
        $doctorId = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT);
        if (!$doctorId) {
            sendResponse(['success' => false, 'message' => 'Doctor ID required']);
        }
        require_once __DIR__ . '/../models/Doctor.php';
        $availability = getDoctorAvailability($doctorId);
        sendResponse(['success' => true, 'availability' => $availability]);
        
    default:
        sendResponse(['success' => false, 'message' => 'Invalid action']);
}
