<?php
/**
 * Auth Controller - Simplified for v1 (login & register only)
 */

require_once __DIR__ . '/../models/User.php';

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
        return ['success' => false, 'message' => 'Your account has been deactivated'];
    }
    
    unset($user['password']);
    
    return [
        'success' => true,
        'message' => 'Login successful',
        'user' => $user
    ];
}

function handleRegister($data) {
    if (empty($data['name']) || empty($data['email']) || empty($data['password'])) {
        return ['success' => false, 'message' => 'All fields are required'];
    }
    
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email format'];
    }
    
    if (strlen($data['password']) < 6) {
        return ['success' => false, 'message' => 'Password must be at least 6 characters'];
    }
    
    $existing = getUserByEmail($data['email']);
    if ($existing) {
        return ['success' => false, 'message' => 'Email already registered'];
    }
    
    try {
        $userId = createUser($data['name'], $data['email'], $data['password']);
        
        return [
            'success' => true,
            'message' => 'Registration successful! You can now login.',
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
