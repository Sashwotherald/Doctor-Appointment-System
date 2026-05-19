<?php
/**
 * Auth Controller
 * Handles login, register, forgot-password and reset-password actions.
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Doctor.php';
require_once __DIR__ . '/../config/constants.php';

// ----- Check if a patient profile still has required fields empty -----
function isPatientProfileIncomplete($profile)
{
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

// ----- Handle user login -----
function handleLogin($data)
{
    // Validate required fields
    if (empty($data['email']) || empty($data['password'])) {
        return ['success' => false, 'message' => 'Email and password are required'];
    }

    // Find user by email
    $user = getUserByEmail($data['email']);
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    // Verify password
    if (!verifyPassword($data['password'], $user['password'])) {
        return ['success' => false, 'message' => 'Invalid email or password'];
    }

    // Check if account is active
    if ($user['status'] === 'inactive') {
        return ['success' => false, 'message' => 'Your account has been deactivated. Contact admin.'];
    }

    // Check doctor approval status
    if ($user['role'] === ROLE_DOCTOR) {
        $profile = getDoctorProfile($user['id']);
        if ($profile && $profile['approval_status'] === DOCTOR_PENDING) {
            return ['success' => false, 'message' => 'Your doctor account is pending admin approval'];
        }
        if ($profile && $profile['approval_status'] === DOCTOR_REJECTED) {
            return ['success' => false, 'message' => 'Your doctor registration has been rejected'];
        }
    }

    // Flag incomplete patient profiles so the frontend can prompt them
    if ($user['role'] === ROLE_PATIENT) {
        $profile = getPatientProfile($user['id']);
        $user['needs_profile_completion'] = isPatientProfileIncomplete($profile);
    }

    // Remove password hash before sending to frontend
    unset($user['password']);

    // START SESSION AND SET VARIABLES
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role'] = $user['role'];

    return [
        'success' => true,
        'message' => 'Login successful',
        'user' => $user
    ];
}

// Handle new user registration
function handleRegister($data)
{
    // Validate required fields
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

    // Check for duplicate email
    $existing = getUserByEmail($data['email']);
    if ($existing) {
        return ['success' => false, 'message' => 'Email already registered'];
    }

    try {
        // Create user row
        $userId = createUser($data['name'], $data['email'], $data['password'], $data['role']);

        // Create role-specific profile
        if ($data['role'] === ROLE_PATIENT) {
            createPatientProfile($userId);
        } else if ($data['role'] === ROLE_DOCTOR) {
            createDoctorProfile($userId);
            // Save specialization if provided during registration
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

// Get basic user profile by ID
function handleGetProfile($userId)
{
    $user = getUserById($userId);
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    unset($user['password']);
    return ['success' => true, 'user' => $user];
}

// ----- Handle forgot-password request -----
// Generates a reset token and returns a link (in a real project this would be emailed)
function handleForgotPassword($data)
{
    if (empty($data['email'])) {
        return ['success' => false, 'message' => 'Email is required'];
    }

    // Lookup the user
    $user = getUserByEmail($data['email']);
    if (!$user) {
        // Return generic success to prevent email enumeration
        return ['success' => true, 'message' => 'If the email exists, a reset link has been sent.'];
    }

    // Create a reset token
    $token = createPasswordResetToken($user['id']);

    // Build the reset link (points to the frontend reset page)
    $resetLink = '/Appointment/frontend/html/reset-password.html?token=' . $token;

    // In a real production app you would email this link.
    // For this project we return it directly so the user can click it.
    return [
        'success' => true,
        'message' => 'Password reset link generated. Check your email.',
        'reset_link' => $resetLink
    ];
}

// ----- Handle the actual password reset -----
function handleResetPassword($data)
{
    if (empty($data['token']) || empty($data['password'])) {
        return ['success' => false, 'message' => 'Token and new password are required'];
    }
    if (strlen($data['password']) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters'];
    }

    // Validate the token
    $resetRecord = validateResetToken($data['token']);
    if (!$resetRecord) {
        return ['success' => false, 'message' => 'Invalid or expired reset token'];
    }

    // Update the user's password
    updateUserPassword($resetRecord['user_id'], $data['password']);

    // Mark the token as used so it cannot be reused
    markTokenUsed($data['token']);

    return ['success' => true, 'message' => 'Password reset successful. You can now login.'];
}

// ----- Validate a token (used by the frontend to check before showing the form) -----
function handleValidateToken($token)
{
    if (empty($token)) {
        return ['success' => false, 'message' => 'Token is required'];
    }
    $resetRecord = validateResetToken($token);
    if (!$resetRecord) {
        return ['success' => false, 'message' => 'Invalid or expired reset token'];
    }
    return ['success' => true, 'message' => 'Token is valid'];
}
