<?php
// ajax/class_students.php
declare(strict_types=1);

require_once __DIR__ . '/../config/classes_bootstrap.php';
require_once __DIR__ . '/../models/ClassModel.php';

header('Content-Type: application/json');

if (!cm_is_authenticated()) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
  exit;
}

$id = $_GET['class_id'] ?? '';
if (!ctype_digit((string) $id)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'error' => 'Invalid class id']);
  exit;
}

try {
  $model = new ClassModel($pdo);
  $class = $model->getById((int) $id);
  if (!$class) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Class not found']);
    exit;
  }

  $students = $model->getStudents((int) $id);

  echo json_encode([
    'ok' => true,
    'class_name' => $class['name'],
    'students' => array_map(static function ($s) {
      return [
        'uid' => $s['student_uid'],
        'name' => trim($s['first_name'] . ' ' . $s['last_name']),
        'status' => $s['status'],
      ];
    }, $students),
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Server error']);
}