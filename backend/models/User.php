<?php
/**
 * User Model - Database operations for users
 */

require_once __DIR__ . '/../config/db.php';

function createUser($name, $email, $password) {
    $pdo = getDBConnection();
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (:name, :email, :password)");
    $stmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':password' => $hashedPassword
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

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}
