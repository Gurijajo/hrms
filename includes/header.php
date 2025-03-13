<?php
require_once __DIR__ . '/functions.php';

// შეამოწმეთ მომხმარებლის ავტორიზაცია
checkLogin();

// მიიღეთ მიმდინარე გვერდი აქტიური მენიუს დასათვალიერებლად
$current_page = basename($_SERVER['PHP_SELF'], '.php');

// დაარეგულირეთ მნიშვნელოვანი ცვლადები
$page_title = $page_title ?? 'ადმინისტრატორის პანელი';
$username = htmlspecialchars($_SESSION['username'] ?? 'სტუმარი');

// მიიღეთ მიმდინარე UTC დრო
$current_utc = gmdate('Y-m-d H:i:s');

// ფუნქცია, რათა შევამოწმოთ, არის თუ არა მენიუ აქტიური
function isActiveMenu($page, $current) {
    return $page === $current ? 'active' : '';
}

// მენიუს სტრუქტურა
$menuStructure = [
    'dashboard' => [
        'title' => 'მთავარი',
        'items' => [
            ['href' => '/admin/dashboard.php', 'icon' => 'fas fa-home', 'text' => 'მთავარი პანელი']
        ]
    ],
    'employee' => [
        'title' => 'მუშაკების მართვა',
        'items' => [
            ['href' => '/admin/users.php', 'icon' => 'fas fa-users', 'text' => 'მომხმარებლები'],
            ['href' => '/admin/sectors.php', 'icon' => 'fas fa-building', 'text' => 'სექტორები'],
            ['href' => '/admin/manage_experience_education.php', 'icon' => 'fas fa-briefcase', 'text' => 'გამოცდილება/განათლება']
        ]
    ],
    'attendance' => [
        'title' => 'წამყვანი და შვებულება',
        'items' => [
            ['href' => '/admin/attendance.php', 'icon' => 'fas fa-clock', 'text' => 'წამყვანი'],
            ['href' => '/admin/manage_attendance.php', 'icon' => 'fas fa-calendar-check', 'text' => 'გადახედვა წამყვანს'],
            ['href' => '/admin/leave_management.php', 'icon' => 'fas fa-calendar-alt', 'text' => 'შვებულების მართვა'],
            ['href' => '/admin/leave_balance.php', 'icon' => 'fas fa-balance-scale', 'text' => 'შვებულების ბალანსი']
        ]
    ],
    'payroll' => [
        'title' => 'ხელფასების მართვა',
        'items' => [
            ['href' => '/admin/salaries.php', 'icon' => 'fas fa-money-bill-wave', 'text' => 'ხელფასები'],
            ['href' => '/admin/bonus_activities.php', 'icon' => 'fas fa-gift', 'text' => 'ბონუსების აქტივობები'],
            ['href' => '/admin/tax_deduction.php', 'icon' => 'fas fa-percent', 'text' => 'ბეგარის შეწყვეტა'],
            ['href' => '/admin/salary_slips.php', 'icon' => 'fas fa-file-invoice-dollar', 'text' => 'ხელფასის სლიპები']
        ]
    ],
    'loans' => [
        'title' => 'სესხები და წინასწარი გადასახდელები',
        'items' => [
            ['href' => '/admin/loans_advance.php', 'icon' => 'fas fa-hand-holding-usd', 'text' => 'სესხები და წინასწარი გადასახდელები']
        ]
    ],
    'system' => [
        'title' => 'სისტემა',
        'items' => [
            ['href' => '/admin/work_schedules.php', 'icon' => 'fas fa-calendar-week', 'text' => 'სამუშაო გრაფიკები'],
            ['href' => '/admin/activity_logs.php', 'icon' => 'fas fa-history', 'text' => 'აქტივობის ჩანაწერები']
        ]
    ]
];
?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="description" content="Agroco HRMS - ადამიანური რესურსების მართვის სისტემა">
    <title><?php echo $page_title; ?> - Agroco HRMS</title>
    
    <!-- ფავიკონი -->
    <link rel="icon" type="image/x-icon" href="/assets/images/favicon.ico">
    
    <!-- CSS დამოკიდებულებები -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/header.css">
