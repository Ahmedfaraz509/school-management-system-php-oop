<?php
declare(strict_types=1);

/**
 * Student Controller for EduPulse SMS
 * 
 * Handles all HTTP requests for student management.
 */

namespace EduPulse\Controllers;

use EduPulse\Helpers\Auth;
use EduPulse\Helpers\Csrf;
use EduPulse\Helpers\Response;
use EduPulse\Models\Student;
use EduPulse\Models\User;
use EduPulse\Models\ClassModel;
use PDO;

class StudentController
{
  private Student $studentModel;
  private User $userModel;
  private ClassModel $classModel;
  private PDO $pdo;

  public function __construct(PDO $pdo)
  {
    $this->pdo = $pdo;
    $this->studentModel = new Student($pdo);
    $this->userModel = new User($pdo);
    $this->classModel = new ClassModel($pdo);
  }

  /**
   * Handle all HTTP requests
   */
  public function handleRequest(): void
  {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? 'index';

    // Set JSON header for AJAX requests
    if ($this->isAjax()) {
      header('Content-Type: application/json; charset=utf-8');
    }

    switch ($method) {
      case 'GET':
        $this->handleGet($action);
        break;
      case 'POST':
        $this->handlePost($action);
        break;
      default:
        Response::jsonError('Method not allowed', 405);
    }
  }

  /**
   * Handle GET requests
   */
  private function handleGet(string $action): void
  {
    switch ($action) {
      case 'index':
      case 'list':
        $this->index();
        break;
      case 'get':
        $this->get();
        break;
      case 'classes':
        $this->getClasses();
        break;
      case 'export':
        $this->export();
        break;
      default:
        Response::jsonError('Action not found', 404);
    }
  }

  /**
   * Handle POST requests
   */
  private function handlePost(string $action): void
  {
    // Validate CSRF for all POST requests
    if (!Csrf::validate()) {
      Response::jsonError('Invalid or missing CSRF token. Please refresh the page and try again.', 403);
    }

    switch ($action) {
      case 'create':
        $this->create();
        break;
      case 'update':
        $this->update();
        break;
      case 'delete':
        $this->delete();
        break;
      case 'search':
        $this->search();
        break;
      default:
        Response::jsonError('Action not found', 404);
    }
  }

  /**
   * Get paginated list of students
   */
  private function index(): void
  {
    // Auth check
    if (!Auth::canManageStudents()) {
      Response::jsonError('Unauthorized', 401);
    }

    $page = (int) ($_GET['page'] ?? 1);
    $search = $_GET['search'] ?? null;

    $filters = [
      'class_id' => $_GET['class_id'] ?? null,
      'gender' => $_GET['gender'] ?? null,
      'status' => $_GET['status'] ?? null
    ];

    // Validate gender
    if (!empty($filters['gender']) && !in_array($filters['gender'], Student::VALID_GENDERS)) {
      $filters['gender'] = null;
    }

    // Validate status
    if (!empty($filters['status']) && !in_array($filters['status'], Student::VALID_STATUSES)) {
      $filters['status'] = null;
    }

    $result = $this->studentModel->getAll($page, 10, $filters, $search);

    Response::jsonPaginated(
      $result['students'],
      $result['total_records'],
      $result['current_page'],
      $result['total_pages']
    );
  }

  /**
   * Get single student by ID
   */
  private function get(): void
  {
    if (!Auth::canManageStudents()) {
      Response::jsonError('Unauthorized', 401);
    }

    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
      Response::jsonError('Invalid student ID');
    }

    $student = $this->studentModel->getById($id);

    if (!$student) {
      Response::jsonError('Student not found', 404);
    }

