<?php
require_once '../database/connect.php';

class FeesProcess
{
  private $conn;
  public $id;
  public $invoice_uid;
  public $student_id;
  public $total_fee;
  public $paid_amount;
  public $remaining;
  public $due_date;
  public $status;
  public $created_at;

  // Constructor with database connection
  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  // Set data method
  public function setData($id, $invoice_uid, $student_id, $total_fee, $paid_amount, $due_date, $status, $created_at)
  {
    $this->id = $id;
    $this->invoice_uid = $invoice_uid;
    $this->student_id = $student_id;
    $this->total_fee = $total_fee;
    $this->paid_amount = $paid_amount;
    $this->due_date = $due_date;
    $this->status = $status;
    $this->created_at = $created_at;
    // remaining is calculated automatically by database
  }

  // Generate invoice UID
  private function generateInvoiceUID()
  {
    return 'INV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
  }

  // Insert fees entry
  public function insert()
  {
    try {
      // Validate foreign keys
      if (!$this->validateForeignKeys()) {
        return false;
      }

      // Generate invoice UID if not provided
      if (empty($this->invoice_uid)) {
        $this->invoice_uid = $this->generateInvoiceUID();
      }

      // Set default status if not provided
      if (empty($this->status)) {
        $this->status = 'Unpaid';
      }

      // Set paid_amount to 0 if not provided
      if ($this->paid_amount === null || $this->paid_amount === '') {
        $this->paid_amount = 0;
      }

      $query = "INSERT INTO fee_invoices (invoice_uid, student_id, total_fee, paid_amount, due_date, status, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?)";

      $stmt = $this->conn->prepare($query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([
        $this->invoice_uid,
        $this->student_id,
        $this->total_fee,
        $this->paid_amount,
        $this->due_date,
        $this->status,
        $this->created_at
      ]);

      if (!$result) {
        error_log("Execute failed: " . print_r($stmt->errorInfo(), true));
        return false;
      }

      return $this->conn->lastInsertId();
    } catch (PDOException $e) {
      error_log("Insert fees error: " . $e->getMessage());
      return false;
    }
  }

  // Update fees entry
  public function update()
  {
    try {
      // Validate foreign keys
      if (!$this->validateForeignKeys()) {
        return false;
      }

      $query = "UPDATE fee_invoices SET 
                      student_id = ?, 
                      total_fee = ?, 
                      paid_amount = ?, 
                      due_date = ?, 
                      status = ? 
                      WHERE id = ?";

      $stmt = $this->conn->prepare($query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([
        $this->student_id,
        $this->total_fee,
        $this->paid_amount,
        $this->due_date,
        $this->status,
        $this->id
      ]);

      if (!$result) {
        error_log("Execute failed: " . print_r($stmt->errorInfo(), true));
        return false;
      }

      return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Update fees error: " . $e->getMessage());
      return false;
    }
  }

  // Delete fees entry
  public function delete($id)
  {
    try {
      // First delete associated payments
      $deletePayments = "DELETE FROM fee_payments WHERE invoice_id = ?";
      $stmtPayments = $this->conn->prepare($deletePayments);
      $stmtPayments->execute([$id]);

      // Then delete the invoice
      $query = "DELETE FROM fee_invoices WHERE id = ?";
      $stmt = $this->conn->prepare($query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([$id]);
      return $result && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Delete fees error: " . $e->getMessage());
      return false;
    }
  }

  // Validate foreign keys
  private function validateForeignKeys()
  {
    try {
      // Check student exists
      $checkStudent = $this->conn->prepare("SELECT id FROM students WHERE id = ? AND status = 'Active'");
      $checkStudent->execute([$this->student_id]);
      if (!$checkStudent->fetch()) {
        error_log("Student ID {$this->student_id} not found or inactive");
        return false;
      }
      return true;
    } catch (PDOException $e) {
      error_log("Validation error: " . $e->getMessage());
      return false;
    }
  }

  // Get all fees with joins
  public function getAllFees()
  {
    try {
      $query = "SELECT f.*, 
                      CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                      s.student_uid,
                      c.name as class_name,
                      c.grade as class_grade
                      FROM fee_invoices f
                      LEFT JOIN students s ON f.student_id = s.id
                      LEFT JOIN classes c ON s.class_id = c.id
                      ORDER BY f.created_at DESC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Fetch all fees error: " . $e->getMessage());
      return [];
    }
  }

  // Get fees by ID
  public function getFeesById($id)
  {
    try {
      $query = "SELECT f.*, 
                      CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                      s.student_uid
                      FROM fee_invoices f
                      LEFT JOIN students s ON f.student_id = s.id
                      WHERE f.id = ?";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$id]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Fetch fees by ID error: " . $e->getMessage());
      return null;
    }
  }

  // Get fees by student
  public function getFeesByStudent($student_id)
  {
    try {
      $query = "SELECT f.*, 
                      CONCAT(s.first_name, ' ', s.last_name) AS student_name
                      FROM fee_invoices f
                      LEFT JOIN students s ON f.student_id = s.id
                      WHERE f.student_id = ?
                      ORDER BY f.created_at DESC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$student_id]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Fetch fees by student error: " . $e->getMessage());
      return [];
    }
  }

  // Get fees by status
  public function getFeesByStatus($status)
  {
    try {
      $query = "SELECT f.*, 
                      CONCAT(s.first_name, ' ', s.last_name) AS student_name
                      FROM fee_invoices f
                      LEFT JOIN students s ON f.student_id = s.id
                      WHERE f.status = ?
                      ORDER BY f.due_date ASC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$status]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Fetch fees by status error: " . $e->getMessage());
      return [];
    }
  }

