<?php
declare(strict_types=1);

/**
 * HTTP Response Helper for EduPulse SMS
 * 
 * Provides consistent JSON response formatting.
 */

namespace EduPulse\Helpers;

class Response
{
  /**
   * Send a JSON success response
   */
  public static function jsonSuccess(
    string $message,
    array $data = [],
    int $statusCode = 200
  ): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
      'success' => true,
      'message' => $message,
      'data' => $data
    ], JSON_UNESCAPED_UNICODE);

    exit;
  }

  /**
   * Send a JSON error response
   */
  public static function jsonError(
    string $message,
    int $statusCode = 400,
    array $errors = []
  ): void {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');

    $response = [
      'success' => false,
      'message' => $message
    ];

    if (!empty($errors)) {
      $response['errors'] = $errors;
    }

    echo json_encode($response, JSON_UNESCAPED_UNICODE);

    exit;
  }

  /**
   * Send a JSON redirect response
   */
  public static function jsonRedirect(string $url, string $message = ''): void
  {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
      'success' => true,
      'redirect' => $url,
      'message' => $message
    ], JSON_UNESCAPED_UNICODE);

    exit;
  }

  /**
   * Send a paginated JSON response
   */
  public static function jsonPaginated(
    array $data,
    int $totalRecords,
    int $currentPage,
    int $totalPages
  ): void {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
      'success' => true,
      'data' => $data,
      'pagination' => [
        'total_records' => $totalRecords,
        'current_page' => $currentPage,
        'total_pages' => $totalPages,
        'per_page' => 10
      ]
    ], JSON_UNESCAPED_UNICODE);

    exit;
  }

  /**
   * Set flash message in session
   */
  public static function setFlash(string $type, string $message): void
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    $_SESSION['flash'] = [
      'type' => $type,
      'message' => $message
    ];
  }

  /**
   * Get and clear flash message
   */
  public static function getFlash(): ?array
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);

    return $flash;
  }
}