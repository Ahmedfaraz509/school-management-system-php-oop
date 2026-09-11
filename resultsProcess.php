<?php
require_once '../database/connect.php';

class ResultsProcess
{
  private $conn;
  public $id;
  public $student_id;
  public $exam_id;
  public $subject_id;
  public $marks_obtained;
  public $total_marks;
  public $percentage;
  public $grade;
  public $result;
  public $created_at;

  // Constructor with database connection
  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  // Set result data
  public function setData($id, $student_id, $exam_id, $subject_id, $marks_obtained, $total_marks, $percentage, $grade, $result, $created_at)
  {
    $this->id = $id;
    $this->student_id = $student_id;
    $this->exam_id = $exam_id;
    $this->subject_id = $subject_id;
    $this->marks_obtained = $marks_obtained;
    $this->total_marks = $total_marks;
    $this->percentage = $percentage;
    $this->grade = $grade;
    $this->result = $result;
    $this->created_at = $created_at;
  }

  // Calculate grade based on percentage
  private function calculateGrade($percentage)
  {
    if ($percentage >= 90)
      return 'A+';
    if ($percentage >= 80)
      return 'A';
    if ($percentage >= 70)
      return 'B';
    if ($percentage >= 60)
      return 'C';
    if ($percentage >= 50)
      return 'D';
    return 'F';
  }

  // Calculate result based on percentage
  private function calculateResult($percentage)
  {
    return ($percentage >= 50) ? 'Pass' : 'Fail';
  }

  // Insert result
  public function insert()
  {
    try {
      // Validate foreign keys
      if (!$this->validateForeignKeys()) {
        return false;
      }

      // Check if result already exists for this student, exam, and subject
      $checkQuery = "SELECT id FROM results WHERE student_id = ? AND exam_id = ? AND subject_id = ?";
      $checkStmt = $this->conn->prepare($checkQuery);
      $checkStmt->execute([$this->student_id, $this->exam_id, $this->subject_id]);

      if ($checkStmt->rowCount() > 0) {
        error_log("Result already exists for student {$this->student_id}, exam {$this->exam_id}, subject {$this->subject_id}");
        return false;
      }

      // Calculate percentage, grade, and result if not provided
      if ($this->percentage === null || $this->percentage === '') {
        $this->percentage = ($this->marks_obtained / $this->total_marks) * 100;
        $this->percentage = round($this->percentage, 2);
      }

      if (empty($this->grade)) {
        $this->grade = $this->calculateGrade($this->percentage);
      }

      if (empty($this->result)) {
        $this->result = $this->calculateResult($this->percentage);
      }

      $insert_query = "INSERT INTO results (student_id, exam_id, subject_id, marks_obtained, total_marks, percentage, grade, result, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

      $stmt = $this->conn->prepare($insert_query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([
        $this->student_id,
        $this->exam_id,
        $this->subject_id,
        $this->marks_obtained,
        $this->total_marks,
        $this->percentage,
        $this->grade,
        $this->result,
        $this->created_at
      ]);

      if (!$result) {
        error_log("Execute failed: " . print_r($stmt->errorInfo(), true));
        return false;
      }

      return $this->conn->lastInsertId();
    } catch (PDOException $e) {
      error_log("Insert result error: " . $e->getMessage());
      return false;
    }
  }

  // Update result
  public function update()
  {
    try {
      // Validate foreign keys
      if (!$this->validateForeignKeys()) {
        return false;
      }

      // Check if result exists
      $checkExists = $this->conn->prepare("SELECT id FROM results WHERE id = ?");
      $checkExists->execute([$this->id]);
      if (!$checkExists->fetch()) {
        error_log("Result ID {$this->id} not found");
        return false;
      }

      // Calculate percentage, grade, and result if not provided
      if ($this->percentage === null || $this->percentage === '') {
        $this->percentage = ($this->marks_obtained / $this->total_marks) * 100;
        $this->percentage = round($this->percentage, 2);
      }

      if (empty($this->grade)) {
        $this->grade = $this->calculateGrade($this->percentage);
      }

      if (empty($this->result)) {
        $this->result = $this->calculateResult($this->percentage);
      }

      $update_query = "UPDATE results SET 
                            student_id = ?, 
                            exam_id = ?, 
                            subject_id = ?, 
                            marks_obtained = ?, 
                            total_marks = ?, 
                            percentage = ?, 
                            grade = ?, 
                            result = ? 
                            WHERE id = ?";

      $stmt = $this->conn->prepare($update_query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([
        $this->student_id,
        $this->exam_id,
        $this->subject_id,
        $this->marks_obtained,
        $this->total_marks,
        $this->percentage,
        $this->grade,
        $this->result,
        $this->id
      ]);

      if (!$result) {
        error_log("Execute failed: " . print_r($stmt->errorInfo(), true));
        return false;
      }

      return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Update result error: " . $e->getMessage());
      return false;
    }
  }

  // Delete result
  public function delete($id)
  {
    try {
      $delete_query = "DELETE FROM results WHERE id = ?";
      $stmt = $this->conn->prepare($delete_query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([$id]);
      return $result && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Delete result error: " . $e->getMessage());
      return false;
    }
  }

  // Validate foreign keys
  private function validateForeignKeys()
  {
    try {
      // Check student exists
      $checkStudent = $this->conn->prepare("SELECT id FROM students WHERE id = ? AND status = 'Active'");
      $checkStudent->execute([$this->student_id]);
      if (!$checkStudent->fetch()) {
        error_log("Student ID {$this->student_id} not found or inactive");
        return false;
      }

      // Check exam exists
      $checkExam = $this->conn->prepare("SELECT id FROM exams WHERE id = ?");
      $checkExam->execute([$this->exam_id]);
      if (!$checkExam->fetch()) {
        error_log("Exam ID {$this->exam_id} not found");
        return false;
      }

      // Check subject exists
      $checkSubject = $this->conn->prepare("SELECT id FROM subjects WHERE id = ? AND status = 'Active'");
      $checkSubject->execute([$this->subject_id]);
      if (!$checkSubject->fetch()) {
        error_log("Subject ID {$this->subject_id} not found or inactive");
        return false;
      }

      return true;
    } catch (PDOException $e) {
      error_log("Validation error: " . $e->getMessage());
      return false;
    }
  }

  // Get results by student ID
  public function getResultsByStudentId($student_id)
  {
    try {
      $query = "SELECT r.*, 
                      s.subject_title,
                      e.exam_title,
                      CONCAT(st.first_name, ' ', st.last_name) as student_name
                      FROM results r
                      LEFT JOIN subjects s ON r.subject_id = s.id
                      LEFT JOIN exams e ON r.exam_id = e.id
                      LEFT JOIN students st ON r.student_id = st.id
                      WHERE r.student_id = ?
                      ORDER BY r.created_at DESC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$student_id]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get results by student error: " . $e->getMessage());
      return [];
    }
  }

  // Get result by ID
  public function getResultById($id)
  {
    try {
      $query = "SELECT r.*, 
                      s.subject_title,
                      e.exam_title,
                      CONCAT(st.first_name, ' ', st.last_name) as student_name,
                      st.student_uid
                      FROM results r
                      LEFT JOIN subjects s ON r.subject_id = s.id
                      LEFT JOIN exams e ON r.exam_id = e.id
                      LEFT JOIN students st ON r.student_id = st.id
                      WHERE r.id = ?";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$id]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get result by ID error: " . $e->getMessage());
      return null;
    }
  }

  // Get all results with joins
  public function getAllResults()
  {
    try {
      $query = "SELECT r.*, 
                      s.subject_title,
                      s.subject_code,
                      e.exam_title,
                      e.exam_date,
                      CONCAT(st.first_name, ' ', st.last_name) as student_name,
                      st.student_uid,
                      c.name as class_name,
                      c.grade as class_grade
                      FROM results r
                      LEFT JOIN subjects s ON r.subject_id = s.id
                      LEFT JOIN exams e ON r.exam_id = e.id
                      LEFT JOIN students st ON r.student_id = st.id
                      LEFT JOIN classes c ON st.class_id = c.id
                      ORDER BY r.created_at DESC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get all results error: " . $e->getMessage());
      return [];
    }
  }

  // Get results by exam
  public function getResultsByExam($exam_id)
  {
    try {
      $query = "SELECT r.*, 
                      s.subject_title,
                      CONCAT(st.first_name, ' ', st.last_name) as student_name,
                      st.student_uid
                      FROM results r
                      LEFT JOIN subjects s ON r.subject_id = s.id
                      LEFT JOIN students st ON r.student_id = st.id
                      WHERE r.exam_id = ?
                      ORDER BY r.marks_obtained DESC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$exam_id]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get results by exam error: " . $e->getMessage());
      return [];
    }
  }

