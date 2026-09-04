<?php
declare(strict_types=1);

/**
 * Bootstrap file for EduPulse SMS
 * 
 * This file initializes the application environment,
 * starts sessions, and loads necessary configurations.
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Set error reporting based on environment
$environment = $_ENV['APP_ENV'] ?? 'production';

if ($environment === 'development') {
  error_reporting(E_ALL);
  ini_set('display_errors', '1');
} else {
  error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
  ini_set('display_errors', '0');
  ini_set('log_errors', '1');
}

// Set default timezone
date_default_timezone_set('UTC');

// Define application constants
define('APP_NAME', 'EduPulse SMS');
define('APP_VERSION', '2.4.0');
define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_PATH', BASE_PATH . '/uploads/students');
define('UPLOAD_URL', '/uploads/students');

// Maximum upload file size (5MB)
define('MAX_FILE_SIZE', 5 * 1024 * 1024);

// Allowed image extensions
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'webp']);

// Allowed MIME types for images
define('ALLOWED_MIME_TYPES', [
  'image/jpeg',
  'image/png',
  'image/webp'
]);

// Load Composer's autoloader if available
$autoloader = BASE_PATH . '/vendor/autoload.php';
if (file_exists($autoloader)) {
  require_once $autoloader;
}

// Load database configuration
require_once __DIR__ . '/database.php';

// Load helper functions
require_once __DIR__ . '/../student_helpers/csrf.php';
require_once __DIR__ . '/../student_helpers/auth.php';
require_once __DIR__ . '/../student_helpers/response.php';

// Load models
require_once __DIR__ . '/../models_student/User.php';
require_once __DIR__ . '/../models_student/ClassModel.php';
require_once __DIR__ . '/../models_student/Student.php';

// Load controller
require_once __DIR__ . '/../controllers/StudentController.php';

/**
 * Helper function to escape output
 */
function escape(string $string): string
{
  return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Generate a unique student UID
 */
function generateStudentUid(PDO $pdo): string
{
  do {
    $randomNum = random_int(10000, 99999);
    $uid = 'STD-' . $randomNum;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_uid = ?");
    $stmt->execute([$uid]);
    $count = (int) $stmt->fetchColumn();
  } while ($count > 0);

  return $uid;
}

/**
 * Format date for display
 */
function formatDate(string $date, string $format = 'F j, Y'): string
{
  return date($format, strtotime($date));
}

/**
 * Calculate age from date of birth
 */
function calculateAge(string $dateOfBirth): int
{
  return (int) date('Y') - (int) date('Y', strtotime($dateOfBirth));
}

/**
 * Create upload directory if it doesn't exist
 */
function ensureUploadDirectory(string $path): bool
{
  if (!is_dir($path)) {
    return mkdir($path, 0755, true);
  }
  return true;
}