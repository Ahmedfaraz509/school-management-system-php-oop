<?php
// messages.php
require_once '../database/connect.php';

session_start();
$teacher_id = $_SESSION['teacher_id'] ?? 10; // Default teacher ID for demo

// Get teacher info
$teacher_stmt = $conn->prepare("
    SELECT id as teacher_db_id, full_name, email, qualification, photo 
    FROM teachers 
    WHERE user_id = :user_id
");
$teacher_stmt->bindValue(':user_id', $teacher_id);
$teacher_stmt->execute();
$teacher = $teacher_stmt->fetch();
$teacher_name = $teacher['full_name'] ?? 'Mr. Ahmed';
$teacher_initials = implode('', array_map(function ($word) {
  return strtoupper(substr($word, 0, 1));
}, explode(' ', $teacher_name)));
$teacher_db_id = $teacher['teacher_db_id'] ?? 0;

// Debug - Check if teacher exists
if (!$teacher_db_id) {
  // Try to get teacher by direct ID
  $fallback_stmt = $conn->prepare("SELECT id, full_name FROM teachers WHERE id = :id");
  $fallback_stmt->bindValue(':id', $teacher_id);
  $fallback_stmt->execute();
  $fallback_teacher = $fallback_stmt->fetch();
  if ($fallback_teacher) {
    $teacher_db_id = $fallback_teacher['id'];
    $teacher_name = $fallback_teacher['full_name'];
  }
}

// Process message actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
  // Send new message
  if (isset($_POST['send_message'])) {
    $recipient_type = $_POST['recipient_type'] ?? '';
    $recipient_id = $_POST['recipient_id'] ?? 0;
    $subject = $_POST['subject'] ?? '';
    $content = $_POST['content'] ?? '';

    if ($recipient_type && $recipient_id && $content && $teacher_db_id) {
      try {
        $insert_stmt = $conn->prepare("
                    INSERT INTO messages (sender_type, sender_id, recipient_type, recipient_id, subject, content, is_read)
                    VALUES ('Teacher', :sender_id, :recipient_type, :recipient_id, :subject, :content, 0)
                ");

        $insert_stmt->bindValue(':sender_id', $teacher_db_id);
        $insert_stmt->bindValue(':recipient_type', $recipient_type);
        $insert_stmt->bindValue(':recipient_id', $recipient_id);
        $insert_stmt->bindValue(':subject', $subject ?: 'Chat Message');
        $insert_stmt->bindValue(':content', $content);

        if ($insert_stmt->execute()) {
          $success_message = "Message sent successfully!";
        } else {
          $error_message = "Error sending message.";
        }
      } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
      }
    } else {
      $error_message = "Please fill in all required fields.";
    }
  }

  // Mark message as read
  if (isset($_POST['mark_read'])) {
    $message_id = $_POST['message_id'] ?? 0;

    if ($message_id && $teacher_db_id) {
      try {
        $update_stmt = $conn->prepare("
                    UPDATE messages 
                    SET is_read = 1 
                    WHERE id = :id AND recipient_type = 'Teacher' AND recipient_id = :recipient_id
                ");
        $update_stmt->bindValue(':id', $message_id);
        $update_stmt->bindValue(':recipient_id', $teacher_db_id);

        if ($update_stmt->execute()) {
          echo json_encode(['success' => true]);
          exit;
        }
      } catch (PDOException $e) {
        // Silent fail
      }
    }
  }

  // Delete message
  if (isset($_POST['delete_message'])) {
    $message_id = $_POST['message_id'] ?? 0;

    if ($message_id && $teacher_db_id) {
      try {
        $delete_stmt = $conn->prepare("
                    DELETE FROM messages 
                    WHERE id = :id AND (sender_id = :sender_id OR recipient_id = :recipient_id)
                ");
        $delete_stmt->bindValue(':id', $message_id);
        $delete_stmt->bindValue(':sender_id', $teacher_db_id);
        $delete_stmt->bindValue(':recipient_id', $teacher_db_id);

        if ($delete_stmt->execute()) {
          $success_message = "Message deleted successfully!";
        } else {
          $error_message = "Error deleting message.";
        }
      } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
      }
    }
  }
}

// Get filter parameters
$tab = $_GET['tab'] ?? 'inbox';
$search = $_GET['search'] ?? '';

// Get message statistics
$stats = ['total' => 0, 'inbox' => 0, 'sent' => 0, 'unread' => 0];
if ($teacher_db_id) {
  try {
    $stats_query = "
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN recipient_type = 'Teacher' AND recipient_id = :teacher_id AND is_read = 0 THEN 1 ELSE 0 END) as unread,
                SUM(CASE WHEN recipient_type = 'Teacher' AND recipient_id = :teacher_id THEN 1 ELSE 0 END) as inbox,
                SUM(CASE WHEN sender_type = 'Teacher' AND sender_id = :teacher_id THEN 1 ELSE 0 END) as sent
            FROM messages
            WHERE sender_type = 'Teacher' AND sender_id = :teacher_id OR recipient_type = 'Teacher' AND recipient_id = :teacher_id
        ";
    $stats_stmt = $conn->prepare($stats_query);
    $stats_stmt->bindValue(':teacher_id', $teacher_db_id);
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch();
    if (!$stats) {
      $stats = ['total' => 0, 'inbox' => 0, 'sent' => 0, 'unread' => 0];
    }
  } catch (PDOException $e) {
    // Table might be empty or structure different
    $stats = ['total' => 0, 'inbox' => 0, 'sent' => 0, 'unread' => 0];
  }
}

