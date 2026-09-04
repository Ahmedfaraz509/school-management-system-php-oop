<?php
require_once '../database/connect.php';

class ParentsProcess
{
  private $conn;
  public $id;
  public $student_id;
  public $parent_uid;
  public $full_name;
  public $relation;          // will be stored as integer (1,2,3)
  public $phone;
  public $email;
  public $password;          // added to match the DB column
  public $address;
  public $status;
  public $created_at;
  public $updated_at;

  // Mapping for relation codes
  const RELATION_MAP = [
    1 => 'Father',
    2 => 'Mother',
    3 => 'Guardian'
  ];

  public function __construct(
    $conn,
    $id = null,
    $student_id = null,
    $parent_uid = null,
    $full_name = null,
    $relation = null,
    $phone = null,
    $email = null,
    $password = 0,          // default PIN/password, you can adjust
    $address = null,
    $status = 'Active',
    $created_at = null,
    $updated_at = null
  ) {
    $this->conn = $conn;
    $this->id = $id;
    $this->student_id = $student_id;
    $this->parent_uid = $parent_uid;
    $this->full_name = $full_name;
    $this->relation = (int) $relation; // ensure integer
    $this->phone = $phone;
    $this->email = $email;
    $this->password = $password;
    $this->address = $address;
    $this->status = $status;
    $this->created_at = $created_at;
    $this->updated_at = $updated_at;
  }

  // ------------------------------
  // INSERT
  // ------------------------------
  // NOTE: Login (auth/login.php, auth/loginprocess.php) authenticates against
  // the `users` table, not `parents`. Previously this method only inserted
  // into `parents`, so a parent account was never created in `users` and the
  // parent could never log in. We now create both rows in one transaction:
  // a `users` row (with a properly hashed password) for authentication, and
  // the `parents` row for the school-specific profile data.
  public function processParentsData()
  {
    // Hash the password before storing it anywhere. Previously the raw
    // integer PIN (e.g. 0) was stored directly, which password_verify()
    // would never match against.
    $hashedPassword = password_hash((string) $this->password, PASSWORD_DEFAULT);

    try {
      $this->conn->beginTransaction();

      // 1) Create the login account in `users` so auth/login.php can find it.
      $userSql = "INSERT INTO users
                      (name, email, password, role, status, created_at, updated_at)
                      VALUES
                      (:name, :email, :password, 'parent', 'active', :created_at, :updated_at)";
      $userStmt = $this->conn->prepare($userSql);
      $userStmt->execute([
        ':name' => $this->full_name,
        ':email' => $this->email,
        ':password' => $hashedPassword,
        ':created_at' => $this->created_at,
        ':updated_at' => $this->updated_at
      ]);

      $newUserId = $this->conn->lastInsertId();

      // 2) Create the parent profile row, linked back to the users row.
      $sql = "INSERT INTO parents
                  (user_id, student_id, parent_uid, full_name, relation, phone, email, password, address, status, created_at, updated_at)
                  VALUES
                  (:user_id, :student_id, :parent_uid, :full_name, :relation, :phone, :email, :password, :address, :status, :created_at, :updated_at)";

      $stmt = $this->conn->prepare($sql);
      $stmt->execute([
        ':user_id' => $newUserId,
        ':student_id' => $this->student_id,
        ':parent_uid' => $this->parent_uid,
        ':full_name' => $this->full_name,
        ':relation' => $this->relation,
        ':phone' => $this->phone,
        ':email' => $this->email,
        ':password' => $hashedPassword,
        ':address' => $this->address,
        ':status' => $this->status,
        ':created_at' => $this->created_at,
        ':updated_at' => $this->updated_at
      ]);

      $this->conn->commit();
      return true;
    } catch (PDOException $e) {
      $this->conn->rollBack();
      die("Insert failed: " . $e->getMessage());
    }
  }

  // ------------------------------
  // SELECT all parents with student name
  // ------------------------------
  public function joinParentsData()
  {
    $sql = "SELECT
                    parents.*,
                    CONCAT(students.first_name, ' ', students.last_name) AS student_name
                FROM parents
                INNER JOIN students ON parents.student_id = students.id
                ORDER BY parents.id DESC";

    try {
      $stmt = $this->conn->prepare($sql);
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      die("Select failed: " . $e->getMessage());
    }
  }

  // ------------------------------
  // SELECT a single parent by ID
  // ------------------------------
  public function getParentById($id)
  {
    $sql = "SELECT * FROM parents WHERE id = :id";
    try {
      $stmt = $this->conn->prepare($sql);
      $stmt->execute([':id' => $id]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      die("Select failed: " . $e->getMessage());
    }
  }

  // ------------------------------
  // UPDATE
  // ------------------------------
  // Keeps the linked `users` row (used for login) in sync with the
  // `parents` profile row, so an edited email/password/status still works
  // for authentication afterwards.
  public function updateParentsData()
  {
    $hashedPassword = password_hash((string) $this->password, PASSWORD_DEFAULT);
    $userStatus = (strtolower($this->status) === 'active') ? 'active' : 'inactive';

    try {
      $this->conn->beginTransaction();

      $sql = "UPDATE parents
                  SET
                      student_id = :student_id,
                      full_name = :full_name,
                      relation = :relation,
                      phone = :phone,
                      email = :email,
                      password = :password,
                      address = :address,
                      status = :status,
                      updated_at = :updated_at
                  WHERE id = :id";

      $stmt = $this->conn->prepare($sql);
      $stmt->execute([
        ':student_id' => $this->student_id,
        ':full_name' => $this->full_name,
        ':relation' => $this->relation,
        ':phone' => $this->phone,
        ':email' => $this->email,
        ':password' => $hashedPassword,
        ':address' => $this->address,
        ':status' => $this->status,
        ':updated_at' => $this->updated_at,
        ':id' => $this->id
      ]);

      // Sync the matching users row via user_id (fetched by id first).
      $userIdStmt = $this->conn->prepare("SELECT user_id FROM parents WHERE id = :id");
      $userIdStmt->execute([':id' => $this->id]);
      $linkedUserId = $userIdStmt->fetchColumn();

      if ($linkedUserId) {
        $userSql = "UPDATE users
                        SET name = :name, email = :email, password = :password,
                            status = :status, updated_at = :updated_at
                        WHERE id = :user_id";
        $userStmt = $this->conn->prepare($userSql);
        $userStmt->execute([
          ':name' => $this->full_name,
          ':email' => $this->email,
          ':password' => $hashedPassword,
          ':status' => $userStatus,
          ':updated_at' => $this->updated_at,
          ':user_id' => $linkedUserId
        ]);
      }

      $this->conn->commit();
      return true;
    } catch (PDOException $e) {
      $this->conn->rollBack();
      die("Update failed: " . $e->getMessage());
    }
  }

  // ------------------------------
  // DELETE
  // ------------------------------
  public function deleteParentsData()
  {
    $sql = "DELETE FROM parents WHERE id = :id";
    try {
      $stmt = $this->conn->prepare($sql);
      $stmt->execute([':id' => $this->id]);
      return true;
    } catch (PDOException $e) {
      die("Delete failed: " . $e->getMessage());
    }
  }

  // Helper to get relation text from code
  public static function getRelationText($code)
  {
    return self::RELATION_MAP[(int) $code] ?? 'Unknown';
  }
}
?>