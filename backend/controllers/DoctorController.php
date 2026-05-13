<?php
/**
 * Doctor Controller - Handles doctor-related operations
 */

require_once __DIR__ . '/../models/Doctor.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Appointment.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/PatientController.php';

function handleGetDoctorProfile($userId) {
    $profile = getDoctorProfile($userId);
    if ($profile) {
        $availability = getDoctorAvailability($userId);
        $profile['availability'] = $availability;
        return ['success' => true, 'profile' => $profile];
    }
    return ['success' => false, 'message' => 'Profile not found'];
}

function handleUpdateDoctorProfile($userId, $data) {
    $result = updateDoctorProfile($userId, $data);
    if ($result) {
        return ['success' => true, 'message' => 'Profile updated successfully'];
    }
    return ['success' => false, 'message' => 'Failed to update profile'];
}

function handleUploadDoctorPhoto($userId, $file) {
    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!isset($file['tmp_name']) || empty($file['tmp_name'])) {
        return ['success' => false, 'message' => 'No file uploaded'];
    }
    
    if (!in_array($file['type'], $allowedTypes)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP'];
    }
    
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'File too large. Max 5MB allowed'];
    }
    
    // Create upload directory if not exists
    $uploadDir = UPLOAD_DIR;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'doctor_' . $userId . '_' . time() . '.' . $extension;
    $targetPath = $uploadDir . $filename;
    
    // Delete old photo if exists
    $profile = getDoctorProfile($userId);
    if ($profile && $profile['photo']) {
        $oldPath = $uploadDir . basename($profile['photo']);
        if (file_exists($oldPath)) {
            unlink($oldPath);
        }
    }
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        $photoUrl = UPLOAD_URL . $filename;
        updateDoctorPhoto($userId, $photoUrl);
        return ['success' => true, 'message' => 'Photo uploaded successfully', 'photo' => $photoUrl];
    }
    
    return ['success' => false, 'message' => 'Failed to upload photo'];
}

function handleSetAvailability($doctorId, $schedules) {
    if (empty($schedules)) {
        return ['success' => false, 'message' => 'No schedule data provided'];
    }
    
    $result = setDoctorAvailability($doctorId, $schedules);
    if ($result) {
        return ['success' => true, 'message' => 'Availability updated successfully'];
    }
    return ['success' => false, 'message' => 'Failed to update availability'];
}

function handleGetDoctorAppointments($doctorId, $status = null) {
    $appointments = getDoctorAppointments($doctorId, $status);
    return ['success' => true, 'appointments' => $appointments];
}

function handleUpdateAppointmentStatus($doctorId, $data) {
    if (empty($data['appointment_id']) || empty($data['status'])) {
        return ['success' => false, 'message' => 'Appointment ID and status are required'];
    }
    
    $appointment = getAppointmentById($data['appointment_id']);
    if (!$appointment) {
        return ['success' => false, 'message' => 'Appointment not found'];
    }
    if ($appointment['doctor_id'] != $doctorId) {
        return ['success' => false, 'message' => 'Unauthorized'];
    }
    
    $validStatuses = [STATUS_APPROVED, STATUS_REJECTED, STATUS_COMPLETED];
    if (!in_array($data['status'], $validStatuses)) {
        return ['success' => false, 'message' => 'Invalid status'];
    }
    
    updateAppointmentStatus($data['appointment_id'], $data['status'], $data['notes'] ?? null);
    
    // Notify patient
    $statusText = ucfirst($data['status']);
    createNotification(
        $appointment['patient_id'],
        "Appointment $statusText",
        "Your appointment with Dr. " . $appointment['doctor_name'] . " on " . $appointment['appointment_date'] . " has been $statusText.",
        'appointment'
    );
    
    return ['success' => true, 'message' => "Appointment $statusText successfully"];
}

function handleGetDoctorPatients($doctorId) {
    $patients = getPatientsByDoctor($doctorId);
    return ['success' => true, 'patients' => $patients];
}

function handleGetDoctorDashboard($doctorId) {
    $todayAppointments = getTodayAppointments($doctorId);
    $pendingAppointments = getDoctorAppointments($doctorId, STATUS_PENDING);
    $allAppointments = getDoctorAppointments($doctorId);
    $patients = getPatientsByDoctor($doctorId);
    $notifications = getUnreadNotifications($doctorId);
    
    return [
        'success' => true,
        'dashboard' => [
            'today_appointments' => $todayAppointments,
            'pending_count' => count($pendingAppointments),
            'total_appointments' => count($allAppointments),
            'total_patients' => count($patients),
            'completed' => count(array_filter($allAppointments, fn($a) => $a['status'] === STATUS_COMPLETED)),
            'unread_notifications' => count($notifications),
            'notifications' => array_slice($notifications, 0, 5)
        ]
    ];
}
