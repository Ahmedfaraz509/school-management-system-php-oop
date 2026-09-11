<?php
require_once '../database/connect.php';

class ExamsProcess
{
  private $conn;
  public $id;
  public $exam_title;
  public $subject_id;
  public $class_id;
  public $exam_date;
  public $start_time;
  public $end_time;
  public $room;
  public $status;
  public $created_at;

  // Constructor with database connection
  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  // Set exam data
  public function setData($id, $exam_title, $subject_id, $class_id, $exam_date, $start_time, $end_time, $room, $status, $created_at)
  {
    $this->id = $id;
    $this->exam_title = $exam_title;
    $this->subject_id = $subject_id;
    $this->class_id = $class_id;
    $this->exam_date = $exam_date;
    $this->start_time = $start_time;
    $this->end_time = $end_time;
    $this->room = $room;
    $this->status = $status;
    $this->created_at = $created_at;
  }

  // Insert exam
  public function insert()
  {
    try {
      // Validate foreign keys
      if (!$this->validateForeignKeys()) {
        return false;
      }

      $insert_query = "INSERT INTO exams (exam_title, subject_id, class_id, exam_date, start_time, end_time, room, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

      $stmt = $this->conn->prepare($insert_query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([
        $this->exam_title,
        $this->subject_id,
        $this->class_id,
        $this->exam_date,
        $this->start_time,
        $this->end_time,
        $this->room,
        $this->status,
        $this->created_at
      ]);

      if (!$result) {
        error_log("Execute failed: " . print_r($stmt->errorInfo(), true));
        return false;
      }

      return $this->conn->lastInsertId();
    } catch (PDOException $e) {
      error_log("Insert exam error: " . $e->getMessage());
      return false;
    }
  }

  // Update exam
  public function update()
  {
    try {
      // Validate foreign keys
      if (!$this->validateForeignKeys()) {
        return false;
      }

      $update_query = "UPDATE exams SET 
                            exam_title = ?, 
                            subject_id = ?, 
                            class_id = ?, 
                            exam_date = ?, 
                            start_time = ?, 
                            end_time = ?, 
                            room = ?, 
                            status = ? 
                            WHERE id = ?";

      $stmt = $this->conn->prepare($update_query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([
        $this->exam_title,
        $this->subject_id,
        $this->class_id,
        $this->exam_date,
        $this->start_time,
        $this->end_time,
        $this->room,
        $this->status,
        $this->id
      ]);

      if (!$result) {
        error_log("Execute failed: " . print_r($stmt->errorInfo(), true));
        return false;
      }

      return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Update exam error: " . $e->getMessage());
      return false;
    }
  }

  // Delete exam
  public function delete($id)
  {
    try {
      $delete_query = "DELETE FROM exams WHERE id = ?";
      $stmt = $this->conn->prepare($delete_query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([$id]);
      return $result && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Delete exam error: " . $e->getMessage());
      return false;
    }
  }

  // Validate foreign keys
  private function validateForeignKeys()
  {
    try {
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

      return true;
    } catch (PDOException $e) {
      error_log("Validation error: " . $e->getMessage());
      return false;
    }
  }

  // Get all exams with joins
  public function getAllExams()
  {
    try {
      $query = "SELECT e.*, 
                      s.subject_title,
                      s.subject_code,
                      c.name as class_name,
                      c.grade as class_grade
                      FROM exams e
                      LEFT JOIN subjects s ON e.subject_id = s.id
                      LEFT JOIN classes c ON e.class_id = c.id
                      ORDER BY e.exam_date ASC, e.start_time ASC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get all exams error: " . $e->getMessage());
      return [];
    }
  }

  // Get exam by ID
  public function getExamById($id)
  {
    try {
      $query = "SELECT e.*, 
                      s.subject_title,
                      c.name as class_name
                      FROM exams e
                      LEFT JOIN subjects s ON e.subject_id = s.id
                      LEFT JOIN classes c ON e.class_id = c.id
                      WHERE e.id = ?";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$id]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get exam by ID error: " . $e->getMessage());
      return null;
    }
  }

  // Get exams by class
  public function getExamsByClass($class_id)
  {
    try {
      $query = "SELECT e.*, 
                      s.subject_title
                      FROM exams e
                      LEFT JOIN subjects s ON e.subject_id = s.id
                      WHERE e.class_id = ?
                      ORDER BY e.exam_date ASC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$class_id]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get exams by class error: " . $e->getMessage());
      return [];
    }
  }

  // Get exams by subject
  public function getExamsBySubject($subject_id)
  {
    try {
      $query = "SELECT e.*, 
                      c.name as class_name
                      FROM exams e
                      LEFT JOIN classes c ON e.class_id = c.id
                      WHERE e.subject_id = ?
                      ORDER BY e.exam_date ASC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$subject_id]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get exams by subject error: " . $e->getMessage());
      return [];
    }
  }

