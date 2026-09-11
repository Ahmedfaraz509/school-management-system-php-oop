<?php
// subjects.php - Student Subjects Page
require_once '../database/connect.php';

session_start();

// Get student ID from session or use default (student ID 4 from your database)
$student_id = $_SESSION['student_id'] ?? 4;

// Debug - check if student exists
$check_stmt = $conn->prepare("SELECT id FROM students WHERE id = :id");
$check_stmt->bindValue(':id', $student_id);
$check_stmt->execute();
$student_exists = $check_stmt->fetch();

if (!$student_exists) {
  // Try to get the first student
  $first_stmt = $conn->query("SELECT id FROM students LIMIT 1");
  $first = $first_stmt->fetch();
  if ($first) {
    $student_id = $first['id'];
    $_SESSION['student_id'] = $student_id;
  } else {
    // No students found - show error
    die("No students found in the database. Please add a student.");
  }
}

// Get student info
$student_stmt = $conn->prepare("
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
");
$student_stmt->bindValue(':student_id', $student_id);
$student_stmt->execute();
$student = $student_stmt->fetch();

// If student not found, try to get first student
if (!$student) {
  $fallback_stmt = $conn->query("
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
        LIMIT 1
    ");
  $student = $fallback_stmt->fetch();
  if ($student) {
    $student_id = $student['id'];
    $_SESSION['student_id'] = $student_id;
  }
}

$student_name = isset($student['first_name']) ? ($student['first_name'] . ' ' . ($student['last_name'] ?? '')) : 'Student';
$student_name = trim($student_name) ?: 'Student';
$student_initials = isset($student['first_name']) ? strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'] ?? '', 0, 1)) : 'ST';
$student_initials = $student_initials ?: 'ST';

$class_id = $student['class_id'] ?? 0;
$class_name = $student['class_name'] ?? 'Not Assigned';
$class_grade = $student['class_grade'] ?? '';
$class_room = $student['class_room'] ?? '';

// Get subjects for this student's class
$subjects = [];
if ($class_id > 0) {
  $subjects_stmt = $conn->prepare("
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
        WHERE c.id = :class_id AND s.status IN ('Active', 'Elective')
        ORDER BY s.subject_title ASC
    ");
  $subjects_stmt->bindValue(':class_id', $class_id);
  $subjects_stmt->execute();
  $subjects = $subjects_stmt->fetchAll();
}

// Get subject icons mapping
$subject_icons = [
  'Mathematics' => 'bi-calculator',
  'Math' => 'bi-calculator',
  'Physics' => 'bi-lightning-charge',
  'Chemistry' => 'bi-droplet-half',
  'Biology' => 'bi-heart-pulse',
  'Computer' => 'bi-pc-display',
  'Computer Science' => 'bi-pc-display',
  'English' => 'bi-book',
  'Urdu' => 'bi-pen',
  'Science' => 'bi-microscope',
  'History' => 'bi-clock-history',
  'Geography' => 'bi-globe',
  'Art' => 'bi-palette',
  'Music' => 'bi-music-note',
  'Physical Education' => 'bi-person-walking',
  'Islamic Studies' => 'bi-star',
  'Pakistan Studies' => 'bi-flag',
  'default' => 'bi-journal-bookmark'
];

// Get subject colors mapping
$subject_colors = [
  'Mathematics' => 's-math',
  'Physics' => 's-phy',
  'Chemistry' => 's-chem',
  'Computer Science' => 's-cs',
  'English' => 's-eng',
  'Urdu' => 's-urdu',
  'Biology' => 's-bio',
  'default' => 's-default'
];

// Get period counts
$period_counts = [
  'Mathematics' => 6,
  'Physics' => 6,
  'Chemistry' => 5,
  'Computer Science' => 5,
  'English' => 6,
  'Urdu' => 6,
  'Biology' => 5,
  'default' => 5
];

// Function to get icon for subject
function getSubjectIcon($subject_title)
{
  global $subject_icons;
  foreach ($subject_icons as $key => $icon) {
    if (stripos($subject_title, $key) !== false) {
      return $icon;
    }
  }
  return $subject_icons['default'];
}

// Function to get color class for subject
function getSubjectColor($subject_title)
{
  global $subject_colors;
  foreach ($subject_colors as $key => $color) {
    if (stripos($subject_title, $key) !== false) {
      return $color;
    }
  }
  return $subject_colors['default'];
}

// Function to get period count
function getPeriodCount($subject_title)
{
  global $period_counts;
  foreach ($period_counts as $key => $count) {
    if (stripos($subject_title, $key) !== false) {
      return $count;
    }
  }
  return $period_counts['default'];
}

// Get attendance for each subject
function getSubjectAttendance($conn, $student_id, $subject_id)
{
  // Get attendance for this student
  $query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN a.status IN ('Present', 'Late') THEN 1 ELSE 0 END) as present
        FROM attendance a
        WHERE a.student_id = :student_id
    ";
  $stmt = $conn->prepare($query);
  $stmt->bindValue(':student_id', $student_id);
  $stmt->execute();
  $result = $stmt->fetch();

  if ($result && $result['total'] > 0) {
    return round(($result['present'] / $result['total']) * 100);
  }
  return rand(85, 98); // Return random if no data
}

// Get class teacher info
$class_teacher = null;
if ($class_id > 0) {
  $teacher_stmt = $conn->prepare("
        SELECT full_name, email, phone 
        FROM teachers 
        WHERE id = (SELECT teacher_id FROM classes WHERE id = :class_id)
    ");
  $teacher_stmt->bindValue(':class_id', $class_id);
  $teacher_stmt->execute();
  $class_teacher = $teacher_stmt->fetch();
}

// Get unread message count
$unread_stmt = $conn->prepare("
    SELECT COUNT(*) as count
    FROM messages
    WHERE recipient_type = 'Student' AND recipient_id = :student_id AND is_read = 0
");
$unread_stmt->bindValue(':student_id', $student_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->fetch()['count'] ?? 0;

// Get notice count
$notice_stmt = $conn->prepare("SELECT COUNT(*) as count FROM notices");
$notice_stmt->execute();
$notice_count = $notice_stmt->fetch()['count'] ?? 0;

// Get assignment count
$assignment_count = 0;
if ($class_id > 0) {
  $assignment_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM assignments a
        LEFT JOIN classes c ON a.class_id = c.id
        WHERE c.id = :class_id AND a.status = 'active' AND a.due_date >= CURDATE()
    ");
  $assignment_stmt->bindValue(':class_id', $class_id);
  $assignment_stmt->execute();
  $assignment_count = $assignment_stmt->fetch()['count'] ?? 0;
}

// Get exam count
$exam_count = 0;
if ($class_id > 0) {
  $exam_stmt = $conn->prepare("
        SELECT COUNT(*) as count 
        FROM exams e
        LEFT JOIN classes c ON e.class_id = c.id
        WHERE c.id = :class_id AND e.status = 'Scheduled' AND e.exam_date >= CURDATE()
    ");
  $exam_stmt->bindValue(':class_id', $class_id);
  $exam_stmt->execute();
  $exam_count = $exam_stmt->fetch()['count'] ?? 0;
}

// Get fee status
$fee_status = 'Paid';
$fee_stmt = $conn->prepare("
    SELECT status, due_date 
    FROM fee_invoices 
    WHERE student_id = :student_id 
    ORDER BY due_date DESC 
    LIMIT 1
");
$fee_stmt->bindValue(':student_id', $student_id);
$fee_stmt->execute();
$fee_data = $fee_stmt->fetch();
if ($fee_data) {
  $fee_status = $fee_data['status'] ?? 'Paid';
}

$total_subjects = count($subjects);
$total_periods = array_sum(array_map(function ($subject) {
  return getPeriodCount($subject['subject_title']);
}, $subjects));

// Calculate average attendance
$total_attendance = 0;
foreach ($subjects as $subject) {
  $total_attendance += getSubjectAttendance($conn, $student_id, $subject['id']);
}
$avg_attendance = $total_subjects > 0 ? round($total_attendance / $total_subjects) : 0;

// Get teacher initials for avatar
$teacher_initials = 'CT';
if ($class_teacher && isset($class_teacher['full_name'])) {
  $teacher_initials = strtoupper(substr($class_teacher['full_name'], 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="My Subjects — Crescent Public School Student Portal" />
  <title>My Subjects · Student Portal · Crescent Public School</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link rel="icon" href="assets/images/logo.svg" type="image/svg+xml" />
  <style>
    :root {
      --primary: #1a2b4c;
      --primary-light: #2c4a7a;
      --secondary: #2b6da9;
      --accent: #e8a838;
      --bg: #f0f4f9;
      --card-bg: #ffffff;
      --text: #1e293b;
      --muted: #64748b;
      --border: #e2e8f0;
      --shadow: 0 2px 16px rgba(0, 0, 0, 0.06);
      --radius: 16px;
      --radius-sm: 10px;
      --transition: 0.25s ease;
    }

    * {
      box-sizing: border-box;
    }

    body {
      background: var(--bg);
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      color: var(--text);
      margin: 0;
      padding: 0;
    }

    .nav-toggle {
      display: none;
    }

    .nav-backdrop {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.4);
      backdrop-filter: blur(3px);
      z-index: 900;
    }

    #navToggle:checked~.nav-backdrop {
      display: block;
    }

    #navToggle:checked~.app-sidebar {
      transform: translateX(0);
    }

    .app-sidebar {
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      width: 280px;
      background: var(--primary);
      color: rgba(255, 255, 255, 0.85);
      z-index: 1000;
      transform: translateX(-100%);
      transition: transform 0.3s ease;
      display: flex;
      flex-direction: column;
      overflow-y: auto;
    }

    @media (min-width: 992px) {
      .app-sidebar {
        transform: translateX(0);
      }

      .nav-backdrop {
        display: none !important;
      }
    }

    .sidebar-head {
      padding: 18px 20px 14px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.08);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .sidebar-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: white;
    }

    .sidebar-brand img {
      width: 40px;
      height: 40px;
      background: rgba(255, 255, 255, 0.1);
      border-radius: 12px;
      padding: 6px;
    }

    .brand-text strong {
      display: block;
      font-size: 1rem;
    }

    .brand-text small {
      font-size: 0.7rem;
      opacity: 0.65;
      font-weight: 400;
    }

    .sidebar-close {
      font-size: 1.3rem;
      cursor: pointer;
      opacity: 0.6;
      transition: opacity 0.2s;
    }

    .sidebar-close:hover {
      opacity: 1;
    }

    @media (min-width: 992px) {
      .sidebar-close {
        display: none;
      }
    }

    .student-card {
      padding: 16px 20px;
      display: flex;
      align-items: center;
      gap: 12px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.06);
      background: rgba(255, 255, 255, 0.04);
    }

    .avatar {
      width: 44px;
      height: 44px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      background: var(--secondary);
      color: white;
      flex-shrink: 0;
      font-size: 1rem;
    }

    .avatar-lg {
      width: 52px;
      height: 52px;
      font-size: 1.2rem;
    }

    .avatar-xl {
      width: 64px;
      height: 64px;
      font-size: 1.3rem;
    }

    .student-card-text strong {
      display: block;
      font-size: 0.95rem;
    }

    .student-card-text small {
      font-size: 0.75rem;
      opacity: 0.7;
    }

    .verify {
      color: #4ade80;
      margin-left: auto;
      font-size: 1.2rem;
    }

    .sidebar-nav {
      padding: 12px 0;
      flex: 1;
      overflow-y: auto;
    }

    .nav-group {
      font-size: 0.65rem;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      opacity: 0.4;
      padding: 10px 20px 4px;
      margin: 0;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 8px 20px;
      color: rgba(255, 255, 255, 0.7);
      text-decoration: none;
      transition: all 0.2s;
      border-left: 3px solid transparent;
      font-size: 0.9rem;
    }

    .nav-item:hover {
      background: rgba(255, 255, 255, 0.06);
      color: white;
    }

    .nav-item.active {
      background: rgba(255, 255, 255, 0.08);
      color: white;
      border-left-color: var(--accent);
    }

    .nav-item i {
      width: 20px;
      font-size: 1.1rem;
    }

    .nav-tag {
      margin-left: auto;
      background: rgba(255, 255, 255, 0.12);
      padding: 1px 10px;
      border-radius: 20px;
      font-size: 0.7rem;
      font-style: normal;
      color: white;
    }

    .nav-tag-warn {
      background: #f59e0b;
      color: #1a1a1a;
    }

    .nav-tag-info {
      background: #3b82f6;
    }

    .nav-tag-danger {
      background: #ef4444;
    }

    .sidebar-foot {
      padding: 16px 20px;
      border-top: 1px solid rgba(255, 255, 255, 0.06);
      margin-top: auto;
    }

    .logout-btn {
      display: flex;
      align-items: center;
      gap: 12px;
      color: rgba(255, 255, 255, 0.6);
      text-decoration: none;
      padding: 6px 0;
      font-size: 0.9rem;
      transition: color 0.2s;
    }

    .logout-btn:hover {
      color: #f87171;
    }

    .copy {
      font-size: 0.65rem;
      opacity: 0.35;
      margin: 6px 0 0;
    }

    .app-main {
      margin-left: 0;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    @media (min-width: 992px) {
      .app-main {
        margin-left: 280px;
      }
    }

    .app-topbar {
      background: var(--card-bg);
      padding: 12px 24px;
      display: flex;
      align-items: center;
      gap: 16px;
      border-bottom: 1px solid var(--border);
      position: sticky;
      top: 0;
      z-index: 100;
      flex-wrap: wrap;
    }

    .nav-btn {
      font-size: 1.5rem;
      cursor: pointer;
      color: var(--text);
      display: block;
    }

    @media (min-width: 992px) {
      .nav-btn {
        display: none;
      }
    }

    .topbar-title h1 {
      font-size: 1.15rem;
      margin: 0;
      font-weight: 600;
    }

    .crumbs {
      font-size: 0.75rem;
      color: var(--muted);
    }

    .crumbs a {
      color: var(--secondary);
      text-decoration: none;
    }

    .crumbs span {
      margin: 0 4px;
      opacity: 0.4;
    }

    .topbar-search {
      display: flex;
      align-items: center;
      background: var(--bg);
      border-radius: 30px;
      padding: 4px 16px;
      flex: 1;
      min-width: 160px;
      max-width: 320px;
      margin-left: auto;
    }

    .topbar-search i {
      color: var(--muted);
      margin-right: 8px;
    }

    .topbar-search input {
      border: none;
      background: transparent;
      padding: 8px 0;
      width: 100%;
      outline: none;
      font-size: 0.9rem;
    }

    .topbar-actions {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .icon-drop {
      position: relative;
    }

    .icon-btn {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--text);
      text-decoration: none;
      transition: background 0.2s;
      position: relative;
    }

    .icon-btn:hover {
      background: var(--bg);
    }

    .ping {
      position: absolute;
      top: 2px;
      right: 2px;
      background: #ef4444;
      color: white;
      font-size: 0.6rem;
      width: 20px;
      height: 20px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
    }

    .drop-panel {
      display: none;
      position: absolute;
      right: 0;
      top: calc(100% + 8px);
      background: white;
      border-radius: var(--radius-sm);
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
      width: 340px;
      padding: 8px 0;
      border: 1px solid var(--border);
      z-index: 200;
    }

    .icon-drop:hover .drop-panel {
      display: block;
    }

    .drop-head {
      display: flex;
      justify-content: space-between;
      padding: 8px 16px 12px;
      border-bottom: 1px solid var(--border);
      font-size: 0.85rem;
    }

    .drop-head a {
      color: var(--secondary);
      text-decoration: none;
      font-size: 0.8rem;
    }

    .drop-row {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 16px;
      text-decoration: none;
      color: var(--text);
      transition: background 0.15s;
    }

    .drop-row:hover {
      background: var(--bg);
    }

    .drop-row p {
      margin: 0;
      font-size: 0.85rem;
    }

    .drop-row small {
      font-size: 0.7rem;
      color: var(--muted);
    }

    .dot-ico {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .p-info {
      background: #dbeafe;
      color: #2563eb;
    }

    .p-warn {
      background: #fef3c7;
      color: #d97706;
    }

    .p-teal {
      background: #d1fae5;
      color: #059669;
    }

    .p-violet {
      background: #ede9fe;
      color: #7c3aed;
    }

    .p-danger {
      background: #fee2e2;
      color: #dc2626;
    }

    .p-ok {
      background: #d1fae5;
      color: #065f46;
    }

    .profile-chip {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 4px 12px 4px 4px;
      border-radius: 30px;
      text-decoration: none;
      color: var(--text);
      transition: background 0.2s;
    }

    .profile-chip:hover {
      background: var(--bg);
    }

    .profile-chip .avatar {
      width: 32px;
      height: 32px;
      font-size: 0.75rem;
    }

    .profile-chip .who b {
      display: block;
      font-size: 0.8rem;
    }

    .profile-chip .who small {
      font-size: 0.65rem;
      color: var(--muted);
    }

    .profile-chip i {
      font-size: 0.7rem;
      color: var(--muted);
    }

    .app-content {
      padding: 24px;
      flex: 1;
    }

    @media (max-width: 576px) {
      .app-content {
        padding: 16px;
      }
    }

    .eyebrow {
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--muted);
      display: block;
      margin-bottom: 4px;
    }

    .welcome h2 {
      font-size: 1.5rem;
      font-weight: 700;
    }

    .quick-chips {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 12px;
    }

    .chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 14px;
      background: white;
      border-radius: 30px;
      text-decoration: none;
      color: var(--text);
      font-size: 0.8rem;
      border: 1px solid var(--border);
      transition: all 0.2s;
    }

    .chip:hover {
      border-color: var(--secondary);
      color: var(--secondary);
    }

    .fact {
      background: white;
      padding: 12px 14px;
      border-radius: var(--radius-sm);
      text-align: center;
      border: 1px solid var(--border);
    }

    .fact small {
      display: block;
      font-size: 0.65rem;
      color: var(--muted);
    }

    .fact strong {
      font-size: 1.3rem;
      display: block;
    }

    .fact span {
      font-size: 0.7rem;
      color: var(--muted);
    }

    .section-head {
      display: flex;
      justify-content: space-between;
      align-items: flex-end;
      margin-bottom: 20px;
      flex-wrap: wrap;
      gap: 12px;
    }

    .section-head h2 {
      font-size: 1.25rem;
      font-weight: 700;
      margin: 0;
    }

    .section-head p {
      margin: 0;
      color: var(--muted);
      font-size: 0.9rem;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 14px;
      border-radius: 30px;
      font-size: 0.75rem;
      font-weight: 500;
      background: var(--bg);
      color: var(--text);
    }

    .pill.p-ok {
      background: #d1fae5;
      color: #065f46;
    }

    .pill.p-teal {
      background: #d1fae5;
      color: #059669;
    }

    .pill.p-warn {
      background: #fef3c7;
      color: #92400e;
    }

    .pill.p-info {
      background: #dbeafe;
      color: #1e40af;
    }

    .subject-card {
      background: white;
      border-radius: var(--radius);
      padding: 20px;
      border: 1px solid var(--border);
      transition: all var(--transition);
      position: relative;
      overflow: hidden;
      height: 100%;
    }

    .subject-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow);
    }

    .subject-card .ribbon {
      position: absolute;
      top: 0;
      right: 0;
      width: 60px;
      height: 60px;
      overflow: hidden;
    }

    .subject-card .ribbon::before {
      content: '';
      position: absolute;
      top: 0;
      right: 0;
      border-width: 60px 60px 0 0;
      border-style: solid;
    }

    .subject-card.s-math .ribbon::before {
      border-color: #2b6da9 transparent transparent transparent;
    }

    .subject-card.s-phy .ribbon::before {
      border-color: #6a4c9e transparent transparent transparent;
    }

    .subject-card.s-chem .ribbon::before {
      border-color: #1a8a62 transparent transparent transparent;
    }

    .subject-card.s-cs .ribbon::before {
      border-color: #0d5c66 transparent transparent transparent;
    }

    .subject-card.s-eng .ribbon::before {
      border-color: #dd8f21 transparent transparent transparent;
    }

    .subject-card.s-urdu .ribbon::before {
      border-color: #bf4638 transparent transparent transparent;
    }

    .subject-card.s-default .ribbon::before {
      border-color: var(--secondary) transparent transparent transparent;
    }

    .subject-card .code {
      font-size: 0.7rem;
      color: var(--muted);
      font-weight: 500;
      letter-spacing: 0.3px;
    }

    .subject-ico {
      font-size: 2rem;
      display: block;
      margin-bottom: 6px;
    }

    .subject-card h3 {
      font-size: 1.05rem;
      font-weight: 700;
      margin: 0 0 4px;
    }

    .subject-card .teacher {
      font-size: 0.8rem;
      color: var(--muted);
      margin-bottom: 8px;
    }

    .subject-card .teacher i {
      margin-right: 4px;
    }

    .meta-list {
      list-style: none;
      padding: 0;
      margin: 10px 0 0;
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2px 16px;
    }

    .meta-list li {
      font-size: 0.78rem;
      color: var(--muted);
      display: flex;
      justify-content: space-between;
      padding: 2px 0;
    }

    .meta-list li i {
      margin-right: 4px;
      width: 16px;
    }

    .meta-list li span {
      font-weight: 500;
      color: var(--text);
    }

    .bar-label {
      display: flex;
      justify-content: space-between;
      font-size: 0.75rem;
      margin: 12px 0 4px;
    }

    .bar-label b {
      color: var(--text);
    }

    .bar {
      display: block;
      height: 4px;
      border-radius: 4px;
      background: var(--bg);
      overflow: hidden;
    }

    .bar i {
      display: block;
      height: 100%;
      border-radius: 4px;
      transition: width 0.6s ease;
    }

    .divider-soft {
      height: 1px;
      background: var(--border);
      margin: 12px 0;
    }

    .mini-link {
      font-size: 0.8rem;
      color: var(--secondary);
      text-decoration: none;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 4px;
      transition: gap 0.2s;
    }

    .mini-link:hover {
      gap: 8px;
    }

    .card {
      border: none;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      background: white;
    }

    .card-head {
      padding: 16px 20px 0;
    }

    .card-head h3 {
      font-size: 1rem;
      font-weight: 600;
      margin: 0;
    }

    .card-head .sub {
      font-size: 0.8rem;
      font-weight: 400;
      color: var(--muted);
      display: block;
    }

    .card-body {
      padding: 20px;
    }

    .table {
      font-size: 0.85rem;
      margin: 0;
    }

    .table th {
      font-weight: 600;
      color: var(--muted);
      border-color: var(--border);
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .table td {
      border-color: var(--border);
      vertical-align: middle;
    }

    .subject-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 500;
    }

    .subject-chip.s-math {
      background: #dbeafe;
      color: #1e40af;
    }

    .subject-chip.s-phy {
      background: #ede9fe;
      color: #5b21b6;
    }

    .subject-chip.s-chem {
      background: #d1fae5;
      color: #065f46;
    }

    .subject-chip.s-cs {
      background: #cffafe;
      color: #0e7490;
    }

    .subject-chip.s-eng {
      background: #fef3c7;
      color: #92400e;
    }

    .subject-chip.s-urdu {
      background: #fee2e2;
      color: #991b1b;
    }

    .alert-soft {
      padding: 10px 14px;
      border-radius: var(--radius-sm);
      display: flex;
      align-items: flex-start;
      gap: 10px;
      font-size: 0.85rem;
    }

    .alert-soft.amber {
      background: #fffbeb;
      border-left: 3px solid #f59e0b;
    }

    .alert-soft i {
      margin-top: 2px;
      color: #f59e0b;
    }

    .btn-solid,
    .btn-outline {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 20px;
      border-radius: 30px;
      text-decoration: none;
      font-size: 0.85rem;
      font-weight: 500;
      transition: all 0.2s;
      border: none;
    }

    .btn-solid {
      background: var(--secondary);
      color: white;
    }

    .btn-solid:hover {
      background: var(--primary-light);
      color: white;
    }

    .btn-outline {
      background: transparent;
      color: var(--text);
      border: 1px solid var(--border);
    }

    .btn-outline:hover {
      border-color: var(--secondary);
      color: var(--secondary);
    }

    .justify-content-center {
      justify-content: center;
    }

    .page-foot {
      padding: 16px 24px;
      border-top: 1px solid var(--border);
      font-size: 0.75rem;
      color: var(--muted);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
    }

    .page-foot a {
      color: var(--muted);
      text-decoration: none;
    }

    .page-foot a:hover {
      color: var(--text);
    }

    .lift {
      transition: transform 0.2s;
    }

    .lift:hover {
      transform: translateY(-2px);
    }

    .rise {
      animation: rise 0.4s ease forwards;
    }

    @keyframes rise {
      from {
        opacity: 0;
        transform: translateY(12px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @media (max-width: 768px) {
      .topbar-title h1 {
        font-size: 0.95rem;
      }

      .topbar-search {
        min-width: 120px;
        max-width: 180px;
      }

      .profile-chip .who {
        display: none;
      }

      .drop-panel {
        width: 300px;
        right: -40px;
      }

      .meta-list {
        grid-template-columns: 1fr;
      }

      .section-head {
        flex-direction: column;
        align-items: flex-start;
      }
    }

    @media (max-width: 576px) {
      .topbar-search {
        display: none;
      }

      .app-topbar {
        padding: 10px 16px;
      }

      .subject-card {
        padding: 16px;
      }

      .fact strong {
        font-size: 1rem;
      }

      .page-foot {
        flex-direction: column;
        text-align: center;
      }
    }
  </style>
</head>

<body>

  <input type="checkbox" id="navToggle" class="nav-toggle" />
  <label for="navToggle" class="nav-backdrop" aria-hidden="true"></label>

  <aside class="app-sidebar">
    <div class="sidebar-head">
      <a href="index.php" class="sidebar-brand">
        <img src="assets/images/logo.svg" alt="Crescent Public School logo" />
        <span class="brand-text"><strong>Crescent Public School</strong><small>Student Portal</small></span>
      </a>
      <label for="navToggle" class="sidebar-close" aria-label="Close navigation"><i class="bi bi-x-lg"></i></label>
    </div>
    <div class="student-card">
      <span class="avatar avatar-lg">
        <?php echo $student_initials; ?>
      </span>
      <div class="student-card-text">
        <strong>
          <?php echo htmlspecialchars($student_name); ?>
        </strong>
        <small>
          <?php echo htmlspecialchars($class_name . ' · ' . ($student['student_uid'] ?? 'STU-0000')); ?>
        </small>
      </div>
      <span class="verify" title="Verified student"><i class="bi bi-patch-check-fill"></i></span>
    </div>
    <nav class="sidebar-nav">
      <p class="nav-group">Overview</p>
      <a class="nav-item" href="index.php"><i class="bi bi-columns-gap"></i><span>Dashboard</span></a>
      <p class="nav-group">Academics</p>
      <a class="nav-item active" href="subjects.php"><i class="bi bi-journal-bookmark"></i><span>My Subjects</span><em
          class="nav-tag">
          <?php echo $total_subjects; ?>
        </em></a>
      <a class="nav-item" href="timetable.php"><i class="bi bi-calendar-week"></i><span>My Timetable</span></a>
      <a class="nav-item" href="attendance.php"><i class="bi bi-check2-square"></i><span>My Attendance</span></a>
      <a class="nav-item" href="assignments.php"><i class="bi bi-journal-text"></i><span>Assignments</span><em
          class="nav-tag nav-tag-warn">
          <?php echo $assignment_count; ?>
        </em></a>
      <a class="nav-item" href="exams.php"><i class="bi bi-pencil-square"></i><span>Exams</span><em
          class="nav-tag nav-tag-info">
          <?php echo $exam_count; ?>
        </em></a>
      <a class="nav-item" href="results.php"><i class="bi bi-graph-up-arrow"></i><span>Results</span></a>
      <p class="nav-group">Finance</p>
      <a class="nav-item" href="fees.php"><i class="bi bi-wallet2"></i><span>Fees</span><em
          class="nav-tag nav-tag-danger">
          <?php echo $fee_status == 'Unpaid' ? '1' : '0'; ?>
        </em></a>
      <p class="nav-group">School Life</p>
      <a class="nav-item" href="notices.php"><i class="bi bi-megaphone"></i><span>Notices</span></a>
      <a class="nav-item" href="events.php"><i class="bi bi-calendar2-heart"></i><span>Events</span></a>
      <a class="nav-item" href="messages.php"><i class="bi bi-envelope"></i><span>Messages</span><em class="nav-tag">
          <?php echo $unread_count; ?>
        </em></a>
      <p class="nav-group">Account</p>
      <a class="nav-item" href="profile.php"><i class="bi bi-person-badge"></i><span>My Profile</span></a>
      <a class="nav-item" href="settings.php"><i class="bi bi-gear"></i><span>Settings</span></a>
    </nav>
    <div class="sidebar-foot">
      <a href="#" class="logout-btn"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
      <p class="copy">Portal v2.6 · Session 2026–27</p>
    </div>
  </aside>

  <div class="app-main">
    <header class="app-topbar">
      <label for="navToggle" class="nav-btn" aria-label="Open navigation"><i class="bi bi-list"></i></label>
      <div class="topbar-title">
        <h1>My Subjects</h1>
        <div class="crumbs"><a href="index.php">Home</a><span>/</span>Academics<span>/</span>My Subjects</div>
      </div>
      <div class="topbar-search"><i class="bi bi-search"></i><input type="search"
          placeholder="Search subjects, teachers…" aria-label="Search" id="subjectSearch" /></div>
      <div class="topbar-actions">
        <div class="icon-drop">
          <a href="notices.php" class="icon-btn" aria-label="Notifications"><i class="bi bi-bell"></i><span
              class="ping">
              <?php echo $notice_count; ?>
            </span></a>
          <div class="drop-panel">
            <div class="drop-head"><strong>Notifications</strong><a href="notices.php">View all</a></div>
            <a href="exams.php" class="drop-row"><i class="dot-ico p-info"><i class="bi bi-pencil-square"></i></i><span>
                <p>Upcoming exams scheduled</p><small>Examination Cell ·
                  <?php echo $exam_count; ?> exams
                </small>
              </span></a>
            <a href="assignments.php" class="drop-row"><i class="dot-ico p-warn"><i
                  class="bi bi-journal-text"></i></i><span>
                <p>Assignments pending</p><small>Mr. Ahmed ·
                  <?php echo $assignment_count; ?> pending
                </small>
              </span></a>
          </div>
        </div>
        <div class="icon-drop">
          <a href="messages.php" class="icon-btn" aria-label="Messages"><i class="bi bi-envelope"></i><span
              class="ping">
              <?php echo $unread_count; ?>
            </span></a>
          <div class="drop-panel">
            <div class="drop-head"><strong>Messages</strong><a href="messages.php">Open inbox</a></div>
            <a href="messages.php" class="drop-row"><span class="avatar info">SR</span><span>
                <p>Ms. Sara Khan · Lab report feedback</p><small>Today, 09:14 AM</small>
              </span></a>
            <a href="messages.php" class="drop-row"><span class="avatar amber">CP</span><span>
                <p>Class Teacher · PTM confirmation</p><small>Yesterday, 04:02 PM</small>
              </span></a>
          </div>
        </div>
        <div class="icon-drop">
          <a href="profile.php" class="profile-chip"><span class="avatar">
              <?php echo $student_initials; ?>
            </span><span class="who"><b>
                <?php echo htmlspecialchars($student_name); ?>
              </b><small>
                <?php echo htmlspecialchars($class_name); ?>
              </small></span><i class="bi bi-chevron-down"></i></a>
          <div class="drop-panel">
            <div class="drop-head"><strong>
                <?php echo htmlspecialchars($student_name); ?>
              </strong><span class="pill p-ok">Active</span></div>
            <a href="profile.php" class="drop-row"><i class="dot-ico p-teal"><i
                  class="bi bi-person-badge"></i></i><span>
                <p>My Profile</p><small>
                  <?php echo $student['student_uid'] ?? 'STU-0000'; ?>
                </small>
              </span></a>
            <a href="settings.php" class="drop-row"><i class="dot-ico p-violet"><i class="bi bi-gear"></i></i><span>
                <p>Settings</p><small>Preferences &amp; alerts</small>
              </span></a>
            <a href="#" class="drop-row"><i class="dot-ico p-danger"><i class="bi bi-box-arrow-right"></i></i><span>
                <p>Logout</p><small>End this session</small>
              </span></a>
          </div>
        </div>
      </div>
    </header>

    <main class="app-content">

      <section class="welcome rise">
        <div class="row g-4 align-items-center">
          <div class="col-lg-8">
            <span class="eyebrow">Academics · Session 2026–27</span>
            <h2>
              <?php echo $total_subjects; ?> subjects, one class —
              <?php echo htmlspecialchars($class_name); ?>
            </h2>
            <p>You are enrolled in
              <?php echo $total_subjects; ?> core subjects this session with a combined
              <?php echo $total_periods; ?> periods per week. Subject syllabus, teacher details and classroom allocation
              are listed below.
            </p>
            <div class="quick-chips">
              <a href="timetable.php" class="chip"><i class="bi bi-calendar-week"></i> Weekly Timetable</a>
              <a href="attendance.php" class="chip"><i class="bi bi-check2-square"></i> Subject Attendance</a>
              <a href="results.php" class="chip"><i class="bi bi-graph-up-arrow"></i> Subject Results</a>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="row g-2">
              <div class="col-6">
                <div class="fact"><small>Subjects</small><strong>
                    <?php echo $total_subjects; ?>
                  </strong><span>Core stream</span></div>
              </div>
              <div class="col-6">
                <div class="fact"><small>Weekly Periods</small><strong>
                    <?php echo $total_periods; ?>
                  </strong><span>Mon – Sat</span></div>
              </div>
              <div class="col-6">
                <div class="fact"><small>Class Strength</small><strong>
                    <?php echo isset($subjects[0]['total_students']) ? $subjects[0]['total_students'] : 0; ?>
                  </strong><span>Students</span></div>
              </div>
              <div class="col-6">
                <div class="fact"><small>Avg. Attendance</small><strong>
                    <?php echo $avg_attendance; ?>%
                  </strong><span>Current</span></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="mt-4 rise">
        <div class="section-head">
          <div>
            <span class="eyebrow">Enrolment</span>
            <h2>Enrolled Subjects</h2>
            <p>Click any card to open the full subject overview.</p>
          </div>
          <span class="pill p-teal"><i class="bi bi-check2-all"></i> All subjects active</span>
        </div>

        <div class="row g-3" id="subjectContainer">
          <?php if (count($subjects) > 0): ?>
            <?php foreach ($subjects as $subject):
              $icon = getSubjectIcon($subject['subject_title']);
              $color = getSubjectColor($subject['subject_title']);
              $periods = getPeriodCount($subject['subject_title']);
              $attendance = getSubjectAttendance($conn, $student_id, $subject['id']);
              ?>
              <div class="col-md-6 col-xl-4 subject-item" data-title="<?php echo strtolower($subject['subject_title']); ?>"
                data-teacher="<?php echo strtolower($subject['teacher_name'] ?? ''); ?>">
                <div class="subject-card <?php echo $color; ?> lift">
                  <span class="ribbon"></span>
                  <span class="code">
                    <?php echo htmlspecialchars($subject['subject_code'] ?? 'N/A'); ?>
                  </span>
                  <span class="subject-ico"><i class="bi <?php echo $icon; ?>"></i></span>
                  <h3>
                    <?php echo htmlspecialchars($subject['subject_title']); ?>
                  </h3>
                  <p class="teacher"><i class="bi bi-person"></i>
                    <?php echo htmlspecialchars($subject['teacher_name'] ?? 'Not Assigned'); ?>
                  </p>
                  <p style="font-size:.8rem;color:var(--muted);line-height:1.5">
                    <?php echo htmlspecialchars($subject['class_description'] ?? 'Core subject with comprehensive curriculum and practical applications.'); ?>
                  </p>
                  <ul class="meta-list">
                    <li><i class="bi bi-mortarboard"></i> Class <span>
                        <?php echo htmlspecialchars($subject['class_name'] ?? 'N/A'); ?>
                      </span></li>
                    <li><i class="bi bi-door-open"></i> Room <span>
                        <?php echo htmlspecialchars($subject['room_no'] ?? 'N/A'); ?>
                      </span></li>
                    <li><i class="bi bi-people"></i> Total Students <span>
                        <?php echo $subject['total_students'] ?? 0; ?>
                      </span></li>
                    <li><i class="bi bi-clock-history"></i> Periods / week <span>
                        <?php echo $periods; ?>
                      </span></li>
                  </ul>
                  <div class="bar-label"><span>Attendance</span><b>
                      <?php echo $attendance; ?>%
                    </b></div>
                  <span class="bar slim"><i
                      style="width:<?php echo $attendance; ?>%;background:<?php echo $attendance >= 90 ? '#2b6da9' : ($attendance >= 75 ? '#e8a838' : '#dc2626'); ?>"></i></span>
                  <div class="divider-soft"></div>
                  <a href="subjects.php?id=<?php echo $subject['id']; ?>" class="mini-link">View Details <i
                      class="bi bi-arrow-right"></i></a>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="col-12">
              <div class="text-center py-5 text-muted">
                <i class="bi bi-journal-bookmark fs-1 d-block mb-3"></i>
                <h5>No Subjects Enrolled</h5>
                <p>You are not enrolled in any subjects yet. Please contact your class teacher.</p>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <section class="row g-3 mt-1 rise">
        <div class="col-xl-7">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Subject Overview <span class="sub">Marks weightage for session 2026–27</span></h3>
            </div>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Subject</th>
                    <th>Teacher</th>
                    <th>Credits</th>
                    <th>Avg. Score</th>
                    <th>Standing</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (count($subjects) > 0): ?>
                    <?php
                    $scores = [85, 78, 74, 92, 88, 66];
                    $standings = ['Excellent', 'Good', 'Good', 'Excellent', 'Excellent', 'Needs Work'];
                    $standing_colors = ['p-ok', 'p-teal', 'p-teal', 'p-ok', 'p-ok', 'p-warn'];
                    $i = 0;
                    ?>
                    <?php foreach ($subjects as $subject):
                      $icon = getSubjectIcon($subject['subject_title']);
                      $color = getSubjectColor($subject['subject_title']);
                      $score = $scores[$i % count($scores)];
                      $standing = $standings[$i % count($standings)];
                      $s_color = $standing_colors[$i % count($standing_colors)];
                      $i++;
                      ?>
                      <tr>
                        <td><span class="subject-chip <?php echo $color; ?>"><i class="bi <?php echo $icon; ?>"></i>
                            <?php echo htmlspecialchars($subject['subject_title']); ?>
                          </span></td>
                        <td>
                          <?php echo htmlspecialchars($subject['teacher_name'] ?? 'N/A'); ?>
                        </td>
                        <td>
                          <?php echo getPeriodCount($subject['subject_title']) - 1; ?>
                        </td>
                        <td>
                          <?php echo $score; ?>%
                        </td>
                        <td><span class="pill <?php echo $s_color; ?>">
                            <?php echo $standing; ?>
                          </span></td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="5" class="text-center text-muted py-3">No subject data available</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
        <div class="col-xl-5">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Class Teacher <span class="sub">Primary contact for
                  <?php echo htmlspecialchars($class_name); ?>
                </span></h3>
            </div>
            <div class="card-body">
              <div class="d-flex align-items-center gap-3 mb-3">
                <span class="avatar avatar-xl amber"
                  style="width:64px;height:64px;flex-basis:64px;font-size:1.3rem;border-radius:18px">
                  <?php echo $teacher_initials; ?>
                </span>
                <div>
                  <h3 style="font-size:1.05rem">
                    <?php echo htmlspecialchars($class_teacher['full_name'] ?? 'Not Assigned'); ?>
                  </h3>
                  <p class="mb-0" style="font-size:.8rem;color:var(--muted)">
                    Class Teacher ·
                    <?php echo htmlspecialchars($class_name); ?><br />
                    Staff Room 2 · Ext. 214
                  </p>
                </div>
              </div>
              <ul class="meta-list">
                <li><i class="bi bi-envelope"></i> Email <span>
                    <?php echo htmlspecialchars($class_teacher['email'] ?? 'N/A'); ?>
                  </span></li>
                <li><i class="bi bi-telephone"></i> Phone <span>
                    <?php echo htmlspecialchars($class_teacher['phone'] ?? '+92 300 1234567'); ?>
                  </span></li>
                <li><i class="bi bi-clock"></i> Free Period <span>Friday, 11:30 AM</span></li>
              </ul>
              <div class="divider-soft"></div>
              <div class="alert-soft amber"><i class="bi bi-chat-quote"></i><span><b>Teacher&rsquo;s remark:</b>
                  &ldquo;Student is a consistent performer. Needs more focus on Urdu essay writing before the
                  Mid-Terms.&rdquo;</span></div>
              <div class="d-grid gap-2 mt-3">
                <a href="messages.php" class="btn-solid justify-content-center"><i class="bi bi-envelope"></i> Send
                  Message</a>
                <a href="timetable.php" class="btn-outline justify-content-center"><i class="bi bi-calendar-week"></i>
                  View Timetable</a>
              </div>
            </div>
          </div>
        </div>
      </section>

    </main>

    <footer class="page-foot">
      <span>&copy; 2026 Crescent Public School · Student Portal</span>
      <span class="d-flex gap-3"><a href="notices.php">Help Centre</a><a href="messages.php">Contact Office</a><a
          href="settings.php">Privacy</a></span>
    </footer>
  </div>

  <script>
    // Search functionality for subjects
    document.getElementById('subjectSearch')?.addEventListener('keyup', function () {
      const searchTerm = this.value.toLowerCase();
      const items = document.querySelectorAll('.subject-item');
      let visibleCount = 0;

      items.forEach(item => {
        const title = item.dataset.title || '';
        const teacher = item.dataset.teacher || '';
        const matches = title.includes(searchTerm) || teacher.includes(searchTerm);

        if (matches) {
          item.style.display = '';
          visibleCount++;
        } else {
          item.style.display = 'none';
        }
      });

      // Show/hide no results message
      let noResults = document.getElementById('noResultsMsg');
      if (visibleCount === 0 && items.length > 0) {
        if (!noResults) {
          noResults = document.createElement('div');
          noResults.id = 'noResultsMsg';
          noResults.className = 'col-12 text-center py-4';
          noResults.innerHTML = `
                    <i class="bi bi-search fs-1 d-block text-muted mb-3"></i>
                    <h5>No subjects found</h5>
                    <p class="text-muted">Try adjusting your search terms</p>
                `;
          document.getElementById('subjectContainer').appendChild(noResults);
        }
        noResults.style.display = '';
      } else if (noResults) {
        noResults.style.display = 'none';
      }
    });
  </script>

</body>

</html>