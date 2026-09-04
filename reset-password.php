<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reset Password | SchoolMS — Smart School Management System</title>

  <!-- Bootstrap 5 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <!-- Google Font: Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <!-- Auth Custom CSS -->
  <link rel="stylesheet" href="css/auth.css">
</head>
<body class="auth-page">

  <!-- Mobile brand header (visible on small screens only) -->
  <div class="mobile-brand d-lg-none">
    <div class="mobile-brand-logo"><i class="bi bi-mortarboard-fill"></i></div>
    <div>
      <div class="mobile-brand-name">School<span>MS</span></div>
      <div class="mobile-brand-tagline">Smart School Management System</div>
    </div>
  </div>

  <main class="auth-main">
    <div class="auth-center">
      <div class="container">
        <div class="auth-card">
          <div class="auth-center-logo"><i class="bi bi-lock"></i></div>
          <div class="card-head">
            <h1>Create New Password</h1>
            <p>Enter your new password below. Make sure it is strong and secure.</p>
          </div>

          <!-- Reset password form (STATIC frontend UI) -->
          <form class="auth-form" action="#" method="post">
            <div class="mb-3">
              <label for="rpPassword" class="form-label">New Password</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock"></i></span>
                <input type="password" class="form-control" id="rpPassword"
                       placeholder="At least 8 characters" aria-label="New password" minlength="8" required>
              </div>
              <div class="hint-text">Minimum 8 characters. Use a mix of letters, numbers and symbols.</div>
            </div>

            <div class="mb-3">
              <label for="rpConfirmPassword" class="form-label">Confirm New Password</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                <input type="password" class="form-control" id="rpConfirmPassword"
                       placeholder="Re-enter your new password" aria-label="Confirm new password" minlength="8" required>
              </div>
            </div>

            <button type="submit" class="btn btn-brand w-100">
              <i class="bi bi-check-circle me-1"></i> Reset Password
            </button>
          </form>

          <a href="login.php" class="back-link">
            <i class="bi bi-arrow-left"></i> Back to Login
          </a>
        </div>
      </div>
    </div>
  </main>

  <!-- Footer -->
  <footer class="auth-footer">
    <div class="container">
      <span>&copy; 2026 SchoolMS. All rights reserved.</span>
      <nav aria-label="Footer">
        <a href="#" aria-label="Privacy Policy">Privacy Policy</a>
        <a href="#" aria-label="Terms and Conditions">Terms &amp; Conditions</a>
        <a href="#" aria-label="Contact School">Contact School</a>
      </nav>
    </div>
  </footer>

</body>
</html>
