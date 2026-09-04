<?php
// classes.php
declare(strict_types=1);

require_once __DIR__ . '/config/classes_bootstrap.php';
require_once __DIR__ . '/models/ClassModel.php';
require_once __DIR__ . '/models/Teacher.php';

cm_require_auth();

$classModel = new ClassModel($pdo);
$teacherModel = new Teacher($pdo);

// Filters
$search = trim((string) ($_GET['search'] ?? ''));
$status = (string) ($_GET['status'] ?? 'all');
if (!in_array($status, ['all', 'active', 'inactive'], true)) {
  $status = 'all';
}

$classes = $classModel->filter($search, $status);
$activeTeachers = $teacherModel->getActive();
$totalClasses = $classModel->totalCount();
$flashes = cm_flash_get();
$csrf = cm_csrf_token();

// Quick cards = first 4 classes
$quickCards = array_slice($classes, 0, 4);
$cardColors = ['primary', 'info', 'warning', 'success'];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Classes Management - EduPulse School Management System</title>
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
          <li class="nav-item"><a href="parents.php" class="nav-link"><i class="bi bi-person-heart"></i><span
                class="nav-text">Parents</span></a></li>
          <li class="nav-item"><a href="classes.php" class="nav-link active"><i class="bi bi-door-open-fill"></i><span
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

    <div class="app-main">
      <header class="app-topbar">
        <div class="topbar-left">
          <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn"><i class="bi bi-list"></i></button>
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
              </li>
              <li><a class="dropdown-item py-2 text-danger" href="#" data-bs-toggle="modal"
                  data-bs-target="#logoutModal"><i class="bi bi-box-arrow-right me-2"></i> Logout</a></li>
            </ul>
          </div>
        </div>
      </header>

      <main class="app-content">
        <div class="page-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
          <div>
            <h1 class="page-title">Classes &amp; Sections</h1>
            <nav aria-label="breadcrumb">
              <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                <li class="breadcrumb-item active">Classes</li>
              </ol>
            </nav>
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-primary btn-sm d-inline-flex align-items-center gap-2"
              data-bs-toggle="modal" data-bs-target="#addClassModal">
              <i class="bi bi-plus-circle-fill"></i> Add Class
            </button>
          </div>
        </div>

        <!-- FLASH MESSAGES -->
        <?php foreach ($flashes as $f): ?>
          <div class="alert alert-<?= htmlspecialchars($f['type']) ?> alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($f['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endforeach; ?>

        <!-- QUICK CARDS (dynamic) -->
        <div class="row g-3 mb-4">
          <?php if (empty($quickCards)): ?>
            <div class="col-12">
              <div class="card p-3 text-muted">No classes available.</div>
            </div>
          <?php else: ?>
            <?php foreach ($quickCards as $i => $c): ?>
              <?php $color = $cardColors[$i % count($cardColors)]; ?>
              <div class="col-md-3 col-sm-6">
                <div class="card h-100 p-3 border-start border-4 border-<?= $color ?>">
                  <div class="d-flex justify-content-between">
                    <h5 class="fw-bold text-dark mb-1"><?= htmlspecialchars($c['name']) ?></h5>
                    <?php if ($c['status'] === 'active'): ?>
                      <span class="badge bg-success-subtle text-success">Active</span>
                    <?php else: ?>
                      <span class="badge bg-secondary-subtle text-secondary">Inactive</span>
                    <?php endif; ?>
                  </div>
                  <p class="text-muted small mb-2"><i
                      class="bi bi-person me-1"></i><?= htmlspecialchars($c['teacher_name'] ?? 'Unassigned') ?></p>
                  <div class="d-flex justify-content-between small text-muted pt-2 border-top">
                    <span><i class="bi bi-people me-1"></i><?= (int) $c['student_count'] ?> Students</span>
                    <span><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($c['room_no'] ?? '—') ?></span>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>

        <!-- FILTER BAR (server-side form) -->
        <div class="filter-card">
          <form method="get" action="classes.php">
            <div class="row g-3 align-items-center">
              <div class="col-md-6">
                <div class="input-group">
                  <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                  <input type="text" name="search" class="form-control border-start-0 ps-0"
                    placeholder="Search class name, teacher, or room..." value="<?= htmlspecialchars($search) ?>">
                </div>
              </div>
              <div class="col-md-4">
                <select name="status" class="form-select" onchange="this.form.submit()">
                  <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Status</option>
                  <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                  <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
              </div>
              <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">Go</button>
                <a href="classes.php" class="btn btn-outline-secondary w-100">Reset</a>
              </div>
            </div>
          </form>
        </div>

        <!-- CLASSES TABLE (dynamic) -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span class="fw-bold">All Academic Classes</span>
            <small class="text-muted">Total: <?= (int) $totalClasses ?> Classes</small>
          </div>
          <div class="table-responsive">
            <table class="table table-custom align-middle" id="classesTable">
              <thead>
                <tr>
                  <th>Class Name</th>
                  <th>Class Teacher</th>
                  <th>Room / Hall</th>
                  <th>Enrolled Students</th>
                  <th>Total Subjects</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($classes)): ?>
                  <tr>
                    <td colspan="7" class="text-center text-muted py-4">No classes found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($classes as $c): ?>
                    <tr>
                      <td class="fw-bold text-dark"><?= htmlspecialchars($c['name']) ?></td>
                      <td><?= htmlspecialchars($c['teacher_name'] ?? 'Unassigned') ?></td>
                      <td><?= htmlspecialchars($c['room_no'] ?? '—') ?></td>
                      <td><span class="badge bg-light text-dark border"><?= (int) $c['student_count'] ?> Students</span></td>
                      <td>N/A</td>
                      <td>
                        <?php if ($c['status'] === 'active'): ?>
                          <span class="badge-subtle badge-subtle-success">Active</span>
                        <?php else: ?>
                          <span class="badge-subtle badge-subtle-secondary">Inactive</span>
                        <?php endif; ?>
                      </td>
                      <td class="text-end">
                        <button type="button" class="btn btn-sm btn-outline-primary me-1 btn-view-students"
                          data-class-id="<?= (int) $c['id'] ?>"
                          data-class-name="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>" data-bs-toggle="modal"
                          data-bs-target="#viewStudentsModal" title="View Students">
                          <i class="bi bi-people-fill me-1"></i>Students
                        </button>
                        <button type="button" class="btn-action btn-action-edit btn-edit-class"
                          data-id="<?= (int) $c['id'] ?>" data-name="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>"
                          data-grade="<?= htmlspecialchars($c['grade'] ?? '', ENT_QUOTES) ?>"
                          data-teacher-id="<?= (int) ($c['teacher_id'] ?? 0) ?>"
                          data-room="<?= htmlspecialchars($c['room_no'] ?? '', ENT_QUOTES) ?>"
                          data-description="<?= htmlspecialchars($c['description'] ?? '', ENT_QUOTES) ?>"
                          data-status="<?= htmlspecialchars($c['status'], ENT_QUOTES) ?>" data-bs-toggle="modal"
                          data-bs-target="#editClassModal" title="Edit">
                          <i class="bi bi-pencil"></i>
                        </button>
                        <button type="button" class="btn-action btn-action-delete btn-delete-class"
                          data-id="<?= (int) $c['id'] ?>" data-item-name="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>"
                          title="Delete">
                          <i class="bi bi-trash"></i>
                        </button>
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

  <!-- ADD CLASS MODAL -->
  <div class="modal fade" id="addClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-door-open-fill text-primary me-2"></i>Add New Class</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form action="actions/class_action.php" method="post">
          <input type="hidden" name="action" value="create">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Class &amp; Section Name *</label>
              <input type="text" name="name" class="form-control" required maxlength="100"
                placeholder="e.g. Grade 11-B">
            </div>
            <div class="mb-3">
              <label class="form-label">Grade</label>
              <input type="text" name="grade" class="form-control" maxlength="50" placeholder="e.g. 11">
            </div>
            <div class="mb-3">
              <label class="form-label">Assigned Class Teacher</label>
              <select name="teacher_id" class="form-select">
                <option value="">Select teacher</option>
                <?php foreach ($activeTeachers as $t): ?>
                  <option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Room Number / Hall</label>
              <input type="text" name="room_no" class="form-control" maxlength="50" placeholder="e.g. Room 204">
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="2"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Status</label>
              <select name="status" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Create Class</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- EDIT CLASS MODAL -->
  <div class="modal fade" id="editClassModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Class Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form action="actions/class_action.php" method="post">
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
          <input type="hidden" name="id" id="edit_id">
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Class Name *</label>
              <input type="text" name="name" id="edit_name" class="form-control" required maxlength="100">
            </div>
            <div class="mb-3">
              <label class="form-label">Grade</label>
              <input type="text" name="grade" id="edit_grade" class="form-control" maxlength="50">
            </div>
            <div class="mb-3">
              <label class="form-label">Class Teacher</label>
              <select name="teacher_id" id="edit_teacher_id" class="form-select">
                <option value="">Select teacher</option>
                <?php foreach ($activeTeachers as $t): ?>
                  <option value="<?= (int) $t['id'] ?>"><?= htmlspecialchars($t['full_name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Room</label>
              <input type="text" name="room_no" id="edit_room_no" class="form-control" maxlength="50">
            </div>
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
            </div>
            <div class="mb-3">
              <label class="form-label">Status</label>
              <select name="status" id="edit_status" class="form-select">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
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

  <!-- VIEW STUDENTS MODAL -->
  <div class="modal fade" id="viewStudentsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-people-fill text-primary me-2"></i>Class Roster - <span
              id="rosterClassName"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-0">
          <ul class="list-group list-group-flush" id="rosterList">
            <li class="list-group-item text-center text-muted p-3">Loading...</li>
          </ul>
        </div>
        <div class="modal-footer">
          <a href="students.php" class="btn btn-primary btn-sm">Go to Full Directory</a>
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        </div>
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
          <form action="actions/class_action.php" method="post" class="d-flex justify-content-center gap-2 mt-3">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf) ?>">
            <input type="hidden" name="class_id" id="delete_class_id">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger">Delete</button>
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
          <p class="text-muted small">Are you sure you want to end your session?</p>
          <div class="d-flex justify-content-center gap-2 mt-3">
            <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
            <a href="logout.php" class="btn btn-danger">Logout</a>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div id="toastContainer"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/script.js"></script>
  <script src="js/classes.js"></script>
</body>

</html>