<?php
/**
<<<<<<< HEAD
 * Appointment Model - Database operations for appointments
=======
 * Appointment Model
 * Handles all database operations for the appointments table.
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
 */

require_once __DIR__ . '/../config/db.php';

<<<<<<< HEAD
function createAppointment($patientId, $doctorId, $date, $time, $reason = '') {
    $pdo = getDBConnection();
    
    // Check for time conflicts
=======
// ----- Create a new appointment (checks for time conflicts) -----
function createAppointment($patientId, $doctorId, $date, $time, $reason = '')
{
    $pdo = getDBConnection();

    // Check if the doctor is available at the requested date and time
    $dayOfWeek = date('l', strtotime($date));
    $stmtAvail = $pdo->prepare("
        SELECT id FROM doctor_availability
        WHERE doctor_id = :doctor_id
        AND day_of_week = :day_of_week
        AND is_available = 1
        AND :time1 >= start_time
        AND :time2 < end_time
    ");
    $stmtAvail->execute([
        ':doctor_id' => $doctorId,
        ':day_of_week' => $dayOfWeek,
        ':time1'      => $time,
        ':time2'      => $time
    ]);
    if (!$stmtAvail->fetch()) {
        return ['success' => false, 'message' => 'Doctor is not available at this time'];
    }

    // Check if the same doctor already has a booking at this date/time
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    $stmt = $pdo->prepare("
        SELECT id FROM appointments 
        WHERE doctor_id = :doctor_id 
        AND appointment_date = :date 
        AND appointment_time = :time 
        AND status NOT IN ('cancelled', 'rejected')
    ");
    $stmt->execute([':doctor_id' => $doctorId, ':date' => $date, ':time' => $time]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'This time slot is already booked'];
    }
<<<<<<< HEAD
    
=======

    // Insert the new appointment
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    $stmt = $pdo->prepare("
        INSERT INTO appointments (patient_id, doctor_id, appointment_date, appointment_time, reason) 
        VALUES (:patient_id, :doctor_id, :date, :time, :reason)
    ");
    $stmt->execute([
        ':patient_id' => $patientId,
        ':doctor_id' => $doctorId,
        ':date' => $date,
        ':time' => $time,
        ':reason' => $reason
    ]);
<<<<<<< HEAD
    
    return ['success' => true, 'id' => $pdo->lastInsertId()];
}

function getAppointmentById($id) {
=======

    return ['success' => true, 'id' => $pdo->lastInsertId()];
}

// ----- Get a single appointment with full patient & doctor details -----
function getAppointmentById($id)
{
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT a.*, 
               p.name as patient_name, p.email as patient_email,
               d.name as doctor_name, d.email as doctor_email,
               dp.specialization, dp.photo as doctor_photo,
               pp.phone as patient_phone, pp.age as patient_age, pp.gender as patient_gender
        FROM appointments a
        INNER JOIN users p ON a.patient_id = p.id
        INNER JOIN users d ON a.doctor_id = d.id
        LEFT JOIN doctor_profiles dp ON d.id = dp.user_id
        LEFT JOIN patient_profiles pp ON p.id = pp.user_id
        WHERE a.id = :id
    ");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

<<<<<<< HEAD
function getPatientAppointments($patientId, $status = null) {
    $pdo = getDBConnection();
    $statusCondition = $status ? "AND a.status = :status" : "";
    $params = [':patient_id' => $patientId];
    if ($status) $params[':status'] = $status;
    
=======
// ----- Get all appointments for a patient (optionally filtered by status) -----
function getPatientAppointments($patientId, $status = null)
{
    $pdo = getDBConnection();
    $statusCondition = $status ? "AND a.status = :status" : "";
    $params = [':patient_id' => $patientId];
    if ($status)
        $params[':status'] = $status;

>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    $stmt = $pdo->prepare("
        SELECT a.*, 
               d.name as doctor_name, d.email as doctor_email,
               dp.specialization, dp.photo as doctor_photo, dp.consultation_fee
        FROM appointments a
        INNER JOIN users d ON a.doctor_id = d.id
        LEFT JOIN doctor_profiles dp ON d.id = dp.user_id
        WHERE a.patient_id = :patient_id $statusCondition
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

<<<<<<< HEAD
function getDoctorAppointments($doctorId, $status = null) {
    $pdo = getDBConnection();
    $statusCondition = $status ? "AND a.status = :status" : "";
    $params = [':doctor_id' => $doctorId];
    if ($status) $params[':status'] = $status;
    
=======
// ----- Get all appointments for a doctor (optionally filtered by status) -----
function getDoctorAppointments($doctorId, $status = null)
{
    $pdo = getDBConnection();
    $statusCondition = $status ? "AND a.status = :status" : "";
    $params = [':doctor_id' => $doctorId];
    if ($status)
        $params[':status'] = $status;

>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    $stmt = $pdo->prepare("
        SELECT a.*, 
               p.name as patient_name, p.email as patient_email,
               pp.phone as patient_phone, pp.age as patient_age, 
               pp.gender as patient_gender, pp.blood_group as patient_blood_group,
               pp.medical_history as patient_medical_history
        FROM appointments a
        INNER JOIN users p ON a.patient_id = p.id
        LEFT JOIN patient_profiles pp ON p.id = pp.user_id
        WHERE a.doctor_id = :doctor_id $statusCondition
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

<<<<<<< HEAD
function getAllAppointments($status = null) {
    $pdo = getDBConnection();
    $statusCondition = $status ? "WHERE a.status = :status" : "";
    $params = $status ? [':status' => $status] : [];
    
=======
// ----- Get all appointments system-wide (for admin) -----
function getAllAppointments($status = null)
{
    $pdo = getDBConnection();
    $statusCondition = $status ? "WHERE a.status = :status" : "";
    $params = $status ? [':status' => $status] : [];

>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    $stmt = $pdo->prepare("
        SELECT a.*, 
               p.name as patient_name, p.email as patient_email,
               d.name as doctor_name, d.email as doctor_email,
               dp.specialization, dp.photo as doctor_photo
        FROM appointments a
        INNER JOIN users p ON a.patient_id = p.id
        INNER JOIN users d ON a.doctor_id = d.id
        LEFT JOIN doctor_profiles dp ON d.id = dp.user_id
        $statusCondition
        ORDER BY a.appointment_date DESC, a.appointment_time DESC
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

<<<<<<< HEAD
function updateAppointmentStatus($id, $status, $notes = null) {
=======
// ----- Update an appointment's status (and optional notes) -----
function updateAppointmentStatus($id, $status, $notes = null)
{
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    $pdo = getDBConnection();
    if ($notes) {
        $stmt = $pdo->prepare("UPDATE appointments SET status = :status, notes = :notes WHERE id = :id");
        return $stmt->execute([':status' => $status, ':notes' => $notes, ':id' => $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE appointments SET status = :status WHERE id = :id");
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }
}

<<<<<<< HEAD
function rescheduleAppointment($id, $newDate, $newTime) {
    $pdo = getDBConnection();
    
    // Get the appointment to check doctor availability
    $apt = getAppointmentById($id);
    if (!$apt) return ['success' => false, 'message' => 'Appointment not found'];
    
    // Check for conflicts
=======
// ----- Reschedule an appointment (checks for time conflicts) -----
function rescheduleAppointment($id, $newDate, $newTime)
{
    $pdo = getDBConnection();

    // Get current appointment details
    $apt = getAppointmentById($id);
    if (!$apt)
        return ['success' => false, 'message' => 'Appointment not found'];

    // Check if the doctor is available at the requested date and time
    $dayOfWeek = date('l', strtotime($newDate));
    $stmtAvail = $pdo->prepare("
        SELECT id FROM doctor_availability
        WHERE doctor_id = :doctor_id
        AND day_of_week = :day_of_week
        AND is_available = 1
        AND :time1 >= start_time
        AND :time2 < end_time
    ");
    $stmtAvail->execute([
        ':doctor_id' => $apt['doctor_id'],
        ':day_of_week' => $dayOfWeek,
        ':time1'       => $newTime,
        ':time2'       => $newTime
    ]);
    if (!$stmtAvail->fetch()) {
        return ['success' => false, 'message' => 'Doctor is not available at this time'];
    }

    // Check if the new time slot is already taken
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    $stmt = $pdo->prepare("
        SELECT id FROM appointments 
        WHERE doctor_id = :doctor_id 
        AND appointment_date = :date 
        AND appointment_time = :time 
        AND id != :id
        AND status NOT IN ('cancelled', 'rejected')
    ");
    $stmt->execute([':doctor_id' => $apt['doctor_id'], ':date' => $newDate, ':time' => $newTime, ':id' => $id]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'This new time slot is already booked'];
    }
<<<<<<< HEAD
    
=======

    // Update the appointment
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    $stmt = $pdo->prepare("
        UPDATE appointments 
        SET appointment_date = :date, appointment_time = :time, status = 'rescheduled' 
        WHERE id = :id
    ");
    $stmt->execute([':date' => $newDate, ':time' => $newTime, ':id' => $id]);
<<<<<<< HEAD
    
    return ['success' => true];
}

function cancelAppointment($id) {
    return updateAppointmentStatus($id, 'cancelled');
}

function countAppointments($status = null) {
=======

    return ['success' => true];
}

// ----- Cancel an appointment -----
function cancelAppointment($id)
{
    return updateAppointmentStatus($id, 'cancelled');
}

// ----- Count appointments, optionally filtered by status -----
function countAppointments($status = null)
{
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    $pdo = getDBConnection();
    if ($status) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments WHERE status = :status");
        $stmt->execute([':status' => $status]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM appointments");
        $stmt->execute();
    }
    $result = $stmt->fetch();
    return $result['total'];
}

<<<<<<< HEAD
function getTodayAppointments($doctorId = null) {
=======
// ----- Get today's appointments (for a specific doctor or all) -----
function getTodayAppointments($doctorId = null)
{
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    $pdo = getDBConnection();
    $today = date('Y-m-d');
    if ($doctorId) {
        $stmt = $pdo->prepare("
            SELECT a.*, p.name as patient_name, pp.phone as patient_phone
            FROM appointments a
            INNER JOIN users p ON a.patient_id = p.id
            LEFT JOIN patient_profiles pp ON p.id = pp.user_id
            WHERE a.doctor_id = :doctor_id AND a.appointment_date = :today
            AND a.status NOT IN ('cancelled', 'rejected')
            ORDER BY a.appointment_time ASC
        ");
        $stmt->execute([':doctor_id' => $doctorId, ':today' => $today]);
    } else {
        $stmt = $pdo->prepare("
            SELECT a.*, p.name as patient_name, d.name as doctor_name
            FROM appointments a
            INNER JOIN users p ON a.patient_id = p.id
            INNER JOIN users d ON a.doctor_id = d.id
            WHERE a.appointment_date = :today
            AND a.status NOT IN ('cancelled', 'rejected')
            ORDER BY a.appointment_time ASC
        ");
        $stmt->execute([':today' => $today]);
    }
    return $stmt->fetchAll();
}

<<<<<<< HEAD
function getAppointmentStats() {
=======
// ----- Get aggregate appointment statistics -----
function getAppointmentStats()
{
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
            SUM(CASE WHEN appointment_date = CURDATE() THEN 1 ELSE 0 END) as today
        FROM appointments
    ");
    $stmt->execute();
    return $stmt->fetch();
}
