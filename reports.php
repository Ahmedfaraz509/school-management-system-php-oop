<?php
require_once '../database/connect.php';

// Get Student Statistics
try {
  $studentQuery = $conn->prepare("SELECT 
        COUNT(*) as total_students,
        SUM(CASE WHEN gender = 'Male' THEN 1 ELSE 0 END) as male_students,
        SUM(CASE WHEN gender = 'Female' THEN 1 ELSE 0 END) as female_students,
        SUM(CASE WHEN status = 'Active' THEN 1 ELSE 0 END) as active_students
        FROM students");
  $studentQuery->execute();
  $studentStats = $studentQuery->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $studentStats = ['total_students' => 0, 'male_students' => 0, 'female_students' => 0, 'active_students' => 0];
}

// Get Teacher Statistics
try {
  $teacherQuery = $conn->prepare("SELECT 
        COUNT(*) as total_teachers,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_teachers,
        SUM(CASE WHEN gender = 'male' THEN 1 ELSE 0 END) as male_teachers,
        SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female_teachers
        FROM teachers");
  $teacherQuery->execute();
  $teacherStats = $teacherQuery->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $teacherStats = ['total_teachers' => 0, 'active_teachers' => 0, 'male_teachers' => 0, 'female_teachers' => 0];
}

// Get Attendance Statistics
try {
  $attendanceQuery = $conn->prepare("SELECT 
        COUNT(*) as total_attendance,
        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present,
        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent,
        SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late
        FROM attendance WHERE attendance_date = CURDATE()");
  $attendanceQuery->execute();
  $attendanceStats = $attendanceQuery->fetch(PDO::FETCH_ASSOC);

  if (!$attendanceStats || $attendanceStats['total_attendance'] == 0) {
    $attendanceStats = ['total_attendance' => 0, 'present' => 0, 'absent' => 0, 'late' => 0];
  }
} catch (PDOException $e) {
  $attendanceStats = ['total_attendance' => 0, 'present' => 0, 'absent' => 0, 'late' => 0];
}

// Get Fees Statistics
try {
  $feesQuery = $conn->prepare("SELECT 
        COUNT(*) as total_invoices,
        SUM(total_fee) as total_fees,
        SUM(paid_amount) as total_paid,
        SUM(total_fee - paid_amount) as total_remaining,
        SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) as paid_invoices,
        SUM(CASE WHEN status = 'Unpaid' THEN 1 ELSE 0 END) as unpaid_invoices,
        SUM(CASE WHEN status = 'Overdue' THEN 1 ELSE 0 END) as overdue_invoices
        FROM fee_invoices");
  $feesQuery->execute();
  $feesStats = $feesQuery->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $feesStats = ['total_invoices' => 0, 'total_fees' => 0, 'total_paid' => 0, 'total_remaining' => 0, 'paid_invoices' => 0, 'unpaid_invoices' => 0, 'overdue_invoices' => 0];
}

// Get Exam Statistics
try {
  $examQuery = $conn->prepare("SELECT 
        COUNT(*) as total_exams,
        SUM(CASE WHEN status = 'Scheduled' THEN 1 ELSE 0 END) as scheduled,
        SUM(CASE WHEN status = 'Ongoing' THEN 1 ELSE 0 END) as ongoing,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed
        FROM exams");
  $examQuery->execute();
  $examStats = $examQuery->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $examStats = ['total_exams' => 0, 'scheduled' => 0, 'ongoing' => 0, 'completed' => 0];
}

// Get Result Statistics
try {
  $resultQuery = $conn->prepare("SELECT 
        COUNT(*) as total_results,
        SUM(CASE WHEN result = 'Pass' THEN 1 ELSE 0 END) as passed,
        SUM(CASE WHEN result = 'Fail' THEN 1 ELSE 0 END) as failed,
        AVG(percentage) as avg_percentage
        FROM results");
  $resultQuery->execute();
  $resultStats = $resultQuery->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $resultStats = ['total_results' => 0, 'passed' => 0, 'failed' => 0, 'avg_percentage' => 0];
}

// Get Class Statistics
try {
  $classQuery = $conn->prepare("SELECT 
        COUNT(*) as total_classes,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_classes
        FROM classes");
  $classQuery->execute();
  $classStats = $classQuery->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  $classStats = ['total_classes' => 0, 'active_classes' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports & Analytics - EduPulse School Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .stat-icon-wrapper {
      width: 48px;
      height: 48px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      flex-shrink: 0;
    }

    .stat-icon-indigo {
      background: #e8eaf6;
      color: #1a237e;
    }

    .stat-icon-emerald {
      background: #d1fae5;
      color: #065f46;
    }

    .stat-icon-cyan {
      background: #e1f5fe;
      color: #0d47a1;
    }

    .stat-icon-purple {
      background: #f3e5f5;
      color: #4a148c;
    }

    .stat-icon-sky {
      background: #e3f2fd;
      color: #0d47a1;
    }

    .stat-icon-amber {
      background: #fff3e0;
      color: #e65100;
    }

    .stat-icon-rose {
      background: #fce4ec;
      color: #c62828;
    }

    .report-card {
      transition: all 0.3s ease;
      border: 1px solid #e9ecef;
    }

    .report-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .report-stats {
      display: flex;
      gap: 20px;
      padding: 10px 0;
      border-top: 1px solid #e9ecef;
      margin-top: 10px;
    }

    .report-stats .stat-item {
      text-align: center;
      flex: 1;
    }

    .report-stats .stat-item .number {
      font-size: 1.2rem;
      font-weight: 700;
      color: #212529;
    }

    .report-stats .stat-item .label {
      font-size: 0.7rem;
      color: #6c757d;
    }

    @media print {

      .app-sidebar,
      .app-topbar,
      .app-footer,
      .no-print {
        display: none !important;
      }

      .app-main {
        margin-left: 0 !important;
        padding: 0 !important;
      }

      .app-content {
        padding: 10px !important;
      }

      .report-card {
        border: 1px solid #ddd !important;
        box-shadow: none !important;
        page-break-inside: avoid;
      }

      body {
        background: white !important;
      }
    }
  </style>
</head>

<body>
  <div class="sidebar-backdrop"></div>

  <div class="app-wrapper">
    <!-- LEFT SIDEBAR -->
    <aside class="app-sidebar">
      <div class="sidebar-header">
        <a href="index.php" class="brand-logo">
          <div class="brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
          <span class="brand-text">EduPulse <small class="fw-normal text-muted fs-6">SMS</small></span>
        </a>
      </div>

      <div class="sidebar-nav">
        <div class="nav-section-title">Main</div>
        <ul class="sidebar-menu">
          <li class="nav-item"><a href="index.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i><span
                class="nav-text">Dashboard</span></a></li>
        </ul>

        <div class="nav-section-title">Academics</div>
        <ul class="sidebar-menu">
          <li class="nav-item"><a href="students.php" class="nav-link"><i class="bi bi-people-fill"></i><span
                class="nav-text">Students</span></a></li>
          <li class="nav-item"><a href="teachers.php" class="nav-link"><i class="bi bi-person-video3"></i><span
                class="nav-text">Teachers</span></a></li>
          <li class="nav-item"><a href="parents.php" class="nav-link"><i class="bi bi-person-heart"></i><span
                class="nav-text">Parents</span></a></li>
          <li class="nav-item"><a href="classes.php" class="nav-link"><i class="bi bi-door-open-fill"></i><span
                class="nav-text">Classes</span></a></li>
          <li class="nav-item"><a href="subjects.php" class="nav-link"><i class="bi bi-book-half"></i><span
                class="nav-text">Subjects</span></a></li>
          <li class="nav-item"><a href="timetable.php" class="nav-link"><i class="bi bi-calendar3-range"></i><span
                class="nav-text">Timetable</span></a></li>
          <li class="nav-item"><a href="assignments.php" class="nav-link"><i class="bi bi-journal-check"></i><span
                class="nav-text">Assignments</span></a></li>
        </ul>

        <div class="nav-section-title">Operations</div>
        <ul class="sidebar-menu">
          <li class="nav-item"><a href="attendance.php" class="nav-link"><i class="bi bi-check2-square"></i><span
                class="nav-text">Attendance</span></a></li>
          <li class="nav-item"><a href="exams.php" class="nav-link"><i class="bi bi-pencil-square"></i><span
                class="nav-text">Exams</span></a></li>
          <li class="nav-item"><a href="results.php" class="nav-link"><i class="bi bi-trophy-fill"></i><span
                class="nav-text">Results</span></a></li>
          <li class="nav-item"><a href="fees.php" class="nav-link"><i class="bi bi-cash-stack"></i><span
                class="nav-text">Fees</span></a></li>
        </ul>

        <div class="nav-section-title">Communication</div>
        <ul class="sidebar-menu">
          <li class="nav-item"><a href="notices.php" class="nav-link"><i class="bi bi-megaphone-fill"></i><span
                class="nav-text">Notices</span></a></li>
          <li class="nav-item"><a href="events.php" class="nav-link"><i class="bi bi-calendar-event-fill"></i><span
                class="nav-text">Events</span></a></li>
          <li class="nav-item"><a href="messages.php" class="nav-link"><i class="bi bi-chat-dots-fill"></i><span
                class="nav-text">Messages</span></a></li>
        </ul>

        <div class="nav-section-title">System</div>
        <ul class="sidebar-menu">
          <li class="nav-item"><a href="reports.php" class="nav-link active"><i
                class="bi bi-bar-chart-line-fill"></i><span class="nav-text">Reports</span></a></li>
          <li class="nav-item"><a href="profile.php" class="nav-link"><i class="bi bi-person-circle"></i><span
                class="nav-text">Profile</span></a></li>
          <li class="nav-item"><a href="settings.php" class="nav-link"><i class="bi bi-gear-fill"></i><span
                class="nav-text">Settings</span></a></li>
          <li class="nav-item"><a href="#" class="nav-link text-danger" data-bs-toggle="modal"
              data-bs-target="#logoutModal"><i class="bi bi-box-arrow-right text-danger"></i><span
                class="nav-text">Logout</span></a></li>
        </ul>
      </div>

      <div class="sidebar-footer">
        <div class="user-quick-info">
          <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop&q=80"
            alt="Admin" class="user-avatar-sm">
          <div class="user-details overflow-hidden">
            <div class="text-white fw-bold text-truncate" style="font-size: 0.85rem;">Dr. Robert Vance</div>
            <div class="text-muted text-truncate" style="font-size: 0.75rem;">Super Admin</div>
          </div>
        </div>
      </div>
    </aside>

    <!-- MAIN CONTENT -->
    <div class="app-main">
      <header class="app-topbar no-print">
        <div class="topbar-left">
          <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn">
            <i class="bi bi-list"></i>
          </button>
          <div class="search-input-group">
            <i class="bi bi-search search-icon"></i>
            <input type="text" class="form-control" placeholder="Search... (Ctrl + K)">
          </div>
        </div>

        <div class="topbar-right">
          <div class="dropdown">
            <a href="#" class="user-dropdown-btn dropdown-toggle" data-bs-toggle="dropdown">
              <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop&q=80"
                alt="Admin">
              <div class="user-meta">
                <span class="user-name">Dr. Robert Vance</span>
                <span class="user-role">Super Admin</span>
              </div>
            </a>
            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2">
              <li><a class="dropdown-item py-2" href="profile.php"><i class="bi bi-person me-2"></i> My Profile</a></li>
              <li><a class="dropdown-item py-2" href="settings.php"><i class="bi bi-gear me-2"></i> Settings</a></li>
              <li>
                <hr class="dropdown-divider">
              </li>
              <li><a class="dropdown-item py-2 text-danger" href="#" data-bs-toggle="modal"
                  data-bs-target="#logoutModal"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
          </div>
        </div>
      </header>

      <main class="app-content">
        <div class="page-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
          <div>
            <h1 class="page-title">Reports & Data Analytics</h1>
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Reports</li>
              </ol>
            </nav>
          </div>
          <div class="d-flex gap-2 no-print">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print()">
              <i class="bi bi-printer me-1"></i> Print Reports
            </button>
          </div>
        </div>

        <!-- 7 Dynamic Report Cards -->
        <div class="row g-4">
          <!-- 1. Student Report -->
          <div class="col-md-6 col-xl-4">
            <div class="card report-card h-100 p-3">
              <div class="d-flex align-items-center gap-3 mb-2">
                <div class="stat-icon-wrapper stat-icon-indigo mb-0">
                  <i class="bi bi-people-fill"></i>
                </div>
                <div>
                  <h5 class="fw-bold text-dark mb-0">Student Report</h5>
                  <small class="text-muted">Enrollments & Demographics</small>
                </div>
              </div>
              <div class="report-stats">
                <div class="stat-item">
                  <div class="number">
                    <?php echo $studentStats['total_students'] ?? 0; ?>
                  </div>
                  <div class="label">Total</div>
                </div>
                <div class="stat-item">
                  <div class="number text-primary">
                    <?php echo $studentStats['male_students'] ?? 0; ?>
                  </div>
                  <div class="label">Male</div>
                </div>
                <div class="stat-item">
                  <div class="number text-danger">
                    <?php echo $studentStats['female_students'] ?? 0; ?>
                  </div>
                  <div class="label">Female</div>
                </div>
                <div class="stat-item">
                  <div class="number text-success">
                    <?php echo $studentStats['active_students'] ?? 0; ?>
                  </div>
                  <div class="label">Active</div>
                </div>
              </div>
              <div class="d-flex gap-2 mt-2">
                <button class="btn btn-sm btn-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#reportModal"
                  data-report="student">
                  <i class="bi bi-eye me-1"></i> View Details
                </button>
              </div>
            </div>
          </div>

          <!-- 2. Teacher Report -->
          <div class="col-md-6 col-xl-4">
            <div class="card report-card h-100 p-3">
              <div class="d-flex align-items-center gap-3 mb-2">
                <div class="stat-icon-wrapper stat-icon-emerald mb-0">
                  <i class="bi bi-person-workspace"></i>
                </div>
                <div>
                  <h5 class="fw-bold text-dark mb-0">Teacher Report</h5>
                  <small class="text-muted">Faculty Roster</small>
                </div>
              </div>
              <div class="report-stats">
                <div class="stat-item">
                  <div class="number">
                    <?php echo $teacherStats['total_teachers'] ?? 0; ?>
                  </div>
                  <div class="label">Total</div>
                </div>
                <div class="stat-item">
                  <div class="number text-success">
                    <?php echo $teacherStats['active_teachers'] ?? 0; ?>
                  </div>
                  <div class="label">Active</div>
                </div>
                <div class="stat-item">
                  <div class="number text-primary">
                    <?php echo $teacherStats['male_teachers'] ?? 0; ?>
                  </div>
                  <div class="label">Male</div>
                </div>
                <div class="stat-item">
                  <div class="number text-danger">
                    <?php echo $teacherStats['female_teachers'] ?? 0; ?>
                  </div>
                  <div class="label">Female</div>
                </div>
              </div>
              <div class="d-flex gap-2 mt-2">
                <button class="btn btn-sm btn-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#reportModal"
                  data-report="teacher">
                  <i class="bi bi-eye me-1"></i> View Details
                </button>
              </div>
            </div>
          </div>

          <!-- 3. Attendance Report -->
          <div class="col-md-6 col-xl-4">
            <div class="card report-card h-100 p-3">
              <div class="d-flex align-items-center gap-3 mb-2">
                <div class="stat-icon-wrapper stat-icon-cyan mb-0">
                  <i class="bi bi-calendar-check-fill"></i>
                </div>
                <div>
                  <h5 class="fw-bold text-dark mb-0">Attendance Report</h5>
                  <small class="text-muted">Today's Stats</small>
                </div>
              </div>
              <div class="report-stats">
                <div class="stat-item">
                  <div class="number">
                    <?php echo $attendanceStats['total_attendance'] ?? 0; ?>
                  </div>
                  <div class="label">Total</div>
                </div>
                <div class="stat-item">
                  <div class="number text-success">
                    <?php echo $attendanceStats['present'] ?? 0; ?>
                  </div>
                  <div class="label">Present</div>
                </div>
                <div class="stat-item">
                  <div class="number text-danger">
                    <?php echo $attendanceStats['absent'] ?? 0; ?>
                  </div>
                  <div class="label">Absent</div>
                </div>
                <div class="stat-item">
                  <div class="number text-warning">
                    <?php echo $attendanceStats['late'] ?? 0; ?>
                  </div>
                  <div class="label">Late</div>
                </div>
              </div>
              <div class="d-flex gap-2 mt-2">
                <button class="btn btn-sm btn-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#reportModal"
                  data-report="attendance">
                  <i class="bi bi-eye me-1"></i> View Details
                </button>
              </div>
            </div>
          </div>

          <!-- 4. Fees Report -->
          <div class="col-md-6 col-xl-4">
            <div class="card report-card h-100 p-3">
              <div class="d-flex align-items-center gap-3 mb-2">
                <div class="stat-icon-wrapper stat-icon-purple mb-0">
                  <i class="bi bi-cash-stack"></i>
                </div>
                <div>
                  <h5 class="fw-bold text-dark mb-0">Fees Report</h5>
                  <small class="text-muted">Revenue & Arrears</small>
                </div>
              </div>
              <div class="report-stats">
                <div class="stat-item">
                  <div class="number text-success">$
                    <?php echo number_format($feesStats['total_paid'] ?? 0, 0); ?>
                  </div>
                  <div class="label">Collected</div>
                </div>
                <div class="stat-item">
                  <div class="number text-warning">$
                    <?php echo number_format($feesStats['total_remaining'] ?? 0, 0); ?>
                  </div>
                  <div class="label">Pending</div>
                </div>
                <div class="stat-item">
                  <div class="number text-danger">
                    <?php echo $feesStats['overdue_invoices'] ?? 0; ?>
                  </div>
                  <div class="label">Overdue</div>
                </div>
                <div class="stat-item">
                  <div class="number">
                    <?php echo $feesStats['total_invoices'] ?? 0; ?>
                  </div>
                  <div class="label">Invoices</div>
                </div>
              </div>
              <div class="d-flex gap-2 mt-2">
                <button class="btn btn-sm btn-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#reportModal"
                  data-report="fees">
                  <i class="bi bi-eye me-1"></i> View Details
                </button>
              </div>
            </div>
          </div>

          <!-- 5. Exam Report -->
          <div class="col-md-6 col-xl-4">
            <div class="card report-card h-100 p-3">
              <div class="d-flex align-items-center gap-3 mb-2">
                <div class="stat-icon-wrapper stat-icon-sky mb-0">
                  <i class="bi bi-pencil-square"></i>
                </div>
                <div>
                  <h5 class="fw-bold text-dark mb-0">Exam Report</h5>
                  <small class="text-muted">Assessment Schedule</small>
                </div>
              </div>
              <div class="report-stats">
                <div class="stat-item">
                  <div class="number">
                    <?php echo $examStats['total_exams'] ?? 0; ?>
                  </div>
                  <div class="label">Total</div>
                </div>
                <div class="stat-item">
                  <div class="number text-primary">
                    <?php echo $examStats['scheduled'] ?? 0; ?>
                  </div>
                  <div class="label">Scheduled</div>
                </div>
                <div class="stat-item">
                  <div class="number text-warning">
                    <?php echo $examStats['ongoing'] ?? 0; ?>
                  </div>
                  <div class="label">Ongoing</div>
                </div>
                <div class="stat-item">
                  <div class="number text-success">
                    <?php echo $examStats['completed'] ?? 0; ?>
                  </div>
                  <div class="label">Completed</div>
                </div>
              </div>
              <div class="d-flex gap-2 mt-2">
                <button class="btn btn-sm btn-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#reportModal"
                  data-report="exam">
                  <i class="bi bi-eye me-1"></i> View Details
                </button>
              </div>
            </div>
          </div>

          <!-- 6. Result Report -->
          <div class="col-md-6 col-xl-4">
            <div class="card report-card h-100 p-3">
              <div class="d-flex align-items-center gap-3 mb-2">
                <div class="stat-icon-wrapper stat-icon-amber mb-0">
                  <i class="bi bi-trophy-fill"></i>
                </div>
                <div>
                  <h5 class="fw-bold text-dark mb-0">Result Report</h5>
                  <small class="text-muted">Grades & Performance</small>
                </div>
              </div>
              <div class="report-stats">
                <div class="stat-item">
                  <div class="number">
                    <?php echo $resultStats['total_results'] ?? 0; ?>
                  </div>
                  <div class="label">Total</div>
                </div>
                <div class="stat-item">
                  <div class="number text-success">
                    <?php echo $resultStats['passed'] ?? 0; ?>
                  </div>
                  <div class="label">Pass</div>
                </div>
                <div class="stat-item">
                  <div class="number text-danger">
                    <?php echo $resultStats['failed'] ?? 0; ?>
                  </div>
                  <div class="label">Fail</div>
                </div>
                <div class="stat-item">
                  <div class="number text-primary">
                    <?php echo round($resultStats['avg_percentage'] ?? 0, 1); ?>%
                  </div>
                  <div class="label">Avg %</div>
                </div>
              </div>
              <div class="d-flex gap-2 mt-2">
                <button class="btn btn-sm btn-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#reportModal"
                  data-report="result">
                  <i class="bi bi-eye me-1"></i> View Details
                </button>
              </div>
            </div>
          </div>

          <!-- 7. Class Report -->
          <div class="col-md-6 col-xl-4">
            <div class="card report-card h-100 p-3">
              <div class="d-flex align-items-center gap-3 mb-2">
                <div class="stat-icon-wrapper stat-icon-rose mb-0">
                  <i class="bi bi-door-open-fill"></i>
                </div>
                <div>
                  <h5 class="fw-bold text-dark mb-0">Class Report</h5>
                  <small class="text-muted">Classrooms & Sections</small>
                </div>
              </div>
              <div class="report-stats">
                <div class="stat-item">
                  <div class="number">
                    <?php echo $classStats['total_classes'] ?? 0; ?>
                  </div>
                  <div class="label">Total</div>
                </div>
                <div class="stat-item">
                  <div class="number text-success">
                    <?php echo $classStats['active_classes'] ?? 0; ?>
                  </div>
                  <div class="label">Active</div>
                </div>
                <div class="stat-item">
                  <div class="number">
                    <?php echo $studentStats['total_students'] ?? 0; ?>
                  </div>
                  <div class="label">Students</div>
                </div>
                <div class="stat-item">
                  <div class="number">
                    <?php echo $teacherStats['total_teachers'] ?? 0; ?>
                  </div>
                  <div class="label">Teachers</div>
                </div>
              </div>
              <div class="d-flex gap-2 mt-2">
                <button class="btn btn-sm btn-primary flex-grow-1" data-bs-toggle="modal" data-bs-target="#reportModal"
                  data-report="class">
                  <i class="bi bi-eye me-1"></i> View Details
                </button>
              </div>
            </div>
          </div>
        </div>
      </main>

      <footer class="app-footer no-print">
        <div>© 2026 <strong>EduPulse Academy</strong> - All rights reserved.</div>
        <div class="d-none d-sm-block">Version 2.4.0</div>
      </footer>
    </div>
  </div>

  <!-- REPORT DETAIL MODAL -->
  <div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-file-earmark-bar-graph text-primary me-2"></i><span
              id="reportModalTitle">Report Details</span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4" id="reportModalBody">
          <div class="alert alert-info d-flex align-items-center gap-2 mb-3">
            <i class="bi bi-info-circle-fill fs-5"></i>
            <div>Report generated on
              <?php echo date('F d, Y h:i A'); ?>
            </div>
          </div>
          <div id="reportContent">
            <!-- Dynamic content will load here -->
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- LOGOUT MODAL -->
  <div class="modal fade" id="logoutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content text-center p-3">
        <div class="modal-body">
          <div class="text-danger fs-1 mb-2"><i class="bi bi-box-arrow-right"></i></div>
          <h5 class="fw-bold">Sign Out</h5>
          <p class="text-muted small">Are you sure you want to end your session?</p>
          <div class="d-flex justify-content-center gap-2 mt-3">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <a href="index.php" class="btn btn-danger">Logout</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="toastContainer"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      // Sidebar toggle
      var sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
      var sidebarBackdrop = document.querySelector('.sidebar-backdrop');
      var sidebar = document.querySelector('.app-sidebar');

      if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', function () {
          sidebar.classList.toggle('show');
          sidebarBackdrop.classList.toggle('show');
        });
      }

      if (sidebarBackdrop) {
        sidebarBackdrop.addEventListener('click', function () {
          sidebar.classList.remove('show');
          sidebarBackdrop.classList.remove('show');
        });
      }

      // Report Modal - Load dynamic content
      var reportButtons = document.querySelectorAll('[data-bs-target="#reportModal"]');
      reportButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
          var reportType = this.dataset.report;
          var modalTitle = document.getElementById('reportModalTitle');
          var modalBody = document.getElementById('reportContent');

          var titles = {
            'student': 'Student Enrollment Report',
            'teacher': 'Faculty & Teacher Report',
            'attendance': 'Attendance Report',
            'fees': 'Financial Fee Report',
            'exam': 'Exam Schedule Report',
            'result': 'Academic Result Report',
            'class': 'Class Distribution Report'
          };

          modalTitle.textContent = titles[reportType] || 'Report Details';

          // Load content based on report type
          var content = '';
          switch (reportType) {
            case 'student':
              content = `
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr><th>Metric</th><th>Value</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>Total Students</td><td class="fw-bold"><?php echo $studentStats['total_students'] ?? 0; ?></td></tr>
                                        <tr><td>Male Students</td><td class="fw-bold"><?php echo $studentStats['male_students'] ?? 0; ?></td></tr>
                                        <tr><td>Female Students</td><td class="fw-bold"><?php echo $studentStats['female_students'] ?? 0; ?></td></tr>
                                        <tr><td>Active Students</td><td class="fw-bold text-success"><?php echo $studentStats['active_students'] ?? 0; ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        `;
              break;
            case 'teacher':
              content = `
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr><th>Metric</th><th>Value</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>Total Teachers</td><td class="fw-bold"><?php echo $teacherStats['total_teachers'] ?? 0; ?></td></tr>
                                        <tr><td>Active Teachers</td><td class="fw-bold text-success"><?php echo $teacherStats['active_teachers'] ?? 0; ?></td></tr>
                                        <tr><td>Male Teachers</td><td class="fw-bold"><?php echo $teacherStats['male_teachers'] ?? 0; ?></td></tr>
                                        <tr><td>Female Teachers</td><td class="fw-bold"><?php echo $teacherStats['female_teachers'] ?? 0; ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        `;
              break;
            case 'attendance':
              content = `
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr><th>Metric</th><th>Value</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>Total Records</td><td class="fw-bold"><?php echo $attendanceStats['total_attendance'] ?? 0; ?></td></tr>
                                        <tr><td>Present</td><td class="fw-bold text-success"><?php echo $attendanceStats['present'] ?? 0; ?></td></tr>
                                        <tr><td>Absent</td><td class="fw-bold text-danger"><?php echo $attendanceStats['absent'] ?? 0; ?></td></tr>
                                        <tr><td>Late</td><td class="fw-bold text-warning"><?php echo $attendanceStats['late'] ?? 0; ?></td></tr>
                                        <tr><td>Attendance Rate</td><td class="fw-bold text-primary">${attendanceRate}%</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        `;
              break;
            case 'fees':
              content = `
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr><th>Metric</th><th>Value</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>Total Invoices</td><td class="fw-bold"><?php echo $feesStats['total_invoices'] ?? 0; ?></td></tr>
                                        <tr><td>Total Fees</td><td class="fw-bold">$$<?php echo number_format($feesStats['total_fees'] ?? 0, 2); ?></td></tr>
                                        <tr><td>Total Paid</td><td class="fw-bold text-success">$$<?php echo number_format($feesStats['total_paid'] ?? 0, 2); ?></td></tr>
                                        <tr><td>Total Remaining</td><td class="fw-bold text-warning">$$<?php echo number_format($feesStats['total_remaining'] ?? 0, 2); ?></td></tr>
                                        <tr><td>Paid Invoices</td><td class="fw-bold text-success"><?php echo $feesStats['paid_invoices'] ?? 0; ?></td></tr>
                                        <tr><td>Unpaid Invoices</td><td class="fw-bold text-danger"><?php echo $feesStats['unpaid_invoices'] ?? 0; ?></td></tr>
                                        <tr><td>Overdue Invoices</td><td class="fw-bold text-danger"><?php echo $feesStats['overdue_invoices'] ?? 0; ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        `;
              break;
            case 'exam':
              content = `
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr><th>Metric</th><th>Value</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>Total Exams</td><td class="fw-bold"><?php echo $examStats['total_exams'] ?? 0; ?></td></tr>
                                        <tr><td>Scheduled</td><td class="fw-bold text-primary"><?php echo $examStats['scheduled'] ?? 0; ?></td></tr>
                                        <tr><td>Ongoing</td><td class="fw-bold text-warning"><?php echo $examStats['ongoing'] ?? 0; ?></td></tr>
                                        <tr><td>Completed</td><td class="fw-bold text-success"><?php echo $examStats['completed'] ?? 0; ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        `;
              break;
            case 'result':
              content = `
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr><th>Metric</th><th>Value</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>Total Results</td><td class="fw-bold"><?php echo $resultStats['total_results'] ?? 0; ?></td></tr>
                                        <tr><td>Passed</td><td class="fw-bold text-success"><?php echo $resultStats['passed'] ?? 0; ?></td></tr>
                                        <tr><td>Failed</td><td class="fw-bold text-danger"><?php echo $resultStats['failed'] ?? 0; ?></td></tr>
                                        <tr><td>Pass Rate</td><td class="fw-bold text-primary">${passRate}%</td></tr>
                                        <tr><td>Average Percentage</td><td class="fw-bold text-primary"><?php echo round($resultStats['avg_percentage'] ?? 0, 1); ?>%</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        `;
              break;
            case 'class':
              content = `
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered">
                                    <thead class="table-light">
                                        <tr><th>Metric</th><th>Value</th></tr>
                                    </thead>
                                    <tbody>
                                        <tr><td>Total Classes</td><td class="fw-bold"><?php echo $classStats['total_classes'] ?? 0; ?></td></tr>
                                        <tr><td>Active Classes</td><td class="fw-bold text-success"><?php echo $classStats['active_classes'] ?? 0; ?></td></tr>
                                        <tr><td>Total Students</td><td class="fw-bold"><?php echo $studentStats['total_students'] ?? 0; ?></td></tr>
                                        <tr><td>Total Teachers</td><td class="fw-bold"><?php echo $teacherStats['total_teachers'] ?? 0; ?></td></tr>
                                        <tr><td>Avg Students/Class</td><td class="fw-bold"><?php echo $classStats['total_classes'] > 0 ? round($studentStats['total_students'] / $classStats['total_classes'], 1) : 0; ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        `;
              break;
          }
          modalBody.innerHTML = content;
        });
      });
    });

    // Toast notification
    function showToast(title, message, type) {
      if (typeof type === 'undefined') type = 'success';
      var container = document.getElementById('toastContainer');
      if (!container) return;

      var toast = document.createElement('div');
      toast.className = 'toast align-items-center text-white bg-' + type + ' border-0';
      toast.setAttribute('role', 'alert');
      toast.setAttribute('aria-live', 'assertive');
      toast.setAttribute('aria-atomic', 'true');
      toast.innerHTML = '<div class="d-flex"><div class="toast-body"><strong>' + title + ':</strong> ' + message + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>';
      container.appendChild(toast);
      var bsToast = new bootstrap.Toast(toast, { delay: 3000 });
      bsToast.show();
      toast.addEventListener('hidden.bs.toast', function () { toast.remove(); });
    }
  </script>
</body>

</html>