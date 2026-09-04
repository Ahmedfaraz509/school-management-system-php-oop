<?php
include_once '../database/connect.php';
require_once 'parentsprocess.php';

// --- Handle POST actions ---
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';

  // Fetch students for dropdown (used in modals)
  $studentStmt = $conn->query("SELECT id, first_name, last_name FROM students WHERE status = 'Active' ORDER BY first_name");
  $students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

  if ($action === 'add') {
    $fullName = trim($_POST['full_name'] ?? '');
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $relation = (int) ($_POST['relation'] ?? 0);
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (int) ($_POST['password'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $status = 'Active';
    $now = date('Y-m-d H:i:s');
    $parentUid = 'PAR-' . strtoupper(uniqid());

    // Validate
    if (empty($fullName) || $studentId <= 0 || $relation <= 0 || empty($phone) || empty($email)) {
      $message = 'Please fill in all required fields.';
      $messageType = 'danger';
    } else {
      $parent = new ParentsProcess(
        $conn,
        null,
        $studentId,
        $parentUid,
        $fullName,
        $relation,
        $phone,
        $email,
        $password,
        $address,
        $status,
        $now,
        $now
      );
      if ($parent->processParentsData()) {
        $message = 'Parent added successfully!';
        $messageType = 'success';
      } else {
        $message = 'Failed to add parent.';
        $messageType = 'danger';
      }
    }
  } elseif ($action === 'edit') {
    $id = (int) ($_POST['id'] ?? 0);
    $studentId = (int) ($_POST['student_id'] ?? 0);
    $fullName = trim($_POST['full_name'] ?? '');
    $relation = (int) ($_POST['relation'] ?? 0);
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = (int) ($_POST['password'] ?? 0);
    $address = trim($_POST['address'] ?? '');
    $status = trim($_POST['status'] ?? 'Active');
    $now = date('Y-m-d H:i:s');

    if ($id <= 0 || empty($fullName) || $studentId <= 0 || $relation <= 0) {
      $message = 'Invalid input.';
      $messageType = 'danger';
    } else {
      $parent = new ParentsProcess(
        $conn,
        $id,
        $studentId,
        null,       // parent_uid not changed
        $fullName,
        $relation,
        $phone,
        $email,
        $password,
        $address,
        $status,
        null,
        $now
      );
      if ($parent->updateParentsData()) {
        $message = 'Parent updated successfully!';
        $messageType = 'success';
      } else {
        $message = 'Failed to update parent.';
        $messageType = 'danger';
      }
    }
  } elseif ($action === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0) {
      $parent = new ParentsProcess($conn, $id);
      if ($parent->deleteParentsData()) {
        $message = 'Parent deleted successfully!';
        $messageType = 'success';
      } else {
        $message = 'Failed to delete parent.';
        $messageType = 'danger';
      }
    } else {
      $message = 'Invalid ID.';
      $messageType = 'danger';
    }
  }
}

// --- Fetch data for display ---
$parentsProcess = new ParentsProcess($conn);
$parents = $parentsProcess->joinParentsData();

