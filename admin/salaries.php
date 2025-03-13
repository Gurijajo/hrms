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
            case 'add_salary':
                $user_id = $_POST['user_id'];
                $fixed_salary = $_POST['fixed_salary'] ?: null;
                $expected_salary = $_POST['expected_salary'] ?: null;
                $hourly_rate = $_POST['hourly_rate'] ?: null;
                $effective_date = $_POST['effective_date'];
                
                $query = "INSERT INTO salaries 
                         (user_id, fixed_salary, expected_salary, hourly_rate, effective_date) 
                         VALUES 
                         (:user_id, :fixed_salary, :expected_salary, :hourly_rate, :effective_date)";
                         
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':fixed_salary' => $fixed_salary,
                    ':expected_salary' => $expected_salary,
                    ':hourly_rate' => $hourly_rate,
                    ':effective_date' => $effective_date
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
                         effective_date = :effective_date
                         WHERE salary_id = :salary_id";
                         
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':salary_id' => $salary_id,
                    ':fixed_salary' => $fixed_salary,
                    ':expected_salary' => $expected_salary,
                    ':hourly_rate' => $hourly_rate,
                    ':effective_date' => $effective_date
                ]);
                break;
        }
        header("Location: salaries.php");
        exit();
    }
}

// Get all employees for dropdown
$employees = $db->query("SELECT u.user_id, u.first_name, u.last_name, r.role_name, s.sector_name 
                        FROM users u 
                        JOIN roles r ON u.role_id = r.role_id 
                        JOIN sectors s ON u.sector_id = s.sector_id 
                        WHERE u.status = 'active' 
                        ORDER BY s.sector_name, u.first_name, u.last_name")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Management - Agroco HRMS</title>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #34495e;
            --accent: #3498db;
            --success: #27ae60;
            --warning: #f1c40f;
            --danger: #e74c3c;
            --light: #ecf0f1;
            --border: #ddd;
            --shadow: 0 2px 4px rgba(0,0,0,0.1);
            --radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: var(--light);
            color: var(--primary);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .system-header {
            background: var(--primary);
            color: white;
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .datetime {
            font-family: 'Roboto Mono', monospace;
            font-size: 1.1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .salary-card {
            background: white;
            border-radius: var(--radius);
            padding: 2rem;
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 1rem;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .input-group {
            display: flex;
            align-items: center;
        }

        .input-group-text {
            background: var(--light);
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-right: none;
            border-radius: var(--radius) 0 0 var(--radius);
        }

        .input-group .form-input {
            border-radius: 0 var(--radius) var(--radius) 0;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .salary-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: var(--shadow);
        }

        .salary-table th,
        .salary-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }

        .salary-table th {
            background: var(--primary);
            color: white;
        }

        .salary-table tr:hover {
            background: var(--light);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-success {
            background: var(--success);
            color: white;
        }

        .amount {
            font-family: 'Roboto Mono', monospace;
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }

            .system-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="system-header">
            <div class="datetime" id="currentDateTime">
                UTC: 2025-03-13 07:05:23
            </div>
            <div class="user-info">
                <span>User:</span>
                <strong>Gurijajo</strong>
            </div>
        </div>

        <h1>Salary Management</h1>

        <!-- Salary Form -->
        <div class="salary-card">
            <h2>Add/Update Salary Information</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" id="formAction" value="add_salary">
                <input type="hidden" name="salary_id" id="salaryId">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label" for="user_id">Employee</label>
                        <select name="user_id" id="user_id" class="form-input" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['user_id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name'] . 
                                          ' (' . $employee['sector_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="fixed_salary">Fixed Salary</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="fixed_salary" id="fixed_salary" 
                                   class="form-input" step="0.01" min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="expected_salary">Expected Salary</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="expected_salary" id="expected_salary" 
                                   class="form-input" step="0.01" min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="hourly_rate">Hourly Rate</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="hourly_rate" id="hourly_rate" 
                                   class="form-input" step="0.01" min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="effective_date">Effective Date</label>
                        <input type="date" name="effective_date" id="effective_date" 
                               class="form-input" required value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Save Salary Information</button>
            </form>
        </div>

        <!-- Salary Records -->
        <div class="salary-table-container">
            <h2>Salary History</h2>
            <table class="salary-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Fixed Salary</th>
                        <th>Expected Salary</th>
                        <th>Hourly Rate</th>
                        <th>Effective Date</th>
                        <th>Last Updated</th>
                        <th>Actions</th>
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
                        echo "<button class='btn btn-primary' onclick='editSalary(" . json_encode($row) . ")'>Edit</button>";
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
        
        // Scroll to form
        document.querySelector('.salary-card').scrollIntoView({ behavior: 'smooth' });
    }

    // Keep the datetime display updated
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