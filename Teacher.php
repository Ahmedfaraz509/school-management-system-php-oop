<?php
// models/Teacher.php
declare(strict_types=1);

class Teacher
{
  private PDO $db;

  public function __construct(PDO $pdo)
  {
    $this->db = $pdo;
  }

  public function getActive(): array
  {
    $sql = "SELECT id, full_name FROM teachers WHERE status = 'active' ORDER BY full_name ASC";
    return $this->db->query($sql)->fetchAll();
  }

  public function exists(int $id): bool
  {
    $stmt = $this->db->prepare("SELECT 1 FROM teachers WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    return (bool) $stmt->fetchColumn();
  }
}