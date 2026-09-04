<?php

declare(strict_types=1);

// Existing database connection (was pointing at a non-existent config/database.php)
require_once __DIR__ . '/../../database/connect.php';

// CSRF helper class
require_once __DIR__ . '/../core/CSRF.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Load required classes
require_once __DIR__ . '/../models/ClassModel.php';

if (file_exists(__DIR__ . '/../models/Teacher.php')) {
  require_once __DIR__ . '/../models/Teacher.php';
}

// Make sure existing connection is available
if (!isset($conn) || !$conn instanceof PDO) {
  die('Database connection is not available.');
}

// connect.php exposes the PDO instance as $conn; the classes module expects $pdo.
$pdo = $conn;

/**
 * ---------------------------------------------------------------------
 * Auth / flash / CSRF helpers used by classes.php, class_action.php and
 * ajax/class_students.php. These were being called but were never
 * defined anywhere in the project, causing fatal "Call to undefined
 * function" errors on every request to the Classes module.
 * ---------------------------------------------------------------------
 */

if (!function_exists('cm_is_authenticated')) {
  function cm_is_authenticated(): bool
  {
    return !empty($_SESSION['logged_in']) && !empty($_SESSION['user_id']);
  }
}

if (!function_exists('cm_require_auth')) {
  function cm_require_auth(): void
  {
    if (!cm_is_authenticated()) {
      header('Location: ../auth/login.php');
      exit;
    }
  }
}

if (!function_exists('cm_is_admin')) {
  function cm_is_admin(): bool
  {
    return cm_is_authenticated() && ($_SESSION['user_role'] ?? '') === 'admin';
  }
}

if (!function_exists('cm_flash_set')) {
  function cm_flash_set(string $type, string $message): void
  {
    if (!isset($_SESSION['_flashes']) || !is_array($_SESSION['_flashes'])) {
      $_SESSION['_flashes'] = [];
    }
    $_SESSION['_flashes'][] = ['type' => $type, 'message' => $message];
  }
}

if (!function_exists('cm_flash_get')) {
  function cm_flash_get(): array
  {
    $flashes = $_SESSION['_flashes'] ?? [];
    $_SESSION['_flashes'] = [];
    return $flashes;
  }
}

if (!function_exists('cm_csrf_token')) {
  function cm_csrf_token(): string
  {
    return CSRF::token();
  }
}

if (!function_exists('cm_csrf_verify')) {
  function cm_csrf_verify(?string $token): bool
  {
    return CSRF::verify($token);
  }
}