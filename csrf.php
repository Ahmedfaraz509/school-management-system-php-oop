<?php
declare(strict_types=1);

/**
 * CSRF Protection Helper for EduPulse SMS
 * 
 * Provides CSRF token generation and validation.
 */

namespace EduPulse\Helpers;

class Csrf
{
  private const TOKEN_NAME = 'csrf_token';
  private const TOKEN_LENGTH = 32;

  /**
   * Generate a new CSRF token
   */
  public static function generate(): string
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    // Generate cryptographically secure token
    $token = bin2hex(random_bytes(self::TOKEN_LENGTH));

    // Store in session
    $_SESSION[self::TOKEN_NAME] = $token;

    return $token;
  }

  /**
   * Get current CSRF token from session
   */
  public static function getToken(): string
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    return $_SESSION[self::TOKEN_NAME] ?? self::generate();
  }

  /**
   * Validate a CSRF token
   */
  public static function validate(?string $token = null): bool
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    if ($token === null) {
      $token = $_POST[self::TOKEN_NAME] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
    }

    if ($token === null || !isset($_SESSION[self::TOKEN_NAME])) {
      return false;
    }

    // Use timing-safe comparison
    return hash_equals($_SESSION[self::TOKEN_NAME], $token);
  }

  /**
   * Get the token input field HTML
   */
  public static function field(): string
  {
    return '<input type="hidden" name="' . self::TOKEN_NAME . '" value="' . escape(self::getToken()) . '">';
  }

  /**
   * Regenerate token (call after successful validation)
   */
  public static function regenerate(): string
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    // Clear old token
    unset($_SESSION[self::TOKEN_NAME]);

    // Generate new one
    return self::generate();
  }
}