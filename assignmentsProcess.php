<?php
// AssignmentsProcess.php
require_once '../database/connect.php';

class AssignmentsProcess
{
  private $conn;

  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  /**
   * Get assignments by teacher with filters
   */
  public function getAssignmentsByTeacher($teacher_id, $filters = [])
  {
    $query = "
            SELECT 
                a.id,
                a.title,
                a.description,
                a.assigned_date,
                a.due_date,
                a.status as assignment_status,
                a.attachment,
                a.created_at,
                a.updated_at,
                s.id as subject_id,
                s.subject_title,
                s.subject_code,
                c.id as class_id,
                c.name as class_name,
                c.grade as class_grade,
                (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'Active') as total_students,
                (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'Active' AND st.gender = 'Male') as male_students,
                (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'Active' AND st.gender = 'Female') as female_students
            FROM assignments a
            LEFT JOIN subjects s ON a.subject_id = s.id
            LEFT JOIN classes c ON a.class_id = c.id
            WHERE a.teacher_id = :teacher_id
        ";

    $params = [':teacher_id' => $teacher_id];

    // Apply filters
    if (!empty($filters['search'])) {
      $query .= " AND (a.title LIKE :search OR a.description LIKE :search)";
      $params[':search'] = '%' . $filters['search'] . '%';
    }

    if (!empty($filters['subject_id'])) {
      $query .= " AND a.subject_id = :subject_id";
      $params[':subject_id'] = $filters['subject_id'];
    }

    if (!empty($filters['class_id'])) {
      $query .= " AND a.class_id = :class_id";
      $params[':class_id'] = $filters['class_id'];
    }

    if (!empty($filters['status'])) {
      $query .= " AND a.status = :status";
      $params[':status'] = $filters['status'];
    }

    $query .= " ORDER BY a.due_date ASC, a.created_at DESC";

    $stmt = $this->conn->prepare($query);
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get assignment by ID
   */
  public function getAssignmentById($assignment_id, $teacher_id = null)
  {
    $query = "
            SELECT 
                a.*,
                s.subject_title,
                s.subject_code,
                c.name as class_name,
                c.grade as class_grade,
                t.full_name as teacher_name
            FROM assignments a
            LEFT JOIN subjects s ON a.subject_id = s.id
            LEFT JOIN classes c ON a.class_id = c.id
            LEFT JOIN teachers t ON a.teacher_id = t.id
            WHERE a.id = :id
        ";

    if ($teacher_id) {
      $query .= " AND a.teacher_id = :teacher_id";
    }

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':id', $assignment_id);
    if ($teacher_id) {
      $stmt->bindValue(':teacher_id', $teacher_id);
    }
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Create a new assignment
   */
  public function createAssignment($data)
  {
    $query = "
            INSERT INTO assignments (
                teacher_id, subject_id, class_id, section_id, 
                title, description, assigned_date, due_date, 
                attachment, status
            ) VALUES (
                :teacher_id, :subject_id, :class_id, :section_id,
                :title, :description, :assigned_date, :due_date,
                :attachment, :status
            )
        ";

    try {
      $stmt = $this->conn->prepare($query);
      $stmt->bindValue(':teacher_id', $data['teacher_id']);
      $stmt->bindValue(':subject_id', $data['subject_id']);
      $stmt->bindValue(':class_id', $data['class_id']);
      $stmt->bindValue(':section_id', $data['section_id'] ?? null);
      $stmt->bindValue(':title', $data['title']);
      $stmt->bindValue(':description', $data['description'] ?? '');
      $stmt->bindValue(':assigned_date', $data['assigned_date'] ?? date('Y-m-d'));
      $stmt->bindValue(':due_date', $data['due_date']);
      $stmt->bindValue(':attachment', $data['attachment'] ?? null);
      $stmt->bindValue(':status', $data['status'] ?? 'active');

      if ($stmt->execute()) {
        return [
          'success' => true,
          'id' => $this->conn->lastInsertId(),
          'message' => 'Assignment created successfully'
        ];
      } else {
        return ['success' => false, 'message' => 'Failed to create assignment'];
      }
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Update an assignment
   */
  public function updateAssignment($assignment_id, $data, $teacher_id = null)
  {
    $updates = [];
    $params = [':id' => $assignment_id];

    $allowed_fields = ['title', 'description', 'due_date', 'status', 'attachment', 'section_id'];
    foreach ($allowed_fields as $field) {
      if (isset($data[$field])) {
        $updates[] = "$field = :$field";
        $params[":$field"] = $data[$field];
      }
    }

    if (empty($updates)) {
      return ['success' => false, 'message' => 'No fields to update'];
    }

    $updates[] = "updated_at = NOW()";

    $query = "UPDATE assignments SET " . implode(', ', $updates) . " WHERE id = :id";

    if ($teacher_id) {
      $query .= " AND teacher_id = :teacher_id";
      $params[':teacher_id'] = $teacher_id;
    }

    try {
      $stmt = $this->conn->prepare($query);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }

      if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Assignment updated successfully'];
      } else {
        return ['success' => false, 'message' => 'Failed to update assignment'];
      }
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Delete an assignment
   */
  public function deleteAssignment($assignment_id, $teacher_id = null)
  {
    $query = "DELETE FROM assignments WHERE id = :id";

    if ($teacher_id) {
      $query .= " AND teacher_id = :teacher_id";
    }

    try {
      $stmt = $this->conn->prepare($query);
      $stmt->bindValue(':id', $assignment_id);
      if ($teacher_id) {
        $stmt->bindValue(':teacher_id', $teacher_id);
      }

      if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Assignment deleted successfully'];
      } else {
        return ['success' => false, 'message' => 'Failed to delete assignment'];
      }
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Get assignment statistics
   */
  public function getAssignmentStats($teacher_id)
  {
    $query = "
            SELECT 
                COUNT(*) as total_assignments,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_assignments,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_assignments,
                SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as draft_assignments,
                SUM(CASE WHEN status = 'active' AND due_date < CURDATE() THEN 1 ELSE 0 END) as overdue_assignments
            FROM assignments
            WHERE teacher_id = :teacher_id
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Get assignments by subject
   */
  public function getAssignmentsBySubject($teacher_id, $subject_id)
  {
    $query = "
            SELECT 
                a.*,
                c.name as class_name,
                c.grade as class_grade
            FROM assignments a
            LEFT JOIN classes c ON a.class_id = c.id
            WHERE a.teacher_id = :teacher_id AND a.subject_id = :subject_id
            ORDER BY a.due_date ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->bindValue(':subject_id', $subject_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get assignments by class
   */
  public function getAssignmentsByClass($teacher_id, $class_id)
  {
    $query = "
            SELECT 
                a.*,
                s.subject_title,
                s.subject_code
            FROM assignments a
            LEFT JOIN subjects s ON a.subject_id = s.id
            WHERE a.teacher_id = :teacher_id AND a.class_id = :class_id
            ORDER BY a.due_date ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->bindValue(':class_id', $class_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get upcoming assignments
   */
  public function getUpcomingAssignments($teacher_id, $days_ahead = 7)
  {
    $query = "
            SELECT 
                a.*,
                s.subject_title,
                c.name as class_name,
                c.grade as class_grade
            FROM assignments a
            LEFT JOIN subjects s ON a.subject_id = s.id
            LEFT JOIN classes c ON a.class_id = c.id
            WHERE a.teacher_id = :teacher_id
                AND a.status = 'active'
                AND a.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days_ahead DAY)
            ORDER BY a.due_date ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->bindValue(':days_ahead', $days_ahead);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get overdue assignments
   */
  public function getOverdueAssignments($teacher_id)
  {
    $query = "
            SELECT 
                a.*,
                s.subject_title,
                c.name as class_name,
                c.grade as class_grade,
                DATEDIFF(CURDATE(), a.due_date) as days_overdue
            FROM assignments a
            LEFT JOIN subjects s ON a.subject_id = s.id
            LEFT JOIN classes c ON a.class_id = c.id
            WHERE a.teacher_id = :teacher_id
                AND a.status = 'active'
                AND a.due_date < CURDATE()
            ORDER BY a.due_date ASC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get assignment submission status
   */
  public function getAssignmentSubmissionStatus($assignment_id)
  {
    // This would require a submissions table
    // For now, we'll return placeholder data
    $query = "
            SELECT 
                COUNT(*) as total_students,
                (SELECT COUNT(*) FROM students WHERE class_id = (SELECT class_id FROM assignments WHERE id = :id) AND status = 'Active') as total_enrolled
            FROM students
            WHERE class_id = (SELECT class_id FROM assignments WHERE id = :id) AND status = 'Active'
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':id', $assignment_id);
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Get all subjects for a teacher (for dropdowns)
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
   * Get all classes for a teacher (for dropdowns)
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
}

// Usage example:
/*
$assignmentProcess = new AssignmentsProcess($conn);

// Get all assignments
$assignments = $assignmentProcess->getAssignmentsByTeacher($teacher_id);

// Get assignment stats
$stats = $assignmentProcess->getAssignmentStats($teacher_id);

// Create a new assignment
$result = $assignmentProcess->createAssignment([
    'teacher_id' => $teacher_id,
    'subject_id' => 1,
    'class_id' => 1,
    'title' => 'Math Homework',
    'description' => 'Complete exercises 1-10',
    'due_date' => '2026-09-20',
    'status' => 'active'
]);

// Get upcoming assignments
$upcoming = $assignmentProcess->getUpcomingAssignments($teacher_id, 7);

// Get overdue assignments
$overdue = $assignmentProcess->getOverdueAssignments($teacher_id);
*/
?>