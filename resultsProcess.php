<?php
// ResultsProcess.php
require_once '../database/connect.php';

class ResultsProcess
{
  private $conn;

  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  /**
   * Get results with filters
   */
  public function getResults($teacher_id, $filters = [])
  {
    $query = "
            SELECT 
                r.id,
                r.student_id,
                r.exam_id,
                r.subject_id,
                r.marks_obtained,
                r.total_marks,
                r.percentage,
                r.grade,
                r.result,
                r.created_at,
                s.first_name,
                s.last_name,
                s.student_uid,
                s.gender,
                sub.subject_title,
                sub.subject_code,
                c.id as class_id,
                c.name as class_name,
                c.grade as class_grade,
                e.exam_title,
                e.exam_date
            FROM results r
            LEFT JOIN students s ON r.student_id = s.id
            LEFT JOIN subjects sub ON r.subject_id = sub.id
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN exams e ON r.exam_id = e.id
            WHERE 1=1
        ";

    $params = [];

    // Teacher filter
    $query .= " AND (sub.teacher_id = :teacher_id OR c.teacher_id = :teacher_id)";
    $params[':teacher_id'] = $teacher_id;

    // Class filter
    if (!empty($filters['class_id'])) {
      $query .= " AND c.id = :class_id";
      $params[':class_id'] = $filters['class_id'];
    }

    // Subject filter
    if (!empty($filters['subject_id'])) {
      $query .= " AND sub.id = :subject_id";
      $params[':subject_id'] = $filters['subject_id'];
    }

    // Exam filter
    if (!empty($filters['exam_id'])) {
      $query .= " AND e.id = :exam_id";
      $params[':exam_id'] = $filters['exam_id'];
    }

    // Student search
    if (!empty($filters['search'])) {
      $query .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.student_uid LIKE :search)";
      $params[':search'] = '%' . $filters['search'] . '%';
    }

    // Result filter
    if (!empty($filters['result'])) {
      $query .= " AND r.result = :result";
      $params[':result'] = $filters['result'];
    }

    $query .= " ORDER BY s.first_name ASC";

