<?php
/**
 * Patient Model
 * Handles all database operations for patient_profiles.
 */

require_once __DIR__ . '/../config/db.php';

// ----- Create an empty patient profile for a new user -----
function createPatientProfile($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO patient_profiles (user_id) VALUES (:user_id)");
    return $stmt->execute([':user_id' => $userId]);
}

// ----- Get full patient profile (joins users + patient_profiles) -----
function getPatientProfile($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.created_at,
               pp.phone, pp.age, pp.gender, pp.blood_group, pp.medical_history, pp.address
        FROM users u
        LEFT JOIN patient_profiles pp ON u.id = pp.user_id
        WHERE u.id = :user_id AND u.role = 'patient'
    ");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetch();
}

// ----- Update patient profile fields (creates profile if missing) -----
function updatePatientProfile($userId, $data) {
    $pdo = getDBConnection();

    // Auto-create profile row if it doesn't exist yet
    $stmt = $pdo->prepare("SELECT id FROM patient_profiles WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    if (!$stmt->fetch()) {
        createPatientProfile($userId);
    }

    $fields = [];
    $params = [':user_id' => $userId];

    // Only allow whitelisted columns
    $allowed = ['phone', 'age', 'gender', 'blood_group', 'medical_history', 'address'];
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

    $sql = "UPDATE patient_profiles SET " . implode(', ', $fields) . " WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

// ----- Get all patients with their profile info -----
function getAllPatients() {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.status, u.created_at,
               pp.phone, pp.age, pp.gender, pp.blood_group, pp.medical_history
        FROM users u
        LEFT JOIN patient_profiles pp ON u.id = pp.user_id
        WHERE u.role = 'patient'
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

// ----- Get all patients who have booked with a specific doctor -----
function getPatientsByDoctor($doctorId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.name, u.email, u.created_at,
               pp.phone, pp.age, pp.gender, pp.blood_group, pp.medical_history
        FROM users u
        LEFT JOIN patient_profiles pp ON u.id = pp.user_id
        INNER JOIN appointments a ON u.id = a.patient_id
        WHERE a.doctor_id = :doctor_id AND u.role = 'patient'
        ORDER BY a.created_at DESC
    ");
    $stmt->execute([':doctor_id' => $doctorId]);
    return $stmt->fetchAll();
}
