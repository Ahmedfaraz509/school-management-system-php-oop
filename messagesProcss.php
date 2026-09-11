<?php
// MessagesProcess.php
require_once '../database/connect.php';

class MessagesProcess
{
  private $conn;

  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  /**
   * Get messages with filters
   */
  public function getMessages($filters = [])
  {
    $query = "
            SELECT 
                m.*,
                CASE 
                    WHEN m.sender_type = 'Teacher' THEN t.full_name
                    WHEN m.sender_type = 'Admin' THEN 'Admin'
                    WHEN m.sender_type = 'Parent' THEN p.full_name
                    WHEN m.sender_type = 'Student' THEN CONCAT(s.first_name, ' ', s.last_name)
                    ELSE 'Unknown'
                END as sender_name,
                CASE 
                    WHEN m.recipient_type = 'Teacher' THEN t2.full_name
                    WHEN m.recipient_type = 'Admin' THEN 'Admin'
                    WHEN m.recipient_type = 'Parent' THEN p2.full_name
                    WHEN m.recipient_type = 'Student' THEN CONCAT(s2.first_name, ' ', s2.last_name)
                    ELSE 'Unknown'
                END as recipient_name
            FROM messages m
            LEFT JOIN teachers t ON m.sender_type = 'Teacher' AND m.sender_id = t.id
            LEFT JOIN teachers t2 ON m.recipient_type = 'Teacher' AND m.recipient_id = t2.id
            LEFT JOIN parents p ON m.sender_type = 'Parent' AND m.sender_id = p.id
            LEFT JOIN parents p2 ON m.recipient_type = 'Parent' AND m.recipient_id = p2.id
            LEFT JOIN students s ON m.sender_type = 'Student' AND m.sender_id = s.id
            LEFT JOIN students s2 ON m.recipient_type = 'Student' AND m.recipient_id = s2.id
            WHERE 1=1
        ";

    $params = [];

    if (!empty($filters['recipient_type']) && !empty($filters['recipient_id'])) {
      $query .= " AND m.recipient_type = :recipient_type AND m.recipient_id = :recipient_id";
      $params[':recipient_type'] = $filters['recipient_type'];
      $params[':recipient_id'] = $filters['recipient_id'];
    }

    if (!empty($filters['sender_type']) && !empty($filters['sender_id'])) {
      $query .= " AND m.sender_type = :sender_type AND m.sender_id = :sender_id";
      $params[':sender_type'] = $filters['sender_type'];
      $params[':sender_id'] = $filters['sender_id'];
    }

    if (!empty($filters['unread'])) {
      $query .= " AND m.is_read = 0";
    }

    if (!empty($filters['search'])) {
      $query .= " AND (m.subject LIKE :search OR m.content LIKE :search)";
      $params[':search'] = '%' . $filters['search'] . '%';
    }

    $query .= " ORDER BY m.created_at DESC";

    $stmt = $this->conn->prepare($query);
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Get conversation between two parties
   */
  public function getConversation($sender_type, $sender_id, $recipient_type, $recipient_id, $limit = 50)
  {
    $query = "
            SELECT 
                m.*,
                CASE 
                    WHEN m.sender_type = 'Teacher' THEN t.full_name
                    WHEN m.sender_type = 'Admin' THEN 'Admin'
                    WHEN m.sender_type = 'Parent' THEN p.full_name
                    WHEN m.sender_type = 'Student' THEN CONCAT(s.first_name, ' ', s.last_name)
                    ELSE 'Unknown'
                END as sender_display_name,
                m.sender_type = :sender_type AND m.sender_id = :sender_id as is_from_sender
            FROM messages m
            LEFT JOIN teachers t ON m.sender_type = 'Teacher' AND m.sender_id = t.id
            LEFT JOIN parents p ON m.sender_type = 'Parent' AND m.sender_id = p.id
            LEFT JOIN students s ON m.sender_type = 'Student' AND m.sender_id = s.id
            WHERE (
                (m.sender_type = :sender_type AND m.sender_id = :sender_id AND m.recipient_type = :recipient_type AND m.recipient_id = :recipient_id)
                OR 
                (m.recipient_type = :sender_type AND m.recipient_id = :sender_id AND m.sender_type = :recipient_type AND m.sender_id = :recipient_id)
            )
            ORDER BY m.created_at ASC
            LIMIT :limit
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':sender_type', $sender_type);
    $stmt->bindValue(':sender_id', $sender_id);
    $stmt->bindValue(':recipient_type', $recipient_type);
    $stmt->bindValue(':recipient_id', $recipient_id);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
  }

