<?php
// timetable.php
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

// Get timetable for the teacher
$timetable_stmt = $conn->prepare("
    SELECT 
        t.id,
        t.day_of_week,
        t.time_slot,
        t.room,
        t.created_at,
        s.subject_title,
        s.subject_code,
        s.status as subject_status,
        c.id as class_id,
        c.name as class_name,
        c.grade as class_grade,
        c.room_no as class_room
    FROM timetable t
    LEFT JOIN subjects s ON t.subject_id = s.id
    LEFT JOIN classes c ON t.class_id = c.id
    WHERE t.teacher_id = :teacher_id
    ORDER BY 
        FIELD(t.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
        t.time_slot ASC
");

$timetable_stmt->bindValue(':teacher_id', $teacher_id);
$timetable_stmt->execute();
$timetable_records = $timetable_stmt->fetchAll();

// Define days of week
$days_of_week = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Define time slots (you can customize these)
$time_slots = [
  '08:00' => '08:00 - 09:00',
  '09:00' => '09:00 - 10:00',
  '10:00' => '10:00 - 11:00',
  '11:00' => '11:00 - 12:00',
  '12:00' => '12:00 - 01:00',
  '13:00' => '01:00 - 02:00',
  '14:00' => '02:00 - 03:00',
  '15:00' => '03:00 - 04:00'
];

// Break times
$break_slots = [
  '10:00' => 'Break',
  '12:00' => 'Lunch Break'
];

// Organize timetable data into a 2D array
$timetable_grid = [];
foreach ($time_slots as $slot_key => $slot_display) {
  $timetable_grid[$slot_key] = [];
  foreach ($days_of_week as $day) {
    $timetable_grid[$slot_key][$day] = null;
  }
}

// Fill the grid with actual data
foreach ($timetable_records as $record) {
  $slot_key = $record['time_slot'];
  $day = $record['day_of_week'];

  if (isset($timetable_grid[$slot_key]) && isset($timetable_grid[$slot_key][$day])) {
    $timetable_grid[$slot_key][$day] = $record;
  }
}

// Get statistics
$total_classes = count($timetable_records);
$unique_days = array_unique(array_column($timetable_records, 'day_of_week'));
$unique_subjects = array_unique(array_column($timetable_records, 'subject_title'));
$total_subjects = count(array_filter($unique_subjects));

// Count classes per day
$classes_per_day = [];
foreach ($days_of_week as $day) {
  $count = 0;
  foreach ($timetable_records as $record) {
    if ($record['day_of_week'] == $day) {
      $count++;
    }
  }
  $classes_per_day[$day] = $count;
}

// Get current day
$current_day = date('l'); // Monday, Tuesday, etc.
$current_time = date('H:i');

// Check if current day has classes
$today_classes = array_filter($timetable_records, function ($record) use ($current_day) {
  return $record['day_of_week'] == $current_day;
});

// Get upcoming class
$upcoming_class = null;
foreach ($today_classes as $class) {
  if ($class['time_slot'] > $current_time) {
    $upcoming_class = $class;
    break;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>My Timetable | Teacher Dashboard</title>
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

    /* Timetable Styles */
    .timetable-wrapper {
      overflow-x: auto;
    }

    .timetable-table {
      min-width: 800px;
      width: 100%;
    }

    .timetable-table th {
      background: #f8f9fa;
      font-weight: 600;
      font-size: 0.85rem;
      padding: 12px 8px;
      border: 1px solid #dee2e6;
    }

    .timetable-table td {
      padding: 10px 8px;
      vertical-align: middle;
      border: 1px solid #dee2e6;
      min-height: 80px;
      height: 80px;
    }

    .timetable-table .time-slot {
      font-weight: 600;
      font-size: 0.8rem;
      white-space: nowrap;
      background: #f8f9fa;
      min-width: 100px;
    }

    .timetable-table .class-cell {
      min-width: 120px;
      position: relative;
      transition: background 0.3s;
    }

    .timetable-table .class-cell:hover {
      background: #e8f4fd;
    }

    .class-cell .subject-name {
      font-weight: 600;
      font-size: 0.9rem;
    }

    .class-cell .class-info {
      font-size: 0.75rem;
      color: #6c757d;
    }

    .class-cell .room-info {
      font-size: 0.7rem;
      color: #6c757d;
      display: block;
    }

    .class-cell .free-text {
      color: #adb5bd;
      font-style: italic;
      font-size: 0.85rem;
    }

    .class-cell .break-badge {
      font-size: 0.8rem;
      padding: 4px 12px;
    }

    /* Today highlight */
    .td-today {
      background: #fff3cd !important;
    }

    .td-today th {
      background: #ffeaa7 !important;
    }

    .td-current {
      background: #d4edda !important;
    }

    .td-current .subject-name {
      color: #155724;
    }

    /* Upcoming class card */
    .upcoming-card {
      border-left: 4px solid #3498db;
    }

    /* Mobile responsive */
    .timetable-table td,
    .timetable-table th {
      padding: 6px 4px;
      font-size: 0.8rem;
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

      .timetable-table td,
      .timetable-table th {
        font-size: 0.7rem;
        padding: 4px 2px;
      }

      .class-cell .subject-name {
        font-size: 0.75rem;
      }

      .class-cell .class-info {
        font-size: 0.65rem;
      }

      .class-cell .room-info {
        font-size: 0.6rem;
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
        <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="students.php"><i class="bi bi-people"></i> My Students</a>
        <a href="attendance.php"><i class="bi bi-calendar2-check"></i> Attendance</a>
        <a href="subjects.php"><i class="bi bi-journal-bookmark"></i> My Subjects</a>
        <a href="timetable.php" class="active"><i class="bi bi-clock-history"></i> My Timetable</a>
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
        <h1 class="td-page-title">My Timetable <small>Weekly teaching schedule</small></h1>
        <div class="td-search ms-auto">
          <div class="input-group">
            <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
            <input type="search" id="searchInput" class="form-control border-start-0" placeholder="Search classes...">
          </div>
        </div>
        <a href="notices.php" class="td-icon-btn"><i class="bi bi-bell"></i><span class="td-dot"></span></a>
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

      <!-- Content -->
      <main class="td-content">
        <!-- Statistics Cards -->
        <div class="row g-3 mb-4">
          <div class="col-6 col-xl-3">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-1"><i class="bi bi-calendar2-week"></i></div>
              <div>
                <h3>
                  <?php echo $total_classes; ?>
                </h3>
                <p>Total Classes</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-2"><i class="bi bi-book"></i></div>
              <div>
                <h3>
                  <?php echo $total_subjects; ?>
                </h3>
                <p>Subjects</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-4"><i class="bi bi-calendar2-day"></i></div>
              <div>
                <h3>
                  <?php echo count($unique_days); ?>
                </h3>
                <p>Days</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-5"><i class="bi bi-clock"></i></div>
              <div>
                <h3>
                  <?php echo count($time_slots); ?>
                </h3>
                <p>Time Slots</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Upcoming Class Alert -->
        <?php if ($upcoming_class): ?>
          <div class="alert alert-info alert-dismissible fade show upcoming-card" role="alert">
            <div class="d-flex align-items-center gap-3 flex-wrap">
              <i class="bi bi-clock-history fs-3"></i>
              <div>
                <strong>Upcoming Class:</strong>
                <?php echo htmlspecialchars($upcoming_class['subject_title']); ?>
                <span class="badge bg-primary ms-2">
                  <?php echo htmlspecialchars($upcoming_class['class_name'] . ' - ' . $upcoming_class['class_grade']); ?>
                </span>
                <span class="badge bg-secondary ms-2">
                  <i class="bi bi-clock me-1"></i>
                  <?php echo $upcoming_class['time_slot']; ?>
                </span>
                <?php if ($upcoming_class['room']): ?>
                  <span class="badge bg-info text-white ms-2">
                    <i class="bi bi-door-open me-1"></i>
                    <?php echo htmlspecialchars($upcoming_class['room']); ?>
                  </span>
                <?php endif; ?>
              </div>
              <div class="ms-auto">
                <a href="students.php?class=<?php echo $upcoming_class['class_id']; ?>" class="btn btn-sm btn-primary">
                  <i class="bi bi-people"></i> View Students
                </a>
              </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php elseif ($total_classes > 0): ?>
          <div class="alert alert-secondary alert-dismissible fade show" role="alert">
            <i class="bi bi-info-circle me-2"></i>
            No more classes scheduled for today. Enjoy your free time!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <!-- Timetable -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <span><i class="bi bi-calendar-week me-2 text-primary"></i>Weekly Timetable</span>
            <div class="d-flex gap-2">
              <span class="badge bg-primary-subtle text-primary">Academic Year 2025 - 2026</span>
              <?php if ($total_classes == 0): ?>
                <span class="badge bg-warning text-dark">No classes scheduled</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="card-body p-0">
            <div class="timetable-wrapper">
              <table class="timetable-table table table-bordered mb-0">
                <thead>
                  <tr>
                    <th style="min-width: 100px;">Time</th>
                    <?php foreach ($days_of_week as $day):
                      $is_today = $day == $current_day;
                      ?>
                      <th class="<?php echo $is_today ? 'td-today' : ''; ?>">
                        <?php echo $day; ?>
                        <?php if ($is_today): ?>
                          <span class="badge bg-danger ms-1">Today</span>
                        <?php endif; ?>
                        <br>
                        <small class="text-muted">
                          <?php echo isset($classes_per_day[$day]) ? $classes_per_day[$day] : 0; ?> classes
                        </small>
                      </th>
                    <?php endforeach; ?>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($time_slots as $slot_key => $slot_display):
                    $is_break = isset($break_slots[$slot_key]);
                    $current_slot_time = $slot_key;
                    $is_current_slot = $current_day == date('l') &&
                      $current_time >= $slot_key &&
                      $current_time < date('H:i', strtotime($slot_key . ' +1 hour'));
                    ?>
                    <tr class="<?php echo $is_current_slot ? 'td-current' : ''; ?>">
                      <td class="time-slot">
                        <?php echo $slot_display; ?>
                        <?php if ($is_current_slot): ?>
                          <span class="badge bg-success d-block mt-1" style="font-size: 0.6rem;">
                            <i class="bi bi-play-fill"></i> Now
                          </span>
                        <?php endif; ?>
                      </td>
                      <?php foreach ($days_of_week as $day):
                        $class_data = $timetable_grid[$slot_key][$day] ?? null;
                        $is_today = $day == $current_day;
                        ?>
                        <td
                          class="class-cell <?php echo $is_today && $is_current_slot ? 'td-current' : ''; ?> <?php echo $is_today ? 'td-today' : ''; ?>">
                          <?php if ($is_break): ?>
                            <span class="badge bg-secondary break-badge">
                              <i class="bi bi-cup-hot me-1"></i>
                              <?php echo $break_slots[$slot_key]; ?>
                            </span>
                          <?php elseif ($class_data): ?>
                            <div class="subject-name">
                              <?php echo htmlspecialchars($class_data['subject_title']); ?>
                              <?php if ($class_data['subject_code']): ?>
                                <br>
                                <small class="text-muted" style="font-size: 0.65rem;">
                                  <?php echo htmlspecialchars($class_data['subject_code']); ?>
                                </small>
                              <?php endif; ?>
                            </div>
                            <div class="class-info">
                              <?php echo htmlspecialchars($class_data['class_name'] ?? 'N/A'); ?>
                              <?php if ($class_data['class_grade']): ?>
                                -
                                <?php echo htmlspecialchars($class_data['class_grade']); ?>
                              <?php endif; ?>
                            </div>
                            <?php if ($class_data['room']): ?>
                              <span class="room-info">
                                <i class="bi bi-door-open me-1"></i>
                                <?php echo htmlspecialchars($class_data['room']); ?>
                              </span>
                            <?php endif; ?>
                            <?php if ($class_data['class_room']): ?>
                              <span class="room-info text-muted">
                                <i class="bi bi-building me-1"></i>
                                <?php echo htmlspecialchars($class_data['class_room']); ?>
                              </span>
                            <?php endif; ?>
                          <?php else: ?>
                            <span class="free-text">— Free —</span>
                          <?php endif; ?>
                        </td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php if ($total_classes > 0): ?>
            <div class="card-footer bg-light">
              <div class="d-flex gap-3 flex-wrap">
                <span class="badge bg-success"><i class="bi bi-play-fill me-1"></i> Current</span>
                <span class="badge bg-warning text-dark"><i class="bi bi-calendar me-1"></i> Today</span>
                <span class="badge bg-secondary"><i class="bi bi-cup-hot me-1"></i> Break</span>
                <span class="text-muted small ms-auto">
                  <i class="bi bi-clock me-1"></i>
                  Last updated:
                  <?php echo date('d M Y, h:i A'); ?>
                </span>
              </div>
            </div>
          <?php endif; ?>
        </div>

        <!-- No timetable message -->
        <?php if ($total_classes == 0): ?>
          <div class="text-center py-5 mt-3">
            <i class="bi bi-calendar2-x fs-1 d-block text-muted mb-3"></i>
            <h5>No Timetable Found</h5>
            <p class="text-muted">You don't have any classes scheduled in your timetable.</p>
            <p class="text-muted small">Contact the administrator to set up your timetable.</p>
          </div>
        <?php endif; ?>
      </main>

      <!-- Footer -->
      <footer class="td-footer">© 2026 Bright Future School — Teacher Panel.</footer>
    </div>
  </div>

  <script>
    // Search functionality for timetable
    document.getElementById('searchInput')?.addEventListener('keyup', function () {
      const searchTerm = this.value.toLowerCase();
      const rows = document.querySelectorAll('.timetable-table tbody tr');

      rows.forEach(row => {
        const cells = row.querySelectorAll('.class-cell');
        let found = false;

        cells.forEach(cell => {
          const text = cell.textContent.toLowerCase();
          if (text.includes(searchTerm)) {
            found = true;
            // Highlight matching cells
            if (searchTerm.length > 0) {
              cell.style.background = '#fff3cd';
            } else {
              cell.style.background = '';
            }
          } else if (searchTerm.length > 0) {
            cell.style.background = '';
          }
        });

        // Show/hide row based on search
        if (searchTerm.length > 0) {
          row.style.display = found ? '' : 'none';
        } else {
          row.style.display = '';
          // Reset all cell backgrounds
          row.querySelectorAll('.class-cell').forEach(cell => {
            cell.style.background = '';
          });
        }
      });
    });

    // Auto-refresh every 5 minutes to update "Now" indicators
    setTimeout(function () {
      location.reload();
    }, 300000); // 5 minutes
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>