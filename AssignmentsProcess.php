<?php
require_once '../database/connect.php';

class AssignmentsProcess
{
  private $conn;
  public $id;
  public $teacher_id;
  public $subject_id;
  public $class_id;
  public $section_id;
  public $title;
  public $description;
  public $assigned_date;
  public $due_date;
  public $attachment;
  public $status;
  public $created_at;
  public $updated_at;

  // Constructor with database connection
  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  // Set assignment data
  public function setData($id, $teacher_id, $subject_id, $class_id, $section_id, $title, $description, $assigned_date, $due_date, $attachment, $status, $created_at, $updated_at)
  {
    $this->id = $id;
    $this->teacher_id = $teacher_id;
    $this->subject_id = $subject_id;
    $this->class_id = $class_id;
    $this->section_id = $section_id;
    $this->title = $title;
    $this->description = $description;
    $this->assigned_date = $assigned_date;
    $this->due_date = $due_date;
    $this->attachment = $attachment;
    $this->status = $status;
    $this->created_at = $created_at;
    $this->updated_at = $updated_at;
  }

  // Insert assignment
  public function insert()
  {
    try {
      // Debug: Log the data being inserted
      error_log("Inserting assignment with data: " . print_r([
        'teacher_id' => $this->teacher_id,
        'subject_id' => $this->subject_id,
        'class_id' => $this->class_id,
        'section_id' => $this->section_id,
        'title' => $this->title,
        'status' => $this->status
      ], true));

      // Check if foreign keys exist
      if (!$this->validateForeignKeys()) {
        return false;
      }

      // Handle empty section_id (set to NULL)
      $section_id = !empty($this->section_id) ? $this->section_id : null;

      $insert_query = "INSERT INTO assignments (
                teacher_id, 
                subject_id, 
                class_id, 
                section_id, 
                title, 
                description, 
                assigned_date, 
                due_date, 
                attachment, 
                status, 
                created_at, 
                updated_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

      $stmt = $this->conn->prepare($insert_query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([
        $this->teacher_id,
        $this->subject_id,
        $this->class_id,
        $section_id,
        $this->title,
        $this->description,
        $this->assigned_date,
        $this->due_date,
        $this->attachment,
        $this->status,
        $this->created_at,
        $this->updated_at
      ]);

      if (!$result) {
        error_log("Execute failed: " . print_r($stmt->errorInfo(), true));
        return false;
      }

      return $this->conn->lastInsertId();
    } catch (PDOException $e) {
      error_log("Insert assignment error: " . $e->getMessage());
      error_log("Error Code: " . $e->getCode());
      return false;
    }
  }

  // Validate foreign keys exist
  private function validateForeignKeys()
  {
    try {
      // Check teacher exists
      $checkTeacher = $this->conn->prepare("SELECT id FROM teachers WHERE id = ? AND status = 'active'");
      $checkTeacher->execute([$this->teacher_id]);
      if (!$checkTeacher->fetch()) {
        error_log("Teacher ID {$this->teacher_id} not found or inactive");
        return false;
      }

      // Check subject exists
      $checkSubject = $this->conn->prepare("SELECT id FROM subjects WHERE id = ? AND status = 'Active'");
      $checkSubject->execute([$this->subject_id]);
      if (!$checkSubject->fetch()) {
        error_log("Subject ID {$this->subject_id} not found or inactive");
        return false;
      }

      // Check class exists
      $checkClass = $this->conn->prepare("SELECT id FROM classes WHERE id = ? AND status = 'active'");
      $checkClass->execute([$this->class_id]);
      if (!$checkClass->fetch()) {
        error_log("Class ID {$this->class_id} not found or inactive");
        return false;
      }

      // Check section exists (if provided)
      if (!empty($this->section_id)) {
        $checkSection = $this->conn->prepare("SELECT id FROM sections WHERE id = ? AND status = 'active'");
        $checkSection->execute([$this->section_id]);
        if (!$checkSection->fetch()) {
          error_log("Section ID {$this->section_id} not found or inactive");
          return false;
        }
      }

      return true;
    } catch (PDOException $e) {
      error_log("Validation error: " . $e->getMessage());
      return false;
    }
  }

  // Update assignment
  public function update()
  {
    try {
      // Debug: Log the data being updated
      error_log("Updating assignment with data: " . print_r([
        'id' => $this->id,
        'teacher_id' => $this->teacher_id,
        'subject_id' => $this->subject_id,
        'class_id' => $this->class_id,
        'section_id' => $this->section_id,
        'title' => $this->title,
        'status' => $this->status
      ], true));

      // Check if assignment exists
      $checkExists = $this->conn->prepare("SELECT id FROM assignments WHERE id = ?");
      $checkExists->execute([$this->id]);
      if (!$checkExists->fetch()) {
        error_log("Assignment ID {$this->id} not found");
        return false;
      }

      // Validate foreign keys
      if (!$this->validateForeignKeys()) {
        return false;
      }

      // Handle empty section_id
      $section_id = !empty($this->section_id) ? $this->section_id : null;

      $update_query = "UPDATE assignments SET 
                            teacher_id = ?, 
                            subject_id = ?, 
                            class_id = ?, 
                            section_id = ?, 
                            title = ?, 
                            description = ?, 
                            assigned_date = ?, 
                            due_date = ?, 
                            attachment = ?, 
                            status = ?, 
                            updated_at = ? 
                            WHERE id = ?";

      $stmt = $this->conn->prepare($update_query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([
        $this->teacher_id,
        $this->subject_id,
        $this->class_id,
        $section_id,
        $this->title,
        $this->description,
        $this->assigned_date,
        $this->due_date,
        $this->attachment,
        $this->status,
        $this->updated_at,
        $this->id
      ]);

      if (!$result) {
        error_log("Execute failed: " . print_r($stmt->errorInfo(), true));
        return false;
      }

      return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Update assignment error: " . $e->getMessage());
      error_log("Error Code: " . $e->getCode());
      return false;
    }
  }

  // Delete assignment
  public function delete($id)
  {
    try {
      $delete_query = "DELETE FROM assignments WHERE id = ?";
      $stmt = $this->conn->prepare($delete_query);
      $result = $stmt->execute([$id]);
      return $result && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Delete assignment error: " . $e->getMessage());
      return false;
    }
  }

  // Get all assignments with joins
  public function getAllAssignments()
  {
    try {
      $query = "SELECT a.*, 
                      t.full_name as teacher_name,
                      s.subject_title,
                      c.name as class_name,
                      c.grade as class_grade,
                      sec.name as section_name
                      FROM assignments a
                      LEFT JOIN teachers t ON a.teacher_id = t.id
                      LEFT JOIN subjects s ON a.subject_id = s.id
                      LEFT JOIN classes c ON a.class_id = c.id
                      LEFT JOIN sections sec ON a.section_id = sec.id
                      ORDER BY a.created_at DESC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get all assignments error: " . $e->getMessage());
      return [];
    }
  }

  // Get assignment by ID
  public function getAssignmentById($id)
  {
    try {
      $query = "SELECT a.*, 
                      t.full_name as teacher_name,
                      s.subject_title,
                      c.name as class_name,
                      sec.name as section_name
                      FROM assignments a
                      LEFT JOIN teachers t ON a.teacher_id = t.id
                      LEFT JOIN subjects s ON a.subject_id = s.id
                      LEFT JOIN classes c ON a.class_id = c.id
                      LEFT JOIN sections sec ON a.section_id = sec.id
                      WHERE a.id = ?";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$id]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get assignment by ID error: " . $e->getMessage());
      return null;
    }
  }

  // Count total assignments
  public function countAssignments()
  {
    try {
      $query = "SELECT COUNT(*) as total FROM assignments";
      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      return $result['total'] ?? 0;
    } catch (PDOException $e) {
      error_log("Count assignments error: " . $e->getMessage());
      return 0;
    }
  }
}
?>