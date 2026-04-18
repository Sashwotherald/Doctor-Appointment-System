<?php
/**
 * User Model - Database operations for users
 */

require_once __DIR__ . '/../config/db.php';

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

function getUserByEmail($email) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
    $stmt->execute([':email' => $email]);
    return $stmt->fetch();
}

function getUserById($id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

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

function updateUser($id, $data) {
    $pdo = getDBConnection();
    $fields = [];
    $params = [':id' => $id];
    
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

function updateUserPassword($id, $newPassword) {
    $pdo = getDBConnection();
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :id");
    return $stmt->execute([':password' => $hashedPassword, ':id' => $id]);
}

function deleteUser($id) {
    $pdo = getDBConnection();
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id AND role != 'admin'");
    return $stmt->execute([':id' => $id]);
}

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

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
