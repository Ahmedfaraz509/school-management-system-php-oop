<?php
declare(strict_types=1);

/**
 * Students Directory Page for EduPulse SMS
 * 
 * Main page for managing students. Connects the existing frontend
 * with the PHP OOP backend.
 */

// Bootstrap the application
require_once __DIR__ . '/config/bootstrap.php';

use EduPulse\Helpers\Auth;
use EduPulse\Helpers\Csrf;
use EduPulse\Helpers\Response;

// Require admin authentication
Auth::requireAdmin();

// Get current user info
$currentUser = [
  'name' => Auth::name() ?: 'Admin',
  'role' => Auth::role() ?: 'admin'
];

// Get classes for dropdown
$classModel = new \EduPulse\Models\ClassModel(getDB());
$classes = $classModel->getAllActive();

// Generate CSRF token
$csrfToken = Csrf::getToken();

// Get flash message if any
$flash = Response::getFlash();
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Students Directory - EduPulse School Management System</title>
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet">
  <!-- Bootstrap 5.3.3 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <!-- Custom Styles -->
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
            <a href="students.php" class="nav-link active">
              <i class="bi bi-people-fill"></i>
              <span class="nav-text">Students</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="teachers.php" class="nav-link">
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
            <div class="text-white fw-bold text-truncate" style="font-size: 0.85rem;">
              <?php echo escape($currentUser['name']); ?>
            </div>
            <div class="text-muted text-truncate" style="font-size: 0.75rem;">
              <?php echo escape(ucfirst($currentUser['role'])); ?>
            </div>
          </div>
        </div>
      </div>
    </aside>

    <!-- MAIN CONTENT AREA -->
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
          <!-- Notification Dropdown -->
          <div class="dropdown">
            <button class="topbar-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="bi bi-bell"></i>
              <span class="notification-badge"></span>
            </button>
            <div class="dropdown-menu dropdown-menu-end shadow border-0 p-0" style="width: 320px;">
              <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                <h6 class="m-0 fw-bold">Notifications</h6>
                <span class="badge bg-primary-subtle text-primary">3 New</span>
              </div>
              <div class="list-group list-group-flush">
                <a href="students.php" class="list-group-item list-group-item-action p-3">
                  <div class="small fw-bold">New Student Enrolled</div>
                  <small class="text-muted">A new student joined the school</small>
                </a>
              </div>
            </div>
          </div>

          <!-- User Dropdown -->
          <div class="dropdown">
            <a href="#" class="user-dropdown-btn dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
              <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=100&auto=format&fit=crop&q=80"
                alt="Admin">
              <div class="user-meta">
                <span class="user-name"><?php echo escape($currentUser['name']); ?></span>
                <span class="user-role"><?php echo escape(ucfirst($currentUser['role'])); ?></span>
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
        <!-- Page Header -->
        <div class="page-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
          <div>
            <h1 class="page-title">Students Directory</h1>
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active" aria-current="page">Students</li>
              </ol>
            </nav>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnExport">
              <i class="bi bi-download me-1"></i> Export
            </button>
            <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2"
              data-bs-toggle="modal" data-bs-target="#addStudentModal">
              <i class="bi bi-person-plus-fill"></i> Add Student
            </button>
          </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="filter-card">
          <div class="row g-3 align-items-center">
            <div class="col-md-4">
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0 ps-0" id="searchInput"
                  placeholder="Search by name, ID, email...">
              </div>
            </div>
            <div class="col-sm-6 col-md-2">
              <select class="form-select" id="filterClass">
                <option value="">All Classes</option>
                <?php foreach ($classes as $class): ?>
                  <option value="<?php echo (int) $class['id']; ?>"><?php echo escape($class['name']); ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="col-sm-6 col-md-2">
              <select class="form-select" id="filterGender">
                <option value="">All Genders</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
                <option value="Other">Other</option>
              </select>
            </div>
            <div class="col-sm-6 col-md-2">
              <select class="form-select" id="filterStatus">
                <option value="">All Statuses</option>
                <option value="Active">Active</option>
                <option value="Pending Doc">Pending Doc</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>
            <div class="col-sm-6 col-md-2">
              <button type="button" class="btn btn-outline-secondary w-100" id="btnResetFilters">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
              </button>
            </div>
          </div>
        </div>

        <!-- Student Table Card -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold" id="recordCount">All Students</span>
            <small class="text-muted" id="paginationInfo">Loading...</small>
          </div>
          <div class="table-responsive">
            <table class="table table-custom align-middle" id="studentsTable">
              <thead>
                <tr>
                  <th>Student ID</th>
                  <th>Student Name</th>
                  <th>Gender</th>
                  <th>Class</th>
                  <th>Email</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="studentsTableBody">
                <tr>
                  <td colspan="7" class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                      <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2 text-muted">Loading students...</p>
                  </td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Pagination -->
          <div class="card-footer" id="paginationContainer">
            <nav aria-label="Students pagination">
              <ul class="pagination justify-content-center mb-0" id="paginationList">
                <!-- Pagination will be populated by JavaScript -->
              </ul>
            </nav>
          </div>
        </div>
      </main>

      <footer class="app-footer">
        <div>© 2026 <strong>EduPulse Academy</strong> - All rights reserved.</div>
        <div class="d-none d-sm-block">Version 2.4.0</div>
      </footer>
    </div>
  </div>

  <!-- ADD STUDENT MODAL -->
  <div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">

    <div class="modal-dialog modal-lg modal-dialog-centered">

      <div class="modal-content">

        <!-- HEADER -->
        <div class="modal-header">
          <h5 class="modal-title" id="addStudentModalLabel">
            <i class="bi bi-person-plus-fill text-primary me-2"></i>
            New Student Admission
          </h5>

          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close">
          </button>
        </div>

        <!-- FORM -->
        <form id="addStudentForm" enctype="multipart/form-data">

          <input type="hidden" name="csrf_token" value="<?php echo escape($csrfToken); ?>">

          <!-- SCROLLABLE BODY -->
          <div class="modal-body">

            <div class="row g-3">

              <!-- FIRST NAME -->
              <div class="col-md-6">
                <label class="form-label">
                  First Name <span class="text-danger">*</span>
                </label>

                <input type="text" class="form-control" name="first_name" required placeholder="e.g. Zayan">

                <div class="invalid-feedback" id="first_name_error">
                </div>
              </div>

              <!-- LAST NAME -->
              <div class="col-md-6">
                <label class="form-label">
                  Last Name <span class="text-danger">*</span>
                </label>

                <input type="text" class="form-control" name="last_name" required placeholder="e.g. Tariq">

                <div class="invalid-feedback" id="last_name_error">
                </div>
              </div>

              <!-- GENDER -->
              <div class="col-md-4">
                <label class="form-label">
                  Gender <span class="text-danger">*</span>
                </label>

                <select class="form-select" name="gender" required>

                  <option value="">Select gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>

                </select>

                <div class="invalid-feedback" id="gender_error">
                </div>
              </div>

              <!-- DOB -->
              <div class="col-md-4">
                <label class="form-label">
                  Date of Birth <span class="text-danger">*</span>
                </label>

                <input type="date" class="form-control" name="date_of_birth" required>

                <div class="invalid-feedback" id="date_of_birth_error">
                </div>
              </div>

              <!-- CLASS -->
              <div class="col-md-4">
                <label class="form-label">
                  Assign Class
                </label>

                <select class="form-select" name="class_id">

                  <option value="">
                    Select class
                  </option>

                  <?php foreach ($classes as $class): ?>

                    <option value="<?php echo (int) $class['id']; ?>">
                      <?php echo escape($class['name']); ?>
                    </option>

                  <?php endforeach; ?>

                </select>
              </div>

              <!-- EMAIL -->
              <div class="col-md-6">
                <label class="form-label">
                  Email Address <span class="text-danger">*</span>
                </label>

                <input type="email" class="form-control" name="email" required placeholder="student@example.com">

                <div class="invalid-feedback" id="email_error">
                </div>
              </div>

              <!-- PASSWORD -->
              <div class="col-md-6">
                <label class="form-label">
                  Password <span class="text-danger">*</span>
                </label>

                <input type="password" class="form-control" name="password" required minlength="8"
                  placeholder="Min. 8 characters">

                <div class="invalid-feedback" id="password_error">
                </div>
              </div>

              <!-- ADMISSION DATE -->
              <div class="col-md-6">
                <label class="form-label">
                  Admission Date <span class="text-danger">*</span>
                </label>

                <input type="date" class="form-control" name="admission_date" required
                  value="<?php echo date('Y-m-d'); ?>">

                <div class="invalid-feedback" id="admission_date_error">
                </div>
              </div>

              <!-- STATUS -->
              <div class="col-md-6">
                <label class="form-label">
                  Enrollment Status
                </label>

                <select class="form-select" name="status">

                  <option value="Active">
                    Active
                  </option>

                  <option value="Pending Doc">
                    Pending Documents
                  </option>

                  <option value="Inactive">
                    Inactive
                  </option>

                </select>
              </div>

              <!-- ADDRESS -->
              <div class="col-12">
                <label class="form-label">
                  Residential Address
                </label>

                <textarea class="form-control" name="address" rows="3"
                  placeholder="Street address, city, zip code"></textarea>
              </div>

              <!-- PHOTO -->
              <div class="col-12">
                <label class="form-label">
                  Student Photo
                </label>

                <input type="file" class="form-control" name="photo" accept=".jpg,.jpeg,.png,.webp">

                <small class="text-muted">
                  Allowed: JPG, PNG, WebP. Max size: 5MB
                </small>

                <div class="invalid-feedback" id="photo_error">
                </div>
              </div>

            </div>

          </div>

          <!-- FOOTER -->
          <div class="modal-footer">

            <button type="button" class="btn btn-light" data-bs-dismiss="modal">
              Cancel
            </button>

            <button type="submit" class="btn btn-primary" id="btnAddStudent">

              <i class="bi bi-check-lg me-1"></i>
              Register Student

            </button>

          </div>

        </form>

      </div>
    </div>
  </div>

  <!-- EDIT STUDENT MODAL -->
  <div class="modal fade" id="editStudentModal" tabindex="-1" aria-labelledby="editStudentModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editStudentModalLabel"><i class="bi bi-pencil-square text-primary me-2"></i>Edit
            Student Profile</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="editStudentForm" enctype="multipart/form-data">
          <input type="hidden" name="csrf_token" value="<?php echo escape($csrfToken); ?>">
          <input type="hidden" name="id" id="edit_student_id">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">First Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="first_name" id="edit_first_name" required>
                <div class="invalid-feedback" id="edit_first_name_error"></div>
              </div>
              <div class="col-md-6">
                <label class="form-label">Last Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" name="last_name" id="edit_last_name" required>
                <div class="invalid-feedback" id="edit_last_name_error"></div>
              </div>
              <div class="col-md-4">
                <label class="form-label">Gender <span class="text-danger">*</span></label>
                <select class="form-select" name="gender" id="edit_gender" required>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="date_of_birth" id="edit_date_of_birth" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Class</label>
                <select class="form-select" name="class_id" id="edit_class_id">
                  <option value="">Select class</option>
                  <?php foreach ($classes as $class): ?>
                    <option value="<?php echo (int) $class['id']; ?>"><?php echo escape($class['name']); ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email <span class="text-danger">*</span></label>
                <input type="email" class="form-control" name="email" id="edit_email" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">New Password <span class="text-muted">(leave blank to keep
                    current)</span></label>
                <input type="password" class="form-control" name="password" minlength="8"
                  placeholder="Min. 8 characters">
              </div>
              <div class="col-md-6">
                <label class="form-label">Status</label>
                <select class="form-select" name="status" id="edit_status">
                  <option value="Active">Active</option>
                  <option value="Pending Doc">Pending Doc</option>
                  <option value="Inactive">Inactive</option>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Admission Date <span class="text-danger">*</span></label>
                <input type="date" class="form-control" name="admission_date" id="edit_admission_date" required>
              </div>
              <div class="col-12">
                <label class="form-label">Address</label>
                <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label">Photo</label>
                <input type="file" class="form-control" name="photo" accept=".jpg,.jpeg,.png,.webp">
                <small class="text-muted">Leave blank to keep current photo</small>
                <div class="mt-2" id="currentPhotoPreview"></div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="btnUpdateStudent">
              <i class="bi bi-save me-1"></i> Update Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- VIEW STUDENT MODAL -->
  <div class="modal fade" id="viewStudentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Student Profile Summary</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="text-center pb-3 border-bottom mb-3">
            <img src="" alt="Student" class="rounded-circle mb-2 border p-1" width="80" height="80" id="view_photo">
            <h5 class="fw-bold mb-0" id="view_name">Loading...</h5>
            <span class="badge bg-primary-subtle text-primary" id="view_student_id">Loading...</span>
            <div class="mt-2"><span class="badge-subtle" id="view_status_badge">Loading...</span></div>
          </div>
          <div class="row g-2 small" id="view_details">
            <!-- Details will be populated by JavaScript -->
          </div>
        </div>
        <div class="modal-footer">
          <a href="results.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-file-earmark-bar-graph me-1"></i>
            Academic Result</a>
          <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- COMMON DELETE CONFIRMATION MODAL -->
  <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content text-center p-3">
        <div class="modal-body">
          <div class="text-danger fs-1 mb-2"><i class="bi bi-exclamation-circle"></i></div>
          <h5 class="fw-bold">Delete Student?</h5>
          <p class="text-muted small">Are you sure you want to delete <span class="delete-item-label fw-bold"
              id="deleteStudentName"></span>? This will also remove their user account. This action cannot be undone.
          </p>
          <form id="deleteStudentForm">
            <input type="hidden" name="csrf_token" value="<?php echo escape($csrfToken); ?>">
            <input type="hidden" name="id" id="delete_student_id">
            <div class="d-flex justify-content-center gap-2 mt-3">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger" id="btnConfirmDelete">Delete</button>
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
            <a href="logout.php" class="btn btn-danger">Logout</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="toastContainer"></div>

  <!-- Bootstrap & Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/script.js"></script>
  <script src="js/students.js"></script>

  <script>
    // Initialize students page
    document.addEventListener('DOMContentLoaded', function () {
      StudentsApp.init();
    });
  </script>
</body>

</html>