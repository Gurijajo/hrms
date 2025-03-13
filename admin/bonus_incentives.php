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
            case 'add_bonus':
                $user_ids = $_POST['user_ids'];
                $bonus_type = $_POST['bonus_type'];
                $amount = $_POST['amount'];
                $date = $_POST['date'];
                $reason = sanitizeInput($_POST['reason']);
                
                // Begin transaction
                $db->beginTransaction();
                try {
                    foreach ($user_ids as $user_id) {
                        $query = "INSERT INTO bonuses 
                                 (user_id, bonus_type, amount, date, reason, created_by) 
                                 VALUES 
                                 (:user_id, :type, :amount, :date, :reason, :created_by)";
                                 
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            ':user_id' => $user_id,
                            ':type' => $bonus_type,
                            ':amount' => $amount,
                            ':date' => $date,
                            ':reason' => $reason,
                            ':created_by' => $_SESSION['user_id']
                        ]);
                        
                        logActivity($db, $_SESSION['user_id'], 'ADD_BONUS', 
                                  "Added $bonus_type bonus of $amount for user ID: $user_id");
                    }
                    $db->commit();
                    $_SESSION['success_message'] = "Bonus added successfully for " . count($user_ids) . " employee(s)";
                } catch (Exception $e) {
                    $db->rollBack();
                    $_SESSION['error_message'] = "Error adding bonus: " . $e->getMessage();
                }
                break;

            case 'add_incentive_rule':
                $target_type = $_POST['target_type'];
                $target_value = $_POST['target_value'];
                $reward_amount = $_POST['reward_amount'];
                $description = sanitizeInput($_POST['description']);
                
                $query = "INSERT INTO incentive_rules 
                         (target_type, target_value, reward_amount, description, created_by) 
                         VALUES 
                         (:type, :value, :amount, :desc, :user)";
                         
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':type' => $target_type,
                    ':value' => $target_value,
                    ':amount' => $reward_amount,
                    ':desc' => $description,
                    ':user' => $_SESSION['user_id']
                ]);
                
                logActivity($db, $_SESSION['user_id'], 'ADD_INCENTIVE_RULE', 
                          "Added incentive rule: $target_type = $target_value, reward: $reward_amount");
                break;
        }
        
        header("Location: bonus_incentives.php");
        exit();
    }
}

