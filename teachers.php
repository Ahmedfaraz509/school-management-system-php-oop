<?php

require_once __DIR__ . '/teacher_process.php'; // contains class Teacher
require_once __DIR__ . '/../database/connect.php';

$insertSuccess = null;
$updateSuccess = null;
$deleteSuccess = null;
$errors = [];

$teacherModel = new Teacher($conn, '', '', ''); // throwaway instance just to call getAll/getById/etc.

// ---------------------------------------------------------
// ADD TEACHER
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_teacher'])) {

  $fullName = trim($_POST['full_name'] ?? '');
  $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
  $rawPassword = $_POST['password'] ?? '';
  $phone = !empty($_POST['phone']) ? trim($_POST['phone']) : null;
  $qualification = !empty($_POST['qualification']) ? trim($_POST['qualification']) : null;
  $joiningDate = !empty($_POST['joining_date']) ? $_POST['joining_date'] : null;

  $statusMap = ['Active' => 'active', 'On Leave' => 'on_leave'];
  $status = $statusMap[$_POST['status'] ?? ''] ?? 'active';

  if (empty($fullName))
    $errors[] = "Full name is required.";
  if (!$email)
    $errors[] = "A valid email address is required.";
  if (empty($rawPassword)) {
    $errors[] = "Password is required.";
  } elseif (strlen($rawPassword) < 6) {
    $errors[] = "Password must be at least 6 characters.";
  }

  if ($email) {
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
    $checkStmt->execute([':email' => $email]);
    if ($checkStmt->fetch()) {
      $errors[] = "A user with this email already exists.";
    }
  }

  $photoPath = null;
  $isPhotoUploaded = !empty($_FILES['photo']['name']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK;
  $allowedMimetypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
  $fileType = null;
  $tmpFilePath = null;

  if ($isPhotoUploaded) {
    $tmpFilePath = $_FILES['photo']['tmp_name'];
    $fileType = mime_content_type($tmpFilePath);
    if (!array_key_exists($fileType, $allowedMimetypes)) {
      $errors[] = "Photo must be a JPEG, PNG, or WEBP image.";
    }
  }

  if (empty($errors)) {
    try {
      $conn->beginTransaction();

      if ($isPhotoUploaded) {
        $uploadDir = 'uploads/teachers/';
        if (!is_dir($uploadDir))
          mkdir($uploadDir, 0755, true);

        $ext = $allowedMimetypes[$fileType];
        $filename = uniqid('teacher_', true) . '.' . $ext;
        $targetPath = $uploadDir . $filename;

        if (move_uploaded_file($tmpFilePath, $targetPath)) {
          $photoPath = $targetPath;
        } else {
          throw new Exception("Failed to move uploaded photo.");
        }
      }

      $hashedPassword = password_hash($rawPassword, PASSWORD_DEFAULT);

      $userStmt = $conn->prepare("
        INSERT INTO users (name, email, password, role, status)
        VALUES (:name, :email, :password, 'teacher', :status)
      ");
      $userStmt->execute([
        ':name' => $fullName,
        ':email' => $email,
        ':password' => $hashedPassword,
        ':status' => $status === 'on_leave' ? 'active' : $status,
      ]);
      $userId = (int) $conn->lastInsertId();

      $teacherId = 'TCH-' . strtoupper(substr(uniqid(), -6));

      $teacher = new Teacher(
        conn: $conn,
        fullName: $fullName,
        email: $email,
        password: $hashedPassword,
        userId: $userId,
        teacherId: $teacherId,
        phone: $phone,
        qualification: $qualification,
        joiningDate: $joiningDate,
        photo: $photoPath,
        status: $status
      );

      $insertSuccess = $teacher->insert();

      if (!$insertSuccess) {
        throw new Exception("Insert returned false.");
      }

      $conn->commit();
    } catch (Throwable $e) {
      if ($conn->inTransaction())
        $conn->rollBack();
      $insertSuccess = false;
      $errors[] = "Something went wrong while saving the teacher.";
      if ($photoPath && file_exists($photoPath))
        unlink($photoPath);
      error_log('Add teacher failed: ' . $e->getMessage());
    }
  }
}

// ---------------------------------------------------------
// UPDATE TEACHER
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_teacher'])) {
  $editId = (int) ($_POST['edit_id'] ?? 0);
  $fullName = trim($_POST['edit_full_name'] ?? '');
  $email = filter_var(trim($_POST['edit_email'] ?? ''), FILTER_VALIDATE_EMAIL);
  $phone = !empty($_POST['edit_phone']) ? trim($_POST['edit_phone']) : null;
  $qualification = !empty($_POST['edit_qualification']) ? trim($_POST['edit_qualification']) : null;
  $joiningDate = !empty($_POST['edit_joining_date']) ? $_POST['edit_joining_date'] : null;

  $statusMap = ['Active' => 'active', 'On Leave' => 'on_leave'];
  $status = $statusMap[$_POST['edit_status'] ?? ''] ?? 'active';

  if ($editId <= 0)
    $errors[] = "Invalid teacher record.";
  if (empty($fullName))
    $errors[] = "Full name is required.";
  if (!$email)
    $errors[] = "A valid email address is required.";

  if (empty($errors)) {
    $existing = $teacherModel->getById($editId);
    if (!$existing) {
      $errors[] = "Teacher not found.";
    } else {
      try {
        $updated = new Teacher(
          conn: $conn,
          fullName: $fullName,
          email: $email,
          password: $existing['password'], // unchanged
          teacherId: $existing['teacher_id'],
          phone: $phone,
          gender: $existing['gender'],
          dateOfBirth: $existing['date_of_birth'],
          qualification: $qualification,
          joiningDate: $joiningDate,
          address: $existing['address'],
          bio: $existing['bio'],
          photo: $existing['photo'],
          status: $status
        );
        $updateSuccess = $updated->update($editId);
      } catch (Throwable $e) {
        $updateSuccess = false;
        $errors[] = "Something went wrong while updating the teacher.";
        error_log('Update teacher failed: ' . $e->getMessage());
      }
    }
  }
}

