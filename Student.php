<?php
declare(strict_types=1);

/**
 * Student Model for EduPulse SMS
 * 
 * Handles all student-related database operations with proper
 * transaction support and relationship management.
 */

namespace EduPulse\Models;

use PDO;
use PDOException;

class Student
{
  private PDO $pdo;

  // Valid status values
  public const VALID_STATUSES = ['Active', 'Pending Doc', 'Inactive'];

  // Valid gender values
  public const VALID_GENDERS = ['Male', 'Female', 'Other'];

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
  }

  /**
   * Get all students with pagination and filtering
   */
  public function getAll(
    int $page = 1,
    int $perPage = 10,
    array $filters = [],
    ?string $search = null
  ): array {
    try {
      $conditions = ['1=1'];
      $params = [];

      // Search condition
      if ($search !== null && trim($search) !== '') {
        $searchTerm = '%' . trim($search) . '%';
        $conditions[] = "(
                    s.first_name LIKE ? OR 
                    s.last_name LIKE ? OR 
                    s.student_uid LIKE ? OR 
                    s.email LIKE ?
                )";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
      }

      // Class filter
      if (!empty($filters['class_id']) && is_numeric($filters['class_id'])) {
        $conditions[] = 's.class_id = ?';
        $params[] = (int) $filters['class_id'];
      }

      // Gender filter
      if (!empty($filters['gender']) && in_array($filters['gender'], self::VALID_GENDERS)) {
        $conditions[] = 's.gender = ?';
        $params[] = $filters['gender'];
      }

      // Status filter
      if (!empty($filters['status']) && in_array($filters['status'], self::VALID_STATUSES)) {
        $conditions[] = 's.status = ?';
        $params[] = $filters['status'];
      }

      $whereClause = implode(' AND ', $conditions);

      // Count total records
      $countSql = "
                SELECT COUNT(*) 
                FROM students s 
                LEFT JOIN classes c ON c.id = s.class_id 
                LEFT JOIN users u ON u.id = s.user_id 
                WHERE {$whereClause}
            ";

      $stmt = $this->pdo->prepare($countSql);
      $stmt->execute($params);
      $totalRecords = (int) $stmt->fetchColumn();

      // Calculate pagination
      $totalPages = max(1, (int) ceil($totalRecords / $perPage));
      $page = max(1, min($page, $totalPages));
      $offset = ($page - 1) * $perPage;

      // Fetch records
      $sql = "
                SELECT
                    s.id,
                    s.user_id,
                    s.student_uid,
                    s.first_name,
                    s.last_name,
                    s.email,
                    s.gender,
                    s.date_of_birth,
                    s.class_id,
                    s.admission_date,
                    s.address,
                    s.status,
                    s.photo_url,
                    c.name AS class_name,
                    u.status AS user_status,
                    u.created_at AS user_created_at
                FROM students s
                LEFT JOIN classes c ON c.id = s.class_id
                LEFT JOIN users u ON u.id = s.user_id
                WHERE {$whereClause}
                ORDER BY s.id DESC
                LIMIT {$perPage} OFFSET {$offset}
            ";

      $stmt = $this->pdo->prepare($sql);
      $stmt->execute($params);
      $students = $stmt->fetchAll();

      return [
        'students' => $students,
        'total_records' => $totalRecords,
        'total_pages' => $totalPages,
        'current_page' => $page,
        'per_page' => $perPage
      ];
    } catch (PDOException $e) {
      error_log('Failed to fetch students: ' . $e->getMessage());
      return [
        'students' => [],
        'total_records' => 0,
        'total_pages' => 0,
        'current_page' => 1,
        'per_page' => $perPage
      ];
    }
  }

  /**
   * Get student by ID
   */
  public function getById(int $id): ?array
  {
    try {
      $stmt = $this->pdo->prepare("
                SELECT
                    s.*,
                    c.name AS class_name,
                    u.status AS user_status,
                    u.name AS user_name,
                    u.email AS user_email
                FROM students s
                LEFT JOIN classes c ON c.id = s.class_id
                LEFT JOIN users u ON u.id = s.user_id
                WHERE s.id = ?
            ");
      $stmt->execute([$id]);
      $student = $stmt->fetch();

      return $student ?: null;
    } catch (PDOException $e) {
      error_log('Failed to fetch student by ID: ' . $e->getMessage());
      return null;
    }
  }

  /**
   * Get student by user ID
   */
  public function getByUserId(int $userId): ?array
  {
    try {
      $stmt = $this->pdo->prepare("SELECT * FROM students WHERE user_id = ?");
      $stmt->execute([$userId]);
      $student = $stmt->fetch();

      return $student ?: null;
    } catch (PDOException $e) {
      error_log('Failed to fetch student by user ID: ' . $e->getMessage());
      return null;
    }
  }

  /**
   * Get student by student UID
   */
  public function getByStudentUid(string $studentUid): ?array
  {
    try {
      $stmt = $this->pdo->prepare("SELECT * FROM students WHERE student_uid = ?");
      $stmt->execute([$studentUid]);
      $student = $stmt->fetch();

      return $student ?: null;
    } catch (PDOException $e) {
      error_log('Failed to fetch student by UID: ' . $e->getMessage());
      return null;
    }
  }

  /**
   * Check if email exists in students table
   */
  public function emailExists(string $email, ?int $excludeId = null): bool
  {
    try {
      if ($excludeId !== null) {
        $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) FROM students 
                    WHERE email = ? AND id != ?
                ");
        $stmt->execute([trim($email), $excludeId]);
      } else {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM students WHERE email = ?");
        $stmt->execute([trim($email)]);
      }

      return (int) $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
      error_log('Failed to check student email: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Create a new student with user account using transaction
   */
  public function create(array $data): ?int
  {
    $this->pdo->beginTransaction();

    try {
      // Step 1: Create user account
      $userModel = new User($this->pdo);
      $fullName = trim($data['first_name']) . ' ' . trim($data['last_name']);

      $userId = $userModel->createStudentUser([
        'name' => $fullName,
        'email' => $data['email'],
        'password' => $data['password']
      ]);

      if (!$userId) {
        $this->pdo->rollBack();
        return null;
      }

      // Step 2: Generate unique student UID
      $studentUid = $this->generateUniqueUid();

      // Step 3: Insert student record
      $stmt = $this->pdo->prepare("
                INSERT INTO students (
                    user_id, student_uid, first_name, last_name, email, password,
                    gender, date_of_birth, class_id, admission_date, address,
                    status, photo_url, created_at, updated_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW()
                )
            ");

      $result = $stmt->execute([
        $userId,
        $studentUid,
        trim($data['first_name']),
        trim($data['last_name']),
        trim($data['email']),
        password_hash($data['password'], PASSWORD_DEFAULT), // Also store in students table as per existing schema
        $data['gender'],
        $data['date_of_birth'],
        !empty($data['class_id']) ? (int) $data['class_id'] : null,
        $data['admission_date'],
        $data['address'] ?? null,
        $data['status'] ?? 'Active',
        $data['photo_url'] ?? null
      ]);

      if (!$result) {
        $this->pdo->rollBack();
        return null;
      }

      $studentId = (int) $this->pdo->lastInsertId();
      $this->pdo->commit();

      return $studentId;
    } catch (PDOException $e) {
      $this->pdo->rollBack();
      error_log('Failed to create student: ' . $e->getMessage());
      return null;
    }
  }

  /**
   * Update a student
   */
  public function update(int $id, array $data): bool
  {
    $this->pdo->beginTransaction();

    try {
      // Get current student data
      $student = $this->getById($id);
      if (!$student) {
        $this->pdo->rollBack();
        return false;
      }

      // Update student record
      $fields = [];
      $values = [];

      $allowedFields = [
        'first_name',
        'last_name',
        'email',
        'gender',
        'date_of_birth',
        'class_id',
        'admission_date',
        'address',
        'status',
        'photo_url'
      ];

      foreach ($allowedFields as $field) {
        if (isset($data[$field])) {
          if ($field === 'class_id') {
            $fields[] = "{$field} = ?";
            $values[] = !empty($data[$field]) ? (int) $data[$field] : null;
          } else {
            $fields[] = "{$field} = ?";
            $values[] = $data[$field];
          }
        }
      }

      // Update password if provided (both in students and users)
      if (!empty($data['password'])) {
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);
        $fields[] = "password = ?";
        $values[] = $passwordHash;
      }

      $fields[] = 'updated_at = NOW()';
      $values[] = $id;

      $sql = "UPDATE students SET " . implode(', ', $fields) . " WHERE id = ?";
      $stmt = $this->pdo->prepare($sql);
      $stmt->execute($values);

      // Update user account if name or email changed
      if (!empty($student['user_id'])) {
        $userModel = new User($this->pdo);
        $userUpdateData = [];

        if (isset($data['first_name']) || isset($data['last_name'])) {
          $newFirstName = $data['first_name'] ?? $student['first_name'];
          $newLastName = $data['last_name'] ?? $student['last_name'];
          $userUpdateData['name'] = trim($newFirstName) . ' ' . trim($newLastName);
        }

        if (isset($data['email'])) {
          $userUpdateData['email'] = trim($data['email']);
        }

        if (isset($data['status'])) {
          $userUpdateData['status'] = $data['status'] === 'Active' ? 'active' : 'inactive';
        }

        if (!empty($data['password'])) {
          $userUpdateData['password'] = $data['password'];
        }

        if (!empty($userUpdateData)) {
          $userModel->update((int) $student['user_id'], $userUpdateData);
        }
      }

      $this->pdo->commit();
      return true;
    } catch (PDOException $e) {
      $this->pdo->rollBack();
      error_log('Failed to update student: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Delete a student and associated user account
   */
  public function delete(int $id): bool
  {
    $this->pdo->beginTransaction();

    try {
      // Get student data including user_id
      $student = $this->getById($id);
      if (!$student) {
        $this->pdo->rollBack();
        return false;
      }

      // Delete student record first (due to foreign key)
      $stmt = $this->pdo->prepare("DELETE FROM students WHERE id = ?");
      $stmt->execute([$id]);

      // Delete associated user account
      if (!empty($student['user_id'])) {
        $userModel = new User($this->pdo);
        $userModel->delete((int) $student['user_id']);
      }

      $this->pdo->commit();
      return true;
    } catch (PDOException $e) {
      $this->pdo->rollBack();
      error_log('Failed to delete student: ' . $e->getMessage());
      return false;
    }
  }

  /**
   * Search students
   */
  public function search(string $term): array
  {
    return $this->getAll(1, 100, [], $term)['students'] ?? [];
  }

  /**
   * Count total students
   */
  public function count(): int
  {
    try {
      $stmt = $this->pdo->query("SELECT COUNT(*) FROM students");
      return (int) $stmt->fetchColumn();
    } catch (PDOException $e) {
      error_log('Failed to count students: ' . $e->getMessage());
      return 0;
    }
  }

  /**
   * Get all students for export
   */
  public function getAllForExport(array $filters = [], ?string $search = null): array
  {
    try {
      $conditions = ['1=1'];
      $params = [];

      if ($search !== null && trim($search) !== '') {
        $searchTerm = '%' . trim($search) . '%';
        $conditions[] = "(
                    s.first_name LIKE ? OR 
                    s.last_name LIKE ? OR 
                    s.student_uid LIKE ? OR 
                    s.email LIKE ?
                )";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
      }

      if (!empty($filters['class_id'])) {
        $conditions[] = 's.class_id = ?';
        $params[] = (int) $filters['class_id'];
      }

      if (!empty($filters['gender'])) {
        $conditions[] = 's.gender = ?';
        $params[] = $filters['gender'];
      }

      if (!empty($filters['status'])) {
        $conditions[] = 's.status = ?';
        $params[] = $filters['status'];
      }

      $whereClause = implode(' AND ', $conditions);

      $sql = "
                SELECT
                    s.student_uid AS 'Student ID',
                    CONCAT(s.first_name, ' ', s.last_name) AS 'Name',
                    s.gender AS 'Gender',
                    c.name AS 'Class',
                    s.email AS 'Email',
                    s.status AS 'Status',
                    s.admission_date AS 'Admission Date'
                FROM students s
                LEFT JOIN classes c ON c.id = s.class_id
                WHERE {$whereClause}
                ORDER BY s.id DESC
            ";

      $stmt = $this->pdo->prepare($sql);
      $stmt->execute($params);

      return $stmt->fetchAll();
    } catch (PDOException $e) {
      error_log('Failed to export students: ' . $e->getMessage());
      return [];
    }
  }

  /**
   * Generate a unique student UID
   */
  private function generateUniqueUid(): string
  {
    do {
      $randomNum = random_int(10000, 99999);
      $uid = 'STD-' . $randomNum;

      $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM students WHERE student_uid = ?");
      $stmt->execute([$uid]);
      $count = (int) $stmt->fetchColumn();
    } while ($count > 0);

    return $uid;
  }
}