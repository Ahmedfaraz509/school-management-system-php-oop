<?php
// profile.php - My Profile page with working image

require_once '../database/connect.php';
require_once 'helpers.php';

// Get student ID from session (for demo, using student_id = 4)
$student_id = 4;

// ===== HANDLE FORM SUBMISSION =====
$update_message = '';
$update_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
  try {
    $first_name = $_POST['first_name'] ?? '';
    $last_name = $_POST['last_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $address = $_POST['address'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';

    $query = "UPDATE students 
                  SET first_name = ?, last_name = ?, email = ?, 
                      address = ?, date_of_birth = ?, gender = ?
                  WHERE id = ?";

    $stmt = $conn->prepare($query);
    $result = $stmt->execute([
      $first_name,
      $last_name,
      $email,
      $address,
      $date_of_birth,
      $gender,
      $student_id
    ]);

    if ($result) {
      $update_message = 'Profile updated successfully!';
      $update_type = 'success';
    } else {
      $update_message = 'Failed to update profile. Please try again.';
      $update_type = 'error';
    }
  } catch (PDOException $e) {
    $update_message = 'Database error: ' . $e->getMessage();
    $update_type = 'error';
  }
}

// ===== GET STUDENT DATA =====
$studentQuery = "SELECT s.*, c.name as class_name, c.grade, c.id as class_id 
                 FROM students s 
                 LEFT JOIN classes c ON s.class_id = c.id 
                 WHERE s.id = ?";
$stmt = $conn->prepare($studentQuery);
$stmt->execute([$student_id]);
$student = $stmt->fetch();

// Get parent data
$parentQuery = "SELECT * FROM parents WHERE student_id = ? LIMIT 1";
$stmt = $conn->prepare($parentQuery);
$stmt->execute([$student_id]);
$parent = $stmt->fetch();

// Get attendance summary
$attendanceQuery = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present
                    FROM attendance 
                    WHERE student_id = ?";
$stmt = $conn->prepare($attendanceQuery);
$stmt->execute([$student_id]);
$attendance = $stmt->fetch();

$attendancePercentage = 0;
if ($attendance && $attendance['total'] > 0) {
  $attendancePercentage = round(($attendance['present'] / $attendance['total']) * 100);
}

// Get subject count
$subjectQuery = "SELECT COUNT(*) as count FROM subjects WHERE class_id = ?";
$stmt = $conn->prepare($subjectQuery);
$stmt->execute([$student['class_id'] ?? 0]);
$subjectCount = $stmt->fetch();

// Get GPA
$gpaQuery = "SELECT AVG(percentage) as avg_percentage FROM results WHERE student_id = ?";
$stmt = $conn->prepare($gpaQuery);
$stmt->execute([$student_id]);
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

// Get class teacher
$teacherQuery = "SELECT t.full_name FROM teachers t 
                 JOIN classes c ON c.teacher_id = t.id 
                 WHERE c.id = ?";
$stmt = $conn->prepare($teacherQuery);
$stmt->execute([$student['class_id'] ?? 0]);
$teacher = $stmt->fetch();

// Format student data
$firstName = htmlspecialchars($student['first_name'] ?? 'Ahmed');
$lastName = htmlspecialchars($student['last_name'] ?? 'Faraz');
$studentName = $firstName . ' ' . $lastName;
$studentInitials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
$studentClass = htmlspecialchars($student['class_name'] ?? 'Class 10');
$studentUID = htmlspecialchars($student['student_uid'] ?? 'STU-1024');
$studentEmail = htmlspecialchars($student['email'] ?? 'student@crescent.edu');
$studentDob = $student['date_of_birth'] ?? '2011-03-14';
$studentGender = htmlspecialchars($student['gender'] ?? 'Male');
$studentAddress = htmlspecialchars($student['address'] ?? 'North Nazimabad, Karachi');
$admissionDate = htmlspecialchars($student['admission_date'] ?? '2021-04-05');
$photoUrl = $student['photo_url'] ?? '';

// Parent info
$parentName = htmlspecialchars($parent['full_name'] ?? 'Muhammad Faraz');
$parentPhone = htmlspecialchars($parent['phone'] ?? '+92 300 7778899');
$parentEmail = htmlspecialchars($parent['email'] ?? 'm.faraz@example.com');

// Teacher info
$teacherName = htmlspecialchars($teacher['full_name'] ?? 'Ms. Farah Noor');

// Get section from class name
$section = 'A';
if (strpos($studentClass, '-') !== false) {
  $parts = explode('-', $studentClass);
  $section = end($parts);
}
$classNumber = str_replace('Class ', '', $studentClass);

// ===== PROFILE IMAGE - FIXED =====
// Check if photo exists in database
if (!empty($photoUrl)) {
  // Check if file exists in the uploads folder
  $fullPath = '../' . $photoUrl;
  if (file_exists($fullPath)) {
    $profileImage = $fullPath;
  } else {
    // Try without ../ 
    if (file_exists($photoUrl)) {
      $profileImage = $photoUrl;
    } else {
      $profileImage = null;
    }
  }
}

// If no image found, generate SVG with initials
if (empty($profileImage)) {
  $profileImage = 'data:image/svg+xml,' . urlencode('<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120"><rect width="120" height="120" rx="28" fill="#3b82f6"/><text x="60" y="78" text-anchor="middle" font-size="48" font-family="Arial" font-weight="bold" fill="#ffffff">' . $studentInitials . '</text></svg>');
}

// Achievements
$achievements = [
  ['icon' => 'bi-trophy', 'title' => 'Inter-House Quiz', 'detail' => '2nd Place'],
  ['icon' => 'bi-award', 'title' => 'Merit Scholarship', 'detail' => '10% waiver'],
  ['icon' => 'bi-star', 'title' => 'Monthly Star Award', 'detail' => 'May 2026'],
  ['icon' => 'bi-mortarboard', 'title' => 'Science Club', 'detail' => 'Member'],
  ['icon' => 'bi-people', 'title' => 'Class Monitor', 'detail' => '2025–26']
];

// Login activity
$loginActivity = [
  ['icon' => 'bi-laptop', 'device' => 'Chrome on Windows', 'time' => 'Today, 08:02 AM'],
  ['icon' => 'bi-phone', 'device' => 'Mobile app · Android', 'time' => 'Yesterday, 07:41 PM'],
  ['icon' => 'bi-laptop', 'device' => 'Library computer', 'time' => '22 Aug 2026'],
  ['icon' => 'bi-phone', 'device' => 'Mobile app · Android', 'time' => '21 Aug 2026']
];

