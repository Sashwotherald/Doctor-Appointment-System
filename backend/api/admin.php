<?php
/**
 * Admin API Endpoint
 * Routes for dashboard, users, doctors, patients, appointments, reports
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure the user is authenticated and has the admin role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../controllers/AdminController.php';

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

switch ($action) {
    case 'getDashboard':
        sendResponse(handleAdminDashboard());
        break;

    case 'getDoctors':
        sendResponse(handleGetAllDoctors());
        break;

    case 'getPatients':
        sendResponse(handleGetAllPatients());
        break;

    case 'getAppointments':
        $status = $_GET['status'] ?? null;
        sendResponse(handleGetAllAppointments($status));
        break;

    case 'approveDoctor':
        $data = getJsonBody();
        sendResponse(handleApproveDoctor(getRequiredField($data, 'doctor_id', 'Doctor ID is required')));
        break;

    case 'rejectDoctor':
        $data = getJsonBody();
        sendResponse(handleRejectDoctor(getRequiredField($data, 'doctor_id', 'Doctor ID is required')));
        break;

    case 'deleteDoctor':
        $data = getJsonBody();
        sendResponse(handleDeleteDoctor(getRequiredField($data, 'doctor_id', 'Doctor ID is required')));
        break;

    case 'deletePatient':
        $data = getJsonBody();
        sendResponse(handleDeletePatient(getRequiredField($data, 'patient_id', 'Patient ID is required')));
        break;

    case 'toggleUserStatus':
        $data = getJsonBody();
        sendResponse(handleToggleUserStatus(getRequiredField($data, 'user_id', 'User ID is required')));
        break;

    case 'updateAppointment':
        sendResponse(handleAdminUpdateAppointment(getJsonBody()));
        break;

    case 'getReports':
        sendResponse(handleGetReports());
        break;



    default:
        sendResponse(['success' => false, 'message' => 'Invalid action']);
}
