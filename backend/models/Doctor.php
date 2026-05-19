<?php
/**
 * Doctor Model
 * Handles all database operations for doctor_profiles and doctor_availability.
 */

require_once __DIR__ . '/../config/db.php';

// ----- Create an empty doctor profile for a new user -----
function createDoctorProfile($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO doctor_profiles (user_id) VALUES (:user_id)");
    return $stmt->execute([':user_id' => $userId]);
}

// ----- Get full doctor profile (joins users + doctor_profiles) -----
function getDoctorProfile($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.created_at,
               dp.specialization, dp.qualification, dp.nmc, dp.experience, dp.phone,
               dp.bio, dp.consultation_fee, dp.photo, dp.approval_status
        FROM users u
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        WHERE u.id = :user_id AND u.role = 'doctor'
    ");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetch();
}

// ----- Update doctor profile fields (creates profile if missing) -----
function updateDoctorProfile($userId, $data) {
    $pdo = getDBConnection();

    // Auto-create profile row if it doesn't exist yet
    $stmt = $pdo->prepare("SELECT id FROM doctor_profiles WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    if (!$stmt->fetch()) {
        createDoctorProfile($userId);
    }

    $fields = [];
    $params = [':user_id' => $userId];

    // Only allow whitelisted columns
    $allowed = ['specialization', 'qualification', 'nmc', 'experience', 'phone', 'bio', 'consultation_fee', 'photo'];
    foreach ($data as $key => $value) {
        if (in_array($key, $allowed)) {
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }
    }

    // Also update the name in the users table if provided
    if (isset($data['name'])) {
        $stmt2 = $pdo->prepare("UPDATE users SET name = :name WHERE id = :id");
        $stmt2->execute([':name' => $data['name'], ':id' => $userId]);
    }

    if (empty($fields)) return true;

    $sql = "UPDATE doctor_profiles SET " . implode(', ', $fields) . " WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

// ----- Get all doctors (optionally only approved ones) -----
function getAllDoctors($approvedOnly = true) {
    $pdo = getDBConnection();
    $approvalCondition = $approvedOnly ? "AND dp.approval_status = 'approved'" : "";
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.status, u.created_at,
               dp.specialization, dp.qualification, dp.nmc, dp.experience, dp.phone,
               dp.bio, dp.consultation_fee, dp.photo, dp.approval_status
        FROM users u
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        WHERE u.role = 'doctor' $approvalCondition
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

// ----- Search doctors by specialization -----
function searchDoctors($specialization = null) {
    $pdo = getDBConnection();
    $conditions = ["u.role = 'doctor'", "dp.approval_status = 'approved'", "u.status = 'active'"];
    $params = [];

    if ($specialization && $specialization !== 'all') {
        $conditions[] = "dp.specialization = :specialization";
        $params[':specialization'] = $specialization;
    }

    $sql = "
        SELECT u.id, u.name, u.email,
               dp.specialization, dp.qualification, dp.nmc, dp.experience,
               dp.consultation_fee, dp.photo, dp.bio
        FROM users u
        INNER JOIN doctor_profiles dp ON u.id = dp.user_id
        WHERE " . implode(' AND ', $conditions) . "
        ORDER BY dp.experience DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $doctors = $stmt->fetchAll();

    return $doctors;
}

// ----- Update doctor approval status (pending / approved / rejected) -----
function updateDoctorApproval($userId, $status) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE doctor_profiles SET approval_status = :status WHERE user_id = :user_id");
    return $stmt->execute([':status' => $status, ':user_id' => $userId]);
}

// ----- Get all availability records for a doctor -----
function getDoctorAvailability($doctorId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM doctor_availability WHERE doctor_id = :doctor_id ORDER BY FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')");
    $stmt->execute([':doctor_id' => $doctorId]);
    return $stmt->fetchAll();
}

// ----- Replace all availability records for a doctor -----
function setDoctorAvailability($doctorId, $schedules) {
    $pdo = getDBConnection();

    // Remove old schedule
    $stmt = $pdo->prepare("DELETE FROM doctor_availability WHERE doctor_id = :doctor_id");
    $stmt->execute([':doctor_id' => $doctorId]);

    // Insert new schedule rows
    $stmt = $pdo->prepare("INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, is_available) VALUES (:doctor_id, :day, :start, :end, :available)");

    foreach ($schedules as $schedule) {
        $stmt->execute([
            ':doctor_id' => $doctorId,
            ':day' => $schedule['day'],
            ':start' => $schedule['start_time'],
            ':end' => $schedule['end_time'],
            ':available' => $schedule['is_available'] ?? 1
        ]);
    }

    return true;
}

// ----- Update the doctor's photo path -----
function updateDoctorPhoto($userId, $photoPath) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE doctor_profiles SET photo = :photo WHERE user_id = :user_id");
    return $stmt->execute([':photo' => $photoPath, ':user_id' => $userId]);
}

// ----- Count doctors, optionally by approval status -----
function countDoctors($status = null) {
    $pdo = getDBConnection();
    if ($status) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM doctor_profiles WHERE approval_status = :status");
        $stmt->execute([':status' => $status]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'doctor'");
        $stmt->execute();
    }
    $result = $stmt->fetch();
    return $result['total'];
}
