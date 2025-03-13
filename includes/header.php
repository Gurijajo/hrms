<?php
require_once __DIR__ . '/functions.php';

checkLogin();

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title ?? 'Admin Panel'; ?> - Agroco HRMS</title>
    <link rel="stylesheet" href="../assets/css/header.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        
    </style>
</head>
<body class="sidebar-visible">
    <!-- Header -->
    <header class="header">
        <div class="header-left">
            <button class="menu-toggle">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-user"></i>
                <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'Gurijajo'); ?></span>
            </div>
            <a href="../auth/logout.php" class="btn btn-danger">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </div>
    </header>

    <!-- Sidebar -->
    <aside class="sidebar">
        <!-- Dashboard Section -->
        <div class="nav-section">
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="/admin/dashboard.php" class="nav-link <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                        <i class="fas fa-home"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Employee Management -->
        <div class="nav-section">
            <div class="nav-section-title">Employee Management</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="/admin/users.php" class="nav-link <?php echo $current_page === 'users' ? 'active' : ''; ?>">
                        <i class="fas fa-users"></i>
                        <span>Users</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/sectors.php" class="nav-link <?php echo $current_page === 'sectors' ? 'active' : ''; ?>">
                        <i class="fas fa-building"></i>
                        <span>Sectors</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/manage_experience_education.php" class="nav-link <?php echo $current_page === 'education' ? 'active' : ''; ?>">
                        <i class="fas fa-graduation-cap"></i>
                        <span>Education</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/manage_experience_education.php" class="nav-link <?php echo $current_page === 'experience' ? 'active' : ''; ?>">
                        <i class="fas fa-briefcase"></i>
                        <span>Experience</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Attendance & Leave -->
        <div class="nav-section">
            <div class="nav-section-title">Attendance & Leave</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="/admin/attendance.php" class="nav-link <?php echo $current_page === 'attendance' ? 'active' : ''; ?>">
                        <i class="fas fa-clock"></i>
                        <span>Attendance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/manage_attendance.php" class="nav-link <?php echo $current_page === 'manage_attendance' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-check"></i>
                        <span>Manage Attendance</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/leave_management.php" class="nav-link <?php echo $current_page === 'leave_management' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Leave Management</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/leave_balance.php" class="nav-link <?php echo $current_page === 'leave_balance' ? 'active' : ''; ?>">
                        <i class="fas fa-balance-scale"></i>
                        <span>Leave Balance</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Payroll Management -->
        <div class="nav-section">
            <div class="nav-section-title">Payroll Management</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="/admin/salaries.php" class="nav-link <?php echo $current_page === 'salaries' ? 'active' : ''; ?>">
                        <i class="fas fa-money-bill-wave"></i>
                        <span>Salaries</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/bonus_activities.php" class="nav-link <?php echo $current_page === 'bonus_activities' ? 'active' : ''; ?>">
                        <i class="fas fa-gift"></i>
                        <span>Bonus Activities</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/tax_deduction.php" class="nav-link <?php echo $current_page === 'tax_deduction' ? 'active' : ''; ?>">
                        <i class="fas fa-percent"></i>
                        <span>Tax Deduction</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/salary_slips.php" class="nav-link <?php echo $current_page === 'salary_slips' ? 'active' : ''; ?>">
                        <i class="fas fa-file-invoice-dollar"></i>
                        <span>Salary Slips</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- Loans & Advances -->
        <div class="nav-section">
            <div class="nav-section-title">Loans & Advances</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="/admin/loans_advance.php" class="nav-link <?php echo $current_page === 'loans_advance' ? 'active' : ''; ?>">
                        <i class="fas fa-hand-holding-usd"></i>
                        <span>Loans & Advances</span>
                    </a>
                </li>
            </ul>
        </div>

        <!-- System -->
        <div class="nav-section">
            <div class="nav-section-title">System</div>
            <ul class="nav-list">
                <li class="nav-item">
                    <a href="/admin/work_schedules.php" class="nav-link <?php echo $current_page === 'work_schedules' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-week"></i>
                        <span>Work Schedules</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a href="/admin/activity_logs.php" class="nav-link <?php echo $current_page === 'activity_logs' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i>
                        <span>Activity Logs</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

   


    <script>
document.addEventListener("DOMContentLoaded", function() {
    const menuToggle = document.querySelector(".menu-toggle");
    const body = document.body;

    menuToggle.addEventListener("click", function() {
        if (window.innerWidth <= 768) {
            // Mobile behavior - toggle sidebar-visible class
            body.classList.toggle('sidebar-visible');
        } else {
            // Desktop behavior - toggle sidebar-collapsed class
            body.classList.toggle('sidebar-collapsed');
        }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            // Remove mobile class when switching to desktop
            body.classList.remove('sidebar-visible');
        } else {
            // Remove desktop class when switching to mobile
            body.classList.remove('sidebar-collapsed');
        }
    });
});
    function updateDateTime() {
        document.getElementById('currentDateTime').textContent = 'UTC: <?php echo $current_utc; ?>';
    }

    // Initialize
    updateDateTime();
    </script>
</body>
</html>