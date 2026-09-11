<?php
// index.php - Teacher Dashboard
require_once '../database/connect.php';

session_start();

// First, let's get the actual teacher ID from the teachers table
// Try to get the first teacher if no session exists
$teacher_stmt = $conn->prepare("
    SELECT id, full_name, user_id, teacher_id
    FROM teachers 
    LIMIT 1
");
$teacher_stmt->execute();
$teacher_data = $teacher_stmt->fetch();

if ($teacher_data) {
  $teacher_db_id = $teacher_data['id'];
  $teacher_name = $teacher_data['full_name'] ?? 'Teacher';
  $teacher_user_id = $teacher_data['user_id'] ?? 0;
} else {
  // If no teachers exist, use default
  $teacher_db_id = 10;
  $teacher_name = 'Mr. Ahmed';
  $teacher_user_id = 0;
}

// If session has a teacher ID, use it instead
if (isset($_SESSION['teacher_id']) && $_SESSION['teacher_id'] > 0) {
  $teacher_db_id = $_SESSION['teacher_id'];
  // Get teacher name from session or database
  $teacher_stmt = $conn->prepare("SELECT full_name FROM teachers WHERE id = :id");
  $teacher_stmt->bindValue(':id', $teacher_db_id);
  $teacher_stmt->execute();
  $teacher = $teacher_stmt->fetch();
  if ($teacher) {
    $teacher_name = $teacher['full_name'];
  }
}

$teacher_initials = implode('', array_map(function ($word) {
  return strtoupper(substr($word, 0, 1));
}, explode(' ', $teacher_name)));

// Debug: Log the teacher ID being used
// echo "<!-- Teacher ID: " . $teacher_db_id . " -->";

// Get statistics
$stats = [];

// Total Students - Get all students in classes taught by this teacher
$student_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT s.id) as total
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE c.teacher_id = :teacher_id AND s.status = 'Active'
");
$student_stmt->bindValue(':teacher_id', $teacher_db_id);
$student_stmt->execute();
$result = $student_stmt->fetch();
$stats['students'] = $result['total'] ?? 0;

// Total Classes
$class_stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM classes
    WHERE teacher_id = :teacher_id AND status = 'active'
");
$class_stmt->bindValue(':teacher_id', $teacher_db_id);
$class_stmt->execute();
$result = $class_stmt->fetch();
$stats['classes'] = $result['total'] ?? 0;

// Total Subjects
$subject_stmt = $conn->prepare("
    SELECT COUNT(DISTINCT s.id) as total
    FROM subjects s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE c.teacher_id = :teacher_id OR s.teacher_id = :teacher_id
");
$subject_stmt->bindValue(':teacher_id', $teacher_db_id);
$subject_stmt->execute();
$result = $subject_stmt->fetch();
$stats['subjects'] = $result['total'] ?? 0;

// Today's Attendance Percentage
$attendance_stmt = $conn->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN a.status IN ('Present', 'Late') THEN 1 ELSE 0 END) as present
    FROM attendance a
    LEFT JOIN students s ON a.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE c.teacher_id = :teacher_id AND a.attendance_date = CURDATE()
");
$attendance_stmt->bindValue(':teacher_id', $teacher_db_id);
$attendance_stmt->execute();
$attendance_data = $attendance_stmt->fetch();
$stats['attendance'] = ($attendance_data['total'] > 0) ? round(($attendance_data['present'] / $attendance_data['total']) * 100) : 0;

// Pending Assignments
$assignment_stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM assignments a
    LEFT JOIN classes c ON a.class_id = c.id
    WHERE c.teacher_id = :teacher_id AND a.status = 'active' AND a.due_date >= CURDATE()
");
$assignment_stmt->bindValue(':teacher_id', $teacher_db_id);
$assignment_stmt->execute();
$result = $assignment_stmt->fetch();
$stats['assignments'] = $result['total'] ?? 0;

