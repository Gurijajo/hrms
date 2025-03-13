<?php
require_once '../includes/functions.php';
checkLogin();
checkAdminAccess();

$database = new Database();
$db = $database->getConnection();

// აქტივობის ლოგირების ფუნქცია
// function logActivity($db, $user_id, $action, $details) {
//     $query = "INSERT INTO activity_logs (user_id, action_type, action_details, ip_address) 
//               VALUES (:user_id, :action, :details, :ip)";
//     $stmt = $db->prepare($query);
//     $stmt->execute([
//         ':user_id' => $user_id,
//         ':action' => $action,
//         ':details' => $details,
//         ':ip' => $_SERVER['REMOTE_ADDR']
//     ]);
// }

// ფორმების გაგზავნის დამუშავება
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'approve_workday':
                $attendance_id = $_POST['attendance_id'];
                $sector_id = $_POST['sector_id'];
                
                // სექტის სამუშაო საათების მიღება
                $stmt = $db->prepare("SELECT working_hours FROM sector_schedules WHERE sector_id = ?");
                $stmt->execute([$sector_id]);
                $sector_hours = $stmt->fetch(PDO::FETCH_COLUMN);
                
                $query = "UPDATE attendance SET 
                         status = 'present',
                         hours_worked = :hours,
                         approved_by = :approved_by,
                         approval_time = CURRENT_TIMESTAMP
                         WHERE attendance_id = :attendance_id";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':hours' => $sector_hours,
                    ':approved_by' => $_SESSION['user_id'],
                    ':attendance_id' => $attendance_id
                ]);
                
                logActivity($db, $_SESSION['user_id'], 'ATTENDANCE_APPROVE', 
                          "სრული სამუშაო დღე დამტკიცებულია, ჩანაწერი ID: $attendance_id");
                break;

            case 'adjust_hours':
                $attendance_id = $_POST['attendance_id'];
                $adjustment = $_POST['adjustment_hours'];
                $reason = $_POST['adjustment_reason'];
                
                $query = "UPDATE attendance SET 
                         adjustment_hours = :adjustment,
                         adjustment_reason = :reason,
                         adjusted_by = :adjusted_by,
                         adjustment_time = CURRENT_TIMESTAMP
                         WHERE attendance_id = :attendance_id";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':adjustment' => $adjustment,
                    ':reason' => $reason,
                    ':adjusted_by' => $_SESSION['user_id'],
                    ':attendance_id' => $attendance_id
                ]);
                
                logActivity($db, $_SESSION['user_id'], 'HOURS_ADJUST', 
                          "სამუშაო საათები შეიცვალა $adjustment-ით, ჩანაწერი ID: $attendance_id");
                break;

            case 'mark_absent':
                $user_id = $_POST['user_id'];
                $date = $_POST['date'];
                $reason = $_POST['absence_reason'];
                
                $query = "INSERT INTO attendance (user_id, date, status, absence_reason, marked_by) 
                         VALUES (:user_id, :date, 'absent', :reason, :marked_by)";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':date' => $date,
                    ':reason' => $reason,
                    ':marked_by' => $_SESSION['user_id']
                ]);
                
                logActivity($db, $_SESSION['user_id'], 'MARK_ABSENT', 
                          "მომხმარებელი ID: $user_id, თარიღი: $date - მიუთითეს 'არა ყოფნა'");
                break;
        }
        
        header("Location: manage_attendance.php");
        exit();
    }
}

// თანამშრომლების მიღება ჩამdropdown-სთვის
$employees = $db->query("SELECT u.user_id, u.first_name, u.last_name, r.role_name, s.sector_name 
                        FROM users u 
                        JOIN roles r ON u.role_id = r.role_id 
                        JOIN sectors s ON u.sector_id = s.sector_id 
                        WHERE u.status = 'active' 
                        ORDER BY s.sector_name, u.first_name, u.last_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>მ उपस्थितობის მენეჯმენტი - Agroco HRMS</title>
    <style>
    /* ... წინა CSS სტილები ... */

    .action-panel {
        background: white;
        border-radius: var(--radius);
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        box-shadow: var(--shadow);
    }

    .action-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 1.5rem;
    }

    .reason-input {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--border);
        border-radius: var(--radius);
        margin-top: 0.5rem;
    }

    .approval-history {
        font-size: 0.875rem;
        color: var(--text-light);
        margin-top: 0.5rem;
    }

    .adjustment-controls {
        display: flex;
        gap: 1rem;
        align-items: center;
    }

    .hours-input {
        width: 80px;
        text-align: center;
    }
    </style>