// Documents
$documents = [
  ['icon' => 'bi-file-earmark-text', 'title' => 'School Identity Card', 'status' => 'Verified', 'status_class' => 'p-ok'],
  ['icon' => 'bi-file-earmark-pdf', 'title' => 'Birth Certificate', 'status' => 'Verified', 'status_class' => 'p-ok'],
  ['icon' => 'bi-file-earmark-medical', 'title' => 'Vaccination Record', 'status' => 'Under Review', 'status_class' => 'p-warn'],
  ['icon' => 'bi-camera', 'title' => 'Profile Photograph', 'status' => 'Approved', 'status_class' => 'p-ok']
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta name="description" content="My Profile — Crescent Public School Student Portal" />
  <title>My Profile &middot; Student Portal &middot; Crescent Public School</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet" />
  <link rel="icon" href="assets/images/logo.svg" type="image/svg+xml" />
  <style>
    /* ===== All styles from exams page ===== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      background: #0a0e1a;
      color: #e8edf5;
      min-height: 100vh;
      display: flex;
    }

    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }

    ::-webkit-scrollbar-track {
      background: #141b2b;
    }

    ::-webkit-scrollbar-thumb {
      background: #2a3a5a;
      border-radius: 10px;
    }

    ::-webkit-scrollbar-thumb:hover {
      background: #3a4a6a;
    }

    .app-sidebar {
      width: 260px;
      min-height: 100vh;
      background: linear-gradient(180deg, #0d1225 0%, #0a0e1a 100%);
      border-right: 1px solid rgba(255, 255, 255, 0.04);
      display: flex;
      flex-direction: column;
      flex-shrink: 0;
      position: sticky;
      top: 0;
      height: 100vh;
      overflow-y: auto;
      z-index: 100;
    }

    .sidebar-head {
      padding: 20px 20px 16px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .sidebar-brand {
      display: flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      color: #e8edf5;
    }

    .sidebar-brand img {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      background: rgba(96, 165, 250, 0.15);
      padding: 6px;
    }

    .brand-text strong {
      display: block;
      font-size: 0.85rem;
      font-weight: 700;
      letter-spacing: -0.3px;
    }

    .brand-text small {
      font-size: 0.6rem;
      opacity: 0.4;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .sidebar-close {
      display: none;
      color: #e8edf5;
      font-size: 1.2rem;
      cursor: pointer;
    }

    .student-card {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 20px;
      margin: 12px 16px 8px;
      background: linear-gradient(135deg, rgba(96, 165, 250, 0.08), rgba(96, 165, 250, 0.02));
      border-radius: 14px;
      border: 1px solid rgba(96, 165, 250, 0.06);
    }

    .avatar {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      border-radius: 50%;
      background: linear-gradient(135deg, #3b82f6, #6366f1);
      color: #fff;
      font-weight: 700;
      font-size: 0.85rem;
      flex-shrink: 0;
    }

    .avatar-lg {
      width: 44px;
      height: 44px;
      font-size: 1rem;
    }

    .avatar.ok {
      background: linear-gradient(135deg, #34d399, #6ee7b7);
    }

    .avatar.info {
      background: linear-gradient(135deg, #3b82f6, #6366f1);
    }

    .avatar.amber {
      background: linear-gradient(135deg, #f59e0b, #fbbf24);
    }

    .avatar.violet {
      background: linear-gradient(135deg, #8b5cf6, #a78bfa);
    }

    .student-card-text {
      flex: 1;
      min-width: 0;
    }

    .student-card-text strong {
      display: block;
      font-size: 0.85rem;
      font-weight: 600;
    }

    .student-card-text small {
      font-size: 0.65rem;
      opacity: 0.5;
      display: block;
    }

    .verify {
      color: #34d399;
      font-size: 0.85rem;
    }

    .sidebar-nav {
      flex: 1;
      padding: 8px 12px 20px;
      overflow-y: auto;
    }

    .nav-group {
      padding: 16px 12px 6px;
      font-size: 0.6rem;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      opacity: 0.3;
      font-weight: 600;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 9px 12px;
      border-radius: 10px;
      color: #7a8aa8;
      text-decoration: none;
      transition: all 0.2s;
      font-size: 0.85rem;
      font-weight: 500;
      position: relative;
    }

    .nav-item:hover {
      background: rgba(255, 255, 255, 0.04);
      color: #e8edf5;
    }

    .nav-item.active {
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.12), rgba(99, 102, 241, 0.06));
      color: #60a5fa;
    }

    .nav-item.active::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      transform: translateY(-50%);
      width: 3px;
      height: 24px;
      background: linear-gradient(180deg, #3b82f6, #6366f1);
      border-radius: 0 4px 4px 0;
    }

    .nav-item i {
      font-size: 1.1rem;
      width: 20px;
      text-align: center;
      flex-shrink: 0;
    }

    .nav-item span {
      flex: 1;
    }

    .nav-tag {
      background: rgba(255, 255, 255, 0.06);
      padding: 1px 10px;
      border-radius: 12px;
      font-size: 0.6rem;
      font-weight: 600;
    }

    .nav-tag-info {
      background: rgba(96, 165, 250, 0.15);
      color: #60a5fa;
    }

    .nav-tag-warn {
      background: rgba(251, 191, 36, 0.15);
      color: #fbbf24;
    }

    .nav-tag-danger {
      background: rgba(239, 68, 68, 0.15);
      color: #f87171;
    }

    .sidebar-foot {
      padding: 12px 20px 16px;
      border-top: 1px solid rgba(255, 255, 255, 0.04);
      margin-top: auto;
    }

    .logout-btn {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 9px 12px;
      border-radius: 10px;
      color: #f87171;
      text-decoration: none;
      transition: all 0.2s;
      font-size: 0.85rem;
      font-weight: 500;
    }

    .logout-btn:hover {
      background: rgba(239, 68, 68, 0.08);
    }

    .copy {
      font-size: 0.6rem;
      opacity: 0.2;
      margin: 8px 0 0 12px;
      letter-spacing: 0.3px;
    }

    .app-main {
      flex: 1;
      min-height: 100vh;
      background: linear-gradient(180deg, #0d1225 0%, #0a0e1a 100%);
      display: flex;
      flex-direction: column;
    }

    .app-topbar {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 14px 28px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      background: rgba(13, 18, 37, 0.8);
      backdrop-filter: blur(12px);
      position: sticky;
      top: 0;
      z-index: 50;
      flex-wrap: wrap;
    }

    .nav-btn {
      display: none;
      font-size: 1.4rem;
      color: #e8edf5;
      cursor: pointer;
      background: none;
      border: none;
      padding: 4px;
    }

    .topbar-title {
      flex: 1;
      min-width: 120px;
    }

    .topbar-title h1 {
      font-size: 1.3rem;
      font-weight: 700;
      margin: 0;
      letter-spacing: -0.5px;
      background: linear-gradient(135deg, #e8edf5, #94a3b8);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .topbar-title .crumbs {
      font-size: 0.7rem;
      opacity: 0.35;
      margin-top: 2px;
      -webkit-text-fill-color: #94a3b8;
    }

    .topbar-title .crumbs a {
      color: #94a3b8;
      text-decoration: none;
      -webkit-text-fill-color: #94a3b8;
    }

    .topbar-title .crumbs a:hover {
      color: #e8edf5;
    }

    .topbar-title .crumbs span {
      margin: 0 4px;
    }

    .topbar-search {
      display: flex;
      align-items: center;
      background: rgba(255, 255, 255, 0.04);
      border-radius: 10px;
      padding: 6px 14px;
      border: 1px solid rgba(255, 255, 255, 0.04);
      transition: all 0.3s;
      min-width: 200px;
    }

    .topbar-search:focus-within {
      border-color: rgba(96, 165, 250, 0.3);
      background: rgba(255, 255, 255, 0.06);
    }

    .topbar-search i {
      opacity: 0.3;
      margin-right: 10px;
      font-size: 0.9rem;
    }

    .topbar-search input {
      background: transparent;
      border: none;
      color: #e8edf5;
      padding: 6px 0;
      outline: none;
      width: 100%;
      font-size: 0.85rem;
    }

    .topbar-search input::placeholder {
      color: #4a5a7a;
    }

    .topbar-actions {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .icon-btn {
      position: relative;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 38px;
      height: 38px;
      border-radius: 10px;
      color: #7a8aa8;
      text-decoration: none;
      transition: all 0.2s;
      border: none;
      background: transparent;
      cursor: pointer;
    }

    .icon-btn:hover {
      background: rgba(255, 255, 255, 0.04);
      color: #e8edf5;
    }

    .icon-btn .ping {
      position: absolute;
      top: 2px;
      right: 2px;
      background: #ef4444;
      color: #fff;
      font-size: 0.55rem;
      padding: 1px 6px;
      border-radius: 10px;
      min-width: 18px;
      text-align: center;
      font-weight: 700;
      box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
    }

    .profile-chip {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 4px 14px 4px 4px;
      border-radius: 30px;
      background: rgba(255, 255, 255, 0.04);
      color: #e8edf5;
      text-decoration: none;
      transition: all 0.2s;
      cursor: pointer;
      border: 1px solid rgba(255, 255, 255, 0.04);
    }

    .profile-chip:hover {
      background: rgba(255, 255, 255, 0.08);
    }

    .profile-chip .who {
      line-height: 1.2;
    }

    .profile-chip .who b {
      display: block;
      font-size: 0.8rem;
      font-weight: 600;
    }

    .profile-chip .who small {
      font-size: 0.6rem;
      opacity: 0.4;
    }

    .profile-chip .bi-chevron-down {
      font-size: 0.7rem;
      opacity: 0.3;
    }

    .icon-drop {
      position: relative;
    }

    .drop-panel {
      display: none;
      position: absolute;
      right: 0;
      top: calc(100% + 8px);
      min-width: 280px;
      background: #141b2b;
      border: 1px solid rgba(255, 255, 255, 0.06);
      border-radius: 14px;
      padding: 6px 0;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.6);
      z-index: 200;
      backdrop-filter: blur(20px);
    }

    .icon-drop:hover .drop-panel {
      display: block;
    }

    .drop-head {
      display: flex;
      justify-content: space-between;
      padding: 10px 16px 10px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      font-size: 0.85rem;
    }

    .drop-head a {
      color: #60a5fa;
      text-decoration: none;
      font-size: 0.75rem;
      font-weight: 500;
    }

    .drop-row {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 16px;
      color: #e8edf5;
      text-decoration: none;
      transition: background 0.2s;
      font-size: 0.85rem;
    }

    .drop-row:hover {
      background: rgba(255, 255, 255, 0.03);
    }

    .drop-row p {
      margin: 0;
    }

    .drop-row small {
      font-size: 0.65rem;
      opacity: 0.4;
      display: block;
    }

    .dot-ico {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
      border-radius: 8px;
      background: rgba(255, 255, 255, 0.04);
      flex-shrink: 0;
    }

    .dot-ico.p-info {
      background: rgba(96, 165, 250, 0.12);
      color: #60a5fa;
    }

    .dot-ico.p-warn {
      background: rgba(251, 191, 36, 0.12);
      color: #fbbf24;
    }

    .dot-ico.p-danger {
      background: rgba(239, 68, 68, 0.12);
      color: #f87171;
    }

    .dot-ico.p-teal {
      background: rgba(52, 211, 153, 0.12);
      color: #34d399;
    }

    .dot-ico.p-violet {
      background: rgba(167, 139, 250, 0.12);
      color: #a78bfa;
    }

    .dot-ico.p-ok {
      background: rgba(52, 211, 153, 0.12);
      color: #34d399;
    }

    .app-content {
      padding: 24px 28px 20px;
      flex: 1;
      max-width: 1400px;
      width: 100%;
      margin: 0 auto;
    }

    .welcome {
      background: linear-gradient(135deg, rgba(59, 130, 246, 0.06), rgba(99, 102, 241, 0.03));
      border-radius: 20px;
      padding: 28px 32px;
      border: 1px solid rgba(255, 255, 255, 0.04);
      position: relative;
      overflow: hidden;
    }

    .welcome::before {
      content: '';
      position: absolute;
      top: -50%;
      right: -20%;
      width: 400px;
      height: 400px;
      background: radial-gradient(circle, rgba(59, 130, 246, 0.04), transparent 70%);
      border-radius: 50%;
      pointer-events: none;
    }

    .welcome .eyebrow {
      display: inline-block;
      font-size: 0.65rem;
      text-transform: uppercase;
      letter-spacing: 1.5px;
      opacity: 0.35;
      font-weight: 600;
      margin-bottom: 4px;
    }

    .welcome h2 {
      font-size: 1.6rem;
      font-weight: 700;
      margin: 0 0 6px;
      letter-spacing: -0.5px;
    }

    .welcome p {
      opacity: 0.6;
      font-size: 0.9rem;
      margin: 0;
      max-width: 600px;
    }

    .welcome p b {
      color: #60a5fa;
      opacity: 1;
    }

    .quick-chips {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 14px;
    }

    .chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 14px;
      border-radius: 20px;
      background: rgba(255, 255, 255, 0.04);
      color: #94a3b8;
      text-decoration: none;
      font-size: 0.75rem;
      font-weight: 500;
      transition: all 0.2s;
      border: 1px solid rgba(255, 255, 255, 0.04);
    }

    .chip:hover {
      background: rgba(255, 255, 255, 0.08);
      color: #e8edf5;
      border-color: rgba(255, 255, 255, 0.08);
    }

    .chip i {
      font-size: 0.8rem;
    }

    .fact {
      background: rgba(255, 255, 255, 0.03);
      padding: 12px 14px;
      border-radius: 12px;
      text-align: center;
      border: 1px solid rgba(255, 255, 255, 0.04);
      height: 100%;
    }

    .fact small {
      display: block;
      font-size: 0.6rem;
      opacity: 0.3;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 600;
    }

    .fact strong {
      display: block;
      font-size: 1.1rem;
      font-weight: 700;
      margin: 4px 0 2px;
      color: #e8edf5;
    }

    .fact span {
      font-size: 0.7rem;
      opacity: 0.4;
    }

    .profile-image-container {
      border-radius: 28px;
      border: 3px solid rgba(255, 255, 255, .35);
      background: rgba(255, 255, 255, .12);
      padding: 4px;
      width: 120px;
      height: 120px;
      overflow: hidden;
      display: inline-block;
    }

    .profile-image-container img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 24px;
    }

    .alert-message {
      padding: 16px 20px;
      border-radius: 12px;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .alert-message.success {
      background: rgba(52, 211, 153, 0.12);
      border: 1px solid rgba(52, 211, 153, 0.2);
      color: #34d399;
    }

    .alert-message.error {
      background: rgba(239, 68, 68, 0.12);
      border: 1px solid rgba(239, 68, 68, 0.2);
      color: #f87171;
    }

    .alert-message i {
      font-size: 1.2rem;
    }

    .card {
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.02), rgba(255, 255, 255, 0.005));
      border: 1px solid rgba(255, 255, 255, 0.04);
      border-radius: 16px;
      overflow: hidden;
      transition: all 0.3s;
    }

    .card.lift:hover {
      border-color: rgba(255, 255, 255, 0.08);
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.2);
    }

    .card .card-head {
      padding: 18px 22px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 8px;
    }

    .card .card-head h3 {
      margin: 0;
      font-size: 1rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }

    .card .card-head h3 .sub {
      font-size: 0.7rem;
      opacity: 0.4;
      font-weight: 400;
    }

    .card .card-body {
      padding: 20px 22px;
    }

    .card .card-body.tight {
      padding: 12px 16px;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 2px 12px;
      border-radius: 20px;
      font-size: 0.7rem;
      font-weight: 600;
      letter-spacing: 0.2px;
    }

    .pill.p-grey {
      background: rgba(255, 255, 255, 0.04);
      color: #5a6a8a;
    }

    .pill.p-ok {
      background: rgba(52, 211, 153, 0.12);
      color: #34d399;
    }

    .pill.p-warn {
      background: rgba(251, 191, 36, 0.12);
      color: #fbbf24;
    }

    .pill.p-danger {
      background: rgba(239, 68, 68, 0.12);
      color: #f87171;
    }

    .pill.p-info {
      background: rgba(96, 165, 250, 0.12);
      color: #60a5fa;
    }

    .pill.p-teal {
      background: rgba(52, 211, 153, 0.12);
      color: #34d399;
    }

    .pill.p-violet {
      background: rgba(167, 139, 250, 0.12);
      color: #a78bfa;
    }

    .divider-soft {
      height: 1px;
      background: rgba(255, 255, 255, 0.04);
      margin: 12px 0;
    }

    .meta-list {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .meta-list li {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      font-size: 0.85rem;
    }

    .meta-list li:last-child {
      border-bottom: none;
    }

    .meta-list li i {
      color: #60a5fa;
      margin-right: 10px;
      width: 18px;
      text-align: center;
      opacity: 0.6;
    }

    .meta-list li span {
      color: #5a6a8a;
      font-weight: 500;
    }

    .notice-row {
      display: flex;
      gap: 12px;
      padding: 10px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      align-items: flex-start;
    }

    .notice-row:last-child {
      border-bottom: none;
    }

    .notice-row .notice-date {
      min-width: 40px;
      text-align: center;
      padding-top: 2px;
      flex-shrink: 0;
    }

    .notice-row .notice-date b {
      display: block;
      font-size: 1rem;
      font-weight: 700;
      color: #60a5fa;
    }

    .notice-row .notice-date small {
      font-size: 0.55rem;
      opacity: 0.3;
      text-transform: uppercase;
      letter-spacing: 0.3px;
    }

    .notice-row h4 {
      font-size: 0.85rem;
      font-weight: 600;
      margin: 0 0 2px;
    }

    .notice-row p {
      font-size: 0.75rem;
      opacity: 0.4;
      margin: 0;
    }

    .notice-row.teal .notice-date b {
      color: #34d399;
    }

    .notice-row.amber .notice-date b {
      color: #fbbf24;
    }

    .notice-row.info .notice-date b {
      color: #60a5fa;
    }

    .notice-row.violet .notice-date b {
      color: #a78bfa;
    }

    .alert-soft {
      display: flex;
      align-items: flex-start;
      gap: 12px;
      padding: 14px 18px;
      border-radius: 12px;
      background: rgba(52, 211, 153, 0.06);
      border-left: 3px solid #34d399;
      font-size: 0.85rem;
    }

    .alert-soft i {
      color: #34d399;
      font-size: 1.1rem;
      margin-top: 2px;
      flex-shrink: 0;
    }

    .alert-soft.teal {
      background: rgba(52, 211, 153, 0.06);
      border-left-color: #34d399;
    }

    .alert-soft.teal i {
      color: #34d399;
    }

    .alert-soft.amber {
      background: rgba(251, 191, 36, 0.06);
      border-left-color: #fbbf24;
    }

    .alert-soft.amber i {
      color: #fbbf24;
    }

    .alert-soft.amber a {
      color: #fbbf24;
    }

    .btn-outline {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 20px;
      border-radius: 10px;
      border: 1px solid rgba(255, 255, 255, 0.06);
      color: #94a3b8;
      text-decoration: none;
      transition: all 0.2s;
      background: transparent;
      font-size: 0.85rem;
      font-weight: 500;
      cursor: pointer;
    }

    .btn-outline:hover {
      background: rgba(255, 255, 255, 0.04);
      border-color: rgba(255, 255, 255, 0.12);
      color: #e8edf5;
    }

    .btn-solid {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 20px;
      border-radius: 10px;
      background: linear-gradient(135deg, #3b82f6, #6366f1);
      color: #fff;
      text-decoration: none;
      transition: all 0.2s;
      border: none;
      font-size: 0.85rem;
      font-weight: 500;
      cursor: pointer;
    }

    .btn-solid:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(59, 130, 246, 0.3);
    }

    .w-100 {
      width: 100%;
    }

    .justify-content-center {
      justify-content: center;
    }

    .d-grid {
      display: grid;
    }

    .gap-2 {
      gap: 8px;
    }

    .mt-3 {
      margin-top: 16px;
    }

    .mb-0 {
      margin-bottom: 0;
    }

    .mb-3 {
      margin-bottom: 16px;
    }

    .me-1 {
      margin-right: 4px;
    }

    .form-label {
      font-size: 0.8rem;
      font-weight: 500;
      opacity: 0.6;
      margin-bottom: 4px;
    }

    .form-control,
    .form-select {
      background: rgba(255, 255, 255, 0.04);
      border: 1px solid rgba(255, 255, 255, 0.06);
      color: #e8edf5;
      border-radius: 10px;
      padding: 10px 14px;
      font-size: 0.85rem;
      transition: all 0.2s;
    }

    .form-control:focus,
    .form-select:focus {
      background: rgba(255, 255, 255, 0.06);
      border-color: rgba(96, 165, 250, 0.3);
      box-shadow: none;
      color: #e8edf5;
    }

    .form-control[readonly] {
      opacity: 0.5;
      cursor: not-allowed;
    }

    .form-control::placeholder {
      color: #4a5a7a;
    }

    .form-select option {
      background: #141b2b;
      color: #e8edf5;
    }

    .form-text {
      font-size: 0.75rem;
      opacity: 0.4;
      margin-top: 4px;
    }

    .info-grid {
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .info-row {
      display: flex;
      justify-content: space-between;
      padding: 6px 0;
      border-bottom: 1px solid rgba(255, 255, 255, 0.04);
      font-size: 0.85rem;
    }

    .info-row:last-child {
      border-bottom: none;
    }

    .info-row dt {
      font-weight: 500;
      opacity: 0.6;
      margin: 0;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .info-row dt i {
      opacity: 0.4;
      width: 18px;
    }

    .info-row dd {
      font-weight: 500;
      margin: 0;
      text-align: right;
    }

    .page-foot {
      padding: 16px 28px;
      border-top: 1px solid rgba(255, 255, 255, 0.04);
      display: flex;
      justify-content: space-between;
      flex-wrap: wrap;
      gap: 8px;
      font-size: 0.75rem;
      opacity: 0.25;
      margin-top: auto;
    }

    .page-foot a {
      color: #94a3b8;
      text-decoration: none;
      transition: color 0.2s;
    }

    .page-foot a:hover {
      color: #e8edf5;
    }

    .page-foot .d-flex {
      display: flex;
      gap: 16px;
    }

    .nav-toggle {
      display: none;
    }

    .nav-backdrop {
      display: none;
    }

    @media (max-width: 992px) {
      .app-sidebar {
        position: fixed;
        left: -280px;
        top: 0;
        bottom: 0;
        width: 280px;
        z-index: 1000;
        transition: left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        flex-direction: column;
        border-right: none;
        box-shadow: 0 0 40px rgba(0, 0, 0, 0.5);
      }

      .nav-toggle:checked~.app-sidebar {
        left: 0;
      }

      .nav-toggle:checked~.nav-backdrop {
        display: block;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.6);
        z-index: 999;
        backdrop-filter: blur(4px);
      }

      .sidebar-close {
        display: inline-flex;
      }

      .nav-btn {
        display: inline-flex;
      }

      .app-topbar {
        padding: 12px 16px;
      }

      .app-content {
        padding: 16px;
      }

      .welcome {
        padding: 20px;
      }

      .welcome h2 {
        font-size: 1.3rem;
      }

      .topbar-search {
        min-width: 120px;
      }

      .profile-chip .who {
        display: none;
      }

      .card .card-head {
        padding: 14px 16px;
      }

      .card .card-body {
        padding: 14px 16px;
      }

      .page-foot {
        flex-direction: column;
        text-align: center;
        padding: 12px 16px;
      }

      .topbar-actions .icon-drop:not(:last-child) {
        display: none;
      }

      .topbar-title h1 {
        font-size: 1.1rem;
      }

      .col-xl-4 {
        margin-bottom: 16px;
      }

      .col-lg-4 {
        margin-bottom: 16px;
      }

      .info-row {
        font-size: 0.8rem;
      }

      .info-row dt i {
        width: 16px;
      }

      .profile-image-container {
        width: 90px;
        height: 90px;
      }
    }

    @media (max-width: 576px) {
      .welcome .row .col-md-auto {
        text-align: center !important;
      }

      .welcome .row .col-md {
        text-align: center;
      }

      .welcome .row .col-md-auto .profile-image-container {
        width: 80px;
        height: 80px;
      }

      .fact {
        padding: 8px 10px;
      }

      .fact strong {
        font-size: 0.95rem;
      }

      .quick-chips .chip {
        font-size: 0.7rem;
        padding: 4px 10px;
      }

      .card .card-head h3 {
        font-size: 0.9rem;
      }

      .card .card-head h3 .sub {
        font-size: 0.6rem;
      }

      .pill {
        font-size: 0.6rem;
        padding: 1px 8px;
      }

      .info-row {
        font-size: 0.75rem;
        flex-wrap: wrap;
      }

      .info-row dd {
        text-align: left;
        width: 100%;
        padding-left: 26px;
      }

      .form-control,
      .form-select {
        font-size: 0.8rem;
        padding: 8px 12px;
      }

      .btn-solid,
      .btn-outline {
        font-size: 0.75rem;
        padding: 6px 14px;
      }

      .notice-row h4 {
        font-size: 0.8rem;
      }

      .notice-row p {
        font-size: 0.7rem;
      }

      .avatar-lg {
        width: 36px;
        height: 36px;
        font-size: 0.8rem;
      }
    }

    @media print {

      .app-sidebar,
      .app-topbar,
      .page-foot {
        display: none !important;
      }

      .app-main {
        background: white !important;
        color: black !important;
        padding: 0 !important;
        margin: 0 !important;
      }

      .app-content {
        padding: 20px !important;
        max-width: 100% !important;
      }

      .card {
        border: 1px solid #ddd !important;
        background: white !important;
        box-shadow: none !important;
        margin-bottom: 16px !important;
        page-break-inside: avoid !important;
      }

      .card .card-head {
        border-bottom: 2px solid #ddd !important;
        padding: 12px 16px !important;
      }

      .card .card-head h3 {
        color: #333 !important;
      }

      .card .card-head h3 .sub {
        color: #666 !important;
        opacity: 1 !important;
      }

      .card .card-body {
        padding: 16px !important;
      }

      .welcome {
        border: 1px solid #ddd !important;
        background: #f9f9f9 !important;
        padding: 20px !important;
        border-radius: 8px !important;
      }

      .welcome h2 {
        color: #333 !important;
      }

      .welcome p {
        color: #555 !important;
        opacity: 1 !important;
      }

      .welcome .eyebrow {
        color: #666 !important;
        opacity: 1 !important;
      }

      .fact {
        border: 1px solid #ddd !important;
        background: #f9f9f9 !important;
      }

      .fact strong {
        color: #333 !important;
      }

      .fact small {
        color: #666 !important;
        opacity: 1 !important;
      }

      .fact span {
        color: #666 !important;
        opacity: 1 !important;
      }

      .pill {
        background: #eee !important;
        color: #333 !important;
        border: 1px solid #ddd !important;
      }

      .meta-list li {
        border-bottom: 1px solid #eee !important;
        color: #333 !important;
      }

      .meta-list li span {
        color: #666 !important;
      }

      .meta-list li i {
        color: #666 !important;
      }

      .info-row {
        border-bottom: 1px solid #eee !important;
      }

      .info-row dt {
        color: #333 !important;
        opacity: 1 !important;
      }

      .info-row dd {
        color: #333 !important;
      }

      .notice-row {
        border-bottom: 1px solid #eee !important;
      }

      .notice-row h4 {
        color: #333 !important;
      }

      .notice-row p {
        color: #666 !important;
        opacity: 1 !important;
      }

      .notice-date b {
        color: #2563eb !important;
      }

      .notice-date small {
        color: #666 !important;
        opacity: 1 !important;
      }

      .alert-soft {
        border-left-color: #666 !important;
        background: #f5f5f5 !important;
        color: #333 !important;
      }

      .alert-soft i {
        color: #666 !important;
      }

      .btn-solid,
      .btn-outline {
        border: 1px solid #ddd !important;
        color: #333 !important;
        background: #f9f9f9 !important;
      }

      .btn-solid i,
      .btn-outline i {
        color: #333 !important;
      }

      .profile-image-container {
        border: 1px solid #ddd !important;
      }
    }
  </style>
</head>

<body>

  <input type="checkbox" id="navToggle" class="nav-toggle" />
  <label for="navToggle" class="nav-backdrop" aria-hidden="true"></label>

  <!-- ===== SIDEBAR ===== -->
  <aside class="app-sidebar">
    <div class="sidebar-head">
      <a href="index.php" class="sidebar-brand">
        <img src="assets/images/logo.svg" alt="Crescent Public School logo" />
        <span class="brand-text"><strong>Crescent Public School</strong><small>Student Portal</small></span>
      </a>
      <label for="navToggle" class="sidebar-close" aria-label="Close navigation"><i class="bi bi-x-lg"></i></label>
    </div>

    <div class="student-card">
      <span class="avatar avatar-lg"><?php echo $studentInitials; ?></span>
      <div class="student-card-text">
        <strong><?php echo $studentName; ?></strong>
        <small><?php echo $studentClass . ' · ' . $studentUID; ?></small>
      </div>
      <span class="verify" title="Verified student"><i class="bi bi-patch-check-fill"></i></span>
    </div>

    <nav class="sidebar-nav">
      <p class="nav-group">Overview</p>
      <a class="nav-item" href="index.php"><i class="bi bi-columns-gap"></i><span>Dashboard</span></a>

      <p class="nav-group">Academics</p>
      <a class="nav-item" href="subjects.php"><i class="bi bi-journal-bookmark"></i><span>My Subjects</span><em
          class="nav-tag"><?php echo $subjectCount['count'] ?? 0; ?></em></a>
      <a class="nav-item" href="timetable.php"><i class="bi bi-calendar-week"></i><span>My Timetable</span></a>
      <a class="nav-item" href="attendance.php"><i class="bi bi-check2-square"></i><span>My Attendance</span></a>
      <a class="nav-item" href="assignments.php"><i class="bi bi-journal-text"></i><span>Assignments</span></a>
      <a class="nav-item" href="exams.php"><i class="bi bi-pencil-square"></i><span>Exams</span></a>
      <a class="nav-item" href="results.php"><i class="bi bi-graph-up-arrow"></i><span>Results</span></a>

      <p class="nav-group">Finance</p>
      <a class="nav-item" href="fees.php"><i class="bi bi-wallet2"></i><span>Fees</span></a>

      <p class="nav-group">School Life</p>
      <a class="nav-item" href="notices.php"><i class="bi bi-megaphone"></i><span>Notices</span></a>
      <a class="nav-item" href="events.php"><i class="bi bi-calendar2-heart"></i><span>Events</span></a>
      <a class="nav-item" href="messages.php"><i class="bi bi-envelope"></i><span>Messages</span></a>

      <p class="nav-group">Account</p>
      <a class="nav-item active" href="profile.php"><i class="bi bi-person-badge"></i><span>My Profile</span></a>
      <a class="nav-item" href="settings.php"><i class="bi bi-gear"></i><span>Settings</span></a>
    </nav>

    <div class="sidebar-foot">
      <a href="#" class="logout-btn"><i class="bi bi-box-arrow-right"></i><span>Logout</span></a>
      <p class="copy">Portal v2.6 &middot; Session 2026&ndash;27</p>
    </div>
  </aside>

  <!-- ===== MAIN CONTENT ===== -->
  <div class="app-main">

    <!-- ===== TOPBAR ===== -->
    <header class="app-topbar">
      <label for="navToggle" class="nav-btn" aria-label="Open navigation"><i class="bi bi-list"></i></label>
      <div class="topbar-title">
        <h1>My Profile</h1>
        <div class="crumbs"><a href="index.php">Home</a><span>/</span>Account<span>/</span>My Profile</div>
      </div>
      <div class="topbar-search">
        <i class="bi bi-search"></i>
        <input type="search" placeholder="Search the portal…" aria-label="Search" />
      </div>
      <div class="topbar-actions">
        <div class="icon-drop">
          <a href="notices.php" class="icon-btn" aria-label="Notifications">
            <i class="bi bi-bell"></i>
            <span class="ping">5</span>
          </a>
          <div class="drop-panel">
            <div class="drop-head"><strong>Notifications</strong><a href="notices.php">View all</a></div>
            <a href="exams.php" class="drop-row">
              <i class="dot-ico p-info"><i class="bi bi-pencil-square"></i></i>
              <span>
                <p>Mid-Term timetable published</p><small>Examination Cell · 2 hours ago</small>
              </span>
            </a>
            <a href="fees.php" class="drop-row">
              <i class="dot-ico p-danger"><i class="bi bi-wallet2"></i></i>
              <span>
                <p>Fee instalment due on Sep 10</p><small>Accounts Office · 3 days ago</small>
              </span>
            </a>
          </div>
        </div>
        <div class="icon-drop">
          <a href="messages.php" class="icon-btn" aria-label="Messages">
            <i class="bi bi-envelope"></i>
            <span class="ping">3</span>
          </a>
          <div class="drop-panel">
            <div class="drop-head"><strong>Messages</strong><a href="messages.php">Open inbox</a></div>
            <a href="messages.php" class="drop-row">
              <span class="avatar info">SR</span>
              <span>
                <p>Ms. Sara Khan · Lab report feedback</p><small>Today, 09:14 AM</small>
              </span>
            </a>
          </div>
        </div>
        <div class="icon-drop">
          <a href="profile.php" class="profile-chip">
            <span class="avatar"><?php echo $studentInitials; ?></span>
            <span class="who"><b><?php echo $firstName; ?></b><small><?php echo $studentClass; ?></small></span>
            <i class="bi bi-chevron-down"></i>
          </a>
          <div class="drop-panel">
            <div class="drop-head"><strong><?php echo $studentName; ?></strong><span class="pill p-ok">Active</span>
            </div>
            <a href="profile.php" class="drop-row"><i class="dot-ico p-teal"><i
                  class="bi bi-person-badge"></i></i><span>
                <p>My Profile</p><small><?php echo $studentUID; ?></small>
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

    <!-- ===== CONTENT ===== -->
    <main class="app-content">

      <!-- ===== SUCCESS/ERROR MESSAGE ===== -->
      <?php if ($update_message): ?>
        <div class="alert-message <?php echo $update_type; ?>">
          <i class="bi <?php echo $update_type == 'success' ? 'bi-check-circle' : 'bi-exclamation-circle'; ?>"></i>
          <?php echo htmlspecialchars($update_message); ?>
        </div>
      <?php endif; ?>

      <!-- ===== PROFILE HEADER ===== -->
      <section class="welcome rise">
        <div class="row g-4 align-items-center">
          <div class="col-md-auto text-md-start text-center">
            <div class="profile-image-container">
              <img src="<?php echo $profileImage; ?>" alt="<?php echo $studentName; ?>" />
            </div>
          </div>
          <div class="col-md">
            <span class="eyebrow">Student Record &middot; Verified</span>
            <h2><?php echo $studentName; ?></h2>
            <p>
              <?php echo $studentClass; ?> &middot; Student ID <?php echo $studentUID; ?><br />
              Admitted <?php echo date('d F Y', strtotime($admissionDate)); ?> &middot; Session
              <?php echo date('Y'); ?>&ndash;<?php echo date('Y') + 1; ?>
            </p>
            <div class="quick-chips">
              <span class="chip"><i class="bi bi-patch-check"></i> Active Enrolment</span>
              <span class="chip"><i class="bi bi-award"></i> Merit Scholarship 10%</span>
              <span class="chip"><i class="bi bi-house-door"></i> House: Ravi</span>
            </div>
          </div>
          <div class="col-md-auto">
            <div class="row g-2">
              <div class="col-6 col-md-12">
                <div class="fact">
                  <small>Attendance</small>
                  <strong><?php echo $attendancePercentage; ?>%</strong>
                  <span>Term 1</span>
                </div>
              </div>
              <div class="col-6 col-md-12">
                <div class="fact">
                  <small>GPA</small>
                  <strong><?php echo number_format($gpa, 1); ?> / 4.0</strong>
                  <span>Grade A</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== PROFILE DETAILS ===== -->
      <section class="row g-3 mt-1">

        <!-- PERSONAL DETAILS -->
        <div class="col-xl-4 rise">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Personal Details <span class="sub">As per school records</span></h3>
            </div>
            <div class="card-body">
              <dl class="info-grid mb-0">
                <div class="info-row">
                  <dt><i class="bi bi-person"></i> Student Name</dt>
                  <dd><?php echo $studentName; ?></dd>
                </div>
                <div class="info-row">
                  <dt><i class="bi bi-hash"></i> Student ID</dt>
                  <dd><?php echo $studentUID; ?></dd>
                </div>
                <div class="info-row">
                  <dt><i class="bi bi-mortarboard"></i> Class</dt>
                  <dd><?php echo $classNumber; ?></dd>
                </div>
                <div class="info-row">
                  <dt><i class="bi bi-diagram-3"></i> Section</dt>
                  <dd><?php echo $section; ?></dd>
                </div>
                <div class="info-row">
                  <dt><i class="bi bi-cake2"></i> Date of Birth</dt>
                  <dd><?php echo date('d F Y', strtotime($studentDob)); ?></dd>
                </div>
                <div class="info-row">
                  <dt><i class="bi bi-gender-ambiguous"></i> Gender</dt>
                  <dd><?php echo $studentGender; ?></dd>
                </div>
                <div class="info-row">
                  <dt><i class="bi bi-envelope"></i> Email</dt>
                  <dd><?php echo $studentEmail; ?></dd>
                </div>
                <div class="info-row">
                  <dt><i class="bi bi-geo-alt"></i> Address</dt>
                  <dd><?php echo $studentAddress; ?></dd>
                </div>
              </dl>
            </div>
          </div>
        </div>

        <!-- GUARDIAN -->
        <div class="col-xl-4 rise rise-2">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Guardian <span class="sub">Emergency contact</span></h3>
            </div>
            <div class="card-body">
              <div class="d-flex align-items-center gap-3 mb-3">
                <span class="avatar avatar-lg ok"><?php echo getInitials($parentName); ?></span>
                <div>
                  <h3 style="font-size:.98rem"><?php echo $parentName; ?></h3>
                  <p class="mb-0" style="font-size:.76rem;color:#5a6a8a;">Father &middot; Primary guardian</p>
                </div>
              </div>
              <dl class="info-grid mb-0">
                <div class="info-row">
                  <dt><i class="bi bi-person-check"></i> Parent Name</dt>
                  <dd><?php echo $parentName; ?></dd>
                </div>
                <div class="info-row">
                  <dt><i class="bi bi-telephone"></i> Parent Phone</dt>
                  <dd><?php echo $parentPhone; ?></dd>
                </div>
                <div class="info-row">
                  <dt><i class="bi bi-briefcase"></i> Occupation</dt>
                  <dd>Civil Engineer</dd>
                </div>
                <div class="info-row">
                  <dt><i class="bi bi-envelope-at"></i> Parent Email</dt>
                  <dd><?php echo $parentEmail; ?></dd>
                </div>
                <div class="info-row">
                  <dt><i class="bi bi-calendar-check"></i> Admission Date</dt>
                  <dd><?php echo date('d F Y', strtotime($admissionDate)); ?></dd>
                </div>
              </dl>
            </div>
          </div>
        </div>

        <!-- ACADEMIC SNAPSHOT -->
        <div class="col-xl-4 rise rise-3">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Academic Snapshot <span class="sub">Session
                  <?php echo date('Y'); ?>&ndash;<?php echo date('Y') + 1; ?></span></h3>
            </div>
            <div class="card-body">
              <ul class="meta-list">
                <li><i class="bi bi-journal-bookmark"></i> Subjects
                  <span><?php echo $subjectCount['count'] ?? 0; ?></span></li>
                <li><i class="bi bi-check2-circle"></i> Attendance <span><?php echo $attendancePercentage; ?>%</span>
                </li>
                <li><i class="bi bi-graph-up-arrow"></i> GPA <span><?php echo number_format($gpa, 1); ?></span></li>
                <li><i class="bi bi-trophy"></i> Class Position <span>3 / 32</span></li>
                <li><i class="bi bi-star"></i> House <span>Ravi — 180 pts</span></li>
                <li><i class="bi bi-people"></i> Class Teacher <span><?php echo $teacherName; ?></span></li>
              </ul>
              <div class="divider-soft"></div>
              <div class="alert-soft teal"><i class="bi bi-quote"></i><span><b>Remark:</b> &ldquo;A conscientious
                  student with strong analytical ability. Should target Urdu writing practice.&rdquo;</span></div>
              <div class="d-grid gap-2 mt-3">
                <a href="results.php" class="btn-solid justify-content-center"><i class="bi bi-file-earmark-text"></i>
                  View Report Card</a>
                <a href="settings.php" class="btn-outline justify-content-center"><i class="bi bi-gear"></i> Account
                  Settings</a>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- ===== EDIT FORM ===== -->
      <section class="mt-4 rise">
        <div class="card lift">
          <div class="card-head">
            <h3>Edit Profile <span class="sub">Update your personal information</span></h3>
            <span class="pill p-teal"><i class="bi bi-pencil-square"></i> Editable fields</span>
          </div>
          <div class="card-body">
            <form method="POST" action="">
              <h4
                style="font-size:.78rem;letter-spacing:.14em;text-transform:uppercase;color:#60a5fa;margin-bottom:1rem">
                Personal Information</h4>
              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label" for="first_name">First Name</label>
                  <input type="text" class="form-control" id="first_name" name="first_name"
                    value="<?php echo $firstName; ?>" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="last_name">Last Name</label>
                  <input type="text" class="form-control" id="last_name" name="last_name"
                    value="<?php echo $lastName; ?>" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="studentId">Student ID</label>
                  <input type="text" class="form-control" id="studentId" value="<?php echo $studentUID; ?>" readonly />
                  <div class="form-text">Student ID cannot be changed.</div>
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="email">Email Address</label>
                  <input type="email" class="form-control" id="email" name="email" value="<?php echo $studentEmail; ?>"
                    required />
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="date_of_birth">Date of Birth</label>
                  <input type="date" class="form-control" id="date_of_birth" name="date_of_birth"
                    value="<?php echo $studentDob; ?>" required />
                </div>
                <div class="col-md-6">
                  <label class="form-label" for="gender">Gender</label>
                  <select class="form-select" id="gender" name="gender">
                    <option value="Male" <?php echo $studentGender == 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $studentGender == 'Female' ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo $studentGender == 'Other' ? 'selected' : ''; ?>>Other</option>
                  </select>
                </div>

                <div class="col-12">
                  <div class="divider-soft"></div>
                </div>

                <div class="col-12">
                  <label class="form-label" for="address">Home Address</label>
                  <textarea class="form-control" id="address" name="address"
                    rows="2"><?php echo $studentAddress; ?></textarea>
                </div>

                <div class="col-12">
                  <div class="d-flex flex-wrap gap-2 justify-content-between align-items-center">
                    <span style="font-size:.76rem;color:#5a6a8a;"><i class="bi bi-shield-lock me-1"></i>Changes to name,
                      date of birth or admission records require verification by the school office.</span>
                    <span class="d-flex gap-2">
                      <a href="profile.php" class="btn-outline"><i class="bi bi-arrow-counterclockwise"></i> Reset</a>
                      <button type="submit" name="update_profile" class="btn-solid"><i class="bi bi-check2"></i> Save
                        Changes</button>
                    </span>
                  </div>
                </div>
              </div>
            </form>
          </div>
        </div>
      </section>

      <!-- ===== DOCUMENTS + ACHIEVEMENTS + ACTIVITY ===== -->
      <section class="row g-3 mt-1 rise">
        <div class="col-lg-4">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Documents <span class="sub">Uploaded records</span></h3>
            </div>
            <div class="card-body tight">
              <?php foreach ($documents as $doc): ?>
                <div
                  class="notice-row <?php echo $doc['status_class'] == 'p-ok' ? 'teal' : ($doc['status_class'] == 'p-warn' ? 'amber' : 'info'); ?>">
                  <div class="notice-date">
                    <b><i class="bi <?php echo $doc['icon']; ?>"></i></b>
                    <small><?php echo $doc['status'] == 'Verified' ? 'OK' : ($doc['status'] == 'Approved' ? 'OK' : 'Pending'); ?></small>
                  </div>
                  <div>
                    <h4><?php echo $doc['title']; ?></h4>
                    <p>
                      <?php echo $doc['status'] == 'Verified' ? 'Verified' : ($doc['status'] == 'Approved' ? 'Approved' : 'Under Review'); ?>
                    </p>
                    <span class="pill <?php echo $doc['status_class']; ?>"><?php echo $doc['status']; ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Achievements <span class="sub">Session 2025&ndash;27</span></h3>
            </div>
            <div class="card-body">
              <ul class="meta-list">
                <?php foreach ($achievements as $achievement): ?>
                  <li><i class="bi <?php echo $achievement['icon']; ?>"></i> <?php echo $achievement['title']; ?>
                    <span><?php echo $achievement['detail']; ?></span></li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
        </div>
        <div class="col-lg-4">
          <div class="card lift h-100">
            <div class="card-head">
              <h3>Account Activity <span class="sub">Recent portal logins</span></h3>
            </div>
            <div class="card-body">
              <ul class="meta-list">
                <?php foreach ($loginActivity as $activity): ?>
                  <li><i class="bi <?php echo $activity['icon']; ?>"></i> <?php echo $activity['device']; ?>
                    <span><?php echo $activity['time']; ?></span></li>
                <?php endforeach; ?>
              </ul>
              <div class="divider-soft"></div>
              <div class="alert-soft amber"><i class="bi bi-shield-exclamation"></i><span>Recognise all these devices?
                  If not, change your password from <a href="settings.php"
                    style="color:inherit;text-decoration:underline">Settings</a>.</span></div>
            </div>
          </div>
        </div>
      </section>

    </main>

    <!-- ===== FOOTER ===== -->
    <footer class="page-foot">
      <span>&copy; 2026 Crescent Public School &middot; Student Portal</span>
      <span class="d-flex gap-3">
        <a href="notices.php">Help Centre</a>
        <a href="messages.php">Contact Office</a>
        <a href="settings.php">Privacy</a>
      </span>
    </footer>
  </div>

</body>

</html>