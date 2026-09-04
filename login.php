<?php
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once '../database/connect.php';

// Handled Connection Object Detection
if (!isset($conn) || !$conn) {
  if (isset($database) && method_exists($database, 'getConnection')) {
    $conn = $database->getConnection();
  }
}

$errorMessage = '';

class Login
{
  private $email;
  private $password;

  public function __construct($email, $password)
  {
    $this->email = trim($email);
    $this->password = $password;
  }

  public function loginUser($conn)
  {
    if (!$conn || !($conn instanceof PDO)) {
      return [
        'success' => false,
        'message' => 'Database connection unavailable.'
      ];
    }

    try {
      // Automatically fetch user & role dynamically from DB based on Email
      $query = "SELECT * FROM users WHERE email = :email AND status = 'active' LIMIT 1";
      $stmt = $conn->prepare($query);
      $stmt->bindParam(':email', $this->email);
      $stmt->execute();

      $user = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$user) {
        return [
          'success' => false,
          'message' => 'Invalid email address or account is inactive.'
        ];
      }

      // Checks both Plain Text (admin123) and Hashed Passwords
      $isPasswordValid = false;
      if (password_verify($this->password, $user['password'])) {
        $isPasswordValid = true;
      } elseif ($this->password === $user['password']) {
        $isPasswordValid = true;
      }

      if (!$isPasswordValid) {
        return [
          'success' => false,
          'message' => 'Invalid password.'
        ];
      }

      session_regenerate_id(true);

      // Save Dynamic DB Record into Session
      $_SESSION['user_id'] = $user['id'];
      $_SESSION['user_name'] = $user['name'];
      $_SESSION['user_email'] = $user['email'];
      $_SESSION['user_role'] = $user['role'];
      $_SESSION['logged_in'] = true;

      // Safe Update for last login time
      try {
        $update = $conn->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
        $update->bindParam(':id', $user['id']);
        $update->execute();
      } catch (PDOException $e) {
        // Ignore if date format mismatch in DB
      }

      // Dynamic Redirection based on role fetched from DB
      $userRole = strtolower(trim($user['role']));

      // Map each role to its actual dashboard folder/entry file
      $dashboardMap = [
        'admin'   => '../admin_dashbord/index.php',
        'teacher' => '../teacher/index.php',
        'student' => '../student-dashboard/index.php',
        'parent'  => '../parent-dashboard/index.php',
      ];

      $targetDashboard = $dashboardMap[$userRole] ?? '../admin_dashbord/index.php';

      if (file_exists($targetDashboard)) {
        header("Location: " . $targetDashboard);
        exit;
      } else {
        header("Location: ../admin_dashbord/index.php");
        exit;
      }

    } catch (PDOException $e) {
      return [
        'success' => false,
        'message' => 'Query Error: ' . $e->getMessage()
      ];
    }
  }
}

/*
|--------------------------------------------------------------------------
| LOGIN FORM SUBMIT PROCESS
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $email = $_POST['email'] ?? '';
  $password = $_POST['password'] ?? '';

  if (empty($email) || empty($password)) {
    $errorMessage = "Please fill in all required fields.";
  } else {
    $login = new Login($email, $password);
    $result = $login->loginUser($conn);

    if (!$result['success']) {
      $errorMessage = $result['message'];
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login | SchoolMS — Smart School Management System</title>

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

  <!-- Mobile brand header -->
  <div class="mobile-brand d-lg-none">
    <div class="mobile-brand-logo"><i class="bi bi-mortarboard-fill"></i></div>
    <div>
      <div class="mobile-brand-name">School<span>MS</span></div>
      <div class="mobile-brand-tagline">Smart School Management System</div>
    </div>
  </div>

  <main class="auth-main">
    <div class="container-fluid p-0">
      <div class="row g-0">
        <!-- ================= LEFT: School Branding ================= -->
        <div class="col-lg-7 auth-brand-panel d-none d-lg-flex">
          <div class="brand-panel-inner">
            <div class="brand-logo"><i class="bi bi-mortarboard-fill"></i></div>
            <h1 class="brand-name">School<span>MS</span></h1>
            <p class="brand-tagline">Smart School Management System</p>
            <p class="brand-desc">
              One secure portal for admins, teachers, students and parents —
              manage grades, attendance, assignments and announcements with ease.
            </p>

            <div class="brand-features">
              <div class="feature-item">
                <i class="bi bi-shield-check"></i>
                <div>
                  <strong>Secure Role-Based Access</strong>
                  <span>Dedicated portals for every role.</span>
                </div>
              </div>
              <div class="feature-item">
                <i class="bi bi-graph-up-arrow"></i>
                <div>
                  <strong>Track Progress &amp; Grades</strong>
                  <span>Stay updated on academic performance.</span>
                </div>
              </div>
              <div class="feature-item">
                <i class="bi bi-chat-square-text"></i>
                <div>
                  <strong>Easy Communication</strong>
                  <span>Announcements and messages in one place.</span>
                </div>
              </div>
            </div>

            <div class="brand-welcome">
              <i class="bi bi-arrow-right-circle"></i>
              <span>Welcome — sign in to continue your learning journey.</span>
            </div>
          </div>
        </div>

        <!-- ================= RIGHT: Login Card ================= -->
        <div class="col-lg-5 auth-form-panel">
          <div class="form-panel-inner">
            <div class="auth-card">
              <div class="card-head">
                <h2>Welcome Back!</h2>
                <p>Sign in to access your SchoolMS account.</p>
              </div>

              <!-- Dynamic Alert Message -->
              <?php if (!empty($errorMessage)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                  <i class="bi bi-exclamation-triangle-fill me-2"></i>
                  <?= htmlspecialchars($errorMessage); ?>
                  <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
              <?php endif; ?>

              <!-- Login Form -->
              <form class="auth-form" action="" method="post">

                <!-- Email Input -->
                <div class="mb-3">
                  <label for="loginEmail" class="form-label">Email Address</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input type="email" name="email" class="form-control" id="loginEmail" placeholder="you@example.com"
                      value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                  </div>
                </div>

                <!-- Password Input -->
                <div class="mb-3">
                  <label for="loginPassword" class="form-label">Password</label>
                  <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input type="password" name="password" class="form-control" id="loginPassword"
                      placeholder="Enter your password" required>
                  </div>
                </div>

                <div class="d-flex justify-content-between align-items-center mb-3">
                  <div class="form-check">
                    <input type="checkbox" name="rememberMe" class="form-check-input" id="rememberMe">
                    <label class="form-check-label" for="rememberMe">Remember me</label>
                  </div>
                  <a href="forgot-password.php" class="forgot-link">Forgot Password?</a>
                </div>

                <button type="submit" class="btn btn-brand w-100">Login</button>
              </form>

              <div class="auth-divider"><span>OR</span></div>

              <a href="register.php" class="btn btn-outline-brand w-100">
                <i class="bi bi-person-plus me-1"></i> Create Account
              </a>

              <p class="switch-text">Don't have an account? <a href="register.php">Register Now</a></p>
            </div>
          </div>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>