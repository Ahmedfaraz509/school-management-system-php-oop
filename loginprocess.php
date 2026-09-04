<?php

require_once '../database/connect.php';

class Login
{
  private $email;
  private $password;

  public function __construct($email, $password)
  {
    $this->email = trim($email);
    $this->password = $password;
  }

  public function loginUser($conn, $loginAs)
  {
    $query = "SELECT * FROM users
                  WHERE email = :email
                  AND role = :role
                  AND status = 'active'
                  LIMIT 1";

    $stmt = $conn->prepare($query);

    $stmt->bindParam(':email', $this->email);
    $stmt->bindParam(':role', $loginAs);

    $stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
      return [
        'success' => false,
        'message' => 'Invalid email, role or account is inactive.'
      ];
    }

    if (!password_verify($this->password, $user['password'])) {
      return [
        'success' => false,
        'message' => 'Invalid password.'
      ];
    }

    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }

    session_regenerate_id(true);

    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['logged_in'] = true;

    $update = $conn->prepare(
      "UPDATE users SET last_login_at = NOW() WHERE id = :id"
    );

    $update->bindParam(':id', $user['id']);
    $update->execute();

    if ($user['role'] === 'admin') {

      header("Location: ../admin_dashbord/index.php");
      exit;

    } elseif ($user['role'] === 'teacher') {

      header("Location: ../teacher/index.php");
      exit;

    } elseif ($user['role'] === 'student') {

      header("Location: ../student-dashboard/index.php");
      exit;

    } elseif ($user['role'] === 'parent') {

      header("Location: ../parent-dashboard/index.php");
      exit;
    }

    return [
      'success' => false,
      'message' => 'Invalid role.'
    ];
  }
}


/*
|--------------------------------------------------------------------------
| LOGIN FORM PROCESS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $email = $_POST['email'] ?? '';
  $password = $_POST['password'] ?? '';
  $loginAs = $_POST['loginAs'] ?? '';

  if (empty($email) || empty($password) || empty($loginAs)) {

    die("Please fill all fields.");

  }

  $login = new Login($email, $password);

  $login->loginUser($conn, $loginAs);
}

?>