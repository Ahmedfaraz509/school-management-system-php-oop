<?php
// TimetableProcess.php
require_once '../database/connect.php';

class TimetableProcess
{
  private $conn;

  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  /**
   * Get timetable by student ID
   */
  public function getTimetableByStudent($student_id)
  {
    $query = "
            SELECT 
                t.id,
                t.day_of_week,
                t.time_slot,
                t.room,
                s.subject_code,
                s.subject_title,
                c.name as class_name,
                c.grade as class_grade,
                te.full_name as teacher_name
            FROM timetable t
            LEFT JOIN subjects s ON t.subject_id = s.id
            LEFT JOIN classes c ON t.class_id = c.id
            LEFT JOIN teachers te ON t.teacher_id = te.id
            LEFT JOIN students st ON st.class_id = c.id
            WHERE st.id = :student_id AND s.status IN ('Active', 'Elective')
            ORDER BY 
                FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                t.time_slot ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get timetable by class ID
   */
  public function getTimetableByClass($class_id)
  {
    $query = "
            SELECT 
                t.id,
                t.day_of_week,
                t.time_slot,
                t.room,
                s.subject_code,
                s.subject_title,
                te.full_name as teacher_name
            FROM timetable t
            LEFT JOIN subjects s ON t.subject_id = s.id
            LEFT JOIN teachers te ON t.teacher_id = te.id
            WHERE t.class_id = :class_id
            ORDER BY 
                FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                t.time_slot ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':class_id', $class_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get timetable by teacher ID
   */
  public function getTimetableByTeacher($teacher_id)
  {
    $query = "
            SELECT 
                t.id,
                t.day_of_week,
                t.time_slot,
                t.room,
                s.subject_code,
                s.subject_title,
                c.name as class_name,
                c.grade as class_grade
            FROM timetable t
            LEFT JOIN subjects s ON t.subject_id = s.id
            LEFT JOIN classes c ON t.class_id = c.id
            WHERE t.teacher_id = :teacher_id
            ORDER BY 
                FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                t.time_slot ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get today's timetable for a student
   */
  public function getTodayTimetable($student_id)
  {
    $today = date('l');
    $query = "
            SELECT 
                t.id,
                t.time_slot,
                t.room,
                s.subject_code,
                s.subject_title,
                te.full_name as teacher_name,
                c.name as class_name,
                CASE 
                    WHEN t.time_slot < CURTIME() THEN 'Completed'
                    WHEN t.time_slot <= ADDTIME(CURTIME(), '01:00:00') THEN 'Ongoing'
                    ELSE 'Upcoming'
                END as status
            FROM timetable t
            LEFT JOIN subjects s ON t.subject_id = s.id
            LEFT JOIN teachers te ON t.teacher_id = te.id
            LEFT JOIN classes c ON t.class_id = c.id
            LEFT JOIN students st ON st.class_id = c.id
            WHERE st.id = :student_id AND t.day_of_week = :day
            ORDER BY t.time_slot ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->bindValue(':day', $today);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get timetable distribution by subject
   */
  public function getSubjectDistribution($student_id)
  {
    $query = "
            SELECT 
                s.subject_title,
                COUNT(*) as period_count
            FROM timetable t
            LEFT JOIN subjects s ON t.subject_id = s.id
            LEFT JOIN classes c ON t.class_id = c.id
            LEFT JOIN students st ON st.class_id = c.id
            WHERE st.id = :student_id
            GROUP BY s.subject_title
            ORDER BY period_count DESC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Check if a time slot is free
   */
  public function isTimeSlotFree($student_id, $day, $time_slot)
  {
    $query = "
            SELECT COUNT(*) as count
            FROM timetable t
            LEFT JOIN classes c ON t.class_id = c.id
            LEFT JOIN students st ON st.class_id = c.id
            WHERE st.id = :student_id 
                AND t.day_of_week = :day 
                AND t.time_slot = :time_slot
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->bindValue(':day', $day);
    $stmt->bindValue(':time_slot', $time_slot);
    $stmt->execute();

    $result = $stmt->fetch();
    return $result['count'] == 0;
  }

  /**
   * Get total periods per week for a student
   */
  public function getTotalPeriods($student_id)
  {
    $query = "
            SELECT COUNT(*) as total
            FROM timetable t
            LEFT JOIN classes c ON t.class_id = c.id
            LEFT JOIN students st ON st.class_id = c.id
            WHERE st.id = :student_id
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':student_id', $student_id);
    $stmt->execute();

    $result = $stmt->fetch();
    return $result['total'] ?? 0;
  }
}

// Usage example:
/*
$timetableProcess = new TimetableProcess($conn);

// Get timetable for a student
$timetable = $timetableProcess->getTimetableByStudent($student_id);

// Get today's timetable
$today = $timetableProcess->getTodayTimetable($student_id);

// Get subject distribution
$distribution = $timetableProcess->getSubjectDistribution($student_id);

// Get total periods
$total = $timetableProcess->getTotalPeriods($student_id);
*/
?>