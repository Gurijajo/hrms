<?php
require_once '../includes/functions.php';
checkLogin();
checkAdminAccess();

$database = new Database();
$db = $database->getConnection();

// Status translations
$statusTranslations = [
    'present' => 'დასწრებული',
    'absent' => 'გაცდენილი',
    'half-day' => 'ნახევარი დღე'
];

// Error handling function
function handleError($message) {
    $_SESSION['error'] = $message;
    header("Location: attendance.php");
    exit();
}

// Form submission processing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'update_attendance':
                    // Validate inputs
                    $attendance_id = filter_input(INPUT_POST, 'attendance_id', FILTER_VALIDATE_INT);
                    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
                    $hours_worked = filter_input(INPUT_POST, 'hours_worked', FILTER_VALIDATE_FLOAT);
                    $adjustment_hours = filter_input(INPUT_POST, 'adjustment_hours', FILTER_VALIDATE_FLOAT) ?: 0;
                    
                    if (!$attendance_id || !in_array($status, array_keys($statusTranslations))) {
                        throw new Exception("არასწორი მონაცემები");
                    }

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
                    $_SESSION['success'] = "დასწრების ჩანაწერი წარმატებით განახლდა";
                    break;

                case 'add_attendance':
                    // Validate inputs
                    $user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
                    $date = filter_input(INPUT_POST, 'date', FILTER_SANITIZE_STRING);
                    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
                    $hours_worked = filter_input(INPUT_POST, 'hours_worked', FILTER_VALIDATE_FLOAT);
                    
                    if (!$user_id || !strtotime($date) || !in_array($status, array_keys($statusTranslations))) {
                        throw new Exception("არასწორი მონაცემები");
                    }

                    // Check for duplicate entry
                    $checkQuery = "SELECT COUNT(*) FROM attendance WHERE user_id = ? AND date = ?";
                    $checkStmt = $db->prepare($checkQuery);
                    $checkStmt->execute([$user_id, $date]);
                    if ($checkStmt->fetchColumn() > 0) {
                        throw new Exception("ამ თარიღში უკვე არსებობს ჩანაწერი");
                    }
                    
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
                    $_SESSION['success'] = "დასწრების ჩანაწერი წარმატებით დაემატა";
                    break;
            }
            header("Location: attendance.php");
            exit();
        } catch (Exception $e) {
            handleError($e->getMessage());
        }
    }
}

// Date range handling
$start_date = isset($_GET['start_date']) ? 
    sanitizeString($_GET['start_date']) : 
    date('Y-m-d', strtotime('-6 days'));
$end_date = isset($_GET['end_date']) ? 
    sanitizeString($_GET['end_date']) : 
    date('Y-m-d');

// Get all active employees
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
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="../assets/css/modal.css">
 
</head>