// ---------------------------------------------------------
// DELETE TEACHER
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_teacher'])) {
  $deleteId = (int) ($_POST['delete_id'] ?? 0);
  if ($deleteId > 0) {
    try {
      $deleteSuccess = $teacherModel->delete($deleteId);
    } catch (Throwable $e) {
      $deleteSuccess = false;
      error_log('Delete teacher failed: ' . $e->getMessage());
    }
  }
}

// ---------------------------------------------------------
// FETCH DATA FOR DISPLAY
// ---------------------------------------------------------
$allTeachers = $teacherModel->getAll();
$totalTeachers = count($allTeachers);
$activeTeachers = count(array_filter($allTeachers, fn($t) => $t['status'] === 'active'));
$onLeaveTeachers = count(array_filter($allTeachers, fn($t) => $t['status'] === 'on_leave'));
$activePct = $totalTeachers > 0 ? round(($activeTeachers / $totalTeachers) * 100, 1) : 0;

function teacherInitials(string $name): string
{
  $parts = preg_split('/\s+/', trim($name));
  $parts = array_filter($parts, fn($p) => !in_array(strtolower(rtrim($p, '.')), ['dr', 'mr', 'mrs', 'ms', 'miss']));
  $parts = array_values($parts);
  $initials = '';
  foreach (array_slice($parts, 0, 2) as $p) {
    $initials .= strtoupper(mb_substr($p, 0, 1));
  }
  return $initials ?: '?';
}

function statusBadge(string $status): string
{
  return match ($status) {
    'active' => '<span class="badge-subtle badge-subtle-success">Active</span>',
    'on_leave' => '<span class="badge-subtle badge-subtle-warning">On Leave</span>',
    default => '<span class="badge-subtle badge-subtle-secondary">Inactive</span>',
  };
}