  /**
   * Send a new message
   */
  public function sendMessage($data)
  {
    $query = "
            INSERT INTO messages (sender_type, sender_id, recipient_type, recipient_id, subject, content, is_read)
            VALUES (:sender_type, :sender_id, :recipient_type, :recipient_id, :subject, :content, 0)
        ";

    try {
      $stmt = $this->conn->prepare($query);
      $stmt->bindValue(':sender_type', $data['sender_type']);
      $stmt->bindValue(':sender_id', $data['sender_id']);
      $stmt->bindValue(':recipient_type', $data['recipient_type']);
      $stmt->bindValue(':recipient_id', $data['recipient_id']);
      $stmt->bindValue(':subject', $data['subject'] ?? 'Chat Message');
      $stmt->bindValue(':content', $data['content']);

      if ($stmt->execute()) {
        return [
          'success' => true,
          'id' => $this->conn->lastInsertId(),
          'message' => 'Message sent successfully'
        ];
      }
      return ['success' => false, 'message' => 'Failed to send message'];
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Mark message as read
   */
  public function markAsRead($message_id, $recipient_type, $recipient_id)
  {
    $query = "
            UPDATE messages 
            SET is_read = 1 
            WHERE id = :id AND recipient_type = :recipient_type AND recipient_id = :recipient_id
        ";

    try {
      $stmt = $this->conn->prepare($query);
      $stmt->bindValue(':id', $message_id);
      $stmt->bindValue(':recipient_type', $recipient_type);
      $stmt->bindValue(':recipient_id', $recipient_id);

      if ($stmt->execute()) {
        return ['success' => true, 'message' => 'Message marked as read'];
      }
      return ['success' => false, 'message' => 'Failed to mark message as read'];
    } catch (Exception $e) {
      return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
  }

  /**
   * Get unread message count
   */
  public function getUnreadCount($recipient_type, $recipient_id)
  {
    $query = "
            SELECT COUNT(*) as count
            FROM messages
            WHERE recipient_type = :recipient_type AND recipient_id = :recipient_id AND is_read = 0
        ";

    $stmt = $this->conn->prepare($query);
    $stmt->bindValue(':recipient_type', $recipient_type);
    $stmt->bindValue(':recipient_id', $recipient_id);
    $stmt->execute();

    $result = $stmt->fetch();
    return $result['count'] ?? 0;
  }

  /**
   * Get teacher contacts
   */
  public function getTeacherContacts($teacher_id)
  {
    $contacts = [];

    // Get students
    $student_query = "
            SELECT 
                'Student' as type,
                s.id,
                s.first_name,
                s.last_name,
                s.student_uid,
                c.name as class_name
            FROM students s
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE c.teacher_id = :teacher_id AND s.status = 'Active'
            ORDER BY s.first_name
        ";
    $stmt = $this->conn->prepare($student_query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();
    $contacts = array_merge($contacts, $stmt->fetchAll());

    // Get parents
    $parent_query = "
            SELECT 
                'Parent' as type,
                p.id,
                p.full_name as first_name,
                '' as last_name,
                p.phone as student_uid,
                'Parent' as class_name
            FROM parents p
            LEFT JOIN students s ON p.student_id = s.id
            LEFT JOIN classes c ON s.class_id = c.id
            WHERE c.teacher_id = :teacher_id
            GROUP BY p.id
            ORDER BY p.full_name
        ";
    $stmt = $this->conn->prepare($parent_query);
    $stmt->bindValue(':teacher_id', $teacher_id);
    $stmt->execute();
    $contacts = array_merge($contacts, $stmt->fetchAll());

    // Add Admin
    $contacts[] = [
      'type' => 'Admin',
      'id' => 1,
      'first_name' => 'Admin',
      'last_name' => '',
      'student_uid' => '',
      'class_name' => 'Administration'
    ];

    return $contacts;
  }

  /**
   * Get message statistics
   */
  public function getMessageStats($user_type, $user_id)
  {
    try {
      $query = "
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN recipient_type = :user_type AND recipient_id = :user_id THEN 1 ELSE 0 END) as inbox,
                    SUM(CASE WHEN sender_type = :user_type AND sender_id = :user_id THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN recipient_type = :user_type AND recipient_id = :user_id AND is_read = 0 THEN 1 ELSE 0 END) as unread
                FROM messages
                WHERE sender_type = :user_type AND sender_id = :user_id OR recipient_type = :user_type AND recipient_id = :user_id
            ";

      $stmt = $this->conn->prepare($query);
      $stmt->bindValue(':user_type', $user_type);
      $stmt->bindValue(':user_id', $user_id);
      $stmt->execute();

      $result = $stmt->fetch();
      if (!$result) {
        return ['total' => 0, 'inbox' => 0, 'sent' => 0, 'unread' => 0];
      }
      return $result;
    } catch (Exception $e) {
      return ['total' => 0, 'inbox' => 0, 'sent' => 0, 'unread' => 0];
    }
  }
}
?>