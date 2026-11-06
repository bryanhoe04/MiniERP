<?php
// config/auth.php - Manajemen Autentikasi & Otorisasi Session

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser(): ?array {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['user_role'] ?? 'kasir'
    ];
}

function requireLogin(): void {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function requireRole(array $allowedRoles): void {
    requireLogin();
    $user = getCurrentUser();
    if (!in_array($user['role'], $allowedRoles, true)) {
        http_response_code(403);
        include __DIR__ . '/../includes/403.php';
        exit;
    }
}

function hasRole(array $allowedRoles): bool {
    if (!isLoggedIn()) return false;
    $user = getCurrentUser();
    return in_array($user['role'], $allowedRoles, true);
}
