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
                t.created_at,
                s.id as subject_id,
                s.subject_title,
                s.subject_code,
                s.status as subject_status,
                c.id as class_id,
                c.name as class_name,
                c.grade as class_grade,
                c.room_no as class_room
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
   * Get timetable for a specific day
   */
  public function getTimetableByDay($teacher_id, $day)
  {
    $query = "
            SELECT 
                t.id,
                t.time_slot,
                t.room,
                s.subject_title,
                s.subject_code,
                c.name as class_name,
                c.grade as class_grade
            FROM timetable t
            LEFT JOIN subjects s ON t.subject_id = s.id
            LEFT JOIN classes c ON t.class_id = c.id
            WHERE t.teacher_id = :teacher_id AND t.day_of_week = :day
            ORDER BY t.time_slot ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->bindValue(':day', $day);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get today's timetable
   */
  public function getTodayTimetable($teacher_id)
  {
    $today = date('l'); // Monday, Tuesday, etc.
    return $this->getTimetableByDay($teacher_id, $today);
  }

  /**
   * Get timetable statistics
   */
  public function getTimetableStats($teacher_id)
  {
    $query = "
            SELECT 
                COUNT(*) as total_classes,
                COUNT(DISTINCT day_of_week) as total_days,
                COUNT(DISTINCT subject_id) as total_subjects,
                COUNT(DISTINCT class_id) as total_classes_taught
            FROM timetable
            WHERE teacher_id = :teacher_id
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Get next upcoming class
   */
  public function getNextClass($teacher_id)
  {
    $today = date('l');
    $current_time = date('H:i');

    $query = "
            SELECT 
                t.id,
                t.day_of_week,
                t.time_slot,
                t.room,
                s.subject_title,
                s.subject_code,
                c.id as class_id,
                c.name as class_name,
                c.grade as class_grade
            FROM timetable t
            LEFT JOIN subjects s ON t.subject_id = s.id
            LEFT JOIN classes c ON t.class_id = c.id
            WHERE t.teacher_id = :teacher_id
                AND (
                    (t.day_of_week = :today AND t.time_slot > :current_time)
                    OR FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday') > 
                       FIELD(:today, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday')
                )
            ORDER BY 
                FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                t.time_slot ASC
            LIMIT 1
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->bindValue(':today', $today);
    $stmt->bindValue(':current_time', $current_time);
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Get classes by subject
   */
  public function getTimetableBySubject($teacher_id, $subject_id)
  {
    $query = "
            SELECT 
                t.id,
                t.day_of_week,
                t.time_slot,
                t.room,
                c.name as class_name,
                c.grade as class_grade
            FROM timetable t
            LEFT JOIN classes c ON t.class_id = c.id
            WHERE t.teacher_id = :teacher_id AND t.subject_id = :subject_id
            ORDER BY 
                FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                t.time_slot ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->bindValue(':subject_id', $subject_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get classes by class ID
   */
  public function getTimetableByClass($teacher_id, $class_id)
  {
    $query = "
            SELECT 
                t.id,
                t.day_of_week,
                t.time_slot,
                t.room,
                s.subject_title,
                s.subject_code
            FROM timetable t
            LEFT JOIN subjects s ON t.subject_id = s.id
            WHERE t.teacher_id = :teacher_id AND t.class_id = :class_id
            ORDER BY 
                FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                t.time_slot ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->bindValue(':class_id', $class_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Check if a time slot is available
   */
  public function isTimeSlotAvailable($teacher_id, $day, $time_slot)
  {
    $query = "
            SELECT COUNT(*) as count
            FROM timetable
            WHERE teacher_id = :teacher_id 
                AND day_of_week = :day 
                AND time_slot = :time_slot
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->bindValue(':day', $day);
    $stmt->bindValue(':time_slot', $time_slot);
    $stmt->execute();

    $result = $stmt->fetch();
    return $result['count'] == 0;
  }

  /**
   * Add a new timetable entry
   */
  public function addTimetableEntry($teacher_id, $day, $time_slot, $subject_id, $class_id, $room = null)
  {
    // Check if slot is available
    if (!$this->isTimeSlotAvailable($teacher_id, $day, $time_slot)) {
      return ['success' => false, 'message' => 'Time slot is already occupied'];
    }

    $query = "
            INSERT INTO timetable (teacher_id, day_of_week, time_slot, subject_id, class_id, room)
            VALUES (:teacher_id, :day, :time_slot, :subject_id, :class_id, :room)
        ";

    try {
      $stmt = $this->conn->prepare($query);
      $stmt->bindValue(':teacher_id', $teacher_id);
      $stmt->bindValue(':day', $day);
      $stmt->bindValue(':time_slot', $time_slot);
      $stmt->bindValue(':subject_id', $subject_id);
      $stmt->bindValue(':class_id', $class_id);
      $stmt->bindValue(':room', $room);
      $stmt->execute();

      return ['success' => true, 'message' => 'Timetable entry added successfully'];
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Update a timetable entry
   */
  public function updateTimetableEntry($entry_id, $day = null, $time_slot = null, $room = null)
  {
    $updates = [];
    $params = [':id' => $entry_id];

    if ($day !== null) {
      $updates[] = "day_of_week = :day";
      $params[':day'] = $day;
    }
    if ($time_slot !== null) {
      $updates[] = "time_slot = :time_slot";
      $params[':time_slot'] = $time_slot;
    }
    if ($room !== null) {
      $updates[] = "room = :room";
      $params[':room'] = $room;
    }

    if (empty($updates)) {
      return ['success' => false, 'message' => 'No fields to update'];
    }

    $query = "UPDATE timetable SET " . implode(', ', $updates) . " WHERE id = :id";

    try {
      $stmt = $this->conn->prepare($query);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }
      $stmt->execute();

      return ['success' => true, 'message' => 'Timetable entry updated successfully'];
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Delete a timetable entry
   */
  public function deleteTimetableEntry($entry_id)
  {
    $query = "DELETE FROM timetable WHERE id = :id";

    try {
      $stmt = $this->conn->prepare($query);
      $stmt->bindValue(':id', $entry_id);
      $stmt->execute();

      return ['success' => true, 'message' => 'Timetable entry deleted successfully'];
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Get timetable by week range
   */
  public function getTimetableByDateRange($teacher_id, $start_date, $end_date)
  {
    // This would require a more complex query if you store dates
    // For now, we'll just return the weekly schedule
    return $this->getTimetableByTeacher($teacher_id);
  }
}

// Usage example:
/*
$timetable = new TimetableProcess($conn);

// Get all timetable entries for a teacher
$timetable_entries = $timetable->getTimetableByTeacher($teacher_id);

// Get today's schedule
$today_schedule = $timetable->getTodayTimetable($teacher_id);

// Get next upcoming class
$next_class = $timetable->getNextClass($teacher_id);

// Add a new timetable entry
$result = $timetable->addTimetableEntry(
    $teacher_id, 
    'Monday', 
    '09:00', 
    $subject_id, 
    $class_id, 
    'Room 201'
);

// Get timetable statistics
$stats = $timetable->getTimetableStats($teacher_id);
*/
?>