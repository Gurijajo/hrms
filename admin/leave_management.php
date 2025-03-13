<?php
require_once '../includes/functions.php';
checkLogin();

$database = new Database();
$db = $database->getConnection();

$current_utc = '2025-03-13 08:45:16';
$current_user = 'Gurijajo';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_request':
                $user_id = $_POST['user_id'];
                $type_id = $_POST['type_id'];
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'];
                $reason = sanitizeInput($_POST['reason']);
                
                // Calculate total days excluding weekends
                $start = new DateTime($start_date);
                $end = new DateTime($end_date);
                $interval = new DateInterval('P1D');
                $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
                
                $total_days = 0;
                foreach ($period as $date) {
                    if ($date->format('N') < 6) { // Skip Saturday (6) and Sunday (7)
                        $total_days++;
                    }
                }
                
                // Check leave balance
                $query = "SELECT * FROM leave_balances 
                         WHERE user_id = :user_id 
                         AND type_id = :type_id 
                         AND year = YEAR(:start_date)";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':type_id' => $type_id,
                    ':start_date' => $start_date
                ]);
                $balance = $stmt->fetch(PDO::FETCH_ASSOC);
                
                $available_days = $balance ? 
                    ($balance['total_days'] - $balance['used_days'] - $balance['pending_days']) : 0;
                
                if ($available_days >= $total_days) {
                    $db->beginTransaction();
                    try {
                        // Insert leave request
                        $query = "INSERT INTO leave_requests 
                                 (user_id, type_id, start_date, end_date, total_days, reason) 
                                 VALUES 
                                 (:user_id, :type_id, :start_date, :end_date, :total_days, :reason)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            ':user_id' => $user_id,
                            ':type_id' => $type_id,
                            ':start_date' => $start_date,
                            ':end_date' => $end_date,
                            ':total_days' => $total_days,
                            ':reason' => $reason
                        ]);
                        
                        // Update leave balance
                        $query = "UPDATE leave_balances 
                                 SET pending_days = pending_days + :days 
                                 WHERE user_id = :user_id 
                                 AND type_id = :type_id 
                                 AND year = YEAR(:start_date)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            ':days' => $total_days,
                            ':user_id' => $user_id,
                            ':type_id' => $type_id,
                            ':start_date' => $start_date
                        ]);
                        
                        $db->commit();
                        $_SESSION['success_message'] = "Leave request submitted successfully.";
                    } catch (Exception $e) {
                        $db->rollBack();
                        $_SESSION['error_message'] = "Error submitting leave request: " . $e->getMessage();
                    }
                } else {
                    $_SESSION['error_message'] = "Insufficient leave balance.";
                }
                break;

            case 'update_status':
                $request_id = $_POST['request_id'];
                $status = $_POST['status'];
                $remarks = sanitizeInput($_POST['remarks']);
                
                $db->beginTransaction();
                try {
                    // Get request details
                    $query = "SELECT * FROM leave_requests WHERE request_id = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$request_id]);
                    $request = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Update request status
                    $query = "UPDATE leave_requests 
                             SET status = :status,
                                 approval_remarks = :remarks,
                                 approved_by = :approved_by,
                                 approved_at = CURRENT_TIMESTAMP
                             WHERE request_id = :request_id";
                    $stmt = $db->prepare($query);
                    $stmt->execute([
                        ':status' => $status,
                        ':remarks' => $remarks,
                        ':approved_by' => $_SESSION['user_id'],
                        ':request_id' => $request_id
                    ]);
                    
                    // Update leave balance
                    if ($status === 'APPROVED') {
                        $query = "UPDATE leave_balances 
                                 SET used_days = used_days + :days,
                                     pending_days = pending_days - :days
                                 WHERE user_id = :user_id 
                                 AND type_id = :type_id 
                                 AND year = YEAR(:start_date)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            ':days' => $request['total_days'],
                            ':user_id' => $request['user_id'],
                            ':type_id' => $request['type_id'],
                            ':start_date' => $request['start_date']
                        ]);
                    } else if ($status === 'REJECTED' || $status === 'CANCELLED') {
                        $query = "UPDATE leave_balances 
                                 SET pending_days = pending_days - :days
                                 WHERE user_id = :user_id 
                                 AND type_id = :type_id 
                                 AND year = YEAR(:start_date)";
                        $stmt = $db->prepare($query);
                        $stmt->execute([
                            ':days' => $request['total_days'],
                            ':user_id' => $request['user_id'],
                            ':type_id' => $request['type_id'],
                            ':start_date' => $request['start_date']
                        ]);
                    }
                    
                    $db->commit();
                    $_SESSION['success_message'] = "Leave request status updated successfully.";
                } catch (Exception $e) {
                    $db->rollBack();
                    $_SESSION['error_message'] = "Error updating request status: " . $e->getMessage();
                }
                break;
        }
        
        header("Location: leave_management.php");
        exit();
    }
}

