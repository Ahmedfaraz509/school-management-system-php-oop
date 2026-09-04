<?php
// actions/class_action.php
declare(strict_types=1);

require_once __DIR__ . '/../config/classes_bootstrap.php';
require_once __DIR__ . '/../models/ClassModel.php';
require_once __DIR__ . '/../models/Teacher.php';

// --- Auth guard ---
cm_require_auth();
if (!cm_is_admin()) {
  cm_flash_set('danger', 'You are not authorized to perform this action.');
  header('Location: ../classes.php');
  exit;
}

// --- Only POST allowed ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  cm_flash_set('danger', 'Invalid request method.');
  header('Location: ../classes.php');
  exit;
}

// --- CSRF ---
if (!cm_csrf_verify($_POST['csrf_token'] ?? null)) {
  cm_flash_set('danger', 'Security validation failed. Please try again.');
  header('Location: ../classes.php');
  exit;
}

$classModel = new ClassModel($pdo);
$teacherModel = new Teacher($pdo);

$action = $_POST['action'] ?? '';

/** Validate + sanitize class input. Returns [data, errors]. */
function validateClassInput(array $post, Teacher $teacherModel): array
{
  $errors = [];

  $name = trim((string) ($post['name'] ?? ''));
  $grade = trim((string) ($post['grade'] ?? ''));
  $teacherId = trim((string) ($post['teacher_id'] ?? ''));
  $roomNo = trim((string) ($post['room_no'] ?? ''));
  $description = trim((string) ($post['description'] ?? ''));
  $status = trim((string) ($post['status'] ?? 'active'));

  // Name
  if ($name === '') {
    $errors[] = 'Class name is required.';
  } elseif (mb_strlen($name) > 100) {
    $errors[] = 'Class name must not exceed 100 characters.';
  }

  // Grade (optional)
  if ($grade !== '' && mb_strlen($grade) > 50) {
    $errors[] = 'Grade must not exceed 50 characters.';
  }

  // Teacher (optional, but must exist if provided)
  $teacherIdVal = null;
  if ($teacherId !== '') {
    if (!ctype_digit($teacherId) || !$teacherModel->exists((int) $teacherId)) {
      $errors[] = 'Selected teacher does not exist.';
    } else {
      $teacherIdVal = (int) $teacherId;
    }
  }

  // Room (optional)
  if ($roomNo !== '' && mb_strlen($roomNo) > 50) {
    $errors[] = 'Room must not exceed 50 characters.';
  }

  // Status
  if (!in_array($status, ['active', 'inactive'], true)) {
    $errors[] = 'Invalid status value.';
  }

  $data = [
    'name' => $name,
    'grade' => $grade !== '' ? $grade : null,
    'teacher_id' => $teacherIdVal,
    'room_no' => $roomNo !== '' ? $roomNo : null,
    'description' => $description !== '' ? $description : null,
    'status' => $status,
  ];

  return [$data, $errors];
}

try {
  switch ($action) {

    case 'create':
      [$data, $errors] = validateClassInput($_POST, $teacherModel);
      if ($errors) {
        cm_flash_set('danger', implode(' ', $errors));
        header('Location: ../classes.php');
        exit;
      }
      $classModel->create($data);
      cm_flash_set('success', 'Class created successfully.');
      header('Location: ../classes.php');
      exit;

    case 'update':
      $id = $_POST['id'] ?? '';
      if (!ctype_digit((string) $id) || !$classModel->getById((int) $id)) {
        cm_flash_set('danger', 'Invalid class selected.');
        header('Location: ../classes.php');
        exit;
      }
      [$data, $errors] = validateClassInput($_POST, $teacherModel);
      if ($errors) {
        cm_flash_set('danger', implode(' ', $errors));
        header('Location: ../classes.php');
        exit;
      }
      $classModel->update((int) $id, $data);
      cm_flash_set('success', 'Class updated successfully.');
      header('Location: ../classes.php');
      exit;

    case 'delete':
      $id = $_POST['class_id'] ?? '';
      if (!ctype_digit((string) $id) || !$classModel->getById((int) $id)) {
        cm_flash_set('danger', 'Invalid class selected.');
        header('Location: ../classes.php');
        exit;
      }
      // Delete safety: block if students assigned
      if ($classModel->countStudents((int) $id) > 0) {
        cm_flash_set('danger', 'Cannot delete this class because students are currently assigned to it.');
        header('Location: ../classes.php');
        exit;
      }
      $classModel->delete((int) $id);
      cm_flash_set('success', 'Class deleted successfully.');
      header('Location: ../classes.php');
      exit;

    default:
      cm_flash_set('danger', 'Unknown action.');
      header('Location: ../classes.php');
      exit;
  }
} catch (Throwable $e) {
  // Log $e->getMessage() to your log system in production.
  cm_flash_set('danger', 'A database error occurred. Please try again.');
  header('Location: ../classes.php');
  exit;
}