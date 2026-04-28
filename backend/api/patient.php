<?php
/**
 * Patient API Endpoint
 * Routes for doctors listing, appointments, profile, notifications
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

// ----- Get a required field or send error -----
function getRequiredField($data, $key, $message)
{
    if (!isset($data[$key]) || $data[$key] === '') {
        sendResponse(['success' => false, 'message' => $message]);
    }
    return $data[$key];
}

$action = $_GET['action'] ?? '';
$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? null;

// Most actions require a logged-in patient (except public doctor listing)
if ($action !== 'getDoctors' && $action !== 'getSpecializations' && $action !== 'getDoctorAvailability' && $action !== 'getDoctorProfile') {
    if (!$userId || $userRole !== 'patient') {
        http_response_code(403);
        sendResponse(['success' => false, 'message' => 'Unauthorized']);
    }
}

switch ($action) {
    case 'getDoctors':
        $filters = [
            'specialization' => $_GET['specialization'] ?? null
        ];
        sendResponse(handleGetDoctors($filters));
        break;

    case 'getSpecializations':
        require_once __DIR__ . '/../config/constants.php';
        sendResponse(['success' => true, 'specializations' => SPECIALIZATIONS]);
        break;

    case 'bookAppointment':
        sendResponse(handleBookAppointment($userId, getJsonBody()));
        break;

    case 'cancelAppointment':
        $data = getJsonBody();
        $appointmentId = getRequiredField($data, 'appointment_id', 'Appointment ID is required');
        sendResponse(handleCancelAppointment($userId, $appointmentId));
        break;

    case 'rescheduleAppointment':
        sendResponse(handleRescheduleAppointment($userId, getJsonBody()));
        break;

    case 'getAppointments':
        $status = $_GET['status'] ?? null;
        sendResponse(handleGetPatientAppointments($userId, $status));
        break;

    case 'getProfile':
        sendResponse(handleGetPatientProfile($userId));
        break;

    case 'updateProfile':
        sendResponse(handleUpdatePatientProfile($userId, getJsonBody()));
        break;

    case 'getDashboard':
        sendResponse(handleGetPatientDashboard($userId));
        break;

    case 'getNotifications':
        sendResponse(['success' => true, 'notifications' => getAllNotifications($userId)]);
        break;

    case 'markNotificationRead':
        $data = getJsonBody();
        $notificationId = getRequiredField($data, 'notification_id', 'Notification ID is required');
        markNotificationRead($notificationId, $userId);
        sendResponse(['success' => true]);
        break;

    case 'markAllRead':
        markAllNotificationsRead($userId);
        sendResponse(['success' => true]);
        break;

    case 'getDoctorAvailability':
        $doctorId = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT);
        if (!$doctorId) {
            sendResponse(['success' => false, 'message' => 'Doctor ID required']);
        }
        require_once __DIR__ . '/../models/Doctor.php';
        $availability = getDoctorAvailability($doctorId);
        sendResponse(['success' => true, 'availability' => $availability]);
        break;

    case 'getDoctorProfile':
        $doctorId = filter_input(INPUT_GET, 'doctor_id', FILTER_VALIDATE_INT);
        if (!$doctorId) {
            sendResponse(['success' => false, 'message' => 'Doctor ID required']);
        }
        require_once __DIR__ . '/../models/Doctor.php';
        $profile = getDoctorProfile($doctorId);
        if (!$profile) {
            sendResponse(['success' => false, 'message' => 'Doctor not found']);
        }
        $availability = getDoctorAvailability($doctorId);
        $profile['availability'] = $availability;
        sendResponse(['success' => true, 'doctor' => $profile]);
        break;

    default:
        sendResponse(['success' => false, 'message' => 'Invalid action']);
}