// Get leave types
$leave_types = $db->query("SELECT * FROM leave_types ORDER BY type_name")->fetchAll(PDO::FETCH_ASSOC);

// Get employees
$employees = $db->query("SELECT u.*, s.sector_name 
                        FROM users u 
                        JOIN sectors s ON u.sector_id = s.sector_id 
                        WHERE u.status = 'active' 
                        ORDER BY u.first_name, u.last_name")->fetchAll(PDO::FETCH_ASSOC);

// Get pending requests
$pending_requests = $db->query("SELECT lr.*, 
                                      CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                                      lt.type_name,
                                      s.sector_name
                               FROM leave_requests lr
                               JOIN users u ON lr.user_id = u.user_id
                               JOIN leave_types lt ON lr.type_id = lt.type_id
                               JOIN sectors s ON u.sector_id = s.sector_id
                               WHERE lr.status = 'PENDING'
                               ORDER BY lr.start_date")->fetchAll(PDO::FETCH_ASSOC);

// Get recent requests
$recent_requests = $db->query("SELECT lr.*, 
                                     CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                                     lt.type_name,
                                     s.sector_name,
                                     CONCAT(a.first_name, ' ', a.last_name) as approver_name
                              FROM leave_requests lr
                              JOIN users u ON lr.user_id = u.user_id
                              JOIN leave_types lt ON lr.type_id = lt.type_id
                              JOIN sectors s ON u.sector_id = s.sector_id
                              LEFT JOIN users a ON lr.approved_by = a.user_id
                              ORDER BY lr.created_at DESC
                              LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
?>
<?php include '../includes/header.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - Agroco HRMS</title>
    
</head>
<body>
    <div class="container">
        <!-- System Header -->
        <div class="system-header">
            <div class="datetime" id="currentDateTime">
                UTC: <?php echo $current_utc; ?>
            </div>
            <div class="user-info">
                <span>User:</span>
                <strong><?php echo $current_user; ?></strong>
            </div>
        </div>

        <h1>Leave Management</h1>

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

        <!-- New Leave Request Section -->
        <div class="section">
            <h2>New Leave Request</h2>
            <form method="POST" action="" id="leaveRequestForm" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_request">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Employee</label>
                        <select name="user_id" class="form-input" required onchange="updateLeaveBalance()">
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
                        <label class="form-label">Leave Type</label>
                        <select name="type_id" class="form-input" required onchange="updateLeaveBalance()">
                            <option value="">Select Leave Type</option>
                            <?php foreach ($leave_types as $type): ?>
                                <option value="<?php echo $type['type_id']; ?>" 
                                        data-requires-attachment="<?php echo $type['requires_attachment']; ?>"
                                        data-min-notice="<?php echo $type['min_days_notice']; ?>"
                                        data-max-days="<?php echo $type['max_days_per_request']; ?>">
                                    <?php echo $type['type_name']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-input" required 
                               min="<?php echo date('Y-m-d'); ?>" onchange="updateDuration()">
                    </div>

                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-input" required 
                               min="<?php echo date('Y-m-d'); ?>" onchange="updateDuration()">
                    </div>
                </div>

                <div id="leaveBalanceInfo" class="leave-balance"></div>
                <div id="durationInfo" class="mt-2"></div>

                <div class="form-group">
                    <label class="form-label">Reason</label>
                    <textarea name="reason" class="form-input" rows="3" required></textarea>
                </div>

                <div class="form-group" id="attachmentGroup" style="display: none;">
                    <label class="form-label">Supporting Document</label>
                    <input type="file" name="attachment" class="form-input">
                    <small class="text-muted">Required for this leave type</small>
                </div>

                <button type="submit" class="btn btn-primary">Submit Request</button>
            </form>
        </div>

        <!-- Pending Approvals Section -->
        <div class="section mt-4">
            <h2>Pending Approvals</h2>
            <div class="leave-requests-grid">
                <?php foreach ($pending_requests as $request): ?>
                    <div class="leave-card">
                        <div class="flex-between">
                            <div>
                                <h3><?php echo htmlspecialchars($request['employee_name']); ?></h3>
                                <p class="text-muted"><?php echo $request['sector_name']; ?></p>
                            </div>
                            <span class="status-badge status-pending">PENDING</span>
                        </div>
                        
                        <div class="leave-details">
                            <p><strong>Type:</strong> <?php echo $request['type_name']; ?></p>
                            <p><strong>Duration:</strong> 
                               <?php echo date('M d', strtotime($request['start_date'])); ?> - 
                               <?php echo date('M d, Y', strtotime($request['end_date'])); ?>
                               (<?php echo $request['total_days']; ?> days)
                            </p>
                            <p><strong>Reason:</strong> <?php echo htmlspecialchars($request['reason']); ?></p>
                        </div>

                        <form method="POST" action="" class="mt-3">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
                            
                            <div class="form-group">
                                <label class="form-label">Remarks</label>
                                <textarea name="remarks" class="form-input" rows="2" required></textarea>
                            </div>
                            
                            <div class="button-group">
                                <button type="submit" name="status" value="APPROVED" class="btn btn-success">
                                    Approve
                                </button>
                                <button type="submit" name="status" value="REJECTED" class="btn btn-danger">
                                    Reject
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Recent Requests Section -->
        <div class="section mt-4">
            <h2>Recent Leave Requests</h2>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Duration</th>
                            <th>Status</th>
                            <th>Approved By</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_requests as $request): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($request['employee_name']); ?>
                                    <br>
                                    <small class="text-muted"><?php echo $request['sector_name']; ?></small>
                                </td>
                                <td><?php echo $request['type_name']; ?></td>
                                <td>
                                    <?php echo date('M d', strtotime($request['start_date'])); ?> - 
                                    <?php echo date('M d, Y', strtotime($request['end_date'])); ?>
                                    <br>
                                    <small><?php echo $request['total_days']; ?> days</small>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo strtolower($request['status']); ?>">
                                        <?php echo $request['status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($request['approver_name']): ?>
                                        <?php echo htmlspecialchars($request['approver_name']); ?>
                                        <br>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y', strtotime($request['approved_at'])); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($request['approval_remarks'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    function updateDateTime() {
        document.getElementById('currentDateTime').textContent = 'UTC: 2025-03-13 08:46:57';
    }

    function updateLeaveBalance() {
        const userId = document.querySelector('select[name="user_id"]').value;
        const typeId = document.querySelector('select[name="type_id"]').value;
        const balanceInfo = document.getElementById('leaveBalanceInfo');
        
        if (userId && typeId) {
            // Fetch leave balance via AJAX
            fetch(`get_leave_balance.php?user_id=${userId}&type_id=${typeId}`)
                .then(response => response.json())
                .then(data => {
                    balanceInfo.innerHTML = `
                        <div class="balance-item">
                            <strong>Total:</strong> ${data.total_days} days
                        </div>
                        <div class="balance-item">
                            <strong>Used:</strong> ${data.used_days} days
                        </div>
                        <div class="balance-item">
                            <strong>Pending:</strong> ${data.pending_days} days
                        </div>
                        <div class="balance-item">
                            <strong>Available:</strong> ${data.available_days} days
                        </div>
                    `;
                });
            
            // Update attachment requirement
            const selectedType = document.querySelector(`select[name="type_id"] option[value="${typeId}"]`);
            const attachmentGroup = document.getElementById('attachmentGroup');
            attachmentGroup.style.display = 
                selectedType.dataset.requiresAttachment === '1' ? 'block' : 'none';
        }
    }

    function updateDuration() {
        const startDate = document.querySelector('input[name="start_date"]').value;
        const endDate = document.querySelector('input[name="end_date"]').value;
        const durationInfo = document.getElementById('durationInfo');
        
        if (startDate && endDate) {
            const start = new Date(startDate);
            const end = new Date(endDate);
            let days = 0;
            
            for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
                if (d.getDay() !== 0 && d.getDay() !== 6) {
                    days++;
                }
            }
            
            durationInfo.textContent = `Duration: ${days} working days`;
        }
    }

    // Initialize
    updateDateTime();
    if (document.querySelector('select[name="user_id"]')) {
        updateLeaveBalance();
        updateDuration();
    }
    </script>
</body>
</html>