function formatStatusLabel(string $status): string
{
  return match ($status) {
    'active' => 'Active',
    'on_leave' => 'On Leave',
    default => 'Inactive',
  };
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Faculty & Teachers - EduPulse School Management System</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
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
          <li class="nav-item">
            <a href="index.php" class="nav-link">
              <i class="bi bi-grid-1x2-fill"></i>
              <span class="nav-text">Dashboard</span>
            </a>
          </li>
        </ul>

        <div class="nav-section-title">Academics</div>
        <ul class="sidebar-menu">
          <li class="nav-item">
            <a href="students.php" class="nav-link">
              <i class="bi bi-people-fill"></i>
              <span class="nav-text">Students</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="teachers.php" class="nav-link active">
              <i class="bi bi-person-video3"></i>
              <span class="nav-text">Teachers</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="parents.php" class="nav-link">
              <i class="bi bi-person-heart"></i>
              <span class="nav-text">Parents</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="classes.php" class="nav-link">
              <i class="bi bi-door-open-fill"></i>
              <span class="nav-text">Classes</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="subjects.php" class="nav-link">
              <i class="bi bi-book-half"></i>
              <span class="nav-text">Subjects</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="timetable.php" class="nav-link">
              <i class="bi bi-calendar3-range"></i>
              <span class="nav-text">Timetable</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="assignments.php" class="nav-link">
              <i class="bi bi-journal-check"></i>
              <span class="nav-text">Assignments</span>
            </a>
          </li>
        </ul>

        <div class="nav-section-title">Operations</div>
        <ul class="sidebar-menu">
          <li class="nav-item">
            <a href="attendance.php" class="nav-link">
              <i class="bi bi-check2-square"></i>
              <span class="nav-text">Attendance</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="exams.php" class="nav-link">
              <i class="bi bi-pencil-square"></i>
              <span class="nav-text">Exams</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="results.php" class="nav-link">
              <i class="bi bi-trophy-fill"></i>
              <span class="nav-text">Results</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="fees.php" class="nav-link">
              <i class="bi bi-cash-stack"></i>
              <span class="nav-text">Fees</span>
            </a>
          </li>
        </ul>

        <div class="nav-section-title">Communication</div>
        <ul class="sidebar-menu">
          <li class="nav-item">
            <a href="notices.php" class="nav-link">
              <i class="bi bi-megaphone-fill"></i>
              <span class="nav-text">Notices</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="events.php" class="nav-link">
              <i class="bi bi-calendar-event-fill"></i>
              <span class="nav-text">Events</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="messages.php" class="nav-link">
              <i class="bi bi-chat-dots-fill"></i>
              <span class="nav-text">Messages</span>
              <span class="badge bg-danger rounded-pill ms-auto">4</span>
            </a>
          </li>
        </ul>

        <div class="nav-section-title">System</div>
        <ul class="sidebar-menu">
          <li class="nav-item">
            <a href="reports.php" class="nav-link">
              <i class="bi bi-bar-chart-line-fill"></i>
              <span class="nav-text">Reports</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="profile.php" class="nav-link">
              <i class="bi bi-person-circle"></i>
              <span class="nav-text">Profile</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="settings.php" class="nav-link">
              <i class="bi bi-gear-fill"></i>
              <span class="nav-text">Settings</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="#" class="nav-link text-danger" data-bs-toggle="modal" data-bs-target="#logoutModal">
              <i class="bi bi-box-arrow-right text-danger"></i>
              <span class="nav-text">Logout</span>
            </a>
          </li>
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
      <header class="app-topbar">
        <div class="topbar-left">
          <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" aria-label="Toggle Sidebar">
            <i class="bi bi-list"></i>
          </button>
          <div class="search-input-group">
            <i class="bi bi-search search-icon"></i>
            <input type="text" class="form-control" placeholder="Search anything... (Ctrl + K)">
          </div>
        </div>

        <div class="topbar-right">
          <div class="dropdown">
            <button class="topbar-btn" type="button" data-bs-toggle="dropdown">
              <i class="bi bi-bell"></i>
              <span class="notification-badge"></span>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow border-0 p-0" style="width: 300px;">
              <div class="p-3 border-bottom">
                <h6 class="m-0 fw-bold">Notifications</h6>
              </div>
              <div class="p-3 small text-muted">No new alerts at this time.</div>
            </div>
          </div>

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

        <?php if ($insertSuccess === true): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            Teacher added successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php elseif ($updateSuccess === true): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            Teacher updated successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php elseif ($deleteSuccess === true): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            Teacher removed successfully.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php elseif (!empty($errors)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
              <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
              <?php endforeach; ?>
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="page-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
          <div>
            <h1 class="page-title">Faculty & Teachers</h1>
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Teachers</li>
              </ol>
            </nav>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm"
              onclick="exportTableDemo('Faculty Directory')">
              <i class="bi bi-download me-1"></i> Export
            </button>
            <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2"
              data-bs-toggle="modal" data-bs-target="#addTeacherModal">
              <i class="bi bi-person-plus-fill"></i> Add Teacher
            </button>
          </div>
        </div>

        <!-- 3 TEACHER STATS CARDS -->
        <div class="row g-3 mb-4">
          <div class="col-md-4">
            <div class="stat-card">
              <div class="d-flex justify-content-between align-items-start">
                <div class="stat-icon-wrapper stat-icon-indigo">
                  <i class="bi bi-people-fill"></i>
                </div>
              </div>
              <div class="stat-title">Total Teachers</div>
              <div class="stat-number"><?= $totalTeachers ?></div>
              <p class="stat-desc">Full-time and adjunct instructors</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="stat-card">
              <div class="d-flex justify-content-between align-items-start">
                <div class="stat-icon-wrapper stat-icon-emerald">
                  <i class="bi bi-check-circle-fill"></i>
                </div>
                <span class="badge bg-success-subtle text-success"><?= $activePct ?>% Active</span>
              </div>
              <div class="stat-title">Active Teachers</div>
              <div class="stat-number"><?= $activeTeachers ?></div>
              <p class="stat-desc">Currently assigned to classes</p>
            </div>
          </div>
          <div class="col-md-4">
            <div class="stat-card">
              <div class="d-flex justify-content-between align-items-start">
                <div class="stat-icon-wrapper stat-icon-rose">
                  <i class="bi bi-pause-circle-fill"></i>
                </div>
                <span class="badge bg-secondary-subtle text-secondary">On Leave</span>
              </div>
              <div class="stat-title">Inactive / On Leave</div>
              <div class="stat-number"><?= $onLeaveTeachers ?></div>
              <p class="stat-desc">Sabbatical or medical leave</p>
            </div>
          </div>
        </div>

        <!-- Filter Bar -->
        <div class="filter-card">
          <div class="row g-3 align-items-center">
            <div class="col-md-5">
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0 ps-0 table-search-input"
                  placeholder="Search teacher by name, email or ID..." data-table="#teachersTable">
              </div>
            </div>
            <div class="col-sm-6 col-md-3">
              <select class="form-select table-filter-select" data-column="2">
                <option value="all">All Subjects</option>
              </select>
            </div>
            <div class="col-sm-6 col-md-2">
              <select class="form-select table-filter-select" data-column="6">
                <option value="all">All Status</option>
                <option value="Active">Active</option>
                <option value="On Leave">On Leave</option>
              </select>
            </div>
            <div class="col-md-2">
              <button type="button" class="btn btn-outline-secondary w-100 btn-reset-filters">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
              </button>
            </div>
          </div>
        </div>

        <!-- Teachers Table -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold">Faculty Roster</span>
            <span class="badge bg-light text-dark border"><?= $totalTeachers ?> Displayed</span>
          </div>
          <div class="table-responsive">
            <table class="table table-custom align-middle" id="teachersTable">
              <thead>
                <tr>
                  <th>Teacher ID</th>
                  <th>Teacher Name</th>
                  <th>Subject</th>
                  <th>Assigned Class</th>
                  <th>Email Address</th>
                  <th>Phone Number</th>
                  <th>Joining Date</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($allTeachers)): ?>
                  <tr>
                    <td colspan="9" class="text-center text-muted py-4">No teachers found. Click "Add Teacher" to create
                      one.</td>
                  </tr>
                <?php endif; ?>

                <?php foreach ($allTeachers as $t): ?>
                  <?php
                  $joinDateFormatted = $t['joining_date'] ? date('M d, Y', strtotime($t['joining_date'])) : '—';
                  $dobValue = $t['date_of_birth'] ?? '';
                  ?>
                  <tr>
                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($t['teacher_id']) ?></span>
                    </td>
                    <td>
                      <div class="avatar-cell">
                        <?php if (!empty($t['photo']) && file_exists(__DIR__ . '/' . $t['photo'])): ?>
                          <img src="<?= htmlspecialchars($t['photo']) ?>" alt="<?= htmlspecialchars($t['full_name']) ?>"
                            class="avatar-img">
                        <?php else: ?>
                          <div class="avatar-initials"><?= htmlspecialchars(teacherInitials($t['full_name'])) ?></div>
                        <?php endif; ?>
                        <div class="avatar-info">
                          <span class="primary-text"><?= htmlspecialchars($t['full_name']) ?></span>
                          <span class="sub-text"><?= htmlspecialchars($t['qualification'] ?: '—') ?></span>
                        </div>
                      </div>
                    </td>
                    <td>—</td>
                    <td>—</td>
                    <td><?= htmlspecialchars($t['email']) ?></td>
                    <td><?= htmlspecialchars($t['phone'] ?: '—') ?></td>
                    <td><?= $joinDateFormatted ?></td>
                    <td><?= statusBadge($t['status']) ?></td>
                    <td class="text-end">
                      <button type="button" class="btn-action btn-action-edit" data-bs-toggle="modal"
                        data-bs-target="#editTeacherModal" data-id="<?= (int) $t['id'] ?>"
                        data-full-name="<?= htmlspecialchars($t['full_name'], ENT_QUOTES) ?>"
                        data-email="<?= htmlspecialchars($t['email'], ENT_QUOTES) ?>"
                        data-phone="<?= htmlspecialchars($t['phone'] ?? '', ENT_QUOTES) ?>"
                        data-qualification="<?= htmlspecialchars($t['qualification'] ?? '', ENT_QUOTES) ?>"
                        data-joining-date="<?= htmlspecialchars($t['joining_date'] ?? '', ENT_QUOTES) ?>"
                        data-status="<?= htmlspecialchars(formatStatusLabel($t['status']), ENT_QUOTES) ?>" title="Edit"><i
                          class="bi bi-pencil"></i></button>
                      <button type="button" class="btn-action btn-action-delete" data-bs-toggle="modal"
                        data-bs-target="#deleteConfirmModal" data-id="<?= (int) $t['id'] ?>"
                        data-item-name="<?= htmlspecialchars($t['full_name'], ENT_QUOTES) ?>" title="Delete"><i
                          class="bi bi-trash"></i></button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </main>

      <footer class="app-footer">
        <div>© 2026 <strong>EduPulse Academy</strong> - All rights reserved.</div>
        <div class="d-none d-sm-block">Version 2.4.0</div>
      </footer>
    </div>
  </div>

  <!-- ADD TEACHER MODAL -->
  <div class="modal fade" id="addTeacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-person-plus-fill text-primary me-2"></i>Add Faculty Member</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form action="" method="POST" enctype="multipart/form-data">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control" required placeholder="e.g. Dr. Jonathan Swift">
              </div>
              <div class="col-md-6">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control" required placeholder="teacher@edupulse.edu">
              </div>
              <div class="col-md-6">
                <label class="form-label">Password *</label>
                <input type="password" name="password" class="form-control" required minlength="6"
                  placeholder="Set an initial password">
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone Number *</label>
                <input type="tel" class="form-control" name="phone" required placeholder="+1 202-555-0000">
              </div>
              <div class="col-md-6">
                <label class="form-label">Photo</label>
                <input type="file" class="form-control" name="photo" accept="image/*">
              </div>
              <div class="col-md-6">
                <label class="form-label">Joining Date</label>
                <input type="date" class="form-control" name="joining_date">
              </div>
              <div class="col-md-6">
                <label class="form-label">Status</label>
                <select class="form-select" name="status">
                  <option value="Active">Active</option>
                  <option value="On Leave">On Leave</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Qualification / Degree</label>
                <input type="text" class="form-control" name="qualification" placeholder="e.g. M.Sc. Physics, B.Ed">
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" name="add_teacher">Save Faculty Member</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- EDIT TEACHER MODAL -->
  <div class="modal fade" id="editTeacherModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Teacher Information</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form action="" method="POST" id="editTeacherForm">
          <input type="hidden" name="edit_id" id="edit_id">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name</label>
                <input type="text" class="form-control" name="edit_full_name" id="edit_full_name" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" name="edit_email" id="edit_email" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Phone</label>
                <input type="tel" class="form-control" name="edit_phone" id="edit_phone">
              </div>
              <div class="col-md-6">
                <label class="form-label">Qualification / Degree</label>
                <input type="text" class="form-control" name="edit_qualification" id="edit_qualification">
              </div>
              <div class="col-md-6">
                <label class="form-label">Joining Date</label>
                <input type="date" class="form-control" name="edit_joining_date" id="edit_joining_date">
              </div>
              <div class="col-md-6">
                <label class="form-label">Status</label>
                <select class="form-select" name="edit_status" id="edit_status">
                  <option value="Active">Active</option>
                  <option value="On Leave">On Leave</option>
                </select>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" name="update_teacher">Update Teacher</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- DELETE CONFIRMATION MODAL -->
  <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content text-center p-3">
        <div class="modal-body">
          <div class="text-danger fs-1 mb-2"><i class="bi bi-exclamation-circle"></i></div>
          <h5 class="fw-bold">Delete Record?</h5>
          <p class="text-muted small">Are you sure you want to remove <span class="delete-item-label fw-bold"></span>?
          </p>
          <form action="" method="POST" id="deleteTeacherForm">
            <input type="hidden" name="delete_id" id="delete_id">
            <div class="d-flex justify-content-center gap-2 mt-3">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger" name="delete_teacher">Delete</button>
            </div>
          </form>
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
          <p class="text-muted small">Are you sure you want to end your current session?</p>
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
  <script src="js/script.js"></script>
  <script>
    // Populate Edit modal from the clicked row's data attributes
    document.getElementById('editTeacherModal').addEventListener('show.bs.modal', function (event) {
      const btn = event.relatedTarget;
      if (!btn) return;

      document.getElementById('edit_id').value = btn.getAttribute('data-id') || '';
      document.getElementById('edit_full_name').value = btn.getAttribute('data-full-name') || '';
      document.getElementById('edit_email').value = btn.getAttribute('data-email') || '';
      document.getElementById('edit_phone').value = btn.getAttribute('data-phone') || '';
      document.getElementById('edit_qualification').value = btn.getAttribute('data-qualification') || '';
      document.getElementById('edit_joining_date').value = btn.getAttribute('data-joining-date') || '';
      document.getElementById('edit_status').value = btn.getAttribute('data-status') || 'Active';
    });

    // Populate Delete modal from the clicked row's data attributes
    document.getElementById('deleteConfirmModal').addEventListener('show.bs.modal', function (event) {
      const btn = event.relatedTarget;
      if (!btn) return;

      document.getElementById('delete_id').value = btn.getAttribute('data-id') || '';
      document.querySelector('#deleteConfirmModal .delete-item-label').textContent = btn.getAttribute('data-item-name') || 'this record';
    });
  </script>
</body>

</html>