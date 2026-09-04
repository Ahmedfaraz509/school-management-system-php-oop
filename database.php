<?php
declare(strict_types=1);

/**
 * Database connection for the Student module (EduPulse SMS).
 *
 * This file was missing even though config_student/bootstrap.php required
 * it, which caused a fatal "Failed opening required database.php" error on
 * every page that bootstraps the student module (students.php, api/students.php).
 *
 * It uses the same credentials as school/database/connect.php so both the
 * legacy $conn-based code and the new PDO-based student module talk to the
 * same database.
 */

if (!function_exists('getDB')) {
  /**
   * Get a shared PDO connection for the student module.
   */
  function getDB(): PDO
  {
    static $pdo = null;

    if ($pdo === null) {
      $host = 'localhost';
      $dbName = 'school_management';
      $username = 'root';
      $password = '';

      $pdo = new PDO(
        "mysql:host={$host};dbname={$dbName};charset=utf8mb4",
        $username,
        $password
      );
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
      $pdo->exec("SET NAMES utf8mb4");
    }

    return $pdo;
  }
}
