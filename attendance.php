<?php
// attendance.php - Student Attendance Page
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

// Get attendance statistics
$attendance_stats = [
  'total_classes' => 0,
  'present' => 0,
  'absent' => 0,
  'late' => 0,
  'percentage' => 0
];

$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late
    FROM attendance
    WHERE student_id = :student_id
");
$stats_stmt->bindValue(':student_id', $student_id);
$stats_stmt->execute();
$stats_data = $stats_stmt->fetch();

if ($stats_data) {
  $attendance_stats['total_classes'] = $stats_data['total'] ?? 0;
  $attendance_stats['present'] = $stats_data['present'] ?? 0;
  $attendance_stats['absent'] = $stats_data['absent'] ?? 0;
  $attendance_stats['late'] = $stats_data['late'] ?? 0;
  $attendance_stats['percentage'] = $attendance_stats['total_classes'] > 0 ?
    round(($attendance_stats['present'] / $attendance_stats['total_classes']) * 100) : 0;
}

// Get class-wise attendance
$subject_attendance = [];
$subject_stmt = $conn->prepare("
    SELECT 
        c.name as subject_title,
        COUNT(a.id) as total_classes,
        SUM(CASE WHEN a.status = 'Present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN a.status = 'Absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN a.status = 'Late' THEN 1 ELSE 0 END) as late
    FROM attendance a
    LEFT JOIN classes c ON a.class_id = c.id
    WHERE a.student_id = :student_id
    GROUP BY a.class_id
");
$subject_stmt->bindValue(':student_id', $student_id);
$subject_stmt->execute();
$subject_attendance = $subject_stmt->fetchAll();

if (empty($subject_attendance) && $attendance_stats['total_classes'] > 0) {
  $subject_attendance[] = [
    'subject_title' => 'All Classes',
    'total_classes' => $attendance_stats['total_classes'],
    'present' => $attendance_stats['present'],
    'absent' => $attendance_stats['absent'],
    'late' => $attendance_stats['late']
  ];
}

// Get recent attendance records
$recent_stmt = $conn->prepare("
    SELECT 
        a.attendance_date,
        c.name as subject_title,
        a.status,
        a.remarks
    FROM attendance a
    LEFT JOIN classes c ON a.class_id = c.id
    WHERE a.student_id = :student_id
    ORDER BY a.attendance_date DESC
    LIMIT 12
");
$recent_stmt->bindValue(':student_id', $student_id);
$recent_stmt->execute();
$recent_records = $recent_stmt->fetchAll();

// Get monthly trend
$monthly_trend = [];
$trend_stmt = $conn->prepare("
    SELECT 
        MONTH(attendance_date) as month,
        MONTHNAME(attendance_date) as month_name,
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present
    FROM attendance
    WHERE student_id = :student_id
    GROUP BY MONTH(attendance_date)
    ORDER BY MONTH(attendance_date) ASC
");
$trend_stmt->bindValue(':student_id', $student_id);
$trend_stmt->execute();
$monthly_trend = $trend_stmt->fetchAll();

$monthly_percentages = [];
foreach ($monthly_trend as $month) {
  $pct = $month['total'] > 0 ? round(($month['present'] / $month['total']) * 100) : 0;
  $monthly_percentages[] = [
    'month' => $month['month_name'] ?? 'Unknown',
    'percentage' => $pct,
    'total' => $month['total']
  ];
}

// Get leave records
$leave_stmt = $conn->prepare("
    SELECT 
        attendance_date,
        remarks,
        status
    FROM attendance
    WHERE student_id = :student_id 
        AND (status = 'Absent' OR status = 'Late')
        AND remarks IS NOT NULL AND remarks != ''
    ORDER BY attendance_date DESC
    LIMIT 4
");
$leave_stmt->bindValue(':student_id', $student_id);
$leave_stmt->execute();
$leave_records = $leave_stmt->fetchAll();

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

// Subject colors for chips
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

function getSubjectIcon($subject_title)
{
  $icons = [
    'Mathematics' => 'bi-calculator',
    'Math' => 'bi-calculator',
    'Physics' => 'bi-lightning-charge',
    'Chemistry' => 'bi-droplet-half',
    'Computer Science' => 'bi-pc-display',
    'Computer' => 'bi-pc-display',
    'English' => 'bi-book',
    'Urdu' => 'bi-pen',
    'Biology' => 'bi-heart-pulse',
    'default' => 'bi-journal-bookmark'
  ];
  foreach ($icons as $key => $icon) {
    if (stripos($subject_title, $key) !== false) {
      return $icon;
    }
  }
  return $icons['default'];
}

function getStatusBadge($status)
{
  switch ($status) {
    case 'Present':
      return 'p-ok';
    case 'Absent':
      return 'p-danger';
    case 'Late':
      return 'p-warn';
    default:
      return 'p-grey';
  }
}

function getStatusLabel($status)
{
  switch ($status) {
    case 'Present':
      return 'Present';
    case 'Absent':
      return 'Absent';
    case 'Late':
      return 'Late';
    default:
      return 'Unknown';
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="My Attendance — Crescent Public School Student Portal" />
  <title>My Attendance · Student Portal · Crescent Public School</title>
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
      --ok: #2b9c6e;
      --danger: #c9434a;
      --warn: #d97706;
      --info: #2563eb;
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

    .pill.p-warn {
      background: #fef3c7;
      color: #92400e;
    }

    .pill.p-danger {
      background: #fee2e2;
      color: #991b1b;
    }

    .pill.p-grey {
      background: #f1f5f9;
      color: #475569;
    }

    .pill.bare {
      background: transparent;
      border: 1px solid var(--border);
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

    .stat-card {
      background: white;
      border-radius: var(--radius);
      padding: 16px 18px;
      border: 1px solid var(--border);
      transition: all var(--transition);
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow);
    }

    .stat-card .stat-top {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 4px;
    }

    .stat-ico {
      width: 40px;
      height: 40px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      background: var(--bg);
    }

    .stat-card.teal .stat-ico {
      background: #d1fae5;
      color: #059669;
    }

    .stat-card.ok .stat-ico {
      background: #d1fae5;
      color: #065f46;
    }

    .stat-card.danger .stat-ico {
      background: #fee2e2;
      color: #dc2626;
    }

    .stat-card.amber .stat-ico {
      background: #fef3c7;
      color: #d97706;
    }

    .stat-trend {
      font-size: 0.7rem;
      font-weight: 500;
      color: var(--ok);
      background: #d1fae5;
      padding: 2px 10px;
      border-radius: 20px;
    }

    .stat-trend.down {
      color: var(--danger);
      background: #fee2e2;
    }

    .stat-num {
      font-size: 1.8rem;
      font-weight: 700;
      margin: 0;
      line-height: 1.2;
    }

    .stat-num small {
      font-size: 1rem;
      font-weight: 400;
      color: var(--muted);
    }

    .stat-title {
      font-weight: 600;
      margin: 0;
      font-size: 0.9rem;
    }

    .stat-desc {
      font-size: 0.75rem;
      color: var(--muted);
      margin: 0;
    }

    .stat-bar {
      display: block;
      height: 4px;
      border-radius: 4px;
      background: var(--bg);
      overflow: hidden;
      margin-top: 8px;
    }

    .stat-bar i {
      display: block;
      height: 100%;
      border-radius: 4px;
      background: var(--ok);
    }

    .stat-card.danger .stat-bar i {
      background: var(--danger);
    }

    .stat-card.amber .stat-bar i {
      background: var(--accent);
    }

    .donut {
      width: 140px;
      height: 140px;
      border-radius: 50%;
      background: conic-gradient(var(--ok) 0%
          <?php echo $attendance_stats['percentage']; ?>
          %, var(--danger)
          <?php echo $attendance_stats['percentage']; ?>
          %
          <?php echo $attendance_stats['percentage'] + (($attendance_stats['total_classes'] > 0 ? round(($attendance_stats['absent'] / $attendance_stats['total_classes']) * 100) : 0)); ?>
          %, var(--accent)
          <?php echo $attendance_stats['percentage'] + (($attendance_stats['total_classes'] > 0 ? round(($attendance_stats['absent'] / $attendance_stats['total_classes']) * 100) : 0)); ?>
          % 100%);
      margin: 0 auto 16px;
      position: relative;
    }

    .donut-mid {
      position: absolute;
      inset: 20px;
      border-radius: 50%;
      background: white;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
    }

    .donut-mid b {
      font-size: 1.6rem;
      display: block;
    }

    .donut-mid small {
      font-size: 0.7rem;
      color: var(--muted);
    }

    .legend {
      display: flex;
      flex-direction: column;
      gap: 4px;
      margin-top: 8px;
    }

    .legend-row {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.8rem;
    }

    .legend-row .dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      flex-shrink: 0;
    }

    .legend-row span {
      flex: 1;
      color: var(--muted);
    }

    .legend-row b {
      font-weight: 600;
      font-size: 0.8rem;
    }

    .divider-soft {
      height: 1px;
      background: var(--border);
      margin: 12px 0;
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

    .notice-row .notice-date {
      text-align: center;
      min-width: 44px;
      background: var(--bg);
      padding: 4px 8px;
      border-radius: var(--radius-sm);
    }

    .notice-row .notice-date b {
      display: block;
      font-size: 1.1rem;
    }

    .notice-row .notice-date small {
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

    .notice-row.ok {
      border-left: 3px solid var(--ok);
    }

    .notice-row.danger {
      border-left: 3px solid var(--danger);
    }

    .notice-row.amber {
      border-left: 3px solid var(--accent);
    }

    .bar {
      display: block;
      height: 6px;
      border-radius: 4px;
      background: var(--bg);
      overflow: hidden;
    }

    .bar.slim {
      height: 4px;
    }

    .bar i {
      display: block;
      height: 100%;
      border-radius: 4px;
      transition: width 0.6s ease;
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

    .t-strong {
      font-weight: 500;
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

    .rise-1 {
      animation-delay: 0.05s;
      opacity: 0;
      animation-fill-mode: forwards;
    }

    .rise-2 {
      animation-delay: 0.1s;
      opacity: 0;
      animation-fill-mode: forwards;
    }

    .rise-3 {
      animation-delay: 0.15s;
      opacity: 0;
      animation-fill-mode: forwards;
    }

    .rise-4 {
      animation-delay: 0.2s;
      opacity: 0;
      animation-fill-mode: forwards;
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

      .card-head {
        flex-direction: column;
        align-items: flex-start;
      }

      .stat-num {
        font-size: 1.4rem;
      }
    }

    @media (max-width: 576px) {
      .topbar-search {
        display: none;
      }

      .app-topbar {
        padding: 10px 16px;
      }

      .fact strong {
        font-size: 1rem;
      }

      .page-foot {
        flex-direction: column;
        text-align: center;
      }

      .donut {
        width: 100px;
        height: 100px;
      }

      .donut-mid {
        inset: 14px;
      }

      .donut-mid b {
        font-size: 1.2rem;
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
      <span class="avatar avatar-lg"><?php echo $student_initials; ?></span>
      <div class="student-card-text">
        <strong><?php echo htmlspecialchars($student_name); ?></strong>
        <small><?php echo htmlspecialchars($class_name . ' · ' . ($student['student_uid'] ?? 'STU-0000')); ?></small>
      </div>
      <span class="verify" title="Verified student"><i class="bi bi-patch-check-fill"></i></span>
    </div>
    <nav class="sidebar-nav">
      <p class="nav-group">Overview</p>
      <a class="nav-item" href="index.php"><i class="bi bi-columns-gap"></i><span>Dashboard</span></a>
      <p class="nav-group">Academics</p>
      <a class="nav-item" href="subjects.php"><i class="bi bi-journal-bookmark"></i><span>My Subjects</span><em
          class="nav-tag"><?php echo count($subject_attendance); ?></em></a>
      <a class="nav-item" href="timetable.php"><i class="bi bi-calendar-week"></i><span>My Timetable</span></a>
      <a class="nav-item active" href="attendance.php"><i class="bi bi-check2-square"></i><span>My Attendance</span></a>
      <a class="nav-item" href="assignments.php"><i class="bi bi-journal-text"></i><span>Assignments</span><em
          class="nav-tag nav-tag-warn"><?php echo $assignment_count; ?></em></a>
      <a class="nav-item" href="exams.php"><i class="bi bi-pencil-square"></i><span>Exams</span><em
          class="nav-tag nav-tag-info">3</em></a>
      <a class="nav-item" href="results.php"><i class="bi bi-graph-up-arrow"></i><span>Results</span></a>
      <p class="nav-group">Finance</p>
      <a class="nav-item" href="fees.php"><i class="bi bi-wallet2"></i><span>Fees</span><em
          class="nav-tag nav-tag-danger">1</em></a>
      <p class="nav-group">School Life</p>
      <a class="nav-item" href="notices.php"><i class="bi bi-megaphone"></i><span>Notices</span></a>
      <a class="nav-item" href="events.php"><i class="bi bi-calendar2-heart"></i><span>Events</span></a>
      <a class="nav-item" href="messages.php"><i class="bi bi-envelope"></i><span>Messages</span><em
          class="nav-tag"><?php echo $unread_count; ?></em></a>
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
        <h1>My Attendance</h1>
        <div class="crumbs"><a href="index.php">Home</a><span>/</span>Academics<span>/</span>My Attendance</div>
      </div>
      <div class="topbar-search"><i class="bi bi-search"></i><input type="search"
          placeholder="Search attendance records…" aria-label="Search" id="attendanceSearch" /></div>
      <div class="topbar-actions">
        <div class="icon-drop">
          <a href="notices.php" class="icon-btn" aria-label="Notifications"><i class="bi bi-bell"></i><span
              class="ping"><?php echo $notice_count; ?></span></a>
          <div class="drop-panel">
            <div class="drop-head"><strong>Notifications</strong><a href="notices.php">View all</a></div>
            <a href="exams.php" class="drop-row"><i class="dot-ico p-info"><i class="bi bi-pencil-square"></i></i><span>
                <p>Mid-Term timetable published</p><small>Examination Cell · 2 hours ago</small>
              </span></a>
            <a href="assignments.php" class="drop-row"><i class="dot-ico p-warn"><i
                  class="bi bi-journal-text"></i></i><span>
                <p>Assignments due soon</p><small>Mr. Ahmed · <?php echo $assignment_count; ?> pending</small>
              </span></a>
          </div>
        </div>
        <div class="icon-drop">
          <a href="messages.php" class="icon-btn" aria-label="Messages"><i class="bi bi-envelope"></i><span
              class="ping"><?php echo $unread_count; ?></span></a>
          <div class="drop-panel">
            <div class="drop-head"><strong>Messages</strong><a href="messages.php">Open inbox</a></div>
            <a href="messages.php" class="drop-row"><span class="avatar info">SR</span><span>
                <p>Ms. Sara Khan · Lab report feedback</p><small>Today, 09:14 AM</small>
              </span></a>
          </div>
        </div>
        <div class="icon-drop">
          <a href="profile.php" class="profile-chip"><span class="avatar"><?php echo $student_initials; ?></span><span
              class="who"><b><?php echo htmlspecialchars($student_name); ?></b><small><?php echo htmlspecialchars($class_name); ?></small></span><i
              class="bi bi-chevron-down"></i></a>
          <div class="drop-panel">
            <div class="drop-head"><strong><?php echo htmlspecialchars($student_name); ?></strong><span
                class="pill p-ok">Active</span></div>
            <a href="profile.php" class="drop-row"><i class="dot-ico p-teal"><i
                  class="bi bi-person-badge"></i></i><span>
                <p>My Profile</p><small><?php echo $student['student_uid'] ?? 'STU-0000'; ?></small>
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
            <span class="eyebrow">Session 2026–27 · Term 1 record</span>
            <h2>
              <?php echo $attendance_stats['percentage'] >= 75 ? 'You are comfortably above the 75% requirement' : 'Attendance needs improvement'; ?>
            </h2>
            <p>A minimum of 75% attendance is required to sit for the Mid-Term examinations. You currently stand at
              <?php echo $attendance_stats['percentage']; ?>% with <?php echo $attendance_stats['absent']; ?> absences
              and <?php echo $attendance_stats['late']; ?> late arrivals recorded this term.</p>
            <div class="quick-chips">
              <a href="timetable.php" class="chip"><i class="bi bi-calendar-week"></i> Timetable</a>
              <a href="messages.php" class="chip"><i class="bi bi-envelope"></i> Apply for Leave</a>
              <a href="results.php" class="chip"><i class="bi bi-graph-up-arrow"></i> Results</a>
            </div>
          </div>
          <div class="col-lg-4">
            <div class="row g-2">
              <div class="col-6">
                <div class="fact"><small>Total
                    Classes</small><strong><?php echo $attendance_stats['total_classes']; ?></strong><span>Term 1</span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Present</small><strong><?php echo $attendance_stats['present']; ?></strong><span><?php echo $attendance_stats['percentage']; ?>%</span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Absent</small><strong><?php echo $attendance_stats['absent']; ?></strong><span><?php echo $attendance_stats['total_classes'] > 0 ? round(($attendance_stats['absent'] / $attendance_stats['total_classes']) * 100) : 0; ?>%</span>
                </div>
              </div>
              <div class="col-6">
                <div class="fact">
                  <small>Late</small><strong><?php echo $attendance_stats['late']; ?></strong><span><?php echo $attendance_stats['total_classes'] > 0 ? round(($attendance_stats['late'] / $attendance_stats['total_classes']) * 100) : 0; ?>%</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- STATISTICS -->
      <section class="row g-3 mt-4">
        <div class="col-6 col-xl-3 rise rise-1">
          <div class="stat-card teal">
            <div class="stat-top"><span class="stat-ico"><i class="bi bi-pie-chart"></i></span><span
                class="stat-trend"><?php echo $attendance_stats['percentage'] >= 75 ? '+Good' : 'Low'; ?></span></div>
            <h3 class="stat-num"><?php echo $attendance_stats['percentage']; ?><small>%</small></h3>
            <p class="stat-title">Overall Attendance</p>
            <p class="stat-desc"><?php echo $attendance_stats['present']; ?> of
              <?php echo $attendance_stats['total_classes']; ?> classes attended</p>
            <span class="stat-bar"><i style="width:<?php echo $attendance_stats['percentage']; ?>%"></i></span>
          </div>
        </div>
        <div class="col-6 col-xl-3 rise rise-2">
          <div class="stat-card ok">
            <div class="stat-top"><span class="stat-ico"><i class="bi bi-check2-circle"></i></span><span
                class="stat-trend">Consistent</span></div>
            <h3 class="stat-num"><?php echo $attendance_stats['present']; ?></h3>
            <p class="stat-title">Present</p>
            <p class="stat-desc">
              <?php echo $attendance_stats['total_classes'] > 0 ? round(($attendance_stats['present'] / $attendance_stats['total_classes']) * 100) : 0; ?>%
              of all conducted classes</p>
            <span class="stat-bar"><i
                style="width:<?php echo $attendance_stats['total_classes'] > 0 ? round(($attendance_stats['present'] / $attendance_stats['total_classes']) * 100) : 0; ?>%"></i></span>
          </div>
        </div>
        <div class="col-6 col-xl-3 rise rise-3">
          <div class="stat-card danger">
            <div class="stat-top"><span class="stat-ico"><i class="bi bi-x-circle"></i></span><span
                class="stat-trend down"><?php echo $attendance_stats['absent']; ?> total</span></div>
            <h3 class="stat-num"><?php echo $attendance_stats['absent']; ?></h3>
            <p class="stat-title">Absent</p>
            <p class="stat-desc">
              <?php echo $attendance_stats['total_classes'] > 0 ? round(($attendance_stats['absent'] / $attendance_stats['total_classes']) * 100) : 0; ?>%
              of classes missed</p>
            <span class="stat-bar"><i
                style="width:<?php echo $attendance_stats['total_classes'] > 0 ? min(round(($attendance_stats['absent'] / $attendance_stats['total_classes']) * 100), 100) : 0; ?>%"></i></span>
          </div>
        </div>
        <div class="col-6 col-xl-3 rise rise-4">
          <div class="stat-card amber">
            <div class="stat-top"><span class="stat-ico"><i class="bi bi-clock-history"></i></span><span
                class="stat-trend down"><?php echo $attendance_stats['late']; ?></span></div>
            <h3 class="stat-num"><?php echo $attendance_stats['late']; ?></h3>
            <p class="stat-title">Late Arrivals</p>
            <p class="stat-desc">
              <?php echo $attendance_stats['total_classes'] > 0 ? round(($attendance_stats['late'] / $attendance_stats['total_classes']) * 100) : 0; ?>%
              of classes</p>
            <span class="stat-bar"><i
                style="width:<?php echo $attendance_stats['total_classes'] > 0 ? min(round(($attendance_stats['late'] / $attendance_stats['total_classes']) * 100), 100) : 0; ?>%"></i></span>
          </div>
        </div>
      </section>

      <!-- DONUT + SUBJECT WISE -->
      <section class="row g-3 mt-1">
        <div class="col-xl-4 rise">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Attendance Split <span class="sub">Present / Absent / Late</span></h3>
            </div>
            <div class="card-body text-center">
              <div class="donut">
                <div class="donut-mid">
                  <b><?php echo $attendance_stats['percentage']; ?>%</b>
                  <small>Present</small>
                </div>
              </div>
              <div class="legend">
                <div class="legend-row"><i class="dot" style="background:var(--ok)"></i><span>Present ·
                    <?php echo $attendance_stats['present']; ?>
                    classes</span><b><?php echo $attendance_stats['total_classes'] > 0 ? round(($attendance_stats['present'] / $attendance_stats['total_classes']) * 100) : 0; ?>%</b>
                </div>
                <div class="legend-row"><i class="dot" style="background:var(--danger)"></i><span>Absent ·
                    <?php echo $attendance_stats['absent']; ?>
                    classes</span><b><?php echo $attendance_stats['total_classes'] > 0 ? round(($attendance_stats['absent'] / $attendance_stats['total_classes']) * 100) : 0; ?>%</b>
                </div>
                <div class="legend-row"><i class="dot" style="background:var(--accent)"></i><span>Late ·
                    <?php echo $attendance_stats['late']; ?>
                    classes</span><b><?php echo $attendance_stats['total_classes'] > 0 ? round(($attendance_stats['late'] / $attendance_stats['total_classes']) * 100) : 0; ?>%</b>
                </div>
              </div>
              <div class="divider-soft"></div>
              <div class="alert-soft teal text-start">
                <i class="bi bi-shield-check"></i>
                <span>
                  <b><?php echo $attendance_stats['percentage'] >= 75 ? 'Eligible for Mid-Terms.' : 'Attendance below 75% requirement.'; ?></b>
                  <?php echo $attendance_stats['percentage'] >= 75 ? 'Attendance requirement of 75% is satisfied with a healthy margin.' : 'Please improve your attendance to meet the 75% requirement.'; ?>
                </span>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-8 rise rise-2">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Class-wise Attendance <span class="sub">Term 1, session 2026–27</span></h3>
            </div>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Class</th>
                    <th>Total Classes</th>
                    <th>Present</th>
                    <th>Absent</th>
                    <th>Percentage</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (count($subject_attendance) > 0): ?>
                    <?php foreach ($subject_attendance as $subject):
                      $color = getSubjectColor($subject['subject_title'] ?? '');
                      $icon = getSubjectIcon($subject['subject_title'] ?? '');
                      $pct = $subject['total_classes'] > 0 ? round(($subject['present'] / $subject['total_classes']) * 100) : 0;
                      ?>
                      <tr>
                        <td><span class="subject-chip <?php echo $color; ?>"><i
                              class="bi <?php echo $icon; ?>"></i><?php echo htmlspecialchars($subject['subject_title'] ?? 'Unknown'); ?></span>
                        </td>
                        <td><?php echo $subject['total_classes']; ?></td>
                        <td><?php echo $subject['present']; ?></td>
                        <td><?php echo $subject['absent']; ?></td>
                        <td style="min-width:150px">
                          <span class="bar slim"><i
                              style="width:<?php echo $pct; ?>%;background:<?php echo $pct >= 90 ? '#2b6da9' : ($pct >= 75 ? '#e8a838' : '#dc2626'); ?>"></i></span>
                          <small style="font-size:.7rem;color:var(--muted)"><?php echo $pct; ?>%</small>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="5" class="text-center py-3 text-muted">No class-wise attendance data available</td>
                    </tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </section>

      <!-- MONTHLY TREND -->
      <section class="row g-3 mt-1">
        <div class="col-xl-7 rise">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Monthly Trend <span class="sub">Attendance percentage by month</span></h3>
            </div>
            <div class="card-body">
              <div class="d-flex align-items-end gap-3" style="height:190px">
                <?php if (count($monthly_percentages) > 0): ?>
                  <?php foreach ($monthly_percentages as $month):
                    $height = max($month['percentage'], 10);
                    $color = $month['percentage'] >= 90 ? '#12747f' : ($month['percentage'] >= 75 ? '#e6b25f' : '#c9434a');
                    ?>
                    <div class="text-center flex-fill">
                      <div class="mx-auto rounded-top"
                        style="height:<?php echo $height; ?>%;width:70%;background:linear-gradient(180deg,<?php echo $color; ?>,<?php echo $color; ?>)">
                      </div>
                      <small style="font-size:.7rem;color:var(--muted)"><?php echo substr($month['month'], 0, 3); ?></small>
                      <b style="font-size:.75rem;display:block"><?php echo $month['percentage']; ?>%</b>
                    </div>
                  <?php endforeach; ?>
                <?php else: ?>
                  <div class="text-center w-100 py-4 text-muted">No monthly data available</div>
                <?php endif; ?>
              </div>
              <div class="divider-soft"></div>
              <p class="mb-0" style="font-size:.78rem;color:var(--muted)">
                <i class="bi bi-info-circle me-1"></i>
                <?php echo count($monthly_percentages) > 0 ? 'Monthly attendance trend shows your performance over time.' : 'Start attending classes to build your attendance record.'; ?>
              </p>
            </div>
          </div>
        </div>
        <div class="col-xl-5 rise rise-2">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Leave Records <span class="sub">Term 1 applications</span></h3>
            </div>
            <div class="card-body tight">
              <?php if (count($leave_records) > 0): ?>
                <?php foreach ($leave_records as $leave):
                  $status = $leave['status'] == 'Absent' ? 'danger' : 'amber';
                  $status_pill = $leave['status'] == 'Absent' ? 'p-danger' : 'p-warn';
                  $status_label = $leave['status'] == 'Absent' ? 'Unapproved' : 'Late';
                  ?>
                  <div class="notice-row <?php echo $status; ?>">
                    <div class="notice-date">
                      <b><?php echo date('d', strtotime($leave['attendance_date'])); ?></b>
                      <small><?php echo date('M', strtotime($leave['attendance_date'])); ?></small>
                    </div>
                    <div>
                      <h4><?php echo $leave['status']; ?></h4>
                      <p><?php echo htmlspecialchars($leave['remarks'] ?? 'No remarks'); ?></p>
                      <span class="pill <?php echo $status_pill; ?>"><?php echo $status_label; ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="text-center py-3 text-muted">
                  <i class="bi bi-file-text fs-4 d-block mb-2"></i>
                  <p>No leave records found</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </section>

      <!-- DETAILED RECORD -->
      <section class="mt-4 rise">
        <div class="card lift">
          <div class="card-head">
            <h3>Attendance Record <span class="sub">Most recent entries first</span></h3>
            <span class="pill p-teal"><i class="bi bi-funnel"></i> Term 1 · All classes</span>
          </div>
          <div class="table-responsive">
            <table class="table" id="attendanceTable">
              <thead>
                <tr>
                  <th>Date</th>
                  <th>Class</th>
                  <th>Status</th>
                  <th>Remarks</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($recent_records) > 0): ?>
                  <?php foreach ($recent_records as $record):
                    $status_badge = getStatusBadge($record['status']);
                    $status_label = getStatusLabel($record['status']);
                    ?>
                    <tr>
                      <td class="t-strong"><?php echo date('M d, Y', strtotime($record['attendance_date'])); ?></td>
                      <td><?php echo htmlspecialchars($record['subject_title'] ?? 'N/A'); ?></td>
                      <td><span class="pill <?php echo $status_badge; ?>"><?php echo $status_label; ?></span></td>
                      <td><?php echo htmlspecialchars($record['remarks'] ?? '—'); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="4" class="text-center py-3 text-muted">No attendance records found</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="card-body d-flex flex-wrap gap-2 justify-content-between align-items-center">
            <span style="font-size:.78rem;color:var(--muted)">Showing <?php echo min(count($recent_records), 12); ?> of
              <?php echo $attendance_stats['total_classes']; ?> attendance records for Term 1.</span>
            <span class="d-flex gap-2">
              <a href="messages.php" class="btn-outline"><i class="bi bi-envelope-plus"></i> Apply for Leave</a>
              <a href="results.php" class="btn-solid"><i class="bi bi-file-earmark-text"></i> Request Report</a>
            </span>
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
    // Search functionality for attendance records
    document.getElementById('attendanceSearch')?.addEventListener('keyup', function () {
      const searchTerm = this.value.toLowerCase();
      const rows = document.querySelectorAll('#attendanceTable tbody tr');
      let visibleCount = 0;

      rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        const matches = text.includes(searchTerm);

        if (searchTerm.length > 0) {
          row.style.display = matches ? '' : 'none';
          if (matches) visibleCount++;
        } else {
          row.style.display = '';
          visibleCount++;
        }
      });

      // Show/hide no results message
      let noResults = document.getElementById('noResultsMsg');
      if (visibleCount === 0 && searchTerm.length > 0) {
        const table = document.querySelector('#attendanceTable');
        if (!noResults) {
          noResults = document.createElement('div');
          noResults.id = 'noResultsMsg';
          noResults.className = 'text-center py-4 text-muted';
          noResults.innerHTML = `
                    <i class="bi bi-search fs-1 d-block mb-3"></i>
                    <h5>No records found</h5>
                    <p>Try adjusting your search terms</p>
                `;
          table.parentNode.appendChild(noResults);
        }
        noResults.style.display = '';
      } else if (noResults) {
        noResults.style.display = 'none';
      }
    });
  </script>

</body>

</html>