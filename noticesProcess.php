<?php
// NoticesProcess.php
require_once '../database/connect.php';

class NoticesProcess
{
  private $conn;

  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  /**
   * Get all notices with filters
   */
  public function getNotices($filters = [])
  {
    $query = "SELECT * FROM notices WHERE 1=1";
    $params = [];

    // Search filter
    if (!empty($filters['search'])) {
      $query .= " AND (title LIKE :search OR details LIKE :search OR posted_by LIKE :search)";
      $params[':search'] = '%' . $filters['search'] . '%';
    }

    // Category filter
    if (!empty($filters['category'])) {
      $query .= " AND category = :category";
      $params[':category'] = $filters['category'];
    }

    // Date range filter
    if (!empty($filters['date_from'])) {
      $query .= " AND created_at >= :date_from";
      $params[':date_from'] = $filters['date_from'];
    }

    if (!empty($filters['date_to'])) {
      $query .= " AND created_at <= :date_to";
      $params[':date_to'] = $filters['date_to'];
    }

    // Posted by filter
    if (!empty($filters['posted_by'])) {
      $query .= " AND posted_by = :posted_by";
      $params[':posted_by'] = $filters['posted_by'];
    }

    $query .= " ORDER BY created_at DESC";

    $stmt = $this->conn->prepare($query);
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get notice by ID
   */
  public function getNoticeById($notice_id)
  {
    $query = "SELECT * FROM notices WHERE id = :id";
    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':id', $notice_id);
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Create a new notice
   */
  public function createNotice($data)
  {
    $query = "
            INSERT INTO notices (title, category, posted_by, details)
            VALUES (:title, :category, :posted_by, :details)
        ";

    try {
      $stmt = $this->conn->prepare($query);
      $stmt->bindValue(':title', $data['title']);
      $stmt->bindValue(':category', $data['category']);
      $stmt->bindValue(':posted_by', $data['posted_by']);
      $stmt->bindValue(':details', $data['details']);

      if ($stmt->execute()) {
        return [
          'success' => true,
          'id' => $this->conn->lastInsertId(),
          'message' => 'Notice created successfully'
        ];
      } else {
        return ['success' => false, 'message' => 'Failed to create notice'];
      }
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Update a notice
   */
  public function updateNotice($notice_id, $data)
  {
    $updates = [];
    $params = [':id' => $notice_id];

    $allowed_fields = ['title', 'category', 'details', 'posted_by'];
    foreach ($allowed_fields as $field) {
      if (isset($data[$field])) {
        $updates[] = "$field = :$field";
        $params[":$field"] = $data[$field];
      }
    }

    if (empty($updates)) {
      return ['success' => false, 'message' => 'No fields to update'];
    }

    $query = "UPDATE notices SET " . implode(', ', $updates) . " WHERE id = :id";

    try {
      $stmt = $this->conn->prepare($query);
      foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
      }

      if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Notice updated successfully'];
      } else {
        return ['success' => false, 'message' => 'Failed to update notice'];
      }
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Delete a notice
   */
  public function deleteNotice($notice_id)
  {
    $query = "DELETE FROM notices WHERE id = :id";

    try {
      $stmt = $this->conn->prepare($query);
      $stmt->bindValue(':id', $notice_id);

      if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Notice deleted successfully'];
      } else {
        return ['success' => false, 'message' => 'Failed to delete notice'];
      }
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Get notice statistics
   */
  public function getNoticeStats()
  {
    $query = "
            SELECT 
                COUNT(*) as total_notices,
                SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as active_notices,
                COUNT(DISTINCT category) as total_categories,
                COUNT(DISTINCT posted_by) as total_authors
            FROM notices
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->execute();

    return $stmt->fetch();
  }

  /**
   * Get notices by category
   */
  public function getNoticesByCategory($category)
  {
    $query = "
            SELECT * FROM notices 
            WHERE category = :category 
            ORDER BY created_at DESC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':category', $category);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get recent notices
   */
  public function getRecentNotices($limit = 5)
  {
    $query = "
            SELECT * FROM notices 
            ORDER BY created_at DESC 
            LIMIT :limit
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get all categories
   */
  public function getAllCategories()
  {
    $query = "SELECT DISTINCT category FROM notices ORDER BY category";
    $stmt = $this->conn->prepare($query);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Search notices
   */
  public function searchNotices($keyword)
  {
    $query = "
            SELECT * FROM notices 
            WHERE title LIKE :keyword 
               OR details LIKE :keyword 
               OR posted_by LIKE :keyword 
               OR category LIKE :keyword
            ORDER BY created_at DESC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':keyword', '%' . $keyword . '%');
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get notices by date range
   */
  public function getNoticesByDateRange($start_date, $end_date)
  {
    $query = "
            SELECT * FROM notices 
            WHERE created_at BETWEEN :start_date AND :end_date
            ORDER BY created_at DESC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':start_date', $start_date);
    $stmt->bindValue(':end_date', $end_date);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get notices posted by a specific person
   */
  public function getNoticesByPoster($posted_by)
  {
    $query = "
            SELECT * FROM notices 
            WHERE posted_by = :posted_by 
            ORDER BY created_at DESC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':posted_by', $posted_by);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get notice count by category
   */
  public function getNoticeCountByCategory()
  {
    $query = "
            SELECT 
                category,
                COUNT(*) as count
            FROM notices
            GROUP BY category
            ORDER BY count DESC
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->execute();

    return $stmt->fetchAll();
  }
}

// Usage example:
/*
$noticeProcess = new NoticesProcess($conn);

// Get all notices
$notices = $noticeProcess->getNotices();

// Get notices with filters
$filtered = $noticeProcess->getNotices([
    'search' => 'meeting',
    'category' => 'Staff'
]);

// Create a new notice
$result = $noticeProcess->createNotice([
    'title' => 'Staff Meeting',
    'category' => 'Staff',
    'posted_by' => 'Principal Office',
    'details' => 'Monthly staff meeting...'
]);

// Get recent notices
$recent = $noticeProcess->getRecentNotices(5);

// Get notice stats
$stats = $noticeProcess->getNoticeStats();

// Get categories
$categories = $noticeProcess->getAllCategories();
*/
?>