</head>
<body class="sidebar-visible">
    <!-- სათაური -->
    <header class="header">
        <div class="header-left">
            <button class="menu-toggle" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="header-right">
            <div class="user-info">
                <i class="fas fa-user"></i>
                <span><?php echo $username; ?></span>
            </div>
            <a href="../auth/logout.php" class="btn btn-danger" title="გამოსვლა">
                <i class="fas fa-sign-out-alt"></i>
                <span>გამოსვლა</span>
            </a>
        </div>
    </header>

    <!-- გვერდის მენიუ -->
    <aside class="sidebar">
        <?php foreach ($menuStructure as $section): ?>
            <div class="nav-section">
                <?php if (isset($section['title'])): ?>
                    <div class="nav-section-title"><?php echo $section['title']; ?></div>
                <?php endif; ?>
                <ul class="nav-list">
                    <?php foreach ($section['items'] as $item): 
                        $current = basename($item['href'], '.php');
                    ?>
                        <li class="nav-item">
                            <a href="<?php echo $item['href']; ?>" 
                               class="nav-link <?php echo isActiveMenu($current, $current_page); ?>"
                               title="<?php echo $item['text']; ?>">
                                <i class="<?php echo $item['icon']; ?>"></i>
                                <span><?php echo $item['text']; ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>
    </aside>

<script>
    // გვერდის მენიუს გადატრიალება ფუნქცია
function toggleSidebar() {
    document.body.classList.toggle('sidebar-collapsed');
    document.body.classList.toggle('sidebar-visible');
    
    // შენახვა გვერდის მდგომარეობის localStorage-ში
    const isSidebarCollapsed = document.body.classList.contains('sidebar-collapsed');
    localStorage.setItem('sidebarCollapsed', isSidebarCollapsed);
}

// დროის განახლების ფუნქცია
function updateDateTime() {
    const now = new Date();
    const utcYear = now.getUTCFullYear();
    const utcMonth = String(now.getUTCMonth() + 1).padStart(2, '0');
    const utcDay = String(now.getUTCDate()).padStart(2, '0');
    const utcHours = String(now.getUTCHours()).padStart(2, '0');
    const utcMinutes = String(now.getUTCMinutes()).padStart(2, '0');
    const utcSeconds = String(now.getUTCSeconds()).padStart(2, '0');
    
    const formattedDateTime = `${utcYear}-${utcMonth}-${utcDay} ${utcHours}:${utcMinutes}:${utcSeconds}`;
    document.getElementById('currentDateTime').textContent = `UTC: ${formattedDateTime}`;
}

// დაწყება დოკუმენტის დატვირთვისას
document.addEventListener('DOMContentLoaded', function() {
    // შეამოწმეთ თუ მენიუა გახსნილი
    const isSidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isSidebarCollapsed) {
        document.body.classList.add('sidebar-collapsed');
        document.body.classList.remove('sidebar-visible');
    }

    // განაახლეთ დრო ახლა და შემდეგ თითოეულ წამში
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // დაამატეთ მოვლენის მოსმენა ეკრანის ზომის ცვლილებისთვის
    let windowWidth = window.innerWidth;
    window.addEventListener('resize', function() {
        if (window.innerWidth !== windowWidth) {
            windowWidth = window.innerWidth;
            if (windowWidth <= 768) {
                document.body.classList.remove('sidebar-visible');
                document.body.classList.add('sidebar-collapsed');
            }
        }
    });
});

// sidebar-ის დაკეტვა როდესაც კლიკავთ მასზე მობილურ მოწყობილობაზე
document.addEventListener('click', function(event) {
    if (window.innerWidth <= 768) {
        const sidebar = document.querySelector('.sidebar');
        const menuToggle = document.querySelector('.menu-toggle');
        
        if (!sidebar.contains(event.target) && !menuToggle.contains(event.target)) {
            document.body.classList.remove('sidebar-visible');
            document.body.classList.add('sidebar-collapsed');
        }
    }
});
</script>
</body>
</html>
