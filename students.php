<?php
// students.php
require_once '../database/connect.php';

// Get the current logged-in teacher ID
session_start();
$teacher_id = $_SESSION['teacher_id'] ?? 10; // Default to teacher ID 10 for demo

// Get filter parameters
$search = $_GET['search'] ?? '';
$class_filter = $_GET['class'] ?? '';
$status_filter = $_GET['status'] ?? '';

// Build the query to get students with their class, teacher, and parent info
$query = "
    SELECT 
        s.id,
        s.student_uid,
        s.first_name,
        s.last_name,
        s.gender,
        s.email,
        s.status as student_status,
        s.class_id,
        s.admission_date,
        s.date_of_birth,
        s.address,
        s.photo_url,
        c.id as class_id,
        c.name as class_name,
        c.grade as class_grade,
        c.room_no,
        c.description as class_description,
        t.id as teacher_id,
        t.full_name as teacher_name,
        t.phone as teacher_phone,
        p.id as parent_id,
        p.full_name as parent_name,
        p.relation as parent_relation,
        p.phone as parent_phone,
        p.email as parent_email,
        p.address as parent_address,
        (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.id) as total_attendance,
        (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.id AND a.status = 'Present') as present_attendance,
        (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.id AND a.status = 'Absent') as absent_attendance,
        (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.id AND a.status = 'Late') as late_attendance
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN teachers t ON c.teacher_id = t.id
    LEFT JOIN parents p ON s.id = p.student_id
    WHERE 1=1
";

// Add filters
if (!empty($search)) {
  $query .= " AND (s.first_name LIKE :search OR s.last_name LIKE :search OR s.student_uid LIKE :search OR s.email LIKE :search)";
}

if (!empty($class_filter)) {
  $query .= " AND s.class_id = :class_id";
}

if (!empty($status_filter)) {
  $query .= " AND s.status = :status";
}

// For teacher, only show students in their classes
$query .= " AND c.teacher_id = :teacher_id";

$query .= " ORDER BY s.first_name ASC, s.last_name ASC";

$stmt = $conn->prepare($query);

// Bind parameters
if (!empty($search)) {
  $stmt->bindValue(':search', '%' . $search . '%');
}

if (!empty($class_filter)) {
  $stmt->bindValue(':class_id', $class_filter);
}

if (!empty($status_filter)) {
  $stmt->bindValue(':status', $status_filter);
}

$stmt->bindValue(':teacher_id', $teacher_id);

$stmt->execute();
$students = $stmt->fetchAll();

// Get total counts for stats
$total_students = count($students);
$male_count = 0;
$female_count = 0;
$total_attendance_percentage = 0;

foreach ($students as $student) {
  if ($student['gender'] == 'Male')
    $male_count++;
  if ($student['gender'] == 'Female')
    $female_count++;

  // Calculate attendance percentage
  if ($student['total_attendance'] > 0) {
    $attendance_percent = ($student['present_attendance'] / $student['total_attendance']) * 100;
    $total_attendance_percentage += $attendance_percent;
  }
}

$average_attendance = $total_students > 0 ? round($total_attendance_percentage / $total_students) : 0;

