<?php
// attendance.php
require_once '../database/connect.php';

session_start();
$teacher_id = $_SESSION['teacher_id'] ?? 10; // Default teacher ID for demo

// Get filter parameters
$search_date = $_GET['date'] ?? date('Y-m-d');
$class_filter = $_GET['class'] ?? '';
$student_search = $_GET['search'] ?? '';

// Process form submission for marking attendance
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_attendance'])) {
    $attendance_date = $_POST['attendance_date'];
    $class_id = $_POST['class_id'];
    $statuses = $_POST['status'] ?? [];
    $remarks = $_POST['remarks'] ?? [];
    
    try {
        $conn->beginTransaction();
        
        // Get all students in this class
        $student_stmt = $conn->prepare("
            SELECT id FROM students 
            WHERE class_id = :class_id AND status = 'Active'
        ");
        $student_stmt->bindValue(':class_id', $class_id);
        $student_stmt->execute();
        $students = $student_stmt->fetchAll();
        
        foreach ($students as $student) {
            $student_id = $student['id'];
            $status = $statuses[$student_id] ?? 'Absent';
            $remark = $remarks[$student_id] ?? '';
            
            // Check if attendance already exists
            $check_stmt = $conn->prepare("
                SELECT id FROM attendance 
                WHERE student_id = :student_id AND attendance_date = :date
            ");
            $check_stmt->bindValue(':student_id', $student_id);
            $check_stmt->bindValue(':date', $attendance_date);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                // Update existing record
                $update_stmt = $conn->prepare("
                    UPDATE attendance 
                    SET status = :status, remarks = :remarks, check_in_time = NOW()
                    WHERE student_id = :student_id AND attendance_date = :date
                ");
                $update_stmt->bindValue(':status', $status);
                $update_stmt->bindValue(':remarks', $remark);
                $update_stmt->bindValue(':student_id', $student_id);
                $update_stmt->bindValue(':date', $attendance_date);
                $update_stmt->execute();
            } else {
                // Insert new record
                $insert_stmt = $conn->prepare("
                    INSERT INTO attendance (student_id, class_id, attendance_date, check_in_time, status, remarks)
                    VALUES (:student_id, :class_id, :date, NOW(), :status, :remarks)
                ");
                $insert_stmt->bindValue(':student_id', $student_id);
                $insert_stmt->bindValue(':class_id', $class_id);
                $insert_stmt->bindValue(':date', $attendance_date);
                $insert_stmt->bindValue(':status', $status);
                $insert_stmt->bindValue(':remarks', $remark);
                $insert_stmt->execute();
            }
        }
        
        $conn->commit();
        $success_message = "Attendance marked successfully for " . date('d M Y', strtotime($attendance_date));
        
        // Refresh the page to show updated data
        header("Location: attendance.php?date=" . $attendance_date . "&class=" . $class_id);
        exit();
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Error marking attendance: " . $e->getMessage();
    }
}

// Get classes for the teacher
$class_stmt = $conn->prepare("
    SELECT DISTINCT c.id, c.name, c.grade, c.room_no
    FROM classes c
    WHERE c.teacher_id = :teacher_id AND c.status = 'active'
    ORDER BY c.name
");
$class_stmt->bindValue(':teacher_id', $teacher_id);
$class_stmt->execute();
$classes = $class_stmt->fetchAll();

// Get students for the selected class (for marking attendance)
$selected_class_students = [];
if ($class_filter && $class_filter != '') {
    $student_stmt = $conn->prepare("
        SELECT s.id, s.first_name, s.last_name, s.student_uid
        FROM students s
        WHERE s.class_id = :class_id AND s.status = 'Active'
        ORDER BY s.first_name, s.last_name
    ");
    $student_stmt->bindValue(':class_id', $class_filter);
    $student_stmt->execute();
    $selected_class_students = $student_stmt->fetchAll();
    
    // Get existing attendance for these students on the selected date
    if (!empty($selected_class_students)) {
        $student_ids = array_column($selected_class_students, 'id');
        $placeholders = implode(',', array_fill(0, count($student_ids), '?'));
        
        $attendance_stmt = $conn->prepare("
            SELECT student_id, status, remarks, check_in_time
            FROM attendance
            WHERE student_id IN ($placeholders) AND attendance_date = ?
        ");
        $params = array_merge($student_ids, [$search_date]);
        $attendance_stmt->execute($params);
        $existing_attendance = [];
        while ($row = $attendance_stmt->fetch()) {
            $existing_attendance[$row['student_id']] = $row;
        }
        
        // Merge attendance data with students
        foreach ($selected_class_students as &$student) {
            if (isset($existing_attendance[$student['id']])) {
                $student['attendance_status'] = $existing_attendance[$student['id']]['status'];
                $student['attendance_remarks'] = $existing_attendance[$student['id']]['remarks'];
                $student['check_in_time'] = $existing_attendance[$student['id']]['check_in_time'];
            } else {
                $student['attendance_status'] = 'Not Marked';
                $student['attendance_remarks'] = '';
                $student['check_in_time'] = null;
            }
        }
    }
}

// Build main attendance query with filters
$query = "
    SELECT 
        a.id,
        a.student_id,
        a.attendance_date,
        a.status,
        a.remarks,
        a.check_in_time,
        s.first_name,
        s.last_name,
        s.student_uid,
        c.id as class_id,
        c.name as class_name,
        c.grade as class_grade
    FROM attendance a
    LEFT JOIN students s ON a.student_id = s.id
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE 1=1
";

$params = [];

// Filter by teacher's classes
$query .= " AND c.teacher_id = :teacher_id";
$params[':teacher_id'] = $teacher_id;

// Date filter
if (!empty($search_date)) {
    $query .= " AND a.attendance_date = :date";
    $params[':date'] = $search_date;
}

// Class filter
if (!empty($class_filter)) {
    $query .= " AND c.id = :class_id";
    $params[':class_id'] = $class_filter;
}

// Student search
if (!empty($student_search)) {
    $query .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.student_uid LIKE :search)";
    $params[':search'] = '%' . $student_search . '%';
}

$query .= " ORDER BY a.attendance_date DESC, s.first_name ASC";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$attendance_records = $stmt->fetchAll();

// Calculate statistics from the filtered records
$total_present = 0;
$total_absent = 0;
$total_late = 0;
$total_records = count($attendance_records);

foreach ($attendance_records as $record) {
    switch ($record['status']) {
        case 'Present':
            $total_present++;
            break;
        case 'Absent':
            $total_absent++;
            break;
        case 'Late':
            $total_late++;
            break;
    }
}

// Calculate attendance percentage
$attendance_percentage = $total_records > 0 ? round((($total_present + $total_late) / $total_records) * 100) : 0;

// Get teacher info
$teacher_stmt = $conn->prepare("
    SELECT full_name, email, qualification 
    FROM teachers 
    WHERE user_id = :user_id
");
$teacher_stmt->bindValue(':user_id', $teacher_id);
$teacher_stmt->execute();
$teacher = $teacher_stmt->fetch();
$teacher_name = $teacher['full_name'] ?? 'Mr. Ahmed';
$teacher_initials = implode('', array_map(function($word) {
    return strtoupper(substr($word, 0, 1));
}, explode(' ', $teacher_name)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Attendance | Teacher Dashboard</title>
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
            border-bottom: 1px solid rgba(255,255,255,0.1);
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
            border-bottom: 1px solid rgba(255,255,255,0.1);
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
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            transition: all 0.3s;
            border-left: 3px solid transparent;
        }
        .td-nav a:hover {
            background: rgba(255,255,255,0.05);
            color: white;
        }
        .td-nav a.active {
            background: rgba(52,152,219,0.2);
            color: white;
            border-left-color: #3498db;
        }
        .td-nav a.logout {
            border-top: 1px solid rgba(255,255,255,0.1);
            margin-top: 10px;
            color: #e74c3c;
        }
        .td-nav a.logout:hover {
            background: rgba(231,76,60,0.1);
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
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
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
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
        .bg-grad-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .bg-grad-2 { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .bg-grad-3 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .bg-grad-4 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .bg-grad-5 { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }

        /* Overlay for mobile */
        .td-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 999;
        }
        #tdSidebarToggle {
            display: none;
        }
        #tdSidebarToggle:checked ~ .td-overlay {
            display: block;
        }
        #tdSidebarToggle:checked ~ .td-sidebar {
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
            #tdSidebarToggle:checked ~ .td-sidebar {
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

        /* Attendance specific styles */
        .student-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: white;
            font-size: 12px;
            flex-shrink: 0;
        }
        .status-badge {
            font-size: 0.85rem;
            padding: 5px 12px;
        }
        .modal-body {
            max-height: 70vh;
            overflow-y: auto;
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
            <div class="td-avatar"><?php echo $teacher_initials; ?></div>
            <div>
                <h6><?php echo htmlspecialchars($teacher_name); ?></h6>
                <p>Mathematics Teacher</p>
            </div>
        </div>
        <nav class="td-nav">
            <div class="td-nav-title">Main</div>
            <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
            <a href="students.php"><i class="bi bi-people"></i> My Students</a>
            <a href="attendance.php" class="active"><i class="bi bi-calendar2-check"></i> Attendance</a>
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
            <h1 class="td-page-title">Attendance <small>Daily class attendance records</small></h1>
            <div class="td-search ms-auto">
                <form method="GET" action="" class="d-flex">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="search" name="search" class="form-control border-start-0" placeholder="Search..." value="<?php echo htmlspecialchars($student_search); ?>">
                        <input type="hidden" name="date" value="<?php echo htmlspecialchars($search_date); ?>">
                        <input type="hidden" name="class" value="<?php echo htmlspecialchars($class_filter); ?>">
                    </div>
                </form>
            </div>
            <a href="notices.php" class="td-icon-btn"><i class="bi bi-bell"></i><span class="td-dot"></span></a>
            <a href="profile.php" class="d-flex align-items-center gap-2 text-dark text-decoration-none">
                <span class="td-avatar"><?php echo $teacher_initials; ?></span>
                <span class="d-none d-md-block">
                    <strong class="d-block" style="font-size:.85rem"><?php echo htmlspecialchars($teacher_name); ?></strong>
                    <small class="text-muted" style="font-size:.72rem">Mathematics Teacher</small>
                </span>
            </a>
        </header>

        <!-- Content -->
        <main class="td-content">
            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="stat-icon bg-grad-2"><i class="bi bi-check2-circle"></i></div>
                        <div>
                            <h3><?php echo $total_present; ?></h3>
                            <p>Present</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="stat-icon bg-grad-5"><i class="bi bi-x-circle"></i></div>
                        <div>
                            <h3><?php echo $total_absent; ?></h3>
                            <p>Absent</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="stat-icon bg-grad-3"><i class="bi bi-clock"></i></div>
                        <div>
                            <h3><?php echo $total_late; ?></h3>
                            <p>Late</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="stat-icon bg-grad-1"><i class="bi bi-percent"></i></div>
                        <div>
                            <h3><?php echo $attendance_percentage; ?>%</h3>
                            <p>Attendance Percentage</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle me-2"></i> <?php echo $success_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $error_message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Date</label>
                            <input type="date" name="date" class="form-control" value="<?php echo htmlspecialchars($search_date); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Class</label>
                            <select name="class" class="form-select">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['name'] . ' - ' . $class['grade']); ?>
                                        <?php if ($class['room_no']): ?>
                                            (Room: <?php echo htmlspecialchars($class['room_no']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Search Student</label>
                            <input type="search" name="search" class="form-control" placeholder="Name or student ID" value="<?php echo htmlspecialchars($student_search); ?>">
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Mark Attendance Button -->
            <div class="mb-3">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#markAttendanceModal">
                    <i class="bi bi-plus-circle"></i> Mark Attendance
                </button>
            </div>

            <!-- Attendance Records -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-calendar2-check me-2 text-primary"></i>Attendance Records</span>
                    <div>
                        <span class="badge bg-light text-dark me-2">
                            Total: <?php echo $total_records; ?>
                        </span>
                        <?php if ($search_date): ?>
                            <span class="badge bg-info text-white">
                                <?php echo date('d M Y', strtotime($search_date)); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Student</th>
                                <th>Class</th>
                                <th>Date</th>
                                <th>Check-in Time</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($attendance_records) > 0): ?>
                                <?php 
                                $avatar_colors = ['#667eea', '#f093fb', '#4facfe', '#43e97b', '#fa709a', '#f5576c', '#764ba2', '#00f2fe', '#38f9d7', '#fee140'];
                                $idx = 0;
                                ?>
                                <?php foreach ($attendance_records as $record): 
                                    $status_class = '';
                                    $status_icon = '';
                                    switch ($record['status']) {
                                        case 'Present':
                                            $status_class = 'bg-success';
                                            $status_icon = 'bi-check-circle';
                                            break;
                                        case 'Absent':
                                            $status_class = 'bg-danger';
                                            $status_icon = 'bi-x-circle';
                                            break;
                                        case 'Late':
                                            $status_class = 'bg-warning text-dark';
                                            $status_icon = 'bi-clock';
                                            break;
                                        default:
                                            $status_class = 'bg-secondary';
                                            $status_icon = 'bi-question-circle';
                                    }
                                    $avatar_color = $avatar_colors[$idx % count($avatar_colors)];
                                    $idx++;
                                    $full_name = trim(($record['first_name'] ?? '') . ' ' . ($record['last_name'] ?? ''));
                                    if (empty($full_name)) $full_name = 'Unknown Student';
                                    $initials = strtoupper(substr($record['first_name'] ?? '', 0, 1) . substr($record['last_name'] ?? '', 0, 1));
                                    if (empty($initials)) $initials = '?';
                                ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="student-avatar" style="background: <?php echo $avatar_color; ?>;">
                                                    <?php echo $initials; ?>
                                                </div>
                                                <div>
                                                    <div class="fw-semibold"><?php echo htmlspecialchars($full_name); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($record['student_uid'] ?? 'N/A'); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($record['class_name']): ?>
                                                <div><?php echo htmlspecialchars($record['class_name']); ?></div>
                                                <small class="text-muted">Grade: <?php echo htmlspecialchars($record['class_grade'] ?? 'N/A'); ?></small>
                                            <?php else: ?>
                                                <span class="text-muted">Not Assigned</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $record['attendance_date'] ? date('d M Y', strtotime($record['attendance_date'])) : 'N/A'; ?></td>
                                        <td><?php echo $record['check_in_time'] ? date('h:i A', strtotime($record['check_in_time'])) : '—'; ?></td>
                                        <td>
                                            <span class="badge <?php echo $status_class; ?> status-badge">
                                                <i class="bi <?php echo $status_icon; ?> me-1"></i>
                                                <?php echo $record['status'] ?? 'Unknown'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['remarks'] ?? '—'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-4">
                                        <i class="bi bi-calendar2-x fs-1 d-block text-muted"></i>
                                        <p class="text-muted mb-0">No attendance records found for the selected criteria</p>
                                        <?php if (!empty($classes)): ?>
                                            <button class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#markAttendanceModal">
                                                <i class="bi bi-plus-circle"></i> Mark Attendance Now
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="td-footer">© 2026 Bright Future School — Teacher Panel.</footer>
    </div>
</div>

<!-- Mark Attendance Modal -->
<div class="modal fade" id="markAttendanceModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-check2-square me-2 text-primary"></i>Mark Attendance</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Date</label>
                            <input type="date" name="attendance_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Select Class</label>
                            <select name="class_id" class="form-select" id="classSelect" required>
                                <option value="">Choose a class...</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['name'] . ' - ' . $class['grade']); ?>
                                        <?php if ($class['room_no']): ?>
                                            (Room: <?php echo htmlspecialchars($class['room_no']); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div id="studentListContainer">
                        <?php if (!empty($selected_class_students)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i>
                                Showing <?php echo count($selected_class_students); ?> active students in this class.
                                <small class="d-block text-muted mt-1">
                                    <?php if ($search_date): ?>
                                        Date: <?php echo date('d M Y', strtotime($search_date)); ?>
                                    <?php endif; ?>
                                </small>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Student</th>
                                            <th>Status</th>
                                            <th>Remarks</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($selected_class_students as $index => $student): 
                                            $current_status = $student['attendance_status'] ?? 'Not Marked';
                                            $current_remarks = $student['attendance_remarks'] ?? '';
                                        ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($student['student_uid']); ?></small>
                                                </td>
                                                <td>
                                                    <input type="hidden" name="status[<?php echo $student['id']; ?>]" id="status_<?php echo $student['id']; ?>" value="<?php echo $current_status != 'Not Marked' ? $current_status : ''; ?>">
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" class="btn btn-outline-success <?php echo $current_status == 'Present' ? 'active' : ''; ?>" 
                                                                onclick="selectStatus(this, 'Present', <?php echo $student['id']; ?>)">
                                                            <i class="bi bi-check"></i> Present
                                                        </button>
                                                        <button type="button" class="btn btn-outline-danger <?php echo $current_status == 'Absent' ? 'active' : ''; ?>" 
                                                                onclick="selectStatus(this, 'Absent', <?php echo $student['id']; ?>)">
                                                            <i class="bi bi-x"></i> Absent
                                                        </button>
                                                        <button type="button" class="btn btn-outline-warning <?php echo $current_status == 'Late' ? 'active' : ''; ?>" 
                                                                onclick="selectStatus(this, 'Late', <?php echo $student['id']; ?>)">
                                                            <i class="bi bi-clock"></i> Late
                                                        </button>
                                                    </div>
                                                </td>
                                                <td>
                                                    <input type="text" name="remarks[<?php echo $student['id']; ?>]" class="form-control form-control-sm" 
                                                           placeholder="Optional remark" value="<?php echo htmlspecialchars($current_remarks); ?>">
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-4">
                                <i class="bi bi-people fs-1 d-block text-muted"></i>
                                <p class="text-muted">Select a class to view students and mark attendance</p>
                                <?php if (empty($classes)): ?>
                                    <p class="text-danger small">No classes assigned to you. Please contact administrator.</p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="mark_attendance" class="btn btn-primary" <?php echo empty($selected_class_students) ? 'disabled' : ''; ?>>
                        <i class="bi bi-check2-all"></i> Save Attendance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Function to select attendance status
    function selectStatus(btn, status, studentId) {
        const btnGroup = btn.closest('.btn-group');
        btnGroup.querySelectorAll('.btn').forEach(b => {
            b.classList.remove('active');
        });
        btn.classList.add('active');
        
        const hiddenInput = document.getElementById('status_' + studentId);
        if (hiddenInput) {
            hiddenInput.value = status;
        }
    }

    // Load students when class is selected in modal
    document.getElementById('classSelect')?.addEventListener('change', function() {
        const classId = this.value;
        if (classId) {
            const dateInput = document.querySelector('input[name="attendance_date"]');
            const date = dateInput ? dateInput.value : '<?php echo date('Y-m-d'); ?>';
            
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('class', classId);
            currentUrl.searchParams.set('date', date);
            window.location.href = currentUrl.toString();
        }
    });

    // Auto-open modal if class is selected
    document.addEventListener('DOMContentLoaded', function() {
        const recordsCount = <?php echo $total_records; ?>;
        const hasClasses = <?php echo count($classes) > 0 ? 'true' : 'false'; ?>;
        
        if (recordsCount === 0 && hasClasses) {
            // Show a subtle hint to mark attendance
            const alertHtml = `
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <i class="bi bi-info-circle me-2"></i>
                    No attendance records found. Click the <strong>"Mark Attendance"</strong> button to record attendance.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            const statsRow = document.querySelector('.row.g-3.mb-4');
            if (statsRow) {
                statsRow.insertAdjacentHTML('afterend', alertHtml);
            }
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>