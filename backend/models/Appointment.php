<?php
/**
 * Appointment Model - Database operations for appointments
 */

require_once __DIR__ . '/../config/db.php';

function createAppointment($patientId, $doctorId, $date, $time, $reason = '') {
    $pdo = getDBConnection();
    
    // Check for time conflicts
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
    
    return ['success' => true, 'id' => $pdo->lastInsertId()];
}

function getAppointmentById($id) {
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

function getPatientAppointments($patientId, $status = null) {
    $pdo = getDBConnection();
    $statusCondition = $status ? "AND a.status = :status" : "";
    $params = [':patient_id' => $patientId];
    if ($status) $params[':status'] = $status;
    
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

function getDoctorAppointments($doctorId, $status = null) {
    $pdo = getDBConnection();
    $statusCondition = $status ? "AND a.status = :status" : "";
    $params = [':doctor_id' => $doctorId];
    if ($status) $params[':status'] = $status;
    
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

function getAllAppointments($status = null) {
    $pdo = getDBConnection();
    $statusCondition = $status ? "WHERE a.status = :status" : "";
    $params = $status ? [':status' => $status] : [];
    
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

function updateAppointmentStatus($id, $status, $notes = null) {
    $pdo = getDBConnection();
    if ($notes) {
        $stmt = $pdo->prepare("UPDATE appointments SET status = :status, notes = :notes WHERE id = :id");
        return $stmt->execute([':status' => $status, ':notes' => $notes, ':id' => $id]);
    } else {
        $stmt = $pdo->prepare("UPDATE appointments SET status = :status WHERE id = :id");
        return $stmt->execute([':status' => $status, ':id' => $id]);
    }
}

function rescheduleAppointment($id, $newDate, $newTime) {
    $pdo = getDBConnection();
    
    // Get the appointment to check doctor availability
    $apt = getAppointmentById($id);
    if (!$apt) return ['success' => false, 'message' => 'Appointment not found'];
    
    // Check for conflicts
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
    
    $stmt = $pdo->prepare("
        UPDATE appointments 
        SET appointment_date = :date, appointment_time = :time, status = 'rescheduled' 
        WHERE id = :id
    ");
    $stmt->execute([':date' => $newDate, ':time' => $newTime, ':id' => $id]);
    
    return ['success' => true];
}

function cancelAppointment($id) {
    return updateAppointmentStatus($id, 'cancelled');
}

function countAppointments($status = null) {
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

function getTodayAppointments($doctorId = null) {
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

function getAppointmentStats() {
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
