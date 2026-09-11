<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Settings | Teacher Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<input type="checkbox" id="tdSidebarToggle">
<div class="td-wrapper">
  <label for="tdSidebarToggle" class="td-overlay"></label>
  <aside class="td-sidebar">
    <div class="td-brand"><i class="bi bi-mortarboard-fill"></i><span>Bright Future<small>School Portal</small></span></div>
    <div class="td-teacher-box"><div class="td-avatar">MA</div><div><h6>Mr. Ahmed</h6><p>Mathematics Teacher</p></div></div>
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
      <a href="notices.php"><i class="bi bi-megaphone"></i> Notices</a>
      <a href="messages.php"><i class="bi bi-chat-dots"></i> Messages</a>
      <div class="td-nav-title">Account</div>
      <a href="profile.php"><i class="bi bi-person-badge"></i> My Profile</a>
      <a href="settings.php" class="active"><i class="bi bi-gear"></i> Settings</a>
      <a href="#" class="logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
  </aside>
  <div class="td-main">
    <header class="td-navbar">
      <label for="tdSidebarToggle" class="td-burger"><i class="bi bi-list"></i></label>
      <h1 class="td-page-title">Settings <small>Account and notification preferences</small></h1>
      <div class="td-search ms-auto"><div class="input-group">
        <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
        <input type="search" class="form-control border-start-0" placeholder="Search...">
      </div></div>
      <a href="notices.php" class="td-icon-btn"><i class="bi bi-bell"></i><span class="td-dot"></span></a>
      <a href="profile.php" class="d-flex align-items-center gap-2 text-dark"><span class="td-avatar">MA</span>
        <span class="d-none d-md-block"><strong class="d-block" style="font-size:.85rem">Mr. Ahmed</strong><small class="text-muted" style="font-size:.72rem">Mathematics Teacher</small></span></a>
    </header>
    <main class="td-content">
      <div class="row g-3">
        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header"><i class="bi bi-person-gear me-2 text-primary"></i>Account Settings</div>
            <div class="card-body">
              <form>
                <div class="mb-3"><label class="form-label">Name</label><input type="text" class="form-control" value="Mr. Ahmed"></div>
                <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" value="ahmed@brightfuture.edu"></div>
                <div class="mb-3"><label class="form-label">Phone</label><input type="tel" class="form-control" value="0300-1122334"></div>
                <div class="mb-3"><label class="form-label">Language</label>
                  <select class="form-select"><option selected>English</option><option>Urdu</option><option>Arabic</option></select></div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Save Account Settings</button>
              </form>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card h-100">
            <div class="card-header"><i class="bi bi-bell me-2 text-primary"></i>Notification Settings</div>
            <div class="card-body">
              <form>
                <div class="setting-row">
                  <div><strong class="d-block small">Email Notifications</strong><p>Receive general updates by email</p></div>
                  <input class="form-check-input m-0" type="checkbox" checked>
                </div>
                <div class="setting-row">
                  <div><strong class="d-block small">Assignment Notifications</strong><p>Alerts when students submit assignments</p></div>
                  <input class="form-check-input m-0" type="checkbox" checked>
                </div>
                <div class="setting-row">
                  <div><strong class="d-block small">Exam Notifications</strong><p>Reminders about upcoming exams</p></div>
                  <input class="form-check-input m-0" type="checkbox" checked>
                </div>
                <div class="setting-row">
                  <div><strong class="d-block small">Attendance Notifications</strong><p>Daily attendance summary alerts</p></div>
                  <input class="form-check-input m-0" type="checkbox">
                </div>
                <div class="setting-row">
                  <div><strong class="d-block small">Notice Notifications</strong><p>New school notices and circulars</p></div>
                  <input class="form-check-input m-0" type="checkbox" checked>
                </div>
                <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check2"></i> Save Notification Settings</button>
              </form>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card">
            <div class="card-header"><i class="bi bi-shield-lock me-2 text-primary"></i>Change Password</div>
            <div class="card-body">
              <form>
                <div class="mb-3"><label class="form-label">Current Password</label><input type="password" class="form-control" placeholder="••••••••"></div>
                <div class="mb-3"><label class="form-label">New Password</label><input type="password" class="form-control" placeholder="••••••••"></div>
                <div class="mb-3"><label class="form-label">Confirm New Password</label><input type="password" class="form-control" placeholder="••••••••"></div>
                <button type="submit" class="btn btn-primary"><i class="bi bi-key"></i> Update Password</button>
              </form>
            </div>
          </div>
        </div>

        <div class="col-lg-6">
          <div class="card">
            <div class="card-header"><i class="bi bi-sliders me-2 text-primary"></i>Preferences</div>
            <div class="card-body">
              <form>
                <div class="mb-3"><label class="form-label">Date Format</label>
                  <select class="form-select"><option selected>DD MMM YYYY</option><option>MM/DD/YYYY</option><option>YYYY-MM-DD</option></select></div>
                <div class="mb-3"><label class="form-label">Time Zone</label>
                  <select class="form-select"><option selected>(GMT+5) Pakistan Standard Time</option><option>(GMT+4) Gulf Standard Time</option><option>(GMT+0) UTC</option></select></div>
                <div class="setting-row">
                  <div><strong class="d-block small">Show Profile to Parents</strong><p>Allow parents to view your contact info</p></div>
                  <input class="form-check-input m-0" type="checkbox" checked>
                </div>
                <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-check2"></i> Save Preferences</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </main>
    <footer class="td-footer">© 2026 Bright Future School — Teacher Panel.</footer>
  </div>
</div>
</body>
</html>
