<?php
// notices.php
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

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  // Create new notice
  if (isset($_POST['create_notice'])) {
    $title = $_POST['title'] ?? '';
    $category = $_POST['category'] ?? '';
    $details = $_POST['details'] ?? '';
    $posted_by = $_POST['posted_by'] ?? $teacher_name;

    if ($title && $category && $details) {
      $insert_stmt = $conn->prepare("
                INSERT INTO notices (title, category, posted_by, details)
                VALUES (:title, :category, :posted_by, :details)
            ");

      $insert_stmt->bindValue(':title', $title);
      $insert_stmt->bindValue(':category', $category);
      $insert_stmt->bindValue(':posted_by', $posted_by);
      $insert_stmt->bindValue(':details', $details);

      if ($insert_stmt->execute()) {
        $success_message = "Notice created successfully!";
      } else {
        $error_message = "Error creating notice.";
      }
    } else {
      $error_message = "Please fill in all required fields.";
    }
  }

  // Delete notice
  if (isset($_POST['delete_notice'])) {
    $notice_id = $_POST['notice_id'] ?? 0;

    if ($notice_id) {
      $delete_stmt = $conn->prepare("
                DELETE FROM notices WHERE id = :id
            ");
      $delete_stmt->bindValue(':id', $notice_id);

      if ($delete_stmt->execute()) {
        $success_message = "Notice deleted successfully!";
      } else {
        $error_message = "Error deleting notice.";
      }
    }
  }
}

// Get filter parameters
$search = $_GET['search'] ?? '';
$category_filter = $_GET['category'] ?? '';

// Build notices query
$query = "SELECT * FROM notices WHERE 1=1";
$params = [];

// Apply search filter
if (!empty($search)) {
  $query .= " AND (title LIKE :search OR details LIKE :search OR posted_by LIKE :search)";
  $params[':search'] = '%' . $search . '%';
}

// Apply category filter
if (!empty($category_filter)) {
  $query .= " AND category = :category";
  $params[':category'] = $category_filter;
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
foreach ($params as $key => $value) {
  $stmt->bindValue($key, $value);
}
$stmt->execute();
$notices = $stmt->fetchAll();

// Get categories for filter
$category_stmt = $conn->prepare("
    SELECT DISTINCT category FROM notices ORDER BY category
");
$category_stmt->execute();
$categories = $category_stmt->fetchAll();

// Category badge colors
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
    case 'training':
      return 'bg-dark-subtle text-dark';
    case 'meeting':
      return 'bg-purple-subtle text-purple';
    default:
      return 'bg-light text-dark';
  }
}

// Category icon
function getCategoryIcon($category)
{
  switch (strtolower($category)) {
    case 'academic':
      return 'bi-book';
    case 'administrative':
      return 'bi-building';
    case 'sports':
      return 'bi-trophy';
    case 'holiday':
      return 'bi-calendar-event';
    case 'event':
      return 'bi-megaphone';
    case 'staff':
      return 'bi-people';
    case 'training':
      return 'bi-mortarboard';
    case 'meeting':
      return 'bi-people-fill';
    default:
      return 'bi-info-circle';
  }
}

// Get status (active/expired based on date)
function getNoticeStatus($created_at)
{
  $days_old = (time() - strtotime($created_at)) / (60 * 60 * 24);
  if ($days_old > 30) {
    return ['label' => 'Expired', 'class' => 'bg-secondary'];
  } elseif ($days_old > 7) {
    return ['label' => 'Old', 'class' => 'bg-warning text-dark'];
  } else {
    return ['label' => 'Active', 'class' => 'bg-success'];
  }
}

// Get time ago
function timeAgo($datetime)
{
  $time = strtotime($datetime);
  $now = time();
  $diff = $now - $time;

  if ($diff < 60) {
    return $diff . ' seconds ago';
  } elseif ($diff < 3600) {
    return floor($diff / 60) . ' minutes ago';
  } elseif ($diff < 86400) {
    return floor($diff / 3600) . ' hours ago';
  } elseif ($diff < 604800) {
    return floor($diff / 86400) . ' days ago';
  } elseif ($diff < 2592000) {
    return floor($diff / 604800) . ' weeks ago';
  } elseif ($diff < 31536000) {
    return floor($diff / 2592000) . ' months ago';
  } else {
    return floor($diff / 31536000) . ' years ago';
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Notices | Teacher Dashboard</title>
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

    /* Notice Cards */
    .notice-card {
      border: none;
      border-radius: 15px;
      box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
      transition: transform 0.3s, box-shadow 0.3s;
      overflow: hidden;
    }

    .notice-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
    }

    .notice-card .card-body {
      padding: 20px;
    }

    .notice-card .notice-title {
      font-size: 1.1rem;
      font-weight: 600;
      margin-bottom: 8px;
    }

    .notice-card .notice-details {
      font-size: 0.9rem;
      color: #6c757d;
      line-height: 1.6;
      display: -webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .notice-card .notice-meta {
      font-size: 0.8rem;
      color: #6c757d;
    }

    .notice-card .notice-meta i {
      color: #0d6efd;
      width: 18px;
    }

    .notice-card .category-badge {
      font-size: 0.75rem;
      padding: 4px 12px;
    }

    /* Gradient backgrounds */
    .bg-grad-1 {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .bg-grad-2 {
      background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }

    .bg-grad-3 {
      background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .bg-grad-4 {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .bg-grad-5 {
      background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }

    /* Custom badge colors */
    .bg-purple-subtle {
      background: #e8d5f5;
      color: #6f42c1;
    }

    .text-purple {
      color: #6f42c1;
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

      .notice-card .card-body {
        padding: 15px;
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
        <a href="timetable.php"><i class="bi bi-clock-history"></i> My Timetable</a>
        <div class="td-nav-title">Academics</div>
        <a href="assignments.php"><i class="bi bi-file-earmark-text"></i> Assignments</a>
        <a href="exams.php"><i class="bi bi-pencil-square"></i> Exams</a>
        <a href="results.php"><i class="bi bi-bar-chart-line"></i> Results</a>
        <div class="td-nav-title">Communication</div>
        <a href="notices.php" class="active"><i class="bi bi-megaphone"></i> Notices</a>
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
        <h1 class="td-page-title">Notices <small>School announcements and circulars</small></h1>
        <div class="td-search ms-auto">
          <form method="GET" action="" class="d-flex">
            <div class="input-group">
              <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
              <input type="search" name="search" class="form-control border-start-0" placeholder="Search notices..."
                value="<?php echo htmlspecialchars($search); ?>">
              <input type="hidden" name="category" value="<?php echo htmlspecialchars($category_filter); ?>">
            </div>
          </form>
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
        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <?php
        $total_notices = count($notices);
        $active_notices = 0;
        $categories_count = [];

        foreach ($notices as $notice) {
          $status = getNoticeStatus($notice['created_at']);
          if ($status['label'] == 'Active') {
            $active_notices++;
          }
          $cat = $notice['category'];
          if (!isset($categories_count[$cat])) {
            $categories_count[$cat] = 0;
          }
          $categories_count[$cat]++;
        }
        ?>
        <div class="row g-3 mb-4">
          <div class="col-6 col-xl-4">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-1"><i class="bi bi-megaphone-fill"></i></div>
              <div>
                <h3>
                  <?php echo $total_notices; ?>
                </h3>
                <p>Total Notices</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-4">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-2"><i class="bi bi-check-circle"></i></div>
              <div>
                <h3>
                  <?php echo $active_notices; ?>
                </h3>
                <p>Active Notices</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-4">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-4"><i class="bi bi-tags"></i></div>
              <div>
                <h3>
                  <?php echo count($categories_count); ?>
                </h3>
                <p>Categories</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Filter and Create Notice -->
        <div class="row g-3 mb-4">
          <div class="col-md-8">
            <div class="card">
              <div class="card-body">
                <form method="GET" action="" class="row g-2">
                  <div class="col-md-6">
                    <input type="search" name="search" class="form-control" placeholder="Search notices..."
                      value="<?php echo htmlspecialchars($search); ?>">
                  </div>
                  <div class="col-md-4">
                    <select name="category" class="form-select">
                      <option value="">All Categories</option>
                      <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
                          <?php echo htmlspecialchars($cat['category']); ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-funnel"></i> Filter</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <button class="btn btn-primary w-100 h-100" data-bs-toggle="modal" data-bs-target="#createNoticeModal">
              <i class="bi bi-plus-lg"></i> Post New Notice
            </button>
          </div>
        </div>

        <!-- Notices Grid -->
        <div class="row g-3">
          <?php if (count($notices) > 0): ?>
            <?php foreach ($notices as $notice):
              $status = getNoticeStatus($notice['created_at']);
              $category_badge = getCategoryBadgeClass($notice['category']);
              $category_icon = getCategoryIcon($notice['category']);
              $time_ago = timeAgo($notice['created_at']);
              ?>
              <div class="col-md-6 col-xl-4">
                <div class="card notice-card h-100">
                  <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                      <span class="badge <?php echo $category_badge; ?> category-badge">
                        <i class="bi <?php echo $category_icon; ?> me-1"></i>
                        <?php echo htmlspecialchars($notice['category']); ?>
                      </span>
                      <span class="badge <?php echo $status['class']; ?>">
                        <?php echo $status['label']; ?>
                      </span>
                    </div>
                    <h6 class="notice-title">
                      <?php echo htmlspecialchars($notice['title']); ?>
                    </h6>
                    <p class="notice-details">
                      <?php echo htmlspecialchars($notice['details']); ?>
                    </p>
                    <div class="notice-meta mt-3">
                      <div><i class="bi bi-calendar3"></i>
                        <?php echo date('d M Y, h:i A', strtotime($notice['created_at'])); ?>
                      </div>
                      <div><i class="bi bi-clock"></i>
                        <?php echo $time_ago; ?>
                      </div>
                      <div><i class="bi bi-person"></i> Posted By:
                        <?php echo htmlspecialchars($notice['posted_by']); ?>
                      </div>
                    </div>
                    <div class="mt-3 d-flex gap-2">
                      <button class="btn btn-sm btn-primary" onclick="viewNotice(<?php echo $notice['id']; ?>)">
                        <i class="bi bi-eye"></i> View Details
                      </button>
                      <?php if ($notice['posted_by'] == $teacher_name || $notice['posted_by'] == 'Teacher'): ?>
                        <button class="btn btn-sm btn-outline-danger"
                          onclick="deleteNotice(<?php echo $notice['id']; ?>, '<?php echo addslashes($notice['title']); ?>')">
                          <i class="bi bi-trash"></i>
                        </button>
                      <?php endif; ?>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="col-12">
              <div class="text-center py-5">
                <i class="bi bi-megaphone fs-1 d-block text-muted mb-3"></i>
                <h5>No Notices Found</h5>
                <p class="text-muted">There are no notices available at the moment.</p>
                <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#createNoticeModal">
                  <i class="bi bi-plus-lg"></i> Post Your First Notice
                </button>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </main>

      <!-- Footer -->
      <footer class="td-footer">© 2026 Bright Future School — Teacher Panel.</footer>
    </div>
  </div>

  <!-- Create Notice Modal -->
  <div class="modal fade" id="createNoticeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-megaphone me-2 text-primary"></i>Post New Notice</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Notice Title <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control" placeholder="Enter notice title" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Category <span class="text-danger">*</span></label>
                <select name="category" class="form-select" required>
                  <option value="">Select Category</option>
                  <option value="Academic">Academic</option>
                  <option value="Administrative">Administrative</option>
                  <option value="Sports">Sports</option>
                  <option value="Holiday">Holiday</option>
                  <option value="Event">Event</option>
                  <option value="Staff">Staff</option>
                  <option value="Training">Training</option>
                  <option value="Meeting">Meeting</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Posted By</label>
                <input type="text" name="posted_by" class="form-control"
                  value="<?php echo htmlspecialchars($teacher_name); ?>" placeholder="Your name">
              </div>
              <div class="col-12">
                <label class="form-label">Details <span class="text-danger">*</span></label>
                <textarea name="details" class="form-control" rows="5" placeholder="Enter notice details..."
                  required></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="create_notice" class="btn btn-primary">
              <i class="bi bi-megaphone"></i> Post Notice
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- View Notice Modal -->
  <div class="modal fade" id="viewNoticeModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-file-text me-2 text-primary"></i>Notice Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="noticeDetails">
          <!-- Content loaded via JavaScript -->
        </div>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
  <div class="modal fade" id="deleteNoticeModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title text-danger"><i class="bi bi-exclamation-triangle me-2"></i>Delete Notice</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="">
          <input type="hidden" name="notice_id" id="delete_notice_id">
          <div class="modal-body">
            <p>Are you sure you want to delete the notice: <strong id="delete_notice_title"></strong>?</p>
            <p class="text-danger small">This action cannot be undone.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="delete_notice" class="btn btn-danger">
              <i class="bi bi-trash"></i> Delete Notice
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // View notice details
    function viewNotice(id) {
      fetch('ajax/get_notice.php?id=' + id)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const status = data.status;
            const statusClass = status === 'Active' ? 'bg-success' : 'bg-secondary';
            const categoryBadge = getCategoryBadgeClass(data.category);
            const categoryIcon = getCategoryIcon(data.category);

            document.getElementById('noticeDetails').innerHTML = `
                        <div class="mb-3">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="badge ${categoryBadge}">
                                    <i class="bi ${categoryIcon} me-1"></i>
                                    ${data.category}
                                </span>
                                <span class="badge ${statusClass}">${status}</span>
                            </div>
                            <h4>${data.title}</h4>
                            <hr>
                            <p>${data.details}</p>
                            <hr>
                            <div class="text-muted small">
                                <div><i class="bi bi-calendar3 me-2"></i> ${data.created_at_formatted}</div>
                                <div><i class="bi bi-clock me-2"></i> ${data.time_ago}</div>
                                <div><i class="bi bi-person me-2"></i> Posted By: ${data.posted_by}</div>
                            </div>
                        </div>
                    `;

            var modal = new bootstrap.Modal(document.getElementById('viewNoticeModal'));
            modal.show();
          } else {
            alert('Error loading notice details');
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('An error occurred while loading the notice.');
        });
    }

    // Delete notice
    function deleteNotice(id, title) {
      document.getElementById('delete_notice_id').value = id;
      document.getElementById('delete_notice_title').textContent = title;

      var modal = new bootstrap.Modal(document.getElementById('deleteNoticeModal'));
      modal.show();
    }

    // Helper functions for category styling
    function getCategoryBadgeClass(category) {
      const map = {
        'Academic': 'bg-primary-subtle text-primary',
        'Administrative': 'bg-info-subtle text-info-emphasis',
        'Sports': 'bg-success-subtle text-success-emphasis',
        'Holiday': 'bg-danger-subtle text-danger-emphasis',
        'Event': 'bg-warning-subtle text-warning-emphasis',
        'Staff': 'bg-secondary-subtle text-secondary',
        'Training': 'bg-dark-subtle text-dark',
        'Meeting': 'bg-purple-subtle text-purple'
      };
      return map[category] || 'bg-light text-dark';
    }

    function getCategoryIcon(category) {
      const map = {
        'Academic': 'bi-book',
        'Administrative': 'bi-building',
        'Sports': 'bi-trophy',
        'Holiday': 'bi-calendar-event',
        'Event': 'bi-megaphone',
        'Staff': 'bi-people',
        'Training': 'bi-mortarboard',
        'Meeting': 'bi-people-fill'
      };
      return map[category] || 'bi-info-circle';
    }
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>