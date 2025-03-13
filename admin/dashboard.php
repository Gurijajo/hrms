<?php
require_once '../includes/functions.php';
require_once '../config/database.php';


$database = new Database();
$db = $database->getConnection();


$queries = [
    "SELECT COUNT(*) as employee_count FROM users u JOIN roles r ON u.role_id = r.role_id WHERE r.role_name = 'employee'",
    "SELECT COUNT(*) as sector_count FROM sectors",
    "SELECT COUNT(*) as attendance_today FROM attendance WHERE DATE(date) = CURDATE()",
    "SELECT COUNT(*) as pending_approvals FROM attendance WHERE approved_by IS NULL"
];

$stats = [];
foreach ($queries as $query) {
    $stmt = $db->query($query);
    $stats[] = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<?php include '../includes/header.php'; ?>
<?php include '../includes/navbar.php'; ?>

<div class="container">
    <header class="dashboard-header">
        <h1>Dashboard</h1>
        <div class="current-info">
            <span>Current Date: <?php echo date('Y-m-d H:i:s'); ?></span>
            <span>Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
        </div>
    </header>

    <div class="grid">
        <div class="card">
            <h3>Employees</h3>
            <p class="stat-number"><?php echo $stats[0]['employee_count']; ?></p>
            <a href="users.php" class="btn btn-primary">Manage Employees</a>
        </div>

        <div class="card">
            <h3>Sectors</h3>
            <p class="stat-number"><?php echo $stats[1]['sector_count']; ?></p>
            <a href="sectors.php" class="btn btn-primary">Manage Sectors</a>
        </div>

        <div class="card">
            <h3>Today's Attendance</h3>
            <p class="stat-number"><?php echo $stats[2]['attendance_today']; ?></p>
            <a href="attendance.php" class="btn btn-primary">View Attendance</a>
        </div>

        <div class="card">
            <h3>Pending Approvals</h3>
            <p class="stat-number"><?php echo $stats[3]['pending_approvals']; ?></p>
            <a href="attendance.php?filter=pending" class="btn btn-primary">Review Pending</a>
        </div>
    </div>

    <div class="recent-activities mt-2">
        <h2>Recent Activities</h2>
        <div class="table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Activity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $query = "SELECT 
                                a.date,
                                CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                                a.status,
                                CASE 
                                    WHEN a.approved_by IS NOT NULL THEN 'Approved'
                                    ELSE 'Pending'
                                END as approval_status
                            FROM attendance a
                            JOIN users u ON a.user_id = u.user_id
                            ORDER BY a.date DESC
                            LIMIT 5";
                    
                    $stmt = $db->query($query);
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        echo "<tr>";
                        echo "<td>" . htmlspecialchars($row['date']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['employee_name']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
                        echo "<td>" . htmlspecialchars($row['approval_status']) . "</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
