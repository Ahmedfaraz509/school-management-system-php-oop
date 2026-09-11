<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>My Profile | Teacher Dashboard</title>
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
      <a href="profile.php" class="active"><i class="bi bi-person-badge"></i> My Profile</a>
      <a href="settings.php"><i class="bi bi-gear"></i> Settings</a>
      <a href="#" class="logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
  </aside>
  <div class="td-main">
    <header class="td-navbar">
      <label for="tdSidebarToggle" class="td-burger"><i class="bi bi-list"></i></label>
      <h1 class="td-page-title">My Profile <small>Personal and professional information</small></h1>
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
        <div class="col-lg-4">
          <div class="card text-center h-100"><div class="card-body">
            <div class="td-avatar lg mx-auto mb-3">MA</div>
            <h5 class="fw-bold mb-0">Mr. Ahmed</h5>
            <p class="text-muted small">Mathematics Teacher</p>
            <span class="badge bg-success mb-3">Active</span>
            <ul class="list-unstyled text-start small">
              <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Teacher ID</span><strong>TCH-2045</strong></li>
              <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Email</span><strong>ahmed@brightfuture.edu</strong></li>
              <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Phone</span><strong>0300-1122334</strong></li>
              <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Subject</span><strong>Mathematics</strong></li>
              <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Classes</span><strong>10-A, 10-B, 9-B, 8-A</strong></li>
              <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Qualification</span><strong>M.Sc Mathematics</strong></li>
              <li class="d-flex justify-content-between py-2 border-bottom"><span class="text-muted">Joining Date</span><strong>01 Sep 2018</strong></li>
              <li class="d-flex justify-content-between py-2"><span class="text-muted text-nowrap me-2">Address</span><strong class="text-end">45-B Model Town, Lahore</strong></li>
            </ul>
          </div></div>
        </div>

        <div class="col-lg-8">
          <div class="card h-100">
            <div class="card-header"><i class="bi bi-pencil-square me-2 text-primary"></i>Edit Profile</div>
            <div class="card-body">
              <form>
                <div class="row g-3">
                  <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" class="form-control" value="Mr. Ahmed"></div>
                  <div class="col-md-6"><label class="form-label">Teacher ID</label><input type="text" class="form-control" value="TCH-2045" readonly></div>
                  <div class="col-md-6"><label class="form-label">Email Address</label><input type="email" class="form-control" value="ahmed@brightfuture.edu"></div>
                  <div class="col-md-6"><label class="form-label">Phone Number</label><input type="tel" class="form-control" value="0300-1122334"></div>
                  <div class="col-md-6"><label class="form-label">Subject</label>
                    <select class="form-select"><option selected>Mathematics</option><option>Physics</option><option>Computer Science</option></select></div>
                  <div class="col-md-6"><label class="form-label">Classes</label><input type="text" class="form-control" value="10-A, 10-B, 9-B, 8-A"></div>
                  <div class="col-md-6"><label class="form-label">Qualification</label><input type="text" class="form-control" value="M.Sc Mathematics"></div>
                  <div class="col-md-6"><label class="form-label">Joining Date</label><input type="date" class="form-control" value="2018-09-01"></div>
                  <div class="col-md-6"><label class="form-label">Date of Birth</label><input type="date" class="form-control" value="1988-04-16"></div>
                  <div class="col-md-6"><label class="form-label">Gender</label>
                    <select class="form-select"><option selected>Male</option><option>Female</option></select></div>
                  <div class="col-12"><label class="form-label">Address</label>
                    <textarea class="form-control" rows="2">45-B Model Town, Lahore, Pakistan</textarea></div>
                  <div class="col-12"><label class="form-label">About Me</label>
                    <textarea class="form-control" rows="3">Passionate mathematics educator with over 7 years of teaching experience in secondary school level, focused on concept-based learning.</textarea></div>
                  <div class="col-12"><label class="form-label">Profile Photo</label><input type="file" class="form-control"></div>
                </div>
                <div class="mt-4 d-flex gap-2">
                  <button type="submit" class="btn btn-primary"><i class="bi bi-check2"></i> Save Changes</button>
                  <button type="reset" class="btn btn-outline-secondary">Reset</button>
                </div>
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