    $stmt = $this->conn->prepare($query);
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get result by ID
   */
  public function getResultById($result_id)
  {
    $query = "
            SELECT 
                r.*,
                s.first_name,
                s.last_name,
                s.student_uid,
                sub.subject_title,
                sub.subject_code,
                e.exam_title,
                e.exam_date
            FROM results r
            LEFT JOIN students s ON r.student_id = s.id
            LEFT JOIN subjects sub ON r.subject_id = sub.id
            LEFT JOIN exams e ON r.exam_id = e.id
            WHERE r.id = :id
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':id', $result_id);
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Add or update a result
   */
  public function saveResult($data)
  {
    // Calculate percentage and grade
    $percentage = round(($data['marks_obtained'] / $data['total_marks']) * 100, 2);
    $grade = $this->calculateGrade($percentage);
    $result_status = $percentage >= 40 ? 'Pass' : 'Fail';

    // Check if result already exists
    $check_stmt = $this->conn->prepare("
            SELECT id FROM results 
            WHERE student_id = :student_id AND exam_id = :exam_id AND subject_id = :subject_id
        ");
    $check_stmt->bindValue(':student_id', $data['student_id']);
    $check_stmt->bindValue(':exam_id', $data['exam_id']);
    $check_stmt->bindValue(':subject_id', $data['subject_id']);
    $check_stmt->execute();

    if ($check_stmt->rowCount() > 0) {
      // Update existing result
      $query = "
                UPDATE results 
                SET marks_obtained = :marks_obtained, 
                    total_marks = :total_marks,
                    percentage = :percentage,
                    grade = :grade,
                    result = :result
                WHERE student_id = :student_id AND exam_id = :exam_id AND subject_id = :subject_id
            ";
    } else {
      // Insert new result
      $query = "
                INSERT INTO results (student_id, exam_id, subject_id, marks_obtained, total_marks, percentage, grade, result)
                VALUES (:student_id, :exam_id, :subject_id, :marks_obtained, :total_marks, :percentage, :grade, :result)
            ";
    }

    try {
      $stmt = $this->conn->prepare($query);
      $stmt->bindValue(':student_id', $data['student_id']);
      $stmt->bindValue(':exam_id', $data['exam_id']);
      $stmt->bindValue(':subject_id', $data['subject_id']);
      $stmt->bindValue(':marks_obtained', $data['marks_obtained']);
      $stmt->bindValue(':total_marks', $data['total_marks']);
      $stmt->bindValue(':percentage', $percentage);
      $stmt->bindValue(':grade', $grade);
      $stmt->bindValue(':result', $result_status);

      if ($stmt->execute()) {
        return [
          'success' => true,
          'message' => 'Result saved successfully',
          'percentage' => $percentage,
          'grade' => $grade,
          'result' => $result_status
        ];
      } else {
        return ['success' => false, 'message' => 'Failed to save result'];
      }
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Delete a result
   */
  public function deleteResult($result_id)
  {
    $query = "DELETE FROM results WHERE id = :id";

    try {
      $stmt = $this->conn->prepare($query);
      $stmt->bindValue(':id', $result_id);

      if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Result deleted successfully'];
      } else {
        return ['success' => false, 'message' => 'Failed to delete result'];
      }
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Calculate grade based on percentage
   */
  public function calculateGrade($percentage)
  {
    if ($percentage >= 90)
      return 'A+';
    if ($percentage >= 80)
      return 'A';
    if ($percentage >= 70)
      return 'B+';
    if ($percentage >= 60)
      return 'B';
    if ($percentage >= 50)
      return 'C+';
    if ($percentage >= 40)
      return 'C';
    if ($percentage >= 33)
      return 'D';
    return 'F';
  }

  /**
   * Get result statistics
   */
  public function getResultStats($teacher_id, $filters = [])
  {
    $query = "
            SELECT 
                COUNT(*) as total_students,
                SUM(CASE WHEN r.result = 'Pass' THEN 1 ELSE 0 END) as passed,
                SUM(CASE WHEN r.result = 'Fail' THEN 1 ELSE 0 END) as failed,
                AVG(r.percentage) as average_percentage,
                MAX(r.percentage) as highest_percentage,
                MIN(r.percentage) as lowest_percentage
            FROM results r
            LEFT JOIN students s ON r.student_id = s.id
            LEFT JOIN subjects sub ON r.subject_id = sub.id
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN exams e ON r.exam_id = e.id
            WHERE 1=1
        ";

    $params = [];

    // Teacher filter
    $query .= " AND (sub.teacher_id = :teacher_id OR c.teacher_id = :teacher_id)";
    $params[':teacher_id'] = $teacher_id;

    // Class filter
    if (!empty($filters['class_id'])) {
      $query .= " AND c.id = :class_id";
      $params[':class_id'] = $filters['class_id'];
    }

    // Subject filter
    if (!empty($filters['subject_id'])) {
      $query .= " AND sub.id = :subject_id";
      $params[':subject_id'] = $filters['subject_id'];
    }

    // Exam filter
    if (!empty($filters['exam_id'])) {
      $query .= " AND e.id = :exam_id";
      $params[':exam_id'] = $filters['exam_id'];
    }

    $stmt = $this->conn->prepare($query);
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $result = $stmt->fetch();
    return [
      'total_students' => (int) ($result['total_students'] ?? 0),
      'passed' => (int) ($result['passed'] ?? 0),
      'failed' => (int) ($result['failed'] ?? 0),
      'average_percentage' => round($result['average_percentage'] ?? 0, 2),
      'highest_percentage' => round($result['highest_percentage'] ?? 0, 2),
      'lowest_percentage' => round($result['lowest_percentage'] ?? 0, 2)
    ];
  }

  /**
   * Get grade distribution
   */
  public function getGradeDistribution($teacher_id, $filters = [])
  {
    $query = "
            SELECT 
                r.grade,
                COUNT(*) as count
            FROM results r
            LEFT JOIN students s ON r.student_id = s.id
            LEFT JOIN subjects sub ON r.subject_id = sub.id
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE (sub.teacher_id = :teacher_id OR c.teacher_id = :teacher_id)
        ";

    $params = [':teacher_id' => $teacher_id];

    if (!empty($filters['class_id'])) {
      $query .= " AND c.id = :class_id";
      $params[':class_id'] = $filters['class_id'];
    }

    if (!empty($filters['subject_id'])) {
      $query .= " AND sub.id = :subject_id";
      $params[':subject_id'] = $filters['subject_id'];
    }

    if (!empty($filters['exam_id'])) {
      $query .= " AND r.exam_id = :exam_id";
      $params[':exam_id'] = $filters['exam_id'];
    }

    $query .= " GROUP BY r.grade ORDER BY FIELD(r.grade, 'A+', 'A', 'B+', 'B', 'C+', 'C', 'D', 'F')";

    $stmt = $this->conn->prepare($query);
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get top performers
   */
  public function getTopPerformers($teacher_id, $limit = 10, $filters = [])
  {
    $query = "
            SELECT 
                s.first_name,
                s.last_name,
                s.student_uid,
                r.marks_obtained,
                r.total_marks,
                r.percentage,
                r.grade,
                sub.subject_title,
                e.exam_title
            FROM results r
            LEFT JOIN students s ON r.student_id = s.id
            LEFT JOIN subjects sub ON r.subject_id = sub.id
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN exams e ON r.exam_id = e.id
            WHERE (sub.teacher_id = :teacher_id OR c.teacher_id = :teacher_id)
                AND r.result = 'Pass'
        ";

    $params = [':teacher_id' => $teacher_id];

    if (!empty($filters['class_id'])) {
      $query .= " AND c.id = :class_id";
      $params[':class_id'] = $filters['class_id'];
    }

    if (!empty($filters['subject_id'])) {
      $query .= " AND sub.id = :subject_id";
      $params[':subject_id'] = $filters['subject_id'];
    }

    if (!empty($filters['exam_id'])) {
      $query .= " AND r.exam_id = :exam_id";
      $params[':exam_id'] = $filters['exam_id'];
    }

    $query .= " ORDER BY r.percentage DESC LIMIT :limit";

    $stmt = $this->conn->prepare($query);
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get student result summary
   */
  public function getStudentResultSummary($student_id)
  {
    $query = "
            SELECT 
                COUNT(*) as total_exams,
                SUM(CASE WHEN result = 'Pass' THEN 1 ELSE 0 END) as passed,
                SUM(CASE WHEN result = 'Fail' THEN 1 ELSE 0 END) as failed,
                AVG(percentage) as average_percentage,
                MAX(percentage) as highest_percentage,
                MIN(percentage) as lowest_percentage
            FROM results
            WHERE student_id = :student_id
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Get all exams with results for dropdown
   */
  public function getExamsWithResults($teacher_id)
  {
    $query = "
            SELECT DISTINCT e.id, e.exam_title, e.exam_date
            FROM exams e
            LEFT JOIN results r ON e.id = r.exam_id
            LEFT JOIN subjects s ON e.subject_id = s.id
            LEFT JOIN classes c ON e.class_id = c.id
            WHERE (s.teacher_id = :teacher_id OR c.teacher_id = :teacher_id)
            ORDER BY e.exam_date DESC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }
}

// Usage example:
/*
$resultProcess = new ResultsProcess($conn);

// Get results with filters
$results = $resultProcess->getResults($teacher_id, [
    'class_id' => 1,
    'subject_id' => 1,
    'exam_id' => 1
]);

// Get result stats
$stats = $resultProcess->getResultStats($teacher_id, [
    'class_id' => 1,
    'subject_id' => 1
]);

// Save a new result
$result = $resultProcess->saveResult([
    'student_id' => 1,
    'exam_id' => 1,
    'subject_id' => 1,
    'marks_obtained' => 85,
    'total_marks' => 100
]);

// Get top performers
$top = $resultProcess->getTopPerformers($teacher_id, 10, ['class_id' => 1]);

// Get grade distribution
$grades = $resultProcess->getGradeDistribution($teacher_id, ['class_id' => 1]);
*/
?>