<?php
// results.php
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

// Get filter parameters
$class_filter = $_GET['class'] ?? '';
$subject_filter = $_GET['subject'] ?? '';
$exam_filter = $_GET['exam'] ?? '';

// Get classes for filter
$class_stmt = $conn->prepare("
    SELECT DISTINCT c.id, c.name, c.grade
    FROM classes c
    WHERE c.teacher_id = :teacher_id AND c.status = 'active'
    ORDER BY c.name
");
$class_stmt->bindValue(':teacher_id', $teacher_id);
$class_stmt->execute();
$classes = $class_stmt->fetchAll();

// Get subjects for filter
$subject_stmt = $conn->prepare("
    SELECT DISTINCT s.id, s.subject_title, s.subject_code
    FROM subjects s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE c.teacher_id = :teacher_id OR s.teacher_id = :teacher_id
    ORDER BY s.subject_title
");
$subject_stmt->bindValue(':teacher_id', $teacher_id);
$subject_stmt->execute();
$subjects = $subject_stmt->fetchAll();

// Get exams for filter (only exams that have results)
$exam_stmt = $conn->prepare("
    SELECT DISTINCT e.id, e.exam_title, e.exam_date
    FROM exams e
    LEFT JOIN results r ON e.id = r.exam_id
    LEFT JOIN subjects s ON e.subject_id = s.id
    LEFT JOIN classes c ON e.class_id = c.id
    WHERE (s.teacher_id = :teacher_id OR c.teacher_id = :teacher_id)
    ORDER BY e.exam_date DESC
");
$exam_stmt->bindValue(':teacher_id', $teacher_id);
$exam_stmt->execute();
$exams = $exam_stmt->fetchAll();

// Build results query
$query = "
    SELECT 
        r.id,
        r.student_id,
        r.exam_id,
        r.subject_id,
        r.marks_obtained,
        r.total_marks,
        r.percentage,
        r.grade,
        r.result,
        r.created_at,
        s.first_name,
        s.last_name,
        s.student_uid,
        s.gender,
        sub.subject_title,
        sub.subject_code,
        c.name as class_name,
        c.grade as class_grade,
        e.exam_title,
        e.exam_date
    FROM results r
    LEFT JOIN students s ON r.student_id = s.id
    LEFT JOIN subjects sub ON r.subject_id = sub.id
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN exams e ON r.exam_id = e.id
    WHERE 1=1
";

$params = [];

// Apply teacher filter
$query .= " AND (sub.teacher_id = :teacher_id OR c.teacher_id = :teacher_id)";
$params[':teacher_id'] = $teacher_id;

// Apply class filter
if (!empty($class_filter)) {
    $query .= " AND c.id = :class_id";
    $params[':class_id'] = $class_filter;
}

// Apply subject filter
if (!empty($subject_filter)) {
    $query .= " AND sub.id = :subject_id";
    $params[':subject_id'] = $subject_filter;
}

// Apply exam filter
if (!empty($exam_filter)) {
    $query .= " AND e.id = :exam_id";
    $params[':exam_id'] = $exam_filter;
}

$query .= " ORDER BY s.first_name ASC";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$results = $stmt->fetchAll();

// Calculate statistics
$total_students = count($results);
$passed = 0;
$failed = 0;
$total_percentage = 0;
$grade_distribution = [];

foreach ($results as $result) {
    if ($result['result'] == 'Pass') {
        $passed++;
    } else {
        $failed++;
    }
    
    $total_percentage += $result['percentage'] ?? 0;
    
    // Grade distribution
    $grade = $result['grade'] ?? 'N/A';
    if (!isset($grade_distribution[$grade])) {
        $grade_distribution[$grade] = 0;
    }
    $grade_distribution[$grade]++;
}

$average_percentage = $total_students > 0 ? round($total_percentage / $total_students) : 0;

// Get selected class/subject/exam names for display
$selected_class_name = '';
$selected_subject_name = '';
$selected_exam_title = '';

if (!empty($class_filter)) {
    foreach ($classes as $class) {
        if ($class['id'] == $class_filter) {
            $selected_class_name = $class['name'] . ' - ' . $class['grade'];
            break;
        }
    }
}

if (!empty($subject_filter)) {
    foreach ($subjects as $subject) {
        if ($subject['id'] == $subject_filter) {
            $selected_subject_name = $subject['subject_title'];
            break;
        }
    }
}

if (!empty($exam_filter)) {
    foreach ($exams as $exam) {
        if ($exam['id'] == $exam_filter) {
            $selected_exam_title = $exam['exam_title'];
            break;
        }
    }
}

// Get grade badge class
function getGradeBadgeClass($grade) {
    $grade = strtoupper($grade);
    switch($grade) {
        case 'A+': return 'bg-success';
        case 'A': return 'bg-success';
        case 'B+': return 'bg-primary';
        case 'B': return 'bg-info text-dark';
        case 'C+': return 'bg-info text-dark';
        case 'C': return 'bg-warning text-dark';
        case 'D': return 'bg-secondary';
        case 'F': return 'bg-danger';
        default: return 'bg-secondary';
    }
}

// Get result badge class
function getResultBadgeClass($result) {
    return $result == 'Pass' ? 'bg-success' : 'bg-danger';
}

// Process form submission for adding/editing results
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new result
    if (isset($_POST['add_result'])) {
        $student_id = $_POST['student_id'] ?? 0;
        $exam_id = $_POST['exam_id'] ?? 0;
        $subject_id = $_POST['subject_id'] ?? 0;
        $marks_obtained = $_POST['marks_obtained'] ?? 0;
        $total_marks = $_POST['total_marks'] ?? 100;
        
        if ($student_id && $exam_id && $subject_id && $marks_obtained !== '') {
            // Calculate percentage and grade
            $percentage = round(($marks_obtained / $total_marks) * 100, 2);
            $grade = calculateGrade($percentage);
            $result_status = $percentage >= 40 ? 'Pass' : 'Fail';
            
            // Check if result already exists
            $check_stmt = $conn->prepare("
                SELECT id FROM results 
                WHERE student_id = :student_id AND exam_id = :exam_id AND subject_id = :subject_id
            ");
            $check_stmt->bindValue(':student_id', $student_id);
            $check_stmt->bindValue(':exam_id', $exam_id);
            $check_stmt->bindValue(':subject_id', $subject_id);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                // Update existing result
                $update_stmt = $conn->prepare("
                    UPDATE results 
                    SET marks_obtained = :marks_obtained, 
                        total_marks = :total_marks,
                        percentage = :percentage,
                        grade = :grade,
                        result = :result
                    WHERE student_id = :student_id AND exam_id = :exam_id AND subject_id = :subject_id
                ");
                
                $update_stmt->bindValue(':marks_obtained', $marks_obtained);
                $update_stmt->bindValue(':total_marks', $total_marks);
                $update_stmt->bindValue(':percentage', $percentage);
                $update_stmt->bindValue(':grade', $grade);
                $update_stmt->bindValue(':result', $result_status);
                $update_stmt->bindValue(':student_id', $student_id);
                $update_stmt->bindValue(':exam_id', $exam_id);
                $update_stmt->bindValue(':subject_id', $subject_id);
                
                if ($update_stmt->execute()) {
                    $success_message = "Result updated successfully!";
                } else {
                    $error_message = "Error updating result.";
                }
            } else {
                // Insert new result
                $insert_stmt = $conn->prepare("
                    INSERT INTO results (student_id, exam_id, subject_id, marks_obtained, total_marks, percentage, grade, result)
                    VALUES (:student_id, :exam_id, :subject_id, :marks_obtained, :total_marks, :percentage, :grade, :result)
                ");
                
                $insert_stmt->bindValue(':student_id', $student_id);
                $insert_stmt->bindValue(':exam_id', $exam_id);
                $insert_stmt->bindValue(':subject_id', $subject_id);
                $insert_stmt->bindValue(':marks_obtained', $marks_obtained);
                $insert_stmt->bindValue(':total_marks', $total_marks);
                $insert_stmt->bindValue(':percentage', $percentage);
                $insert_stmt->bindValue(':grade', $grade);
                $insert_stmt->bindValue(':result', $result_status);
                
                if ($insert_stmt->execute()) {
                    $success_message = "Result added successfully!";
                } else {
                    $error_message = "Error adding result.";
                }
            }
        } else {
            $error_message = "Please fill in all required fields.";
        }
    }
    
    // Delete result
    if (isset($_POST['delete_result'])) {
        $result_id = $_POST['result_id'] ?? 0;
        
        if ($result_id) {
            $delete_stmt = $conn->prepare("
                DELETE FROM results WHERE id = :id
            ");
            $delete_stmt->bindValue(':id', $result_id);
            
            if ($delete_stmt->execute()) {
                $success_message = "Result deleted successfully!";
            } else {
                $error_message = "Error deleting result.";
            }
        }
    }
}

// Function to calculate grade
function calculateGrade($percentage) {
    if ($percentage >= 90) return 'A+';
    if ($percentage >= 80) return 'A';
    if ($percentage >= 70) return 'B+';
    if ($percentage >= 60) return 'B';
    if ($percentage >= 50) return 'C+';
    if ($percentage >= 40) return 'C';
    if ($percentage >= 33) return 'D';
    return 'F';
}

// Get students for dropdown (for adding results)
$student_stmt = $conn->prepare("
    SELECT s.id, s.first_name, s.last_name, s.student_uid
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE c.teacher_id = :teacher_id AND s.status = 'Active'
    ORDER BY s.first_name
");
$student_stmt->bindValue(':teacher_id', $teacher_id);
$student_stmt->execute();
$students = $student_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Results | Teacher Dashboard</title>
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

        /* Result specific styles */
        .result-student {
            font-weight: 500;
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 4px 12px;
        }
        .percentage-bar {
            height: 6px;
            border-radius: 3px;
            background: #e9ecef;
            overflow: hidden;
            min-width: 80px;
        }
        .percentage-bar .bar {
            height: 100%;
            border-radius: 3px;
            transition: width 0.5s;
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
            <a href="exams.php"><i class="bi bi-pencil-square"></i> Exams</a>
            <a href="results.php" class="active"><i class="bi bi-bar-chart-line"></i> Results</a>
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
            <h1 class="td-page-title">Results <small>Student exam results overview</small></h1>
            <div class="td-search ms-auto">
                <form method="GET" action="" class="d-flex">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
                        <input type="search" name="search" class="form-control border-start-0" placeholder="Search students..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
                        <input type="hidden" name="class" value="<?php echo htmlspecialchars($class_filter); ?>">
                        <input type="hidden" name="subject" value="<?php echo htmlspecialchars($subject_filter); ?>">
                        <input type="hidden" name="exam" value="<?php echo htmlspecialchars($exam_filter); ?>">
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
                        <div class="stat-icon bg-grad-1"><i class="bi bi-people-fill"></i></div>
                        <div>
                            <h3><?php echo $total_students; ?></h3>
                            <p>Students Appeared</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="stat-icon bg-grad-2"><i class="bi bi-check2-circle"></i></div>
                        <div>
                            <h3><?php echo $passed; ?></h3>
                            <p>Passed</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="stat-icon bg-grad-5"><i class="bi bi-x-circle"></i></div>
                        <div>
                            <h3><?php echo $failed; ?></h3>
                            <p>Failed</p>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-xl-3">
                    <div class="card stat-card">
                        <div class="stat-icon bg-grad-4"><i class="bi bi-graph-up-arrow"></i></div>
                        <div>
                            <h3><?php echo $average_percentage; ?>%</h3>
                            <p>Class Average</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="" class="row g-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label">Class</label>
                            <select name="class" class="form-select">
                                <option value="">Select Class</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['id']; ?>" <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['name'] . ' - ' . $class['grade']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Subject</label>
                            <select name="subject" class="form-select">
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>" <?php echo $subject_filter == $subject['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject['subject_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Exam</label>
                            <select name="exam" class="form-select">
                                <option value="">Select Exam</option>
                                <?php foreach ($exams as $exam): ?>
                                    <option value="<?php echo $exam['id']; ?>" <?php echo $exam_filter == $exam['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($exam['exam_title'] . ' (' . date('d M Y', strtotime($exam['exam_date'])) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 d-grid">
                            <button type="submit" class="btn btn-primary"><i class="bi bi-search"></i> Show Results</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Add Result Button -->
            <div class="mb-3">
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addResultModal">
                    <i class="bi bi-plus-lg"></i> Add Result
                </button>
                <?php if (!empty($results)): ?>
                    <button class="btn btn-outline-success ms-2" onclick="window.print()">
                        <i class="bi bi-printer"></i> Print
                    </button>
                <?php endif; ?>
            </div>

            <!-- Results Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>
                        <i class="bi bi-bar-chart-line me-2 text-primary"></i>
                        Result Sheet
                        <?php if ($selected_class_name): ?>
                            — <?php echo htmlspecialchars($selected_class_name); ?>
                        <?php endif; ?>
                        <?php if ($selected_subject_name): ?>
                            · <?php echo htmlspecialchars($selected_subject_name); ?>
                        <?php endif; ?>
                        <?php if ($selected_exam_title): ?>
                            · <?php echo htmlspecialchars($selected_exam_title); ?>
                        <?php endif; ?>
                    </span>
                    <span class="badge bg-light text-dark">
                        <?php echo $total_students; ?> students
                    </span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle" id="resultsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Student ID</th>
                                <th>Student Name</th>
                                <th>Class</th>
                                <th>Subject</th>
                                <th>Marks</th>
                                <th>Total</th>
                                <th>Percentage</th>
                                <th>Grade</th>
                                <th>Result</th>
                                <th style="width: 80px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($results) > 0): ?>
                                <?php foreach ($results as $result): 
                                    $percentage = $result['percentage'] ?? 0;
                                    $bar_color = $percentage >= 80 ? 'bg-success' : ($percentage >= 60 ? 'bg-primary' : ($percentage >= 40 ? 'bg-warning' : 'bg-danger'));
                                ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($result['student_uid'] ?? 'N/A'); ?></td>
                                        <td>
                                            <div class="result-student">
                                                <?php echo htmlspecialchars(($result['first_name'] ?? '') . ' ' . ($result['last_name'] ?? '')); ?>
                                                <?php if ($result['gender']): ?>
                                                    <br><small class="text-muted"><?php echo htmlspecialchars($result['gender']); ?></small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($result['class_name'] ?? 'N/A'); ?>
                                            <?php if ($result['class_grade']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($result['class_grade']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($result['subject_title'] ?? 'N/A'); ?>
                                            <?php if ($result['subject_code']): ?>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($result['subject_code']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo $result['marks_obtained'] ?? 0; ?></strong></td>
                                        <td><?php echo $result['total_marks'] ?? 100; ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span><?php echo $percentage; ?>%</span>
                                                <div class="percentage-bar">
                                                    <div class="bar <?php echo $bar_color; ?>" style="width: <?php echo min($percentage, 100); ?>%;"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getGradeBadgeClass($result['grade']); ?> status-badge">
                                                <?php echo htmlspecialchars($result['grade'] ?? 'N/A'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo getResultBadgeClass($result['result']); ?> status-badge">
                                                <i class="bi <?php echo $result['result'] == 'Pass' ? 'bi-check-circle' : 'bi-x-circle'; ?> me-1"></i>
                                                <?php echo $result['result'] ?? 'N/A'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="editResult(<?php echo $result['id']; ?>)">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteResult(<?php echo $result['id']; ?>, '<?php echo addslashes(($result['first_name'] ?? '') . ' ' . ($result['last_name'] ?? '')); ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center py-4">
                                        <i class="bi bi-bar-chart-line fs-1 d-block text-muted"></i>
                                        <p class="text-muted mb-0">No results found</p>
                                        <?php if (empty($class_filter) && empty($subject_filter) && empty($exam_filter)): ?>
                                            <p class="text-muted small">Select a class, subject, and exam to view results</p>
                                        <?php else: ?>
                                            <button class="btn btn-sm btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#addResultModal">
                                                <i class="bi bi-plus-lg"></i> Add your first result
                                            </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!empty($results)): ?>
                    <div class="card-footer bg-light">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex gap-3 flex-wrap">
                                <span><span class="badge bg-success">A+</span> 90%+</span>
                                <span><span class="badge bg-success">A</span> 80-89%</span>
                                <span><span class="badge bg-primary">B+</span> 70-79%</span>
                                <span><span class="badge bg-info text-dark">B</span> 60-69%</span>
                                <span><span class="badge bg-warning text-dark">C</span> 40-59%</span>
                                <span><span class="badge bg-danger">F</span> Below 40%</span>
                            </div>
                            <span class="text-muted small">
                                <i class="bi bi-clock me-1"></i>
                                Last updated: <?php echo date('d M Y, h:i A'); ?>
                            </span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <!-- Footer -->
        <footer class="td-footer">© 2026 Bright Future School — Teacher Panel.</footer>
    </div>
</div>

<!-- Add Result Modal -->
<div class="modal fade" id="addResultModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-plus-circle me-2 text-primary"></i>Add Result</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Student <span class="text-danger">*</span></label>
                            <select name="student_id" class="form-select" required>
                                <option value="">Select Student</option>
                                <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>">
                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name'] . ' (' . $student['student_uid'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Exam <span class="text-danger">*</span></label>
                            <select name="exam_id" class="form-select" required>
                                <option value="">Select Exam</option>
                                <?php foreach ($exams as $exam): ?>
                                    <option value="<?php echo $exam['id']; ?>">
                                        <?php echo htmlspecialchars($exam['exam_title'] . ' (' . date('d M Y', strtotime($exam['exam_date'])) . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subject <span class="text-danger">*</span></label>
                            <select name="subject_id" class="form-select" required>
                                <option value="">Select Subject</option>
                                <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo htmlspecialchars($subject['subject_title']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Marks Obtained <span class="text-danger">*</span></label>
                            <input type="number" name="marks_obtained" class="form-control" placeholder="e.g., 85" min="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Total Marks</label>
                            <input type="number" name="total_marks" class="form-control" placeholder="e.g., 100" value="100" min="1">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_result" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Save Result
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Result Modal -->
<div class="modal fade" id="editResultModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Result</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="result_id" id="edit_result_id">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Student</label>
                            <input type="text" id="edit_student_name" class="form-control" disabled>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Exam</label>
                            <input type="text" id="edit_exam_title" class="form-control" disabled>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Subject</label>
                            <input type="text" id="edit_subject_title" class="form-control" disabled>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Marks Obtained <span class="text-danger">*</span></label>
                            <input type="number" name="marks_obtained" id="edit_marks_obtained" class="form-control" min="0" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Total Marks</label>
                            <input type="number" name="total_marks" id="edit_total_marks" class="form-control" value="100" min="1">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_result" class="btn btn-primary">
                        <i class="bi bi-check2"></i> Update Result
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteResultModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Result</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="result_id" id="delete_result_id">
                <div class="modal-body">
                    <p>Are you sure you want to delete the result for: <strong id="delete_result_name"></strong>?</p>
                    <p class="text-danger small">This action cannot be undone. All associated data will be permanently removed.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="delete_result" class="btn btn-danger">
                        <i class="bi bi-trash"></i> Delete Result
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Edit result - load data into modal
    function editResult(id) {
        fetch('ajax/get_result.php?id=' + id)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('edit_result_id').value = data.id;
                    document.getElementById('edit_student_name').value = data.student_name;
                    document.getElementById('edit_exam_title').value = data.exam_title;
                    document.getElementById('edit_subject_title').value = data.subject_title;
                    document.getElementById('edit_marks_obtained').value = data.marks_obtained;
                    document.getElementById('edit_total_marks').value = data.total_marks;
                    
                    var modal = new bootstrap.Modal(document.getElementById('editResultModal'));
                    modal.show();
                } else {
                    alert('Error loading result data');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while loading the result.');
            });
    }

    // Delete result
    function deleteResult(id, name) {
        document.getElementById('delete_result_id').value = id;
        document.getElementById('delete_result_name').textContent = name;
        
        var modal = new bootstrap.Modal(document.getElementById('deleteResultModal'));
        modal.show();
    }

    // Auto-set total marks when adding result
    document.addEventListener('DOMContentLoaded', function() {
        const totalMarksInput = document.querySelector('input[name="total_marks"]');
        if (totalMarksInput) {
            totalMarksInput.value = 100;
        }
    });
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>