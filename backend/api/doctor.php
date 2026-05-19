<?php
/**
 * Doctor API Endpoint
<<<<<<< HEAD
 * Handles: profile, photo upload, availability, appointments, patients
=======
 * Routes for profile, photo upload, availability, appointments, patients, notifications
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

<<<<<<< HEAD
=======
// Handle CORS preflight
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

<<<<<<< HEAD
require_once __DIR__ . '/../controllers/DoctorController.php';
require_once __DIR__ . '/../controllers/PatientController.php';

function sendResponse($payload) {
=======
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../controllers/DoctorController.php';
require_once __DIR__ . '/../controllers/PatientController.php';

// ----- Send JSON response and stop -----
function sendResponse($payload)
{
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    echo json_encode($payload);
    exit;
}

<<<<<<< HEAD
function getJsonBody() {
=======
// ----- Parse JSON body -----
function getJsonBody()
{
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    $rawBody = file_get_contents('php://input');
    $data = json_decode($rawBody, true);
    return is_array($data) ? $data : [];
}

<<<<<<< HEAD
function getRequiredUserId() {
    $userId = filter_input(INPUT_GET, 'user_id', FILTER_VALIDATE_INT);
    if (!$userId) {
        sendResponse(['success' => false, 'message' => 'User ID required']);
    }

    return $userId;
}

$action = $_GET['action'] ?? '';
$userId = getRequiredUserId();
=======
$action = $_GET['action'] ?? '';

// Check Doctor Auth
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'doctor') {
    http_response_code(403);
    sendResponse(['success' => false, 'message' => 'Unauthorized']);
}

$userId = $_SESSION['user_id'];
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e

switch ($action) {
    case 'getProfile':
        sendResponse(handleGetDoctorProfile($userId));
<<<<<<< HEAD
        
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
=======
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
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e

    case 'markAllRead':
        markAllNotificationsRead($userId);
        sendResponse(['success' => true, 'message' => 'All notifications marked as read']);
<<<<<<< HEAD
        
=======
        break;

>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    default:
        sendResponse(['success' => false, 'message' => 'Invalid action']);
}
