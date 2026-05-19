<?php
/**
 * Auth Controller
 * Handles login, register, forgot-password and reset-password actions.
 */

require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Patient.php';
require_once __DIR__ . '/../models/Doctor.php';
require_once __DIR__ . '/../config/constants.php';
<<<<<<< HEAD
=======
require_once __DIR__ . '/../helpers/Mailer.php';
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e

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
<<<<<<< HEAD
=======
    
    // Validate Full Name
    if (strlen($data['name']) > 30) {
        return ['success' => false, 'message' => 'Full name cannot exceed 30 characters'];
    }
    if (!preg_match('/^[a-zA-Z\s]+$/', $data['name'])) {
        return ['success' => false, 'message' => 'Full name can only contain letters and spaces'];
    }

>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }
    if (strlen($data['password']) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters'];
    }
<<<<<<< HEAD
=======
    if (!preg_match('/[A-Z]/', $data['password'])) {
        return ['success' => false, 'message' => 'Password must contain at least one capital letter'];
    }
    if (!preg_match('/[0-9]/', $data['password'])) {
        return ['success' => false, 'message' => 'Password must contain at least one number'];
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $data['password'])) {
        return ['success' => false, 'message' => 'Password must contain at least one special character'];
    }
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    if (!in_array($data['role'], [ROLE_PATIENT, ROLE_DOCTOR])) {
        return ['success' => false, 'message' => 'Invalid role selected'];
    }

<<<<<<< HEAD
=======
    if ($data['role'] === ROLE_DOCTOR) {
        if (empty($data['nmc'])) {
            return ['success' => false, 'message' => 'NMC number is required for doctors'];
        }
        if (empty($data['specialization'])) {
            return ['success' => false, 'message' => 'Specialization is required for doctors'];
        }
    }

>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
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
<<<<<<< HEAD
            // Save specialization if provided during registration
            if (!empty($data['specialization'])) {
                updateDoctorProfile($userId, ['specialization' => $data['specialization']]);
=======

            $updateData = [];
            if (!empty($data['specialization'])) {
                $updateData['specialization'] = $data['specialization'];
            }
            if (!empty($data['nmc'])) {
                $updateData['nmc'] = $data['nmc'];
            }

            if (!empty($updateData)) {
                updateDoctorProfile($userId, $updateData);
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
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
<<<<<<< HEAD
// Generates a reset token and returns a link (in a real project this would be emailed)
=======
// Generates a reset OTP and emails it to the user via Gmail SMTP
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
function handleForgotPassword($data)
{
    if (empty($data['email'])) {
        return ['success' => false, 'message' => 'Email is required'];
    }

    // Lookup the user
    $user = getUserByEmail($data['email']);
    if (!$user) {
        // Return generic success to prevent email enumeration
<<<<<<< HEAD
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
=======
        return ['success' => true, 'message' => 'If the email exists, a password reset OTP has been sent.'];
    }

    // Create a reset OTP
    $otp = createPasswordResetOtp($user['id']);

    // Build the beautiful HTML email
    $emailHtml = "
    <div style='font-family: Arial, sans-serif; padding: 20px; background-color: #f4f4f4;'>
        <div style='background-color: #fff; padding: 20px; border-radius: 5px; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>
            <h2 style='color: #333;'>Password Reset Request</h2>
            <p>Dear {$user['name']},</p>
            <p>You have requested to reset your password. Please use the following One-Time Password (OTP) to reset your password:</p>
            <h3 style='background-color: #128C7E; color: #fff; padding: 10px; display: inline-block; border-radius: 5px;'>{$otp}</h3>
            <p>If you did not request this, please ignore this email.</p>
            <p>Regards,<br>Herald Clinical Sanctuary</p>
        </div>
    </div>";

    // Send the email via Gmail SMTP
    $mailResult = sendMail(
        $user['email'],
        $user['name'],
        'Password Reset OTP - Herald Clinical Sanctuary',
        $emailHtml
    );

    if (!$mailResult['success']) {
        error_log('Forgot-password email failed for ' . $user['email'] . ': ' . $mailResult['message']);
        // Email sending failed (SMTP not configured) – return the OTP
        // directly so the user can still reset their password (dev/demo mode).
        return [
            'success' => true,
            'message' => 'Email delivery is not configured. Your OTP is below.',
            'otp' => $otp
        ];
    }

    return [
        'success' => true,
        'message' => 'Password reset OTP has been sent to your email address.'
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    ];
}

// ----- Handle the actual password reset -----
function handleResetPassword($data)
{
<<<<<<< HEAD
    if (empty($data['token']) || empty($data['password'])) {
        return ['success' => false, 'message' => 'Token and new password are required'];
=======
    if (empty($data['otp']) || empty($data['password'])) {
        return ['success' => false, 'message' => 'OTP and new password are required'];
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    }
    if (strlen($data['password']) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters'];
    }
<<<<<<< HEAD

    // Validate the token
    $resetRecord = validateResetToken($data['token']);
    if (!$resetRecord) {
        return ['success' => false, 'message' => 'Invalid or expired reset token'];
=======
    if (!preg_match('/[A-Z]/', $data['password'])) {
        return ['success' => false, 'message' => 'Password must contain at least one capital letter'];
    }
    if (!preg_match('/[0-9]/', $data['password'])) {
        return ['success' => false, 'message' => 'Password must contain at least one number'];
    }
    if (!preg_match('/[^a-zA-Z0-9]/', $data['password'])) {
        return ['success' => false, 'message' => 'Password must contain at least one special character'];
    }
    $resetRecord = validateResetOtp($data['otp']);
    if (!$resetRecord) {
        return ['success' => false, 'message' => 'Invalid or expired reset OTP'];
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
    }

    // Update the user's password
    updateUserPassword($resetRecord['user_id'], $data['password']);

<<<<<<< HEAD
    // Mark the token as used so it cannot be reused
    markTokenUsed($data['token']);
=======
    // Mark the OTP as used so it cannot be reused
    markOtpUsed($data['otp']);
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e

    return ['success' => true, 'message' => 'Password reset successful. You can now login.'];
}

<<<<<<< HEAD
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
=======
// ----- Validate an OTP (used by the frontend to check before showing the form) -----
function handleValidateOtp($otp)
{
    if (empty($otp)) {
        return ['success' => false, 'message' => 'OTP is required'];
    }
    $resetRecord = validateResetOtp($otp);
    if (!$resetRecord) {
        return ['success' => false, 'message' => 'Invalid or expired reset OTP'];
    }
    return ['success' => true, 'message' => 'OTP is valid'];
>>>>>>> 6dfa967331fa76f1debbef58388a047103e50e9e
}
