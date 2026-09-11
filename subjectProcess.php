<?php
// SubjectProcess.php
require_once '../database/connect.php';

class SubjectProcess
{
  private $conn;

  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  /**
   * Get subjects by teacher ID with class and student information
   */
  public function getSubjectsByTeacher($teacher_id)
  {
    $query = "
            SELECT 
                s.id,
                s.subject_code,
                s.subject_title,
                s.status as subject_status,
                s.created_at,
                c.id as class_id,
                c.name as class_name,
                c.grade as class_grade,
                c.room_no,
                c.description as class_description,
                (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'Active') as student_count,
                (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.gender = 'Male' AND st.status = 'Active') as male_count,
                (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.gender = 'Female' AND st.status = 'Active') as female_count
            FROM subjects s
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE c.teacher_id = :teacher_id OR s.teacher_id = :teacher_id
            GROUP BY s.id
            ORDER BY s.subject_title ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get subject details by ID
   */
  public function getSubjectById($subject_id)
  {
    $query = "
            SELECT 
                s.*,
                c.id as class_id,
                c.name as class_name,
                c.grade as class_grade,
                c.room_no,
                t.full_name as teacher_name,
                (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'Active') as student_count
            FROM subjects s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN teachers t ON c.teacher_id = t.id
            WHERE s.id = :subject_id
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':subject_id', $subject_id);
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Get students for a specific subject/class
   */
  public function getSubjectStudents($subject_id)
  {
    $query = "
            SELECT 
                st.id,
                st.student_uid,
                st.first_name,
                st.last_name,
                st.email,
                st.gender,
                st.status,
                st.photo_url
            FROM students st
            LEFT JOIN subjects s ON s.class_id = st.class_id
            WHERE s.id = :subject_id AND st.status = 'Active'
            ORDER BY st.first_name, st.last_name
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':subject_id', $subject_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get subject statistics for a teacher
   */
  public function getSubjectStats($teacher_id)
  {
    $query = "
            SELECT 
                COUNT(DISTINCT s.id) as total_subjects,
                COUNT(DISTINCT c.id) as total_classes,
                SUM((SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'Active')) as total_students,
                SUM((SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.gender = 'Male' AND st.status = 'Active')) as male_students,
                SUM((SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.gender = 'Female' AND st.status = 'Active')) as female_students
            FROM subjects s
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE c.teacher_id = :teacher_id OR s.teacher_id = :teacher_id
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Get subjects with upcoming exams
   */
  public function getSubjectsWithUpcomingExams($teacher_id, $days_ahead = 30)
  {
    $query = "
            SELECT DISTINCT
                s.id,
                s.subject_title,
                s.subject_code,
                e.exam_title,
                e.exam_date,
                e.start_time,
                e.end_time,
                c.name as class_name
            FROM subjects s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN exams e ON e.subject_id = s.id
            WHERE (c.teacher_id = :teacher_id OR s.teacher_id = :teacher_id)
                AND e.exam_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days_ahead DAY)
                AND e.status != 'Completed'
            ORDER BY e.exam_date ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->bindValue(':days_ahead', $days_ahead);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get subjects with pending assignments
   */
  public function getSubjectsWithPendingAssignments($teacher_id)
  {
    $query = "
            SELECT DISTINCT
                s.id,
                s.subject_title,
                s.subject_code,
                a.title as assignment_title,
                a.due_date,
                a.status as assignment_status,
                c.name as class_name,
                COUNT(DISTINCT st.id) as student_count
            FROM subjects s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN assignments a ON a.subject_id = s.id
            LEFT JOIN students st ON st.class_id = c.id
            WHERE (c.teacher_id = :teacher_id OR s.teacher_id = :teacher_id)
                AND a.due_date >= CURDATE()
                AND a.status = 'active'
            GROUP BY s.id, a.id
            ORDER BY a.due_date ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get subject attendance summary
   */
  public function getSubjectAttendanceSummary($teacher_id, $subject_id = null)
  {
    $query = "
            SELECT 
                s.id as subject_id,
                s.subject_title,
                COUNT(DISTINCT a.id) as total_attendance,
                SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late,
                ROUND((SUM(CASE WHEN a.status IN ('Present', 'Late') THEN 1 ELSE 0 END) / COUNT(DISTINCT a.id)) * 100, 2) as attendance_percentage
            FROM subjects s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN students st ON st.class_id = c.id
            LEFT JOIN attendance a ON a.student_id = st.id
            WHERE (c.teacher_id = :teacher_id OR s.teacher_id = :teacher_id)
        ";

    if ($subject_id) {
      $query .= " AND s.id = :subject_id";
    }

    $query .= " GROUP BY s.id ORDER BY s.subject_title";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    if ($subject_id) {
      $stmt->bindValue(':subject_id', $subject_id);
    }
    $stmt->execute();

    return $stmt->fetchAll();
  }
}

// Usage example:
/*
$subjectProcess = new SubjectProcess($conn);

// Get all subjects for a teacher
$subjects = $subjectProcess->getSubjectsByTeacher($teacher_id);

// Get subject stats
$stats = $subjectProcess->getSubjectStats($teacher_id);

// Get subjects with upcoming exams
$exam_subjects = $subjectProcess->getSubjectsWithUpcomingExams($teacher_id, 30);

// Get subject attendance summary
$attendance_summary = $subjectProcess->getSubjectAttendanceSummary($teacher_id);
*/
?>