<?php
declare(strict_types=1);

/**
 * Authentication Helper for EduPulse SMS
 * 
 * Handles user authentication and authorization.
 */

namespace EduPulse\Helpers;

class Auth
{
  /**
   * Check if user is logged in
   */
  public static function check(): bool
  {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
  }

  /**
   * Check if current user is admin
   */
  public static function isAdmin(): bool
  {
    return self::check() && ($_SESSION['user_role'] ?? '') === 'admin';
  }

  /**
   * Check if current user is teacher
   */
  public static function isTeacher(): bool
  {
    return self::check() && ($_SESSION['user_role'] ?? '') === 'teacher';
  }

  /**
   * Check if current user is student
   */
  public static function isStudent(): bool
  {
    return self::check() && ($_SESSION['user_role'] ?? '') === 'student';
  }

  /**
   * Get current user ID
   */
  public static function userId(): ?int
  {
    return $_SESSION['user_id'] ?? null;
  }

  /**
   * Get current user role
   */
  public static function role(): ?string
  {
    return $_SESSION['user_role'] ?? null;
  }

  /**
   * Get current user name
   */
  public static function name(): ?string
  {
    return $_SESSION['user_name'] ?? null;
  }

  /**
   * Login user
   */
  public static function login(array $user): void
  {
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['logged_in_at'] = time();
  }

  /**
   * Logout user
   */
  public static function logout(): void
  {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
      );
    }

    session_destroy();
  }

  /**
   * Require authentication - redirects to login if not authenticated
   */
  public static function requireAuth(string $redirectUrl = 'login.php'): void
  {
    if (!self::check()) {
      header('Location: ' . $redirectUrl);
      exit;
    }
  }

  /**
   * Require admin role - redirects or dies if not admin
   */
  public static function requireAdmin(string $redirectUrl = 'index.php'): void
  {
    self::requireAuth($redirectUrl);

    if (!self::isAdmin()) {
      // Redirect to dashboard if not admin
      header('Location: ' . $redirectUrl);
      exit;
    }
  }

  /**
   * Check permission for student management
   */
  public static function canManageStudents(): bool
  {
    return self::isAdmin();
  }
}