<?php
require_once '../database/connect.php';

class Timetable_process
{
  private $conn;
  public $id, $class_id, $day_of_week, $subject_id, $teacher_id, $room, $time_slot, $created_at;

  // Constructor with database connection
  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  // Set data method
  public function setData($id, $class_id, $day_of_week, $subject_id, $teacher_id, $room, $time_slot, $created_at)
  {
    $this->id = $id;
    $this->class_id = $class_id;
    $this->day_of_week = $day_of_week;
    $this->subject_id = $subject_id;
    $this->teacher_id = $teacher_id;
    $this->room = $room;
    $this->time_slot = $time_slot;
    $this->created_at = $created_at;
  }

  // Insert timetable entry
  public function insert()
  {
    try {
      $query = "INSERT INTO timetable (class_id, day_of_week, subject_id, teacher_id, room, time_slot, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";
      $stmt = $this->conn->prepare($query);
      $result = $stmt->execute([
        $this->class_id,
        $this->day_of_week,
        $this->subject_id,
        $this->teacher_id,
        $this->room,
        $this->time_slot,
        $this->created_at
      ]);
      return $result ? $this->conn->lastInsertId() : false;
    } catch (PDOException $e) {
      error_log("Insert timetable error: " . $e->getMessage());
      return false;
    }
  }

  // Update timetable entry
  public function update()
  {
    try {
      $query = "UPDATE timetable SET 
                      class_id = ?, 
                      day_of_week = ?, 
                      subject_id = ?, 
                      teacher_id = ?, 
                      room = ?, 
                      time_slot = ? 
                      WHERE id = ?";
      $stmt = $this->conn->prepare($query);
      $result = $stmt->execute([
        $this->class_id,
        $this->day_of_week,
        $this->subject_id,
        $this->teacher_id,
        $this->room,
        $this->time_slot,
        $this->id
      ]);
      return $result && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Update timetable error: " . $e->getMessage());
      return false;
    }
  }

  // Delete timetable entry
  public function delete()
  {
    try {
      $query = "DELETE FROM timetable WHERE id = ?";
      $stmt = $this->conn->prepare($query);
      $result = $stmt->execute([$this->id]);
      return $result && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Delete timetable error: " . $e->getMessage());
      return false;
    }
  }

  // Get timetable by class
  public function getTimetableByClass($class_id)
  {
    try {
      $query = "SELECT t.*, 
                      s.subject_title, 
                      s.subject_code,
                      te.full_name as teacher_name,
                      c.name as class_name,
                      c.grade as class_grade
                      FROM timetable t
                      LEFT JOIN subjects s ON t.subject_id = s.id
                      LEFT JOIN teachers te ON t.teacher_id = te.id
                      LEFT JOIN classes c ON t.class_id = c.id
                      WHERE t.class_id = ?
                      ORDER BY FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
                      t.time_slot";
      $stmt = $this->conn->prepare($query);
      $stmt->execute([$class_id]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get timetable by class error: " . $e->getMessage());
      return [];
    }
  }

  // Get timetable by ID
  public function getTimetableById($id)
  {
    try {
      $query = "SELECT * FROM timetable WHERE id = ?";
      $stmt = $this->conn->prepare($query);
      $stmt->execute([$id]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get timetable by ID error: " . $e->getMessage());
      return null;
    }
  }

  // Get all timetables
  public function getAllTimetables()
  {
    try {
      $query = "SELECT t.*, 
                      s.subject_title, 
                      s.subject_code,
                      te.full_name as teacher_name,
                      c.name as class_name,
                      c.grade as class_grade
                      FROM timetable t
                      LEFT JOIN subjects s ON t.subject_id = s.id
                      LEFT JOIN teachers te ON t.teacher_id = te.id
                      LEFT JOIN classes c ON t.class_id = c.id
                      ORDER BY t.class_id, t.day_of_week, t.time_slot";
      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get all timetables error: " . $e->getMessage());
      return [];
    }
  }

  // Check for conflicts
  public function checkConflict($class_id, $day_of_week, $time_slot, $exclude_id = null)
  {
    try {
      $query = "SELECT COUNT(*) as count FROM timetable 
                      WHERE class_id = ? AND day_of_week = ? AND time_slot = ?";
      $params = [$class_id, $day_of_week, $time_slot];

      if ($exclude_id) {
        $query .= " AND id != ?";
        $params[] = $exclude_id;
      }

      $stmt = $this->conn->prepare($query);
      $stmt->execute($params);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      return $result['count'] > 0;
    } catch (PDOException $e) {
      error_log("Check conflict error: " . $e->getMessage());
      return false;
    }
  }
}
?>