// Upcoming Exams
$exam_stmt = $conn->prepare("
    SELECT COUNT(*) as total
    FROM exams e
    LEFT JOIN subjects s ON e.subject_id = s.id
    LEFT JOIN classes c ON e.class_id = c.id
    WHERE (s.teacher_id = :teacher_id OR c.teacher_id = :teacher_id) 
        AND e.status = 'Scheduled' 
        AND e.exam_date >= CURDATE()
");
$exam_stmt->bindValue(':teacher_id', $teacher_db_id);
$exam_stmt->execute();
$result = $exam_stmt->fetch();
$stats['exams'] = $result['total'] ?? 0;

// Get Today's Timetable
$today = date('l');
$timetable_stmt = $conn->prepare("
    SELECT 
        t.id,
        t.time_slot,
        t.room,
        s.subject_title,
        c.name as class_name,
        c.grade as class_grade,
        CASE 
            WHEN t.time_slot < CURTIME() THEN 'Completed'
            WHEN t.time_slot <= ADDTIME(CURTIME(), '01:00:00') THEN 'Upcoming'
            ELSE 'Scheduled'
        END as status
    FROM timetable t
    LEFT JOIN subjects s ON t.subject_id = s.id
    LEFT JOIN classes c ON t.class_id = c.id
    WHERE t.teacher_id = :teacher_id AND t.day_of_week = :day
    ORDER BY t.time_slot ASC
    LIMIT 5
");
$timetable_stmt->bindValue(':teacher_id', $teacher_db_id);
$timetable_stmt->bindValue(':day', $today);
$timetable_stmt->execute();
$today_timetable = $timetable_stmt->fetchAll();

// Get My Classes with student counts
$classes_stmt = $conn->prepare("
    SELECT 
        c.id,
        c.name,
        c.grade,
        c.room_no,
        (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.status = 'Active') as student_count
    FROM classes c
    WHERE c.teacher_id = :teacher_id AND c.status = 'active'
    ORDER BY c.name
    LIMIT 3
");
$classes_stmt->bindValue(':teacher_id', $teacher_db_id);
$classes_stmt->execute();
$my_classes = $classes_stmt->fetchAll();

// Get Recent Attendance
$recent_attendance_stmt = $conn->prepare("
    SELECT 
        CONCAT(s.first_name, ' ', s.last_name) as student_name,
        c.name as class_name,
        a.attendance_date,
        a.status
    FROM attendance a
    LEFT JOIN students s ON a.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE c.teacher_id = :teacher_id
    ORDER BY a.attendance_date DESC, a.created_at DESC
    LIMIT 5
");
$recent_attendance_stmt->bindValue(':teacher_id', $teacher_db_id);
$recent_attendance_stmt->execute();
$recent_attendance = $recent_attendance_stmt->fetchAll();

// Get Pending Assignments
$pending_assignments_stmt = $conn->prepare("
    SELECT 
        a.title,
        s.subject_title,
        c.name as class_name,
        a.due_date
    FROM assignments a
    LEFT JOIN subjects s ON a.subject_id = s.id
    LEFT JOIN classes c ON a.class_id = c.id
    WHERE c.teacher_id = :teacher_id AND a.status = 'active' AND a.due_date >= CURDATE()
    ORDER BY a.due_date ASC
    LIMIT 5
");
$pending_assignments_stmt->bindValue(':teacher_id', $teacher_db_id);
$pending_assignments_stmt->execute();
$pending_assignments = $pending_assignments_stmt->fetchAll();

// Get Upcoming Exams
$upcoming_exams_stmt = $conn->prepare("
    SELECT 
        e.exam_title,
        c.name as class_name,
        s.subject_title,
        e.exam_date,
        e.start_time,
        e.room,
        e.status
    FROM exams e
    LEFT JOIN subjects s ON e.subject_id = s.id
    LEFT JOIN classes c ON e.class_id = c.id
    WHERE (s.teacher_id = :teacher_id OR c.teacher_id = :teacher_id) 
        AND e.status = 'Scheduled' 
        AND e.exam_date >= CURDATE()
    ORDER BY e.exam_date ASC
    LIMIT 5
");
$upcoming_exams_stmt->bindValue(':teacher_id', $teacher_db_id);
$upcoming_exams_stmt->execute();
$upcoming_exams = $upcoming_exams_stmt->fetchAll();

// Get Latest Notices
$notices_stmt = $conn->prepare("
    SELECT id, title, category, details, posted_by, created_at
    FROM notices
    ORDER BY created_at DESC
    LIMIT 4
");
$notices_stmt->execute();
$latest_notices = $notices_stmt->fetchAll();

// Function to get status badge class
function getStatusBadgeClass($status)
{
  switch (strtolower($status)) {
    case 'completed':
      return 'bg-success';
    case 'upcoming':
      return 'bg-warning text-dark';
    case 'scheduled':
      return 'bg-secondary';
    case 'pending':
      return 'bg-warning text-dark';
    case 'overdue':
      return 'bg-danger';
    case 'active':
      return 'bg-info text-dark';
    case 'present':
      return 'bg-success';
    case 'absent':
      return 'bg-danger';
    case 'late':
      return 'bg-warning text-dark';
    default:
      return 'bg-secondary';
  }
}

// Function to get category badge class
function getCategoryBadgeClass($category)
{
  switch (strtolower($category)) {
    case 'academic':
      return 'bg-primary-subtle text-primary';
    case 'administrative':
      return 'bg-info-subtle text-info-emphasis';
    case 'sports':
      return 'bg-success-subtle text-success-emphasis';
    case 'holiday':
      return 'bg-danger-subtle text-danger-emphasis';
    case 'event':
      return 'bg-warning-subtle text-warning-emphasis';
    case 'staff':
      return 'bg-secondary-subtle text-secondary';
    default:
      return 'bg-light text-dark';
  }
}

// Get unread message count
$unread_stmt = $conn->prepare("
    SELECT COUNT(*) as count
    FROM messages
    WHERE recipient_type = 'Teacher' AND recipient_id = :teacher_id AND is_read = 0
");
$unread_stmt->bindValue(':teacher_id', $teacher_db_id);
$unread_stmt->execute();
$unread_count = $unread_stmt->fetch()['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Teacher Dashboard | Bright Future School</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    /* Sidebar Styles */
    .td-wrapper {
      display: flex;
      min-height: 100vh;
    }

    .td-sidebar {
      width: 260px;
      background: #2c3e50;
      color: #ecf0f1;
      position: fixed;
      height: 100vh;
      overflow-y: auto;
      z-index: 1000;
      transition: transform 0.3s ease;
    }

    .td-main {
      flex: 1;
      margin-left: 260px;
      background: #f4f6f9;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .td-brand {
      padding: 20px;
      font-size: 1.3rem;
      font-weight: bold;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .td-brand i {
      font-size: 1.8rem;
      color: #3498db;
    }

    .td-brand small {
      display: block;
      font-size: 0.65rem;
      font-weight: normal;
      opacity: 0.7;
    }

    .td-teacher-box {
      padding: 20px;
      display: flex;
      align-items: center;
      gap: 12px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .td-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #3498db;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: white;
      font-size: 14px;
      flex-shrink: 0;
    }

    .td-teacher-box h6 {
      margin: 0;
      font-size: 0.9rem;
      color: white;
    }

    .td-teacher-box p {
      margin: 0;
      font-size: 0.75rem;
      opacity: 0.7;
    }

    .td-nav {
      padding: 10px 0;
    }

    .td-nav-title {
      padding: 10px 20px;
      font-size: 0.7rem;
      text-transform: uppercase;
      opacity: 0.5;
      letter-spacing: 1px;
    }

    .td-nav a {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 20px;
      color: rgba(255, 255, 255, 0.7);
      text-decoration: none;
      transition: all 0.3s;
      border-left: 3px solid transparent;
    }

    .td-nav a:hover {
      background: rgba(255, 255, 255, 0.05);
      color: white;
    }

    .td-nav a.active {
      background: rgba(52, 152, 219, 0.2);
      color: white;
      border-left-color: #3498db;
    }

    .td-nav a.logout {
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      margin-top: 10px;
      color: #e74c3c;
    }

    .td-nav a.logout:hover {
      background: rgba(231, 76, 60, 0.1);
    }

    .td-nav a i {
      width: 20px;
    }

    /* Navbar Styles */
    .td-navbar {
      background: white;
      padding: 15px 25px;
      display: flex;
      align-items: center;
      gap: 15px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
      position: sticky;
      top: 0;
      z-index: 100;
      flex-wrap: wrap;
    }

    .td-burger {
      font-size: 1.5rem;
      cursor: pointer;
      display: none;
    }

    .td-page-title {
      font-size: 1.2rem;
      margin: 0;
    }

    .td-page-title small {
      font-size: 0.75rem;
      color: #6c757d;
      font-weight: normal;
    }

    .td-search {
      min-width: 200px;
    }

    .td-icon-btn {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f8f9fa;
      color: #333;
      text-decoration: none;
      position: relative;
      transition: background 0.3s;
    }

    .td-icon-btn:hover {
      background: #e9ecef;
      color: #333;
    }

    .td-icon-btn .badge-count {
      position: absolute;
      top: -5px;
      right: -5px;
      background: #e74c3c;
      color: white;
      font-size: 0.6rem;
      padding: 2px 6px;
      border-radius: 50%;
      border: 2px solid white;
    }

    .td-dot {
      width: 8px;
      height: 8px;
      background: #e74c3c;
      border-radius: 50%;
      position: absolute;
      top: 8px;
      right: 8px;
      border: 2px solid white;
    }

    .td-content {
      padding: 25px;
      flex: 1;
    }

    .td-footer {
      background: white;
      padding: 15px 25px;
      text-align: center;
      font-size: 0.85rem;
      color: #6c757d;
      border-top: 1px solid #e9ecef;
    }

    /* Stat Cards */
    .stat-card {
      padding: 15px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      display: flex;
      align-items: center;
      gap: 15px;
      background: white;
      transition: transform 0.2s;
      border: none;
    }

    .stat-card:hover {
      transform: translateY(-2px);
    }

    .stat-icon {
      width: 48px;
      height: 48px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 24px;
      flex-shrink: 0;
    }

    .stat-card h3 {
      margin: 0;
      font-size: 1.5rem;
    }

    .stat-card p {
      margin: 0;
      color: #6c757d;
      font-size: 0.85rem;
    }

    /* Section Title */
    .section-title {
      font-size: 1.1rem;
      font-weight: 600;
      margin-bottom: 15px;
      color: #2c3e50;
    }

    .section-title i {
      color: #3498db;
      margin-right: 8px;
    }

    /* Class Cards */
    .class-card {
      border: none;
      border-radius: 15px;
      box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
      transition: transform 0.3s, box-shadow 0.3s;
    }

    .class-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
    }

    .class-card .progress {
      height: 6px;
      border-radius: 3px;
    }

    /* Notice Cards */
    .notice-card {
      border: none;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.06);
      transition: transform 0.3s;
    }

    .notice-card:hover {
      transform: translateY(-3px);
    }

    /* Gradient backgrounds */
    .bg-grad-1 {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .bg-grad-2 {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .bg-grad-3 {
      background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .bg-grad-4 {
      background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }

    .bg-grad-5 {
      background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }

    .bg-grad-6 {
      background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%);
    }

    /* Overlay for mobile */
    .td-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 999;
    }

    #tdSidebarToggle {
      display: none;
    }

    #tdSidebarToggle:checked~.td-overlay {
      display: block;
    }

    #tdSidebarToggle:checked~.td-sidebar {
      transform: translateX(0);
    }

    /* Responsive */
    @media (max-width: 992px) {
      .td-sidebar {
        transform: translateX(-100%);
      }

      .td-main {
        margin-left: 0;
      }

      .td-burger {
        display: block;
      }

      #tdSidebarToggle:checked~.td-sidebar {
        transform: translateX(0);
      }

      .td-search {
        min-width: 150px;
      }
    }

    @media (max-width: 576px) {
      .td-navbar {
        padding: 10px 15px;
      }

      .td-content {
        padding: 15px;
      }

      .td-search {
        min-width: 100px;
        order: 10;
        width: 100%;
      }

      .stat-card {
        padding: 10px;
        gap: 10px;
      }

      .stat-icon {
        width: 36px;
        height: 36px;
        font-size: 18px;
      }

      .stat-card h3 {
        font-size: 1.2rem;
      }
    }
  </style>
</head>

<body>
  <input type="checkbox" id="tdSidebarToggle">
  <div class="td-wrapper">
    <label for="tdSidebarToggle" class="td-overlay"></label>

    <!-- Sidebar -->
    <aside class="td-sidebar">
      <div class="td-brand">
        <i class="bi bi-mortarboard-fill"></i>
        <span>Bright Future<small>School Portal</small></span>
      </div>
      <div class="td-teacher-box">
        <div class="td-avatar">
          <?php echo $teacher_initials; ?>
        </div>
        <div>
          <h6>
            <?php echo htmlspecialchars($teacher_name); ?>
          </h6>
          <p>Mathematics Teacher</p>
        </div>
      </div>
      <nav class="td-nav">
        <div class="td-nav-title">Main</div>
        <a href="index.php" class="active"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="students.php"><i class="bi bi-people"></i> My Students</a>
        <a href="attendance.php"><i class="bi bi-calendar2-check"></i> Attendance</a>
        <a href="subjects.php"><i class="bi bi-journal-bookmark"></i> My Subjects</a>
        <a href="timetable.php"><i class="bi bi-clock-history"></i> My Timetable</a>
        <div class="td-nav-title">Academics</div>
        <a href="assignments.php"><i class="bi bi-file-earmark-text"></i> Assignments</a>
        <a href="exams.php"><i class="bi bi-pencil-square"></i> Exams</a>
        <a href="results.php"><i class="bi bi-bar-chart-line"></i> Results</a>
        <div class="td-nav-title">Communication</div>
        <a href="notices.php"><i class="bi bi-megaphone"></i> Notices</a>
        <a href="messages.php"><i class="bi bi-chat-dots"></i> Messages</a>
        <div class="td-nav-title">Account</div>
        <a href="profile.php"><i class="bi bi-person-badge"></i> My Profile</a>
        <a href="settings.php"><i class="bi bi-gear"></i> Settings</a>
        <a href="#" class="logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </nav>
    </aside>

    <!-- Main Content -->
    <div class="td-main">
      <!-- Navbar -->
      <header class="td-navbar">
        <label for="tdSidebarToggle" class="td-burger"><i class="bi bi-list"></i></label>
        <h1 class="td-page-title">Teacher Dashboard <small>Welcome back,
            <?php echo htmlspecialchars($teacher_name); ?>
          </small></h1>
        <div class="td-search ms-auto">
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
            <input type="search" class="form-control border-start-0" placeholder="Search students, classes..."
              id="globalSearch">
          </div>
        </div>
        <a href="notices.php" class="td-icon-btn"><i class="bi bi-bell"></i><span class="td-dot"></span></a>
        <a href="messages.php" class="td-icon-btn position-relative">
          <i class="bi bi-envelope"></i>
          <?php if ($unread_count > 0): ?>
            <span class="badge-count">
              <?php echo $unread_count; ?>
            </span>
          <?php endif; ?>
        </a>
        <a href="profile.php" class="d-flex align-items-center gap-2 text-dark text-decoration-none">
          <span class="td-avatar">
            <?php echo $teacher_initials; ?>
          </span>
          <span class="d-none d-md-block">
            <strong class="d-block" style="font-size:.85rem">
              <?php echo htmlspecialchars($teacher_name); ?>
            </strong>
            <small class="text-muted" style="font-size:.72rem">Mathematics Teacher</small>
          </span>
        </a>
      </header>

      <main class="td-content">
        <!-- STATS -->
        <div class="row g-3 mb-4">
          <div class="col-6 col-lg-4 col-xl-2">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-1"><i class="bi bi-people-fill"></i></div>
              <div>
                <h3>
                  <?php echo $stats['students']; ?>
                </h3>
                <p>My Students</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-lg-4 col-xl-2">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-2"><i class="bi bi-building"></i></div>
              <div>
                <h3>
                  <?php echo $stats['classes']; ?>
                </h3>
                <p>My Classes</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-lg-4 col-xl-2">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-3"><i class="bi bi-journal-bookmark-fill"></i></div>
              <div>
                <h3>
                  <?php echo $stats['subjects']; ?>
                </h3>
                <p>My Subjects</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-lg-4 col-xl-2">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-4"><i class="bi bi-calendar2-check-fill"></i></div>
              <div>
                <h3>
                  <?php echo $stats['attendance']; ?>%
                </h3>
                <p>Today's Attendance</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-lg-4 col-xl-2">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-5"><i class="bi bi-file-earmark-text-fill"></i></div>
              <div>
                <h3>
                  <?php echo $stats['assignments']; ?>
                </h3>
                <p>Pending Assignments</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-lg-4 col-xl-2">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-6"><i class="bi bi-pencil-square"></i></div>
              <div>
                <h3>
                  <?php echo $stats['exams']; ?>
                </h3>
                <p>Upcoming Exams</p>
              </div>
            </div>
          </div>
        </div>

        <!-- TIMETABLE -->
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-clock me-2 text-primary"></i>Today's Timetable</span>
            <a href="timetable.php" class="btn btn-sm btn-outline-primary">Full Timetable</a>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead>
                <tr>
                  <th>Time</th>
                  <th>Class</th>
                  <th>Subject</th>
                  <th>Room</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($today_timetable) > 0): ?>
                  <?php foreach ($today_timetable as $slot): ?>
                    <tr>
                      <td>
                        <?php echo htmlspecialchars($slot['time_slot']); ?>
                      </td>
                      <td>
                        <?php echo htmlspecialchars($slot['class_name'] . ' - ' . $slot['class_grade']); ?>
                      </td>
                      <td>
                        <?php echo htmlspecialchars($slot['subject_title']); ?>
                      </td>
                      <td>
                        <?php echo htmlspecialchars($slot['room'] ?? 'N/A'); ?>
                      </td>
                      <td>
                        <span class="badge <?php echo getStatusBadgeClass($slot['status']); ?>">
                          <?php echo $slot['status']; ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="5" class="text-center text-muted py-3">
                      <i class="bi bi-clock me-1"></i> No classes scheduled for today
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- MY CLASSES -->
        <h2 class="section-title"><i class="bi bi-building"></i> My Classes</h2>
        <div class="row g-3 mb-4">
          <?php if (count($my_classes) > 0): ?>
            <?php foreach ($my_classes as $class): ?>
              <div class="col-md-6 col-xl-4">
                <div class="card class-card h-100">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <h5 class="fw-bold mb-0">
                        <?php echo htmlspecialchars($class['name'] . ' - ' . $class['grade']); ?>
                      </h5>
                      <span class="badge bg-primary-subtle text-primary">
                        <?php echo htmlspecialchars($class['room_no'] ?? 'No Room'); ?>
                      </span>
                    </div>
                    <p class="text-muted small mb-3">Subject: Mathematics</p>
                    <div class="d-flex justify-content-between small mb-1">
                      <span>Students</span>
                      <strong>
                        <?php echo $class['student_count']; ?>
                      </strong>
                    </div>
                    <div class="progress mb-3">
                      <div class="progress-bar bg-success" style="width: 75%;"></div>
                    </div>
                    <div class="d-flex gap-2">
                      <a href="students.php?class=<?php echo $class['id']; ?>" class="btn btn-primary btn-sm">View
                        Students</a>
                      <a href="attendance.php?class=<?php echo $class['id']; ?>"
                        class="btn btn-outline-secondary btn-sm">Attendance</a>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="col-12">
              <div class="text-center py-4 text-muted">
                <i class="bi bi-building fs-1 d-block mb-2"></i>
                <p>No classes assigned yet</p>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <div class="row g-3 mb-4">
          <!-- RECENT ATTENDANCE -->
          <div class="col-xl-6">
            <div class="card h-100">
              <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar2-check me-2 text-primary"></i>Recent Attendance</span>
                <a href="attendance.php" class="btn btn-sm btn-outline-primary">View All</a>
              </div>
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>Student</th>
                      <th>Class</th>
                      <th>Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($recent_attendance) > 0): ?>
                      <?php foreach ($recent_attendance as $att): ?>
                        <tr>
                          <td>
                            <?php echo htmlspecialchars($att['student_name']); ?>
                          </td>
                          <td>
                            <?php echo htmlspecialchars($att['class_name']); ?>
                          </td>
                          <td>
                            <?php echo date('d M Y', strtotime($att['attendance_date'])); ?>
                          </td>
                          <td>
                            <span class="badge <?php echo getStatusBadgeClass($att['status']); ?>">
                              <?php echo $att['status']; ?>
                            </span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="4" class="text-center text-muted py-3">No attendance records found</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- PENDING ASSIGNMENTS -->
          <div class="col-xl-6">
            <div class="card h-100">
              <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-file-earmark-text me-2 text-primary"></i>Pending Assignments</span>
                <a href="assignments.php" class="btn btn-sm btn-outline-primary">View All</a>
              </div>
              <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
                    <tr>
                      <th>Assignment</th>
                      <th>Subject</th>
                      <th>Class</th>
                      <th>Due Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (count($pending_assignments) > 0): ?>
                      <?php foreach ($pending_assignments as $assign):
                        $is_overdue = strtotime($assign['due_date']) < time();
                        $status = $is_overdue ? 'Overdue' : 'Pending';
                        ?>
                        <tr>
                          <td>
                            <?php echo htmlspecialchars($assign['title']); ?>
                          </td>
                          <td>
                            <?php echo htmlspecialchars($assign['subject_title']); ?>
                          </td>
                          <td>
                            <?php echo htmlspecialchars($assign['class_name']); ?>
                          </td>
                          <td>
                            <?php echo date('d M Y', strtotime($assign['due_date'])); ?>
                          </td>
                          <td>
                            <span class="badge <?php echo $is_overdue ? 'bg-danger' : 'bg-warning text-dark'; ?>">
                              <?php echo $status; ?>
                            </span>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php else: ?>
                      <tr>
                        <td colspan="5" class="text-center text-muted py-3">No pending assignments</td>
                      </tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            </div>
          </div>
        </div>

        <!-- UPCOMING EXAMS -->
        <div class="card mb-4">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-pencil-square me-2 text-primary"></i>Upcoming Exams</span>
            <a href="exams.php" class="btn btn-sm btn-outline-primary">All Exams</a>
          </div>
          <div class="table-responsive">
            <table class="table table-hover">
              <thead>
                <tr>
                  <th>Exam</th>
                  <th>Class</th>
                  <th>Subject</th>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Room</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($upcoming_exams) > 0): ?>
                  <?php foreach ($upcoming_exams as $exam): ?>
                    <tr>
                      <td>
                        <?php echo htmlspecialchars($exam['exam_title']); ?>
                      </td>
                      <td>
                        <?php echo htmlspecialchars($exam['class_name']); ?>
                      </td>
                      <td>
                        <?php echo htmlspecialchars($exam['subject_title']); ?>
                      </td>
                      <td>
                        <?php echo date('d M Y', strtotime($exam['exam_date'])); ?>
                      </td>
                      <td>
                        <?php echo date('h:i A', strtotime($exam['start_time'])); ?>
                      </td>
                      <td>
                        <?php echo htmlspecialchars($exam['room'] ?? 'N/A'); ?>
                      </td>
                      <td>
                        <span class="badge <?php echo getStatusBadgeClass($exam['status']); ?>">
                          <?php echo $exam['status']; ?>
                        </span>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" class="text-center text-muted py-3">No upcoming exams</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- NOTICES -->
        <h2 class="section-title"><i class="bi bi-megaphone"></i> Latest Notices</h2>
        <div class="row g-3">
          <?php if (count($latest_notices) > 0): ?>
            <?php foreach ($latest_notices as $notice): ?>
              <div class="col-md-6 col-xl-3">
                <div class="card notice-card h-100">
                  <div class="card-body">
                    <span class="badge <?php echo getCategoryBadgeClass($notice['category']); ?> mb-2">
                      <?php echo htmlspecialchars($notice['category']); ?>
                    </span>
                    <h6 class="fw-bold">
                      <?php echo htmlspecialchars($notice['title']); ?>
                    </h6>
                    <p class="small text-muted">
                      <?php echo htmlspecialchars(substr($notice['details'], 0, 80)) . (strlen($notice['details']) > 80 ? '...' : ''); ?>
                    </p>
                    <div class="d-flex justify-content-between align-items-center">
                      <small class="text-muted">
                        <i class="bi bi-calendar3"></i>
                        <?php echo date('d M Y', strtotime($notice['created_at'])); ?>
                      </small>
                      <a href="notices.php" class="btn btn-sm btn-primary">View</a>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="col-12">
              <div class="text-center py-4 text-muted">
                <i class="bi bi-megaphone fs-1 d-block mb-2"></i>
                <p>No notices available</p>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </main>

      <footer class="td-footer">© 2026 Bright Future School — Teacher Panel. All rights reserved.</footer>
    </div>
  </div>

  <script>
    // Global search functionality
    document.getElementById('globalSearch')?.addEventListener('keyup', function (e) {
      if (e.key === 'Enter') {
        const searchTerm = this.value.trim();
        if (searchTerm) {
          window.location.href = 'students.php?search=' + encodeURIComponent(searchTerm);
        }
      }
    });

    // Auto-refresh dashboard every 60 seconds
    setTimeout(function () {
      location.reload();
    }, 60000);
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>