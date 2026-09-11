<?php
require_once '../database/connect.php';

class MessagesProcess
{
  private $conn;
  public $id;
  public $sender_type;
  public $sender_id;
  public $recipient_type;
  public $recipient_id;
  public $subject;
  public $content;
  public $is_read;
  public $created_at;

  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  public function setData($id, $sender_type, $sender_id, $recipient_type, $recipient_id, $subject, $content, $is_read, $created_at)
  {
    $this->id = $id;
    $this->sender_type = $sender_type;
    $this->sender_id = $sender_id;
    $this->recipient_type = $recipient_type;
    $this->recipient_id = $recipient_id;
    $this->subject = $subject;
    $this->content = $content;
    $this->is_read = $is_read;
    $this->created_at = $created_at;
  }

  public function insert()
  {
    try {
      if ($this->is_read === null) {
        $this->is_read = 0;
      }

      $insert_query = "INSERT INTO messages (sender_type, sender_id, recipient_type, recipient_id, subject, content, is_read, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

      $stmt = $this->conn->prepare($insert_query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([
        $this->sender_type,
        $this->sender_id,
        $this->recipient_type,
        $this->recipient_id,
        $this->subject,
        $this->content,
        $this->is_read,
        $this->created_at
      ]);

      if (!$result) {
        error_log("Execute failed: " . print_r($stmt->errorInfo(), true));
        return false;
      }

      return $this->conn->lastInsertId();
    } catch (PDOException $e) {
      error_log("Insert message error: " . $e->getMessage());
      return false;
    }
  }

  public function markAsRead($id)
  {
    try {
      $update_query = "UPDATE messages SET is_read = 1 WHERE id = ?";
      $stmt = $this->conn->prepare($update_query);
      $result = $stmt->execute([$id]);
      return $result && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Mark as read error: " . $e->getMessage());
      return false;
    }
  }

  public function delete($id)
  {
    try {
      $delete_query = "DELETE FROM messages WHERE id = ?";
      $stmt = $this->conn->prepare($delete_query);
      $result = $stmt->execute([$id]);
      return $result && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Delete message error: " . $e->getMessage());
      return false;
    }
  }

  public function getChatMessages($user1_type, $user1_id, $user2_type, $user2_id, $limit = 50)
  {
    try {
      $query = "SELECT m.*, 
                      CASE 
                          WHEN m.sender_type = 'Admin' THEN (SELECT name FROM users WHERE id = m.sender_id)
                          WHEN m.sender_type = 'Teacher' THEN (SELECT full_name FROM teachers WHERE id = m.sender_id)
                          WHEN m.sender_type = 'Parent' THEN (SELECT full_name FROM parents WHERE id = m.sender_id)
                          WHEN m.sender_type = 'Student' THEN (SELECT CONCAT(first_name, ' ', last_name) FROM students WHERE id = m.sender_id)
                      END as sender_name,
                      CASE 
                          WHEN m.sender_type = 'Admin' THEN 'admin'
                          WHEN m.sender_type = 'Teacher' THEN 'teacher'
                          WHEN m.sender_type = 'Parent' THEN 'parent'
                          WHEN m.sender_type = 'Student' THEN 'student'
                      END as sender_role,
                      CASE 
                          WHEN m.sender_type = 'Admin' THEN (SELECT photo_url FROM users WHERE id = m.sender_id)
                          WHEN m.sender_type = 'Teacher' THEN (SELECT photo FROM teachers WHERE id = m.sender_id)
                          WHEN m.sender_type = 'Student' THEN (SELECT photo_url FROM students WHERE id = m.sender_id)
                          ELSE NULL
                      END as sender_avatar
                      FROM messages m
                      WHERE (sender_type = ? AND sender_id = ? AND recipient_type = ? AND recipient_id = ?)
                         OR (sender_type = ? AND sender_id = ? AND recipient_type = ? AND recipient_id = ?)
                      ORDER BY m.created_at ASC
                      LIMIT ?";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([
        $user1_type,
        $user1_id,
        $user2_type,
        $user2_id,
        $user2_type,
        $user2_id,
        $user1_type,
        $user1_id,
        $limit
      ]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get chat messages error: " . $e->getMessage());
      return [];
    }
  }