  // Get results by subject
  public function getResultsBySubject($subject_id)
  {
    try {
      $query = "SELECT r.*, 
                      CONCAT(st.first_name, ' ', st.last_name) as student_name,
                      e.exam_title
                      FROM results r
                      LEFT JOIN students st ON r.student_id = st.id
                      LEFT JOIN exams e ON r.exam_id = e.id
                      WHERE r.subject_id = ?
                      ORDER BY r.marks_obtained DESC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$subject_id]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get results by subject error: " . $e->getMessage());
      return [];
    }
  }

  // Count total results
  public function countResults()
  {
    try {
      $query = "SELECT COUNT(*) as total FROM results";
      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      return $result['total'] ?? 0;
    } catch (PDOException $e) {
      error_log("Count results error: " . $e->getMessage());
      return 0;
    }
  }

  // Get result statistics
  public function getResultStats()
  {
    try {
      $query = "SELECT 
                      COUNT(*) as total,
                      SUM(CASE WHEN result = 'Pass' THEN 1 ELSE 0 END) as passed,
                      SUM(CASE WHEN result = 'Fail' THEN 1 ELSE 0 END) as failed,
                      AVG(percentage) as avg_percentage,
                      MAX(percentage) as max_percentage,
                      MIN(percentage) as min_percentage
                      FROM results";

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get result stats error: " . $e->getMessage());
      return ['total' => 0, 'passed' => 0, 'failed' => 0, 'avg_percentage' => 0, 'max_percentage' => 0, 'min_percentage' => 0];
    }
  }

  // Get students without results for an exam
  public function getStudentsWithoutResults($exam_id, $class_id = null)
  {
    try {
      $query = "SELECT s.id, CONCAT(s.first_name, ' ', s.last_name) as student_name, s.student_uid
                      FROM students s
                      WHERE s.status = 'Active'";

      $params = [];

      if ($class_id) {
        $query .= " AND s.class_id = ?";
        $params[] = $class_id;
      }

      $query .= " AND s.id NOT IN (
                          SELECT student_id FROM results WHERE exam_id = ?
                      ) ORDER BY s.first_name";

      $params[] = $exam_id;

      $stmt = $this->conn->prepare($query);
      $stmt->execute($params);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get students without results error: " . $e->getMessage());
      return [];
    }
  }
}
?>