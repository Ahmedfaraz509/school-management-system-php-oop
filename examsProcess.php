<?php
// ExamsProcess.php
require_once '../database/connect.php';

class ExamsProcess
{
  private $conn;

  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  /**
   * Get exams by teacher with filters
   */
  public function getExamsByTeacher($teacher_id, $filters = [])
  {
    $query = "
            SELECT 
                e.id,
                e.exam_title,
                e.exam_date,
                e.start_time,
                e.end_time,
                e.room,
                e.status as exam_status,
                e.created_at,
                s.id as subject_id,
                s.subject_title,
                s.subject_code,
                c.id as class_id,
                c.name as class_name,
                c.grade as class_grade,
                (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'Active') as total_students
            FROM exams e
            LEFT JOIN subjects s ON e.subject_id = s.id
            LEFT JOIN classes c ON e.class_id = c.id
            WHERE 1=1
        ";

    $params = [];

    // Teacher filter
    $query .= " AND (s.teacher_id = :teacher_id OR c.teacher_id = :teacher_id)";
    $params[':teacher_id'] = $teacher_id;

    // Search filter
    if (!empty($filters['search'])) {
      $query .= " AND (e.exam_title LIKE :search OR s.subject_title LIKE :search)";
      $params[':search'] = '%' . $filters['search'] . '%';
    }

    // Subject filter
    if (!empty($filters['subject_id'])) {
      $query .= " AND e.subject_id = :subject_id";
      $params[':subject_id'] = $filters['subject_id'];
    }

    // Class filter
    if (!empty($filters['class_id'])) {
      $query .= " AND e.class_id = :class_id";
      $params[':class_id'] = $filters['class_id'];
    }

    // Status filter
    if (!empty($filters['status'])) {
      $query .= " AND e.status = :status";
      $params[':status'] = $filters['status'];
    }

    // Date range filter
    if (!empty($filters['date_from'])) {
      $query .= " AND e.exam_date >= :date_from";
      $params[':date_from'] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
      $query .= " AND e.exam_date <= :date_to";
      $params[':date_to'] = $filters['date_to'];
    }

    $query .= " ORDER BY e.exam_date DESC, e.start_time ASC";

    $stmt = $this->conn->prepare($query);
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get exam by ID
   */
  public function getExamById($exam_id)
  {
    $query = "
            SELECT 
                e.*,
                s.subject_title,
                s.subject_code,
                c.name as class_name,
                c.grade as class_grade,
                (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'Active') as total_students
            FROM exams e
            LEFT JOIN subjects s ON e.subject_id = s.id
            LEFT JOIN classes c ON e.class_id = c.id
            WHERE e.id = :id
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':id', $exam_id);
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Create a new exam
   */
  public function createExam($data)
  {
    $query = "
            INSERT INTO exams (
                exam_title, subject_id, class_id, exam_date, 
                start_time, end_time, room, status
            ) VALUES (
                :exam_title, :subject_id, :class_id, :exam_date,
                :start_time, :end_time, :room, :status
            )
        ";

    try {
      $stmt = $this->conn->prepare($query);
      $stmt->bindValue(':exam_title', $data['exam_title']);
      $stmt->bindValue(':subject_id', $data['subject_id']);
      $stmt->bindValue(':class_id', $data['class_id']);
      $stmt->bindValue(':exam_date', $data['exam_date']);
      $stmt->bindValue(':start_time', $data['start_time']);
      $stmt->bindValue(':end_time', $data['end_time']);
      $stmt->bindValue(':room', $data['room'] ?? '');
      $stmt->bindValue(':status', $data['status'] ?? 'Scheduled');

      if ($stmt->execute()) {
        return [
          'success' => true,
          'id' => $this->conn->lastInsertId(),
          'message' => 'Exam created successfully'
        ];
      } else {
        return ['success' => false, 'message' => 'Failed to create exam'];
      }
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Update an exam
   */
  public function updateExam($exam_id, $data)
  {
    $updates = [];
    $params = [':id' => $exam_id];

    $allowed_fields = ['exam_title', 'exam_date', 'start_time', 'end_time', 'room', 'status', 'subject_id', 'class_id'];
    foreach ($allowed_fields as $field) {
      if (isset($data[$field])) {
        $updates[] = "$field = :$field";
        $params[":$field"] = $data[$field];
      }
    }

    if (empty($updates)) {
      return ['success' => false, 'message' => 'No fields to update'];
    }

    $query = "UPDATE exams SET " . implode(', ', $updates) . " WHERE id = :id";

    try {
      $stmt = $this->conn->prepare($query);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }

      if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Exam updated successfully'];
      } else {
        return ['success' => false, 'message' => 'Failed to update exam'];
      }
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Delete an exam
   */
  public function deleteExam($exam_id)
  {
    $query = "DELETE FROM exams WHERE id = :id";

    try {
      $stmt = $this->conn->prepare($query);
      $stmt->bindValue(':id', $exam_id);

      if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Exam deleted successfully'];
      } else {
        return ['success' => false, 'message' => 'Failed to delete exam'];
      }
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Update exam status
   */
  public function updateExamStatus($exam_id, $status)
  {
    $query = "UPDATE exams SET status = :status WHERE id = :id";

    try {
      $stmt = $this->conn->prepare($query);
      $stmt->bindValue(':status', $status);
      $stmt->bindValue(':id', $exam_id);

      if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Exam status updated successfully'];
      } else {
        return ['success' => false, 'message' => 'Failed to update exam status'];
      }
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Get exam statistics
   */
  public function getExamStats($teacher_id)
  {
    $query = "
            SELECT 
                COUNT(*) as total_exams,
                SUM(CASE WHEN status = 'Scheduled' AND exam_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming_exams,
                SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END) as ongoing_exams,
                SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_exams,
                SUM(CASE WHEN status = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_exams,
                SUM(CASE WHEN status = 'Scheduled' AND exam_date < CURDATE() THEN 1 ELSE 0 END) as overdue_exams
            FROM exams e
            LEFT JOIN subjects s ON e.subject_id = s.id
            LEFT JOIN classes c ON e.class_id = c.id
            WHERE s.teacher_id = :teacher_id OR c.teacher_id = :teacher_id
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Get upcoming exams
   */
  public function getUpcomingExams($teacher_id, $days_ahead = 30)
  {
    $query = "
            SELECT 
                e.*,
                s.subject_title,
                s.subject_code,
                c.name as class_name,
                c.grade as class_grade
            FROM exams e
            LEFT JOIN subjects s ON e.subject_id = s.id
            LEFT JOIN classes c ON e.class_id = c.id
            WHERE (s.teacher_id = :teacher_id OR c.teacher_id = :teacher_id)
                AND e.status = 'Scheduled'
                AND e.exam_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days_ahead DAY)
            ORDER BY e.exam_date ASC, e.start_time ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->bindValue(':days_ahead', $days_ahead);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get today's exams
   */
  public function getTodayExams($teacher_id)
  {
    $query = "
            SELECT 
                e.*,
                s.subject_title,
                s.subject_code,
                c.name as class_name,
                c.grade as class_grade
            FROM exams e
            LEFT JOIN subjects s ON e.subject_id = s.id
            LEFT JOIN classes c ON e.class_id = c.id
            WHERE (s.teacher_id = :teacher_id OR c.teacher_id = :teacher_id)
                AND e.exam_date = CURDATE()
            ORDER BY e.start_time ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get exams by subject
   */
  public function getExamsBySubject($teacher_id, $subject_id)
  {
    $query = "
            SELECT 
                e.*,
                c.name as class_name,
                c.grade as class_grade
            FROM exams e
            LEFT JOIN classes c ON e.class_id = c.id
            WHERE e.subject_id = :subject_id
                AND (SELECT teacher_id FROM subjects WHERE id = :subject_id) = :teacher_id
            ORDER BY e.exam_date DESC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':subject_id', $subject_id);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get exams by class
   */
  public function getExamsByClass($teacher_id, $class_id)
  {
    $query = "
            SELECT 
                e.*,
                s.subject_title,
                s.subject_code
            FROM exams e
            LEFT JOIN subjects s ON e.subject_id = s.id
            WHERE e.class_id = :class_id
                AND (SELECT teacher_id FROM classes WHERE id = :class_id) = :teacher_id
            ORDER BY e.exam_date DESC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':class_id', $class_id);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get all subjects for dropdown
   */
  public function getTeacherSubjects($teacher_id)
  {
    $query = "
            SELECT DISTINCT s.id, s.subject_title, s.subject_code
            FROM subjects s
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE c.teacher_id = :teacher_id OR s.teacher_id = :teacher_id
            ORDER BY s.subject_title
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get all classes for dropdown
   */
  public function getTeacherClasses($teacher_id)
  {
    $query = "
            SELECT DISTINCT c.id, c.name, c.grade
            FROM classes c
            WHERE c.teacher_id = :teacher_id AND c.status = 'active'
            ORDER BY c.name
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Check for scheduling conflicts
   */
  public function checkScheduleConflict($teacher_id, $exam_date, $start_time, $end_time, $exam_id = null)
  {
    $query = "
            SELECT COUNT(*) as conflict_count
            FROM exams e
            LEFT JOIN subjects s ON e.subject_id = s.id
            LEFT JOIN classes c ON e.class_id = c.id
            WHERE (s.teacher_id = :teacher_id OR c.teacher_id = :teacher_id)
                AND e.exam_date = :exam_date
                AND (
                    (e.start_time <= :start_time AND e.end_time > :start_time) OR
                    (e.start_time < :end_time AND e.end_time >= :end_time) OR
                    (e.start_time >= :start_time AND e.end_time <= :end_time)
                )
        ";

    if ($exam_id) {
      $query .= " AND e.id != :exam_id";
    }

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->bindValue(':exam_date', $exam_date);
    $stmt->bindValue(':start_time', $start_time);
    $stmt->bindValue(':end_time', $end_time);
    if ($exam_id) {
      $stmt->bindValue(':exam_id', $exam_id);
    }
    $stmt->execute();

    $result = $stmt->fetch();
    return $result['conflict_count'] > 0;
  }
}

// Usage example:
/*
$examProcess = new ExamsProcess($conn);

// Get all exams
$exams = $examProcess->getExamsByTeacher($teacher_id);

// Get exam stats
$stats = $examProcess->getExamStats($teacher_id);

// Create a new exam
$result = $examProcess->createExam([
    'exam_title' => 'Mid-Term Exam',
    'subject_id' => 1,
    'class_id' => 1,
    'exam_date' => '2026-09-25',
    'start_time' => '09:00',
    'end_time' => '11:00',
    'room' => 'Hall A',
    'status' => 'Scheduled'
]);

// Get upcoming exams
$upcoming = $examProcess->getUpcomingExams($teacher_id, 30);

// Get today's exams
$today_exams = $examProcess->getTodayExams($teacher_id);

// Check for schedule conflicts
$has_conflict = $examProcess->checkScheduleConflict($teacher_id, '2026-09-25', '09:00', '11:00');
*/
?>