</head>
<body>
    <div class="container">
        <!-- სისტემის სათაური: მიმდინარე დრო და მომხმარებელი -->
        <div class="system-header">
            <div class="datetime" id="currentDateTime">
                UTC: 2025-03-13 07:14:26
            </div>
            <div class="user-info">
                <span>მომხმარებელი:</span>
                <strong>Gurijajo</strong>
            </div>
        </div>

        <h1>დასწრების მენეჯმენტი</h1>

        <!-- სწრაფი მოქმედებების პანელი -->
        <div class="action-panel">
            <h2>სწრაფი მოქმედებები</h2>
            <div class="action-grid">
                <!-- სრული სამუშაო დღის დამტკიცება -->
                <div class="action-card">
                    <h3>სრული სამუშაო დღის დამტკიცება</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="approve_workday">
                        <div class="form-group">
                            <label>აირჩიეთ თანამშრომელი</label>
                            <select name="user_id" class="form-input" required>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['user_id']; ?>">
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . 
                                              $employee['last_name'] . ' (' . $employee['sector_name'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>თარიღი</label>
                            <input type="date" name="date" class="form-input" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-success">სრული დღის დამტკიცება</button>
                    </form>
                </div>

                <!-- სამუშაო საათების ცვლილება -->
                <div class="action-card">
                    <h3>სამუშაო საათების ცვლილება</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="adjust_hours">
                        <div class="form-group">
                            <label>აირჩიეთ ჩანაწერი</label>
                            <select name="attendance_id" class="form-input" required>
                                <?php
                                $records = $db->query("SELECT a.attendance_id, a.date, 
                                                            CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                                                            s.sector_name
                                                     FROM attendance a
                                                     JOIN users u ON a.user_id = u.user_id
                                                     JOIN sectors s ON u.sector_id = s.sector_id
                                                     WHERE a.date = CURRENT_DATE
                                                     ORDER BY employee_name")->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($records as $record):
                                ?>
                                <option value="<?php echo $record['attendance_id']; ?>">
                                    <?php echo htmlspecialchars($record['employee_name'] . 
                                          ' - ' . $record['date'] . ' (' . $record['sector_name'] . ')'); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>შეცვლა (საათი)</label>
                            <div class="adjustment-controls">
                                <button type="button" onclick="adjustHours(-1)">-1</button>
                                <input type="number" name="adjustment_hours" id="adjustmentHours" 
                                       class="form-input hours-input" step="0.5" value="0" required>
                                <button type="button" onclick="adjustHours(1)">+1</button>
                            </div>
                        </div>
                        <div class="form-group">
                            <label>შეცვლის მიზეზი</label>
                            <textarea name="adjustment_reason" class="reason-input" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary">შეცვლის განხორციელება</button>
                    </form>
                </div>

                <!-- არა ყოფნის მითითება -->
                <div class="action-card">
                    <h3>არა ყოფნის მითითება</h3>
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="mark_absent">
                        <div class="form-group">
                            <label>აირჩიეთ თანამშრომელი</label>
                            <select name="user_id" class="form-input" required>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee['user_id']; ?>">
                                        <?php echo htmlspecialchars($employee['first_name'] . ' ' . 
                                              $employee['last_name'] . ' (' . $employee['sector_name'] . ')'); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>თარიღი</label>
                            <input type="date" name="date" class="form-input" 
                                   value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="form-group">
                            <label>არა ყოფნის მიზეზი</label>
                            <textarea name="absence_reason" class="reason-input" required></textarea>
                        </div>
                        <button type="submit" class="btn btn-danger">არა ყოფნის მითითება</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ბოლო m არსებული აქტივობა -->
        <div class="activity-log">
            <h2>ბოლო მ उपस्थितობის აქტივობა</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>თარიღი/დრო</th>
                        <th>თანამშრომელი</th>
                        <th>მოქმედება</th>
                        <th>განახლებული პირი</th>
                        <th>დეტალები</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT al.*, 
                                    CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                                    CONCAT(m.first_name, ' ', m.last_name) as modifier_name
                             FROM activity_logs al
                             JOIN users u ON al.user_id = u.user_id
                             JOIN users m ON al.modified_by = m.user_id
                             WHERE al.action_type IN ('ATTENDANCE_APPROVE', 'HOURS_ADJUST', 'MARK_ABSENT')
                             ORDER BY al.created_at DESC
                             LIMIT 10";
                    
                    $logs = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($logs as $log):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($log['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($log['employee_name']); ?></td>
                        <td><?php echo htmlspecialchars($log['action_type']); ?></td>
                        <td><?php echo htmlspecialchars($log['modifier_name']); ?></td>
                        <td><?php echo htmlspecialchars($log['action_details']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    // დროის გამოჩენის განახლება
    function updateDateTime() {
        const dateTimeElement = document.getElementById('currentDateTime');
        dateTimeElement.textContent = 'UTC: 2025-03-13 07:14:26';
    }

    // საათების ცვლილების ფუნქცია
    function adjustHours(value) {
        const input = document.getElementById('adjustmentHours');
        let newValue = parseFloat(input.value) + value;
        input.value = Math.max(-12, Math.min(12, newValue));
    }

    // დროის ინიციალიზაცია
    updateDateTime();
    </script>
</body>
</html>