  // Count total fees
  public function countFees()
  {
    try {
      $query = "SELECT COUNT(*) as total FROM fee_invoices";
      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      return $result['total'] ?? 0;
    } catch (PDOException $e) {
      error_log("Count fees error: " . $e->getMessage());
      return 0;
    }
  }

  // Get fee statistics
  public function getFeeStats()
  {
    try {
      $query = "SELECT 
                      COUNT(*) as total_invoices,
                      SUM(total_fee) as total_fees,
                      SUM(paid_amount) as total_paid,
                      SUM(total_fee - paid_amount) as total_remaining,
                      SUM(CASE WHEN status = 'Paid' THEN total_fee ELSE 0 END) as total_collected,
                      SUM(CASE WHEN status = 'Unpaid' OR status = 'Partial' THEN total_fee - paid_amount ELSE 0 END) as total_pending,
                      SUM(CASE WHEN status = 'Overdue' THEN total_fee - paid_amount ELSE 0 END) as total_overdue,
                      COUNT(CASE WHEN status = 'Paid' THEN 1 END) as paid_count,
                      COUNT(CASE WHEN status = 'Unpaid' THEN 1 END) as unpaid_count,
                      COUNT(CASE WHEN status = 'Partial' THEN 1 END) as partial_count,
                      COUNT(CASE WHEN status = 'Overdue' THEN 1 END) as overdue_count
                      FROM fee_invoices";

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get fee stats error: " . $e->getMessage());
      return [
        'total_invoices' => 0,
        'total_fees' => 0,
        'total_paid' => 0,
        'total_remaining' => 0,
        'total_collected' => 0,
        'total_pending' => 0,
        'total_overdue' => 0,
        'paid_count' => 0,
        'unpaid_count' => 0,
        'partial_count' => 0,
        'overdue_count' => 0
      ];
    }
  }

  // Record payment
  public function recordPayment($invoice_id, $amount, $payment_method, $payment_reference)
  {
    try {
      $this->conn->beginTransaction();

      // Insert payment record
      $query = "INSERT INTO fee_payments (invoice_id, amount_paid, payment_method, payment_reference, payment_date) 
                      VALUES (?, ?, ?, ?, NOW())";

      $stmt = $this->conn->prepare($query);
      $result = $stmt->execute([$invoice_id, $amount, $payment_method, $payment_reference]);

      if (!$result) {
        $this->conn->rollBack();
        return false;
      }

      // Update invoice paid_amount
      $updateQuery = "UPDATE fee_invoices SET 
                            paid_amount = paid_amount + ?,
                            status = CASE 
                                WHEN (paid_amount + ?) >= total_fee THEN 'Paid'
                                WHEN (paid_amount + ?) > 0 THEN 'Partial'
                                ELSE 'Unpaid'
                            END
                            WHERE id = ?";

      $updateStmt = $this->conn->prepare($updateQuery);
      $updateResult = $updateStmt->execute([$amount, $amount, $amount, $invoice_id]);

      if (!$updateResult) {
        $this->conn->rollBack();
        return false;
      }

      $this->conn->commit();
      return true;
    } catch (PDOException $e) {
      $this->conn->rollBack();
      error_log("Record payment error: " . $e->getMessage());
      return false;
    }
  }

  // Get payments by invoice
  public function getPaymentsByInvoice($invoice_id)
  {
    try {
      $query = "SELECT * FROM fee_payments WHERE invoice_id = ? ORDER BY payment_date DESC";
      $stmt = $this->conn->prepare($query);
      $stmt->execute([$invoice_id]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get payments by invoice error: " . $e->getMessage());
      return [];
    }
  }

  // Search fees
  public function searchFees($keyword)
  {
    try {
      $search_query = "SELECT f.*, 
                            CONCAT(s.first_name, ' ', s.last_name) AS student_name,
                            s.student_uid
                            FROM fee_invoices f
                            LEFT JOIN students s ON f.student_id = s.id
                            WHERE f.invoice_uid LIKE ? 
                            OR CONCAT(s.first_name, ' ', s.last_name) LIKE ?
                            OR s.student_uid LIKE ?
                            ORDER BY f.created_at DESC";

      $search_term = "%{$keyword}%";
      $stmt = $this->conn->prepare($search_query);
      $stmt->execute([$search_term, $search_term, $search_term]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Search fees error: " . $e->getMessage());
      return [];
    }
  }

  // Update fee status (check for overdue)
  public function updateOverdueStatus()
  {
    try {
      $query = "UPDATE fee_invoices 
                      SET status = 'Overdue' 
                      WHERE due_date < CURDATE() 
                      AND status NOT IN ('Paid', 'Overdue')";

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->rowCount();
    } catch (PDOException $e) {
      error_log("Update overdue status error: " . $e->getMessage());
      return 0;
    }
  }
}
?>