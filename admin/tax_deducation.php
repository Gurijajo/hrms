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
            case 'add_tax_rule':
                $min_salary = $_POST['min_salary'];
                $max_salary = $_POST['max_salary'] ?: null;
                $tax_rate = $_POST['tax_rate'];
                $description = sanitizeInput($_POST['description']);
                
                $query = "INSERT INTO tax_rules 
                         (min_salary, max_salary, tax_rate, description, created_by) 
                         VALUES 
                         (:min, :max, :rate, :desc, :user)";
                         
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':min' => $min_salary,
                    ':max' => $max_salary,
                    ':rate' => $tax_rate,
                    ':desc' => $description,
                    ':user' => $_SESSION['user_id']
                ]);
                
                logActivity($db, $_SESSION['user_id'], 'ADD_TAX_RULE', 
                          "Added tax rule: $tax_rate% for salary range $min_salary-$max_salary");
                break;

            case 'add_deduction':
                $user_id = $_POST['user_id'];
                $type = $_POST['deduction_type'];
                $amount = $_POST['amount'];
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'] ?: null;
                $description = sanitizeInput($_POST['description']);
                
                $query = "INSERT INTO deductions 
                         (user_id, deduction_type, amount, start_date, end_date, description, created_by) 
                         VALUES 
                         (:user, :type, :amount, :start, :end, :desc, :creator)";
                         
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':user' => $user_id,
                    ':type' => $type,
                    ':amount' => $amount,
                    ':start' => $start_date,
                    ':end' => $end_date,
                    ':desc' => $description,
                    ':creator' => $_SESSION['user_id']
                ]);
                
                logActivity($db, $_SESSION['user_id'], 'ADD_DEDUCTION', 
                          "Added $type deduction of $amount for user ID: $user_id");
                break;
        }
        
        header("Location: tax_deductions.php");
        exit();
    }
}

// Get all employees for dropdown
$employees = $db->query("SELECT u.user_id, u.first_name, u.last_name, r.role_name, s.sector_name, 
                               sal.fixed_salary, sal.hourly_rate
                        FROM users u 
                        JOIN roles r ON u.role_id = r.role_id 
                        JOIN sectors s ON u.sector_id = s.sector_id 
                        LEFT JOIN salaries sal ON u.user_id = sal.user_id
                        WHERE u.status = 'active' 
                        ORDER BY s.sector_name, u.first_name, u.last_name")->fetchAll(PDO::FETCH_ASSOC);

// Get tax rules
$tax_rules = $db->query("SELECT * FROM tax_rules ORDER BY min_salary")->fetchAll(PDO::FETCH_ASSOC);

// Get active deductions
$deductions = $db->query("SELECT d.*, 
                                CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                                CONCAT(c.first_name, ' ', c.last_name) as creator_name
                         FROM deductions d
                         JOIN users u ON d.user_id = u.user_id
                         JOIN users c ON d.created_by = c.user_id
                         WHERE (d.end_date IS NULL OR d.end_date >= CURRENT_DATE)
                         ORDER BY d.start_date DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax & Deductions Management - Agroco HRMS</title>
    <style>
        /* Previous CSS styles... */
        .tax-rules-section,
        .deductions-section {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .rule-card {
            background: var(--light);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
        }

        .deduction-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .deduction-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .amount {
            font-family: 'Roboto Mono', monospace;
            font-weight: 500;
            color: var(--danger);
        }

        .tax-rate {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            background: var(--primary);
            color: white;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="system-header">
            <div class="datetime" id="currentDateTime">
                UTC: 2025-03-13 07:49:21
            </div>
            <div class="user-info">
                <span>User:</span>
                <strong>Gurijajo</strong>
            </div>
        </div>

        <h1>Tax & Deductions Management</h1>

        <!-- Tax Rules Section -->
        <div class="tax-rules-section">
            <h2>Tax Rules</h2>
            <form method="POST" action="" class="mb-3">
                <input type="hidden" name="action" value="add_tax_rule">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Minimum Salary</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="min_salary" class="form-input" required step="0.01">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Maximum Salary</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="max_salary" class="form-input" step="0.01">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tax Rate (%)</label>
                        <input type="number" name="tax_rate" class="form-input" required 
                               step="0.01" min="0" max="100">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-input" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Add Tax Rule</button>
            </form>

            <div class="tax-rules-list">
                <?php foreach ($tax_rules as $rule): ?>
                    <div class="rule-card">
                        <div class="flex-between">
                            <span class="tax-rate"><?php echo $rule['tax_rate']; ?>%</span>
                            <strong>
                                $<?php echo number_format($rule['min_salary'], 2); ?> 
                                <?php echo $rule['max_salary'] ? '- $' . number_format($rule['max_salary'], 2) : '+'; ?>
                            </strong>
                        </div>
                        <p><?php echo htmlspecialchars($rule['description']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Deductions Section -->
        <div class="deductions-section">
            <h2>Employee Deductions</h2>
            <form method="POST" action="" class="mb-3">
                <input type="hidden" name="action" value="add_deduction">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Employee</label>
                        <select name="user_id" class="form-input" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['user_id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . 
                                          $employee['last_name'] . ' (' . $employee['sector_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Deduction Type</label>
                        <select name="deduction_type" class="form-input" required>
                            <option value="PROVIDENT_FUND">Provident Fund</option>
                            <option value="HEALTH_INSURANCE">Health Insurance</option>
                            <option value="LOAN_REPAYMENT">Loan Repayment</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="amount" class="form-input" required step="0.01">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-input" required 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-input">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-input" rows="2" required></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Add Deduction</button>
            </form>

            <div class="deduction-grid">
                <?php foreach ($deductions as $deduction): ?>
                    <div class="deduction-card">
                        <div class="flex-between mb-2">
                            <h3><?php echo htmlspecialchars($deduction['employee_name']); ?></h3>
                            <span class="amount">-$<?php echo number_format($deduction['amount'], 2); ?></span>
                        </div>
                        <p><strong>Type:</strong> <?php echo str_replace('_', ' ', $deduction['deduction_type']); ?></p>
                        <p><strong>Period:</strong> 
                           <?php echo date('M d, Y', strtotime($deduction['start_date'])); ?> - 
                           <?php echo $deduction['end_date'] ? date('M d, Y', strtotime($deduction['end_date'])) : 'Ongoing'; ?>
                        </p>
                        <p><strong>Description:</strong> <?php echo htmlspecialchars($deduction['description']); ?></p>
                        <small>Added by <?php echo htmlspecialchars($deduction['creator_name']); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
    function updateDateTime() {
        document.getElementById('currentDateTime').textContent = 'UTC: 2025-03-13 07:49:21';
    }

    // Initialize datetime
    updateDateTime();
    </script>
</body>
</html>