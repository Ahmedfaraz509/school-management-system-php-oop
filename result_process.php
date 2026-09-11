<?php
// result_process.php
// Process file for handling result-related database operations

require_once '../database/connect.php';

class ResultProcess
{
  private $conn;
  private $student_id;
  private $student_data;

  public function __construct($conn, $student_id = null)
  {
    $this->conn = $conn;
    $this->student_id = $student_id;
    if ($student_id) {
      $this->loadStudentData();
    }
  }

  /**
   * Load student data including class information
   */
  public function loadStudentData()
  {
    $query = "SELECT s.*, c.name as class_name, c.grade, c.id as class_id 
                  FROM students s 
                  LEFT JOIN classes c ON s.class_id = c.id 
                  WHERE s.id = ?";
    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_id]);
    $this->student_data = $stmt->fetch();
    return $this->student_data;
  }

  /**
   * Get student data
   */
  public function getStudentData()
  {
    return $this->student_data;
  }

  /**
   * Get all results for a student
   */
  public function getStudentResults($limit = null)
  {
    if (!$this->student_data) {
      return [];
    }

    $query = "SELECT r.*, e.exam_title, e.exam_date, 
                  s.subject_title, s.subject_code,
                  CASE 
                      WHEN r.percentage >= 90 THEN 'A+'
                      WHEN r.percentage >= 80 THEN 'A'
                      WHEN r.percentage >= 70 THEN 'B+'
                      WHEN r.percentage >= 60 THEN 'B'
                      WHEN r.percentage >= 50 THEN 'C'
                      WHEN r.percentage >= 40 THEN 'D'
                      ELSE 'F'
                  END as grade,
                  CASE 
                      WHEN r.percentage >= 90 THEN 'p-ok'
                      WHEN r.percentage >= 80 THEN 'p-ok'
                      WHEN r.percentage >= 70 THEN 'p-teal'
                      WHEN r.percentage >= 60 THEN 'p-teal'
                      WHEN r.percentage >= 50 THEN 'p-warn'
                      WHEN r.percentage >= 40 THEN 'p-warn'
                      ELSE 'p-danger'
                  END as grade_color,
                  CASE 
                      WHEN r.percentage >= 40 THEN 'Pass'
                      ELSE 'Fail'
                  END as result_status
                  FROM results r
                  JOIN exams e ON r.exam_id = e.id
                  JOIN subjects s ON r.subject_id = s.id
                  WHERE r.student_id = ?
                  ORDER BY e.exam_date DESC, r.id DESC";

    if ($limit) {
      $query .= " LIMIT " . intval($limit);
    }

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_id]);
    return $stmt->fetchAll();
  }

  /**
   * Get results by exam
   */
  public function getResultsByExam($exam_id)
  {
    $query = "SELECT r.*, s.subject_title, s.subject_code,
                  CASE 
                      WHEN r.percentage >= 90 THEN 'A+'
                      WHEN r.percentage >= 80 THEN 'A'
                      WHEN r.percentage >= 70 THEN 'B+'
                      WHEN r.percentage >= 60 THEN 'B'
                      WHEN r.percentage >= 50 THEN 'C'
                      WHEN r.percentage >= 40 THEN 'D'
                      ELSE 'F'
                  END as grade
                  FROM results r
                  JOIN subjects s ON r.subject_id = s.id
                  WHERE r.student_id = ? AND r.exam_id = ?
                  ORDER BY r.subject_id ASC";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_id, $exam_id]);
    return $stmt->fetchAll();
  }

  /**
   * Get result statistics
   */
  public function getResultStats()
  {
    if (!$this->student_data) {
      return [
        'total_marks' => 0,
        'obtained_marks' => 0,
        'average_percentage' => 0,
        'gpa' => 0,
        'total_exams' => 0,
        'passed_exams' => 0,
        'failed_exams' => 0,
        'best_subject' => 'N/A',
        'worst_subject' => 'N/A'
      ];
    }

    $query = "SELECT 
                    SUM(r.total_marks) as total_marks,
                    SUM(r.marks_obtained) as obtained_marks,
                    AVG(r.percentage) as avg_percentage,
                    COUNT(*) as total_exams,
                    SUM(CASE WHEN r.percentage >= 40 THEN 1 ELSE 0 END) as passed,
                    SUM(CASE WHEN r.percentage < 40 THEN 1 ELSE 0 END) as failed,
                    (SELECT subject_title FROM subjects WHERE id = (
                        SELECT subject_id FROM results 
                        WHERE student_id = ? 
                        ORDER BY percentage DESC LIMIT 1
                    )) as best_subject,
                    (SELECT subject_title FROM subjects WHERE id = (
                        SELECT subject_id FROM results 
                        WHERE student_id = ? 
                        ORDER BY percentage ASC LIMIT 1
                    )) as worst_subject
                    FROM results r
                    WHERE r.student_id = ?";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_id, $this->student_id, $this->student_id]);
    $stats = $stmt->fetch();

    // Calculate GPA (simplified)
    $gpa = 0;
    if ($stats && $stats['avg_percentage']) {
      $avg = $stats['avg_percentage'];
      if ($avg >= 90)
        $gpa = 4.0;
      elseif ($avg >= 80)
        $gpa = 3.7;
      elseif ($avg >= 70)
        $gpa = 3.3;
      elseif ($avg >= 60)
        $gpa = 3.0;
      elseif ($avg >= 50)
        $gpa = 2.0;
      elseif ($avg >= 40)
        $gpa = 1.0;
    }

    return [
      'total_marks' => $stats['total_marks'] ?? 0,
      'obtained_marks' => $stats['obtained_marks'] ?? 0,
      'average_percentage' => round($stats['avg_percentage'] ?? 0, 1),
      'gpa' => $gpa,
      'total_exams' => $stats['total_exams'] ?? 0,
      'passed_exams' => $stats['passed'] ?? 0,
      'failed_exams' => $stats['failed'] ?? 0,
      'best_subject' => $stats['best_subject'] ?? 'N/A',
      'worst_subject' => $stats['worst_subject'] ?? 'N/A'
    ];
  }

  /**
   * Get subject-wise average for a student
   */
  public function getSubjectAverages()
  {
    if (!$this->student_data) {
      return [];
    }

    $query = "SELECT 
                    s.subject_title,
                    AVG(r.percentage) as avg_percentage,
                    MAX(r.percentage) as max_score,
                    MIN(r.percentage) as min_score,
                    COUNT(r.id) as exam_count
                    FROM results r
                    JOIN subjects s ON r.subject_id = s.id
                    WHERE r.student_id = ?
                    GROUP BY r.subject_id, s.subject_title
                    ORDER BY avg_percentage DESC";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_id]);
    return $stmt->fetchAll();
  }

  /**
   * Get overall grade based on percentage
   */
  public static function getGrade($percentage)
  {
    if ($percentage >= 90)
      return ['grade' => 'A+', 'point' => 4.0, 'remark' => 'Outstanding'];
    if ($percentage >= 80)
      return ['grade' => 'A', 'point' => 3.7, 'remark' => 'Excellent'];
    if ($percentage >= 70)
      return ['grade' => 'B+', 'point' => 3.3, 'remark' => 'Very good'];
    if ($percentage >= 60)
      return ['grade' => 'B', 'point' => 3.0, 'remark' => 'Good'];
    if ($percentage >= 50)
      return ['grade' => 'C', 'point' => 2.0, 'remark' => 'Satisfactory'];
    if ($percentage >= 40)
      return ['grade' => 'D', 'point' => 1.0, 'remark' => 'Needs improvement'];
    return ['grade' => 'F', 'point' => 0.0, 'remark' => 'Fail'];
  }

  /**
   * Get grade color
   */
  public static function getGradeColor($grade)
  {
    $colors = [
      'A+' => 'p-ok',
      'A' => 'p-ok',
      'B+' => 'p-teal',
      'B' => 'p-teal',
      'C' => 'p-warn',
      'D' => 'p-warn',
      'F' => 'p-danger'
    ];
    return $colors[$grade] ?? 'p-grey';
  }

  /**
   * Get class rank for student
   */
  public function getClassRank()
  {
    if (!$this->student_data) {
      return ['rank' => 0, 'total' => 0];
    }

    // Get all students in same class with their average
    $query = "SELECT 
                    s.id,
                    s.first_name,
                    s.last_name,
                    AVG(r.percentage) as avg_score
                    FROM students s
                    LEFT JOIN results r ON s.id = r.student_id
                    WHERE s.class_id = ?
                    GROUP BY s.id
                    ORDER BY avg_score DESC";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_data['class_id']]);
    $students = $stmt->fetchAll();

    $rank = 0;
    $total = count($students);

    foreach ($students as $index => $student) {
      if ($student['id'] == $this->student_id) {
        $rank = $index + 1;
        break;
      }
    }

    return ['rank' => $rank, 'total' => $total];
  }

  /**
   * Get results by subject
   */
  public function getResultsBySubject($subject_id)
  {
    $query = "SELECT r.*, e.exam_title, e.exam_date
                  FROM results r
                  JOIN exams e ON r.exam_id = e.id
                  WHERE r.student_id = ? AND r.subject_id = ?
                  ORDER BY e.exam_date DESC";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_id, $subject_id]);
    return $stmt->fetchAll();
  }

  /**
   * Get exam notices
   */
  public function getExamNotices($limit = 3)
  {
    $query = "SELECT * FROM notices 
                  WHERE category IN ('Academic') 
                  ORDER BY created_at DESC 
                  LIMIT " . intval($limit);

    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll();
  }

  /**
   * Check if student has any results
   */
  public function hasResults()
  {
    $query = "SELECT COUNT(*) as count FROM results WHERE student_id = ?";
    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_id]);
    $result = $stmt->fetch();
    return $result['count'] > 0;
  }

  /**
   * Add a new result
   */
  public function addResult($data)
  {
    $query = "INSERT INTO results (student_id, exam_id, subject_id, marks_obtained, total_marks, grade, result) 
                  VALUES (?, ?, ?, ?, ?, ?, ?)";

    $gradeData = self::getGrade(($data['marks_obtained'] / $data['total_marks']) * 100);

    $stmt = $this->conn->prepare($query);
    return $stmt->execute([
      $data['student_id'],
      $data['exam_id'],
      $data['subject_id'],
      $data['marks_obtained'],
      $data['total_marks'],
      $gradeData['grade'],
      $gradeData['grade'] == 'F' ? 'Fail' : 'Pass'
    ]);
  }

  /**
   * Update a result
   */
  public function updateResult($result_id, $marks_obtained, $total_marks)
  {
    $percentage = ($marks_obtained / $total_marks) * 100;
    $gradeData = self::getGrade($percentage);

    $query = "UPDATE results 
                  SET marks_obtained = ?, total_marks = ?, 
                      grade = ?, result = ?
                  WHERE id = ?";

    $stmt = $this->conn->prepare($query);
    return $stmt->execute([
      $marks_obtained,
      $total_marks,
      $gradeData['grade'],
      $gradeData['grade'] == 'F' ? 'Fail' : 'Pass',
      $result_id
    ]);
  }

  /**
   * Delete a result
   */
  public function deleteResult($result_id)
  {
    $query = "DELETE FROM results WHERE id = ?";
    $stmt = $this->conn->prepare($query);
    return $stmt->execute([$result_id]);
  }

  /**
   * Get subject-wise color mapping
   */
  public static function getSubjectColor($subject_title)
  {
    $colors = [
      'Mathematics' => 's-math',
      'Physics' => 's-phy',
      'Computer' => 's-cs',
      'Chemistry' => 's-chem',
      'English' => 's-eng',
      'Urdu' => 's-urdu',
      'Biology' => 's-bio',
      'Economics' => 's-econ',
      'Accounting' => 's-acc',
      'Business' => 's-bus'
    ];

    foreach ($colors as $key => $color) {
      if (stripos($subject_title, $key) !== false) {
        return $color;
      }
    }
    return 's-math';
  }

  /**
   * Get subject icon
   */
  public static function getSubjectIcon($subject_title)
  {
    $icons = [
      'Mathematics' => 'bi-calculator',
      'Physics' => 'bi-lightning-charge',
      'Computer' => 'bi-pc-display',
      'Chemistry' => 'bi-droplet-half',
      'English' => 'bi-book',
      'Urdu' => 'bi-pen',
      'Biology' => 'bi-heart-pulse',
      'Economics' => 'bi-graph-up',
      'Accounting' => 'bi-cash-stack',
      'Business' => 'bi-briefcase'
    ];

    foreach ($icons as $key => $icon) {
      if (stripos($subject_title, $key) !== false) {
        return $icon;
      }
    }
    return 'bi-book';
  }

  /**
   * Format percentage
   */
  public static function formatPercentage($value)
  {
    return number_format($value, 1) . '%';
  }

  /**
   * Get color for percentage bar
   */
  public static function getBarColor($percentage)
  {
    if ($percentage >= 80)
      return '#0d5c66';
    if ($percentage >= 70)
      return '#dd8f21';
    if ($percentage >= 60)
      return '#2b6da9';
    if ($percentage >= 50)
      return '#6a4c9e';
    if ($percentage >= 40)
      return '#1a8a62';
    return '#bf4638';
  }
}

// Helper functions for use in templates
function getSubjectColor($subject_title)
{
  return ResultProcess::getSubjectColor($subject_title);
}

function getSubjectIcon($subject_title)
{
  return ResultProcess::getSubjectIcon($subject_title);
}

function getGradeColor($grade)
{
  return ResultProcess::getGradeColor($grade);
}

function getGrade($percentage)
{
  return ResultProcess::getGrade($percentage);
}

// Initialize the process for the current student
// Usage: $resultProcess = new ResultProcess($conn, $student_id);
?>