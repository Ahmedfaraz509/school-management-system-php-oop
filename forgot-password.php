<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Forgot Password | SchoolMS — Smart School Management System</title>

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
          <div class="auth-center-logo"><i class="bi bi-key"></i></div>
          <div class="card-head">
            <h1>Forgot Password?</h1>
            <p>Enter your registered email address and we will send instructions to reset your password.</p>
          </div>

          <!-- Forgot password form (STATIC frontend UI) -->
          <form class="auth-form" action="#" method="post">
            <div class="mb-3">
              <label for="fpEmail" class="form-label">Email Address</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                <input type="email" class="form-control" id="fpEmail"
                       placeholder="you@example.com" aria-label="Registered email address" required>
              </div>
            </div>

            <a href="reset-password.php" class="btn btn-brand w-100">
              <i class="bi bi-send me-1"></i> Send Reset Link
            </a>
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
