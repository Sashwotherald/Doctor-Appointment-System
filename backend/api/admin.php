<?php
/**
 * Admin API Endpoint
 * Handles: dashboard, users, doctors, patients, appointments, reports, settings
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../controllers/AdminController.php';

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

switch ($action) {
    case 'getDashboard':
        sendResponse(handleAdminDashboard());
        
    case 'getDoctors':
        sendResponse(handleGetAllDoctors());
        
    case 'getPatients':
        sendResponse(handleGetAllPatients());
        
    case 'getAppointments':
        $status = $_GET['status'] ?? null;
        sendResponse(handleGetAllAppointments($status));
        
    case 'approveDoctor':
        $data = getJsonBody();
        sendResponse(handleApproveDoctor(getRequiredField($data, 'doctor_id', 'Doctor ID is required')));
        
    case 'rejectDoctor':
        $data = getJsonBody();
        sendResponse(handleRejectDoctor(getRequiredField($data, 'doctor_id', 'Doctor ID is required')));
        
    case 'deleteDoctor':
        $data = getJsonBody();
        sendResponse(handleDeleteDoctor(getRequiredField($data, 'doctor_id', 'Doctor ID is required')));
        
    case 'deletePatient':
        $data = getJsonBody();
        sendResponse(handleDeletePatient(getRequiredField($data, 'patient_id', 'Patient ID is required')));
        
    case 'toggleUserStatus':
        $data = getJsonBody();
        sendResponse(handleToggleUserStatus(getRequiredField($data, 'user_id', 'User ID is required')));
        
    case 'updateAppointment':
        sendResponse(handleAdminUpdateAppointment(getJsonBody()));
        
    case 'getReports':
        sendResponse(handleGetReports());
        
    case 'getSettings':
        sendResponse(handleGetSettings());
        
    case 'updateSettings':
        sendResponse(handleUpdateSettings(getJsonBody()));
        
    default:
        sendResponse(['success' => false, 'message' => 'Invalid action']);
}
