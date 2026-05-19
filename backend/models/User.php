<?php
/**
 * User Model
 * Handles all database operations for the users table
 * and the password_resets table (forgot-password feature).
 */

require_once __DIR__ . '/../config/db.php';

// ----- Create a new user row -----
function createUser($name, $email, $password, $role) {
    $pdo = getDBConnection();
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (:name, :email, :password, :role)");
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':password' => $hashedPassword,
        ':role' => $role
    ]);
    return $pdo->lastInsertId();
}

// ----- Fetch a single user by email -----
function getUserByEmail($email) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    return $stmt->fetch();
}

// ----- Fetch a single user by ID -----
function getUserById($id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

// ----- Get all users, optionally filtered by role -----
function getAllUsers($role = null) {
    $pdo = getDBConnection();
    if ($role) {
        $stmt = $pdo->prepare("SELECT id, name, email, role, status, created_at FROM users WHERE role = :role ORDER BY created_at DESC");
        $stmt->execute([':role' => $role]);
    } else {
        $stmt = $pdo->prepare("SELECT id, name, email, role, status, created_at FROM users ORDER BY created_at DESC");
        $stmt->execute();
    }
    return $stmt->fetchAll();
}

// ----- Update allowed user fields (name, email, status, role) -----
function updateUser($id, $data) {
    $pdo = getDBConnection();
    $fields = [];
    $params = [':id' => $id];

    // Only allow whitelisted columns
    foreach ($data as $key => $value) {
        if (in_array($key, ['name', 'email', 'status', 'role'])) {
            $fields[] = "$key = :$key";
            $params[":$key"] = $value;
        }
    }

    if (empty($fields)) return false;

    $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute($params);
}

// ----- Update user password (hashes automatically) -----
function updateUserPassword($id, $newPassword) {
    $pdo = getDBConnection();
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
    return $stmt->execute([':password' => $hashedPassword, ':id' => $id]);
}

// ----- Delete a user (admins cannot be deleted) -----
function deleteUser($id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND role != 'admin'");
    return $stmt->execute([':id' => $id]);
}

// ----- Count users, optionally by role -----
function countUsers($role = null) {
    $pdo = getDBConnection();
    if ($role) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = :role");
        $stmt->execute([':role' => $role]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users");
        $stmt->execute();
    }
    $result = $stmt->fetch();
    return $result['total'];
}

// ----- Verify a plain-text password against a hash -----
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// =====================================================
// Password Reset Functions (Forgot Password Feature)
// =====================================================

// ----- Create a password-reset OTP for the given user -----
function createPasswordResetOtp($userId) {
    $pdo = getDBConnection();

    // Invalidate any previous unused otps for this user
    $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE user_id = :user_id AND used = 0");
    $stmt->execute([':user_id' => $userId]);

    // Generate a 6-digit OTP and set 1-hour expiry
    // Use MySQL's DATE_ADD(NOW(), INTERVAL 1 HOUR) so that creation and
    // validation both rely on the same MySQL clock (avoids PHP ↔ MySQL timezone mismatches).
    $otp = sprintf("%06d", mt_rand(1, 999999));

    $stmt = $pdo->prepare("INSERT INTO password_resets (user_id, otp, expires_at) VALUES (:user_id, :otp, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
    $stmt->execute([
        ':user_id' => $userId,
        ':otp' => $otp
    ]);

    return $otp;
}

// ----- Validate a reset OTP (must exist, not expired, not used) -----
function validateResetOtp($otp) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM password_resets WHERE otp = :otp AND used = 0 AND expires_at > NOW()");
    $stmt->execute([':otp' => $otp]);
    return $stmt->fetch();
}

// ----- Mark an OTP as used after the password has been changed -----
function markOtpUsed($otp) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("UPDATE password_resets SET used = 1 WHERE otp = :otp");
    return $stmt->execute([':otp' => $otp]);
}
