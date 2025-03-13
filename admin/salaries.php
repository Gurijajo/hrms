<?php
require_once '../includes/functions.php';
checkLogin();
checkAdminAccess();

$database = new Database();
$db = $database->getConnection();

// ფორმების გაგზავნა
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_salary':
                $user_id = $_POST['user_id'];
                $fixed_salary = $_POST['fixed_salary'] ?: null;
                $expected_salary = $_POST['expected_salary'] ?: null;
                $hourly_rate = $_POST['hourly_rate'] ?: null;
                $effective_date = $_POST['effective_date'];
                
                $query = "INSERT INTO salaries 
                         (user_id, fixed_salary, expected_salary, hourly_rate, effective_date, created_by, updated_by) 
                         VALUES 
                         (:user_id, :fixed_salary, :expected_salary, :hourly_rate, :effective_date, :created_by, :updated_by)";
                         
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':fixed_salary' => $fixed_salary,
                    ':expected_salary' => $expected_salary,
                    ':hourly_rate' => $hourly_rate,
                    ':effective_date' => $effective_date,
                    ':created_by' => $_SESSION['user_id'],
                    ':updated_by' => $_SESSION['user_id']
                ]);
                break;

            case 'update_salary':
                $salary_id = $_POST['salary_id'];
                $fixed_salary = $_POST['fixed_salary'] ?: null;
                $expected_salary = $_POST['expected_salary'] ?: null;
                $hourly_rate = $_POST['hourly_rate'] ?: null;
                $effective_date = $_POST['effective_date'];
                
                $query = "UPDATE salaries SET 
                         fixed_salary = :fixed_salary,
                         expected_salary = :expected_salary,
                         hourly_rate = :hourly_rate,
                         effective_date = :effective_date,
                         updated_by = :updated_by,
                         updated_at = CURRENT_TIMESTAMP
                         WHERE salary_id = :salary_id";
                         
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':salary_id' => $salary_id,
                    ':fixed_salary' => $fixed_salary,
                    ':expected_salary' => $expected_salary,
                    ':hourly_rate' => $hourly_rate,
                    ':effective_date' => $effective_date,
                    ':updated_by' => $_SESSION['user_id']
                ]);
                break;
        }

        // აქტივობის ლოგირება
        $action_type = $_POST['action'] === 'add_salary' ? 'ADD_SALARY' : 'UPDATE_SALARY';
        $details = "ხელფასის ინფორმაცია " . ($_POST['action'] === 'add_salary' ? 'დაემატა' : 'განახლდა') . 
                  " მომხმარებლის ID: " . $_POST['user_id'];
        logActivity($db, $_SESSION['user_id'], $action_type, $details);

        header("Location: salaries.php");
        exit();
    }
}

// ყველა თანამშრომლის მიღება სანახავი ჩამოსაშლელი მენიუსთვის
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
    <title>ხელფასების მენეჯმენტი - Agroco HRMS</title>
    
</head>
<body>
    <div class="container">
        

        <h1>ხელფასების მენეჯმენტი</h1>

        <!-- ხელფასის ფორმა -->
        <div class="salary-card">
            <h2>ხელფასის ინფორმაციის დამატება/განახლება</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" id="formAction" value="add_salary">
                <input type="hidden" name="salary_id" id="salaryId">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="user_id">თანამშრომელი</label>
                        <select name="user_id" id="user_id" class="form-input" required>
                            <option value="">აირჩიეთ თანამშრომელი</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['user_id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . 
                                          ' (' . $employee['sector_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="fixed_salary">ფიქსირებული ხელფასი</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="fixed_salary" id="fixed_salary" 
                                   class="form-input" step="0.01" min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="expected_salary">მოლოდინის ხელფასი</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="expected_salary" id="expected_salary" 
                                   class="form-input" step="0.01" min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="hourly_rate">საათობრივი განაკვეთი</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="hourly_rate" id="hourly_rate" 
                                   class="form-input" step="0.01" min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="effective_date">მოქმედების თარიღი</label>
                        <input type="date" name="effective_date" id="effective_date" 
                               class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">ხელფასის ინფორმაციის შენახვა</button>
            </form>
        </div>

        <!-- ხელფასის ჩანაწერები -->
        <div class="salary-table-container">
            <h2>ხელფასის ისტორია</h2>
            <table class="salary-table">
                <thead>
                    <tr>
                        <th>თანამშრომელი</th>
                        <th>ფიქსირებული ხელფასი</th>
                        <th>მოლოდინის ხელფასი</th>
                        <th>საათობრივი განაკვეთი</th>
                        <th>მოქმედების თარიღი</th>
                        <th>ბოლო განახლება</th>
                        <th>მოქმედებები</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT s.*, 
                                    CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                                    r.role_name,
                                    sec.sector_name
                             FROM salaries s
                             JOIN users u ON s.user_id = u.user_id
                             JOIN roles r ON u.role_id = r.role_id
                             JOIN sectors sec ON u.sector_id = sec.sector_id
                             ORDER BY s.effective_date DESC, employee_name";
                    
                    $stmt = $db->query($query);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['employee_name']) . 
                             "<br><small>" . htmlspecialchars($row['role_name'] . ' - ' . $row['sector_name']) . "</small></td>";
                        echo "<td class='amount'>" . ($row['fixed_salary'] ? '$' . number_format($row['fixed_salary'], 2) : '-') . "</td>";
                        echo "<td class='amount'>" . ($row['expected_salary'] ? '$' . number_format($row['expected_salary'], 2) : '-') . "</td>";
                        echo "<td class='amount'>" . ($row['hourly_rate'] ? '$' . number_format($row['hourly_rate'], 2) : '-') . "</td>";
                        echo "<td>" . htmlspecialchars($row['effective_date']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['updated_at']) . "</td>";
                        echo "<td>";
                        echo "<button class='btn btn-primary' onclick='editSalary(" . json_encode($row) . ")'>რედაქტირება</button>";
                        echo "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    function editSalary(salary) {
        document.getElementById('formAction').value = 'update_salary';
        document.getElementById('salaryId').value = salary.salary_id;
        document.getElementById('user_id').value = salary.user_id;
        document.getElementById('fixed_salary').value = salary.fixed_salary;
        document.getElementById('expected_salary').value = salary.expected_salary;
        document.getElementById('hourly_rate').value = salary.hourly_rate;
        document.getElementById('effective_date').value = salary.effective_date;
        
        // გადახვევა ფორმაზე
        document.querySelector('.salary-card').scrollIntoView({ behavior: 'smooth' });
    }

    // დროის განახლება
    const dateTimeElement = document.getElementById('currentDateTime');
    function updateDateTime() {
        const now = new Date();
        const utcString = now.toISOString().slice(0, 19).replace('T', ' ');
        dateTimeElement.textContent = 'UTC: ' + utcString;
    }

    setInterval(updateDateTime, 1000);
    </script>
</body>
</html>
