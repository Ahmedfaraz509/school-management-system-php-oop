<?php
// core/CSRF.php
declare(strict_types=1);

class CSRF
{
  private const KEY = '_csrf_token';

  public static function token(): string
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    if (empty($_SESSION[self::KEY])) {
      $_SESSION[self::KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[self::KEY];
  }

  public static function verify(?string $token): bool
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    if (empty($token) || empty($_SESSION[self::KEY])) {
      return false;
    }
    return hash_equals($_SESSION[self::KEY], $token);
  }
}