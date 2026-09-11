<?php
// message_process.php
// Process file for handling message-related database operations

require_once '../database/connect.php';

class MessageProcess
{
  private $conn;
  private $student_id;
  private $student_data;
  private $user_id;

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
    $query = "SELECT s.*, c.name as class_name, c.grade, c.id as class_id, s.user_id
                  FROM students s 
                  LEFT JOIN classes c ON s.class_id = c.id 
                  WHERE s.id = ?";
    $stmt = $this->conn->prepare($query);
    $stmt->execute([$this->student_id]);
    $this->student_data = $stmt->fetch();
    $this->user_id = $this->student_data['user_id'] ?? null;
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
   * Get all received messages for a student
   */
  public function getInboxMessages($limit = null)
  {
    if (!$this->student_data) {
      return [];
    }

    try {
      $query = "SELECT m.*,
                      CASE 
                          WHEN m.sender_type = 'Admin' THEN 'Admin Office'
                          WHEN m.sender_type = 'Teacher' THEN CONCAT('Teacher: ', t.full_name)
                          WHEN m.sender_type = 'Student' THEN 'Student'
                          WHEN m.sender_type = 'Parent' THEN 'Parent'
                          ELSE m.sender_type
                      END as sender_name,
                      CASE 
                          WHEN m.sender_type = 'Admin' THEN 'p-info'
                          WHEN m.sender_type = 'Teacher' THEN 'p-teal'
                          WHEN m.sender_type = 'Student' THEN 'p-ok'
                          WHEN m.sender_type = 'Parent' THEN 'p-warn'
                          ELSE 'p-grey'
                      END as sender_color,
                      CASE 
                          WHEN m.is_read = 0 THEN 'p-danger'
                          WHEN m.is_read = 1 THEN 'p-grey'
                          ELSE 'p-grey'
                      END as read_color,
                      CASE 
                          WHEN m.is_read = 0 THEN 'Unread'
                          ELSE 'Read'
                      END as read_status,
                      DATE_FORMAT(m.created_at, '%h:%i %p') as formatted_time,
                      DATE_FORMAT(m.created_at, '%b %d') as formatted_date,
                      CASE 
                          WHEN DATEDIFF(CURDATE(), m.created_at) = 0 THEN 'Today'
                          WHEN DATEDIFF(CURDATE(), m.created_at) = 1 THEN 'Yesterday'
                          ELSE DATE_FORMAT(m.created_at, '%b %d')
                      END as time_ago
                      FROM messages m
                      LEFT JOIN teachers t ON (m.sender_type = 'Teacher' AND m.sender_id = t.id)
                      WHERE m.recipient_type = 'Student' AND m.recipient_id = ?
                      ORDER BY m.created_at DESC";

      if ($limit) {
        $query .= " LIMIT " . intval($limit);
      }

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$this->student_id]);
      return $stmt->fetchAll();

    } catch (PDOException $e) {
      error_log("Error fetching inbox messages: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get sent messages from a student
   */
  public function getSentMessages($limit = null)
  {
    if (!$this->student_data) {
      return [];
    }

    try {
      $query = "SELECT m.*,
                      CASE 
                          WHEN m.recipient_type = 'Admin' THEN 'Admin Office'
                          WHEN m.recipient_type = 'Teacher' THEN CONCAT('Teacher: ', t.full_name)
                          WHEN m.recipient_type = 'Student' THEN 'Student'
                          WHEN m.recipient_type = 'Parent' THEN 'Parent'
                          ELSE m.recipient_type
                      END as recipient_name,
                      CASE 
                          WHEN m.sender_type = 'Admin' THEN 'p-info'
                          WHEN m.sender_type = 'Teacher' THEN 'p-teal'
                          WHEN m.sender_type = 'Student' THEN 'p-ok'
                          WHEN m.sender_type = 'Parent' THEN 'p-warn'
                          ELSE 'p-grey'
                      END as sender_color,
                      DATE_FORMAT(m.created_at, '%h:%i %p') as formatted_time,
                      DATE_FORMAT(m.created_at, '%b %d') as formatted_date,
                      CASE 
                          WHEN DATEDIFF(CURDATE(), m.created_at) = 0 THEN 'Today'
                          WHEN DATEDIFF(CURDATE(), m.created_at) = 1 THEN 'Yesterday'
                          ELSE DATE_FORMAT(m.created_at, '%b %d')
                      END as time_ago
                      FROM messages m
                      LEFT JOIN teachers t ON (m.recipient_type = 'Teacher' AND m.recipient_id = t.id)
                      WHERE m.sender_type = 'Student' AND m.sender_id = ?
                      ORDER BY m.created_at DESC";

      if ($limit) {
        $query .= " LIMIT " . intval($limit);
      }

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$this->student_id]);
      return $stmt->fetchAll();

    } catch (PDOException $e) {
      error_log("Error fetching sent messages: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get draft messages for a student
   */
  public function getDraftMessages($limit = null)
  {
    // For demo purposes, we'll return sample data
    // In a real implementation, you'd have a drafts table or a status field
    return [];
  }

  /**
   * Get unread message count
   */
  public function getUnreadCount()
  {
    if (!$this->student_data) {
      return 0;
    }

    try {
      $query = "SELECT COUNT(*) as count 
                      FROM messages 
                      WHERE recipient_type = 'Student' AND recipient_id = ? AND is_read = 0";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$this->student_id]);
      $result = $stmt->fetch();
      return $result['count'] ?? 0;

    } catch (PDOException $e) {
      error_log("Error fetching unread count: " . $e->getMessage());
      return 0;
    }
  }

  /**
   * Get message by ID
   */
  public function getMessageById($message_id)
  {
    try {
      $query = "SELECT m.*,
                      CASE 
                          WHEN m.sender_type = 'Admin' THEN 'Admin Office'
                          WHEN m.sender_type = 'Teacher' THEN CONCAT('Teacher: ', t.full_name)
                          WHEN m.sender_type = 'Student' THEN 'Student'
                          WHEN m.sender_type = 'Parent' THEN 'Parent'
                          ELSE m.sender_type
                      END as sender_name,
                      CASE 
                          WHEN m.sender_type = 'Admin' THEN 'p-info'
                          WHEN m.sender_type = 'Teacher' THEN 'p-teal'
                          WHEN m.sender_type = 'Student' THEN 'p-ok'
                          WHEN m.sender_type = 'Parent' THEN 'p-warn'
                          ELSE 'p-grey'
                      END as sender_color,
                      DATE_FORMAT(m.created_at, '%h:%i %p') as formatted_time,
                      DATE_FORMAT(m.created_at, '%b %d, %Y') as formatted_date
                      FROM messages m
                      LEFT JOIN teachers t ON (m.sender_type = 'Teacher' AND m.sender_id = t.id)
                      WHERE m.id = ?";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$message_id]);
      return $stmt->fetch();

    } catch (PDOException $e) {
      error_log("Error fetching message by ID: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Mark message as read
   */
  public function markAsRead($message_id)
  {
    try {
      $query = "UPDATE messages SET is_read = 1 WHERE id = ?";
      $stmt = $this->conn->prepare($query);
      return $stmt->execute([$message_id]);
    } catch (PDOException $e) {
      error_log("Error marking message as read: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Send a new message
   */
  public function sendMessage($data)
  {
    try {
      $query = "INSERT INTO messages (sender_type, sender_id, recipient_type, recipient_id, subject, content, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, NOW())";

      $stmt = $this->conn->prepare($query);
      return $stmt->execute([
        $data['sender_type'],
        $data['sender_id'],
        $data['recipient_type'],
        $data['recipient_id'],
        $data['subject'],
        $data['content']
      ]);
    } catch (PDOException $e) {
      error_log("Error sending message: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Delete a message
   */
  public function deleteMessage($message_id)
  {
    try {
      $query = "DELETE FROM messages WHERE id = ?";
      $stmt = $this->conn->prepare($query);
      return $stmt->execute([$message_id]);
    } catch (PDOException $e) {
      error_log("Error deleting message: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Get conversation between student and a specific sender
   */
  public function getConversation($sender_id, $sender_type)
  {
    if (!$this->student_data) {
      return [];
    }

    try {
      $query = "SELECT m.*,
                      CASE 
                          WHEN m.sender_type = 'Admin' THEN 'Admin Office'
                          WHEN m.sender_type = 'Teacher' THEN 'Teacher'
                          WHEN m.sender_type = 'Student' THEN 'Student'
                          WHEN m.sender_type = 'Parent' THEN 'Parent'
                          ELSE m.sender_type
                      END as sender_name,
                      DATE_FORMAT(m.created_at, '%h:%i %p') as formatted_time,
                      DATE_FORMAT(m.created_at, '%b %d, %Y') as formatted_date
                      FROM messages m
                      WHERE (m.sender_type = ? AND m.sender_id = ? AND m.recipient_type = 'Student' AND m.recipient_id = ?)
                         OR (m.recipient_type = ? AND m.recipient_id = ? AND m.sender_type = 'Student' AND m.sender_id = ?)
                      ORDER BY m.created_at ASC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$sender_type, $sender_id, $this->student_id, $sender_type, $sender_id, $this->student_id]);
      return $stmt->fetchAll();

    } catch (PDOException $e) {
      error_log("Error fetching conversation: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get recent messages for a student (for dashboard)
   */
  public function getRecentMessages($limit = 5)
  {
    return $this->getInboxMessages($limit);
  }

  /**
   * Get message statistics
   */
  public function getMessageStats()
  {
    if (!$this->student_data) {
      return [
        'total' => 0,
        'unread' => 0,
        'sent' => 0,
        'drafts' => 0
      ];
    }

    $unread = $this->getUnreadCount();
    $inbox = $this->getInboxMessages();
    $sent = $this->getSentMessages();

    return [
      'total' => count($inbox),
      'unread' => $unread,
      'sent' => count($sent),
      'drafts' => 0
    ];
  }

  /**
   * Get contacts for a student (teachers, admin, etc.)
   */
  public function getContacts()
  {
    if (!$this->student_data) {
      return [];
    }

    try {
      // Get teachers for the student's class
      $query = "SELECT DISTINCT t.id, t.full_name, t.email, t.phone,
                      'Teacher' as type,
                      CASE 
                          WHEN t.full_name LIKE '%Sara%' THEN 'info'
                          WHEN t.full_name LIKE '%Ahmed%' THEN 'ok'
                          WHEN t.full_name LIKE '%Farah%' THEN 'amber'
                          WHEN t.full_name LIKE '%Ali%' THEN 'violet'
                          ELSE 'info'
                      END as avatar_color
                      FROM teachers t
                      JOIN subjects s ON s.teacher_id = t.id
                      JOIN classes c ON s.class_id = c.id
                      JOIN students st ON st.class_id = c.id
                      WHERE st.id = ? AND t.status = 'active'
                      LIMIT 5";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$this->student_id]);
      $teachers = $stmt->fetchAll();

      // Add admin as contact
      $contacts = [
        [
          'id' => 1,
          'full_name' => 'Admin Office',
          'type' => 'Admin',
          'avatar_color' => 'violet'
        ],
        [
          'id' => 2,
          'full_name' => 'Accounts Office',
          'type' => 'Admin',
          'avatar_color' => 'danger'
        ]
      ];

      return array_merge($contacts, $teachers);

    } catch (PDOException $e) {
      error_log("Error fetching contacts: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get notification count for badge
   */
  public function getNotificationCount()
  {
    return $this->getUnreadCount();
  }

  /**
   * Get teacher name from ID
   */
  public function getTeacherName($teacher_id)
  {
    try {
      $query = "SELECT full_name FROM teachers WHERE id = ?";
      $stmt = $this->conn->prepare($query);
      $stmt->execute([$teacher_id]);
      $result = $stmt->fetch();
      return $result['full_name'] ?? 'Unknown Teacher';
    } catch (PDOException $e) {
      return 'Unknown Teacher';
    }
  }

  /**
   * Get admin name
   */
  public function getAdminName()
  {
    return 'Admin Office';
  }

  /**
   * Format message content for preview
   */
  public static function formatPreview($content, $length = 80)
  {
    $clean = strip_tags($content);
    if (strlen($clean) > $length) {
      return substr($clean, 0, $length) . '…';
    }
    return $clean;
  }

  /**
   * Get avatar color based on name
   */
  public static function getAvatarColor($name)
  {
    $colors = [
      'Sara' => 'info',
      'Ahmed' => 'ok',
      'Farah' => 'amber',
      'Ali' => 'violet',
      'Hina' => 'teal',
      'Bilal' => 'ok',
      'Tariq' => 'amber',
      'Admin' => 'violet',
      'Accounts' => 'danger',
      'Examination' => 'info',
      'Library' => 'ok'
    ];

    foreach ($colors as $key => $color) {
      if (stripos($name, $key) !== false) {
        return $color;
      }
    }
    return 'info';
  }

  /**
   * Get initials from name
   */
  public static function getInitials($name)
  {
    $words = explode(' ', $name);
    $initials = '';
    foreach ($words as $word) {
      if (!empty($word)) {
        $initials .= strtoupper(substr($word, 0, 1));
      }
    }
    return substr($initials, 0, 2);
  }

  /**
   * Get sender type label
   */
  public static function getSenderTypeLabel($type)
  {
    $labels = [
      'Admin' => 'Admin Office',
      'Teacher' => 'Teacher',
      'Student' => 'Student',
      'Parent' => 'Parent'
    ];
    return $labels[$type] ?? $type;
  }
}

// Helper functions for use in templates
function getAvatarColor($name)
{
  return MessageProcess::getAvatarColor($name);
}

function getInitials($name)
{
  return MessageProcess::getInitials($name);
}

function formatPreview($content, $length = 80)
{
  return MessageProcess::formatPreview($content, $length);
}

function getSenderTypeLabel($type)
{
  return MessageProcess::getSenderTypeLabel($type);
}

// Initialize the process for the current student
// Usage: $messageProcess = new MessageProcess($conn, $student_id);
?>