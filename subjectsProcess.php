<?php
// SubjectsProcess.php
require_once '../database/connect.php';

class SubjectsProcess
{
  private $conn;

  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  /**
   * Get subjects by student ID
   */
  public function getSubjectsByStudent($student_id)
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
                t.full_name as teacher_name,
                t.id as teacher_id,
                (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'Active') as total_students
            FROM subjects s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN teachers t ON c.teacher_id = t.id OR s.teacher_id = t.id
            LEFT JOIN students st ON st.class_id = c.id
            WHERE st.id = :student_id AND s.status IN ('Active', 'Elective')
            ORDER BY s.subject_title ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get subject by ID
   */
  public function getSubjectById($subject_id)
  {
    $query = "
            SELECT 
                s.*,
                c.name as class_name,
                c.grade as class_grade,
                c.room_no,
                t.full_name as teacher_name,
                (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'Active') as total_students
            FROM subjects s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN teachers t ON c.teacher_id = t.id OR s.teacher_id = t.id
            WHERE s.id = :subject_id
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':subject_id', $subject_id);
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Get subject attendance for a student
   */
  public function getSubjectAttendance($student_id, $subject_id)
  {
    // This query assumes you have attendance tracking by subject
    // If not, you'll need to modify this query
    $query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN a.status IN ('Present', 'Late') THEN 1 ELSE 0 END) as present
            FROM attendance a
            LEFT JOIN students s ON a.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN subjects sub ON sub.class_id = c.id
            WHERE a.student_id = :student_id AND sub.id = :subject_id
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->bindValue(':subject_id', $subject_id);
    $stmt->execute();

    $result = $stmt->fetch();
    if ($result && $result['total'] > 0) {
      return round(($result['present'] / $result['total']) * 100);
    }
    return 0;
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
                s.gender,
                s.class_id,
                c.name as class_name,
                c.grade as class_grade,
                c.room_no as class_room,
                t.full_name as teacher_name,
                t.id as teacher_id
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN teachers t ON c.teacher_id = t.id
            WHERE s.id = :student_id
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Get class teacher
   */
  public function getClassTeacher($class_id)
  {
    $query = "
            SELECT t.full_name, t.email, t.phone
            FROM teachers t
            WHERE t.id = (SELECT teacher_id FROM classes WHERE id = :class_id)
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':class_id', $class_id);
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Get subject statistics for a student
   */
  public function getSubjectStats($student_id)
  {
    $query = "
            SELECT 
                COUNT(DISTINCT s.id) as total_subjects,
                SUM(CASE WHEN s.status = 'Active' THEN 1 ELSE 0 END) as active_subjects,
                SUM(CASE WHEN s.status = 'Elective' THEN 1 ELSE 0 END) as elective_subjects
            FROM subjects s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN students st ON st.class_id = c.id
            WHERE st.id = :student_id
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->execute();

    return $stmt->fetch();
  }
}

// Usage example:
/*
$subjectProcess = new SubjectsProcess($conn);

// Get subjects for a student
$subjects = $subjectProcess->getSubjectsByStudent($student_id);

// Get student info
$student = $subjectProcess->getStudentInfo($student_id);

// Get class teacher
$teacher = $subjectProcess->getClassTeacher($class_id);

// Get subject stats
$stats = $subjectProcess->getSubjectStats($student_id);
*/
?>