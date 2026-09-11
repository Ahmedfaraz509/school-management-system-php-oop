<?php
// profile_process.php
// Process file for handling profile-related database operations

require_once '../database/connect.php';

class ProfileProcess
{
  private $conn;
  private $student_id;
  private $student_data;

  public function __construct($conn, $student_id = null)
  {
    $this->conn = $conn;
    $this->student_id = $student_id;
    if ($student_id) {
      $this->loadStudentData();
    }
  }

  /**
   * Load student data including class information
   */
  public function loadStudentData()
  {
    $query = "SELECT s.*, c.name as class_name, c.grade, c.id as class_id,
                  CONCAT(s.first_name, ' ', s.last_name) as full_name
                  FROM students s 
                  LEFT JOIN classes c ON s.class_id = c.id 
                  WHERE s.id = ?";
    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_id]);
    $this->student_data = $stmt->fetch();
    return $this->student_data;
  }

  /**
   * Get student data
   */
  public function getStudentData()
  {
    return $this->student_data;
  }

  /**
   * Get student profile data
   */
  public function getProfile()
  {
    if (!$this->student_data) {
      return null;
    }

    // Get guardian/parent information
    $parentQuery = "SELECT * FROM parents WHERE student_id = ? LIMIT 1";
    $stmt = $this->conn->prepare($parentQuery);
    $stmt->execute([$this->student_id]);
    $parent = $stmt->fetch();

    // Get class teacher
    $teacherQuery = "SELECT t.full_name, t.phone, t.email 
                         FROM teachers t
                         JOIN classes c ON c.teacher_id = t.id
                         WHERE c.id = ?";
    $stmt = $this->conn->prepare($teacherQuery);
    $stmt->execute([$this->student_data['class_id']]);
    $teacher = $stmt->fetch();

    // Get attendance summary
    $attendanceQuery = "SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
                            SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
                            SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late
                            FROM attendance 
                            WHERE student_id = ?";
    $stmt = $this->conn->prepare($attendanceQuery);
    $stmt->execute([$this->student_id]);
    $attendance = $stmt->fetch();

    $attendancePercentage = 0;
    if ($attendance && $attendance['total'] > 0) {
      $attendancePercentage = round(($attendance['present'] / $attendance['total']) * 100);
    }

    // Get subject count
    $subjectQuery = "SELECT COUNT(*) as count FROM subjects WHERE class_id = ?";
    $stmt = $this->conn->prepare($subjectQuery);
    $stmt->execute([$this->student_data['class_id']]);
    $subjectCount = $stmt->fetch();

    // Get GPA from results
    $gpaQuery = "SELECT AVG(percentage) as avg_percentage FROM results WHERE student_id = ?";
    $stmt = $this->conn->prepare($gpaQuery);
    $stmt->execute([$this->student_id]);
    $gpaResult = $stmt->fetch();

    $gpa = 0;
    $avgPercentage = 0;
    if ($gpaResult && $gpaResult['avg_percentage']) {
      $avgPercentage = round($gpaResult['avg_percentage'], 1);
      if ($avgPercentage >= 90)
        $gpa = 4.0;
      elseif ($avgPercentage >= 80)
        $gpa = 3.7;
      elseif ($avgPercentage >= 70)
        $gpa = 3.3;
      elseif ($avgPercentage >= 60)
        $gpa = 3.0;
      elseif ($avgPercentage >= 50)
        $gpa = 2.0;
      elseif ($avgPercentage >= 40)
        $gpa = 1.0;
    }

    return [
      'student' => $this->student_data,
      'parent' => $parent,
      'teacher' => $teacher,
      'attendance' => $attendance,
      'attendance_percentage' => $attendancePercentage,
      'subject_count' => $subjectCount['count'] ?? 0,
      'gpa' => $gpa,
      'average_percentage' => $avgPercentage
    ];
  }

  /**
   * Update student profile
   */
  public function updateProfile($data)
  {
    try {
      $query = "UPDATE students 
                      SET first_name = ?, last_name = ?, email = ?, phone = ?, 
                          address = ?, date_of_birth = ?, gender = ?
                      WHERE id = ?";

      $stmt = $this->conn->prepare($query);
      return $stmt->execute([
        $data['first_name'],
        $data['last_name'],
        $data['email'],
        $data['phone'] ?? null,
        $data['address'] ?? null,
        $data['date_of_birth'],
        $data['gender'],
        $this->student_id
      ]);
    } catch (PDOException $e) {
      error_log("Error updating profile: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Update parent/guardian information
   */
  public function updateParent($data)
  {
    try {
      // First check if parent exists
      $checkQuery = "SELECT id FROM parents WHERE student_id = ?";
      $stmt = $this->conn->prepare($checkQuery);
      $stmt->execute([$this->student_id]);
      $existing = $stmt->fetch();

      if ($existing) {
        $query = "UPDATE parents 
                          SET full_name = ?, phone = ?, email = ?, address = ?, relation = ?
                          WHERE student_id = ?";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
          $data['full_name'],
          $data['phone'],
          $data['email'],
          $data['address'] ?? null,
          $data['relation'] ?? 1,
          $this->student_id
        ]);
      } else {
        // Insert new parent
        $query = "INSERT INTO parents (student_id, parent_uid, full_name, phone, email, address, relation) 
                          VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = $this->conn->prepare($query);
        return $stmt->execute([
          $this->student_id,
          'PAR-' . strtoupper(uniqid()),
          $data['full_name'],
          $data['phone'],
          $data['email'],
          $data['address'] ?? null,
          $data['relation'] ?? 1
        ]);
      }
    } catch (PDOException $e) {
      error_log("Error updating parent: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Update student photo
   */
  public function updatePhoto($photo_url)
  {
    try {
      $query = "UPDATE students SET photo_url = ? WHERE id = ?";
      $stmt = $this->conn->prepare($query);
      return $stmt->execute([$photo_url, $this->student_id]);
    } catch (PDOException $e) {
      error_log("Error updating photo: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Get student achievements
   */
  public function getAchievements()
  {
    // This would typically come from a student_achievements table
    // For demo purposes, return sample data
    return [
      ['icon' => 'bi-trophy', 'title' => 'Inter-House Quiz', 'detail' => '2nd Place'],
      ['icon' => 'bi-award', 'title' => 'Merit Scholarship', 'detail' => '10% waiver'],
      ['icon' => 'bi-star', 'title' => 'Monthly Star Award', 'detail' => 'May 2026'],
      ['icon' => 'bi-mortarboard', 'title' => 'Science Club', 'detail' => 'Member'],
      ['icon' => 'bi-people', 'title' => 'Class Monitor', 'detail' => '2025–26']
    ];
  }

  /**
   * Get recent login activity
   */
  public function getLoginActivity()
  {
    // This would come from a login_history table
    // For demo purposes, return sample data
    return [
      ['icon' => 'bi-laptop', 'device' => 'Chrome on Windows', 'time' => 'Today, 08:02 AM'],
      ['icon' => 'bi-phone', 'device' => 'Mobile app · Android', 'time' => 'Yesterday, 07:41 PM'],
      ['icon' => 'bi-laptop', 'device' => 'Library computer', 'time' => '22 Aug 2026'],
      ['icon' => 'bi-phone', 'device' => 'Mobile app · Android', 'time' => '21 Aug 2026']
    ];
  }

  /**
   * Get uploaded documents
   */
  public function getDocuments()
  {
    // This would come from a documents table
    // For demo purposes, return sample data
    return [
      ['icon' => 'bi-file-earmark-text', 'title' => 'School Identity Card', 'status' => 'Verified', 'status_class' => 'p-ok'],
      ['icon' => 'bi-file-earmark-pdf', 'title' => 'Birth Certificate', 'status' => 'Verified', 'status_class' => 'p-ok'],
      ['icon' => 'bi-file-earmark-medical', 'title' => 'Vaccination Record', 'status' => 'Under Review', 'status_class' => 'p-warn'],
      ['icon' => 'bi-camera', 'title' => 'Profile Photograph', 'status' => 'Approved', 'status_class' => 'p-ok']
    ];
  }

  /**
   * Get gender options
   */
  public static function getGenderOptions()
  {
    return ['Male', 'Female', 'Other'];
  }

  /**
   * Get relation options
   */
  public static function getRelationOptions()
  {
    return [
      1 => 'Father',
      2 => 'Mother',
      3 => 'Guardian'
    ];
  }

  /**
   * Get blood group options
   */
  public static function getBloodGroups()
  {
    return ['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'];
  }

  /**
   * Format date for display
   */
  public static function formatDate($date, $format = 'd F Y')
  {
    if (!$date)
      return 'N/A';
    return date($format, strtotime($date));
  }

  /**
   * Get age from date of birth
   */
  public static function getAge($dob)
  {
    if (!$dob)
      return 'N/A';
    $birthDate = new DateTime($dob);
    $today = new DateTime('today');
    $age = $birthDate->diff($today);
    return $age->y . ' years';
  }
}

// Helper functions for use in templates
function formatDate($date, $format = 'd F Y')
{
  return ProfileProcess::formatDate($date, $format);
}

function getAge($dob)
{
  return ProfileProcess::getAge($dob);
}
?>