// Get all employees
$employees = $db->query("SELECT u.user_id, u.first_name, u.last_name, r.role_name, s.sector_name 
                        FROM users u 
                        JOIN roles r ON u.role_id = r.role_id 
                        JOIN sectors s ON u.sector_id = s.sector_id 
                        WHERE u.status = 'active' 
                        ORDER BY s.sector_name, u.first_name, u.last_name")->fetchAll(PDO::FETCH_ASSOC);

// Get incentive rules
$incentive_rules = $db->query("SELECT ir.*, CONCAT(u.first_name, ' ', u.last_name) as creator_name 
                              FROM incentive_rules ir 
                              JOIN users u ON ir.created_by = u.user_id 
                              ORDER BY ir.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get recent bonuses
$recent_bonuses = $db->query("SELECT b.*, 
                                    CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                                    CONCAT(c.first_name, ' ', c.last_name) as creator_name,
                                    s.sector_name
                             FROM bonuses b
                             JOIN users u ON b.user_id = u.user_id
                             JOIN users c ON b.created_by = c.user_id
                             JOIN sectors s ON u.sector_id = s.sector_id
                             ORDER BY b.created_at DESC
                             LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bonus & Incentives Management - Agroco HRMS</title>
    <style>
        /* Previous CSS styles... */
        .bonus-section,
        .incentive-section {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .employee-select {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 0.5rem;
        }

        .employee-option {
            padding: 0.5rem;
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .employee-option:hover {
            background: var(--light);
        }

        .employee-option.selected {
            background: var(--accent);
            color: white;
        }

        .bonus-amount {
            color: var(--success);
            font-weight: 500;
            font-family: 'Roboto Mono', monospace;
        }

        .incentive-card {
            background: var(--light);
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
        }

        .bonus-history {
            margin-top: 2rem;
        }

        .sector-tag {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 20px;
            background: var(--secondary);
            color: white;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="system-header">
            <div class="datetime" id="currentDateTime">
                UTC: 2025-03-13 07:55:18
            </div>
            <div class="user-info">
                <span>User:</span>
                <strong>Gurijajo</strong>
            </div>
        </div>

        <h1>Bonus & Incentives Management</h1>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <?php 
                echo $_SESSION['success_message'];
                unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger">
                <?php 
                echo $_SESSION['error_message'];
                unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Bonus Section -->
        <div class="bonus-section">
            <h2>Add New Bonus</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_bonus">
                
                <div class="form-group">
                    <label class="form-label">Select Employees</label>
                    <div class="employee-select">
                        <?php foreach ($employees as $employee): ?>
                            <div class="employee-option" onclick="toggleEmployee(this, <?php echo $employee['user_id']; ?>)">
                                <input type="checkbox" name="user_ids[]" value="<?php echo $employee['user_id']; ?>" 
                                       style="display: none;">
                                <?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?>
                                <span class="sector-tag"><?php echo htmlspecialchars($employee['sector_name']); ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Bonus Type</label>
                        <select name="bonus_type" class="form-input" required>
                            <option value="PERFORMANCE">Performance Bonus</option>
                            <option value="ANNUAL">Annual Bonus</option>
                            <option value="PROJECT">Project Completion Bonus</option>
                            <option value="HOLIDAY">Holiday Bonus</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="amount" class="form-input" required step="0.01" min="0">
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date</label>
                        <input type="date" name="date" class="form-input" required 
                               value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-input" rows="3" required></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Add Bonus</button>
            </form>
        </div>

        <!-- Incentive Rules Section -->
        <div class="incentive-section">
            <h2>Incentive Rules</h2>
            <form method="POST" action="" class="mb-3">
                <input type="hidden" name="action" value="add_incentive_rule">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Target Type</label>
                        <select name="target_type" class="form-input" required>
                            <option value="SALES">Sales Target</option>
                            <option value="PRODUCTIVITY">Productivity Target</option>
                            <option value="ATTENDANCE">Perfect Attendance</option>
                            <option value="OVERTIME">Overtime Hours</option>
                            <option value="PERFORMANCE">Performance Rating</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Target Value</label>
                        <input type="number" name="target_value" class="form-input" required step="0.01">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Reward Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="reward_amount" class="form-input" required step="0.01" min="0">
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-input" rows="2" required></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Add Incentive Rule</button>
            </form>

            <div class="incentive-rules-list">
                <?php foreach ($incentive_rules as $rule): ?>
                    <div class="incentive-card">
                        <div class="flex-between">
                            <h3><?php echo str_replace('_', ' ', $rule['target_type']); ?></h3>
                            <span class="bonus-amount">$<?php echo number_format($rule['reward_amount'], 2); ?></span>
                        </div>
                        <p><strong>Target:</strong> <?php echo $rule['target_value']; ?></p>
                        <p><?php echo htmlspecialchars($rule['description']); ?></p>
                        <small>Created by <?php echo htmlspecialchars($rule['creator_name']); ?></small>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Bonuses -->
        <div class="bonus-history">
            <h2>Recent Bonuses</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Reason</th>
                        <th>Added By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_bonuses as $bonus): ?>
                        <tr>
                            <td><?php echo date('M d, Y', strtotime($bonus['date'])); ?></td>
                            <td>
                                <?php echo htmlspecialchars($bonus['employee_name']); ?>
                                <br>
                                <small class="sector-tag">
                                    <?php echo htmlspecialchars($bonus['sector_name']); ?>
                                </small>
                            </td>
                            <td><?php echo str_replace('_', ' ', $bonus['bonus_type']); ?></td>
                            <td class="bonus-amount">$<?php echo number_format($bonus['amount'], 2); ?></td>
                            <td><?php echo htmlspecialchars($bonus['reason']); ?></td>
                            <td><?php echo htmlspecialchars($bonus['creator_name']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
    function updateDateTime() {
        document.getElementById('currentDateTime').textContent = 'UTC: 2025-03-13 07:55:18';
    }

    function toggleEmployee(element, userId) {
        element.classList.toggle('selected');
        const checkbox = element.querySelector('input[type="checkbox"]');
        checkbox.checked = !checkbox.checked;
    }

    // Initialize datetime
    updateDateTime();
    </script>
</body>
</html>