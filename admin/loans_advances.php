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
            case 'add_loan':
                $user_id = $_POST['user_id'];
                $amount = $_POST['amount'];
                $interest_rate = $_POST['interest_rate'];
                $tenure_months = $_POST['tenure_months'];
                $start_date = $_POST['start_date'];
                $purpose = sanitizeInput($_POST['purpose']);
                
                // Calculate monthly installment
                $monthly_interest = ($interest_rate / 100) / 12;
                $monthly_installment = ($amount * $monthly_interest * pow(1 + $monthly_interest, $tenure_months)) / 
                                     (pow(1 + $monthly_interest, $tenure_months) - 1);
                
                $db->beginTransaction();
                try {
                    // Add loan record
                    $query = "INSERT INTO loans 
                             (user_id, amount, interest_rate, tenure_months, monthly_installment,
                              start_date, purpose, status, created_by) 
                             VALUES 
                             (:user, :amount, :rate, :tenure, :installment,
                              :start, :purpose, 'PENDING', :created_by)";
                             
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        ':user' => $user_id,
                        ':amount' => $amount,
                        ':rate' => $interest_rate,
                        ':tenure' => $tenure_months,
                        ':installment' => $monthly_installment,
                        ':start' => $start_date,
                        ':purpose' => $purpose,
                        ':created_by' => $_SESSION['user_id']
                    ]);
                    
                    $loan_id = $db->lastInsertId();
                    
                    // Generate installment schedule
                    $date = new DateTime($start_date);
                    for ($i = 1; $i <= $tenure_months; $i++) {
                        $query = "INSERT INTO loan_installments 
                                 (loan_id, installment_number, due_date, amount) 
                                 VALUES 
                                 (:loan, :num, :date, :amount)";
                                 
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            ':loan' => $loan_id,
                            ':num' => $i,
                            ':date' => $date->format('Y-m-d'),
                            ':amount' => $monthly_installment
                        ]);
                        
                        $date->modify('+1 month');
                    }
                    
                    logActivity($db, $_SESSION['user_id'], 'ADD_LOAN', 
                              "Added loan of $amount for user ID: $user_id");
                    
                    $db->commit();
                    $_SESSION['success_message'] = "Loan application submitted successfully";
                } catch (Exception $e) {
                    $db->rollBack();
                    $_SESSION['error_message'] = "Error adding loan: " . $e->getMessage();
                }
                break;

            case 'add_advance':
                $user_id = $_POST['user_id'];
                $amount = $_POST['amount'];
                $repayment_date = $_POST['repayment_date'];
                $reason = sanitizeInput($_POST['reason']);
                
                $query = "INSERT INTO salary_advances 
                         (user_id, amount, request_date, repayment_date, reason, status, created_by) 
                         VALUES 
                         (:user, :amount, CURRENT_DATE, :repayment, :reason, 'PENDING', :created_by)";
                         
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':user' => $user_id,
                    ':amount' => $amount,
                    ':repayment' => $repayment_date,
                    ':reason' => $reason,
                    ':created_by' => $_SESSION['user_id']
                ]);
                
                logActivity($db, $_SESSION['user_id'], 'ADD_ADVANCE', 
                          "Added salary advance of $amount for user ID: $user_id");
                break;

            case 'update_status':
                $type = $_POST['type'];
                $id = $_POST['id'];
                $status = $_POST['status'];
                $remarks = sanitizeInput($_POST['remarks']);
                
                $table = $type === 'LOAN' ? 'loans' : 'salary_advances';
                $id_field = $type === 'LOAN' ? 'loan_id' : 'advance_id';
                
                $query = "UPDATE $table SET 
                         status = :status,
                         approval_remarks = :remarks,
                         approved_by = :approved_by,
                         approved_at = CURRENT_TIMESTAMP
                         WHERE $id_field = :id";
                         
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':status' => $status,
                    ':remarks' => $remarks,
                    ':approved_by' => $_SESSION['user_id'],
                    ':id' => $id
                ]);
                
                logActivity($db, $_SESSION['user_id'], 'UPDATE_' . $type . '_STATUS', 
                          "Updated $type ID: $id status to $status");
                break;
        }
        
        header("Location: loan_advance.php");
        exit();
    }
}

