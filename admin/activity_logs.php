<?php
require_once '../includes/functions.php';
checkLogin();
checkAdminAccess();

$database = new Database();
$db = $database->getConnection();

// Pagination settings
$records_per_page = 50;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $records_per_page;

// Filtering
$action_type = isset($_GET['action_type']) ? sanitizeInput($_GET['action_type']) : '';
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$date_from = isset($_GET['date_from']) ? sanitizeInput($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitizeInput($_GET['date_to']) : '';

// Build query conditions
$conditions = [];
$params = [];

if ($action_type) {
    $conditions[] = "action_type = :action_type";
    $params[':action_type'] = $action_type;
}

if ($user_id) {
    $conditions[] = "user_id = :user_id";
    $params[':user_id'] = $user_id;
}

if ($date_from) {
    $conditions[] = "created_at >= :date_from";
    $params[':date_from'] = $date_from . ' 00:00:00';
}

if ($date_to) {
    $conditions[] = "created_at <= :date_to";
    $params[':date_to'] = $date_to . ' 23:59:59';
}

$where_clause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Get total records for pagination
$count_query = "SELECT COUNT(*) FROM activity_logs $where_clause";
$stmt = $db->prepare($count_query);
$stmt->execute($params);
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $records_per_page);

// Get activity logs with user details
$query = "SELECT al.*, 
                 CONCAT(u.first_name, ' ', u.last_name) as user_name,
                 u.username
          FROM activity_logs al
          JOIN users u ON al.user_id = u.user_id
          $where_clause
          ORDER BY al.created_at DESC
          LIMIT :offset, :limit";

$stmt = $db->prepare($query);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->bindValue(':limit', $records_per_page, PDO::PARAM_INT);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get unique action types for filter
$action_types = $db->query("SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type")
                  ->fetchAll(PDO::FETCH_COLUMN);

// Get users for filter
$users = $db->query("SELECT user_id, username, first_name, last_name 
                     FROM users ORDER BY username")
           ->fetchAll(PDO::FETCH_ASSOC);
?>


<?php require '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - Agroco HRMS</title>
    <style>
        /* ... Previous CSS styles ... */

    </style>
</head>
<body>
        <div class="container">
        <h1>Activity Logs</h1>

        <!-- Filters -->
        <div class="filters-section">
            <form method="GET" action="">
                <div class="filters-grid">
                    <div class="form-group">
                        <label class="form-label">Action Type</label>
                        <select name="action_type" class="form-input">
                            <option value="">All Actions</option>
                            <?php foreach ($action_types as $type): ?>
                                <option value="<?php echo $type; ?>" 
                                        <?php echo $action_type === $type ? 'selected' : ''; ?>>
                                    <?php echo $type; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">User</label>
                        <select name="user_id" class="form-input">
                            <option value="">All Users</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['user_id']; ?>"
                                        <?php echo $user_id === $user['user_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-input" 
                               value="<?php echo $date_from; ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-input" 
                               value="<?php echo $date_to; ?>">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Apply Filters</button>
                <a href="activity_logs.php" class="btn btn-secondary">Reset</a>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Details</th>
                        <th>Affected Record</th>
                        <th>IP Address</th>
                        <th>Values</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><?php echo $log['created_at']; ?></td>
                            <td><?php echo htmlspecialchars($log['user_name']); ?></td>
                            <td>
                                <span class="badge badge-<?php echo strtolower($log['action_type']); ?>">
                                    <?php echo $log['action_type']; ?>
                                </span>
                            </td>
                            <td class="log-details">
                                <?php echo htmlspecialchars($log['action_details']); ?>
                            </td>
                            <td>
                                <?php if ($log['affected_table'] && $log['affected_record_id']): ?>
                                    <?php echo $log['affected_table']; ?> #<?php echo $log['affected_record_id']; ?>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $log['ip_address']; ?></td>
                            <td>
                                <?php if ($log['old_values'] || $log['new_values']): ?>
                                    <button class="btn btn-sm btn-info" 
                                            onclick="showValues(<?php echo htmlspecialchars(json_encode($log)); ?>)">
                                        View Changes
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>&action_type=<?php echo urlencode($action_type); ?>&user_id=<?php echo $user_id; ?>&date_from=<?php echo urlencode($date_from); ?>&date_to=<?php echo urlencode($date_to); ?>" 
                       class="page-link <?php echo $page === $i ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Values Popup -->
    <div class="overlay" id="overlay"></div>
    <div class="values-popup" id="valuesPopup">
        <h3>Changes</h3>
        <div id="oldValues">
            <h4>Previous Values:</h4>
            <pre></pre>
        </div>
        <div id="newValues">
            <h4>New Values:</h4>
            <pre></pre>
        </div>
        <button class="btn btn-secondary" onclick="hideValues()">Close</button>
    </div>
    </div>

    <script>
    function updateDateTime() {
        document.getElementById('currentDateTime').textContent = 'UTC: 2025-03-13 08:14:32';
    }

    function showValues(log) {
        document.getElementById('overlay').style.display = 'block';
        document.getElementById('valuesPopup').style.display = 'block';
        
        const oldValuesElem = document.querySelector('#oldValues pre');
        const newValuesElem = document.querySelector('#newValues pre');
        
        oldValuesElem.textContent = log.old_values ? 
            JSON.stringify(JSON.parse(log.old_values), null, 2) : 'No previous values';
        newValuesElem.textContent = log.new_values ? 
            JSON.stringify(JSON.parse(log.new_values), null, 2) : 'No new values';
    }

    function hideValues() {
        document.getElementById('overlay').style.display = 'none';
        document.getElementById('valuesPopup').style.display = 'none';
    }

    // Initialize datetime
    updateDateTime();
    </script>
</body>
</html>