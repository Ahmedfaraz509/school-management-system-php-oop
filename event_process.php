<?php
// event_process.php
// Process file for handling event-related database operations

require_once '../database/connect.php';

class EventProcess
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
   * Get all events
   */
  public function getAllEvents($limit = null)
  {
    try {
      $query = "SELECT e.*,
                      CASE 
                          WHEN e.status = 'Confirmed' THEN 'p-ok'
                          WHEN e.status = 'Planning' THEN 'p-warn'
                          ELSE 'p-grey'
                      END as status_color,
                      CASE 
                          WHEN e.status = 'Confirmed' THEN 'Confirmed'
                          WHEN e.status = 'Planning' THEN 'Planning'
                          ELSE 'Pending'
                      END as display_status,
                      DATE_FORMAT(e.event_date, '%d %b %Y') as formatted_date,
                      DATE_FORMAT(e.event_time, '%h:%i %p') as formatted_time,
                      CASE 
                          WHEN DATEDIFF(e.event_date, CURDATE()) = 0 THEN 'Today'
                          WHEN DATEDIFF(e.event_date, CURDATE()) = 1 THEN 'Tomorrow'
                          WHEN DATEDIFF(e.event_date, CURDATE()) < 7 THEN CONCAT(DATEDIFF(e.event_date, CURDATE()), ' days left')
                          WHEN DATEDIFF(e.event_date, CURDATE()) < 30 THEN CONCAT(FLOOR(DATEDIFF(e.event_date, CURDATE()) / 7), ' weeks left')
                          WHEN DATEDIFF(e.event_date, CURDATE()) < 0 THEN 'Past Event'
                          ELSE DATE_FORMAT(e.event_date, '%b %d, %Y')
                      END as days_left
                      FROM events e
                      ORDER BY e.event_date ASC";

      if ($limit) {
        $query .= " LIMIT " . intval($limit);
      }

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      $result = $stmt->fetchAll();
      return $result ? $result : [];

    } catch (PDOException $e) {
      error_log("Error fetching events: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get upcoming events
   */
  public function getUpcomingEvents($limit = null)
  {
    try {
      $query = "SELECT e.*,
                      CASE 
                          WHEN e.status = 'Confirmed' THEN 'p-ok'
                          WHEN e.status = 'Planning' THEN 'p-warn'
                          ELSE 'p-grey'
                      END as status_color,
                      CASE 
                          WHEN e.status = 'Confirmed' THEN 'Confirmed'
                          WHEN e.status = 'Planning' THEN 'Planning'
                          ELSE 'Pending'
                      END as display_status,
                      DATE_FORMAT(e.event_date, '%d %b %Y') as formatted_date,
                      DATE_FORMAT(e.event_time, '%h:%i %p') as formatted_time,
                      CASE 
                          WHEN DATEDIFF(e.event_date, CURDATE()) = 0 THEN 'Today'
                          WHEN DATEDIFF(e.event_date, CURDATE()) = 1 THEN 'Tomorrow'
                          WHEN DATEDIFF(e.event_date, CURDATE()) < 7 THEN CONCAT(DATEDIFF(e.event_date, CURDATE()), ' days left')
                          WHEN DATEDIFF(e.event_date, CURDATE()) < 30 THEN CONCAT(FLOOR(DATEDIFF(e.event_date, CURDATE()) / 7), ' weeks left')
                          ELSE DATE_FORMAT(e.event_date, '%b %d, %Y')
                      END as days_left
                      FROM events e
                      WHERE e.event_date >= CURDATE()
                      ORDER BY e.event_date ASC";

      if ($limit) {
        $query .= " LIMIT " . intval($limit);
      }

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetchAll();

    } catch (PDOException $e) {
      error_log("Error fetching upcoming events: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get past events
   */
  public function getPastEvents($limit = null)
  {
    try {
      $query = "SELECT e.*,
                      CASE 
                          WHEN e.status = 'Confirmed' THEN 'p-ok'
                          WHEN e.status = 'Planning' THEN 'p-warn'
                          ELSE 'p-grey'
                      END as status_color,
                      DATE_FORMAT(e.event_date, '%d %b %Y') as formatted_date,
                      DATE_FORMAT(e.event_time, '%h:%i %p') as formatted_time
                      FROM events e
                      WHERE e.event_date < CURDATE()
                      ORDER BY e.event_date DESC";

      if ($limit) {
        $query .= " LIMIT " . intval($limit);
      }

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetchAll();

    } catch (PDOException $e) {
      error_log("Error fetching past events: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get event by ID
   */
  public function getEventById($event_id)
  {
    try {
      $query = "SELECT e.*,
                      CASE 
                          WHEN e.status = 'Confirmed' THEN 'p-ok'
                          WHEN e.status = 'Planning' THEN 'p-warn'
                          ELSE 'p-grey'
                      END as status_color,
                      DATE_FORMAT(e.event_date, '%d %b %Y') as formatted_date,
                      DATE_FORMAT(e.event_time, '%h:%i %p') as formatted_time
                      FROM events e
                      WHERE e.id = ?";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$event_id]);
      return $stmt->fetch();

    } catch (PDOException $e) {
      error_log("Error fetching event by ID: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Get events by status
   */
  public function getEventsByStatus($status)
  {
    try {
      $query = "SELECT e.*,
                      CASE 
                          WHEN e.status = 'Confirmed' THEN 'p-ok'
                          WHEN e.status = 'Planning' THEN 'p-warn'
                          ELSE 'p-grey'
                      END as status_color,
                      DATE_FORMAT(e.event_date, '%d %b %Y') as formatted_date,
                      DATE_FORMAT(e.event_time, '%h:%i %p') as formatted_time
                      FROM events e
                      WHERE e.status = ?
                      ORDER BY e.event_date ASC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$status]);
      return $stmt->fetchAll();

    } catch (PDOException $e) {
      error_log("Error fetching events by status: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get events by date range
   */
  public function getEventsByDateRange($start_date, $end_date)
  {
    try {
      $query = "SELECT e.*,
                      CASE 
                          WHEN e.status = 'Confirmed' THEN 'p-ok'
                          WHEN e.status = 'Planning' THEN 'p-warn'
                          ELSE 'p-grey'
                      END as status_color,
                      DATE_FORMAT(e.event_date, '%d %b %Y') as formatted_date,
                      DATE_FORMAT(e.event_time, '%h:%i %p') as formatted_time
                      FROM events e
                      WHERE e.event_date BETWEEN ? AND ?
                      ORDER BY e.event_date ASC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$start_date, $end_date]);
      return $stmt->fetchAll();

    } catch (PDOException $e) {
      error_log("Error fetching events by date range: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get event statistics
   */
  public function getEventStats()
  {
    try {
      $query = "SELECT 
                        COUNT(*) as total,
                        SUM(CASE WHEN event_date >= CURDATE() THEN 1 ELSE 0 END) as upcoming,
                        SUM(CASE WHEN event_date < CURDATE() THEN 1 ELSE 0 END) as past,
                        SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
                        SUM(CASE WHEN status = 'Planning' THEN 1 ELSE 0 END) as planning
                        FROM events";

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      $result = $stmt->fetch();

      return [
        'total' => $result['total'] ?? 0,
        'upcoming' => $result['upcoming'] ?? 0,
        'past' => $result['past'] ?? 0,
        'confirmed' => $result['confirmed'] ?? 0,
        'planning' => $result['planning'] ?? 0
      ];

    } catch (PDOException $e) {
      error_log("Error fetching event stats: " . $e->getMessage());
      return [
        'total' => 0,
        'upcoming' => 0,
        'past' => 0,
        'confirmed' => 0,
        'planning' => 0
      ];
    }
  }

  /**
   * Add a new event
   */
  public function addEvent($data)
  {
    try {
      $query = "INSERT INTO events (event_name, status, event_date, event_time, location, organizer, description, created_at) 
                      VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

      $stmt = $this->conn->prepare($query);
      return $stmt->execute([
        $data['event_name'],
        $data['status'] ?? 'Planning',
        $data['event_date'],
        $data['event_time'],
        $data['location'],
        $data['organizer'],
        $data['description'] ?? null
      ]);
    } catch (PDOException $e) {
      error_log("Error adding event: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Update an event
   */
  public function updateEvent($event_id, $data)
  {
    try {
      $query = "UPDATE events 
                      SET event_name = ?, status = ?, event_date = ?, event_time = ?, 
                          location = ?, organizer = ?, description = ?
                      WHERE id = ?";

      $stmt = $this->conn->prepare($query);
      return $stmt->execute([
        $data['event_name'],
        $data['status'],
        $data['event_date'],
        $data['event_time'],
        $data['location'],
        $data['organizer'],
        $data['description'],
        $event_id
      ]);
    } catch (PDOException $e) {
      error_log("Error updating event: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Delete an event
   */
  public function deleteEvent($event_id)
  {
    try {
      $query = "DELETE FROM events WHERE id = ?";
      $stmt = $this->conn->prepare($query);
      return $stmt->execute([$event_id]);
    } catch (PDOException $e) {
      error_log("Error deleting event: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Update event status
   */
  public function updateEventStatus($event_id, $status)
  {
    try {
      $query = "UPDATE events SET status = ? WHERE id = ?";
      $stmt = $this->conn->prepare($query);
      return $stmt->execute([$status, $event_id]);
    } catch (PDOException $e) {
      error_log("Error updating event status: " . $e->getMessage());
      return false;
    }
  }

  /**
   * Get next upcoming event
   */
  public function getNextEvent()
  {
    try {
      $query = "SELECT e.*,
                      CASE 
                          WHEN e.status = 'Confirmed' THEN 'p-ok'
                          WHEN e.status = 'Planning' THEN 'p-warn'
                          ELSE 'p-grey'
                      END as status_color,
                      DATE_FORMAT(e.event_date, '%d %b %Y') as formatted_date,
                      DATE_FORMAT(e.event_time, '%h:%i %p') as formatted_time,
                      DATEDIFF(e.event_date, CURDATE()) as days_until
                      FROM events e
                      WHERE e.event_date >= CURDATE()
                      ORDER BY e.event_date ASC
                      LIMIT 1";

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetch();

    } catch (PDOException $e) {
      error_log("Error fetching next event: " . $e->getMessage());
      return null;
    }
  }

  /**
   * Get events for dashboard (limited)
   */
  public function getDashboardEvents($limit = 4)
  {
    return $this->getUpcomingEvents($limit);
  }

  /**
   * Get notification count
   */
  public function getEventNotificationCount()
  {
    $stats = $this->getEventStats();
    return $stats['upcoming'] ?? 0;
  }

  /**
   * Search events
   */
  public function searchEvents($keyword)
  {
    try {
      $query = "SELECT e.*,
                      CASE 
                          WHEN e.status = 'Confirmed' THEN 'p-ok'
                          WHEN e.status = 'Planning' THEN 'p-warn'
                          ELSE 'p-grey'
                      END as status_color,
                      DATE_FORMAT(e.event_date, '%d %b %Y') as formatted_date,
                      DATE_FORMAT(e.event_time, '%h:%i %p') as formatted_time
                      FROM events e
                      WHERE e.event_name LIKE ? 
                         OR e.description LIKE ? 
                         OR e.location LIKE ?
                         OR e.organizer LIKE ?
                      ORDER BY e.event_date ASC";

      $searchTerm = '%' . $keyword . '%';
      $stmt = $this->conn->prepare($query);
      $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
      return $stmt->fetchAll();

    } catch (PDOException $e) {
      error_log("Error searching events: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get events for a specific month
   */
  public function getEventsByMonth($month, $year)
  {
    try {
      $query = "SELECT e.*,
                      CASE 
                          WHEN e.status = 'Confirmed' THEN 'p-ok'
                          WHEN e.status = 'Planning' THEN 'p-warn'
                          ELSE 'p-grey'
                      END as status_color,
                      DATE_FORMAT(e.event_date, '%d %b %Y') as formatted_date,
                      DATE_FORMAT(e.event_time, '%h:%i %p') as formatted_time
                      FROM events e
                      WHERE MONTH(e.event_date) = ? AND YEAR(e.event_date) = ?
                      ORDER BY e.event_date ASC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$month, $year]);
      return $stmt->fetchAll();

    } catch (PDOException $e) {
      error_log("Error fetching events by month: " . $e->getMessage());
      return [];
    }
  }

  /**
   * Get event status color
   */
  public static function getStatusColor($status)
  {
    $colors = [
      'Confirmed' => 'p-ok',
      'Planning' => 'p-warn',
      'Completed' => 'p-grey',
      'Cancelled' => 'p-danger'
    ];
    return $colors[$status] ?? 'p-grey';
  }

  /**
   * Get status label
   */
  public static function getStatusLabel($status)
  {
    $labels = [
      'Confirmed' => 'Confirmed',
      'Planning' => 'Planning',
      'Completed' => 'Completed',
      'Cancelled' => 'Cancelled'
    ];
    return $labels[$status] ?? $status;
  }

  /**
   * Get event icon based on description
   */
  public static function getEventIcon($event_name)
  {
    $icons = [
      'Exhibition' => 'bi-lightbulb',
      'Exam' => 'bi-pencil-square',
      'Sports' => 'bi-trophy',
      'Cultural' => 'bi-mic',
      'Community' => 'bi-tree',
      'Competition' => 'bi-robot',
      'Seminar' => 'bi-people',
      'Workshop' => 'bi-tools',
      'Festival' => 'bi-gift',
      'Meeting' => 'bi-chat'
    ];

    foreach ($icons as $key => $icon) {
      if (stripos($event_name, $key) !== false) {
        return $icon;
      }
    }
    return 'bi-calendar2-heart';
  }

  /**
   * Get event color based on name
   */
  public static function getEventColor($event_name)
  {
    $colors = [
      'Exhibition' => 'teal',
      'Exam' => 'info',
      'Sports' => 'amber',
      'Cultural' => 'violet',
      'Community' => 'ok',
      'Competition' => 'danger',
      'Seminar' => 'info',
      'Workshop' => 'teal',
      'Festival' => 'ok',
      'Meeting' => 'violet'
    ];

    foreach ($colors as $key => $color) {
      if (stripos($event_name, $key) !== false) {
        return $color;
      }
    }
    return 'info';
  }
}

// Helper functions for use in templates
function getStatusColor($status)
{
  return EventProcess::getStatusColor($status);
}

function getStatusLabel($status)
{
  return EventProcess::getStatusLabel($status);
}

function getEventIcon($event_name)
{
  return EventProcess::getEventIcon($event_name);
}

function getEventColor($event_name)
{
  return EventProcess::getEventColor($event_name);
}

// Initialize the process for the current student
// Usage: $eventProcess = new EventProcess($conn, $student_id);
?>