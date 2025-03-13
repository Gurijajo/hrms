<?php
require_once '../includes/functions.php';
require_once '../includes/dompdf/autoload.inc.php'; // Make sure to install dompdf
checkLogin();

use Dompdf\Dompdf;
use Dompdf\Options;

$database = new Database();
$db = $database->getConnection();

// Handle salary slip generation
if (isset($_POST['generate_slip'])) {
    $user_id = $_POST['user_id'];
    $month = $_POST['month'];
    $year = $_POST['year'];
    
    // Get employee details
    $query = "SELECT u.*, r.role_name, s.sector_name,
                     sal.fixed_salary, sal.hourly_rate,
                     (SELECT SUM(amount) FROM bonuses 
                      WHERE user_id = u.user_id 
                      AND MONTH(date) = :month 
                      AND YEAR(date) = :year) as total_bonus,
                     (SELECT SUM(amount) FROM deductions 
                      WHERE user_id = u.user_id 
                      AND MONTH(date) = :month 
                      AND YEAR(date) = :year) as total_deductions,
                     (SELECT SUM(hours_worked) FROM attendance 
                      WHERE user_id = u.user_id 
                      AND MONTH(date) = :month 
                      AND YEAR(date) = :year) as total_hours
              FROM users u
              JOIN roles r ON u.role_id = r.role_id
              JOIN sectors s ON u.sector_id = s.sector_id
              LEFT JOIN salaries sal ON u.user_id = sal.user_id
              WHERE u.user_id = :user_id
              ORDER BY sal.effective_date DESC
              LIMIT 1";
              
    $stmt = $db->prepare($query);
    $stmt->execute([
        ':user_id' => $user_id,
        ':month' => $month,
        ':year' => $year
    ]);
    
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($employee) {
        // Calculate salary components
        $basic_salary = $employee['fixed_salary'] ?? 0;
        $hourly_earnings = ($employee['hourly_rate'] ?? 0) * ($employee['total_hours'] ?? 0);
        $total_bonus = $employee['total_bonus'] ?? 0;
        
        // Calculate deductions
        $total_deductions = $employee['total_deductions'] ?? 0;
        $tax_rate = 0.2; // 20% tax rate
        $tax_amount = ($basic_salary + $hourly_earnings + $total_bonus) * $tax_rate;
        
        // Generate PDF
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isPhpEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        // PDF content
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .header { text-align: center; margin-bottom: 30px; }
                .company-name { font-size: 24px; font-weight: bold; }
                .slip-title { font-size: 20px; margin: 20px 0; }
                .info-section { margin: 20px 0; }
                .info-row { margin: 10px 0; }
                .amount-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .amount-table th, .amount-table td { 
                    padding: 10px; 
                    border: 1px solid #ddd; 
                    text-align: left; 
                }
                .total-row { font-weight: bold; background: #f8f9fa; }
            </style>
        </head>
        <body>
            <div class="header">
                <div class="company-name">Agroco HRMS</div>
                <div class="slip-title">Salary Slip - ' . date("F Y", mktime(0, 0, 0, $month, 1, $year)) . '</div>
            </div>
            
            <div class="info-section">
                <div class="info-row">
                    <strong>Employee Name:</strong> ' . htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) . '
                </div>
                <div class="info-row">
                    <strong>Employee ID:</strong> ' . htmlspecialchars($employee['user_id']) . '
                </div>
                <div class="info-row">
                    <strong>Role:</strong> ' . htmlspecialchars($employee['role_name']) . '
                </div>
                <div class="info-row">
                    <strong>Sector:</strong> ' . htmlspecialchars($employee['sector_name']) . '
                </div>
            </div>
            
            <table class="amount-table">
                <tr>
                    <th colspan="2">Earnings</th>
                </tr>
                <tr>
                    <td>Basic Salary</td>
                    <td>$' . number_format($basic_salary, 2) . '</td>
                </tr>
                <tr>
                    <td>Hourly Earnings</td>
                    <td>$' . number_format($hourly_earnings, 2) . '</td>
                </tr>
                <tr>
                    <td>Bonuses</td>
                    <td>$' . number_format($total_bonus, 2) . '</td>
                </tr>
                <tr>
                    <th colspan="2">Deductions</th>
                </tr>
                <tr>
                    <td>Tax (' . ($tax_rate * 100) . '%)</td>
                    <td>$' . number_format($tax_amount, 2) . '</td>
                </tr>
                <tr>
                    <td>Other Deductions</td>
                    <td>$' . number_format($total_deductions, 2) . '</td>
                </tr>
                <tr class="total-row">
                    <td>Net Salary</td>
                    <td>$' . number_format(($basic_salary + $hourly_earnings + $total_bonus) - ($tax_amount + $total_deductions), 2) . '</td>
                </tr>
            </table>
            
            <div class="info-section">
                <div class="info-row">
                    <strong>Generated on:</strong> ' . date('Y-m-d H:i:s') . ' UTC
                </div>
                <div class="info-row">
                    <strong>Generated by:</strong> ' . htmlspecialchars($_SESSION['username']) . '
                </div>
            </div>
        </body>
        </html>';
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        // Log activity
        logActivity($db, $_SESSION['user_id'], 'GENERATE_SALARY_SLIP', 
                   "Generated salary slip for user ID: $user_id - " . date("F Y", mktime(0, 0, 0, $month, 1, $year)));
        
        // Output PDF
        $dompdf->stream("salary_slip_" . $employee['username'] . "_" . $year . "_" . $month . ".pdf", 
                       array("Attachment" => true));
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
    <title>Salary Slip Generation - Agroco HRMS</title>
    <style>
        /* Previous CSS styles... */
        .generation-form {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
        }

        .history-section {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .month-picker {
            display: flex;
            gap: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="system-header">
            <div class="datetime" id="currentDateTime">
                UTC: 2025-03-13 07:47:13
            </div>
            <div class="user-info">
                <span>User:</span>
                <strong>Gurijajo</strong>
            </div>
        </div>

        <h1>Salary Slip Generation</h1>

        <div class="generation-form">
            <h2>Generate Salary Slip</h2>
            <form method="POST" action="">
                <input type="hidden" name="generate_slip" value="1">
                
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
                        <label class="form-label">Month</label>
                        <select name="month" class="form-input" required>
                            <?php
                            for ($i = 1; $i <= 12; $i++) {
                                $month = date('F', mktime(0, 0, 0, $i, 1));
                                echo "<option value='$i'" . ($i == date('n') ? ' selected' : '') . ">$month</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Year</label>
                        <select name="year" class="form-input" required>
                            <?php
                            $current_year = date('Y');
                            for ($year = $current_year; $year >= $current_year - 2; $year--) {
                                echo "<option value='$year'>$year</option>";
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Generate Salary Slip</button>
            </form>
        </div>

        <!-- Generation History -->
        <div class="history-section">
            <h2>Recent Generations</h2>
            <table class="table">
                <thead>
                    <tr>
                        <th>Date Generated</th>
                        <th>Employee</th>
                        <th>Period</th>
                        <th>Generated By</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT al.*, 
                                    CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                                    CONCAT(g.first_name, ' ', g.last_name) as generator_name
                             FROM activity_logs al
                             JOIN users u ON al.affected_record_id = u.user_id
                             JOIN users g ON al.user_id = g.user_id
                             WHERE al.action_type = 'GENERATE_SALARY_SLIP'
                             ORDER BY al.created_at DESC
                             LIMIT 10";
                    
                    $history = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($history as $record):
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($record['created_at']); ?></td>
                        <td><?php echo htmlspecialchars($record['employee_name']); ?></td>
                        <td><?php echo htmlspecialchars(extractPeriod($record['action_details'])); ?></td>
                        <td><?php echo htmlspecialchars($record['generator_name']); ?></td>
                        <td>
                            <form method="POST" action="">
                                <input type="hidden" name="regenerate" value="1">
                                <input type="hidden" name="user_id" value="<?php echo $record['affected_record_id']; ?>">
                                <button type="submit" class="btn btn-secondary">Regenerate</button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
function updateDateTime() {
    const now = new Date();
    const formattedDateTime = now.toISOString().replace('T', ' ').substring(0, 19); // Format as "YYYY-MM-DD HH:MM:SS"
    document.getElementById('currentDateTime').textContent = `UTC: ${formattedDateTime}`;
}

// Update every second
setInterval(updateDateTime, 1000);

// Initialize datetime immediately
updateDateTime();
    </script>
</body>
</html>

<?php
function extractPeriod($details) {
    if (preg_match('/- ([A-Za-z]+ \d{4})/', $details, $matches)) {
        return $matches[1];
    }
    return '';
}
?>