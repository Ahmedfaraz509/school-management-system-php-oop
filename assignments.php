<?php
// assignments.php
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
    // Create new assignment
    if (isset($_POST['create_assignment'])) {
        $title = $_POST['title'] ?? '';
        $description = $_POST['description'] ?? '';
        $subject_id = $_POST['subject_id'] ?? 0;
        $class_id = $_POST['class_id'] ?? 0;
        $section_id = $_POST['section_id'] ?? null;
        $due_date = $_POST['due_date'] ?? '';
        $assigned_date = date('Y-m-d');
        $status = $_POST['status'] ?? 'active';
        $attachment = $_POST['attachment'] ?? null;
        
        if ($title && $subject_id && $class_id && $due_date) {
            $insert_stmt = $conn->prepare("
                INSERT INTO assignments (teacher_id, subject_id, class_id, section_id, title, description, assigned_date, due_date, attachment, status)
                VALUES (:teacher_id, :subject_id, :class_id, :section_id, :title, :description, :assigned_date, :due_date, :attachment, :status)
            ");
            
            $insert_stmt->bindValue(':teacher_id', $teacher_id);
            $insert_stmt->bindValue(':subject_id', $subject_id);
            $insert_stmt->bindValue(':class_id', $class_id);
            $insert_stmt->bindValue(':section_id', $section_id);
            $insert_stmt->bindValue(':title', $title);
            $insert_stmt->bindValue(':description', $description);
            $insert_stmt->bindValue(':assigned_date', $assigned_date);
            $insert_stmt->bindValue(':due_date', $due_date);
            $insert_stmt->bindValue(':attachment', $attachment);
            $insert_stmt->bindValue(':status', $status);
            
            if ($insert_stmt->execute()) {
                $success_message = "Assignment created successfully!";
            } else {
                $error_message = "Error creating assignment.";
            }
        } else {
            $error_message = "Please fill in all required fields.";
        }
    }
    
    // Update assignment status
    if (isset($_POST['update_status'])) {
        $assignment_id = $_POST['assignment_id'] ?? 0;
        $new_status = $_POST['new_status'] ?? '';
        
        if ($assignment_id && $new_status) {
            $update_stmt = $conn->prepare("
                UPDATE assignments 
                SET status = :status, updated_at = NOW()
                WHERE id = :id AND teacher_id = :teacher_id
            ");
            
            $update_stmt->bindValue(':status', $new_status);
            $update_stmt->bindValue(':id', $assignment_id);
            $update_stmt->bindValue(':teacher_id', $teacher_id);
            
            if ($update_stmt->execute()) {
                $success_message = "Assignment status updated!";
            } else {
                $error_message = "Error updating assignment status.";
            }
        }
    }
    
    // Delete assignment
    if (isset($_POST['delete_assignment'])) {
        $assignment_id = $_POST['assignment_id'] ?? 0;
        
        if ($assignment_id) {
            $delete_stmt = $conn->prepare("
                DELETE FROM assignments 
                WHERE id = :id AND teacher_id = :teacher_id
            ");
            
            $delete_stmt->bindValue(':id', $assignment_id);
            $delete_stmt->bindValue(':teacher_id', $teacher_id);
            
            if ($delete_stmt->execute()) {
                $success_message = "Assignment deleted successfully!";
            } else {
                $error_message = "Error deleting assignment.";
            }
        }
    }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$subject_filter = $_GET['subject'] ?? '';
$class_filter = $_GET['class'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build assignments query
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
        s.id as subject_id,
        s.subject_title,
        s.subject_code,
        c.id as class_id,
        c.name as class_name,
        c.grade as class_grade,
        (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'Active') as total_students
    FROM assignments a
    LEFT JOIN subjects s ON a.subject_id = s.id
    LEFT JOIN classes c ON a.class_id = c.id
    WHERE a.teacher_id = :teacher_id
";

$params = [':teacher_id' => $teacher_id];

// Apply filters
if (!empty($search)) {
    $query .= " AND (a.title LIKE :search OR a.description LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($subject_filter)) {
    $query .= " AND a.subject_id = :subject_id";
    $params[':subject_id'] = $subject_filter;
}

if (!empty($class_filter)) {
    $query .= " AND a.class_id = :class_id";
    $params[':class_id'] = $class_filter;
}

if (!empty($status_filter)) {
    $query .= " AND a.status = :status";
    $params[':status'] = $status_filter;
}

$query .= " ORDER BY a.due_date ASC, a.created_at DESC";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$assignments = $stmt->fetchAll();

// Calculate statistics
$total_assignments = count($assignments);
$active_assignments = 0;
$completed_assignments = 0;
$overdue_assignments = 0;
$draft_assignments = 0;
$pending_submissions = 0;

$today = date('Y-m-d');

foreach ($assignments as $assignment) {
    switch ($assignment['assignment_status']) {
        case 'active':
            $active_assignments++;
            break;
        case 'completed':
            $completed_assignments++;
            break;
        case 'draft':
            $draft_assignments++;
            break;
    }
    
    // Check if overdue
    if ($assignment['assignment_status'] == 'active' && $assignment['due_date'] < $today) {
        $overdue_assignments++;
    }
}

// Get subjects for filter
$subject_stmt = $conn->prepare("
    SELECT DISTINCT s.id, s.subject_title, s.subject_code
    FROM subjects s
    LEFT JOIN assignments a ON a.subject_id = s.id
    WHERE a.teacher_id = :teacher_id
    ORDER BY s.subject_title
");
$subject_stmt->bindValue(':teacher_id', $teacher_id);
$subject_stmt->execute();
$subjects = $subject_stmt->fetchAll();

// Get classes for filter
$class_stmt = $conn->prepare("
    SELECT DISTINCT c.id, c.name, c.grade
    FROM classes c
    LEFT JOIN assignments a ON a.class_id = c.id
    WHERE a.teacher_id = :teacher_id
    ORDER BY c.name
");
$class_stmt->bindValue(':teacher_id', $teacher_id);
$class_stmt->execute();
$classes = $class_stmt->fetchAll();

// Get all subjects for dropdown (for creating assignment)
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

// Get all classes for dropdown (for creating assignment)
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
function getStatusBadgeClass($status) {
    switch($status) {
        case 'active': return 'bg-info text-dark';
        case 'completed': return 'bg-success';
        case 'draft': return 'bg-secondary';
        case 'overdue': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

// Get status icon
function getStatusIcon($status) {
    switch($status) {
        case 'active': return 'bi-play-circle';
        case 'completed': return 'bi-check-circle';
        case 'draft': return 'bi-pencil-square';
        case 'overdue': return 'bi-exclamation-circle';
        default: return 'bi-circle';
    }
}

// Check if assignment is overdue
function isOverdue($due_date, $status) {
    if ($status == 'completed' || $status == 'draft') {
        return false;
    }
    return $due_date < date('Y-m-d');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Assignments | Teacher Dashboard</title>
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

        /* Assignment specific styles */
        .assignment-title {
            font-weight: 600;
            font-size: 0.95rem;
        }
        .assignment-description {
            font-size: 0.8rem;
            color: #6c757d;
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 4px 12px;
        }
        .due-date-urgent {
            color: #dc3545;
            font-weight: 600;
        }
        .due-date-warning {
            color: #ffc107;
            font-weight: 600;
        }

        /* Modal styles */
        .modal-body {
            max-height: 70vh;
            overflow-y: auto;
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
            <a href="assignments.php" class="active"><i class="bi bi-file-earmark-text"></i> Assignments</a>
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
            <h1 class="td-page-title">Assignments <small>Manage class assignments</small></h1>
            <div class="td-search ms-auto">
                <form method="GET" action="" class="d-flex">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="search" name="search" class="form-control border-start-0" placeholder="Search assignments..." value="<?php echo htmlspecialchars($search); ?>">
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
                <div class="col-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="stat-icon bg-grad-1"><i class="bi bi-files"></i></div>
                        <div>
                            <h3><?php echo $total_assignments; ?></h3>
                            <p>Total Assignments</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="stat-icon bg-grad-6"><i class="bi bi-lightning-charge"></i></div>
                        <div>
                            <h3><?php echo $active_assignments; ?></h3>
                            <p>Active Assignments</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="stat-icon bg-grad-2"><i class="bi bi-check2-all"></i></div>
                        <div>
                            <h3><?php echo $completed_assignments; ?></h3>
                            <p>Completed Assignments</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="stat-icon bg-grad-5"><i class="bi bi-hourglass-split"></i></div>
                        <div>
                            <h3><?php echo $overdue_assignments; ?></h3>
                            <p>Overdue Assignments</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Search Assignment</label>
                            <input type="search" name="search" class="form-control" placeholder="Assignment title" value="<?php echo htmlspecialchars($search); ?>">
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
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Create Assignment Button -->
            <div class="mb-3">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAssignmentModal">
                    <i class="bi bi-plus-lg"></i> New Assignment
                </button>
            </div>

            <!-- Assignment List -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-file-earmark-text me-2 text-primary"></i>Assignment List</span>
                    <span class="badge bg-light text-dark">
                        <?php echo $total_assignments; ?> assignments
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Assignment</th>
                                <th>Subject</th>
                                <th>Class</th>
                                <th>Assigned Date</th>
                                <th>Due Date</th>
                                <th>Students</th>
                                <th>Status</th>
                                <th style="width: 120px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($assignments) > 0): ?>
                                <?php foreach ($assignments as $assignment): 
                                    $is_overdue = isOverdue($assignment['due_date'], $assignment['assignment_status']);
                                    $status_display = $is_overdue ? 'overdue' : $assignment['assignment_status'];
                                    $status_class = getStatusBadgeClass($status_display);
                                    $status_icon = getStatusIcon($status_display);
                                    $days_until_due = ceil((strtotime($assignment['due_date']) - strtotime($today)) / (60 * 60 * 24));
                                    $due_date_class = '';
                                    if ($days_until_due < 0 && $assignment['assignment_status'] != 'completed') {
                                        $due_date_class = 'due-date-urgent';
                                    } elseif ($days_until_due <= 3 && $assignment['assignment_status'] != 'completed') {
                                        $due_date_class = 'due-date-warning';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <div class="assignment-title"><?php echo htmlspecialchars($assignment['title']); ?></div>
                                            <?php if ($assignment['description']): ?>
                                                <div class="assignment-description"><?php echo htmlspecialchars($assignment['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($assignment['subject_title'] ?? 'N/A'); ?></td>
                                        <td>
                                            <?php echo htmlspecialchars($assignment['class_name'] ?? 'N/A'); ?>
                                            <?php if ($assignment['class_grade']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($assignment['class_grade']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($assignment['assigned_date'])); ?></td>
                                        <td class="<?php echo $due_date_class; ?>">
                                            <?php echo date('d M Y', strtotime($assignment['due_date'])); ?>
                                            <?php if ($is_overdue): ?>
                                                <span class="badge bg-danger ms-1">Overdue</span>
                                            <?php elseif ($days_until_due <= 3 && $assignment['assignment_status'] == 'active'): ?>
                                                <span class="badge bg-warning text-dark ms-1"><?php echo $days_until_due; ?> days left</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo $assignment['total_students'] ?? 0; ?></td>
                                        <td>
                                            <span class="badge <?php echo $status_class; ?> status-badge">
                                                <i class="bi <?php echo $status_icon; ?> me-1"></i>
                                                <?php echo ucfirst($status_display); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="viewAssignment(<?php echo $assignment['id']; ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-outline-secondary" onclick="editAssignment(<?php echo $assignment['id']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteAssignment(<?php echo $assignment['id']; ?>, '<?php echo addslashes($assignment['title']); ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-4">
                                        <i class="bi bi-file-earmark-x fs-1 d-block text-muted"></i>
                                        <p class="text-muted mb-0">No assignments found</p>
                                        <?php if (empty($search) && empty($subject_filter) && empty($class_filter) && empty($status_filter)): ?>
                                            <button class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#createAssignmentModal">
                                                <i class="bi bi-plus-lg"></i> Create your first assignment
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

<!-- Create Assignment Modal -->
<div class="modal fade" id="createAssignmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-file-earmark-plus me-2 text-primary"></i>Create New Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Assignment Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" class="form-control" placeholder="Enter assignment title" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Enter assignment description"></textarea>
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
                        <div class="col-md-6">
                            <label class="form-label">Due Date <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="active">Active</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Attachment URL (optional)</label>
                            <input type="text" name="attachment" class="form-control" placeholder="https://example.com/file.pdf">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_assignment" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Create Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Assignment Modal -->
<div class="modal fade" id="editAssignmentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="assignment_id" id="edit_assignment_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Assignment Title <span class="text-danger">*</span></label>
                            <input type="text" name="title" id="edit_title" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="new_status" id="edit_status" class="form-select">
                                <option value="active">Active</option>
                                <option value="completed">Completed</option>
                                <option value="draft">Draft</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Due Date <span class="text-danger">*</span></label>
                            <input type="date" name="due_date" id="edit_due_date" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_status" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Update Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteAssignmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Assignment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="assignment_id" id="delete_assignment_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete the assignment: <strong id="delete_assignment_title"></strong>?</p>
                    <p class="text-danger small">This action cannot be undone. All associated data will be permanently removed.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_assignment" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete Assignment
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // View assignment (placeholder - you can implement a detailed view)
    function viewAssignment(id) {
        window.location.href = 'view_assignment.php?id=' + id;
    }

    // Edit assignment - load data into modal
    function editAssignment(id) {
        // Fetch assignment data via AJAX
        fetch('ajax/get_assignment.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_assignment_id').value = data.id;
                    document.getElementById('edit_title').value = data.title;
                    document.getElementById('edit_description').value = data.description || '';
                    document.getElementById('edit_status').value = data.status;
                    document.getElementById('edit_due_date').value = data.due_date;
                    
                    // Show the modal
                    var modal = new bootstrap.Modal(document.getElementById('editAssignmentModal'));
                    modal.show();
                } else {
                    alert('Error loading assignment data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while loading the assignment.');
            });
    }

    // Delete assignment
    function deleteAssignment(id, title) {
        document.getElementById('delete_assignment_id').value = id;
        document.getElementById('delete_assignment_title').textContent = title;
        
        var modal = new bootstrap.Modal(document.getElementById('deleteAssignmentModal'));
        modal.show();
    }

    // Auto-set due date to 7 days from now when creating assignment
    document.addEventListener('DOMContentLoaded', function() {
        const dueDateInput = document.querySelector('input[name="due_date"]');
        if (dueDateInput) {
            const date = new Date();
            date.setDate(date.getDate() + 7);
            dueDateInput.value = date.toISOString().split('T')[0];
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>