// Fetch students for dropdowns (used in both modals)
$studentStmt = $conn->query("SELECT id, first_name, last_name FROM students WHERE status = 'Active' ORDER BY first_name");
$students = $studentStmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count
$totalParents = count($parents);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Parents & Guardians - EduPulse School Management System</title>
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
    <!-- LEFT SIDEBAR (unchanged) -->
    <aside class="app-sidebar">
      <div class="sidebar-header">
        <a href="index.php" class="brand-logo">
          <div class="brand-icon"><i class="bi bi-mortarboard-fill"></i></div>
          <span class="brand-text">EduPulse <small class="fw-normal text-muted fs-6">SMS</small></span>
        </a>
      </div>
      <div class="sidebar-nav">
        <!-- same menu as original, keep as is -->
        <div class="nav-section-title">Main</div>
        <ul class="sidebar-menu">
          <li class="nav-item"><a href="index.php" class="nav-link"><i class="bi bi-grid-1x2-fill"></i><span
                class="nav-text">Dashboard</span></a></li>
        </ul>
        <div class="nav-section-title">Academics</div>
        <ul class="sidebar-menu">
          <li class="nav-item"><a href="students.php" class="nav-link"><i class="bi bi-people-fill"></i><span
                class="nav-text">Students</span></a></li>
          <li class="nav-item"><a href="teachers.php" class="nav-link"><i class="bi bi-person-video3"></i><span
                class="nav-text">Teachers</span></a></li>
          <li class="nav-item"><a href="parents.php" class="nav-link active"><i class="bi bi-person-heart"></i><span
                class="nav-text">Parents</span></a></li>
          <li class="nav-item"><a href="classes.php" class="nav-link"><i class="bi bi-door-open-fill"></i><span
                class="nav-text">Classes</span></a></li>
          <li class="nav-item"><a href="subjects.php" class="nav-link"><i class="bi bi-book-half"></i><span
                class="nav-text">Subjects</span></a></li>
          <li class="nav-item"><a href="timetable.php" class="nav-link"><i class="bi bi-calendar3-range"></i><span
                class="nav-text">Timetable</span></a></li>
          <li class="nav-item"><a href="assignments.php" class="nav-link"><i class="bi bi-journal-check"></i><span
                class="nav-text">Assignments</span></a></li>
        </ul>
        <div class="nav-section-title">Operations</div>
        <ul class="sidebar-menu">
          <li class="nav-item"><a href="attendance.php" class="nav-link"><i class="bi bi-check2-square"></i><span
                class="nav-text">Attendance</span></a></li>
          <li class="nav-item"><a href="exams.php" class="nav-link"><i class="bi bi-pencil-square"></i><span
                class="nav-text">Exams</span></a></li>
          <li class="nav-item"><a href="results.php" class="nav-link"><i class="bi bi-trophy-fill"></i><span
                class="nav-text">Results</span></a></li>
          <li class="nav-item"><a href="fees.php" class="nav-link"><i class="bi bi-cash-stack"></i><span
                class="nav-text">Fees</span></a></li>
        </ul>
        <div class="nav-section-title">Communication</div>
        <ul class="sidebar-menu">
          <li class="nav-item"><a href="notices.php" class="nav-link"><i class="bi bi-megaphone-fill"></i><span
                class="nav-text">Notices</span></a></li>
          <li class="nav-item"><a href="events.php" class="nav-link"><i class="bi bi-calendar-event-fill"></i><span
                class="nav-text">Events</span></a></li>
          <li class="nav-item"><a href="messages.php" class="nav-link"><i class="bi bi-chat-dots-fill"></i><span
                class="nav-text">Messages</span><span class="badge bg-danger rounded-pill ms-auto">4</span></a></li>
        </ul>
        <div class="nav-section-title">System</div>
        <ul class="sidebar-menu">
          <li class="nav-item"><a href="reports.php" class="nav-link"><i class="bi bi-bar-chart-line-fill"></i><span
                class="nav-text">Reports</span></a></li>
          <li class="nav-item"><a href="profile.php" class="nav-link"><i class="bi bi-person-circle"></i><span
                class="nav-text">Profile</span></a></li>
          <li class="nav-item"><a href="settings.php" class="nav-link"><i class="bi bi-gear-fill"></i><span
                class="nav-text">Settings</span></a></li>
          <li class="nav-item"><a href="#" class="nav-link text-danger" data-bs-toggle="modal"
              data-bs-target="#logoutModal"><i class="bi bi-box-arrow-right text-danger"></i><span
                class="nav-text">Logout</span></a></li>
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
              <li>
                <a class="dropdown-item py-2 text-danger" href="auth/login.php">
                  <i class="bi bi-box-arrow-right me-2"></i> Logout
                </a>
              </li>
            </ul>
          </div>
        </div>
      </header>

      <main class="app-content">
        <!-- Page Header -->
        <div class="page-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
          <div>
            <h1 class="page-title">Parents & Guardians</h1>
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Parents</li>
              </ol>
            </nav>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportTableDemo('Guardians CSV')">
              <i class="bi bi-download me-1"></i> Export
            </button>
            <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2"
              data-bs-toggle="modal" data-bs-target="#addParentModal">
              <i class="bi bi-person-plus-fill"></i> Add Parent
            </button>
          </div>
        </div>

        <!-- Display messages -->
        <?php if ($message): ?>
          <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($message) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        <?php endif; ?>

        <!-- Filter Bar (unchanged) -->
        <div class="filter-card">
          <div class="row g-3 align-items-center">
            <div class="col-md-5">
              <div class="input-group">
                <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                <input type="text" class="form-control border-start-0 ps-0 table-search-input"
                  placeholder="Search parent name, student, phone..." data-table="#parentsTable">
              </div>
            </div>
            <div class="col-sm-6 col-md-3">
              <select class="form-select table-filter-select" data-column="3">
                <option value="all">All Relationships</option>
                <option value="Father">Father</option>
                <option value="Mother">Mother</option>
                <option value="Guardian">Guardian</option>
              </select>
            </div>
            <div class="col-sm-6 col-md-2">
              <select class="form-select table-filter-select" data-column="7">
                <option value="all">All Status</option>
                <option value="Active">Active</option>
                <option value="Inactive">Inactive</option>
              </select>
            </div>
            <div class="col-md-2">
              <button type="button" class="btn btn-outline-secondary w-100 btn-reset-filters">
                <i class="bi bi-arrow-counterclockwise me-1"></i> Reset
              </button>
            </div>
          </div>
        </div>

        <!-- Parents Table -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold">Guardian Records</span>
            <small class="text-muted">Total Registered:
              <?= $totalParents ?>
            </small>
          </div>
          <div class="table-responsive">
            <table class="table table-custom align-middle" id="parentsTable">
              <thead>
                <tr>
                  <th>Parent ID</th>
                  <th>Parent Name</th>
                  <th>Student (Ward)</th>
                  <th>Relationship</th>
                  <th>Phone Number</th>
                  <th>Email</th>
                  <th>Password</th>
                  <th>Residential Address</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($parents)): ?>
                  <tr>
                    <td colspan="9" class="text-center text-muted">No parents found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($parents as $p): ?>
                    <?php
                    $relText = ParentsProcess::getRelationText($p['relation']);
                    $statusBadge = $p['status'] === 'Active'
                      ? '<span class="badge-subtle badge-subtle-success">Active</span>'
                      : '<span class="badge-subtle badge-subtle-secondary">Inactive</span>';
                    ?>
                    <tr data-id="<?= $p['id'] ?>">
                      <td><span class="badge bg-light text-dark border">
                          <?= htmlspecialchars($p['parent_uid']) ?>
                        </span></td>
                      <td class="fw-semibold text-dark">
                        <?= htmlspecialchars($p['full_name']) ?>
                      </td>
                      <td><span class="badge bg-primary-subtle text-primary">
                          <?= htmlspecialchars($p['student_name']) ?>
                        </span></td>
                      <td>
                        <?= htmlspecialchars($relText) ?>
                      </td>
                      <td>
                        <?= htmlspecialchars($p['phone']) ?>
                      </td>
                      <td>
                        <?= htmlspecialchars($p['email']) ?>
                      </td>
                      <td>
                        <?= htmlspecialchars($p['password']) ?>
                      </td>
                      <td>
                        <?= htmlspecialchars($p['address']) ?>
                      </td>
                      <td>
                        <?= $statusBadge ?>
                      </td>
                      <td class="text-end">
                        <button type="button" class="btn-action btn-action-view" data-bs-toggle="modal"
                          data-bs-target="#viewParentModal" data-id="<?= $p['id'] ?>"
                          data-name="<?= htmlspecialchars($p['full_name']) ?>"
                          data-student="<?= htmlspecialchars($p['student_name']) ?>" data-relation="<?= $relText ?>"
                          data-phone="<?= htmlspecialchars($p['phone']) ?>"
                          data-email="<?= htmlspecialchars($p['email']) ?>"
                          data-address="<?= htmlspecialchars($p['address']) ?>" title="View"><i
                            class="bi bi-eye"></i></button>

                        <button type="button" class="btn-action btn-action-edit" data-bs-toggle="modal"
                          data-bs-target="#editParentModal" data-id="<?= $p['id'] ?>"
                          data-student-id="<?= $p['student_id'] ?>" data-fullname="<?= htmlspecialchars($p['full_name']) ?>"
                          data-relation="<?= $p['relation'] ?>" data-phone="<?= htmlspecialchars($p['phone']) ?>"
                          data-email="<?= htmlspecialchars($p['email']) ?>" data-password="<?= $p['password'] ?? 0 ?>"
                          data-address="<?= htmlspecialchars($p['address']) ?>" data-status="<?= $p['status'] ?>"
                          title="Edit"><i class="bi bi-pencil"></i></button>

                        <button type="button" class="btn-action btn-action-delete" data-bs-toggle="modal"
                          data-bs-target="#deleteConfirmModal" data-id="<?= $p['id'] ?>"
                          data-name="<?= htmlspecialchars($p['full_name']) ?>" title="Delete"><i
                            class="bi bi-trash"></i></button>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
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

  <!-- ========== ADD PARENT MODAL ========== -->
  <div class="modal fade" id="addParentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <form method="POST">
          <input type="hidden" name="action" value="add">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-person-heart text-primary me-2"></i>Add Guardian Account</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" class="form-control" required placeholder="e.g. Salim Merchant">
              </div>
              <div class="col-md-6">
                <label class="form-label">Student (Ward) *</label>
                <select name="student_id" class="form-select" required>
                  <option value="">-- Select Student --</option>
                  <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>">
                      <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Relationship *</label>
                <select name="relation" class="form-select" required>
                  <option value="">-- Select --</option>
                  <option value="1">Father</option>
                  <option value="2">Mother</option>
                  <option value="3">Guardian</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Phone Number *</label>
                <input type="tel" name="phone" class="form-control" required placeholder="+1 202-555-0000">
              </div>
              <div class="col-md-4">
                <label class="form-label">Email Address *</label>
                <input type="email" name="email" class="form-control" required placeholder="parent@example.com">
              </div>

              <div class="col-md-4">
                <label class="form-label">Password/PIN *</label>
                <input type="number" name="password" class="form-control" required placeholder="0000">
              </div>
              <div class="col-12">
                <label class="form-label">Residential Address</label>
                <textarea class="form-control" rows="2" name="address" placeholder="Full home address"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Guardian</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ========== EDIT PARENT MODAL ========== -->
  <div class="modal fade" id="editParentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <form method="POST">
          <input type="hidden" name="action" value="edit">
          <input type="hidden" name="id" id="edit_id">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Parent Details</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Full Name *</label>
                <input type="text" name="full_name" id="edit_fullname" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label">Student (Ward) *</label>
                <select name="student_id" id="edit_student_id" class="form-select" required>
                  <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>">
                      <?= htmlspecialchars($s['first_name'] . ' ' . $s['last_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Relationship *</label>
                <select name="relation" id="edit_relation" class="form-select" required>
                  <option value="1">Father</option>
                  <option value="2">Mother</option>
                  <option value="3">Guardian</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Phone *</label>
                <input type="tel" name="phone" id="edit_phone" class="form-control" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Email *</label>
                <input type="email" name="email" id="edit_email" class="form-control" required>
              </div>
              <div class="col-md-4">
                <label class="form-label">Password/PIN</label>
                <input type="number" name="password" id="edit_password" class="form-control" value="0">
              </div>
              <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" id="edit_status" class="form-select">
                  <option value="Active">Active</option>
                  <option value="Inactive">Inactive</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Address</label>
                <textarea class="form-control" rows="2" name="address" id="edit_address"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ========== VIEW PARENT MODAL ========== -->
  <div class="modal fade" id="viewParentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Guardian Profile</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="text-center pb-3 border-bottom mb-3">
            <div class="avatar-initials mx-auto mb-2" style="width: 60px; height: 60px; font-size: 1.25rem;"
              id="view_initials">MK</div>
            <h5 class="fw-bold mb-0" id="view_name">Mustafa Khan</h5>
            <span class="text-muted small" id="view_uid">Guardian ID: PRN-401</span>
          </div>
          <div class="row g-2 small">
            <div class="col-5 text-muted">Ward (Student):</div>
            <div class="col-7 fw-semibold text-primary" id="view_student">Ahmed Khan (Grade 10-A)</div>
            <div class="col-5 text-muted">Relationship:</div>
            <div class="col-7 fw-semibold" id="view_relation">Father</div>
            <div class="col-5 text-muted">Contact Phone:</div>
            <div class="col-7 fw-semibold" id="view_phone">+1 202-555-0143</div>
            <div class="col-5 text-muted">Email:</div>
            <div class="col-7 fw-semibold" id="view_email">mustafa.khan@example.com</div>
            <div class="col-5 text-muted">Address:</div>
            <div class="col-7 fw-semibold" id="view_address">42 Maple Ave, Oakridge</div>
            <div class="col-5 text-muted">Fee Account Status:</div>
            <div class="col-7 fw-bold text-success">Cleared (Up to date)</div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ========== DELETE CONFIRMATION MODAL ========== -->
  <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
      <div class="modal-content text-center p-3">
        <form method="POST">
          <input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" id="delete_id">
          <div class="modal-body">
            <div class="text-danger fs-1 mb-2"><i class="bi bi-exclamation-circle"></i></div>
            <h5 class="fw-bold">Delete Record?</h5>
            <p class="text-muted small">Are you sure you want to remove <span class="delete-item-label fw-bold"
                id="delete_name"></span>?</p>
            <div class="d-flex justify-content-center gap-2 mt-3">
              <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-danger">Delete</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ========== LOGOUT MODAL ========== -->
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
  <script>
    // -------------------------------------------------------------
    // Populate Edit Modal with data from the row
    // -------------------------------------------------------------
    document.querySelectorAll('.btn-action-edit').forEach(btn => {
      btn.addEventListener('click', function () {
        const id = this.dataset.id;
        const studentId = this.dataset.studentId;
        const fullname = this.dataset.fullname;
        const relation = this.dataset.relation;
        const phone = this.dataset.phone;
        const email = this.dataset.email;
        const password = this.dataset.password || 0;
        const address = this.dataset.address;
        const status = this.dataset.status;

        document.getElementById('edit_id').value = id;
        document.getElementById('edit_fullname').value = fullname;
        document.getElementById('edit_student_id').value = studentId;
        document.getElementById('edit_relation').value = relation;
        document.getElementById('edit_phone').value = phone;
        document.getElementById('edit_email').value = email;
        document.getElementById('edit_password').value = password;
        document.getElementById('edit_address').value = address;
        document.getElementById('edit_status').value = status;
      });
    });

    // -------------------------------------------------------------
    // Populate View Modal with data
    // -------------------------------------------------------------
    document.querySelectorAll('.btn-action-view').forEach(btn => {
      btn.addEventListener('click', function () {
        const name = this.dataset.name;
        const student = this.dataset.student;
        const relation = this.dataset.relation;
        const phone = this.dataset.phone;
        const email = this.dataset.email;
        const address = this.dataset.address;
        // generate initials
        const initials = name.split(' ').map(w => w[0]).join('').substring(0, 2).toUpperCase();

        document.getElementById('view_initials').textContent = initials;
        document.getElementById('view_name').textContent = name;
        document.getElementById('view_uid').textContent = 'Guardian ID: ' + this.closest('tr').querySelector('.badge').textContent;
        document.getElementById('view_student').textContent = student;
        document.getElementById('view_relation').textContent = relation;
        document.getElementById('view_phone').textContent = phone;
        document.getElementById('view_email').textContent = email;
        document.getElementById('view_address').textContent = address;
      });
    });

    // -------------------------------------------------------------
    // Populate Delete Modal with name and ID
    // -------------------------------------------------------------
    document.querySelectorAll('.btn-action-delete').forEach(btn => {
      btn.addEventListener('click', function () {
        const id = this.dataset.id;
        const name = this.dataset.name;
        document.getElementById('delete_id').value = id;
        document.getElementById('delete_name').textContent = name;
      });
    });
  </script>
</body>

</html>