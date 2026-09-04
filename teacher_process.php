<?php

require_once __DIR__ . '/../database/connect.php';

class Teacher
{
  private PDO $conn;

  public function __construct(
    PDO $conn,
    string $fullName,
    string $email,
    string $password,
    ?int $userId = null,
    ?string $teacherId = null,
    ?string $phone = null,
    ?string $gender = null,
    ?string $dateOfBirth = null,
    ?string $qualification = null,
    ?string $joiningDate = null,
    ?string $address = null,
    ?string $bio = null,
    ?string $photo = null,
    string $status = 'active'
  ) {
    $this->conn = $conn;

    $this->fullName = $fullName;
    $this->email = $email;
    $this->password = $password;
    $this->userId = $userId;
    $this->teacherId = $teacherId;
    $this->phone = $phone;
    $this->gender = $gender;
    $this->dateOfBirth = $dateOfBirth;
    $this->qualification = $qualification;
    $this->joiningDate = $joiningDate;
    $this->address = $address;
    $this->bio = $bio;
    $this->photo = $photo;
    $this->status = strtolower($status);
  }

  private string $fullName;
  private string $email;
  private string $password;
  private ?int $userId;
  private ?string $teacherId;
  private ?string $phone;
  private ?string $gender;
  private ?string $dateOfBirth;
  private ?string $qualification;
  private ?string $joiningDate;
  private ?string $address;
  private ?string $bio;
  private ?string $photo;
  private string $status;

  /**
   * Insert teacher into teachers table
   */
  public function insert(): bool
  {
    if ($this->userId === null) {
      throw new Exception('User ID is required.');
    }

    if (empty($this->teacherId)) {
      throw new Exception('Teacher ID is required.');
    }

    $query = "
            INSERT INTO teachers
            (
                user_id,
                teacher_id,
                full_name,
                phone,
                email,
                password,
                gender,
                date_of_birth,
                qualification,
                joining_date,
                address,
                bio,
                photo,
                status
            )
            VALUES
            (
                :user_id,
                :teacher_id,
                :full_name,
                :phone,
                :email,
                :password,
                :gender,
                :date_of_birth,
                :qualification,
                :joining_date,
                :address,
                :bio,
                :photo,
                :status
            )
        ";

    $stmt = $this->conn->prepare($query);

    return $stmt->execute([
      ':user_id' => $this->userId,
      ':teacher_id' => $this->teacherId,
      ':full_name' => $this->fullName,
      ':phone' => $this->phone,
      ':email' => $this->email,
      ':password' => $this->password,
      ':gender' => $this->gender,
      ':date_of_birth' => $this->dateOfBirth,
      ':qualification' => $this->qualification,
      ':joining_date' => $this->joiningDate,
      ':address' => $this->address,
      ':bio' => $this->bio,
      ':photo' => $this->photo,
      ':status' => $this->status
    ]);
  }

  /**
   * Get all teachers
   */
  public function getAll(): array
  {
    $query = "
            SELECT *
            FROM teachers
            ORDER BY id DESC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Get teacher by ID
   */
  public function getById(int $id): ?array
  {
    $query = "
            SELECT *
            FROM teachers
            WHERE id = :id
            LIMIT 1
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([
      ':id' => $id
    ]);

    $teacher = $stmt->fetch(PDO::FETCH_ASSOC);

    return $teacher ?: null;
  }

  /**
   * Update teacher
   */
  public function update(int $id): bool
  {
    $query = "
            UPDATE teachers
            SET
                teacher_id = :teacher_id,
                full_name = :full_name,
                phone = :phone,
                email = :email,
                gender = :gender,
                date_of_birth = :date_of_birth,
                qualification = :qualification,
                joining_date = :joining_date,
                address = :address,
                bio = :bio,
                photo = :photo,
                status = :status
            WHERE id = :id
        ";

    $stmt = $this->conn->prepare($query);

    return $stmt->execute([
      ':teacher_id' => $this->teacherId,
      ':full_name' => $this->fullName,
      ':phone' => $this->phone,
      ':email' => $this->email,
      ':gender' => $this->gender,
      ':date_of_birth' => $this->dateOfBirth,
      ':qualification' => $this->qualification,
      ':joining_date' => $this->joiningDate,
      ':address' => $this->address,
      ':bio' => $this->bio,
      ':photo' => $this->photo,
      ':status' => $this->status,
      ':id' => $id
    ]);
  }

  /**
   * Delete teacher
   */
  public function delete(int $id): bool
  {
    $query = "
            DELETE FROM teachers
            WHERE id = :id
        ";

    $stmt = $this->conn->prepare($query);

    return $stmt->execute([
      ':id' => $id
    ]);
  }
}