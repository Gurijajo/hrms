<?php
require_once '../includes/functions.php';
checkLogin();
checkAdminAccess();

$database = new Database();
$db = $database->getConnection();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_schedule':
                $sector_id = $_POST['sector_id'];
                $schedule_name = sanitizeInput($_POST['schedule_name']);
                $start_time = $_POST['start_time'];
                $end_time = $_POST['end_time'];
                $break_start = $_POST['break_start'];
                $break_end = $_POST['break_end'];
                $working_days = implode(',', $_POST['working_days']);
                
                $query = "INSERT INTO sector_schedules 
                         (sector_id, schedule_name, start_time, end_time, break_start, break_end, 
                          working_days, created_by) 
                         VALUES 
                         (:sector_id, :name, :start, :end, :break_start, :break_end, 
                          :days, :created_by)";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':sector_id' => $sector_id,
                    ':name' => $schedule_name,
                    ':start' => $start_time,
                    ':end' => $end_time,
                    ':break_start' => $break_start,
                    ':break_end' => $break_end,
                    ':days' => $working_days,
                    ':created_by' => $_SESSION['user_id']
                ]);

                logActivity($db, $_SESSION['user_id'], 'CREATE_SCHEDULE', 
                          "Created new schedule for sector ID: $sector_id");
                break;

            case 'update_schedule':
                $schedule_id = $_POST['schedule_id'];
                $start_time = $_POST['start_time'];
                $end_time = $_POST['end_time'];
                $break_start = $_POST['break_start'];
                $break_end = $_POST['break_end'];
                $working_days = implode(',', $_POST['working_days']);
                
                $query = "UPDATE sector_schedules SET 
                         start_time = :start,
                         end_time = :end,
                         break_start = :break_start,
                         break_end = :break_end,
                         working_days = :days,
                         updated_by = :updated_by,
                         updated_at = CURRENT_TIMESTAMP
                         WHERE schedule_id = :id";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':start' => $start_time,
                    ':end' => $end_time,
                    ':break_start' => $break_start,
                    ':break_end' => $break_end,
                    ':days' => $working_days,
                    ':updated_by' => $_SESSION['user_id'],
                    ':id' => $schedule_id
                ]);

                logActivity($db, $_SESSION['user_id'], 'UPDATE_SCHEDULE', 
                          "Updated schedule ID: $schedule_id");
                break;
        }
        
        header("Location: work_schedules.php");
        exit();
    }
}

// Get all sectors
$sectors = $db->query("SELECT * FROM sectors ORDER BY sector_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Work Schedules Management - Agroco HRMS</title>
    <style>
        /* ... Previous CSS styles ... */
        .schedule-card {
            background: white;
            border-radius: var(--radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            margin-bottom: 1.5rem;
        }

        .schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
        }

        .time-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .days-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 0.5rem;
            margin: 1rem 0;
        }

        .day-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .time-display {
            font-family: 'Roboto Mono', monospace;
            background: var(--light);
            padding: 0.5rem;
            border-radius: var(--radius);
            text-align: center;
        }

        .active-schedule {
            border-left: 4px solid var(--success);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="system-header">
            <div class="datetime" id="currentDateTime">
                UTC: 2025-03-13 07:24:19
            </div>
            <div class="user-info">
                <span>User:</span>
                <strong>Gurijajo</strong>
            </div>
        </div>

        <h1 class="page-title">Work Schedules Management</h1>

        <!-- Create New Schedule -->
        <div class="schedule-card">
            <h2>Create New Schedule</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_schedule">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Sector</label>
                        <select name="sector_id" class="form-input" required>
                            <?php foreach ($sectors as $sector): ?>
                                <option value="<?php echo $sector['sector_id']; ?>">
                                    <?php echo htmlspecialchars($sector['sector_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Schedule Name</label>
                        <input type="text" name="schedule_name" class="form-input" required>
                    </div>
                </div>

                <div class="time-grid">
                    <div class="form-group">
                        <label class="form-label">Start Time</label>
                        <input type="time" name="start_time" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">End Time</label>
                        <input type="time" name="end_time" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Break Start</label>
                        <input type="time" name="break_start" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Break End</label>
                        <input type="time" name="break_end" class="form-input" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Working Days</label>
                    <div class="days-grid">
                        <?php
                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                        foreach ($days as $day):
                        ?>
                        <div class="day-checkbox">
                            <input type="checkbox" name="working_days[]" value="<?php echo $day; ?>" 
                                   <?php echo in_array($day, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday']) ? 'checked' : ''; ?>>
                            <label><?php echo $day; ?></label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Create Schedule</button>
            </form>
        </div>

        <!-- Existing Schedules -->
        <h2>Current Schedules</h2>
        <?php
        $query = "SELECT ss.*, s.sector_name,
                         COUNT(u.user_id) as employee_count
                  FROM sector_schedules ss
                  JOIN sectors s ON ss.sector_id = s.sector_id
                  LEFT JOIN users u ON s.sector_id = u.sector_id
                  GROUP BY ss.schedule_id
                  ORDER BY s.sector_name, ss.created_at DESC";
        
        $schedules = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($schedules as $schedule):
        ?>
        <div class="schedule-card <?php echo $schedule['is_active'] ? 'active-schedule' : ''; ?>">
            <div class="schedule-header">
                <h3><?php echo htmlspecialchars($schedule['schedule_name']); ?></h3>
                <span><?php echo htmlspecialchars($schedule['sector_name']); ?></span>
            </div>

            <div class="time-grid">
                <div class="time-display">
                    <strong>Work Hours</strong><br>
                    <?php echo date('h:i A', strtotime($schedule['start_time'])); ?> - 
                    <?php echo date('h:i A', strtotime($schedule['end_time'])); ?>
                </div>

                <div class="time-display">
                    <strong>Break Time</strong><br>
                    <?php echo date('h:i A', strtotime($schedule['break_start'])); ?> - 
                    <?php echo date('h:i A', strtotime($schedule['break_end'])); ?>
                </div>

                <div class="time-display">
                    <strong>Working Days</strong><br>
                    <?php echo str_replace(',', ', ', $schedule['working_days']); ?>
                </div>

                <div class="time-display">
                    <strong>Employees</strong><br>
                    <?php echo $schedule['employee_count']; ?> assigned
                </div>
            </div>

            <div class="actions" style="margin-top: 1rem;">
                <button class="btn btn-primary" onclick="editSchedule(<?php echo htmlspecialchars(json_encode($schedule)); ?>)">
                    Edit Schedule
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
    function updateDateTime() {
        document.getElementById('currentDateTime').textContent = 'UTC: 2025-03-13 07:24:19';
    }

    function editSchedule(schedule) {
        // Implementation for editing schedule
        // You can create a modal or form to edit the schedule
    }

    // Initialize datetime
    updateDateTime();
    </script>
</body>
</html>