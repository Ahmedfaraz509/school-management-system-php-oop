<?php
require_once '../database/connect.php';

class Subjectprocess
{
  private $conn;
  public $id;
  public $subject_code;
  public $subject_title;
  public $class_id;
  public $teacher_id;
  public $status;
  public $created_at;

  // Constructor with database connection
  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  // Set subject data
  public function setData($id, $subject_code, $subject_title, $class_id, $teacher_id, $status, $created_at)
  {
    $this->id = $id;
    $this->subject_code = $subject_code;
    $this->subject_title = $subject_title;
    $this->class_id = $class_id;
    $this->teacher_id = $teacher_id;
    $this->status = $status;
    $this->created_at = $created_at;
  }

  // Insert subject
  public function insert()
  {
    try {
      $insert_query = "INSERT INTO subjects (subject_code, subject_title, class_id, teacher_id, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?)";

      $stmt = $this->conn->prepare($insert_query);
      $stmt->execute([
        $this->subject_code,
        $this->subject_title,
        $this->class_id,
        $this->teacher_id,
        $this->status,
        $this->created_at
      ]);

      return $this->conn->lastInsertId();
    } catch (PDOException $e) {
      error_log("Insert error: " . $e->getMessage());
      return false;
    }
  }

  // Update subject
  public function update()
  {
    try {
      $update_query = "UPDATE subjects SET 
                            subject_code = ?, 
                            subject_title = ?, 
                            class_id = ?, 
                            teacher_id = ?, 
                            status = ? 
                            WHERE id = ?";

      $stmt = $this->conn->prepare($update_query);
      $stmt->execute([
        $this->subject_code,
        $this->subject_title,
        $this->class_id,
        $this->teacher_id,
        $this->status,
        $this->id
      ]);

      return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Update error: " . $e->getMessage());
      return false;
    }
  }

  // Delete subject
  public function delete()
  {
    try {
      $delete_query = "DELETE FROM subjects WHERE id = ?";
      $stmt = $this->conn->prepare($delete_query);
      $stmt->execute([$this->id]);

      return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Delete error: " . $e->getMessage());
      return false;
    }
  }

  // Get all subjects with joins
  public function getAllSubjects()
  {
    try {
      $select_query = "SELECT 
                            s.*,
                            c.name as class_name,
                            c.grade as class_grade,
                            t.full_name as teacher_name,
                            t.teacher_id as teacher_code
                            FROM subjects s
                            LEFT JOIN classes c ON s.class_id = c.id
                            LEFT JOIN teachers t ON s.teacher_id = t.id
                            ORDER BY s.created_at DESC";

      $stmt = $this->conn->prepare($select_query);
      $stmt->execute();

      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get subjects error: " . $e->getMessage());
      return [];
    }
  }

  // Get single subject by ID
  public function getSubjectById($id)
  {
    try {
      $select_query = "SELECT * FROM subjects WHERE id = ?";
      $stmt = $this->conn->prepare($select_query);
      $stmt->execute([$id]);

      return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get subject error: " . $e->getMessage());
      return null;
    }
  }

  // Get subjects by class
  public function getSubjectsByClass($class_id)
  {
    try {
      $select_query = "SELECT * FROM subjects WHERE class_id = ? ORDER BY subject_title";
      $stmt = $this->conn->prepare($select_query);
      $stmt->execute([$class_id]);

      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get subjects by class error: " . $e->getMessage());
      return [];
    }
  }

  // Get subjects by teacher
  public function getSubjectsByTeacher($teacher_id)
  {
    try {
      $select_query = "SELECT * FROM subjects WHERE teacher_id = ? ORDER BY subject_title";
      $stmt = $this->conn->prepare($select_query);
      $stmt->execute([$teacher_id]);

      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get subjects by teacher error: " . $e->getMessage());
      return [];
    }
  }

  // Count total subjects
  public function countSubjects()
  {
    try {
      $count_query = "SELECT COUNT(*) as total FROM subjects";
      $stmt = $this->conn->prepare($count_query);
      $stmt->execute();

      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      return $result['total'] ?? 0;
    } catch (PDOException $e) {
      error_log("Count subjects error: " . $e->getMessage());
      return 0;
    }
  }

  // Search subjects
  public function searchSubjects($keyword)
  {
    try {
      $search_query = "SELECT 
                            s.*,
                            c.name as class_name,
                            t.full_name as teacher_name
                            FROM subjects s
                            LEFT JOIN classes c ON s.class_id = c.id
                            LEFT JOIN teachers t ON s.teacher_id = t.id
                            WHERE s.subject_code LIKE ? 
                            OR s.subject_title LIKE ? 
                            OR t.full_name LIKE ?
                            ORDER BY s.subject_title";

      $search_term = "%{$keyword}%";
      $stmt = $this->conn->prepare($search_query);
      $stmt->execute([$search_term, $search_term, $search_term]);

      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Search subjects error: " . $e->getMessage());
      return [];
    }
  }
}
?>