// Get classes for filter dropdown
$class_stmt = $conn->prepare("
    SELECT DISTINCT c.id, c.name, c.grade, c.room_no 
    FROM classes c 
    WHERE c.teacher_id = :teacher_id AND c.status = 'active'
    ORDER BY c.name
");
$class_stmt->bindValue(':teacher_id', $teacher_id);
$class_stmt->execute();
$classes = $class_stmt->fetchAll();

// Get pagination
$page = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;
$total_pages = ceil($total_students / $per_page);
$paginated_students = array_slice($students, $offset, $per_page);

// Get parent relation mapping
$relation_map = [
  1 => 'Father',
  2 => 'Mother',
  3 => 'Guardian',
  4 => 'Grandfather',
  5 => 'Grandmother',
  6 => 'Uncle',
  7 => 'Aunt',
  8 => 'Sibling',
  9 => 'Other'
];

// Function to get status badge class
function getStatusBadgeClass($status)
{
  switch ($status) {
    case 'Active':
      return 'bg-success';
    case 'Inactive':
      return 'bg-danger';
    case 'Pending Doc':
      return 'bg-warning text-dark';
    default:
      return 'bg-secondary';
  }
}

// Function to get relation text
function getRelationText($relation_code, $relation_map)
{
  return $relation_map[$relation_code] ?? 'Unknown';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Students | Teacher Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    .student-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: white;
    }

    .student-avatar-sm {
      width: 32px;
      height: 32px;
      font-size: 12px;
    }

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

    .parent-info-card {
      background: #f8f9fa;
      border-radius: 8px;
      padding: 8px 12px;
      font-size: 0.85rem;
    }

    .class-badge {
      background: #e9ecef;
      padding: 2px 10px;
      border-radius: 12px;
      font-size: 0.8rem;
      font-weight: 500;
    }

    .student-row:hover {
      background-color: #f8f9fa;
    }

    .detail-icon {
      font-size: 0.9rem;
      margin-right: 4px;
      opacity: 0.7;
    }

    .expand-btn {
      cursor: pointer;
      transition: transform 0.3s;
    }

    .expand-btn.expanded {
      transform: rotate(180deg);
    }

    .detail-row {
      display: none;
      background-color: #f8f9fa;
    }

    .detail-row.show {
      display: table-row;
    }

    .detail-label {
      font-weight: 600;
      color: #495057;
      min-width: 120px;
      display: inline-block;
    }

    .info-grid {
      display: grid;
      grid-template-columns: 1fr 1fr 1fr;
      gap: 15px;
      padding: 10px 0;
    }

    .info-item {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .info-item i {
      color: #6c757d;
      width: 20px;
    }

    @media (max-width: 768px) {
      .info-grid {
        grid-template-columns: 1fr;
        gap: 8px;
      }
    }

    .table-responsive {
      overflow-x: auto;
    }

    .table td,
    .table th {
      vertical-align: middle;
    }
  </style>
</head>

<body>
  <input type="checkbox" id="tdSidebarToggle">
  <div class="td-wrapper">
    <label for="tdSidebarToggle" class="td-overlay"></label>
    <aside class="td-sidebar">
      <div class="td-brand"><i class="bi bi-mortarboard-fill"></i><span>Bright Future<small>School Portal</small></span>
      </div>
      <div class="td-teacher-box">
        <div class="td-avatar">MA</div>
        <div>
          <h6>Mr. Ahmed</h6>
          <p>Mathematics Teacher</p>
        </div>
      </div>
      <nav class="td-nav">
        <div class="td-nav-title">Main</div>
        <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="students.php" class="active"><i class="bi bi-people"></i> My Students</a>
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
    <div class="td-main">
      <header class="td-navbar">
        <label for="tdSidebarToggle" class="td-burger"><i class="bi bi-list"></i></label>
        <h1 class="td-page-title">My Students <small>All students assigned to your classes</small></h1>
        <div class="td-search ms-auto">
          <form method="GET" action="students.php" class="d-flex">
            <div class="input-group">
              <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
              <input type="search" name="search" class="form-control border-start-0"
                placeholder="Search by name, ID or email..." value="<?php echo htmlspecialchars($search); ?>">
              <button type="submit" class="btn btn-primary d-none">Search</button>
            </div>
          </form>
        </div>
        <a href="notices.php" class="td-icon-btn"><i class="bi bi-bell"></i><span class="td-dot"></span></a>
        <a href="profile.php" class="d-flex align-items-center gap-2 text-dark">
          <span class="td-avatar">MA</span>
          <span class="d-none d-md-block">
            <strong class="d-block" style="font-size:.85rem">Mr. Ahmed</strong>
            <small class="text-muted" style="font-size:.72rem">Mathematics Teacher</small>
          </span>
        </a>
      </header>
      <main class="td-content">
        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
          <div class="col-6 col-xl-3">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-1"><i class="bi bi-people-fill"></i></div>
              <div>
                <h3>
                  <?php echo $total_students; ?>
                </h3>
                <p>Total Students</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-2"><i class="bi bi-gender-male"></i></div>
              <div>
                <h3>
                  <?php echo $male_count; ?>
                </h3>
                <p>Male Students</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-5"><i class="bi bi-gender-female"></i></div>
              <div>
                <h3>
                  <?php echo $female_count; ?>
                </h3>
                <p>Female Students</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-4"><i class="bi bi-graph-up"></i></div>
              <div>
                <h3>
                  <?php echo $average_attendance; ?>%
                </h3>
                <p>Avg. Attendance</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Filter Form -->
        <div class="card mb-4">
          <div class="card-body">
            <form method="GET" action="students.php" class="row g-3 align-items-end">
              <input type="hidden" name="page" value="1">
              <div class="col-md-4">
                <label class="form-label">Search Student</label>
                <input type="search" name="search" class="form-control" placeholder="Name, ID or email"
                  value="<?php echo htmlspecialchars($search); ?>">
              </div>
              <div class="col-md-3">
                <label class="form-label">Class</label>
                <select name="class" class="form-select">
                  <option value="">All Classes</option>
                  <?php foreach ($classes as $class): ?>
                    <option value="<?php echo $class['id']; ?>" <?php echo $class_filter == $class['id'] ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($class['name'] . ' - ' . $class['grade']); ?>
                      <?php if ($class['room_no']): ?>
                        (Room:
                        <?php echo htmlspecialchars($class['room_no']); ?>)
                      <?php endif; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                  <option value="">All Status</option>
                  <option value="Active" <?php echo $status_filter == 'Active' ? 'selected' : ''; ?>>Active</option>
                  <option value="Inactive" <?php echo $status_filter == 'Inactive' ? 'selected' : ''; ?>>Inactive
                  </option>
                  <option value="Pending Doc" <?php echo $status_filter == 'Pending Doc' ? 'selected' : ''; ?>>Pending
                    Doc</option>
                </select>
              </div>
              <div class="col-md-2 d-grid">
                <button type="submit" class="btn btn-primary"><i class="bi bi-funnel"></i> Filter</button>
              </div>
            </form>
          </div>
        </div>

        <!-- Student List -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-list-ul me-2 text-primary"></i>Student List</span>
            <div>
              <button class="btn btn-sm btn-outline-secondary me-1" onclick="expandAll()">
                <i class="bi bi-arrows-expand"></i> Expand All
              </button>
              <a href="#" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i> Export</a>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table table-hover align-middle" id="studentTable">
              <thead class="table-light">
                <tr>
                  <th style="width: 40px;"></th>
                  <th>Student</th>
                  <th>Class</th>
                  <th>Parent/Guardian</th>
                  <th>Attendance</th>
                  <th>Status</th>
                  <th style="width: 60px;">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (count($paginated_students) > 0): ?>
                  <?php foreach ($paginated_students as $index => $student):
                    $attendance_percent = 0;
                    if ($student['total_attendance'] > 0) {
                      $attendance_percent = round(($student['present_attendance'] / $student['total_attendance']) * 100);
                    }
                    $status_class = getStatusBadgeClass($student['student_status']);
                    $relation_text = getRelationText($student['parent_relation'], $relation_map);
                    $avatar_color = ['#667eea', '#f093fb', '#4facfe', '#43e97b', '#fa709a', '#f5576c', '#764ba2', '#00f2fe', '#38f9d7', '#fee140'][$index % 10];
                    $student_name = htmlspecialchars($student['first_name'] . ' ' . $student['last_name']);
                    $initials = substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1);
                    ?>
                    <tr class="student-row">
                      <td>
                        <button class="btn btn-sm btn-link text-secondary expand-btn p-0"
                          onclick="toggleDetails(<?php echo $student['id']; ?>)">
                          <i class="bi bi-chevron-down"></i>
                        </button>
                      </td>
                      <td>
                        <div class="d-flex align-items-center gap-2">
                          <?php if ($student['photo_url']): ?>
                            <img src="<?php echo htmlspecialchars($student['photo_url']); ?>" alt="Student"
                              class="rounded-circle" width="32" height="32" style="object-fit: cover;">
                          <?php else: ?>
                            <div class="student-avatar student-avatar-sm" style="background: <?php echo $avatar_color; ?>;">
                              <?php echo strtoupper($initials); ?>
                            </div>
                          <?php endif; ?>
                          <div>
                            <div class="fw-semibold">
                              <?php echo $student_name; ?>
                            </div>
                            <small class="text-muted">
                              <?php echo htmlspecialchars($student['student_uid'] ?? 'N/A'); ?>
                            </small>
                          </div>
                        </div>
                      </td>
                      <td>
                        <div>
                          <span class="class-badge">
                            <?php echo htmlspecialchars($student['class_name'] ?? 'Not Assigned'); ?>
                          </span>
                          <?php if ($student['class_grade']): ?>
                            <br>
                            <small class="text-muted">Grade:
                              <?php echo htmlspecialchars($student['class_grade']); ?>
                            </small>
                          <?php endif; ?>
                        </div>
                      </td>
                      <td>
                        <?php if ($student['parent_name']): ?>
                          <div>
                            <div>
                              <?php echo htmlspecialchars($student['parent_name']); ?>
                            </div>
                            <small class="text-muted">
                              <?php echo htmlspecialchars($relation_text); ?>
                              <?php if ($student['parent_phone']): ?>
                                <i class="bi bi-telephone ms-1"></i>
                                <?php echo htmlspecialchars($student['parent_phone']); ?>
                              <?php endif; ?>
                            </small>
                          </div>
                        <?php else: ?>
                          <span class="text-muted small">No parent recorded</span>
                        <?php endif; ?>
                      </td>
                      <td>
                        <div class="d-flex align-items-center">
                          <span class="fw-semibold me-2">
                            <?php echo $attendance_percent; ?>%
                          </span>
                          <div class="progress" style="width: 60px; height: 6px;">
                            <div
                              class="progress-bar <?php echo $attendance_percent >= 80 ? 'bg-success' : ($attendance_percent >= 60 ? 'bg-warning' : 'bg-danger'); ?>"
                              style="width: <?php echo $attendance_percent; ?>%"></div>
                          </div>
                        </div>
                      </td>
                      <td>
                        <span class="badge <?php echo $status_class; ?>">
                          <?php echo htmlspecialchars($student['student_status'] ?? 'Unknown'); ?>
                        </span>
                      </td>
                      <td>
                        <div class="btn-group">
                          <button class="btn btn-sm btn-outline-primary"
                            onclick="viewStudent(<?php echo $student['id']; ?>)">
                            <i class="bi bi-eye"></i>
                          </button>
                          <button class="btn btn-sm btn-outline-secondary"
                            onclick="messageStudent(<?php echo $student['id']; ?>)">
                            <i class="bi bi-chat"></i>
                          </button>
                        </div>
                      </td>
                    </tr>
                    <!-- Detail Row -->
                    <tr class="detail-row" id="detail_<?php echo $student['id']; ?>">
                      <td colspan="7">
                        <div class="p-3">
                          <div class="row">
                            <div class="col-md-6">
                              <h6 class="text-primary mb-3"><i class="bi bi-person-vcard"></i> Student Details</h6>
                              <div class="info-grid">
                                <div class="info-item">
                                  <i class="bi bi-envelope"></i>
                                  <span>
                                    <?php echo htmlspecialchars($student['email'] ?? 'N/A'); ?>
                                  </span>
                                </div>
                                <div class="info-item">
                                  <i class="bi bi-calendar3"></i>
                                  <span>DOB:
                                    <?php echo htmlspecialchars($student['date_of_birth'] ?? 'N/A'); ?>
                                  </span>
                                </div>
                                <div class="info-item">
                                  <i class="bi bi-calendar-check"></i>
                                  <span>Admission:
                                    <?php echo htmlspecialchars($student['admission_date'] ?? 'N/A'); ?>
                                  </span>
                                </div>
                                <div class="info-item">
                                  <i class="bi bi-gender-<?php echo strtolower($student['gender'] ?? ''); ?>"></i>
                                  <span>
                                    <?php echo htmlspecialchars($student['gender'] ?? 'N/A'); ?>
                                  </span>
                                </div>
                                <div class="info-item">
                                  <i class="bi bi-geo-alt"></i>
                                  <span>
                                    <?php echo htmlspecialchars($student['address'] ?? 'No address'); ?>
                                  </span>
                                </div>
                              </div>
                            </div>
                            <div class="col-md-6">
                              <h6 class="text-primary mb-3"><i class="bi bi-people"></i> Class & Teacher Information</h6>
                              <div class="info-grid">
                                <div class="info-item">
                                  <i class="bi bi-building"></i>
                                  <span>Class:
                                    <?php echo htmlspecialchars($student['class_name'] ?? 'Not Assigned'); ?>
                                  </span>
                                </div>
                                <div class="info-item">
                                  <i class="bi bi-book"></i>
                                  <span>Grade:
                                    <?php echo htmlspecialchars($student['class_grade'] ?? 'N/A'); ?>
                                  </span>
                                </div>
                                <div class="info-item">
                                  <i class="bi bi-door-open"></i>
                                  <span>Room:
                                    <?php echo htmlspecialchars($student['room_no'] ?? 'N/A'); ?>
                                  </span>
                                </div>
                                <div class="info-item">
                                  <i class="bi bi-person-badge"></i>
                                  <span>Teacher:
                                    <?php echo htmlspecialchars($student['teacher_name'] ?? 'Not Assigned'); ?>
                                  </span>
                                </div>
                                <?php if ($student['teacher_phone']): ?>
                                  <div class="info-item">
                                    <i class="bi bi-telephone"></i>
                                    <span>Teacher Phone:
                                      <?php echo htmlspecialchars($student['teacher_phone']); ?>
                                    </span>
                                  </div>
                                <?php endif; ?>
                              </div>
                            </div>
                          </div>

                          <?php if ($student['parent_name']): ?>
                            <hr>
                            <div class="row">
                              <div class="col-12">
                                <h6 class="text-primary mb-3"><i class="bi bi-person-lines-fill"></i> Parent/Guardian Details
                                </h6>
                                <div class="info-grid">
                                  <div class="info-item">
                                    <i class="bi bi-person"></i>
                                    <span><strong>Name:</strong>
                                      <?php echo htmlspecialchars($student['parent_name']); ?>
                                    </span>
                                  </div>
                                  <div class="info-item">
                                    <i class="bi bi-tag"></i>
                                    <span><strong>Relation:</strong>
                                      <?php echo htmlspecialchars($relation_text); ?>
                                    </span>
                                  </div>
                                  <div class="info-item">
                                    <i class="bi bi-telephone"></i>
                                    <span><strong>Phone:</strong>
                                      <?php echo htmlspecialchars($student['parent_phone'] ?? 'N/A'); ?>
                                    </span>
                                  </div>
                                  <div class="info-item">
                                    <i class="bi bi-envelope"></i>
                                    <span><strong>Email:</strong>
                                      <?php echo htmlspecialchars($student['parent_email'] ?? 'N/A'); ?>
                                    </span>
                                  </div>
                                  <?php if ($student['parent_address']): ?>
                                    <div class="info-item">
                                      <i class="bi bi-geo-alt"></i>
                                      <span><strong>Address:</strong>
                                        <?php echo htmlspecialchars($student['parent_address']); ?>
                                      </span>
                                    </div>
                                  <?php endif; ?>
                                </div>
                              </div>
                            </div>
                          <?php endif; ?>

                          <div class="mt-3 d-flex gap-2">
                            <a href="admin_dashboard/students.php?id=<?php echo $student['id']; ?>"
                              class="btn btn-sm btn-primary">
                              <i class="bi bi-eye"></i> View Full Profile
                            </a>
                            <a href="attendance.php?student=<?php echo $student['id']; ?>"
                              class="btn btn-sm btn-info text-white">
                              <i class="bi bi-calendar-check"></i> View Attendance
                            </a>
                            <a href="messages.php?student=<?php echo $student['id']; ?>" class="btn btn-sm btn-secondary">
                              <i class="bi bi-chat"></i> Send Message
                            </a>
                            <?php if ($student['parent_id']): ?>
                              <a href="messages.php?parent=<?php echo $student['parent_id']; ?>"
                                class="btn btn-sm btn-outline-success">
                                <i class="bi bi-chat-dots"></i> Contact Parent
                              </a>
                            <?php endif; ?>
                          </div>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr>
                    <td colspan="7" class="text-center py-4">
                      <i class="bi bi-inbox fs-1 d-block text-muted"></i>
                      <p class="text-muted mb-0">No students found matching your criteria</p>
                    </td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
          <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
            <small class="text-muted">
              Showing
              <?php echo min($offset + 1, $total_students); ?> to
              <?php echo min($offset + $per_page, $total_students); ?> of
              <?php echo $total_students; ?> students
            </small>
            <?php if ($total_pages > 1): ?>
              <ul class="pagination pagination-sm mb-0">
                <?php if ($page > 1): ?>
                  <li class="page-item">
                    <a class="page-link"
                      href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&class=<?php echo urlencode($class_filter); ?>&status=<?php echo urlencode($status_filter); ?>">Previous</a>
                  </li>
                <?php else: ?>
                  <li class="page-item disabled"><span class="page-link">Previous</span></li>
                <?php endif; ?>

                <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                ?>

                <?php if ($start_page > 1): ?>
                  <li class="page-item"><a class="page-link"
                      href="?page=1&search=<?php echo urlencode($search); ?>&class=<?php echo urlencode($class_filter); ?>&status=<?php echo urlencode($status_filter); ?>">1</a>
                  </li>
                  <?php if ($start_page > 2): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                  <?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                  <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                    <a class="page-link"
                      href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&class=<?php echo urlencode($class_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                      <?php echo $i; ?>
                    </a>
                  </li>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                  <?php if ($end_page < $total_pages - 1): ?>
                    <li class="page-item disabled"><span class="page-link">...</span></li>
                  <?php endif; ?>
                  <li class="page-item"><a class="page-link"
                      href="?page=<?php echo $total_pages; ?>&search=<?php echo urlencode($search); ?>&class=<?php echo urlencode($class_filter); ?>&status=<?php echo urlencode($status_filter); ?>">
                      <?php echo $total_pages; ?>
                    </a></li>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                  <li class="page-item">
                    <a class="page-link"
                      href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&class=<?php echo urlencode($class_filter); ?>&status=<?php echo urlencode($status_filter); ?>">Next</a>
                  </li>
                <?php else: ?>
                  <li class="page-item disabled"><span class="page-link">Next</span></li>
                <?php endif; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      </main>
      <footer class="td-footer">© 2026 Bright Future School — Teacher Panel.</footer>
    </div>
  </div>

  <script>
    // Toggle student detail row
    function toggleDetails(studentId) {
      const detailRow = document.getElementById('detail_' + studentId);
      const btn = event.currentTarget;

      if (detailRow.classList.contains('show')) {
        detailRow.classList.remove('show');
        btn.classList.remove('expanded');
        btn.innerHTML = '<i class="bi bi-chevron-down"></i>';
      } else {
        // Close all other open details
        document.querySelectorAll('.detail-row.show').forEach(row => {
          row.classList.remove('show');
          const otherBtn = row.previousElementSibling.querySelector('.expand-btn');
          if (otherBtn) {
            otherBtn.classList.remove('expanded');
            otherBtn.innerHTML = '<i class="bi bi-chevron-down"></i>';
          }
        });

        detailRow.classList.add('show');
        btn.classList.add('expanded');
        btn.innerHTML = '<i class="bi bi-chevron-up"></i>';
      }
    }

    // Expand all details
    function expandAll() {
      const detailRows = document.querySelectorAll('.detail-row');
      const isAllExpanded = Array.from(detailRows).every(row => row.classList.contains('show'));

      detailRows.forEach((row, index) => {
        const btn = row.previousElementSibling.querySelector('.expand-btn');
        if (isAllExpanded) {
          row.classList.remove('show');
          if (btn) {
            btn.classList.remove('expanded');
            btn.innerHTML = '<i class="bi bi-chevron-down"></i>';
          }
        } else {
          row.classList.add('show');
          if (btn) {
            btn.classList.add('expanded');
            btn.innerHTML = '<i class="bi bi-chevron-up"></i>';
          }
        }
      });
    }

    // View student profile
    function viewStudent(studentId) {
      window.location.href = 'view_student.php?id=' + studentId;
    }

    // Message student
    function messageStudent(studentId) {
      window.location.href = 'messages.php?student=' + studentId;
    }

    // Auto-open first student on page load (optional)
    document.addEventListener('DOMContentLoaded', function () {
      // Uncomment to auto-open first student
      // const firstRow = document.querySelector('.student-row');
      // if (firstRow) {
      //     const btn = firstRow.querySelector('.expand-btn');
      //     if (btn) btn.click();
      // }
    });
  </script>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
</body>

</html>