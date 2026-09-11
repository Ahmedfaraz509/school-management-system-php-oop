<?php
require_once '../database/connect.php';

class EventsProcess
{
  private $conn;
  public $id;
  public $event_name;
  public $status;
  public $event_date;
  public $event_time;
  public $location;
  public $organizer;
  public $description;
  public $created_at;

  // Constructor with database connection
  public function __construct($conn)
  {
    $this->conn = $conn;
  }

  // Set event data
  public function setData($id, $event_name, $status, $event_date, $event_time, $location, $organizer, $description, $created_at)
  {
    $this->id = $id;
    $this->event_name = $event_name;
    $this->status = $status;
    $this->event_date = $event_date;
    $this->event_time = $event_time;
    $this->location = $location;
    $this->organizer = $organizer;
    $this->description = $description;
    $this->created_at = $created_at;
  }

  // Insert event
  public function insert()
  {
    try {
      // Set default status if not provided
      if (empty($this->status)) {
        $this->status = 'Planning';
      }

      $insert_query = "INSERT INTO events (event_name, status, event_date, event_time, location, organizer, description, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

      $stmt = $this->conn->prepare($insert_query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([
        $this->event_name,
        $this->status,
        $this->event_date,
        $this->event_time,
        $this->location,
        $this->organizer,
        $this->description,
        $this->created_at
      ]);

      if (!$result) {
        error_log("Execute failed: " . print_r($stmt->errorInfo(), true));
        return false;
      }

      return $this->conn->lastInsertId();
    } catch (PDOException $e) {
      error_log("Insert event error: " . $e->getMessage());
      return false;
    }
  }

  // Update event
  public function update()
  {
    try {
      $update_query = "UPDATE events SET 
                            event_name = ?, 
                            status = ?, 
                            event_date = ?, 
                            event_time = ?, 
                            location = ?, 
                            organizer = ?, 
                            description = ? 
                            WHERE id = ?";

      $stmt = $this->conn->prepare($update_query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([
        $this->event_name,
        $this->status,
        $this->event_date,
        $this->event_time,
        $this->location,
        $this->organizer,
        $this->description,
        $this->id
      ]);

      if (!$result) {
        error_log("Execute failed: " . print_r($stmt->errorInfo(), true));
        return false;
      }

      return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Update event error: " . $e->getMessage());
      return false;
    }
  }

  // Delete event
  public function delete($id)
  {
    try {
      $delete_query = "DELETE FROM events WHERE id = ?";

      $stmt = $this->conn->prepare($delete_query);

      if (!$stmt) {
        error_log("Prepare failed: " . print_r($this->conn->errorInfo(), true));
        return false;
      }

      $result = $stmt->execute([$id]);

      if (!$result) {
        error_log("Execute failed: " . print_r($stmt->errorInfo(), true));
        return false;
      }

      return $result && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
      error_log("Delete event error: " . $e->getMessage());
      return false;
    }
  }

  // Get all events
  public function getAllEvents()
  {
    try {
      $query = "SELECT * FROM events ORDER BY event_date ASC, event_time ASC";
      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Fetch all events error: " . $e->getMessage());
      return [];
    }
  }

  // Get event by ID
  public function getEventById($id)
  {
    try {
      $query = "SELECT * FROM events WHERE id = ?";
      $stmt = $this->conn->prepare($query);
      $stmt->execute([$id]);
      return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Fetch event by ID error: " . $e->getMessage());
      return null;
    }
  }

  // Get events by status
  public function getEventsByStatus($status)
  {
    try {
      $query = "SELECT * FROM events WHERE status = ? ORDER BY event_date ASC";
      $stmt = $this->conn->prepare($query);
      $stmt->execute([$status]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Fetch events by status error: " . $e->getMessage());
      return [];
    }
  }

  // Get upcoming events
  public function getUpcomingEvents()
  {
    try {
      $query = "SELECT * FROM events WHERE event_date >= CURDATE() AND status = 'Confirmed' ORDER BY event_date ASC LIMIT 10";
      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Fetch upcoming events error: " . $e->getMessage());
      return [];
    }
  }

  // Count total events
  public function countEvents()
  {
    try {
      $query = "SELECT COUNT(*) as total FROM events";
      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      $result = $stmt->fetch(PDO::FETCH_ASSOC);
      return $result['total'] ?? 0;
    } catch (PDOException $e) {
      error_log("Count events error: " . $e->getMessage());
      return 0;
    }
  }

  // Get event statistics by status
  public function getEventStats()
  {
    try {
      $query = "SELECT 
                      COUNT(*) as total,
                      SUM(CASE WHEN status = 'Confirmed' THEN 1 ELSE 0 END) as confirmed,
                      SUM(CASE WHEN status = 'Planning' THEN 1 ELSE 0 END) as planning
                      FROM events";

      $stmt = $this->conn->prepare($query);
      $stmt->execute();
      return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Get event stats error: " . $e->getMessage());
      return ['total' => 0, 'confirmed' => 0, 'planning' => 0];
    }
  }

  // Search events
  public function searchEvents($keyword)
  {
    try {
      $search_query = "SELECT * FROM events 
                            WHERE event_name LIKE ? 
                            OR description LIKE ?
                            OR location LIKE ?
                            OR organizer LIKE ?
                            ORDER BY event_date ASC";

      $search_term = "%{$keyword}%";
      $stmt = $this->conn->prepare($search_query);
      $stmt->execute([$search_term, $search_term, $search_term, $search_term]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Search events error: " . $e->getMessage());
      return [];
    }
  }

  // Get events by month
  public function getEventsByMonth($month, $year)
  {
    try {
      $query = "SELECT * FROM events 
                      WHERE MONTH(event_date) = ? AND YEAR(event_date) = ? 
                      ORDER BY event_date ASC";

      $stmt = $this->conn->prepare($query);
      $stmt->execute([$month, $year]);
      return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
      error_log("Fetch events by month error: " . $e->getMessage());
      return [];
    }
  }
}
?>