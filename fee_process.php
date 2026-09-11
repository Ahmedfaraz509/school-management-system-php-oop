<?php
// fee_process.php
// Process file for handling fee-related database operations

require_once '../database/connect.php';

class FeeProcess
{
  private $conn;
  private $student_id;
  private $student_data;

  public function __construct($conn, $student_id = null)
  {
    $this->conn = $conn;
    $this->student_id = $student_id;
    if ($student_id) {
      $this->loadStudentData();
    }
  }

  /**
   * Load student data including class information
   */
  public function loadStudentData()
  {
    $query = "SELECT s.*, c.name as class_name, c.grade, c.id as class_id 
                  FROM students s 
                  LEFT JOIN classes c ON s.class_id = c.id 
                  WHERE s.id = ?";
    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_id]);
    $this->student_data = $stmt->fetch();
    return $this->student_data;
  }

  /**
   * Get student data
   */
  public function getStudentData()
  {
    return $this->student_data;
  }

  /**
   * Get all fee invoices for a student
   */
  public function getFeeInvoices()
  {
    if (!$this->student_data) {
      return [];
    }

    $query = "SELECT fi.*, 
                  CASE 
                      WHEN fi.status = 'Paid' THEN 'p-ok'
                      WHEN fi.status = 'Partial' THEN 'p-warn'
                      WHEN fi.status = 'Overdue' THEN 'p-danger'
                      ELSE 'p-grey'
                  END as status_color,
                  CASE 
                      WHEN fi.status = 'Paid' THEN 'Paid'
                      WHEN fi.status = 'Partial' THEN 'Partial'
                      WHEN fi.status = 'Overdue' THEN 'Overdue'
                      ELSE 'Pending'
                  END as display_status
                  FROM fee_invoices fi
                  WHERE fi.student_id = ?
                  ORDER BY fi.due_date DESC";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_id]);
    return $stmt->fetchAll();
  }

  /**
   * Get fee invoice by ID
   */
  public function getFeeInvoiceById($invoice_id)
  {
    $query = "SELECT fi.*, 
                  CONCAT(s.first_name, ' ', s.last_name) as student_name,
                  s.student_uid, c.name as class_name
                  FROM fee_invoices fi
                  JOIN students s ON fi.student_id = s.id
                  LEFT JOIN classes c ON s.class_id = c.id
                  WHERE fi.id = ?";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$invoice_id]);
    return $stmt->fetch();
  }

  /**
   * Get fee summary statistics
   */
  public function getFeeSummary()
  {
    if (!$this->student_data) {
      return [
        'total_fee' => 0,
        'paid_amount' => 0,
        'remaining_amount' => 0,
        'total_invoices' => 0,
        'paid_invoices' => 0,
        'pending_invoices' => 0,
        'overdue_invoices' => 0,
        'partial_invoices' => 0
      ];
    }

    $query = "SELECT 
                    SUM(total_fee) as total_fee,
                    SUM(paid_amount) as paid_amount,
                    SUM(total_fee - paid_amount) as remaining_amount,
                    COUNT(*) as total_invoices,
                    SUM(CASE WHEN status = 'Paid' THEN 1 ELSE 0 END) as paid_count,
                    SUM(CASE WHEN status = 'Unpaid' THEN 1 ELSE 0 END) as pending_count,
                    SUM(CASE WHEN status = 'Overdue' THEN 1 ELSE 0 END) as overdue_count,
                    SUM(CASE WHEN status = 'Partial' THEN 1 ELSE 0 END) as partial_count
                    FROM fee_invoices 
                    WHERE student_id = ?";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_id]);
    $result = $stmt->fetch();

    return [
      'total_fee' => $result['total_fee'] ?? 0,
      'paid_amount' => $result['paid_amount'] ?? 0,
      'remaining_amount' => $result['remaining_amount'] ?? 0,
      'total_invoices' => $result['total_invoices'] ?? 0,
      'paid_invoices' => $result['paid_count'] ?? 0,
      'pending_invoices' => $result['pending_count'] ?? 0,
      'overdue_invoices' => $result['overdue_count'] ?? 0,
      'partial_invoices' => $result['partial_count'] ?? 0
    ];
  }

  /**
   * Get fee payments history
   */
  public function getPaymentHistory($limit = 10)
  {
    if (!$this->student_data) {
      return [];
    }

    $query = "SELECT fp.*, fi.invoice_uid, fi.total_fee
                  FROM fee_payments fp
                  JOIN fee_invoices fi ON fp.invoice_id = fi.id
                  WHERE fi.student_id = ?
                  ORDER BY fp.payment_date DESC
                  LIMIT " . intval($limit);

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_id]);
    return $stmt->fetchAll();
  }

  /**
   * Get overdue invoices
   */
  public function getOverdueInvoices()
  {
    if (!$this->student_data) {
      return [];
    }

    $query = "SELECT fi.*, 
                  DATEDIFF(CURDATE(), fi.due_date) as days_overdue
                  FROM fee_invoices fi
                  WHERE fi.student_id = ? 
                  AND fi.status = 'Overdue'
                  ORDER BY fi.due_date ASC";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_id]);
    return $stmt->fetchAll();
  }

  /**
   * Get pending invoices
   */
  public function getPendingInvoices()
  {
    if (!$this->student_data) {
      return [];
    }

    $query = "SELECT fi.*, 
                  DATEDIFF(fi.due_date, CURDATE()) as days_until_due
                  FROM fee_invoices fi
                  WHERE fi.student_id = ? 
                  AND fi.status IN ('Unpaid', 'Partial')
                  AND fi.due_date >= CURDATE()
                  ORDER BY fi.due_date ASC";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_id]);
    return $stmt->fetchAll();
  }

  /**
   * Add a new fee invoice
   */
  public function addInvoice($data)
  {
    $query = "INSERT INTO fee_invoices (invoice_uid, student_id, total_fee, paid_amount, due_date, status) 
                  VALUES (?, ?, ?, ?, ?, ?)";

    $stmt = $this->conn->prepare($query);
    return $stmt->execute([
      $data['invoice_uid'],
      $data['student_id'],
      $data['total_fee'],
      $data['paid_amount'] ?? 0,
      $data['due_date'],
      $data['status'] ?? 'Unpaid'
    ]);
  }

  /**
   * Record a fee payment
   */
  public function recordPayment($data)
  {
    // Start transaction
    $this->conn->beginTransaction();

    try {
      // Insert payment record
      $query = "INSERT INTO fee_payments (invoice_id, amount_paid, payment_method, payment_reference, payment_date) 
                      VALUES (?, ?, ?, ?, NOW())";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([
        $data['invoice_id'],
        $data['amount_paid'],
        $data['payment_method'],
        $data['payment_reference'] ?? null
      ]);

      // Update invoice status
      $updateQuery = "UPDATE fee_invoices 
                            SET paid_amount = paid_amount + ?, 
                                status = CASE 
                                    WHEN paid_amount + ? >= total_fee THEN 'Paid'
                                    WHEN paid_amount + ? > 0 THEN 'Partial'
                                    ELSE 'Unpaid'
                                END
                            WHERE id = ?";

      $stmt = $this->conn->prepare($updateQuery);
      $stmt->execute([
        $data['amount_paid'],
        $data['amount_paid'],
        $data['amount_paid'],
        $data['invoice_id']
      ]);

      $this->conn->commit();
      return true;

    } catch (Exception $e) {
      $this->conn->rollBack();
      return false;
    }
  }

  /**
   * Generate invoice UID
   */
  public static function generateInvoiceUID()
  {
    return 'INV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
  }

  /**
   * Get fee notices
   */
  public function getFeeNotices($limit = 3)
  {
    $query = "SELECT * FROM notices 
                  WHERE category IN ('Administrative') 
                  ORDER BY created_at DESC 
                  LIMIT " . intval($limit);

    $stmt = $this->conn->prepare($query);
    $stmt->execute();
    return $stmt->fetchAll();
  }

  /**
   * Get student messages for fees
   */
  public function getFeeMessages($limit = 3)
  {
    if (!$this->student_data) {
      return [];
    }

    $query = "SELECT m.*, 
                  CASE 
                      WHEN m.sender_type = 'Admin' THEN 'Accounts Office'
                      ELSE m.sender_type
                  END as sender_name
                  FROM messages m
                  WHERE (m.recipient_type = 'Student' AND m.recipient_id = ?)
                  AND m.subject LIKE '%fee%'
                  ORDER BY m.created_at DESC
                  LIMIT " . intval($limit);

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_id]);
    return $stmt->fetchAll();
  }

  /**
   * Get fee structure for class
   */
  public function getFeeStructure()
  {
    if (!$this->student_data) {
      return [];
    }

    // This would typically come from a fee_structure table
    // For now, return default structure
    return [
      'tuition_monthly' => 8500,
      'lab_charges' => 'Included',
      'transport' => 'Not opted',
      'library_sports' => 'Included',
      'admission_fee' => 9000,
      'annual_charges' => 0
    ];
  }

  /**
   * Get concessions/discounts
   */
  public function getConcessions()
  {
    if (!$this->student_data) {
      return [];
    }

    return [
      'scholarship' => [
        'name' => 'Merit scholarship',
        'percentage' => 10,
        'amount' => 6000,
        'description' => 'Applied on tuition based on previous year results'
      ],
      'sibling_discount' => [
        'applicable' => false,
        'percentage' => 0
      ]
    ];
  }

  /**
   * Update invoice status
   */
  public function updateInvoiceStatus($invoice_id)
  {
    $query = "UPDATE fee_invoices 
                  SET status = CASE 
                      WHEN paid_amount >= total_fee THEN 'Paid'
                      WHEN paid_amount > 0 THEN 'Partial'
                      WHEN due_date < CURDATE() AND paid_amount < total_fee THEN 'Overdue'
                      ELSE 'Unpaid'
                  END
                  WHERE id = ?";

    $stmt = $this->conn->prepare($query);
    return $stmt->execute([$invoice_id]);
  }

  /**
   * Get payment method badge color
   */
  public static function getPaymentMethodColor($method)
  {
    $colors = [
      'Bank Transfer' => 'p-info',
      'Credit Card' => 'p-violet',
      'Cash' => 'p-teal',
      'Cheque' => 'p-warn',
      'Pay Order' => 'p-ok'
    ];
    return $colors[$method] ?? 'p-grey';
  }

  /**
   * Format currency
   */
  public static function formatCurrency($amount)
  {
    return 'Rs. ' . number_format($amount, 0);
  }

  /**
   * Get status badge color
   */
  public static function getStatusColor($status)
  {
    $colors = [
      'Paid' => 'p-ok',
      'Partial' => 'p-warn',
      'Unpaid' => 'p-grey',
      'Overdue' => 'p-danger',
      'Pending' => 'p-warn'
    ];
    return $colors[$status] ?? 'p-grey';
  }

  /**
   * Calculate late fee
   */
  public static function calculateLateFee($due_date, $amount, $rate_per_day = 0.01)
  {
    $due = new DateTime($due_date);
    $now = new DateTime();
    if ($now <= $due) {
      return 0;
    }
    $diff = $now->diff($due);
    $days = $diff->days;
    return round($amount * $rate_per_day * $days, 0);
  }

  /**
   * Get all invoices with payment status
   */
  public function getAllInvoicesWithStatus()
  {
    $invoices = $this->getFeeInvoices();
    $summary = $this->getFeeSummary();

    return [
      'invoices' => $invoices,
      'summary' => $summary
    ];
  }

  /**
   * Delete an invoice
   */
  public function deleteInvoice($invoice_id)
  {
    // Check if there are payments associated
    $checkQuery = "SELECT COUNT(*) as count FROM fee_payments WHERE invoice_id = ?";
    $stmt = $this->conn->prepare($checkQuery);
    $stmt->execute([$invoice_id]);
    $result = $stmt->fetch();

    if ($result['count'] > 0) {
      return false; // Cannot delete invoice with payments
    }

    $query = "DELETE FROM fee_invoices WHERE id = ?";
    $stmt = $this->conn->prepare($query);
    return $stmt->execute([$invoice_id]);
  }

  /**
   * Get invoice by UID
   */
  public function getInvoiceByUID($invoice_uid)
  {
    $query = "SELECT fi.*, 
                  CONCAT(s.first_name, ' ', s.last_name) as student_name,
                  s.student_uid, c.name as class_name
                  FROM fee_invoices fi
                  JOIN students s ON fi.student_id = s.id
                  LEFT JOIN classes c ON s.class_id = c.id
                  WHERE fi.invoice_uid = ?";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$invoice_uid]);
    return $stmt->fetch();
  }

  /**
   * Get monthly fee summary for charts
   */
  public function getMonthlySummary()
  {
    if (!$this->student_data) {
      return [];
    }

    $query = "SELECT 
                    MONTH(due_date) as month,
                    YEAR(due_date) as year,
                    SUM(total_fee) as total,
                    SUM(paid_amount) as paid,
                    SUM(total_fee - paid_amount) as remaining
                    FROM fee_invoices 
                    WHERE student_id = ?
                    GROUP BY YEAR(due_date), MONTH(due_date)
                    ORDER BY year DESC, month DESC
                    LIMIT 6";

    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_id]);
    return $stmt->fetchAll();
  }
}

// Helper functions for use in templates
function formatCurrency($amount)
{
  return FeeProcess::formatCurrency($amount);
}

function getStatusColor($status)
{
  return FeeProcess::getStatusColor($status);
}

function getPaymentMethodColor($method)
{
  return FeeProcess::getPaymentMethodColor($method);
}

// Initialize the process for the current student
// Usage: $feeProcess = new FeeProcess($conn, $student_id);
?>