    Response::jsonSuccess('Student retrieved successfully', ['student' => $student]);
  }

  /**
   * Get all active classes for dropdown
   */
  private function getClasses(): void
  {
    $classes = $this->classModel->getAllActive();
    Response::jsonSuccess('Classes retrieved successfully', ['classes' => $classes]);
  }

  /**
   * Create a new student
   */
  private function create(): void
  {
    if (!Auth::canManageStudents()) {
      Response::jsonError('Unauthorized', 401);
    }

    // Get and validate input
    $data = $this->validateCreateInput();

    if (isset($data['errors']) && !empty($data['errors'])) {
      Response::jsonError('Validation failed', 400, $data['errors']);
    }

    // Check email uniqueness in users table
    if ($this->userModel->emailExists($data['email'])) {
      Response::jsonError('This email address is already registered in the system.');
    }

    // Check email uniqueness in students table
    if ($this->studentModel->emailExists($data['email'])) {
      Response::jsonError('This email address is already assigned to another student.');
    }

    // Validate class exists
    if (!empty($data['class_id']) && !$this->classModel->exists((int) $data['class_id'])) {
      Response::jsonError('Selected class does not exist.');
    }

    // Handle photo upload
    $photoUrl = $this->handlePhotoUpload();
    if ($photoUrl === false) {
      Response::jsonError('Failed to upload photo. Please check the file type and size.');
    }
    $data['photo_url'] = $photoUrl;

    // Create student
    $studentId = $this->studentModel->create($data);

    if (!$studentId) {
      // Delete uploaded photo if student creation failed
      if ($photoUrl) {
        @unlink(BASE_PATH . $photoUrl);
      }
      Response::jsonError('Unable to save student. Please try again.');
    }

    $newToken = Csrf::regenerate();
    Response::jsonSuccess('Student registered successfully!', [
      'student_id' => $studentId,
      'redirect' => 'students.php',
      'csrf_token' => $newToken
    ]);
  }

  /**
   * Update an existing student
   */
  private function update(): void
  {
    if (!Auth::canManageStudents()) {
      Response::jsonError('Unauthorized', 401);
    }

    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
      Response::jsonError('Invalid student ID');
    }

    // Get existing student
    $student = $this->studentModel->getById($id);
    if (!$student) {
      Response::jsonError('Student not found', 404);
    }

    // Validate input
    $data = $this->validateUpdateInput($id);

    if (isset($data['errors']) && !empty($data['errors'])) {
      Response::jsonError('Validation failed', 400, $data['errors']);
    }

    // Check email uniqueness if changed
    if (!empty($data['email']) && $data['email'] !== $student['email']) {
      if ($this->userModel->emailExists($data['email']) && $data['email'] !== $student['user_email']) {
        Response::jsonError('This email address is already registered in the system.');
      }
      if ($this->studentModel->emailExists($data['email'], $id)) {
        Response::jsonError('This email address is already assigned to another student.');
      }
    }

    // Validate class exists
    if (!empty($data['class_id']) && !$this->classModel->exists((int) $data['class_id'])) {
      Response::jsonError('Selected class does not exist.');
    }

    // Handle photo upload if new photo provided
    $photoUrl = $this->handlePhotoUpload();
    if ($photoUrl === false) {
      Response::jsonError('Failed to upload photo. Please check the file type and size.');
    }
    if ($photoUrl !== null) {
      $data['photo_url'] = $photoUrl;

      // Delete old photo if exists
      if (!empty($student['photo_url'])) {
        @unlink(BASE_PATH . $student['photo_url']);
      }
    }

    // Update student
    $success = $this->studentModel->update($id, $data);

    if (!$success) {
      Response::jsonError('Unable to update student. Please try again.');
    }

    $newToken = Csrf::regenerate();
    Response::jsonSuccess('Student updated successfully!', ['csrf_token' => $newToken]);
  }

  /**
   * Delete a student
   */
  private function delete(): void
  {
    if (!Auth::canManageStudents()) {
      Response::jsonError('Unauthorized', 401);
    }

    $id = (int) ($_POST['id'] ?? 0);

    if ($id <= 0) {
      Response::jsonError('Invalid student ID');
    }

    // Get student for photo deletion
    $student = $this->studentModel->getById($id);
    if (!$student) {
      Response::jsonError('Student not found', 404);
    }

    // Delete student
    $success = $this->studentModel->delete($id);

    if (!$success) {
      Response::jsonError('Unable to delete student. Please try again.');
    }

    // Delete photo if exists
    if (!empty($student['photo_url'])) {
      @unlink(BASE_PATH . $student['photo_url']);
    }

    $newToken = Csrf::regenerate();
    Response::jsonSuccess('Student deleted successfully!', ['csrf_token' => $newToken]);
  }

  /**
   * Search students
   */
  private function search(): void
  {
    if (!Auth::canManageStudents()) {
      Response::jsonError('Unauthorized', 401);
    }

    $term = $_POST['term'] ?? '';

    $students = $this->studentModel->search($term);

    Response::jsonSuccess('Search completed', ['students' => $students]);
  }

  /**
   * Export students to CSV
   */
  private function export(): void
  {
    if (!Auth::canManageStudents()) {
      Response::jsonError('Unauthorized', 401);
    }

    $search = $_GET['search'] ?? null;

    $filters = [
      'class_id' => $_GET['class_id'] ?? null,
      'gender' => $_GET['gender'] ?? null,
      'status' => $_GET['status'] ?? null
    ];

    $students = $this->studentModel->getAllForExport($filters, $search);

    if (empty($students)) {
      Response::jsonError('No students found to export');
    }

    // Generate CSV
    $this->generateCsv($students);
  }

  /**
   * Validate create input
   */
  private function validateCreateInput(): array
  {
    $errors = [];
    $data = [];

    // First name
    $data['first_name'] = trim($_POST['first_name'] ?? '');
    if (empty($data['first_name'])) {
      $errors['first_name'] = 'First name is required';
    } elseif (strlen($data['first_name']) > 100) {
      $errors['first_name'] = 'First name must not exceed 100 characters';
    }

    // Last name
    $data['last_name'] = trim($_POST['last_name'] ?? '');
    if (empty($data['last_name'])) {
      $errors['last_name'] = 'Last name is required';
    } elseif (strlen($data['last_name']) > 100) {
      $errors['last_name'] = 'Last name must not exceed 100 characters';
    }

    // Email
    $data['email'] = trim($_POST['email'] ?? '');
    if (empty($data['email'])) {
      $errors['email'] = 'Email is required';
    } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
      $errors['email'] = 'Please enter a valid email address';
    } elseif (strlen($data['email']) > 255) {
      $errors['email'] = 'Email must not exceed 255 characters';
    }

    // Gender
    $data['gender'] = $_POST['gender'] ?? '';
    if (empty($data['gender'])) {
      $errors['gender'] = 'Gender is required';
    } elseif (!in_array($data['gender'], Student::VALID_GENDERS)) {
      $errors['gender'] = 'Invalid gender selection';
    }

    // Date of birth
    $data['date_of_birth'] = $_POST['date_of_birth'] ?? '';
    if (empty($data['date_of_birth'])) {
      $errors['date_of_birth'] = 'Date of birth is required';
    } elseif (!$this->isValidDate($data['date_of_birth'])) {
      $errors['date_of_birth'] = 'Please enter a valid date';
    }

    // Class ID
    $data['class_id'] = $_POST['class_id'] ?? '';
    // Class is optional but if provided must be numeric
    if (!empty($data['class_id']) && !is_numeric($data['class_id'])) {
      $errors['class_id'] = 'Invalid class selection';
    }

    // Admission date
    $data['admission_date'] = $_POST['admission_date'] ?? '';
    if (empty($data['admission_date'])) {
      $errors['admission_date'] = 'Admission date is required';
    } elseif (!$this->isValidDate($data['admission_date'])) {
      $errors['admission_date'] = 'Please enter a valid date';
    }

    // Status
    $data['status'] = $_POST['status'] ?? 'Active';
    if (!in_array($data['status'], Student::VALID_STATUSES)) {
      $data['status'] = 'Active';
    }

    // Address (optional)
    $data['address'] = trim($_POST['address'] ?? '');

    // Password
    $password = $_POST['password'] ?? '';
    if (empty($password)) {
      $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
      $errors['password'] = 'Password must be at least 8 characters';
    }
    $data['password'] = $password;

    if (!empty($errors)) {
      $data['errors'] = $errors;
    }

    return $data;
  }

  /**
   * Validate update input
   */
  private function validateUpdateInput(int $excludeId): array
  {
    $errors = [];
    $data = [];

    // First name
    if (isset($_POST['first_name'])) {
      $data['first_name'] = trim($_POST['first_name']);
      if (empty($data['first_name'])) {
        $errors['first_name'] = 'First name is required';
      } elseif (strlen($data['first_name']) > 100) {
        $errors['first_name'] = 'First name must not exceed 100 characters';
      }
    }

    // Last name
    if (isset($_POST['last_name'])) {
      $data['last_name'] = trim($_POST['last_name']);
      if (empty($data['last_name'])) {
        $errors['last_name'] = 'Last name is required';
      } elseif (strlen($data['last_name']) > 100) {
        $errors['last_name'] = 'Last name must not exceed 100 characters';
      }
    }

    // Email
    if (isset($_POST['email'])) {
      $data['email'] = trim($_POST['email']);
      if (empty($data['email'])) {
        $errors['email'] = 'Email is required';
      } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
      } elseif (strlen($data['email']) > 255) {
        $errors['email'] = 'Email must not exceed 255 characters';
      }
    }

    // Gender
    if (isset($_POST['gender'])) {
      $data['gender'] = $_POST['gender'];
      if (!in_array($data['gender'], Student::VALID_GENDERS)) {
        $errors['gender'] = 'Invalid gender selection';
      }
    }

    // Date of birth
    if (isset($_POST['date_of_birth'])) {
      $data['date_of_birth'] = $_POST['date_of_birth'];
      if (empty($data['date_of_birth'])) {
        $errors['date_of_birth'] = 'Date of birth is required';
      } elseif (!$this->isValidDate($data['date_of_birth'])) {
        $errors['date_of_birth'] = 'Please enter a valid date';
      }
    }

    // Class ID
    if (isset($_POST['class_id'])) {
      $data['class_id'] = $_POST['class_id'];
    }

    // Admission date
    if (isset($_POST['admission_date'])) {
      $data['admission_date'] = $_POST['admission_date'];
      if (empty($data['admission_date'])) {
        $errors['admission_date'] = 'Admission date is required';
      } elseif (!$this->isValidDate($data['admission_date'])) {
        $errors['admission_date'] = 'Please enter a valid date';
      }
    }

    // Status
    if (isset($_POST['status'])) {
      $data['status'] = $_POST['status'];
      if (!in_array($data['status'], Student::VALID_STATUSES)) {
        $errors['status'] = 'Invalid status selection';
      }
    }

    // Address
    if (isset($_POST['address'])) {
      $data['address'] = trim($_POST['address']);
    }

    // Password (optional for update)
    if (!empty($_POST['password'])) {
      $password = $_POST['password'];
      if (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
      } else {
        $data['password'] = $password;
      }
    }

    if (!empty($errors)) {
      $data['errors'] = $errors;
    }

    return $data;
  }

  /**
   * Handle photo upload
   * 
   * @return string|null|false The upload path on success, null if no upload, false on error
   */
  private function handlePhotoUpload(): string|false|null
  {
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] === UPLOAD_ERR_NO_FILE) {
      return null; // No file uploaded
    }

    $file = $_FILES['photo'];

    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
      error_log('Photo upload error: ' . $file['error']);
      return false;
    }

    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
      return false;
    }

    // Get file info
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    // Validate MIME type
    if (!in_array($mimeType, ALLOWED_MIME_TYPES)) {
      return false;
    }

    // Get extension
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    // Validate extension
    if (!in_array($extension, ALLOWED_EXTENSIONS)) {
      return false;
    }

    // Generate unique filename
    $filename = 'student_' . bin2hex(random_bytes(8)) . '.' . $extension;

    // Ensure upload directory exists
    if (!ensureUploadDirectory(UPLOAD_PATH)) {
      return false;
    }

    // Set upload path
    $uploadPath = UPLOAD_PATH . '/' . $filename;
    $uploadUrl = UPLOAD_URL . '/' . $filename;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
      return false;
    }

    return $uploadUrl;
  }

  /**
   * Check if date is valid
   */
  private function isValidDate(string $date): bool
  {
    $d = \DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
  }

  /**
   * Generate CSV file for export
   */
  private function generateCsv(array $data): void
  {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=students_export_' . date('Y-m-d_His') . '.csv');

    // Add BOM for Excel UTF-8 compatibility
    echo "\xEF\xBB\xBF";

    if (empty($data)) {
      echo "No data available";
      exit;
    }

    $output = fopen('php://output', 'w');

    // Write header
    fputcsv($output, array_keys($data[0]));

    // Write data
    foreach ($data as $row) {
      fputcsv($output, $row);
    }

    fclose($output);
    exit;
  }

  /**
   * Check if request is AJAX
   */
  private function isAjax(): bool
  {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
      strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
  }
}