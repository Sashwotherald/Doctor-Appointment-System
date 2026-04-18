<?php
/**
 * Auth Controller - Handles login, register, and session management
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Doctor.php';
require_once __DIR__ . '/../config/constants.php';

function isPatientProfileIncomplete($profile) {
    if (!$profile) {
        return true;
    }

    $requiredFields = ['phone', 'age', 'gender', 'address'];
    foreach ($requiredFields as $field) {
        $value = isset($profile[$field]) ? $profile[$field] : null;
        if ($value === null || trim((string) $value) === '') {
            return true;
        }
    }

    return false;
}

function handleLogin($data) {
    if (empty($data['email']) || empty($data['password'])) {
        return ['success' => false, 'message' => 'Email and password are required'];
    }
    
    $user = getUserByEmail($data['email']);
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    if (!verifyPassword($data['password'], $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }
    
    if ($user['status'] === 'inactive') {
        return ['success' => false, 'message' => 'Your account has been deactivated. Contact admin.'];
    }
    
    // Check doctor approval if doctor
    if ($user['role'] === ROLE_DOCTOR) {
        $profile = getDoctorProfile($user['id']);
        if ($profile && $profile['approval_status'] === DOCTOR_PENDING) {
            return ['success' => false, 'message' => 'Your doctor account is pending admin approval'];
        }
        if ($profile && $profile['approval_status'] === DOCTOR_REJECTED) {
            return ['success' => false, 'message' => 'Your doctor registration has been rejected'];
        }
    }

    if ($user['role'] === ROLE_PATIENT) {
        $profile = getPatientProfile($user['id']);
        $user['needs_profile_completion'] = isPatientProfileIncomplete($profile);
    }
    
    // Remove password from response
    unset($user['password']);
    
    return [
        'success' => true,
        'message' => 'Login successful',
        'user' => $user
    ];
}

function handleRegister($data) {
    // Validation
    if (empty($data['name']) || empty($data['email']) || empty($data['password']) || empty($data['role'])) {
        return ['success' => false, 'message' => 'All fields are required'];
    }
    
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }
    
    if (strlen($data['password']) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters'];
    }
    
    if (!in_array($data['role'], [ROLE_PATIENT, ROLE_DOCTOR])) {
        return ['success' => false, 'message' => 'Invalid role selected'];
    }
    
    // Check if email already exists
    $existing = getUserByEmail($data['email']);
    if ($existing) {
        return ['success' => false, 'message' => 'Email already registered'];
    }
    
    try {
        $userId = createUser($data['name'], $data['email'], $data['password'], $data['role']);
        
        // Create role-specific profile
        if ($data['role'] === ROLE_PATIENT) {
            createPatientProfile($userId);
        } else if ($data['role'] === ROLE_DOCTOR) {
            createDoctorProfile($userId);
            // If specialization provided during registration
            if (!empty($data['specialization'])) {
                updateDoctorProfile($userId, ['specialization' => $data['specialization']]);
            }
        }
        
        return [
            'success' => true,
            'message' => $data['role'] === ROLE_DOCTOR 
                ? 'Registration successful! Your account is pending admin approval.' 
                : 'Registration successful! You can now login.',
            'user_id' => $userId
        ];
    } catch (Exception $e) {
        return ['success' => false, 'message' => 'Registration failed: ' . $e->getMessage()];
    }
}

function handleGetProfile($userId) {
    $user = getUserById($userId);
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    unset($user['password']);
    return ['success' => true, 'user' => $user];
}