  public function getConversations($user_type, $user_id)
  {
    try {
      $query = "SELECT 
                      CASE 
                          WHEN m.sender_type = ? AND m.sender_id = ? THEN m.recipient_type
                          ELSE m.sender_type
                      END as other_type,
                      CASE 
                          WHEN m.sender_type = ? AND m.sender_id = ? THEN m.recipient_id
                          ELSE m.sender_id
                      END as other_id,
                      CASE 
                          WHEN m.sender_type = 'Admin' AND m.sender_id != ? THEN (SELECT name FROM users WHERE id = m.sender_id)
                          WHEN m.recipient_type = 'Admin' AND m.recipient_id != ? THEN (SELECT name FROM users WHERE id = m.recipient_id)
                          WHEN m.sender_type = 'Teacher' AND m.sender_id != ? THEN (SELECT full_name FROM teachers WHERE id = m.sender_id)
                          WHEN m.recipient_type = 'Teacher' AND m.recipient_id != ? THEN (SELECT full_name FROM teachers WHERE id = m.recipient_id)
                          WHEN m.sender_type = 'Parent' AND m.sender_id != ? THEN (SELECT full_name FROM parents WHERE id = m.sender_id)
                          WHEN m.recipient_type = 'Parent' AND m.recipient_id != ? THEN (SELECT full_name FROM parents WHERE id = m.recipient_id)
                          WHEN m.sender_type = 'Student' AND m.sender_id != ? THEN (SELECT CONCAT(first_name, ' ', last_name) FROM students WHERE id = m.sender_id)
                          WHEN m.recipient_type = 'Student' AND m.recipient_id != ? THEN (SELECT CONCAT(first_name, ' ', last_name) FROM students WHERE id = m.recipient_id)
                      END as other_name,
                      MAX(m.created_at) as last_message_time,
                      (SELECT content FROM messages WHERE 
                          (sender_type = m.sender_type AND sender_id = m.sender_id AND recipient_type = m.recipient_type AND recipient_id = m.recipient_id)
                          OR (sender_type = m.recipient_type AND sender_id = m.recipient_id AND recipient_type = m.sender_type AND recipient_id = m.sender_id)
                          ORDER BY created_at DESC LIMIT 1
                      ) as last_message,
                      (SELECT is_read FROM messages WHERE 
                          recipient_type = ? AND recipient_id = ? 
                          AND sender_type = (CASE WHEN m.sender_type = ? AND m.sender_id = ? THEN m.recipient_type ELSE m.sender_type END)
                          AND sender_id = (CASE WHEN m.sender_type = ? AND m.sender_id = ? THEN m.recipient_id ELSE m.sender_id END)
                          ORDER BY created_at DESC LIMIT 1
                      ) as is_read,
                      COUNT(CASE WHEN m.recipient_type = ? AND m.recipient_id = ? AND m.is_read = 0 THEN 1 END) as unread_count
                      FROM messages m
                      WHERE m.sender_type = ? AND m.sender_id = ? OR m.recipient_type = ? AND m.recipient_id = ?
                      GROUP BY other_type, other_id
                      ORDER BY last_message_time DESC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([
        $user_type,
        $user_id,
        $user_type,
        $user_id,
        $user_id,
        $user_id,
        $user_id,
        $user_id,
        $user_id,
        $user_id,
        $user_id,
        $user_id,
        $user_type,
        $user_id,
        $user_type,
        $user_id,
        $user_type,
        $user_id,
        $user_type,
        $user_id,
        $user_type,
        $user_id
      ]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get conversations error: " . $e->getMessage());
      return [];
    }
  }

  public function getUnreadCount($recipient_type, $recipient_id)
  {
    try {
      $query = "SELECT COUNT(*) as total FROM messages 
                      WHERE recipient_type = ? AND recipient_id = ? AND is_read = 0";
      $stmt = $this->conn->prepare($query);
      $stmt->execute([$recipient_type, $recipient_id]);
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      return $result['total'] ?? 0;
    } catch (PDOException $e) {
      error_log("Count unread messages error: " . $e->getMessage());
      return 0;
    }
  }
}
?>