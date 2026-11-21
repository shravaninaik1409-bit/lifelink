<?php
/**
 * LifeLink - Authentication & Access Control Guard
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/functions.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    return [
        'user_id' => $_SESSION['user_id'],
        'full_name' => $_SESSION['full_name'] ?? 'User',
        'email' => $_SESSION['email'] ?? '',
        'role' => $_SESSION['role'] ?? '',
        'donor_id' => $_SESSION['donor_id'] ?? null,
        'blood_group' => $_SESSION['blood_group'] ?? null,
    ];
}

function requireLogin() {
    if (!isLoggedIn()) {
        $_SESSION['flash_error'] = "Please log in to continue.";
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
}

function requireRole($allowedRoles) {
    requireLogin();
    if (!is_array($allowedRoles)) {
        $allowedRoles = [$allowedRoles];
    }
    $currentRole = $_SESSION['role'] ?? '';
    if (!in_array($currentRole, $allowedRoles)) {
        $_SESSION['flash_error'] = "Access denied. You do not have permission to view that page.";
        // Redirect to appropriate dashboard based on their role
        if ($currentRole === 'admin') {
            header("Location: " . BASE_URL . "/admin/dashboard.php");
        } elseif ($currentRole === 'donor') {
            header("Location: " . BASE_URL . "/donor/dashboard.php");
        } elseif ($currentRole === 'requester') {
            header("Location: " . BASE_URL . "/requester/dashboard.php");
        } else {
            header("Location: " . BASE_URL . "/index.php");
        }
        exit();
    }
}
