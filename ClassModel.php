<?php
declare(strict_types=1);

/**
 * Class Model for EduPulse SMS
 * 
 * Handles class/grade operations for student enrollment.
 */

namespace EduPulse\Models;

use PDO;
use PDOException;

class ClassModel
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  /**
   * Get all active classes
   */
  public function getAllActive(): array
  {
    try {
      $stmt = $this->pdo->prepare("
                SELECT id, name 
                FROM classes 
                WHERE status = 'active' 
                ORDER BY name ASC
            ");
      $stmt->execute();

      return $stmt->fetchAll();
    } catch (PDOException $e) {
      error_log('Failed to fetch classes: ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Get class by ID
   */
  public function getById(int $id): ?array
  {
    try {
      $stmt = $this->pdo->prepare("SELECT * FROM classes WHERE id = ?");
      $stmt->execute([$id]);
      $class = $stmt->fetch();

      return $class ?: null;
    } catch (PDOException $e) {
      error_log('Failed to fetch class: ' . $e->getMessage());
      return null;
    }
  }

  /**
   * Check if class exists
   */
  public function exists(int $id): bool
  {
    try {
      $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM classes WHERE id = ?");
      $stmt->execute([$id]);

      return (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
      error_log('Failed to check class existence: ' . $e->getMessage());
      return false;
    }
  }
}