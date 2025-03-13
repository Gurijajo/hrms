<?php
require_once '../includes/functions.php';
checkLogin();
checkAdminAccess();

$database = new Database();
$db = $database->getConnection();

// ფორმის გაგზავნის დამუშავება
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_attendance':
                $attendance_id = $_POST['attendance_id'];
                $status = $_POST['status'];
                $hours_worked = $_POST['hours_worked'] ?: null;
                $adjustment_hours = $_POST['adjustment_hours'] ?: 0;
                
                $query = "UPDATE attendance 
                         SET status = :status,
                             hours_worked = :hours_worked,
                             adjustment_hours = :adjustment_hours,
                             approved_by = :approved_by,
                             updated_at = CURRENT_TIMESTAMP
                         WHERE attendance_id = :attendance_id";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':status' => $status,
                    ':hours_worked' => $hours_worked,
                    ':adjustment_hours' => $adjustment_hours,
                    ':approved_by' => $_SESSION['user_id'],
                    ':attendance_id' => $attendance_id
                ]);
                break;

            case 'add_attendance':
                $user_id = $_POST['user_id'];
                $date = $_POST['date'];
                $status = $_POST['status'];
                $hours_worked = $_POST['hours_worked'] ?: null;
                
                $query = "INSERT INTO attendance 
                         (user_id, date, status, hours_worked, approved_by) 
                         VALUES 
                         (:user_id, :date, :status, :hours_worked, :approved_by)";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':date' => $date,
                    ':status' => $status,
                    ':hours_worked' => $hours_worked,
                    ':approved_by' => $_SESSION['user_id']
                ]);
                break;
        }
        header("Location: attendance.php");
        exit();
    }
}

// ცალკეული პერიოდის დასაწყისი და დასასრული
$start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-6 days'));
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// ყველა თანამშრომლის სიის მიღება
$employees = $db->query("SELECT u.user_id, u.first_name, u.last_name, r.role_name, s.sector_name 
                        FROM users u 
                        JOIN roles r ON u.role_id = r.role_id 
                        JOIN sectors s ON u.sector_id = s.sector_id 
                        WHERE u.status = 'active' 
                        ORDER BY s.sector_name, u.first_name, u.last_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>დასწრების მართვა - Agroco HRMS</title>
    <link rel="stylesheet" href="../assets/css/attendance.css">
</head>

<body>
    <div class="attandace-content">



        <div class="attendance-form">
            <h2>დასწრების ჩაწერა</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_attendance">
                
                <div class="form-group">
                    <label class="form-label" for="user_id">თანამშრომელი</label>
                    <select name="user_id" id="user_id" class="form-input" required>
                        <option value="">არჩიეთ თანამშრომელი</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?php echo $employee['user_id']; ?>">
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . 
                                      ' (' . $employee['sector_name'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="date">თარიღი</label>
                    <input type="date" name="date" id="date" class="form-input" 
                           value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="status">სტატუსი</label>
                    <select name="status" id="status" class="form-input" required>
                        <option value="present">დასწრებული</option>
                        <option value="absent">გასწორებული</option>
                        <option value="half-day">ნახევარი დღე</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="hours_worked">გატარებული საათები</label>
                    <input type="number" name="hours_worked" id="hours_worked" 
                           class="form-input" step="0.5" min="0" max="24">
                </div>

                <button type="submit" class="abtn btn-primary">დასწრების ჩაწერა</button>
            </form>
        </div>

        <!-- თარიღის ინტერვალის ფილტრი -->
        <div class="date-range-picker">
            <form method="GET" action="" class="filters">
                <div class="form-group">
                    <label class="form-label" for="start_date">დაწყების თარიღი</label>
                    <input type="date" name="start_date" id="start_date" 
                           class="form-input" value="<?php echo $start_date; ?>">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="end_date">დასრულების თარიღი</label>
                    <input type="date" name="end_date" id="end_date" 
                           class="form-input" value="<?php echo $end_date; ?>">
                </div>
                
                <button type="submit" class="btn btn-primary">ფილტრირება</button>
            </form>
        </div>

        <!-- დასწრების ჩანაწერები -->
        <div class="table-container">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>თარიღი</th>
                        <th>თანამშრომელი</th>
                        <th>სტატუსი</th>
                        <th>გატარებული საათები</th>
                        <th>დამთხვევა</th>
                        <th>განმტკიცებული</th>
                        <th>ქმედებები</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT a.*, 
                                    CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                                    s.sector_name,
                                    CONCAT(au.first_name, ' ', au.last_name) as approved_by_name
                             FROM attendance a
                             JOIN users u ON a.user_id = u.user_id
                             JOIN sectors s ON u.sector_id = s.sector_id
                             LEFT JOIN users au ON a.approved_by = au.user_id
                             WHERE a.date BETWEEN :start_date AND :end_date
                             ORDER BY a.date DESC, u.first_name, u.last_name";
                    
                    $stmt = $db->prepare($query);
                    $stmt->execute([':start_date' => $start_date, ':end_date' => $end_date]);
                    
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['date']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['employee_name']) . 
                             "<br><small>" . htmlspecialchars($row['sector_name']) . "</small></td>";
                        echo "<td><span class='status-badge status-" . $row['status'] . "'>" . 
                             htmlspecialchars($row['status']) . "</span></td>";
                        echo "<td>" . ($row['hours_worked'] ? htmlspecialchars($row['hours_worked']) : '-') . "</td>";
                        echo "<td>" . ($row['adjustment_hours'] ? sprintf('%+.1f', $row['adjustment_hours']) : '-') . "</td>";
                        echo "<td>" . ($row['approved_by_name'] ?? '-') . "</td>";
                        echo "<td>";
                        echo "<button class='btn btn-primary' onclick='editAttendance(" . json_encode($row) . ")'>რედაქტირება</button>";
                        echo "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    </div>

    <script>
    function updateDateTime() {
        const now = new Date();
        const utcString = now.toISOString().slice(0, 19).replace('T', ' ');
        document.getElementById('currentDateTime').textContent = 'UTC: ' + utcString;
    }

    setInterval(updateDateTime, 1000);
    updateDateTime();

    function editAttendance(attendance) {
        // დასწრების რედაქტირებისთვის შესრულება
        // შეგიძლიათ შექმნათ მოდალი ან ფორმა დასწრების ჩანაწერის რედაქტირებისთვის
    }
    </script>
</body>
</html>
