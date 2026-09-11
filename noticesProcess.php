<?php
require_once '../database/connect.php';

class NoticesProcess
{
  private $conn;
  public $id;
  public $title;
  public $category;
  public $posted_by;
  public $details;
  public $created_at;

  // Constructor with database connection
  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  // Set notice data
  public function setData($id, $title, $category, $posted_by, $details, $created_at)
  {
    $this->id = $id;
    $this->title = $title;
    $this->category = $category;
    $this->posted_by = $posted_by;
    $this->details = $details;
    $this->created_at = $created_at;
  }

  // Insert notice
  public function insert()
  {
    try {
      $insert_query = "INSERT INTO notices (title, category, posted_by, details, created_at) 
                            VALUES (?, ?, ?, ?, ?)";

      $stmt = $this->conn->prepare($insert_query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([
        $this->title,
        $this->category,
        $this->posted_by,
        $this->details,
        $this->created_at
      ]);

      if (!$result) {
        error_log("Execute failed: " . print_r($stmt->errorInfo(), true));
        return false;
      }

      return $this->conn->lastInsertId();
    } catch (PDOException $e) {
      error_log("Insert notice error: " . $e->getMessage());
      return false;
    }
  }

  // Update notice
  public function update()
  {
    try {
      $update_query = "UPDATE notices SET 
                            title = ?, 
                            category = ?, 
                            posted_by = ?, 
                            details = ? 
                            WHERE id = ?";

      $stmt = $this->conn->prepare($update_query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([
        $this->title,
        $this->category,
        $this->posted_by,
        $this->details,
        $this->id
      ]);

      if (!$result) {
        error_log("Execute failed: " . print_r($stmt->errorInfo(), true));
        return false;
      }

      return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Update notice error: " . $e->getMessage());
      return false;
    }
  }

  // Delete notice
  public function delete($id)
  {
    try {
      $delete_query = "DELETE FROM notices WHERE id = ?";
      $stmt = $this->conn->prepare($delete_query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([$id]);
      return $result && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Delete notice error: " . $e->getMessage());
      return false;
    }
  }

  // Get all notices
  public function getAllNotices()
  {
    try {
      $query = "SELECT * FROM notices ORDER BY created_at DESC";
      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Fetch all notices error: " . $e->getMessage());
      return [];
    }
  }

  // Get notice by ID
  public function getNoticeById($id)
  {
    try {
      $query = "SELECT * FROM notices WHERE id = ?";
      $stmt = $this->conn->prepare($query);
      $stmt->execute([$id]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Fetch notice by ID error: " . $e->getMessage());
      return null;
    }
  }

  // Get notices by category
  public function getNoticesByCategory($category)
  {
    try {
      $query = "SELECT * FROM notices WHERE category = ? ORDER BY created_at DESC";
      $stmt = $this->conn->prepare($query);
      $stmt->execute([$category]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Fetch notices by category error: " . $e->getMessage());
      return [];
    }
  }

  // Count total notices
  public function countNotices()
  {
    try {
      $query = "SELECT COUNT(*) as total FROM notices";
      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      return $result['total'] ?? 0;
    } catch (PDOException $e) {
      error_log("Count notices error: " . $e->getMessage());
      return 0;
    }
  }

  // Get notice statistics by category
  public function getNoticeStats()
  {
    try {
      $query = "SELECT 
                      COUNT(*) as total,
                      SUM(CASE WHEN category = 'Academic' THEN 1 ELSE 0 END) as academic,
                      SUM(CASE WHEN category = 'Administrative' THEN 1 ELSE 0 END) as administrative,
                      SUM(CASE WHEN category = 'Sports' THEN 1 ELSE 0 END) as sports,
                      SUM(CASE WHEN category = 'Holiday' THEN 1 ELSE 0 END) as holiday,
                      SUM(CASE WHEN category = 'Event' THEN 1 ELSE 0 END) as event
                      FROM notices";

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get notice stats error: " . $e->getMessage());
      return ['total' => 0, 'academic' => 0, 'administrative' => 0, 'sports' => 0, 'holiday' => 0, 'event' => 0];
    }
  }

  // Search notices
  public function searchNotices($keyword)
  {
    try {
      $search_query = "SELECT * FROM notices 
                            WHERE title LIKE ? 
                            OR details LIKE ?
                            OR posted_by LIKE ?
                            ORDER BY created_at DESC";

      $search_term = "%{$keyword}%";
      $stmt = $this->conn->prepare($search_query);
      $stmt->execute([$search_term, $search_term, $search_term]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Search notices error: " . $e->getMessage());
      return [];
    }
  }

  // Get recent notices (limit)
  public function getRecentNotices($limit = 5)
  {
    try {
      $query = "SELECT * FROM notices ORDER BY created_at DESC LIMIT ?";
      $stmt = $this->conn->prepare($query);
      $stmt->execute([$limit]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get recent notices error: " . $e->getMessage());
      return [];
    }
  }
}
?>