// Build messages query based on tab
$messages = [];
if ($teacher_db_id) {
  try {
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

    // Tab filtering
    if ($tab == 'inbox') {
      $query .= " AND m.recipient_type = 'Teacher' AND m.recipient_id = :teacher_id";
      $params[':teacher_id'] = $teacher_db_id;
    } elseif ($tab == 'sent') {
      $query .= " AND m.sender_type = 'Teacher' AND m.sender_id = :teacher_id";
      $params[':teacher_id'] = $teacher_db_id;
    } elseif ($tab == 'unread') {
      $query .= " AND m.recipient_type = 'Teacher' AND m.recipient_id = :teacher_id AND m.is_read = 0";
      $params[':teacher_id'] = $teacher_db_id;
    }

    // Search filter
    if (!empty($search)) {
      $query .= " AND (m.subject LIKE :search OR m.content LIKE :search)";
      $params[':search'] = '%' . $search . '%';
    }

    $query .= " ORDER BY m.created_at DESC";

    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
      $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $messages = $stmt->fetchAll();
  } catch (PDOException $e) {
    $messages = [];
  }
}

// Get contacts for compose dropdown
$contacts = [];

// Get student contacts
try {
  $contacts_stmt = $conn->prepare("
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
    ");
  $contacts_stmt->bindValue(':teacher_id', $teacher_db_id);
  $contacts_stmt->execute();
  $student_contacts = $contacts_stmt->fetchAll();
  $contacts = array_merge($contacts, $student_contacts);
} catch (PDOException $e) {
  // No students found or table issue
}

// Get parent contacts
try {
  $parent_contacts_stmt = $conn->prepare("
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
    ");
  $parent_contacts_stmt->bindValue(':teacher_id', $teacher_db_id);
  $parent_contacts_stmt->execute();
  $parent_contacts = $parent_contacts_stmt->fetchAll();
  $contacts = array_merge($contacts, $parent_contacts);
} catch (PDOException $e) {
  // No parents found
}

// Add Admin contact
$contacts[] = [
  'type' => 'Admin',
  'id' => 1,
  'first_name' => 'Admin',
  'last_name' => '',
  'student_uid' => '',
  'class_name' => 'Administration'
];

// Get conversation for chat view
$conversation_id = isset($_GET['conversation']) ? (int) $_GET['conversation'] : 0;
$conversation_messages = [];
$conversation_participant = null;

if ($conversation_id > 0 && $teacher_db_id) {
  // Find participant
  foreach ($contacts as $contact) {
    if ($contact['id'] == $conversation_id) {
      $conversation_participant = $contact;
      break;
    }
  }

  if ($conversation_participant) {
    try {
      $conv_stmt = $conn->prepare("
                SELECT 
                    m.*,
                    CASE 
                        WHEN m.sender_type = 'Teacher' THEN 'You'
                        WHEN m.sender_type = 'Admin' THEN 'Admin'
                        WHEN m.sender_type = 'Parent' THEN p.full_name
                        WHEN m.sender_type = 'Student' THEN CONCAT(s.first_name, ' ', s.last_name)
                        ELSE 'Unknown'
                    END as sender_display_name,
                    m.sender_type = 'Teacher' AND m.sender_id = :teacher_id as is_from_teacher
                FROM messages m
                LEFT JOIN parents p ON m.sender_type = 'Parent' AND m.sender_id = p.id
                LEFT JOIN students s ON m.sender_type = 'Student' AND m.sender_id = s.id
                WHERE (
                    (m.sender_type = 'Teacher' AND m.sender_id = :teacher_id AND m.recipient_type = :recipient_type AND m.recipient_id = :recipient_id)
                    OR 
                    (m.recipient_type = 'Teacher' AND m.recipient_id = :teacher_id AND m.sender_type = :recipient_type AND m.sender_id = :recipient_id)
                )
                ORDER BY m.created_at ASC
            ");

      $conv_stmt->bindValue(':teacher_id', $teacher_db_id);
      $conv_stmt->bindValue(':recipient_type', $conversation_participant['type']);
      $conv_stmt->bindValue(':recipient_id', $conversation_participant['id']);
      $conv_stmt->execute();
      $conversation_messages = $conv_stmt->fetchAll();
    } catch (PDOException $e) {
      $conversation_messages = [];
    }
  }
}

// Function to get status badge
function getMessageStatusBadge($is_read, $is_from_teacher)
{
  if ($is_from_teacher) {
    return '<span class="badge bg-primary">Sent</span>';
  } elseif ($is_read) {
    return '<span class="badge bg-success">Read</span>';
  } else {
    return '<span class="badge bg-danger">Unread</span>';
  }
}

// Function to get time ago
function timeAgo($datetime)
{
  if (!$datetime)
    return 'N/A';
  $time = strtotime($datetime);
  $now = time();
  $diff = $now - $time;

  if ($diff < 60) {
    return 'Just now';
  } elseif ($diff < 3600) {
    return floor($diff / 60) . 'm ago';
  } elseif ($diff < 86400) {
    return floor($diff / 3600) . 'h ago';
  } elseif ($diff < 604800) {
    return floor($diff / 86400) . 'd ago';
  } else {
    return date('d M Y', $time);
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Messages | Teacher Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    .td-wrapper {
      display: flex;
      min-height: 100vh;
    }

    .td-sidebar {
      width: 260px;
      background: #2c3e50;
      color: #ecf0f1;
      position: fixed;
      height: 100vh;
      overflow-y: auto;
      z-index: 1000;
      transition: transform 0.3s ease;
    }

    .td-main {
      flex: 1;
      margin-left: 260px;
      background: #f4f6f9;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
    }

    .td-brand {
      padding: 20px;
      font-size: 1.3rem;
      font-weight: bold;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .td-brand i {
      font-size: 1.8rem;
      color: #3498db;
    }

    .td-brand small {
      display: block;
      font-size: 0.65rem;
      font-weight: normal;
      opacity: 0.7;
    }

    .td-teacher-box {
      padding: 20px;
      display: flex;
      align-items: center;
      gap: 12px;
      border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .td-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background: #3498db;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      color: white;
      font-size: 14px;
      flex-shrink: 0;
    }

    .td-teacher-box h6 {
      margin: 0;
      font-size: 0.9rem;
      color: white;
    }

    .td-teacher-box p {
      margin: 0;
      font-size: 0.75rem;
      opacity: 0.7;
    }

    .td-nav {
      padding: 10px 0;
    }

    .td-nav-title {
      padding: 10px 20px;
      font-size: 0.7rem;
      text-transform: uppercase;
      opacity: 0.5;
      letter-spacing: 1px;
    }

    .td-nav a {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 20px;
      color: rgba(255, 255, 255, 0.7);
      text-decoration: none;
      transition: all 0.3s;
      border-left: 3px solid transparent;
    }

    .td-nav a:hover {
      background: rgba(255, 255, 255, 0.05);
      color: white;
    }

    .td-nav a.active {
      background: rgba(52, 152, 219, 0.2);
      color: white;
      border-left-color: #3498db;
    }

    .td-nav a.logout {
      border-top: 1px solid rgba(255, 255, 255, 0.1);
      margin-top: 10px;
      color: #e74c3c;
    }

    .td-nav a.logout:hover {
      background: rgba(231, 76, 60, 0.1);
    }

    .td-nav a i {
      width: 20px;
    }

    .td-navbar {
      background: white;
      padding: 15px 25px;
      display: flex;
      align-items: center;
      gap: 15px;
      box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
      position: sticky;
      top: 0;
      z-index: 100;
      flex-wrap: wrap;
    }

    .td-burger {
      font-size: 1.5rem;
      cursor: pointer;
      display: none;
    }

    .td-page-title {
      font-size: 1.2rem;
      margin: 0;
    }

    .td-page-title small {
      font-size: 0.75rem;
      color: #6c757d;
      font-weight: normal;
    }

    .td-search {
      min-width: 200px;
    }

    .td-icon-btn {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f8f9fa;
      color: #333;
      text-decoration: none;
      position: relative;
      transition: background 0.3s;
    }

    .td-icon-btn:hover {
      background: #e9ecef;
      color: #333;
    }

    .td-dot {
      width: 8px;
      height: 8px;
      background: #e74c3c;
      border-radius: 50%;
      position: absolute;
      top: 8px;
      right: 8px;
      border: 2px solid white;
    }

    .td-content {
      padding: 25px;
      flex: 1;
    }

    .td-footer {
      background: white;
      padding: 15px 25px;
      text-align: center;
      font-size: 0.85rem;
      color: #6c757d;
      border-top: 1px solid #e9ecef;
    }

    .stat-card {
      padding: 15px;
      border-radius: 10px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
      display: flex;
      align-items: center;
      gap: 15px;
      background: white;
      transition: transform 0.2s;
      border: none;
    }

    .stat-card:hover {
      transform: translateY(-2px);
    }

    .stat-icon {
      width: 48px;
      height: 48px;
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 24px;
      flex-shrink: 0;
    }

    .stat-card h3 {
      margin: 0;
      font-size: 1.5rem;
    }

    .stat-card p {
      margin: 0;
      color: #6c757d;
      font-size: 0.85rem;
    }

    .msg-tabs .nav-link {
      border-radius: 8px;
      padding: 8px 16px;
      font-size: 0.9rem;
    }

    .msg-tabs .nav-link.active {
      background: #0d6efd;
      color: white;
    }

    .msg-tabs .nav-link:not(.active) {
      background: white;
      color: #333;
    }

    .msg-item {
      cursor: pointer;
      transition: background 0.2s;
    }

    .msg-item:hover {
      background: #f0f4ff;
    }

    .msg-item.unread {
      font-weight: 600;
    }

    .chat-container {
      display: flex;
      flex-direction: column;
      height: 500px;
    }

    .chat-messages {
      flex: 1;
      overflow-y: auto;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 8px;
    }

    .chat-message {
      margin-bottom: 15px;
      max-width: 70%;
    }

    .chat-message.sent {
      margin-left: auto;
    }

    .chat-message.received {
      margin-right: auto;
    }

    .chat-message .message-bubble {
      padding: 10px 15px;
      border-radius: 12px;
      position: relative;
    }

    .chat-message.sent .message-bubble {
      background: #0d6efd;
      color: white;
    }

    .chat-message.received .message-bubble {
      background: white;
      border: 1px solid #dee2e6;
    }

    .chat-message .message-time {
      font-size: 0.7rem;
      opacity: 0.7;
      margin-top: 4px;
      display: block;
    }

    .chat-input {
      padding: 10px;
      background: white;
      border-radius: 8px;
      border: 1px solid #dee2e6;
    }

    .bg-grad-1 {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }

    .bg-grad-2 {
      background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
    }

    .bg-grad-3 {
      background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
    }

    .bg-grad-4 {
      background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    }

    .bg-grad-5 {
      background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
    }

    .bg-grad-6 {
      background: linear-gradient(135deg, #a18cd1 0%, #fbc2eb 100%);
    }

    .td-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 999;
    }

    #tdSidebarToggle {
      display: none;
    }

    #tdSidebarToggle:checked~.td-overlay {
      display: block;
    }

    #tdSidebarToggle:checked~.td-sidebar {
      transform: translateX(0);
    }

    @media (max-width: 992px) {
      .td-sidebar {
        transform: translateX(-100%);
      }

      .td-main {
        margin-left: 0;
      }

      .td-burger {
        display: block;
      }

      #tdSidebarToggle:checked~.td-sidebar {
        transform: translateX(0);
      }

      .td-search {
        min-width: 150px;
      }
    }

    @media (max-width: 576px) {
      .td-navbar {
        padding: 10px 15px;
      }

      .td-content {
        padding: 15px;
      }

      .td-search {
        min-width: 100px;
        order: 10;
        width: 100%;
      }

      .stat-card {
        padding: 10px;
        gap: 10px;
      }

      .stat-icon {
        width: 36px;
        height: 36px;
        font-size: 18px;
      }

      .stat-card h3 {
        font-size: 1.2rem;
      }

      .chat-message {
        max-width: 85%;
      }
    }
  </style>
</head>

<body>
  <input type="checkbox" id="tdSidebarToggle">
  <div class="td-wrapper">
    <label for="tdSidebarToggle" class="td-overlay"></label>

    <aside class="td-sidebar">
      <div class="td-brand">
        <i class="bi bi-mortarboard-fill"></i>
        <span>Bright Future<small>School Portal</small></span>
      </div>
      <div class="td-teacher-box">
        <div class="td-avatar">
          <?php echo $teacher_initials; ?>
        </div>
        <div>
          <h6>
            <?php echo htmlspecialchars($teacher_name); ?>
          </h6>
          <p>Mathematics Teacher</p>
        </div>
      </div>
      <nav class="td-nav">
        <div class="td-nav-title">Main</div>
        <a href="index.php"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="students.php"><i class="bi bi-people"></i> My Students</a>
        <a href="attendance.php"><i class="bi bi-calendar2-check"></i> Attendance</a>
        <a href="subjects.php"><i class="bi bi-journal-bookmark"></i> My Subjects</a>
        <a href="timetable.php"><i class="bi bi-clock-history"></i> My Timetable</a>
        <div class="td-nav-title">Academics</div>
        <a href="assignments.php"><i class="bi bi-file-earmark-text"></i> Assignments</a>
        <a href="exams.php"><i class="bi bi-pencil-square"></i> Exams</a>
        <a href="results.php"><i class="bi bi-bar-chart-line"></i> Results</a>
        <div class="td-nav-title">Communication</div>
        <a href="notices.php"><i class="bi bi-megaphone"></i> Notices</a>
        <a href="messages.php" class="active"><i class="bi bi-chat-dots"></i> Messages</a>
        <div class="td-nav-title">Account</div>
        <a href="profile.php"><i class="bi bi-person-badge"></i> My Profile</a>
        <a href="settings.php"><i class="bi bi-gear"></i> Settings</a>
        <a href="#" class="logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
      </nav>
    </aside>

    <div class="td-main">
      <header class="td-navbar">
        <label for="tdSidebarToggle" class="td-burger"><i class="bi bi-list"></i></label>
        <h1 class="td-page-title">Messages <small>Inbox and conversations</small></h1>
        <div class="td-search ms-auto">
          <form method="GET" action="" class="d-flex">
            <div class="input-group">
              <span class="input-group-text bg-white border-end-0"><i class="bi bi-search"></i></span>
              <input type="search" name="search" class="form-control border-start-0" placeholder="Search messages..."
                value="<?php echo htmlspecialchars($search); ?>">
              <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
            </div>
          </form>
        </div>
        <a href="notices.php" class="td-icon-btn"><i class="bi bi-bell"></i><span class="td-dot"></span></a>
        <a href="profile.php" class="d-flex align-items-center gap-2 text-dark text-decoration-none">
          <span class="td-avatar">
            <?php echo $teacher_initials; ?>
          </span>
          <span class="d-none d-md-block">
            <strong class="d-block" style="font-size:.85rem">
              <?php echo htmlspecialchars($teacher_name); ?>
            </strong>
            <small class="text-muted" style="font-size:.72rem">Mathematics Teacher</small>
          </span>
        </a>
      </header>

      <main class="td-content">
        <?php if (isset($success_message)): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="bi bi-check-circle me-2"></i>
            <?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>
        <?php if (isset($error_message)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
          <div class="col-6 col-xl-3">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-1"><i class="bi bi-inbox"></i></div>
              <div>
                <h3>
                  <?php echo $stats['inbox'] ?? 0; ?>
                </h3>
                <p>Inbox</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-2"><i class="bi bi-send"></i></div>
              <div>
                <h3>
                  <?php echo $stats['sent'] ?? 0; ?>
                </h3>
                <p>Sent</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-3"><i class="bi bi-envelope-exclamation"></i></div>
              <div>
                <h3>
                  <?php echo $stats['unread'] ?? 0; ?>
                </h3>
                <p>Unread</p>
              </div>
            </div>
          </div>
          <div class="col-6 col-xl-3">
            <div class="card stat-card">
              <div class="stat-icon bg-grad-5"><i class="bi bi-chat-dots"></i></div>
              <div>
                <h3>
                  <?php echo count($messages); ?>
                </h3>
                <p>Total Messages</p>
              </div>
            </div>
          </div>
        </div>

        <ul class="nav nav-pills msg-tabs mb-3 gap-2">
          <li class="nav-item">
            <a class="nav-link <?php echo $tab == 'inbox' ? 'active' : 'bg-white'; ?>" href="?tab=inbox">
              <i class="bi bi-inbox me-1"></i> Inbox
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo $tab == 'sent' ? 'active' : 'bg-white'; ?>" href="?tab=sent">
              <i class="bi bi-send me-1"></i> Sent
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link <?php echo $tab == 'unread' ? 'active' : 'bg-white'; ?>" href="?tab=unread">
              <i class="bi bi-envelope-exclamation me-1"></i> Unread
            </a>
          </li>
          <li class="nav-item ms-auto">
            <button class="nav-link bg-success text-white" data-bs-toggle="modal" data-bs-target="#composeModal">
              <i class="bi bi-pencil-square me-1"></i> Compose
            </button>
          </li>
        </ul>

        <?php if ($conversation_id > 0 && $conversation_participant): ?>
          <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span>
                <i class="bi bi-chat-dots me-2 text-primary"></i>
                Chat with
                <?php echo htmlspecialchars($conversation_participant['first_name'] . ' ' . $conversation_participant['last_name']); ?>
                <span class="badge bg-secondary ms-2">
                  <?php echo $conversation_participant['type']; ?>
                </span>
              </span>
              <a href="?tab=<?php echo $tab; ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
              </a>
            </div>
            <div class="card-body">
              <div class="chat-container">
                <div class="chat-messages" id="chatMessages">
                  <?php if (count($conversation_messages) > 0): ?>
                    <?php foreach ($conversation_messages as $msg): ?>
                      <div class="chat-message <?php echo $msg['is_from_teacher'] ? 'sent' : 'received'; ?>">
                        <div class="message-bubble">
                          <?php if (!$msg['is_from_teacher']): ?>
                            <div class="message-sender">
                              <?php echo htmlspecialchars($msg['sender_display_name']); ?>
                            </div>
                          <?php endif; ?>
                          <?php echo nl2br(htmlspecialchars($msg['content'])); ?>
                          <span class="message-time">
                            <?php echo timeAgo($msg['created_at']); ?>
                          </span>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <div class="text-center text-muted py-5">
                      <i class="bi bi-chat fs-1 d-block mb-3"></i>
                      <p>No messages yet. Start a conversation!</p>
                    </div>
                  <?php endif; ?>
                </div>
                <div class="chat-input mt-3">
                  <form method="POST" action="" id="chatForm">
                    <input type="hidden" name="recipient_type" value="<?php echo $conversation_participant['type']; ?>">
                    <input type="hidden" name="recipient_id" value="<?php echo $conversation_participant['id']; ?>">
                    <input type="hidden" name="subject" value="Chat Message">
                    <div class="input-group">
                      <input type="text" name="content" class="form-control" placeholder="Type your message..." required>
                      <button type="submit" name="send_message" class="btn btn-primary">
                        <i class="bi bi-send"></i> Send
                      </button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span>
                <i
                  class="bi bi-<?php echo $tab == 'inbox' ? 'inbox' : ($tab == 'sent' ? 'send' : 'envelope-exclamation'); ?> me-2 text-primary"></i>
                <?php echo ucfirst($tab); ?>
              </span>
              <span class="badge bg-light text-dark">
                <?php echo count($messages); ?> messages
              </span>
            </div>
            <div class="list-group list-group-flush">
              <?php if (count($messages) > 0): ?>
                <?php foreach ($messages as $msg):
                  $is_from_teacher = ($msg['sender_type'] == 'Teacher' && $msg['sender_id'] == $teacher_db_id);
                  $is_unread = (!$is_from_teacher && $msg['is_read'] == 0);
                  $sender_display = $tab == 'sent' ? 'To: ' . $msg['recipient_name'] : $msg['sender_name'];
                  ?>
                  <a href="?tab=<?php echo $tab; ?>&conversation=<?php echo $is_from_teacher ? $msg['recipient_id'] : $msg['sender_id']; ?>"
                    class="list-group-item list-group-item-action msg-item <?php echo $is_unread ? 'unread' : ''; ?>">
                    <div class="d-flex justify-content-between align-items-center">
                      <div class="d-flex align-items-center gap-3 flex-grow-1">
                        <div class="msg-sender">
                          <i class="bi bi-person-circle me-1"></i>
                          <?php echo htmlspecialchars($sender_display); ?>
                        </div>
                        <div class="msg-subject flex-grow-1">
                          <?php echo htmlspecialchars($msg['subject'] ?? 'No Subject'); ?>
                        </div>
                        <div class="msg-preview d-none d-md-block text-muted small">
                          <?php echo htmlspecialchars(substr($msg['content'], 0, 60)) . (strlen($msg['content']) > 60 ? '...' : ''); ?>
                        </div>
                      </div>
                      <div class="d-flex align-items-center gap-2">
                        <span class="msg-time small text-muted">
                          <?php echo timeAgo($msg['created_at']); ?>
                        </span>
                        <?php if ($tab == 'inbox'): ?>
                          <?php if ($is_unread): ?>
                            <span class="badge bg-danger">Unread</span>
                          <?php else: ?>
                            <span class="badge bg-success">Read</span>
                          <?php endif; ?>
                        <?php else: ?>
                          <span class="badge bg-primary">Sent</span>
                        <?php endif; ?>
                      </div>
                    </div>
                  </a>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="text-center py-5">
                  <i class="bi bi-inbox fs-1 d-block text-muted mb-3"></i>
                  <h5>No messages in
                    <?php echo $tab; ?>
                  </h5>
                  <p class="text-muted">Your
                    <?php echo $tab; ?> folder is empty.
                  </p>
                  <?php if ($tab == 'inbox'): ?>
                    <button class="btn btn-primary mt-2" data-bs-toggle="modal" data-bs-target="#composeModal">
                      <i class="bi bi-pencil-square me-1"></i> Compose Message
                    </button>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        <?php endif; ?>
      </main>

      <footer class="td-footer">© 2026 Bright Future School — Teacher Panel.</footer>
    </div>
  </div>

  <!-- Compose Message Modal -->
  <div class="modal fade" id="composeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil-square me-2 text-primary"></i>Compose Message</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" action="">
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-12">
                <label class="form-label">Recipient Type <span class="text-danger">*</span></label>
                <select name="recipient_type" class="form-select" id="recipientType" required>
                  <option value="">Select Recipient Type</option>
                  <option value="Student">Student</option>
                  <option value="Parent">Parent</option>
                  <option value="Admin">Admin</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Recipient <span class="text-danger">*</span></label>
                <select name="recipient_id" class="form-select" id="recipientId" required>
                  <option value="">Select Recipient</option>
                  <?php foreach ($contacts as $contact): ?>
                    <option value="<?php echo $contact['id']; ?>" data-type="<?php echo $contact['type']; ?>">
                      <?php echo htmlspecialchars($contact['type'] . ': ' . $contact['first_name'] . ' ' . ($contact['last_name'] ?? '')); ?>
                      <?php if ($contact['class_name']): ?>
                        (
                        <?php echo htmlspecialchars($contact['class_name']); ?>)
                      <?php endif; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Subject</label>
                <input type="text" name="subject" class="form-control" placeholder="Enter subject (optional)">
              </div>
              <div class="col-12">
                <label class="form-label">Message <span class="text-danger">*</span></label>
                <textarea name="content" class="form-control" rows="5" placeholder="Type your message here..."
                  required></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="send_message" class="btn btn-primary">
              <i class="bi bi-send"></i> Send Message
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    // Filter recipients based on type selection
    document.getElementById('recipientType')?.addEventListener('change', function () {
      const type = this.value;
      const recipientSelect = document.getElementById('recipientId');
      const options = recipientSelect.querySelectorAll('option');

      options.forEach(option => {
        if (option.value === '') {
          option.style.display = '';
          return;
        }
        const optionType = option.getAttribute('data-type');
        if (type === '' || optionType === type) {
          option.style.display = '';
        } else {
          option.style.display = 'none';
        }
      });

      recipientSelect.value = '';
    });

    // Scroll chat to bottom
    function scrollChatToBottom() {
      const chatMessages = document.getElementById('chatMessages');
      if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
      }
    }

    document.addEventListener('DOMContentLoaded', function () {
      scrollChatToBottom();
    });

    // Auto-refresh chat every 30 seconds
    let chatRefreshInterval = null;

    function startChatRefresh() {
      if (chatRefreshInterval) {
        clearInterval(chatRefreshInterval);
      }

      if (document.querySelector('.chat-container')) {
        chatRefreshInterval = setInterval(function () {
          const url = new URL(window.location.href);
          const conversationId = url.searchParams.get('conversation');
          if (conversationId) {
            location.reload();
          }
        }, 30000);
      }
    }

    if (document.querySelector('.chat-container')) {
      startChatRefresh();
    }
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>