  // Get exams by status
  public function getExamsByStatus($status)
  {
    try {
      $query = "SELECT e.*, 
                      s.subject_title,
                      c.name as class_name
                      FROM exams e
                      LEFT JOIN subjects s ON e.subject_id = s.id
                      LEFT JOIN classes c ON e.class_id = c.id
                      WHERE e.status = ?
                      ORDER BY e.exam_date ASC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$status]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get exams by status error: " . $e->getMessage());
      return [];
    }
  }

  // Count total exams
  public function countExams()
  {
    try {
      $query = "SELECT COUNT(*) as total FROM exams";
      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      return $result['total'] ?? 0;
    } catch (PDOException $e) {
      error_log("Count exams error: " . $e->getMessage());
      return 0;
    }
  }

  // Get exam statistics
  public function getExamStats()
  {
    try {
      $query = "SELECT 
                      COUNT(*) as total,
                      SUM(CASE WHEN status = 'Scheduled' THEN 1 ELSE 0 END) as scheduled,
                      SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END) as ongoing,
                      SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
                      SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled
                      FROM exams";

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get exam stats error: " . $e->getMessage());
      return ['total' => 0, 'scheduled' => 0, 'ongoing' => 0, 'completed' => 0, 'cancelled' => 0];
    }
  }

  // Check for scheduling conflicts
  public function checkConflict($class_id, $exam_date, $start_time, $end_time, $exclude_id = null)
  {
    try {
      $query = "SELECT COUNT(*) as count FROM exams 
                      WHERE class_id = ? AND exam_date = ? 
                      AND ((start_time <= ? AND end_time > ?) OR (start_time < ? AND end_time >= ?))
                      AND status != 'Cancelled'";

      $params = [$class_id, $exam_date, $end_time, $start_time, $end_time, $start_time];

      if ($exclude_id) {
        $query .= " AND id != ?";
        $params[] = $exclude_id;
      }

      $stmt = $this->conn->prepare($query);
      $stmt->execute($params);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      return $result['count'] > 0;
    } catch (PDOException $e) {
      error_log("Check conflict error: " . $e->getMessage());
      return false;
    }
  }

  // Search exams
  public function searchExams($keyword)
  {
    try {
      $search_query = "SELECT e.*, 
                            s.subject_title,
                            c.name as class_name
                            FROM exams e
                            LEFT JOIN subjects s ON e.subject_id = s.id
                            LEFT JOIN classes c ON e.class_id = c.id
                            WHERE e.exam_title LIKE ? 
                            OR s.subject_title LIKE ?
                            OR c.name LIKE ?
                            ORDER BY e.exam_date ASC";

      $search_term = "%{$keyword}%";
      $stmt = $this->conn->prepare($search_query);
      $stmt->execute([$search_term, $search_term, $search_term]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Search exams error: " . $e->getMessage());
      return [];
    }
  }
}
?>