// Get employees with their current loan and advance status
$employees = $db->query("SELECT u.user_id, u.first_name, u.last_name, r.role_name, s.sector_name,
                               sal.fixed_salary,
                               (SELECT COUNT(*) FROM loans 
                                WHERE user_id = u.user_id AND status = 'ACTIVE') as active_loans,
                               (SELECT COUNT(*) FROM salary_advances 
                                WHERE user_id = u.user_id AND status = 'ACTIVE') as active_advances
                        FROM users u 
                        JOIN roles r ON u.role_id = r.role_id 
                        JOIN sectors s ON u.sector_id = s.sector_id 
                        LEFT JOIN salaries sal ON u.user_id = sal.user_id
                        WHERE u.status = 'active' 
                        ORDER BY s.sector_name, u.first_name, u.last_name")->fetchAll(PDO::FETCH_ASSOC);

// Get pending approvals
$pending_approvals = $db->query("(SELECT 'LOAN' as type, l.loan_id as id, 
                                        u.first_name, u.last_name, 
                                        l.amount, l.created_at, l.status
                                 FROM loans l
                                 JOIN users u ON l.user_id = u.user_id
                                 WHERE l.status = 'PENDING')
                                UNION ALL
                                (SELECT 'ADVANCE' as type, a.advance_id as id,
                                        u.first_name, u.last_name,
                                        a.amount, a.request_date as created_at, a.status
                                 FROM salary_advances a
                                 JOIN users u ON a.user_id = u.user_id
                                 WHERE a.status = 'PENDING')
                                ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

// Get active loans and advances
$active_records = $db->query("(SELECT 'LOAN' as type, l.*, 
                                     u.first_name, u.last_name,
                                     CONCAT(c.first_name, ' ', c.last_name) as creator_name
                              FROM loans l
                              JOIN users u ON l.user_id = u.user_id
                              JOIN users c ON l.created_by = c.user_id
                              WHERE l.status = 'ACTIVE')
                              UNION ALL
                              (SELECT 'ADVANCE' as type, a.*,
                                      u.first_name, u.last_name,
                                      CONCAT(c.first_name, ' ', c.last_name) as creator_name
                              FROM salary_advances a
                              JOIN users u ON a.user_id = u.user_id
                              JOIN users c ON a.created_by = c.user_id
                              WHERE a.status = 'ACTIVE')
                              ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan & Advance Salary Management - Agroco HRMS</title>
    <style>
        /* ... Previous CSS styles ... */
        .loan-section,
        .advance-section,
        .approvals-section {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .installment-schedule {
            max-height: 200px;
            overflow-y: auto;
            margin-top: 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        .status-pending { background: var(--warning); color: var(--primary); }
        .status-active { background: var(--success); color: white; }
        .status-rejected { background: var(--danger); color: white; }
        .status-completed { background: var(--primary); color: white; }

        .loan-card,
        .advance-card {
            background: var(--light);
            padding: 1.5rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
        }

        .amount-large {
            font-size: 1.25rem;
            font-weight: 500;
            font-family: 'Roboto Mono', monospace;
            color: var(--primary);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- System Header -->
        <div class="system-header">
            <div class="datetime" id="currentDateTime">
                UTC: 2025-03-13 08:01:32
            </div>
            <div class="user-info">
                <span>User:</span>
                <strong>Gurijajo</strong>
            </div>
        </div>

        <h1>Loan & Advance Salary Management</h1>

        <!-- Success/Error Messages -->
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

        <!-- Loan Application Section -->
        <div class="loan-section">
            <h2>New Loan Application</h2>
            <form method="POST" action="" id="loanForm">
                <input type="hidden" name="action" value="add_loan">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Employee</label>
                        <select name="user_id" class="form-input" required onchange="updateLoanLimits()">
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['user_id']; ?>" 
                                        data-salary="<?php echo $employee['fixed_salary']; ?>"
                                        data-loans="<?php echo $employee['active_loans']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . 
                                          $employee['last_name'] . ' (' . $employee['sector_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Loan Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="amount" id="loanAmount" class="form-input" 
                                   required step="0.01" min="0">
                        </div>
                        <small id="loanLimitInfo" class="text-muted"></small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Interest Rate (%)</label>
                        <input type="number" name="interest_rate" class="form-input" 
                               required step="0.01" min="0" max="30">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Tenure (Months)</label>
                        <input type="number" name="tenure_months" class="form-input" 
                               required min="1" max="60">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-input" required 
                               min="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Purpose</label>
                    <textarea name="purpose" class="form-input" rows="3" required></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Submit Loan Application</button>
            </form>
        </div>

        <!-- Salary Advance Section -->
        <div class="advance-section">
            <h2>Salary Advance Request</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_advance">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Employee</label>
                        <select name="user_id" class="form-input" required onchange="updateAdvanceLimits()">
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['user_id']; ?>"
                                        data-salary="<?php echo $employee['fixed_salary']; ?>"
                                        data-advances="<?php echo $employee['active_advances']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . 
                                          $employee['last_name'] . ' (' . $employee['sector_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" name="amount" id="advanceAmount" class="form-input" 
                                   required step="0.01" min="0">
                        </div>
                        <small id="advanceLimitInfo" class="text-muted"></small>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Repayment Date</label>
                        <input type="date" name="repayment_date" class="form-input" required 
                               min="<?php echo date('Y-m-d', strtotime('+1 month')); ?>"
                               max="<?php echo date('Y-m-d', strtotime('+3 months')); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-input" rows="3" required></textarea>
                </div>

                <button type="submit" class="btn btn-primary">Submit Advance Request</button>
            </form>
        </div>

        <!-- Pending Approvals Section -->
        <div class="approvals-section">
            <h2>Pending Approvals</h2>
            <div class="approvals-grid">
                <?php foreach ($pending_approvals as $approval): ?>
                    <div class="approval-card">
                        <div class="flex-between">
                            <div>
                                <h3><?php echo $approval['type']; ?> Request</h3>
                                <p><?php echo htmlspecialchars($approval['first_name'] . ' ' . $approval['last_name']); ?></p>
                            </div>
                            <span class="amount-large">$<?php echo number_format($approval['amount'], 2); ?></span>
                        </div>
                        <p><strong>Requested:</strong> <?php echo date('M d, Y', strtotime($approval['created_at'])); ?></p>
                        
                        <form method="POST" action="" class="approval-form">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="type" value="<?php echo $approval['type']; ?>">
                            <input type="hidden" name="id" value="<?php echo $approval['id']; ?>">
                            
                            <div class="form-group">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks" class="form-input" rows="2" required></textarea>
                            </div>
                            
                            <div class="button-group">
                                <button type="submit" name="status" value="APPROVED" class="btn btn-success">Approve</button>
                                <button type="submit" name="status" value="REJECTED" class="btn btn-danger">Reject</button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Active Loans and Advances -->
        <div class="active-records-section">
            <h2>Active Loans & Advances</h2>
            <div class="records-grid">
                <?php foreach ($active_records as $record): ?>
                    <div class="<?php echo strtolower($record['type']); ?>-card">
                        <div class="flex-between">
                            <div>
                                <h3><?php echo $record['type']; ?></h3>
                                <p><?php echo htmlspecialchars($record['first_name'] . ' ' . $record['last_name']); ?></p>
                            </div>
                            <span class="amount-large">$<?php echo number_format($record['amount'], 2); ?></span>
                        </div>
                        
                        <?php if ($record['type'] === 'LOAN'): ?>
                            <p><strong>Interest Rate:</strong> <?php echo $record['interest_rate']; ?>%</p>
                            <p><strong>Monthly Installment:</strong> 
                               $<?php echo number_format($record['monthly_installment'], 2); ?></p>
                            <p><strong>Remaining Tenure:</strong> 
                               <?php echo $record['tenure_months']; ?> months</p>
                        <?php else: ?>
                            <p><strong>Repayment Date:</strong> 
                               <?php echo date('M d, Y', strtotime($record['repayment_date'])); ?></p>
                        <?php endif; ?>
                        
                        <p><strong>Added by:</strong> <?php echo htmlspecialchars($record['creator_name']); ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
    function updateDateTime() {
        document.getElementById('currentDateTime').textContent = 'UTC: 2025-03-13 08:03:19';
    }

    function updateLoanLimits() {
        const select = document.querySelector('select[name="user_id"]');
        const option = select.options[select.selectedIndex];
        const salary = parseFloat(option.dataset.salary || 0);
        const activeLoans = parseInt(option.dataset.loans || 0);
        
        const loanAmount = document.getElementById('loanAmount');
        const loanLimitInfo = document.getElementById('loanLimitInfo');
        
        if (activeLoans >= 2) {
            loanAmount.max = 0;
            loanLimitInfo.textContent = 'Employee has reached maximum number of active loans';
            loanLimitInfo.style.color = 'var(--danger)';
        } else {
            const maxLoan = salary * 12; // Maximum loan amount = 1 year salary
            loanAmount.max = maxLoan;
            loanLimitInfo.textContent = `Maximum loan amount: $${maxLoan.toFixed(2)}`;
            loanLimitInfo.style.color = 'var(--text-light)';
        }
    }

    function updateAdvanceLimits() {
        const select = document.querySelector('select[name="user_id"]');
        const option = select.options[select.selectedIndex];
        const salary = parseFloat(option.dataset.salary || 0);
        const activeAdvances = parseInt(option.dataset.advances || 0);
        
        const advanceAmount = document.getElementById('advanceAmount');
        const advanceLimitInfo = document.getElementById('advanceLimitInfo');
        
        if (activeAdvances >= 1) {
            advanceAmount.max = 0;
            advanceLimitInfo.textContent = 'Employee already has an active salary advance';
            advanceLimitInfo.style.color = 'var(--danger)';
        } else {
            const maxAdvance = salary * 0.5; // Maximum advance = 50% of monthly salary
            advanceAmount.max = maxAdvance;
            advanceLimitInfo.textContent = `Maximum advance amount: $${maxAdvance.toFixed(2)}`;
            advanceLimitInfo.style.color = 'var(--text-light)';
        }
    }

    // Initialize datetime and form validations
    updateDateTime();
    if (document.querySelector('select[name="user_id"]')) {
        updateLoanLimits();
        updateAdvanceLimits();
    }
    </script>
</body>
</html>