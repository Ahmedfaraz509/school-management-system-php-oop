<?php
// studentsProcess.php
require_once '../database/connect.php';

class StudentsProcess
{
  public function getStudentsByTeacher($conn, $teacher_id)
  {
    $query = "
            SELECT 
                s.id,
                s.student_uid,
                s.first_name,
                s.last_name,
                s.gender,
                s.email,
                s.status,
                s.class_id,
                c.name as class_name,
                c.grade as class_grade
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE c.teacher_id = :teacher_id
            ORDER BY s.first_name ASC
        ";

    $stmt = $conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  public function getStudentAttendance($conn, $student_id)
  {
    $query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
                SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late
            FROM attendance 
            WHERE student_id = :student_id
        ";

    $stmt = $conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->execute();

    return $stmt->fetch();
  }

  public function getClassStudents($conn, $class_id)
  {
    $query = "
            SELECT 
                s.id,
                s.student_uid,
                s.first_name,
                s.last_name,
                s.gender,
                s.email,
                s.status
            FROM students s
            WHERE s.class_id = :class_id AND s.status = 'Active'
            ORDER BY s.first_name ASC
        ";

    $stmt = $conn->prepare($query);
    $stmt->bindValue(':class_id', $class_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  public function getStudentDetails($conn, $student_id)
  {
    $query = "
            SELECT 
                s.*,
                c.name as class_name,
                c.grade as class_grade,
                c.teacher_id,
                t.full_name as teacher_name
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            LEFT JOIN teachers t ON c.teacher_id = t.id
            WHERE s.id = :student_id
        ";

    $stmt = $conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->execute();

    return $stmt->fetch();
  }
}
?>