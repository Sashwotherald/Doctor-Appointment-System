<?php
/**
 * Admin Controller
 * Full system control: dashboard stats, user management, reports.
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Doctor.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Appointment.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/PatientController.php';

// ----- Get admin dashboard overview stats -----
function handleAdminDashboard()
{
    $stats = getAppointmentStats();

    return [
        'success' => true,
        'dashboard' => [
            'total_patients' => countUsers(ROLE_PATIENT),
            'total_doctors' => countUsers(ROLE_DOCTOR),
            'pending_doctors' => countDoctors(DOCTOR_PENDING),
            'total_appointments' => $stats['total'] ?? 0,
            'pending_appointments' => $stats['pending'] ?? 0,
            'today_appointments' => $stats['today'] ?? 0,
            'completed_appointments' => $stats['completed'] ?? 0,
            'cancelled_appointments' => $stats['cancelled'] ?? 0,
            'recent_appointments' => array_slice(getAllAppointments(), 0, 10)
        ]
    ];
}

// ----- Get all doctors (including non-approved) -----
function handleGetAllDoctors()
{
    $doctors = getAllDoctors(false);
    return ['success' => true, 'doctors' => $doctors];
}

// ----- Get all patients -----
function handleGetAllPatients()
{
    $patients = getAllPatients();
    return ['success' => true, 'patients' => $patients];
}

// ----- Get all appointments, optionally filtered by status -----
function handleGetAllAppointments($status = null)
{
    $appointments = getAllAppointments($status);
    return ['success' => true, 'appointments' => $appointments];
}

// ----- Approve a doctor registration -----
function handleApproveDoctor($doctorId)
{
    $result = updateDoctorApproval($doctorId, DOCTOR_APPROVED);
    if ($result) {
        // Send notification to the doctor
        createNotification($doctorId, 'Account Approved', 'Your doctor account has been approved. You can now login and start receiving appointments.', 'approval');
        return ['success' => true, 'message' => 'Doctor approved successfully'];
    }
    return ['success' => false, 'message' => 'Failed to approve doctor'];
}

// ----- Reject a doctor registration -----
function handleRejectDoctor($doctorId)
{
    $result = updateDoctorApproval($doctorId, DOCTOR_REJECTED);
    if ($result) {
        createNotification($doctorId, 'Account Rejected', 'Your doctor registration has been rejected. Please contact admin for details.', 'rejection');
        return ['success' => true, 'message' => 'Doctor rejected'];
    }
    return ['success' => false, 'message' => 'Failed to reject doctor'];
}

// ----- Delete a doctor account (removes photo file too) -----
function handleDeleteDoctor($doctorId)
{
    // Delete uploaded photo from disk if it exists
    $profile = getDoctorProfile($doctorId);
    if ($profile && $profile['photo']) {
        $photoPath = __DIR__ . '/../uploads/doctors/' . basename($profile['photo']);
        if (file_exists($photoPath)) {
            unlink($photoPath);
        }
    }

    $result = deleteUser($doctorId);
    if ($result) {
        return ['success' => true, 'message' => 'Doctor deleted successfully'];
    }
    return ['success' => false, 'message' => 'Failed to delete doctor'];
}

// ----- Delete a patient account -----
function handleDeletePatient($patientId)
{
    $result = deleteUser($patientId);
    if ($result) {
        return ['success' => true, 'message' => 'Patient deleted successfully'];
    }
    return ['success' => false, 'message' => 'Failed to delete patient'];
}

// ----- Admin updates an appointment status -----
function handleAdminUpdateAppointment($data)
{
    if (empty($data['appointment_id']) || empty($data['status'])) {
        return ['success' => false, 'message' => 'Appointment ID and status required'];
    }

    $result = updateAppointmentStatus($data['appointment_id'], $data['status'], $data['notes'] ?? null);
    if ($result) {
        return ['success' => true, 'message' => 'Appointment updated'];
    }
    return ['success' => false, 'message' => 'Failed to update appointment'];
}

// ----- Toggle a user between active and inactive -----
function handleToggleUserStatus($userId)
{
    $user = getUserById($userId);
    if (!$user)
        return ['success' => false, 'message' => 'User not found'];
    if ($user['role'] === ROLE_ADMIN)
        return ['success' => false, 'message' => 'Cannot modify admin status'];

    $newStatus = $user['status'] === 'active' ? 'inactive' : 'active';
    $result = updateUser($userId, ['status' => $newStatus]);
    if ($result) {
        return ['success' => true, 'message' => 'User status updated to ' . $newStatus];
    }
    return ['success' => false, 'message' => 'Failed to update status'];
}

// ----- Generate reports (monthly stats, top doctors, specialization counts) -----
function handleGetReports()
{
    $pdo = getDBConnection();

    // Monthly appointment stats (last 12 months)
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(appointment_date, '%Y-%m') as month,
            COUNT(*) as total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled
        FROM appointments 
        GROUP BY DATE_FORMAT(appointment_date, '%Y-%m')
        ORDER BY month DESC
        LIMIT 12
    ");
    $stmt->execute();
    $monthlyStats = $stmt->fetchAll();

    // Top doctors ranked by total appointments
    $stmt = $pdo->prepare("
        SELECT d.name as doctor_name, dp.specialization, COUNT(a.id) as total_appointments,
               SUM(CASE WHEN a.status = 'completed' THEN 1 ELSE 0 END) as completed_appointments
        FROM appointments a
        INNER JOIN users d ON a.doctor_id = d.id
        LEFT JOIN doctor_profiles dp ON d.id = dp.user_id
        GROUP BY a.doctor_id, d.name, dp.specialization
        ORDER BY total_appointments DESC
        LIMIT 10
    ");
    $stmt->execute();
    $topDoctors = $stmt->fetchAll();

    // Number of doctors per specialization
    $stmt = $pdo->prepare("
        SELECT dp.specialization, COUNT(*) as count
        FROM doctor_profiles dp
        WHERE dp.specialization IS NOT NULL
        GROUP BY dp.specialization
        ORDER BY count DESC
    ");
    $stmt->execute();
    $specializations = $stmt->fetchAll();

    return [
        'success' => true,
        'reports' => [
            'monthly_stats' => $monthlyStats,
            'top_doctors' => $topDoctors,
            'specializations' => $specializations,
            'overview' => getAppointmentStats()
        ]
    ];
}
