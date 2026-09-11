<?php
// exams.php
require_once '../database/connect.php';

session_start();
$teacher_id = $_SESSION['teacher_id'] ?? 10; // Default teacher ID for demo

// Get teacher info
$teacher_stmt = $conn->prepare("
    SELECT full_name, email, qualification, photo 
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

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Create new exam
    if (isset($_POST['create_exam'])) {
        $exam_title = $_POST['exam_title'] ?? '';
        $subject_id = $_POST['subject_id'] ?? 0;
        $class_id = $_POST['class_id'] ?? 0;
        $exam_date = $_POST['exam_date'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $end_time = $_POST['end_time'] ?? '';
        $room = $_POST['room'] ?? '';
        $status = $_POST['status'] ?? 'Scheduled';
        
        if ($exam_title && $subject_id && $class_id && $exam_date && $start_time && $end_time) {
            $insert_stmt = $conn->prepare("
                INSERT INTO exams (exam_title, subject_id, class_id, exam_date, start_time, end_time, room, status)
                VALUES (:exam_title, :subject_id, :class_id, :exam_date, :start_time, :end_time, :room, :status)
            ");
            
            $insert_stmt->bindValue(':exam_title', $exam_title);
            $insert_stmt->bindValue(':subject_id', $subject_id);
            $insert_stmt->bindValue(':class_id', $class_id);
            $insert_stmt->bindValue(':exam_date', $exam_date);
            $insert_stmt->bindValue(':start_time', $start_time);
            $insert_stmt->bindValue(':end_time', $end_time);
            $insert_stmt->bindValue(':room', $room);
            $insert_stmt->bindValue(':status', $status);
            
            if ($insert_stmt->execute()) {
                $success_message = "Exam created successfully!";
            } else {
                $error_message = "Error creating exam.";
            }
        } else {
            $error_message = "Please fill in all required fields.";
        }
    }
    
    // Update exam status
    if (isset($_POST['update_status'])) {
        $exam_id = $_POST['exam_id'] ?? 0;
        $new_status = $_POST['new_status'] ?? '';
        
        if ($exam_id && $new_status) {
            $update_stmt = $conn->prepare("
                UPDATE exams 
                SET status = :status
                WHERE id = :id
            ");
            
            $update_stmt->bindValue(':status', $new_status);
            $update_stmt->bindValue(':id', $exam_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Exam status updated!";
            } else {
                $error_message = "Error updating exam status.";
            }
        }
    }
    
    // Delete exam
    if (isset($_POST['delete_exam'])) {
        $exam_id = $_POST['exam_id'] ?? 0;
        
        if ($exam_id) {
            $delete_stmt = $conn->prepare("
                DELETE FROM exams 
                WHERE id = :id
            ");
            
            $delete_stmt->bindValue(':id', $exam_id);
            
            if ($delete_stmt->execute()) {
                $success_message = "Exam deleted successfully!";
            } else {
                $error_message = "Error deleting exam.";
            }
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$subject_filter = $_GET['subject'] ?? '';
$class_filter = $_GET['class'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build exams query
$query = "
    SELECT 
        e.id,
        e.exam_title,
        e.exam_date,
        e.start_time,
        e.end_time,
        e.room,
        e.status as exam_status,
        e.created_at,
        s.id as subject_id,
        s.subject_title,
        s.subject_code,
        c.id as class_id,
        c.name as class_name,
        c.grade as class_grade,
        (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'Active') as total_students
    FROM exams e
    LEFT JOIN subjects s ON e.subject_id = s.id
    LEFT JOIN classes c ON e.class_id = c.id
    WHERE 1=1
";

$params = [];

// Apply teacher filter
$query .= " AND (s.teacher_id = :teacher_id OR c.teacher_id = :teacher_id)";
$params[':teacher_id'] = $teacher_id;

// Apply search filter
if (!empty($search)) {
    $query .= " AND (e.exam_title LIKE :search OR s.subject_title LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

// Apply subject filter
if (!empty($subject_filter)) {
    $query .= " AND e.subject_id = :subject_id";
    $params[':subject_id'] = $subject_filter;
}

// Apply class filter
if (!empty($class_filter)) {
    $query .= " AND e.class_id = :class_id";
    $params[':class_id'] = $class_filter;
}

// Apply status filter
if (!empty($status_filter)) {
    $query .= " AND e.status = :status";
    $params[':status'] = $status_filter;
}

$query .= " ORDER BY e.exam_date DESC, e.start_time ASC";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$exams = $stmt->fetchAll();

// Calculate statistics
$total_exams = count($exams);
$upcoming_exams = 0;
$ongoing_exams = 0;
$completed_exams = 0;
$scheduled_exams = 0;
$cancelled_exams = 0;

$today = date('Y-m-d');
$current_time = date('H:i:s');

foreach ($exams as $exam) {
    switch ($exam['exam_status']) {
        case 'Scheduled':
            $scheduled_exams++;
            if ($exam['exam_date'] >= $today) {
                $upcoming_exams++;
            }
            break;
        case 'Ongoing':
            $ongoing_exams++;
            break;
        case 'Completed':
            $completed_exams++;
            break;
        case 'Cancelled':
            $cancelled_exams++;
            break;
    }
}

// Get subjects for filter
$subject_stmt = $conn->prepare("
    SELECT DISTINCT s.id, s.subject_title, s.subject_code
    FROM subjects s
    LEFT JOIN exams e ON e.subject_id = s.id
    LEFT JOIN classes c ON e.class_id = c.id
    WHERE s.teacher_id = :teacher_id OR c.teacher_id = :teacher_id
    ORDER BY s.subject_title
");
$subject_stmt->bindValue(':teacher_id', $teacher_id);
$subject_stmt->execute();
$subjects = $subject_stmt->fetchAll();

// Get classes for filter
$class_stmt = $conn->prepare("
    SELECT DISTINCT c.id, c.name, c.grade
    FROM classes c
    LEFT JOIN exams e ON e.class_id = c.id
    WHERE c.teacher_id = :teacher_id
    ORDER BY c.name
");
$class_stmt->bindValue(':teacher_id', $teacher_id);
$class_stmt->execute();
$classes = $class_stmt->fetchAll();

// Get all subjects for dropdown (for creating exam)
$all_subjects_stmt = $conn->prepare("
    SELECT DISTINCT s.id, s.subject_title, s.subject_code
    FROM subjects s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE c.teacher_id = :teacher_id OR s.teacher_id = :teacher_id
    ORDER BY s.subject_title
");
$all_subjects_stmt->bindValue(':teacher_id', $teacher_id);
$all_subjects_stmt->execute();
$all_subjects = $all_subjects_stmt->fetchAll();

// Get all classes for dropdown (for creating exam)
$all_classes_stmt = $conn->prepare("
    SELECT DISTINCT c.id, c.name, c.grade
    FROM classes c
    WHERE c.teacher_id = :teacher_id AND c.status = 'active'
    ORDER BY c.name
");
$all_classes_stmt->bindValue(':teacher_id', $teacher_id);
$all_classes_stmt->execute();
$all_classes = $all_classes_stmt->fetchAll();

// Get status badge class
function getExamStatusBadgeClass($status) {
    switch($status) {
        case 'Scheduled': return 'bg-warning text-dark';
        case 'Ongoing': return 'bg-info text-white';
        case 'Completed': return 'bg-success';
        case 'Cancelled': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

// Get status icon
function getExamStatusIcon($status) {
    switch($status) {
        case 'Scheduled': return 'bi-calendar-event';
        case 'Ongoing': return 'bi-play-circle';
        case 'Completed': return 'bi-check-circle';
        case 'Cancelled': return 'bi-x-circle';
        default: return 'bi-circle';
    }
}

// Check if exam is upcoming
function isUpcoming($exam_date) {
    return $exam_date >= date('Y-m-d');
}

// Check if exam is today
function isToday($exam_date) {
    return $exam_date == date('Y-m-d');
}

// Format time
function formatTime($time) {
    return date('h:i A', strtotime($time));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Exams | Teacher Dashboard</title>
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

        /* Exam specific styles */
        .exam-title {
            font-weight: 600;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 4px 12px;
        }
        .exam-today {
            background: #fff3cd !important;
        }
        .exam-today .exam-title {
            color: #856404;
        }

        /* Gradient backgrounds */
        .bg-grad-1 { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .bg-grad-2 { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
        .bg-grad-3 { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .bg-grad-4 { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .bg-grad-5 { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
        .bg-grad-6 { background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%); }

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
            <a href="attendance.php"><i class="bi bi-calendar2-check"></i> Attendance</a>
            <a href="subjects.php"><i class="bi bi-journal-bookmark"></i> My Subjects</a>
            <a href="timetable.php"><i class="bi bi-clock-history"></i> My Timetable</a>
            <div class="td-nav-title">Academics</div>
            <a href="assignments.php"><i class="bi bi-file-earmark-text"></i> Assignments</a>
            <a href="exams.php" class="active"><i class="bi bi-pencil-square"></i> Exams</a>
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
            <h1 class="td-page-title">Exams <small>Exam schedule and management</small></h1>
            <div class="td-search ms-auto">
                <form method="GET" action="" class="d-flex">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="search" name="search" class="form-control border-start-0" placeholder="Search exams..." value="<?php echo htmlspecialchars($search); ?>">
                        <input type="hidden" name="subject" value="<?php echo htmlspecialchars($subject_filter); ?>">
                        <input type="hidden" name="class" value="<?php echo htmlspecialchars($class_filter); ?>">
                        <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
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

            <!-- Statistics Cards -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-md-4">
                    <div class="card stat-card">
                        <div class="stat-icon bg-grad-3"><i class="bi bi-calendar-event"></i></div>
                        <div>
                            <h3><?php echo $upcoming_exams; ?></h3>
                            <p>Upcoming Exams</p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card stat-card">
                        <div class="stat-icon bg-grad-2"><i class="bi bi-check2-circle"></i></div>
                        <div>
                            <h3><?php echo $completed_exams; ?></h3>
                            <p>Completed Exams</p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-4">
                    <div class="card stat-card">
                        <div class="stat-icon bg-grad-1"><i class="bi bi-clipboard-data"></i></div>
                        <div>
                            <h3><?php echo $total_exams; ?></h3>
                            <p>Total Exams</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="search" name="search" class="form-control" placeholder="Exam or subject..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Subject</label>
                            <select name="subject" class="form-select">
                                <option value="">All Subjects</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>" <?php echo $subject_filter == $subject['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject['subject_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Class</label>
                            <select name="class" class="form-select">
                                <option value="">All Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['name'] . ' - ' . $class['grade']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-grid">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Create Exam Button -->
            <div class="mb-3">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createExamModal">
                    <i class="bi bi-plus-lg"></i> Schedule Exam
                </button>
                <a href="results.php" class="btn btn-outline-secondary ms-2">
                    <i class="bi bi-bar-chart-line"></i> View Results
                </a>
            </div>

            <!-- Exam List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-pencil-square me-2 text-primary"></i>Exam Schedule</span>
                    <span class="badge bg-light text-dark">
                        <?php echo $total_exams; ?> exams
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Exam</th>
                                <th>Subject</th>
                                <th>Class</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Room</th>
                                <th>Students</th>
                                <th>Status</th>
                                <th style="width: 100px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($exams) > 0): ?>
                                <?php foreach ($exams as $exam): 
                                    $is_today = isToday($exam['exam_date']);
                                    $status_class = getExamStatusBadgeClass($exam['exam_status']);
                                    $status_icon = getExamStatusIcon($exam['exam_status']);
                                    $row_class = $is_today ? 'exam-today' : '';
                                ?>
                                    <tr class="<?php echo $row_class; ?>">
                                        <td>
                                            <div class="exam-title"><?php echo htmlspecialchars($exam['exam_title']); ?></div>
                                            <?php if ($is_today): ?>
                                                <span class="badge bg-danger">Today</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($exam['subject_title'] ?? 'N/A'); ?>
                                            <?php if ($exam['subject_code']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($exam['subject_code']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($exam['class_name'] ?? 'N/A'); ?>
                                            <?php if ($exam['class_grade']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($exam['class_grade']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($exam['exam_date'])); ?></td>
                                        <td>
                                            <?php echo formatTime($exam['start_time']); ?>
                                            <br><small class="text-muted">to <?php echo formatTime($exam['end_time']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($exam['room'] ?? '—'); ?></td>
                                        <td><?php echo $exam['total_students'] ?? 0; ?></td>
                                        <td>
                                            <span class="badge <?php echo $status_class; ?> status-badge">
                                                <i class="bi <?php echo $status_icon; ?> me-1"></i>
                                                <?php echo $exam['exam_status']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <?php if ($exam['exam_status'] != 'Completed' && $exam['exam_status'] != 'Cancelled'): ?>
                                                    <button class="btn btn-outline-success" onclick="markCompleted(<?php echo $exam['id']; ?>, '<?php echo addslashes($exam['exam_title']); ?>')">
                                                        <i class="bi bi-check2"></i>
                                                    </button>
                                                <?php endif; ?>
                                                <button class="btn btn-outline-primary" onclick="editExam(<?php echo $exam['id']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteExam(<?php echo $exam['id']; ?>, '<?php echo addslashes($exam['exam_title']); ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-4">
                                        <i class="bi bi-calendar2-x fs-1 d-block text-muted"></i>
                                        <p class="text-muted mb-0">No exams found</p>
                                        <?php if (empty($search) && empty($subject_filter) && empty($class_filter)): ?>
                                            <button class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#createExamModal">
                                                <i class="bi bi-plus-lg"></i> Schedule your first exam
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

<!-- Create Exam Modal -->
<div class="modal fade" id="createExamModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-plus me-2 text-primary"></i>Schedule New Exam</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Exam Title <span class="text-danger">*</span></label>
                            <input type="text" name="exam_title" class="form-control" placeholder="e.g., Mid-Term Exam" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subject <span class="text-danger">*</span></label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($all_subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_title'] . ' (' . $subject['subject_code'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Class <span class="text-danger">*</span></label>
                            <select name="class_id" class="form-select" required>
                                <option value="">Select Class</option>
                                <?php foreach ($all_classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($class['name'] . ' - ' . $class['grade']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Exam Date <span class="text-danger">*</span></label>
                            <input type="date" name="exam_date" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" class="form-control" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Time <span class="text-danger">*</span></label>
                            <input type="time" name="end_time" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Room</label>
                            <input type="text" name="room" class="form-control" placeholder="e.g., Hall A, Room 201">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Scheduled">Scheduled</option>
                                <option value="Ongoing">Ongoing</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_exam" class="btn btn-primary">
                        <i class="bi bi-calendar-plus"></i> Schedule Exam
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Exam Modal -->
<div class="modal fade" id="editExamModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Exam</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="exam_id" id="edit_exam_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Exam Title <span class="text-danger">*</span></label>
                            <input type="text" name="exam_title" id="edit_exam_title" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Subject</label>
                            <select name="subject_id" id="edit_subject_id" class="form-select">
                                <?php foreach ($all_subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Class</label>
                            <select name="class_id" id="edit_class_id" class="form-select">
                                <?php foreach ($all_classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>">
                                        <?php echo htmlspecialchars($class['name'] . ' - ' . $class['grade']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Exam Date</label>
                            <input type="date" name="exam_date" id="edit_exam_date" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Time</label>
                            <input type="time" name="start_time" id="edit_start_time" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Time</label>
                            <input type="time" name="end_time" id="edit_end_time" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Room</label>
                            <input type="text" name="room" id="edit_room" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="new_status" id="edit_status" class="form-select">
                                <option value="Scheduled">Scheduled</option>
                                <option value="Ongoing">Ongoing</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Update Exam
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteExamModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Exam</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="exam_id" id="delete_exam_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete the exam: <strong id="delete_exam_title"></strong>?</p>
                    <p class="text-danger small">This action cannot be undone. All associated data will be permanently removed.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_exam" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete Exam
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Mark as Completed Modal -->
<div class="modal fade" id="markCompletedModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-success"><i class="bi bi-check2-circle me-2"></i>Mark Exam as Completed</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="exam_id" id="mark_completed_id">
                <div class="modal-body">
                    <p>Mark <strong id="mark_completed_title"></strong> as completed?</p>
                    <p class="text-muted small">This will update the exam status to "Completed".</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-success">
                        <i class="bi bi-check2"></i> Mark as Completed
                    </button>
                    <input type="hidden" name="new_status" value="Completed">
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Edit exam - load data into modal
    function editExam(id) {
        // Fetch exam data via AJAX
        fetch('ajax/get_exam.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_exam_id').value = data.id;
                    document.getElementById('edit_exam_title').value = data.exam_title;
                    document.getElementById('edit_subject_id').value = data.subject_id;
                    document.getElementById('edit_class_id').value = data.class_id;
                    document.getElementById('edit_exam_date').value = data.exam_date;
                    document.getElementById('edit_start_time').value = data.start_time;
                    document.getElementById('edit_end_time').value = data.end_time;
                    document.getElementById('edit_room').value = data.room || '';
                    document.getElementById('edit_status').value = data.status;
                    
                    var modal = new bootstrap.Modal(document.getElementById('editExamModal'));
                    modal.show();
                } else {
                    alert('Error loading exam data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while loading the exam.');
            });
    }

    // Delete exam
    function deleteExam(id, title) {
        document.getElementById('delete_exam_id').value = id;
        document.getElementById('delete_exam_title').textContent = title;
        
        var modal = new bootstrap.Modal(document.getElementById('deleteExamModal'));
        modal.show();
    }

    // Mark exam as completed
    function markCompleted(id, title) {
        document.getElementById('mark_completed_id').value = id;
        document.getElementById('mark_completed_title').textContent = title;
        
        var modal = new bootstrap.Modal(document.getElementById('markCompletedModal'));
        modal.show();
    }

    // Auto-set date to next week when creating exam
    document.addEventListener('DOMContentLoaded', function() {
        const dateInput = document.querySelector('input[name="exam_date"]');
        if (dateInput) {
            const date = new Date();
            date.setDate(date.getDate() + 7);
            dateInput.value = date.toISOString().split('T')[0];
        }
        
        // Set default time
        const startTimeInput = document.querySelector('input[name="start_time"]');
        if (startTimeInput) {
            startTimeInput.value = '09:00';
        }
        const endTimeInput = document.querySelector('input[name="end_time"]');
        if (endTimeInput) {
            endTimeInput.value = '11:00';
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>