<?php
// AttendanceProcess.php
require_once '../database/connect.php';

class AttendanceProcess
{
  private $conn;

  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  /**
   * Get attendance records by date with filters
   */
  public function getAttendanceByDate($date, $class_id = null, $teacher_id = null, $search = null)
  {
    $query = "
            SELECT 
                a.id,
                a.student_id,
                a.attendance_date,
                a.status,
                a.remarks,
                a.check_in_time,
                s.first_name,
                s.last_name,
                s.student_uid,
                c.id as class_id,
                c.name as class_name,
                c.grade as class_grade
            FROM attendance a
            LEFT JOIN students s ON a.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE 1=1
        ";

    $params = [];

    if ($date) {
      $query .= " AND a.attendance_date = :date";
      $params[':date'] = $date;
    }

    if ($class_id) {
      $query .= " AND c.id = :class_id";
      $params[':class_id'] = $class_id;
    }

    if ($teacher_id) {
      $query .= " AND c.teacher_id = :teacher_id";
      $params[':teacher_id'] = $teacher_id;
    }

    if ($search) {
      $query .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.student_uid LIKE :search)";
      $params[':search'] = '%' . $search . '%';
    }

    $query .= " ORDER BY a.attendance_date DESC, s.first_name ASC";

    $stmt = $this->conn->prepare($query);
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get attendance statistics
   */
  public function getAttendanceStats($teacher_id, $date = null, $class_id = null)
  {
    $query = "
            SELECT 
                COUNT(*) as total_records,
                SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late,
                ROUND((SUM(CASE WHEN a.status IN ('Present', 'Late') THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
            FROM attendance a
            LEFT JOIN students s ON a.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE c.teacher_id = :teacher_id
        ";

    $params = [':teacher_id' => $teacher_id];

    if ($date) {
      $query .= " AND a.attendance_date = :date";
      $params[':date'] = $date;
    }

    if ($class_id) {
      $query .= " AND c.id = :class_id";
      $params[':class_id'] = $class_id;
    }

    $stmt = $this->conn->prepare($query);
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $result = $stmt->fetch();

    return [
      'total' => (int) ($result['total_records'] ?? 0),
      'present' => (int) ($result['present'] ?? 0),
      'absent' => (int) ($result['absent'] ?? 0),
      'late' => (int) ($result['late'] ?? 0),
      'percentage' => (float) ($result['percentage'] ?? 0)
    ];
  }

  /**
   * Get students for attendance marking
   */
  public function getStudentsForAttendance($class_id, $date = null)
  {
    $date = $date ?? date('Y-m-d');

    $query = "
            SELECT 
                s.id,
                s.first_name,
                s.last_name,
                s.student_uid,
                a.status as attendance_status,
                a.remarks as attendance_remarks,
                a.check_in_time
            FROM students s
            LEFT JOIN attendance a ON s.id = a.student_id AND a.attendance_date = :date
            WHERE s.class_id = :class_id AND s.status = 'Active'
            ORDER BY s.first_name, s.last_name
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':date', $date);
    $stmt->bindValue(':class_id', $class_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Mark or update attendance for a student
   */
  public function markAttendance($student_id, $class_id, $date, $status, $remarks = '')
  {
    try {
      // Check if attendance already exists
      $check_stmt = $this->conn->prepare("
                SELECT id FROM attendance 
                WHERE student_id = :student_id AND attendance_date = :date
            ");
      $check_stmt->bindValue(':student_id', $student_id);
      $check_stmt->bindValue(':date', $date);
      $check_stmt->execute();
      $existing = $check_stmt->fetch();

      if ($existing) {
        // Update existing record
        $query = "
                    UPDATE attendance 
                    SET status = :status, 
                        remarks = :remarks, 
                        check_in_time = NOW()
                    WHERE student_id = :student_id AND attendance_date = :date
                ";
      } else {
        // Insert new record
        $query = "
                    INSERT INTO attendance (student_id, class_id, attendance_date, check_in_time, status, remarks)
                    VALUES (:student_id, :class_id, :date, NOW(), :status, :remarks)
                ";
      }

      $stmt = $this->conn->prepare($query);
      $stmt->bindValue(':student_id', $student_id);
      $stmt->bindValue(':class_id', $class_id);
      $stmt->bindValue(':date', $date);
      $stmt->bindValue(':status', $status);
      $stmt->bindValue(':remarks', $remarks);

      return $stmt->execute();
    } catch (Exception $e) {
      error_log("Attendance marking error: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Mark attendance for multiple students
   */
  public function markBulkAttendance($class_id, $date, $attendance_data, $remarks_data = [])
  {
    try {
      $this->conn->beginTransaction();

      $student_stmt = $this->conn->prepare("
                SELECT id FROM students 
                WHERE class_id = :class_id AND status = 'Active'
            ");
      $student_stmt->bindValue(':class_id', $class_id);
      $student_stmt->execute();
      $students = $student_stmt->fetchAll();

      foreach ($students as $student) {
        $student_id = $student['id'];
        $status = $attendance_data[$student_id] ?? 'Absent';
        $remark = $remarks_data[$student_id] ?? '';

        $this->markAttendance($student_id, $class_id, $date, $status, $remark);
      }

      $this->conn->commit();
      return true;
    } catch (Exception $e) {
      $this->conn->rollBack();
      error_log("Bulk attendance error: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Get monthly attendance summary
   */
  public function getMonthlyAttendance($student_id, $month, $year)
  {
    $query = "
            SELECT 
                DAY(attendance_date) as day,
                status
            FROM attendance
            WHERE student_id = :student_id 
                AND MONTH(attendance_date) = :month 
                AND YEAR(attendance_date) = :year
            ORDER BY attendance_date
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->bindValue(':month', $month);
    $stmt->bindValue(':year', $year);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get today's attendance for a class
   */
  public function getTodayAttendance($class_id)
  {
    $query = "
            SELECT 
                s.id,
                s.first_name,
                s.last_name,
                s.student_uid,
                COALESCE(a.status, 'Not Marked') as status,
                a.remarks,
                a.check_in_time
            FROM students s
            LEFT JOIN attendance a ON s.id = a.student_id 
                AND a.attendance_date = CURDATE()
            WHERE s.class_id = :class_id AND s.status = 'Active'
            ORDER BY s.first_name, s.last_name
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':class_id', $class_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get classes for a teacher
   */
  public function getTeacherClasses($teacher_id)
  {
    $query = "
            SELECT DISTINCT c.id, c.name, c.grade, c.room_no
            FROM classes c
            WHERE c.teacher_id = :teacher_id AND c.status = 'active'
            ORDER BY c.name
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }
}

// Usage example in attendance.php:
/*
$attendance = new AttendanceProcess($conn);

// Get stats
$stats = $attendance->getAttendanceStats($teacher_id, $search_date, $class_filter);

// Get records
$records = $attendance->getAttendanceByDate($search_date, $class_filter, $teacher_id, $student_search);

// Mark bulk attendance
$attendance->markBulkAttendance($class_id, $date, $statuses, $remarks);
*/
?>