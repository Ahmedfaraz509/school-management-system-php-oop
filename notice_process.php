<?php
// notice_process.php
// Process file for handling notice-related database operations

require_once '../database/connect.php';

class NoticeProcess
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
   * Get all notices - FIXED with proper data handling
   */
  public function getAllNotices($limit = null)
  {
    try {
      $query = "SELECT n.*,
                      CASE 
                          WHEN n.category = 'Academic' THEN 'p-teal'
                          WHEN n.category = 'Administrative' THEN 'p-violet'
                          WHEN n.category = 'Sports' THEN 'p-ok'
                          WHEN n.category = 'Holiday' THEN 'p-warn'
                          WHEN n.category = 'Event' THEN 'p-info'
                          ELSE 'p-grey'
                      END as category_color,
                      CASE 
                          WHEN n.category = 'Academic' THEN 'bi-mortarboard'
                          WHEN n.category = 'Administrative' THEN 'bi-gear'
                          WHEN n.category = 'Sports' THEN 'bi-trophy'
                          WHEN n.category = 'Holiday' THEN 'bi-calendar-check'
                          WHEN n.category = 'Event' THEN 'bi-calendar2-heart'
                          ELSE 'bi-info-circle'
                      END as category_icon,
                      DATE_FORMAT(n.created_at, '%d %b %Y') as formatted_date,
                      DATE_FORMAT(n.created_at, '%h:%i %p') as formatted_time,
                      CASE 
                          WHEN DATEDIFF(CURDATE(), n.created_at) = 0 THEN 'Today'
                          WHEN DATEDIFF(CURDATE(), n.created_at) = 1 THEN 'Yesterday'
                          WHEN DATEDIFF(CURDATE(), n.created_at) < 7 THEN CONCAT(DATEDIFF(CURDATE(), n.created_at), ' days ago')
                          WHEN DATEDIFF(CURDATE(), n.created_at) < 30 THEN CONCAT(FLOOR(DATEDIFF(CURDATE(), n.created_at) / 7), ' weeks ago')
                          ELSE DATE_FORMAT(n.created_at, '%b %d, %Y')
                      END as time_ago
                      FROM notices n
                      ORDER BY n.created_at DESC";

      if ($limit) {
        $query .= " LIMIT " . intval($limit);
      }

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      $result = $stmt->fetchAll();

      // If no notices, return empty array
      return $result ? $result : [];

    } catch (PDOException $e) {
      error_log("Error fetching notices: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get notices by category
   */
  public function getNoticesByCategory($category, $limit = null)
  {
    try {
      $query = "SELECT n.*,
                      CASE 
                          WHEN n.category = 'Academic' THEN 'p-teal'
                          WHEN n.category = 'Administrative' THEN 'p-violet'
                          WHEN n.category = 'Sports' THEN 'p-ok'
                          WHEN n.category = 'Holiday' THEN 'p-warn'
                          WHEN n.category = 'Event' THEN 'p-info'
                          ELSE 'p-grey'
                      END as category_color,
                      CASE 
                          WHEN n.category = 'Academic' THEN 'bi-mortarboard'
                          WHEN n.category = 'Administrative' THEN 'bi-gear'
                          WHEN n.category = 'Sports' THEN 'bi-trophy'
                          WHEN n.category = 'Holiday' THEN 'bi-calendar-check'
                          WHEN n.category = 'Event' THEN 'bi-calendar2-heart'
                          ELSE 'bi-info-circle'
                      END as category_icon,
                      DATE_FORMAT(n.created_at, '%d %b %Y') as formatted_date,
                      DATE_FORMAT(n.created_at, '%h:%i %p') as formatted_time,
                      CASE 
                          WHEN DATEDIFF(CURDATE(), n.created_at) = 0 THEN 'Today'
                          WHEN DATEDIFF(CURDATE(), n.created_at) = 1 THEN 'Yesterday'
                          WHEN DATEDIFF(CURDATE(), n.created_at) < 7 THEN CONCAT(DATEDIFF(CURDATE(), n.created_at), ' days ago')
                          WHEN DATEDIFF(CURDATE(), n.created_at) < 30 THEN CONCAT(FLOOR(DATEDIFF(CURDATE(), n.created_at) / 7), ' weeks ago')
                          ELSE DATE_FORMAT(n.created_at, '%b %d, %Y')
                      END as time_ago
                      FROM notices n
                      WHERE n.category = ?
                      ORDER BY n.created_at DESC";

      if ($limit) {
        $query .= " LIMIT " . intval($limit);
      }

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$category]);
      return $stmt->fetchAll();

    } catch (PDOException $e) {
      error_log("Error fetching notices by category: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get important notices - FIXED
   */
  public function getImportantNotices($limit = null)
  {
    try {
      $query = "SELECT n.*,
                      CASE 
                          WHEN n.category = 'Academic' THEN 'p-teal'
                          WHEN n.category = 'Administrative' THEN 'p-violet'
                          WHEN n.category = 'Sports' THEN 'p-ok'
                          WHEN n.category = 'Holiday' THEN 'p-warn'
                          WHEN n.category = 'Event' THEN 'p-info'
                          ELSE 'p-grey'
                      END as category_color,
                      CASE 
                          WHEN n.category = 'Academic' THEN 'bi-mortarboard'
                          WHEN n.category = 'Administrative' THEN 'bi-gear'
                          WHEN n.category = 'Sports' THEN 'bi-trophy'
                          WHEN n.category = 'Holiday' THEN 'bi-calendar-check'
                          WHEN n.category = 'Event' THEN 'bi-calendar2-heart'
                          ELSE 'bi-info-circle'
                      END as category_icon,
                      DATE_FORMAT(n.created_at, '%d %b %Y') as formatted_date,
                      DATE_FORMAT(n.created_at, '%h:%i %p') as formatted_time,
                      CASE 
                          WHEN DATEDIFF(CURDATE(), n.created_at) = 0 THEN 'Today'
                          WHEN DATEDIFF(CURDATE(), n.created_at) = 1 THEN 'Yesterday'
                          WHEN DATEDIFF(CURDATE(), n.created_at) < 7 THEN CONCAT(DATEDIFF(CURDATE(), n.created_at), ' days ago')
                          WHEN DATEDIFF(CURDATE(), n.created_at) < 30 THEN CONCAT(FLOOR(DATEDIFF(CURDATE(), n.created_at) / 7), ' weeks ago')
                          ELSE DATE_FORMAT(n.created_at, '%b %d, %Y')
                      END as time_ago
                      FROM notices n
                      WHERE n.title LIKE '%Important%' 
                         OR n.title LIKE '%Mandatory%' 
                         OR n.title LIKE '%Urgent%'
                         OR n.title LIKE '%Action%'
                         OR n.details LIKE '%important%'
                      ORDER BY n.created_at DESC";

      if ($limit) {
        $query .= " LIMIT " . intval($limit);
      }

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetchAll();

    } catch (PDOException $e) {
      error_log("Error fetching important notices: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get notice statistics - FIXED
   */
  public function getNoticeStats()
  {
    try {
      $query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN category = 'Academic' THEN 1 ELSE 0 END) as academic,
                        SUM(CASE WHEN category = 'Administrative' THEN 1 ELSE 0 END) as administrative,
                        SUM(CASE WHEN category = 'Sports' THEN 1 ELSE 0 END) as sports,
                        SUM(CASE WHEN category = 'Holiday' THEN 1 ELSE 0 END) as holiday,
                        SUM(CASE WHEN category = 'Event' THEN 1 ELSE 0 END) as event,
                        SUM(CASE WHEN title LIKE '%Important%' OR title LIKE '%Mandatory%' OR title LIKE '%Urgent%' THEN 1 ELSE 0 END) as important
                        FROM notices";

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      $result = $stmt->fetch();

      // Ensure all values are set
      return [
        'total' => $result['total'] ?? 0,
        'academic' => $result['academic'] ?? 0,
        'administrative' => $result['administrative'] ?? 0,
        'sports' => $result['sports'] ?? 0,
        'holiday' => $result['holiday'] ?? 0,
        'event' => $result['event'] ?? 0,
        'important' => $result['important'] ?? 0
      ];

    } catch (PDOException $e) {
      error_log("Error fetching notice stats: " . $e->getMessage());
      return [
        'total' => 0,
        'academic' => 0,
        'administrative' => 0,
        'sports' => 0,
        'holiday' => 0,
        'event' => 0,
        'important' => 0
      ];
    }
  }

  /**
   * Get categories with counts - FIXED
   */
  public function getCategoriesWithCounts()
  {
    try {
      $query = "SELECT 
                        category,
                        COUNT(*) as count
                        FROM notices
                        WHERE category IS NOT NULL AND category != ''
                        GROUP BY category
                        ORDER BY count DESC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetchAll();

    } catch (PDOException $e) {
      error_log("Error fetching categories: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get recent notices
   */
  public function getRecentNotices($limit = 5)
  {
    return $this->getAllNotices($limit);
  }

  /**
   * Get notice by ID
   */
  public function getNoticeById($notice_id)
  {
    try {
      $query = "SELECT n.*,
                      CASE 
                          WHEN n.category = 'Academic' THEN 'p-teal'
                          WHEN n.category = 'Administrative' THEN 'p-violet'
                          WHEN n.category = 'Sports' THEN 'p-ok'
                          WHEN n.category = 'Holiday' THEN 'p-warn'
                          WHEN n.category = 'Event' THEN 'p-info'
                          ELSE 'p-grey'
                      END as category_color,
                      CASE 
                          WHEN n.category = 'Academic' THEN 'bi-mortarboard'
                          WHEN n.category = 'Administrative' THEN 'bi-gear'
                          WHEN n.category = 'Sports' THEN 'bi-trophy'
                          WHEN n.category = 'Holiday' THEN 'bi-calendar-check'
                          WHEN n.category = 'Event' THEN 'bi-calendar2-heart'
                          ELSE 'bi-info-circle'
                      END as category_icon,
                      DATE_FORMAT(n.created_at, '%d %b %Y') as formatted_date,
                      DATE_FORMAT(n.created_at, '%h:%i %p') as formatted_time
                      FROM notices n
                      WHERE n.id = ?";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$notice_id]);
      return $stmt->fetch();

    } catch (PDOException $e) {
      error_log("Error fetching notice by ID: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Add a new notice
   */
  public function addNotice($data)
  {
    try {
      $query = "INSERT INTO notices (title, category, posted_by, details, created_at) 
                      VALUES (?, ?, ?, ?, NOW())";

      $stmt = $this->conn->prepare($query);
      return $stmt->execute([
        $data['title'],
        $data['category'],
        $data['posted_by'],
        $data['details']
      ]);
    } catch (PDOException $e) {
      error_log("Error adding notice: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Get archive notices (older than 30 days)
   */
  public function getArchiveNotices($limit = null)
  {
    try {
      $query = "SELECT n.*,
                      CASE 
                          WHEN n.category = 'Academic' THEN 'p-teal'
                          WHEN n.category = 'Administrative' THEN 'p-violet'
                          WHEN n.category = 'Sports' THEN 'p-ok'
                          WHEN n.category = 'Holiday' THEN 'p-warn'
                          WHEN n.category = 'Event' THEN 'p-info'
                          ELSE 'p-grey'
                      END as category_color,
                      DATE_FORMAT(n.created_at, '%d %b %Y') as formatted_date
                      FROM notices n
                      WHERE n.created_at < DATE_SUB(CURDATE(), INTERVAL 30 DAY)
                      ORDER BY n.created_at DESC";

      if ($limit) {
        $query .= " LIMIT " . intval($limit);
      }

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetchAll();

    } catch (PDOException $e) {
      error_log("Error fetching archive notices: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get category color
   */
  public static function getCategoryColor($category)
  {
    $colors = [
      'Academic' => 'p-teal',
      'Administrative' => 'p-violet',
      'Sports' => 'p-ok',
      'Holiday' => 'p-warn',
      'Event' => 'p-info'
    ];
    return $colors[$category] ?? 'p-grey';
  }

  /**
   * Get category icon
   */
  public static function getCategoryIcon($category)
  {
    $icons = [
      'Academic' => 'bi-mortarboard',
      'Administrative' => 'bi-gear',
      'Sports' => 'bi-trophy',
      'Holiday' => 'bi-calendar-check',
      'Event' => 'bi-calendar2-heart'
    ];
    return $icons[$category] ?? 'bi-info-circle';
  }

  /**
   * Get category label
   */
  public static function getCategoryLabel($category)
  {
    $labels = [
      'Academic' => 'Academic',
      'Administrative' => 'Admin',
      'Sports' => 'Sports',
      'Holiday' => 'Holiday',
      'Event' => 'Event'
    ];
    return $labels[$category] ?? $category;
  }

  /**
   * Check if notice is important
   */
  public static function isImportant($title)
  {
    $keywords = ['Important', 'Mandatory', 'Urgent', 'Required', 'Action', 'Alert', 'Notice'];
    foreach ($keywords as $keyword) {
      if (stripos($title, $keyword) !== false) {
        return true;
      }
    }
    return false;
  }
}

// Helper functions for use in templates
function getCategoryColor($category)
{
  return NoticeProcess::getCategoryColor($category);
}

function getCategoryIcon($category)
{
  return NoticeProcess::getCategoryIcon($category);
}

function getCategoryLabel($category)
{
  return NoticeProcess::getCategoryLabel($category);
}

function isImportant($title)
{
  return NoticeProcess::isImportant($title);
}

// Initialize the process for the current student
// Usage: $noticeProcess = new NoticeProcess($conn, $student_id);
?>