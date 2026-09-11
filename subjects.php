<?php
// subjects.php
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
$teacher_initials = implode('', array_map(function ($word) {
  return strtoupper(substr($word, 0, 1));
}, explode(' ', $teacher_name)));

// Get subjects taught by this teacher with class and student info
$subject_stmt = $conn->prepare("
    SELECT 
        s.id,
        s.subject_code,
        s.subject_title,
        s.status as subject_status,
        s.created_at,
        c.id as class_id,
        c.name as class_name,
        c.grade as class_grade,
        c.room_no,
        c.description as class_description,
        (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.status = 'Active') as student_count,
        (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.gender = 'Male' AND st.status = 'Active') as male_count,
        (SELECT COUNT(*) FROM students st WHERE st.class_id = c.id AND st.gender = 'Female' AND st.status = 'Active') as female_count
    FROM subjects s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE c.teacher_id = :teacher_id OR s.teacher_id = :teacher_id
    GROUP BY s.id
    ORDER BY s.subject_title ASC
");

$subject_stmt->bindValue(':teacher_id', $teacher_id);
$subject_stmt->execute();
$subjects = $subject_stmt->fetchAll();

// Calculate statistics
$total_subjects = count($subjects);
$total_classes = 0;
$total_students = 0;

// Get unique classes
$class_ids = [];
foreach ($subjects as $subject) {
  if ($subject['class_id'] && !in_array($subject['class_id'], $class_ids)) {
    $class_ids[] = $subject['class_id'];
    $total_classes++;
  }
  $total_students += $subject['student_count'] ?? 0;
}

// Get subject icons mapping
$subject_icons = [
  'Mathematics' => 'bi-calculator',
  'Math' => 'bi-calculator',
  'Physics' => 'bi-magnet',
  'Chemistry' => 'bi-flask',
  'Biology' => 'bi-heart-pulse',
  'Computer' => 'bi-pc-display',
  'Computer Science' => 'bi-pc-display',
  'English' => 'bi-book',
  'Urdu' => 'bi-pencil',
  'Science' => 'bi-microscope',
  'History' => 'bi-clock-history',
  'Geography' => 'bi-globe',
  'Art' => 'bi-palette',
  'Music' => 'bi-music-note',
  'Physical Education' => 'bi-person-walking',
  'default' => 'bi-journal-bookmark'
];

// Get gradient colors for subject cards
$gradients = [
  'bg-grad-1' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
  'bg-grad-2' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
  'bg-grad-3' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
  'bg-grad-4' => 'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
  'bg-grad-5' => 'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
  'bg-grad-6' => 'linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%)',
  'bg-grad-7' => 'linear-gradient(135deg, #fccb90 0%, #d57eeb 100%)',
  'bg-grad-8' => 'linear-gradient(135deg, #89f7fe 0%, #66a6ff 100%)'
];

// Function to get icon for subject
function getSubjectIcon($subject_title)
{
  global $subject_icons;
  foreach ($subject_icons as $key => $icon) {
    if (stripos($subject_title, $key) !== false) {
      return $icon;
    }
  }
  return $subject_icons['default'];
}

// Function to get gradient class
function getGradientClass($index)
{
  $gradients = ['bg-grad-1', 'bg-grad-2', 'bg-grad-3', 'bg-grad-4', 'bg-grad-5', 'bg-grad-6', 'bg-grad-7', 'bg-grad-8'];
  return $gradients[$index % count($gradients)];
}

// Get status badge class
function getStatusBadgeClass($status)
{
  switch ($status) {
    case 'Active':
      return 'bg-success';
    case 'Elective':
      return 'bg-info text-white';
    case 'Inactive':
      return 'bg-danger';
    default:
      return 'bg-secondary';
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Subjects | Teacher Dashboard</title>
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

    /* Subject Cards */
    .subject-card {
      border: none;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
      transition: transform 0.3s, box-shadow 0.3s;
    }

    .subject-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
    }

    .subject-head {
      padding: 25px 20px;
      color: white;
      text-align: center;
      position: relative;
    }

    .subject-head i {
      font-size: 2.5rem;
      opacity: 0.9;
    }

    .subject-head h5 {
      margin-top: 8px;
      margin-bottom: 0;
      font-weight: 600;
    }

    .subject-head small {
      opacity: 0.85;
      font-size: 0.8rem;
    }

    .subject-body {
      padding: 20px;
    }

    .subject-body .info-item {
      display: flex;
      justify-content: space-between;
      padding: 8px 0;
      border-bottom: 1px solid #f0f0f0;
    }

    .subject-body .info-item:last-child {
      border-bottom: none;
    }

    .subject-body .info-item .label {
      color: #6c757d;
      font-size: 0.85rem;
    }

    .subject-body .info-item .value {
      font-weight: 600;
      font-size: 0.85rem;
    }

    .subject-body .description {
      color: #6c757d;
      font-size: 0.85rem;
      margin: 15px 0;
      line-height: 1.5;
      min-height: 40px;
    }

    .subject-body .btn-group {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
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

    .bg-grad-7 {
      background: linear-gradient(135deg, #fccb90 0%, #d57eeb 100%);
    }

    .bg-grad-8 {
      background: linear-gradient(135deg, #89f7fe 0%, #66a6ff 100%);
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

      .subject-head {
        padding: 20px 15px;
      }

      .subject-head i {
        font-size: 2rem;
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
        <a href="subjects.php" class="active"><i class="bi bi-journal-bookmark"></i> My Subjects</a>
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
        <h1 class="td-page-title">My Subjects <small>Subjects you currently teach</small></h1>
        <div class="td-search ms-auto">
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
            <input type="search" id="searchInput" class="form-control border-start-0" placeholder="Search subjects...">
          </div>
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
          <div class="col-6 col-xl-4">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-1"><i class="bi bi-journal-bookmark-fill"></i></div>
              <div>
                <h3><?php echo $total_subjects; ?></h3>
                <p>Total Subjects</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-4">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-4"><i class="bi bi-building"></i></div>
              <div>
                <h3><?php echo $total_classes; ?></h3>
                <p>Classes Taught</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-4">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-2"><i class="bi bi-people-fill"></i></div>
              <div>
                <h3><?php echo $total_students; ?></h3>
                <p>Total Students</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Subject Cards -->
        <?php if (count($subjects) > 0): ?>
          <div class="row g-3" id="subjectContainer">
            <?php foreach ($subjects as $index => $subject):
              $icon = getSubjectIcon($subject['subject_title']);
              $gradient_class = getGradientClass($index);
              $status_class = getStatusBadgeClass($subject['subject_status']);
              $student_count = $subject['student_count'] ?? 0;
              $class_display = $subject['class_name'] ? $subject['class_name'] . ' - ' . $subject['class_grade'] : 'Not Assigned';
              $room_display = $subject['room_no'] ?? 'Not Assigned';
              ?>
              <div class="col-md-6 col-xl-4 subject-item" data-title="<?php echo strtolower($subject['subject_title']); ?>"
                data-class="<?php echo strtolower($subject['class_name'] ?? ''); ?>">
                <div class="card subject-card h-100">
                  <div class="subject-head <?php echo $gradient_class; ?>">
                    <i class="bi <?php echo $icon; ?>"></i>
                    <h5><?php echo htmlspecialchars($subject['subject_title']); ?></h5>
                    <small>
                      <?php echo $subject['subject_status'] ?? 'Active'; ?>
                      · <?php echo htmlspecialchars($subject['subject_code'] ?? 'N/A'); ?>
                    </small>
                    <span class="badge <?php echo $status_class; ?> position-absolute top-0 end-0 m-2">
                      <?php echo htmlspecialchars($subject['subject_status'] ?? 'Active'); ?>
                    </span>
                  </div>
                  <div class="subject-body">
                    <div class="info-item">
                      <span class="label"><i class="bi bi-building me-1"></i> Class</span>
                      <span class="value"><?php echo htmlspecialchars($class_display); ?></span>
                    </div>
                    <div class="info-item">
                      <span class="label"><i class="bi bi-people me-1"></i> Students</span>
                      <span class="value"><?php echo $student_count; ?></span>
                    </div>
                    <div class="info-item">
                      <span class="label"><i class="bi bi-door-open me-1"></i> Room</span>
                      <span class="value"><?php echo htmlspecialchars($room_display); ?></span>
                    </div>
                    <div class="info-item">
                      <span class="label"><i class="bi bi-calendar3 me-1"></i> Created</span>
                      <span
                        class="value"><?php echo $subject['created_at'] ? date('d M Y', strtotime($subject['created_at'])) : 'N/A'; ?></span>
                    </div>

                    <?php if ($subject['class_description']): ?>
                      <p class="description"><?php echo htmlspecialchars($subject['class_description']); ?></p>
                    <?php else: ?>
                      <p class="description text-muted">No description available for this subject.</p>
                    <?php endif; ?>

                    <div class="btn-group">
                      <a href="students.php?class=<?php echo $subject['class_id']; ?>" class="btn btn-primary btn-sm">
                        <i class="bi bi-people"></i> View Students
                      </a>
                      <a href="attendance.php?class=<?php echo $subject['class_id']; ?>"
                        class="btn btn-outline-info btn-sm">
                        <i class="bi bi-calendar2-check"></i> Attendance
                      </a>
                      <a href="results.php?subject=<?php echo $subject['id']; ?>" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-bar-chart-line"></i> Results
                      </a>
                      <a href="assignments.php?subject=<?php echo $subject['id']; ?>"
                        class="btn btn-outline-primary btn-sm">
                        <i class="bi bi-file-earmark-text"></i> Assignments
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="text-center py-5">
            <i class="bi bi-journal-bookmark fs-1 d-block text-muted mb-3"></i>
            <h5>No Subjects Found</h5>
            <p class="text-muted">You are not assigned to any subjects yet.</p>
            <p class="text-muted small">Contact the administrator to assign subjects to your account.</p>
          </div>
        <?php endif; ?>
      </main>

      <!-- Footer -->
      <footer class="td-footer">© 2026 Bright Future School — Teacher Panel.</footer>
    </div>
  </div>

  <script>
    // Search functionality for subjects
    document.getElementById('searchInput')?.addEventListener('keyup', function () {
      const searchTerm = this.value.toLowerCase();
      const subjectItems = document.querySelectorAll('.subject-item');
      let visibleCount = 0;

      subjectItems.forEach(item => {
        const title = item.dataset.title || '';
        const className = item.dataset.class || '';
        const matches = title.includes(searchTerm) || className.includes(searchTerm);

        if (matches) {
          item.style.display = '';
          visibleCount++;
        } else {
          item.style.display = 'none';
        }
      });

      // Show/hide no results message
      let noResultsMsg = document.getElementById('noResultsMsg');
      if (visibleCount === 0 && subjectItems.length > 0) {
        if (!noResultsMsg) {
          noResultsMsg = document.createElement('div');
          noResultsMsg.id = 'noResultsMsg';
          noResultsMsg.className = 'col-12 text-center py-5';
          noResultsMsg.innerHTML = `
                    <i class="bi bi-search fs-1 d-block text-muted mb-3"></i>
                    <h5>No subjects found</h5>
                    <p class="text-muted">Try adjusting your search terms</p>
                `;
          document.getElementById('subjectContainer').appendChild(noResultsMsg);
        }
        noResultsMsg.style.display = '';
      } else if (noResultsMsg) {
        noResultsMsg.style.display = 'none';
      }
    });
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>