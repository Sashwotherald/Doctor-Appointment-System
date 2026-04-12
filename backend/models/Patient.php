<?php
/**
 * Patient Model - Database operations for patient profiles
 */

require_once __DIR__ . '/../config/db.php';

function createPatientProfile($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO patient_profiles (user_id) VALUES (:user_id)");
    return $stmt->execute([':user_id' => $userId]);
}

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

function updatePatientProfile($userId, $data) {
    $pdo = getDBConnection();
    
    // Check if profile exists
    $stmt = $pdo->prepare("SELECT id FROM patient_profiles WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $exists = $stmt->fetch();
    
    if (!$exists) {
        createPatientProfile($userId);
    }
    
    $fields = [];
    $params = [':user_id' => $userId];
    
    $allowed = ['phone', 'age', 'gender', 'blood_group', 'medical_history', 'address'];
    foreach ($data as $key => $value) {
        if (in_array($key, $allowed)) {
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }
    }
    
    // Update name in users table if provided
    if (isset($data['name'])) {
        $stmt2 = $pdo->prepare("UPDATE users SET name = :name WHERE id = :id");
        $stmt2->execute([':name' => $data['name'], ':id' => $userId]);
    }
    
    if (empty($fields)) return true;
    
    $sql = "UPDATE patient_profiles SET " . implode(', ', $fields) . " WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

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