<body>

   
    <div class="attendance-content">
        <div class="container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo htmlspecialchars($_SESSION['success']); 
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo htmlspecialchars($_SESSION['error']); 
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>
            <h1>დასწრება</h1>
            <div class="attendance-form">
                <h2>დასწრების ჩაწერა</h2>
                <form method="POST" action="" id="addAttendanceForm">
                    <input type="hidden" name="action" value="add_attendance">
                    
                    <div class="form-group">
                        <label class="form-label" for="user_id">თანამშრომელი</label>
                        <select name="user_id" id="user_id" class="form-input" required>
                            <option value="">აირჩიეთ თანამშრომელი</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo htmlspecialchars($employee['user_id']); ?>">
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
                            <?php foreach ($statusTranslations as $value => $label): ?>
                                <option value="<?php echo htmlspecialchars($value); ?>">
                                    <?php echo htmlspecialchars($label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="hours_worked">გატარებული საათები</label>
                        <input type="number" name="hours_worked" id="hours_worked" 
                               class="form-input" step="0.5" min="0" max="24">
                    </div>

                    <button type="submit" class="btn btn-primary">დასწრების ჩაწერა</button>
                </form>
            </div>

            <div class="date-range-picker">
                <form method="GET" action="" class="filters">
                    <div class="form-group">
                        <label class="form-label" for="start_date">დაწყების თარიღი</label>
                        <input type="date" name="start_date" id="start_date" 
                               class="form-input" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="end_date">დასრულების თარიღი</label>
                        <input type="date" name="end_date" id="end_date" 
                               class="form-input" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">ფილტრირება</button>
                </form>
            </div>

            <div class="table-container">
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>თარიღი</th>
                            <th>თანამშრომელი</th>
                            <th>სტატუსი</th>
                            <th>გატარებული საათები</th>
                            <th>დამთხვევა</th>
                            <th>დამტკიცებული</th>
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
                                 htmlspecialchars($statusTranslations[$row['status']]) . "</span></td>";
                            echo "<td>" . ($row['hours_worked'] ? htmlspecialchars($row['hours_worked']) : '-') . "</td>";
                            echo "<td>" . ($row['adjustment_hours'] ? sprintf('%+.1f', $row['adjustment_hours']) : '-') . "</td>";
                            echo "<td>" . ($row['approved_by_name'] ?? '-') . "</td>";
                            echo "<td>";
                            echo "<button class='btn btn-primary' onclick='editAttendance(" . 
                                 htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8') . ")'>რედაქტირება</button>";
                            echo "</td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal for editing attendance -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>დასწრების რედაქტირება</h2>
            <form method="POST" action="" id="editAttendanceForm">
                <input type="hidden" name="action" value="update_attendance">
                <input type="hidden" name="attendance_id" id="edit_attendance_id">
                
                <div class="form-group">
                    <label class="form-label" for="edit_status">სტატუსი</label>
                    <select name="status" id="edit_status" class="form-input" required>
                        <?php foreach ($statusTranslations as $value => $label): ?>
                            <option value="<?php echo htmlspecialchars($value); ?>">
                                <?php echo htmlspecialchars($label); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_hours_worked">გატარებული საათები</label>
                    <input type="number" name="hours_worked" id="edit_hours_worked" 
                           class="form-input" step="0.5" min="0" max="24">
                </div>

                <div class="form-group">
                    <label class="form-label" for="edit_adjustment_hours">დამთხვევის საათები</label>
                    <input type="number" name="adjustment_hours" id="edit_adjustment_hours" 
                           class="form-input" step="0.5" min="-24" max="24" value="0">
                </div>

                <button type="submit" class="btn btn-primary">განახლება</button>
            </form>
        </div>
    </div>

    <script>
    // Current datetime display
    function updateDateTime() {
        const now = new Date();
        const options = {
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        };
        const formattedDate = now.toLocaleString('en-US', options)
            .replace(/(\d+)\/(\d+)\/(\d+)/, '$3-$1-$2');
        document.getElementById('currentDateTime').textContent = 'UTC: ' + formattedDate;
    }

    setInterval(updateDateTime, 1000);
    updateDateTime();

    // Modal functionality
    const modal = document.getElementById('editModal');
    const span = document.getElementsByClassName('close')[0];
    
    span.onclick = function() {
        modal.style.display = "none";
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }

    // Edit attendance function
    function editAttendance(attendance) {
        document.getElementById('edit_attendance_id').value = attendance.attendance_id;
        document.getElementById('edit_status').value = attendance.status;
        document.getElementById('edit_hours_worked').value = attendance.hours_worked || '';
        document.getElementById('edit_adjustment_hours').value = attendance.adjustment_hours || '0';
        
        // Display modal
        modal.style.display = "block";
    }

    // Form validation
    document.getElementById('addAttendanceForm').onsubmit = validateForm;
    document.getElementById('editAttendanceForm').onsubmit = validateForm;

    function validateForm(e) {
        const form = e.target;
        const status = form.querySelector('[name="status"]').value;
        const hoursWorked = form.querySelector('[name="hours_worked"]').value;

        if (status === 'present' && !hoursWorked) {
            alert('გთხოვთ შეიყვანოთ გატარებული საათები დასწრებული სტატუსისთვის');
            e.preventDefault();
            return false;
        }

        if (hoursWorked && (hoursWorked < 0 || hoursWorked > 24)) {
            alert('გატარებული საათები უნდა იყოს 0-დან 24-მდე');
            e.preventDefault();
            return false;
        }

        return true;
    }

    // Status dependent fields
    function updateHoursField(statusSelect) {
        const hoursField = statusSelect.form.querySelector('[name="hours_worked"]');
        if (statusSelect.value === 'present') {
            hoursField.required = true;
        } else {
            hoursField.required = false;
        }
    }

    // Add event listeners for status fields
    document.querySelectorAll('select[name="status"]').forEach(select => {
        select.addEventListener('change', (e) => updateHoursField(e.target));
        // Initialize on page load
        updateHoursField(select);
    });

    // Date range validation
    document.querySelector('.filters').addEventListener('submit', function(e) {
        const startDate = new Date(document.getElementById('start_date').value);
        const endDate = new Date(document.getElementById('end_date').value);
        
        if (endDate < startDate) {
            alert('დასრულების თარიღი ვერ იქნება დაწყების თარიღზე ადრე');
            e.preventDefault();
            return false;
        }

        const maxRange = new Date(startDate);
        maxRange.setDate(maxRange.getDate() + 31); // Max 31 days range

        if (endDate > maxRange) {
            alert('თარიღების დიაპაზონი არ უნდა აღემატებოდეს 31 დღეს');
            e.preventDefault();
            return false;
        }
    });

    // Initialize tooltips for status badges
    const statusBadges = document.querySelectorAll('.status-badge');
    statusBadges.forEach(badge => {
        badge.title = badge.textContent;
    });

    // Current user info
    const currentUser = '<?php echo htmlspecialchars($_SESSION['username']); ?>';
    const currentDateTime = '<?php echo date("Y-m-d H:i:s"); ?>';
    console.log(`Logged in as: ${currentUser}`);
    console.log(`Current time: ${currentDateTime}`);
    </script>

    
</body>
</html>