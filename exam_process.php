<?php
// exam_process.php
// Process file for handling exam-related database operations

require_once '../database/connect.php';

class ExamProcess
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
   * Get upcoming exams for a student's class
   */
  public function getUpcomingExams($limit = null)
  {
    if (!$this->student_data) {
      return [];
    }

    $query = "SELECT e.*, s.subject_title, s.subject_code, s.id as subject_id,
                  CASE 
                      WHEN e.status = 'Scheduled' THEN 'Upcoming'
                      WHEN e.status = 'Ongoing' THEN 'Ongoing'
                      ELSE e.status
                  END as display_status,
                  CASE 
                      WHEN e.status = 'Scheduled' THEN 'p-info'
                      WHEN e.status = 'Ongoing' THEN 'p-warn'
                      WHEN e.status = 'Completed' THEN 'p-ok'
                      ELSE 'p-grey'
                  END as status_color,
                  DATEDIFF(e.exam_date, CURDATE()) as days_until
                  FROM exams e
                  JOIN subjects s ON e.subject_id = s.id
                  WHERE e.class_id = ? 
                  AND e.exam_date >= CURDATE()
                  AND e.status != 'Cancelled'
                  ORDER BY e.exam_date ASC";

    if ($limit) {
      $query .= " LIMIT " . intval($limit);
    }

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_data['class_id']]);
    return $stmt->fetchAll();
  }

  /**
   * Get completed exams for a student's class
   */
  public function getCompletedExams($limit = 10)
  {
    if (!$this->student_data) {
      return [];
    }

    $query = "SELECT e.*, s.subject_title, s.subject_code, s.id as subject_id,
                  CASE 
                      WHEN e.status = 'Completed' THEN 'Result Declared'
                      WHEN e.status = 'Cancelled' THEN 'Cancelled'
                      ELSE e.status
                  END as display_status,
                  CASE 
                      WHEN e.status = 'Completed' THEN 'p-ok'
                      WHEN e.status = 'Cancelled' THEN 'p-danger'
                      ELSE 'p-grey'
                  END as status_color
                  FROM exams e
                  JOIN subjects s ON e.subject_id = s.id
                  WHERE e.class_id = ? 
                  AND e.exam_date < CURDATE()
                  ORDER BY e.exam_date DESC
                  LIMIT " . intval($limit);

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_data['class_id']]);
    return $stmt->fetchAll();
  }

  /**
   * Get exam statistics
   */
  public function getExamStats()
  {
    if (!$this->student_data) {
      return [
        'upcoming_count' => 0,
        'completed_count' => 0,
        'ongoing_count' => 0,
        'total_count' => 0
      ];
    }

    $query = "SELECT 
                    COUNT(CASE WHEN exam_date >= CURDATE() AND status != 'Cancelled' THEN 1 END) as upcoming_count,
                    COUNT(CASE WHEN exam_date < CURDATE() AND status != 'Cancelled' THEN 1 END) as completed_count,
                    COUNT(CASE WHEN status = 'Ongoing' THEN 1 END) as ongoing_count,
                    COUNT(*) as total_count
                    FROM exams 
                    WHERE class_id = ?";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_data['class_id']]);
    return $stmt->fetch();
  }

  /**
   * Get first upcoming exam date
   */
  public function getFirstExamDate()
  {
    if (!$this->student_data) {
      return null;
    }

    $query = "SELECT MIN(exam_date) as first_date, exam_title, subject_id
                  FROM exams 
                  WHERE class_id = ? AND exam_date >= CURDATE() AND status != 'Cancelled'";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_data['class_id']]);
    return $stmt->fetch();
  }

  /**
   * Calculate days until first exam
   */
  public function getDaysUntilFirstExam()
  {
    $firstExam = $this->getFirstExamDate();
    if (!$firstExam || !$firstExam['first_date']) {
      return 0;
    }

    $now = new DateTime();
    $examDate = new DateTime($firstExam['first_date']);
    $diff = $now->diff($examDate);
    return $diff->days;
  }

  /**
   * Get exams by status
   */
  public function getExamsByStatus($status)
  {
    if (!$this->student_data) {
      return [];
    }

    $query = "SELECT e.*, s.subject_title, s.subject_code, s.id as subject_id
                  FROM exams e
                  JOIN subjects s ON e.subject_id = s.id
                  WHERE e.class_id = ? AND e.status = ?
                  ORDER BY e.exam_date ASC";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_data['class_id'], $status]);
    return $stmt->fetchAll();
  }

  /**
   * Get today's exams
   */
  public function getTodaysExams()
  {
    if (!$this->student_data) {
      return [];
    }

    $query = "SELECT e.*, s.subject_title, s.subject_code
                  FROM exams e
                  JOIN subjects s ON e.subject_id = s.id
                  WHERE e.class_id = ? 
                  AND e.exam_date = CURDATE()
                  ORDER BY e.start_time ASC";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_data['class_id']]);
    return $stmt->fetchAll();
  }

  /**
   * Get subject count for student's class
   */
  public function getSubjectCount()
  {
    if (!$this->student_data) {
      return 0;
    }

    $query = "SELECT COUNT(*) as count FROM subjects WHERE class_id = ? AND status = 'Active'";
    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_data['class_id']]);
    $result = $stmt->fetch();
    return $result['count'] ?? 0;
  }

  /**
   * Get exam notices
   */
  public function getExamNotices($limit = 3)
  {
    $query = "SELECT * FROM notices 
                  WHERE category IN ('Academic', 'Holiday') 
                  ORDER BY created_at DESC 
                  LIMIT " . intval($limit);

    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll();
  }

  /**
   * Check if student is eligible for exam
   */
  public function checkEligibility()
  {
    if (!$this->student_data) {
      return ['eligible' => false, 'reason' => 'Student not found'];
    }

    // Check attendance requirement (example: need 75% attendance)
    $attendanceQuery = "SELECT 
                            COUNT(*) as total_days,
                            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days
                            FROM attendance 
                            WHERE student_id = ? AND class_id = ?";

    $stmt = $this->conn->prepare($attendanceQuery);
    $stmt->execute([$this->student_id, $this->student_data['class_id']]);
    $attendance = $stmt->fetch();

    if ($attendance && $attendance['total_days'] > 0) {
      $percentage = ($attendance['present_days'] / $attendance['total_days']) * 100;
      if ($percentage < 75) {
        return ['eligible' => false, 'reason' => 'Attendance below 75%'];
      }
    }

    return ['eligible' => true, 'reason' => 'Eligible'];
  }

  /**
   * Get priority level for exam
   */
  public static function getPriorityLevel($index)
  {
    if ($index < 3) {
      return ['label' => 'High priority', 'class' => 'p-danger'];
    } elseif ($index < 5) {
      return ['label' => 'Medium priority', 'class' => 'p-warn'];
    } else {
      return ['label' => 'Confident', 'class' => 'p-ok'];
    }
  }
}