<?php
/**
<<<<<<< HEAD
 * Doctor Model - Database operations for doctor profiles
=======
 * Doctor Model
 * Handles all database operations for doctor_profiles and doctor_availability.
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
 */

require_once __DIR__ . '/../config/db.php';

<<<<<<< HEAD
=======
// ----- Create an empty doctor profile for a new user -----
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
function createDoctorProfile($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("INSERT INTO doctor_profiles (user_id) VALUES (:user_id)");
    return $stmt->execute([':user_id' => $userId]);
}

<<<<<<< HEAD
=======
// ----- Get full doctor profile (joins users + doctor_profiles) -----
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
function getDoctorProfile($userId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.created_at,
<<<<<<< HEAD
               dp.specialization, dp.qualification, dp.experience, dp.phone,
=======
               dp.specialization, dp.qualification, dp.nmc, dp.experience, dp.phone,
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
               dp.bio, dp.consultation_fee, dp.photo, dp.approval_status
        FROM users u
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        WHERE u.id = :user_id AND u.role = 'doctor'
    ");
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetch();
}

<<<<<<< HEAD
function updateDoctorProfile($userId, $data) {
    $pdo = getDBConnection();
    
    // Check if profile exists
    $stmt = $pdo->prepare("SELECT id FROM doctor_profiles WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $userId]);
    $exists = $stmt->fetch();
    
    if (!$exists) {
        createDoctorProfile($userId);
    }
    
    $fields = [];
    $params = [':user_id' => $userId];
    
    $allowed = ['specialization', 'qualification', 'experience', 'phone', 'bio', 'consultation_fee', 'photo'];
=======
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
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    foreach ($data as $key => $value) {
        if (in_array($key, $allowed)) {
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }
    }
<<<<<<< HEAD
    
    // Update name in users table if provided
=======

    // Also update the name in the users table if provided
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    if (isset($data['name'])) {
        $stmt2 = $pdo->prepare("UPDATE users SET name = :name WHERE id = :id");
        $stmt2->execute([':name' => $data['name'], ':id' => $userId]);
    }
<<<<<<< HEAD
    
    if (empty($fields)) return true;
    
=======

    if (empty($fields)) return true;

>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    $sql = "UPDATE doctor_profiles SET " . implode(', ', $fields) . " WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

<<<<<<< HEAD
=======
// ----- Get all doctors (optionally only approved ones) -----
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
function getAllDoctors($approvedOnly = true) {
    $pdo = getDBConnection();
    $approvalCondition = $approvedOnly ? "AND dp.approval_status = 'approved'" : "";
    $stmt = $pdo->prepare("
        SELECT u.id, u.name, u.email, u.status, u.created_at,
<<<<<<< HEAD
               dp.specialization, dp.qualification, dp.experience, dp.phone,
=======
               dp.specialization, dp.qualification, dp.nmc, dp.experience, dp.phone,
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
               dp.bio, dp.consultation_fee, dp.photo, dp.approval_status
        FROM users u
        LEFT JOIN doctor_profiles dp ON u.id = dp.user_id
        WHERE u.role = 'doctor' $approvalCondition
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    return $stmt->fetchAll();
}

<<<<<<< HEAD
function searchDoctors($specialization = null, $date = null) {
    $pdo = getDBConnection();
    $conditions = ["u.role = 'doctor'", "dp.approval_status = 'approved'", "u.status = 'active'"];
    $params = [];
    
=======
// ----- Search doctors by specialization -----
function searchDoctors($specialization = null) {
    $pdo = getDBConnection();
    $conditions = ["u.role = 'doctor'", "dp.approval_status = 'approved'", "u.status = 'active'"];
    $params = [];

>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    if ($specialization && $specialization !== 'all') {
        $conditions[] = "dp.specialization = :specialization";
        $params[':specialization'] = $specialization;
    }
<<<<<<< HEAD
    
    $sql = "
        SELECT u.id, u.name, u.email,
               dp.specialization, dp.qualification, dp.experience,
=======

    $sql = "
        SELECT u.id, u.name, u.email,
               dp.specialization, dp.qualification, dp.nmc, dp.experience,
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
               dp.consultation_fee, dp.photo, dp.bio
        FROM users u
        INNER JOIN doctor_profiles dp ON u.id = dp.user_id
        WHERE " . implode(' AND ', $conditions) . "
        ORDER BY dp.experience DESC
    ";
<<<<<<< HEAD
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $doctors = $stmt->fetchAll();
    
    // If date filter specified, include availability info
    if ($date) {
        $dayOfWeek = date('l', strtotime($date));
        foreach ($doctors as &$doctor) {
            $avStmt = $pdo->prepare("
                SELECT start_time, end_time FROM doctor_availability 
                WHERE doctor_id = :doctor_id AND day_of_week = :day AND is_available = 1
            ");
            $avStmt->execute([':doctor_id' => $doctor['id'], ':day' => $dayOfWeek]);
            $doctor['availability'] = $avStmt->fetchAll();
        }
        // Filter out doctors with no availability on that day
        $doctors = array_values(array_filter($doctors, function($d) {
            return !empty($d['availability']);
        }));
    }
    
    return $doctors;
}

=======

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $doctors = $stmt->fetchAll();

    return $doctors;
}

// ----- Update doctor approval status (pending / approved / rejected) -----
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
function updateDoctorApproval($userId, $status) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE doctor_profiles SET approval_status = :status WHERE user_id = :user_id");
    return $stmt->execute([':status' => $status, ':user_id' => $userId]);
}

<<<<<<< HEAD
=======
// ----- Get all availability records for a doctor -----
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
function getDoctorAvailability($doctorId) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM doctor_availability WHERE doctor_id = :doctor_id ORDER BY FIELD(day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday')");
    $stmt->execute([':doctor_id' => $doctorId]);
    return $stmt->fetchAll();
}

<<<<<<< HEAD
function setDoctorAvailability($doctorId, $schedules) {
    $pdo = getDBConnection();
    
    // Delete existing availability
    $stmt = $pdo->prepare("DELETE FROM doctor_availability WHERE doctor_id = :doctor_id");
    $stmt->execute([':doctor_id' => $doctorId]);
    
    // Insert new availability
    $stmt = $pdo->prepare("INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, is_available) VALUES (:doctor_id, :day, :start, :end, :available)");
    
=======
// ----- Replace all availability records for a doctor -----
function setDoctorAvailability($doctorId, $schedules) {
    $pdo = getDBConnection();

    // Remove old schedule
    $stmt = $pdo->prepare("DELETE FROM doctor_availability WHERE doctor_id = :doctor_id");
    $stmt->execute([':doctor_id' => $doctorId]);

    // Insert new schedule rows
    $stmt = $pdo->prepare("INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, is_available) VALUES (:doctor_id, :day, :start, :end, :available)");

>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    foreach ($schedules as $schedule) {
        $stmt->execute([
            ':doctor_id' => $doctorId,
            ':day' => $schedule['day'],
            ':start' => $schedule['start_time'],
            ':end' => $schedule['end_time'],
            ':available' => $schedule['is_available'] ?? 1
        ]);
    }
<<<<<<< HEAD
    
    return true;
}

=======

    return true;
}

// ----- Update the doctor's photo path -----
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
function updateDoctorPhoto($userId, $photoPath) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE doctor_profiles SET photo = :photo WHERE user_id = :user_id");
    return $stmt->execute([':photo' => $photoPath, ':user_id' => $userId]);
}

<<<<<<< HEAD
=======
// ----- Count doctors, optionally by approval status -----
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
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
