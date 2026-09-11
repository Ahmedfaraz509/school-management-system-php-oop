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
   * Get attendance statistics for a student
   */
  public function getAttendanceStats($student_id)
  {
    $query = "
            SELECT 
                COUNT(*) as total_classes,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late,
                ROUND((SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
            FROM attendance
            WHERE student_id = :student_id
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->execute();

    $result = $stmt->fetch();
    if (!$result || $result['total_classes'] == 0) {
      return [
        'total_classes' => 0,
        'present' => 0,
        'absent' => 0,
        'late' => 0,
        'percentage' => 0
      ];
    }

    return [
      'total_classes' => (int) $result['total_classes'],
      'present' => (int) $result['present'],
      'absent' => (int) $result['absent'],
      'late' => (int) $result['late'],
      'percentage' => round($result['percentage'] ?? 0, 2)
    ];
  }

  /**
   * Get subject-wise attendance for a student
   */
  public function getSubjectWiseAttendance($student_id)
  {
    $query = "
            SELECT 
                s.id,
                s.subject_title,
                s.subject_code,
                COUNT(a.id) as total_classes,
                SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late,
                ROUND((SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) as percentage
            FROM attendance a
            LEFT JOIN subjects s ON a.subject_id = s.id
            WHERE a.student_id = :student_id
            GROUP BY s.id
            ORDER BY s.subject_title ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get recent attendance records for a student
   */
  public function getRecentAttendance($student_id, $limit = 12)
  {
    $query = "
            SELECT 
                a.attendance_date,
                s.subject_title,
                t.full_name as teacher_name,
                a.status,
                a.remarks,
                a.check_in_time
            FROM attendance a
            LEFT JOIN subjects s ON a.subject_id = s.id
            LEFT JOIN teachers t ON a.teacher_id = t.id
            WHERE a.student_id = :student_id
            ORDER BY a.attendance_date DESC
            LIMIT :limit
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get monthly attendance trend
   */
  public function getMonthlyTrend($student_id)
  {
    $query = "
            SELECT 
                MONTH(attendance_date) as month_num,
                MONTHNAME(attendance_date) as month_name,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                ROUND((SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
            FROM attendance
            WHERE student_id = :student_id
            GROUP BY MONTH(attendance_date)
            ORDER BY MONTH(attendance_date) ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->execute();

    $results = $stmt->fetchAll();
    $trend = [];

    foreach ($results as $row) {
      $trend[] = [
        'month' => $row['month_name'],
        'percentage' => round($row['percentage'] ?? 0, 2),
        'total' => (int) $row['total'],
        'present' => (int) $row['present']
      ];
    }

    return $trend;
  }

  /**
   * Get leave records (absences with remarks)
   */
  public function getLeaveRecords($student_id, $limit = 4)
  {
    $query = "
            SELECT 
                a.attendance_date,
                s.subject_title,
                a.remarks,
                a.status
            FROM attendance a
            LEFT JOIN subjects s ON a.subject_id = s.id
            WHERE a.student_id = :student_id 
                AND a.status IN ('Absent', 'Late')
                AND a.remarks IS NOT NULL AND a.remarks != ''
            ORDER BY a.attendance_date DESC
            LIMIT :limit
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get attendance for a specific date range
   */
  public function getAttendanceByDateRange($student_id, $start_date, $end_date)
  {
    $query = "
            SELECT 
                a.attendance_date,
                s.subject_title,
                a.status,
                a.remarks
            FROM attendance a
            LEFT JOIN subjects s ON a.subject_id = s.id
            WHERE a.student_id = :student_id 
                AND a.attendance_date BETWEEN :start_date AND :end_date
            ORDER BY a.attendance_date DESC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->bindValue(':start_date', $start_date);
    $stmt->bindValue(':end_date', $end_date);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Mark attendance for a student
   */
  public function markAttendance($student_id, $class_id, $subject_id, $date, $status, $remarks = '', $teacher_id = null)
  {
    // Check if attendance already exists
    $check_stmt = $this->conn->prepare("
            SELECT id FROM attendance 
            WHERE student_id = :student_id AND attendance_date = :date AND subject_id = :subject_id
        ");
    $check_stmt->bindValue(':student_id', $student_id);
    $check_stmt->bindValue(':date', $date);
    $check_stmt->bindValue(':subject_id', $subject_id);
    $check_stmt->execute();

    if ($check_stmt->rowCount() > 0) {
      // Update existing record
      $query = "
                UPDATE attendance 
                SET status = :status, 
                    remarks = :remarks, 
                    check_in_time = NOW()
                WHERE student_id = :student_id AND attendance_date = :date AND subject_id = :subject_id
            ";
    } else {
      // Insert new record
      $query = "
                INSERT INTO attendance (student_id, class_id, subject_id, attendance_date, check_in_time, status, remarks, teacher_id)
                VALUES (:student_id, :class_id, :subject_id, :date, NOW(), :status, :remarks, :teacher_id)
            ";
    }

    try {
      $stmt = $this->conn->prepare($query);
      $stmt->bindValue(':student_id', $student_id);
      $stmt->bindValue(':class_id', $class_id);
      $stmt->bindValue(':subject_id', $subject_id);
      $stmt->bindValue(':date', $date);
      $stmt->bindValue(':status', $status);
      $stmt->bindValue(':remarks', $remarks);

      if ($teacher_id !== null) {
        $stmt->bindValue(':teacher_id', $teacher_id);
      }

      if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Attendance marked successfully'];
      }
      return ['success' => false, 'message' => 'Failed to mark attendance'];
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Get attendance summary by class
   */
  public function getClassAttendanceSummary($class_id, $date = null)
  {
    $date_condition = $date ? "AND attendance_date = :date" : "AND attendance_date = CURDATE()";

    $query = "
            SELECT 
                COUNT(*) as total_students,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late,
                ROUND((SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as percentage
            FROM attendance a
            LEFT JOIN students s ON a.student_id = s.id
            WHERE s.class_id = :class_id {$date_condition}
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':class_id', $class_id);
    if ($date) {
      $stmt->bindValue(':date', $date);
    }
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Get student attendance summary
   */
  public function getStudentAttendanceSummary($student_id)
  {
    $stats = $this->getAttendanceStats($student_id);

    return [
      'total_classes' => $stats['total_classes'],
      'present' => $stats['present'],
      'absent' => $stats['absent'],
      'late' => $stats['late'],
      'percentage' => $stats['percentage'],
      'status' => $stats['percentage'] >= 75 ? 'Eligible' : 'Not Eligible',
      'message' => $stats['percentage'] >= 75 ?
        'Attendance requirement of 75% is satisfied.' :
        'Attendance is below the required 75%. Please improve.'
    ];
  }

  /**
   * Get attendance by subject for a specific date
   */
  public function getSubjectAttendanceByDate($student_id, $date, $subject_id = null)
  {
    $query = "
            SELECT 
                a.id,
                a.status,
                a.remarks,
                a.check_in_time,
                s.subject_title,
                s.subject_code
            FROM attendance a
            LEFT JOIN subjects s ON a.subject_id = s.id
            WHERE a.student_id = :student_id AND a.attendance_date = :date
        ";

    if ($subject_id) {
      $query .= " AND a.subject_id = :subject_id";
    }

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->bindValue(':date', $date);
    if ($subject_id) {
      $stmt->bindValue(':subject_id', $subject_id);
    }
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get student info
   */
  public function getStudentInfo($student_id)
  {
    $query = "
            SELECT 
                s.id,
                s.first_name,
                s.last_name,
                s.student_uid,
                s.class_id,
                c.name as class_name,
                c.grade as class_grade
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE s.id = :student_id
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Get students who are absent today
   */
  public function getAbsentStudentsToday($class_id = null)
  {
    $query = "
            SELECT 
                s.id,
                s.first_name,
                s.last_name,
                s.student_uid,
                c.name as class_name
            FROM students s
            LEFT JOIN attendance a ON s.id = a.student_id 
                AND a.attendance_date = CURDATE()
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE a.status = 'Absent' OR a.id IS NULL
        ";

    if ($class_id) {
      $query .= " AND s.class_id = :class_id";
    }

    $query .= " ORDER BY s.first_name ASC";

    $stmt = $this->conn->prepare($query);
    if ($class_id) {
      $stmt->bindValue(':class_id', $class_id);
    }
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get attendance report for a class
   */
  public function getClassAttendanceReport($class_id, $start_date, $end_date)
  {
    $query = "
            SELECT 
                s.id as student_id,
                s.first_name,
                s.last_name,
                s.student_uid,
                COUNT(a.id) as total_classes,
                SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late,
                ROUND((SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) / COUNT(a.id)) * 100, 2) as percentage
            FROM students s
            LEFT JOIN attendance a ON s.id = a.student_id 
                AND a.attendance_date BETWEEN :start_date AND :end_date
            WHERE s.class_id = :class_id AND s.status = 'Active'
            GROUP BY s.id
            ORDER BY s.first_name ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':class_id', $class_id);
    $stmt->bindValue(':start_date', $start_date);
    $stmt->bindValue(':end_date', $end_date);
    $stmt->execute();

    return $stmt->fetchAll();
  }
}

// Usage example:
/*
$attendanceProcess = new AttendanceProcess($conn);

// Get attendance stats for a student
$stats = $attendanceProcess->getAttendanceStats($student_id);

// Get subject-wise attendance
$subject_attendance = $attendanceProcess->getSubjectWiseAttendance($student_id);

// Get recent attendance records
$recent = $attendanceProcess->getRecentAttendance($student_id, 12);

// Get monthly trend
$trend = $attendanceProcess->getMonthlyTrend($student_id);

// Get leave records
$leaves = $attendanceProcess->getLeaveRecords($student_id);

// Mark attendance
$result = $attendanceProcess->markAttendance($student_id, $class_id, $subject_id, date('Y-m-d'), 'Present');
*/
?>