<?php
/**
 * Patient Controller
 * Handles doctor search, booking, cancellation, reschedule, profile, notifications.
 */

require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Doctor.php';
require_once __DIR__ . '/../models/Appointment.php';
require_once __DIR__ . '/../config/constants.php';

// ----- Search doctors by specialization -----
function handleGetDoctors($filters = []) {
    $specialization = $filters['specialization'] ?? null;

    $doctors = searchDoctors($specialization);

    return ['success' => true, 'doctors' => $doctors];
}

// ----- Book a new appointment -----
function handleBookAppointment($patientId, $data) {
    // Validate required fields
    if (empty($data['doctor_id']) || empty($data['date']) || empty($data['time'])) {
        return ['success' => false, 'message' => 'Doctor, date, and time are required'];
    }

    // Validate the date
    $appointmentDate = strtotime($data['date']);
    if (!$appointmentDate) {
        return ['success' => false, 'message' => 'Invalid appointment date'];
    }
    if (date('Y-m-d', $appointmentDate) < date('Y-m-d')) {
        return ['success' => false, 'message' => 'Cannot book appointments in the past'];
    }

    // Create the appointment
    $result = createAppointment(
        $patientId,
        $data['doctor_id'],
        $data['date'],
        $data['time'],
        $data['reason'] ?? ''
    );

    // Notify the doctor if booking was successful
    if ($result['success']) {
        createNotification(
            $data['doctor_id'],
            'New Appointment Request',
            'A patient has requested an appointment on ' . $data['date'] . ' at ' . $data['time'],
            'appointment'
        );
    }

    return $result;
}

// ----- Cancel an existing appointment -----
function handleCancelAppointment($patientId, $appointmentId) {
    $appointment = getAppointmentById($appointmentId);
    if (!$appointment) {
        return ['success' => false, 'message' => 'Appointment not found'];
    }
    if ($appointment['patient_id'] != $patientId) {
        return ['success' => false, 'message' => 'Unauthorized'];
    }
    if (in_array($appointment['status'], [STATUS_COMPLETED, STATUS_CANCELLED], true)) {
        return ['success' => false, 'message' => 'Cannot cancel this appointment'];
    }

    cancelAppointment($appointmentId);

    // Notify the doctor
    createNotification(
        $appointment['doctor_id'],
        'Appointment Cancelled',
        'Patient ' . $appointment['patient_name'] . ' has cancelled their appointment on ' . $appointment['appointment_date'],
        'cancellation'
    );

    return ['success' => true, 'message' => 'Appointment cancelled successfully'];
}

// ----- Reschedule an appointment to a new date/time -----
function handleRescheduleAppointment($patientId, $data) {
    if (empty($data['appointment_id']) || empty($data['new_date']) || empty($data['new_time'])) {
        return ['success' => false, 'message' => 'Appointment ID, new date, and new time are required'];
    }

    // Verify ownership
    $appointment = getAppointmentById($data['appointment_id']);
    if (!$appointment) {
        return ['success' => false, 'message' => 'Appointment not found'];
    }
    if ($appointment['patient_id'] != $patientId) {
        return ['success' => false, 'message' => 'Unauthorized'];
    }

    $result = rescheduleAppointment($data['appointment_id'], $data['new_date'], $data['new_time']);

    // Notify the doctor if rescheduling was successful
    if ($result['success']) {
        createNotification(
            $appointment['doctor_id'],
            'Appointment Rescheduled',
            'Patient ' . $appointment['patient_name'] . ' has rescheduled to ' . $data['new_date'] . ' at ' . $data['new_time'],
            'reschedule'
        );
    }

    return $result;
}

// ----- Get patient's appointments (optionally filtered) -----
function handleGetPatientAppointments($patientId, $status = null) {
    $appointments = getPatientAppointments($patientId, $status);
    return ['success' => true, 'appointments' => $appointments];
}

// ----- Update patient profile fields -----
function handleUpdatePatientProfile($userId, $data) {
    $result = updatePatientProfile($userId, $data);
    if ($result) {
        return ['success' => true, 'message' => 'Profile updated successfully'];
    }
    return ['success' => false, 'message' => 'Failed to update profile'];
}

// ----- Get patient profile -----
function handleGetPatientProfile($userId) {
    $profile = getPatientProfile($userId);
    if ($profile) {
        return ['success' => true, 'profile' => $profile];
    }
    return ['success' => false, 'message' => 'Profile not found'];
}

// ----- Patient dashboard: upcoming, pending, total counts -----
function handleGetPatientDashboard($patientId) {
    $upcoming = getPatientAppointments($patientId, STATUS_APPROVED);
    $pending = getPatientAppointments($patientId, STATUS_PENDING);
    $totalAppointments = count(getPatientAppointments($patientId));
    $notifications = getUnreadNotifications($patientId);

    return [
        'success' => true,
        'dashboard' => [
            'upcoming_appointments' => array_slice($upcoming, 0, 5),
            'pending_appointments' => array_slice($pending, 0, 5),
            'total_appointments' => $totalAppointments,
            'unread_notifications' => count($notifications),
            'notifications' => array_slice($notifications, 0, 5)
        ]
    ];
}

// =====================================================
// Notification Helper Functions
// =====================================================

// ----- Create a new notification for a user -----
function createNotification($userId, $title, $message, $type = 'info') {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (:user_id, :title, :message, :type)");
    return $stmt->execute([':user_id' => $userId, ':title' => $title, ':message' => $message, ':type' => $type]);
}

// ----- Get all unread notifications for a user -----
function getUnreadNotifications($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :user_id AND is_read = 0 ORDER BY created_at DESC");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}

// ----- Get all notifications (read + unread, latest 50) -----
function getAllNotifications($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 50");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetchAll();
}

// ----- Mark a single notification as read -----
function markNotificationRead($notificationId, $userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
    return $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);
}

// ----- Mark all notifications as read for a user -----
function markAllNotificationsRead($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id");
    return $stmt->execute([':user_id' => $userId]);
}
