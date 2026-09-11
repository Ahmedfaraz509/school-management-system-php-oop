<?php
// timetable.php - Student Timetable Page
require_once '../database/connect.php';

session_start();

// Get student ID from session or use default
$student_id = $_SESSION['student_id'] ?? 4;

// Check if student exists
$check_stmt = $conn->prepare("SELECT id FROM students WHERE id = :id");
$check_stmt->bindValue(':id', $student_id);
$check_stmt->execute();
$student_exists = $check_stmt->fetch();

if (!$student_exists) {
  $first_stmt = $conn->query("SELECT id FROM students LIMIT 1");
  $first = $first_stmt->fetch();
  if ($first) {
    $student_id = $first['id'];
    $_SESSION['student_id'] = $student_id;
  } else {
    die("No students found in the database.");
  }
}

// Get student info
$student_stmt = $conn->prepare("
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
");
$student_stmt->bindValue(':student_id', $student_id);
$student_stmt->execute();
$student = $student_stmt->fetch();

if (!$student) {
  $fallback_stmt = $conn->query("
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

// Get timetable for this student's class
$timetable = [];
if ($class_id > 0) {
  $timetable_stmt = $conn->prepare("
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
        WHERE t.class_id = :class_id
        ORDER BY 
            FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
            t.time_slot ASC
    ");
  $timetable_stmt->bindValue(':class_id', $class_id);
  $timetable_stmt->execute();
  $timetable = $timetable_stmt->fetchAll();
}

// Define days of week
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Define time slots (from your database)
$time_slots = [
  '08:00' => '08:00 – 09:00',
  '09:00' => '09:00 – 10:00',
  '10:00' => '10:00 – 10:30',
  '10:30' => '10:30 – 11:30',
  '11:30' => '11:30 – 12:30',
  '12:30' => '12:30 – 01:15',
  '01:15' => '01:15 – 02:15'
];

// Break slots
$break_slots = [
  '10:00' => 'Morning Break',
  '12:30' => 'Lunch & Prayer Break'
];

// Organize timetable into grid
$timetable_grid = [];
foreach ($time_slots as $slot_key => $slot_display) {
  $timetable_grid[$slot_key] = [];
  foreach ($days_of_week as $day) {
    $timetable_grid[$slot_key][$day] = null;
  }
}

// Fill grid with data
foreach ($timetable as $record) {
  $slot_key = $record['time_slot'];
  $day = $record['day_of_week'];

  if (isset($timetable_grid[$slot_key]) && isset($timetable_grid[$slot_key][$day])) {
    $timetable_grid[$slot_key][$day] = $record;
  }
}

// Get subject colors for styling
$subject_colors = [
  'Mathematics' => 's-math',
  'Math' => 's-math',
  'Physics' => 's-phy',
  'Chemistry' => 's-chem',
  'Computer' => 's-cs',
  'Computer Science' => 's-cs',
  'English' => 's-eng',
  'Urdu' => 's-urdu',
  'Biology' => 's-bio',
  'default' => 's-default'
];

function getSubjectColor($subject_title)
{
  global $subject_colors;
  if (!$subject_title)
    return $subject_colors['default'];
  foreach ($subject_colors as $key => $color) {
    if (stripos($subject_title, $key) !== false) {
      return $color;
    }
  }
  return $subject_colors['default'];
}

// Calculate statistics
$total_periods = count($timetable);
$today = date('l');
$today_periods = 0;
$today_remaining = 0;
$current_time = date('H:i');

foreach ($timetable as $record) {
  if ($record['day_of_week'] == $today) {
    $today_periods++;
    if ($record['time_slot'] > $current_time) {
      $today_remaining++;
    }
  }
}

// Get today's periods for display
$today_periods_list = [];
foreach ($timetable as $record) {
  if ($record['day_of_week'] == $today) {
    $today_periods_list[] = $record;
  }
}

// Sort today's periods by time
usort($today_periods_list, function ($a, $b) {
  return strcmp($a['time_slot'], $b['time_slot']);
});

// Get subject distribution
$subject_distribution = [];
foreach ($timetable as $record) {
  $title = $record['subject_title'] ?? 'Unknown';
  if (!isset($subject_distribution[$title])) {
    $subject_distribution[$title] = 0;
  }
  $subject_distribution[$title]++;
}

// Get unique subjects for pills
$unique_subjects = array_keys($subject_distribution);

// Get current week number
$week_number = date('W');
$week_start = date('d', strtotime('monday this week'));
$week_end = date('d', strtotime('saturday this week'));
$month = date('F');

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
        WHERE a.class_id = :class_id AND a.status = 'active' AND a.due_date >= CURDATE()
    ");
  $assignment_stmt->bindValue(':class_id', $class_id);
  $assignment_stmt->execute();
  $assignment_count = $assignment_stmt->fetch()['count'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="My Timetable — Crescent Public School Student Portal" />
  <title>My Timetable · Student Portal · Crescent Public School</title>
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

    .p-grey {
      background: #f1f5f9;
      color: #475569;
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

    .card {
      border: none;
      border-radius: var(--radius);
      box-shadow: var(--shadow);
      background: white;
    }

    .card-head {
      padding: 16px 20px 0;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
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

    .card-body.tight {
      padding: 16px 20px;
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

    .pill.bare {
      background: transparent;
      border: 1px solid var(--border);
    }

    .pill.s-math {
      background: #e7f0f8;
      color: #24598c;
    }

    .pill.s-phy {
      background: #efeaf8;
      color: #54398a;
    }

    .pill.s-chem {
      background: #e2f4ec;
      color: #14684a;
    }

    .pill.s-cs {
      background: #e2f0f2;
      color: #0a4a52;
    }

    .pill.s-eng {
      background: #fdf2df;
      color: #8f5b0c;
    }

    .pill.s-urdu {
      background: #fbeae7;
      color: #9b3729;
    }

    .pill.p-ok {
      background: #d1fae5;
      color: #065f46;
    }

    .pill.p-teal {
      background: #d1fae5;
      color: #059669;
    }

    .pill.p-info {
      background: #dbeafe;
      color: #1e40af;
    }

    .pill.p-grey {
      background: #f1f5f9;
      color: #475569;
    }

    /* Timetable Styles */
    .table-wrap {
      padding: 16px 20px 20px;
      overflow-x: auto;
    }

    .tt-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.85rem;
      min-width: 700px;
    }

    .tt-table th {
      background: #f8fafc;
      font-weight: 600;
      color: var(--muted);
      padding: 10px 8px;
      border: 1px solid var(--border);
      text-align: center;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .tt-table td {
      padding: 8px;
      border: 1px solid var(--border);
      text-align: center;
      vertical-align: middle;
      min-height: 60px;
    }

    .tt-table th:first-child {
      background: #f8fafc;
      font-weight: 600;
      color: var(--text);
      font-size: 0.8rem;
    }

    .tt-cell {
      display: block;
      padding: 6px 8px;
      border-radius: var(--radius-sm);
      font-size: 0.8rem;
      text-align: center;
    }

    .tt-cell b {
      display: block;
      font-size: 0.85rem;
    }

    .tt-cell small {
      font-size: 0.65rem;
      opacity: 0.7;
      display: block;
    }

    .tt-cell.s-math {
      background: #e7f0f8;
      color: #24598c;
    }

    .tt-cell.s-phy {
      background: #efeaf8;
      color: #54398a;
    }

    .tt-cell.s-chem {
      background: #e2f4ec;
      color: #14684a;
    }

    .tt-cell.s-cs {
      background: #e2f0f2;
      color: #0a4a52;
    }

    .tt-cell.s-eng {
      background: #fdf2df;
      color: #8f5b0c;
    }

    .tt-cell.s-urdu {
      background: #fbeae7;
      color: #9b3729;
    }

    .tt-cell.break-cell {
      background: #f1f5f9;
      color: #64748b;
      padding: 8px;
    }

    .tt-cell.break-cell b {
      color: #475569;
    }

    /* Today schedule rows */
    .notice-row {
      display: flex;
      gap: 12px;
      align-items: center;
      padding: 8px 0;
      border-bottom: 1px solid var(--border);
    }

    .notice-row:last-child {
      border-bottom: none;
    }

    .notice-date {
      text-align: center;
      min-width: 44px;
      background: var(--bg);
      padding: 4px 8px;
      border-radius: var(--radius-sm);
    }

    .notice-date b {
      display: block;
      font-size: 1.1rem;
    }

    .notice-date small {
      font-size: 0.6rem;
      color: var(--muted);
    }

    .notice-row h4 {
      font-size: 0.9rem;
      margin: 0;
      font-weight: 600;
    }

    .notice-row p {
      font-size: 0.75rem;
      color: var(--muted);
      margin: 0;
    }

    .notice-row.s-math {
      border-left: 3px solid #2b6da9;
    }

    .notice-row.s-phy {
      border-left: 3px solid #6a4c9e;
    }

    .notice-row.s-chem {
      border-left: 3px solid #1a8a62;
    }

    .notice-row.s-cs {
      border-left: 3px solid #0d5c66;
    }

    .notice-row.s-eng {
      border-left: 3px solid #dd8f21;
    }

    .notice-row.s-urdu {
      border-left: 3px solid #bf4638;
    }

    .bar-label {
      display: flex;
      justify-content: space-between;
      font-size: 0.8rem;
      margin-bottom: 2px;
    }

    .bar {
      display: block;
      height: 6px;
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

    .bar.mb-3 {
      margin-bottom: 12px;
    }

    .meta-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .meta-list li {
      display: flex;
      justify-content: space-between;
      padding: 6px 0;
      font-size: 0.85rem;
      border-bottom: 1px solid var(--border);
    }

    .meta-list li:last-child {
      border-bottom: none;
    }

    .meta-list li i {
      margin-right: 8px;
      color: var(--secondary);
      width: 20px;
    }

    .alert-soft {
      padding: 10px 14px;
      border-radius: var(--radius-sm);
      display: flex;
      align-items: flex-start;
      gap: 10px;
      font-size: 0.85rem;
    }

    .alert-soft.teal {
      background: #f0fdf4;
      border-left: 3px solid #059669;
    }

    .alert-soft i {
      margin-top: 2px;
      color: #059669;
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

    /* Divider */
    .divider-soft {
      height: 1px;
      background: var(--border);
      margin: 12px 0;
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

      .card-head {
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

      .tt-table {
        font-size: 0.7rem;
        min-width: 500px;
      }

      .tt-cell b {
        font-size: 0.7rem;
      }

      .tt-cell small {
        font-size: 0.55rem;
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
      <a class="nav-item" href="subjects.php"><i class="bi bi-journal-bookmark"></i><span>My Subjects</span><em
          class="nav-tag">
          <?php echo count($unique_subjects); ?>
        </em></a>
      <a class="nav-item active" href="timetable.php"><i class="bi bi-calendar-week"></i><span>My Timetable</span></a>
      <a class="nav-item" href="attendance.php"><i class="bi bi-check2-square"></i><span>My Attendance</span></a>
      <a class="nav-item" href="assignments.php"><i class="bi bi-journal-text"></i><span>Assignments</span><em
          class="nav-tag nav-tag-warn">
          <?php echo $assignment_count; ?>
        </em></a>
      <a class="nav-item" href="exams.php"><i class="bi bi-pencil-square"></i><span>Exams</span><em
          class="nav-tag nav-tag-info">3</em></a>
      <a class="nav-item" href="results.php"><i class="bi bi-graph-up-arrow"></i><span>Results</span></a>
      <p class="nav-group">Finance</p>
      <a class="nav-item" href="fees.php"><i class="bi bi-wallet2"></i><span>Fees</span><em
          class="nav-tag nav-tag-danger">1</em></a>
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
        <h1>My Timetable</h1>
        <div class="crumbs"><a href="index.php">Home</a><span>/</span>Academics<span>/</span>My Timetable</div>
      </div>
      <div class="topbar-search"><i class="bi bi-search"></i><input type="search" placeholder="Search periods or rooms…"
          aria-label="Search" id="timetableSearch" /></div>
      <div class="topbar-actions">
        <div class="icon-drop">
          <a href="notices.php" class="icon-btn" aria-label="Notifications"><i class="bi bi-bell"></i><span
              class="ping">
              <?php echo $notice_count; ?>
            </span></a>
          <div class="drop-panel">
            <div class="drop-head"><strong>Notifications</strong><a href="notices.php">View all</a></div>
            <a href="exams.php" class="drop-row"><i class="dot-ico p-info"><i class="bi bi-pencil-square"></i></i><span>
                <p>Mid-Term timetable published</p><small>Examination Cell · 2 hours ago</small>
              </span></a>
            <a href="assignments.php" class="drop-row"><i class="dot-ico p-warn"><i
                  class="bi bi-journal-text"></i></i><span>
                <p>Assignments due soon</p><small>Mr. Ahmed ·
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
            <span class="eyebrow">Week
              <?php echo $week_number; ?> ·
              <?php echo $week_start; ?> –
              <?php echo $week_end; ?>
              <?php echo $month; ?> 2026
            </span>
            <h2>Weekly class timetable</h2>
            <p>School hours run from 08:00 AM to 02:15 PM, Monday to Saturday. The scrollable grid below shows every
              period with its subject, teacher and room allocation.</p>
            <div class="quick-chips">
              <a href="subjects.php" class="chip"><i class="bi bi-journal-bookmark"></i> My Subjects</a>
              <a href="attendance.php" class="chip"><i class="bi bi-check2-square"></i> Attendance</a>
              <a href="assignments.php" class="chip"><i class="bi bi-journal-text"></i> Assignments</a>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="row g-2">
              <div class="col-6">
                <div class="fact"><small>Periods / Week</small><strong>
                    <?php echo $total_periods; ?>
                  </strong><span>6 days</span></div>
              </div>
              <div class="col-6">
                <div class="fact"><small>Today&rsquo;s Periods</small><strong>
                    <?php echo $today_periods; ?>
                  </strong><span>
                    <?php echo $today_remaining; ?> remaining
                  </span></div>
              </div>
              <div class="col-6">
                <div class="fact"><small>Assembly</small><strong>07:45 AM</strong><span>Main ground</span></div>
              </div>
              <div class="col-6">
                <div class="fact"><small>Dismissal</small><strong>02:15 PM</strong><span>Gate 2</span></div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="mt-4 rise">
        <div class="card lift">
          <div class="card-head">
            <h3>
              <?php echo htmlspecialchars($class_name); ?> · Weekly Timetable <span class="sub">Drag horizontally on
                small screens</span>
            </h3>
            <span class="d-flex gap-2 flex-wrap">
              <?php if (count($unique_subjects) > 0): ?>
                <?php foreach ($unique_subjects as $subject):
                  $color = getSubjectColor($subject);
                  ?>
                  <span class="pill <?php echo $color; ?> bare">
                    <?php echo htmlspecialchars($subject); ?>
                  </span>
                <?php endforeach; ?>
              <?php else: ?>
                <span class="pill bare">No subjects</span>
              <?php endif; ?>
            </span>
          </div>
          <div class="table-wrap">
            <table class="tt-table" id="timetableTable">
              <thead>
                <tr>
                  <th style="width:120px">Time</th>
                  <?php foreach ($days_of_week as $day): ?>
                    <th>
                      <?php echo $day; ?>
                    </th>
                  <?php endforeach; ?>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($time_slots as $slot_key => $slot_display):
                  $is_break = isset($break_slots[$slot_key]);
                  ?>
                  <tr class="time-row" data-time="<?php echo $slot_key; ?>">
                    <th>
                      <?php echo $slot_display; ?>
                    </th>
                    <?php foreach ($days_of_week as $day):
                      $record = $timetable_grid[$slot_key][$day] ?? null;
                      ?>
                      <td>
                        <?php if ($is_break): ?>
                          <span class="tt-cell break-cell">
                            <b>
                              <?php echo $break_slots[$slot_key]; ?>
                            </b>
                            <small>School courtyard</small>
                          </span>
                        <?php elseif ($record):
                          $color = getSubjectColor($record['subject_title'] ?? '');
                          ?>
                          <span class="tt-cell <?php echo $color; ?>">
                            <b>
                              <?php echo htmlspecialchars($record['subject_title'] ?? 'N/A'); ?>
                            </b>
                            <small>
                              <?php echo htmlspecialchars($record['teacher_name'] ?? 'N/A'); ?> ·
                              <?php echo htmlspecialchars($record['room'] ?? 'N/A'); ?>
                            </small>
                          </span>
                        <?php else: ?>
                          <span class="text-muted" style="font-size:0.7rem;">—</span>
                        <?php endif; ?>
                      </td>
                    <?php endforeach; ?>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section class="row g-3 mt-1 rise">
        <div class="col-lg-4">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Today ·
                <?php echo $today; ?> <span class="sub">
                  <?php echo date('d F Y'); ?>
                </span>
              </h3>
            </div>
            <div class="card-body tight">
              <?php if (count($today_periods_list) > 0): ?>
                <?php foreach ($today_periods_list as $period):
                  $color = getSubjectColor($period['subject_title'] ?? '');
                  $time_parts = explode(':', $period['time_slot']);
                  $hour = ltrim($time_parts[0], '0');
                  $ampm = $hour >= 12 ? 'PM' : 'AM';
                  $display_hour = $hour > 12 ? $hour - 12 : $hour;
                  $status = $period['time_slot'] < $current_time ? 'Completed' :
                    ($period['time_slot'] == $current_time ? 'Ongoing' : 'Upcoming');
                  $status_class = $status == 'Completed' ? 'p-grey' : ($status == 'Ongoing' ? 'p-info' : 'p-teal');
                  ?>
                  <div class="notice-row <?php echo $color; ?>">
                    <div class="notice-date">
                      <b>
                        <?php echo $display_hour; ?>
                      </b>
                      <small>
                        <?php echo $ampm; ?>
                      </small>
                    </div>
                    <div>
                      <h4>
                        <?php echo htmlspecialchars($period['subject_title'] ?? 'N/A'); ?>
                      </h4>
                      <p>
                        <?php echo htmlspecialchars($period['teacher_name'] ?? 'N/A'); ?> ·
                        <?php echo htmlspecialchars($period['room'] ?? 'N/A'); ?>
                      </p>
                      <span class="pill <?php echo $status_class; ?>">
                        <?php echo $status; ?>
                      </span>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="text-center py-3 text-muted">
                  <i class="bi bi-calendar2-day fs-4 d-block mb-2"></i>
                  <p>No classes scheduled for today</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Period Distribution <span class="sub">Weekly load per subject</span></h3>
            </div>
            <div class="card-body">
              <?php if (count($subject_distribution) > 0):
                $max_periods = max($subject_distribution);
                foreach ($subject_distribution as $subject => $count):
                  $color = getSubjectColor($subject);
                  $percentage = round(($count / $max_periods) * 100);
                  $colors = [
                    's-math' => '#2b6da9',
                    's-phy' => '#6a4c9e',
                    's-chem' => '#1a8a62',
                    's-cs' => '#0d5c66',
                    's-eng' => '#dd8f21',
                    's-urdu' => '#bf4638',
                    'default' => '#64748b'
                  ];
                  $bar_color = $colors[$color] ?? $colors['default'];
                  ?>
                  <div class="bar-label"><span><b>
                        <?php echo htmlspecialchars($subject); ?>
                      </b> ·
                      <?php echo $count; ?> periods
                    </span><b>
                      <?php echo $count; ?>
                    </b></div>
                  <span class="bar mb-3"><i
                      style="width:<?php echo $percentage; ?>%;background:<?php echo $bar_color; ?>"></i></span>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="text-center py-3 text-muted">
                  <i class="bi bi-bar-chart fs-4 d-block mb-2"></i>
                  <p>No subject distribution data available</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>School Routine <span class="sub">Standard timings</span></h3>
            </div>
            <div class="card-body">
              <ul class="meta-list">
                <li><i class="bi bi-flag"></i> Assembly <span>07:45 AM</span></li>
                <li><i class="bi bi-door-open"></i> School opens <span>07:30 AM</span></li>
                <li><i class="bi bi-bell"></i> Period 1 begins <span>08:00 AM</span></li>
                <li><i class="bi bi-cup-hot"></i> Morning break <span>10:00 AM</span></li>
                <li><i class="bi bi-basket"></i> Lunch &amp; prayer <span>12:30 PM</span></li>
                <li><i class="bi bi-house-door"></i> Dismissal <span>02:15 PM</span></li>
                <li><i class="bi bi-calendar-x"></i> Weekend <span>Sunday</span></li>
              </ul>
              <div class="divider-soft"></div>
              <div class="alert-soft teal"><i class="bi bi-info-circle"></i><span>Friday assembly is held after the
                  prayer break. Saturday has no assembly &mdash; classes start directly at 08:00 AM.</span></div>
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
    // Search functionality for timetable
    document.getElementById('timetableSearch')?.addEventListener('keyup', function () {
      const searchTerm = this.value.toLowerCase();
      const rows = document.querySelectorAll('#timetableTable tbody tr');
      let visibleCount = 0;

      rows.forEach(row => {
        const cells = row.querySelectorAll('td');
        let found = false;

        cells.forEach(cell => {
          const text = cell.textContent.toLowerCase();
          if (text.includes(searchTerm)) {
            found = true;
            if (searchTerm.length > 0) {
              cell.style.background = '#fff3cd';
            } else {
              cell.style.background = '';
            }
          } else if (searchTerm.length > 0) {
            cell.style.background = '';
          }
        });

        if (searchTerm.length > 0) {
          row.style.display = found ? '' : 'none';
          if (found) visibleCount++;
        } else {
          row.style.display = '';
          row.querySelectorAll('td').forEach(cell => {
            cell.style.background = '';
          });
          visibleCount++;
        }
      });

      // Show/hide no results message
      let noResults = document.getElementById('noResultsMsg');
      if (visibleCount === 0 && searchTerm.length > 0) {
        if (!noResults) {
          const tableWrap = document.querySelector('.table-wrap');
          noResults = document.createElement('div');
          noResults.id = 'noResultsMsg';
          noResults.className = 'text-center py-4 text-muted';
          noResults.innerHTML = `
                    <i class="bi bi-search fs-1 d-block mb-3"></i>
                    <h5>No periods found</h5>
                    <p>Try adjusting your search terms</p>
                `;
          tableWrap.appendChild(noResults);
        }
        noResults.style.display = '';
      } else if (noResults) {
        noResults.style.display = 'none';
      }
    });
  </script>

</body>

</html>