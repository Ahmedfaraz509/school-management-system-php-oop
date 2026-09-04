<?php
declare(strict_types=1);

/**
 * User Model for EduPulse SMS
 * 
 * Handles user account operations including student user creation.
 */

namespace EduPulse\Models;

use PDO;
use PDOException;

class User
{
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  /**
   * Find a user by ID
   */
  public function findById(int $id): ?array
  {
    $stmt = $this->pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    $user = $stmt->fetch();

    return $user ?: null;
  }

  /**
   * Find a user by email
   */
  public function findByEmail(string $email): ?array
  {
    $stmt = $this->pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([trim($email)]);
    $user = $stmt->fetch();

    return $user ?: null;
  }

  /**
   * Check if email exists
   */
  public function emailExists(string $email): bool
  {
    $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
    $stmt->execute([trim($email)]);

    return (int) $stmt->fetchColumn() > 0;
  }

  /**
   * Create a student user account
   * 
   * @param array $data User data including name, email, password
   * @return int|null The newly created user ID or null on failure
   */
  public function createStudentUser(array $data): ?int
  {
    try {
      $stmt = $this->pdo->prepare("
                INSERT INTO users (name, email, password, role, status, created_at, updated_at)
                VALUES (?, ?, ?, 'student', 'active', NOW(), NOW())
            ");

      $result = $stmt->execute([
        trim($data['name']),
        trim($data['email']),
        password_hash($data['password'], PASSWORD_DEFAULT)
      ]);

      if ($result) {
        return (int) $this->pdo->lastInsertId();
      }

      return null;
    } catch (PDOException $e) {
      error_log('Failed to create student user: ' . $e->getMessage());
      return null;
    }
  }

  /**
   * Update a user
   */
  public function update(int $id, array $data): bool
  {
    try {
      $fields = [];
      $values = [];

      if (isset($data['name'])) {
        $fields[] = 'name = ?';
        $values[] = trim($data['name']);
      }

      if (isset($data['email'])) {
        $fields[] = 'email = ?';
        $values[] = trim($data['email']);
      }

      if (isset($data['password']) && !empty($data['password'])) {
        $fields[] = 'password = ?';
        $values[] = password_hash($data['password'], PASSWORD_DEFAULT);
      }

      if (isset($data['status'])) {
        $fields[] = 'status = ?';
        $values[] = $data['status'];
      }

      if (empty($fields)) {
        return false;
      }

      $fields[] = 'updated_at = NOW()';
      $values[] = $id;

      $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
      $stmt = $this->pdo->prepare($sql);

      return $stmt->execute($values);
    } catch (PDOException $e) {
      error_log('Failed to update user: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Delete a user
   */
  public function delete(int $id): bool
  {
    try {
      $stmt = $this->pdo->prepare("DELETE FROM users WHERE id = ?");
      return $stmt->execute([$id]);
    } catch (PDOException $e) {
      error_log('Failed to delete user: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Verify user password
   */
  public function verifyPassword(string $email, string $password): ?array
  {
    $user = $this->findByEmail($email);

    if ($user && password_verify($password, $user['password'])) {
      return $user;
    }

    return null;
  }
}