<?php
require_once '../includes/functions.php';
checkLogin();
checkAdminAccess();

$database = new Database();
$db = $database->getConnection();

// ფილტრაციისთვის ყველა სექტორის მიღება
$sectors_query = "SELECT DISTINCT s.sector_id, s.sector_name 
                 FROM sectors s 
                 JOIN users u ON s.sector_id = u.sector_id 
                 WHERE u.status = 'active' 
                 ORDER BY s.sector_name";
$sectors = $db->query($sectors_query)->fetchAll(PDO::FETCH_ASSOC);

// არჩეული სექტორის მიღება (თუ არის)
$selected_sector = isset($_GET['sector']) ? (int)$_GET['sector'] : 0;
$form_selected_sector = isset($_POST['form_sector']) ? (int)$_POST['form_sector'] : 0;

// ფორმების გაგზავნის დამუშავება
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_experience':
                $user_id = $_POST['user_id'];
                $company = sanitizeInput($_POST['company_name']);
                $position = sanitizeInput($_POST['position']);
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'] ?: null;
                $description = sanitizeInput($_POST['description']);
                
                $query = "INSERT INTO experience 
                         (user_id, company_name, position, start_date, end_date, description) 
                         VALUES 
                         (:user_id, :company, :position, :start_date, :end_date, :description)";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':company' => $company,
                    ':position' => $position,
                    ':start_date' => $start_date,
                    ':end_date' => $end_date,
                    ':description' => $description
                ]);

                logActivity($db, $_SESSION['user_id'], 'ADD_EXPERIENCE', 
                          "გამოცდილების ჩანაწერი დამატებულია მომხმარებლის ID: $user_id -ზე, კომპანია: $company");
                break;

            case 'add_education':
                $user_id = $_POST['user_id'];
                $institution = sanitizeInput($_POST['institution']);
                $degree = sanitizeInput($_POST['degree']);
                $field = sanitizeInput($_POST['field_of_study']);
                $start_date = $_POST['start_date'];
                $end_date = $_POST['end_date'] ?: null;
                
                $query = "INSERT INTO education 
                         (user_id, institution, degree, field_of_study, start_date, end_date) 
                         VALUES 
                         (:user_id, :institution, :degree, :field, :start_date, :end_date)";
                
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':user_id' => $user_id,
                    ':institution' => $institution,
                    ':degree' => $degree,
                    ':field' => $field,
                    ':start_date' => $start_date,
                    ':end_date' => $end_date
                ]);

                logActivity($db, $_SESSION['user_id'], 'ADD_EDUCATION', 
                          "განათლების ჩანაწერი დამატებულია მომხმარებლის ID: $user_id -ზე, დაწესებულება: $institution");
                break;
        }
        
        header("Location: manage_experience_education.php" . 
               ($selected_sector ? "?sector=" . $selected_sector : ""));
        exit();
    }
}

// თანამშრომლების მიღება სექტორის ფილტრის მიხედვით ფორმებისთვის
$employees_query = "SELECT u.user_id, u.first_name, u.last_name, r.role_name, s.sector_name, s.sector_id
                   FROM users u 
                   JOIN roles r ON u.role_id = r.role_id 
                   JOIN sectors s ON u.sector_id = s.sector_id 
                   WHERE u.status = 'active' " .
                   ($form_selected_sector ? "AND s.sector_id = :sector_id " : "") .
                   "ORDER BY s.sector_name, u.first_name, u.last_name";

$stmt = $db->prepare($employees_query);
if ($form_selected_sector) {
    $stmt->bindParam(':sector_id', $form_selected_sector, PDO::PARAM_INT);
}
$stmt->execute();
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ბოლო გამოცდილების ჩანაწერების მიღება (სექტორის ფილტრით)
$experience_query = "SELECT e.*, 
                           CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                           s.sector_name
                    FROM experience e
                    JOIN users u ON e.user_id = u.user_id
                    JOIN sectors s ON u.sector_id = s.sector_id
                    WHERE 1=1 " . 
                    ($selected_sector ? "AND u.sector_id = :sector_id " : "") .
                    "ORDER BY e.created_at DESC 
                    LIMIT 5";

$stmt = $db->prepare($experience_query);
if ($selected_sector) {
    $stmt->bindParam(':sector_id', $selected_sector, PDO::PARAM_INT);
}
$stmt->execute();
$experiences = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ბოლო განათლების ჩანაწერების მიღება (სექტორის ფილტრით)
$education_query = "SELECT ed.*, 
                          CONCAT(u.first_name, ' ', u.last_name) as employee_name,
                          s.sector_name
                   FROM education ed
                   JOIN users u ON ed.user_id = u.user_id
                   JOIN sectors s ON u.sector_id = s.sector_id
                   WHERE 1=1 " . 
                   ($selected_sector ? "AND u.sector_id = :sector_id " : "") .
                   "ORDER BY ed.created_at DESC 
                   LIMIT 5";

$stmt = $db->prepare($education_query);
if ($selected_sector) {
    $stmt->bindParam(':sector_id', $selected_sector, PDO::PARAM_INT);
}
$stmt->execute();
$education_records = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<?php require_once '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>გამოცდილებისა და განათლების მენეჯმენტი - Agroco HRMS</title>
</head>
<body>
    <div class="container">
        <div class="system-header">
            <div class="datetime" id="currentDateTime">
                UTC: 2025-03-13 12:36:20
            </div>
            <div class="user-info">
                <span>მომხმარებელი:</span>
                <strong>Gurijajo</strong>
            </div>
        </div>

        <h1 class="page-title">გამოცდილებისა და განათლების მენეჯმენტი</h1>

        <!-- სექტორის ფილტრაცია -->
        <div class="filter-section">
            <form method="GET" action="" class="filter-form">
                <div class="form-group" style="margin-bottom: 2rem;">
                    <label class="form-label">ჩანაწერების ფილტრაცია სექტორის მიხედვით:</label>
                    <div class="filter-controls">
                        <select name="sector" class="form-input" style="max-width: 300px;" onchange="this.form.submit()">
                            <option value="0">ყველა სექტორი</option>
                            <?php foreach ($sectors as $sector): ?>
                                <option value="<?php echo $sector['sector_id']; ?>" 
                                        <?php echo $selected_sector == $sector['sector_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sector['sector_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($selected_sector): ?>
                            <a href="?sector=0" class="btn btn-secondary">ფილტრის გასუფთავება</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <div class="tabs">
            <div class="tab active" onclick="switchTab('experience')">სამუშაო გამოცდილება</div>
            <div class="tab" onclick="switchTab('education')">განათლება</div>
        </div>
        <!-- სამუშაო გამოცდილების ფორმა -->
        <div id="experienceForm" class="form-section">
            <h2>სამუშაო გამოცდილების დამატება</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_experience">
                
                <div class="form-grid">
                    <!-- თანამშრომლების ფილტრაცია სექტორის მიხედვით -->
                    <div class="form-group">
                        <label class="form-label">თანამშრომლების ფილტრაცია სექტორის მიხედვით</label>
                        <select name="form_sector" class="form-input" onchange="filterEmployees(this.value, 'experience')">
                            <option value="0">ყველა სექტორი</option>
                            <?php foreach ($sectors as $sector): ?>
                                <option value="<?php echo $sector['sector_id']; ?>" 
                                        <?php echo $form_selected_sector == $sector['sector_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sector['sector_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- თანამშრომლის არჩევა -->
                    <div class="form-group">
                        <label class="form-label">თანამშრომელი</label>
                        <select name="user_id" class="form-input" id="experienceEmployeeSelect" required>
                            <option value="">აირჩიეთ თანამშრომელი</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['user_id']; ?>" 
                                        data-sector="<?php echo $employee['sector_id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . 
                                          $employee['last_name'] . ' (' . $employee['sector_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">კომპანიის სახელი</label>
                        <input type="text" name="company_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">პოზიცია</label>
                        <input type="text" name="position" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">დაწყების თარიღი</label>
                        <input type="date" name="start_date" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">დასრულების თარიღი</label>
                        <input type="date" name="end_date" class="form-input">
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label">აღწერა</label>
                        <textarea name="description" class="form-input" rows="4"></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">გამოცდილების დამატება</button>
            </form>
        </div>

        <!-- განათლების ფორმა -->
        <div id="educationForm" class="form-section" style="display: none;">
            <h2>განათლების დამატება</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_education">
                
                <div class="form-grid">
                    <!-- თანამშრომლების ფილტრაცია სექტორის მიხედვით -->
                    <div class="form-group">
                        <label class="form-label">თანამშრომლების ფილტრაცია სექტორის მიხედვით</label>
                        <select name="form_sector" class="form-input" onchange="filterEmployees(this.value, 'education')">
                            <option value="0">ყველა სექტორი</option>
                            <?php foreach ($sectors as $sector): ?>
                                <option value="<?php echo $sector['sector_id']; ?>" 
                                        <?php echo $form_selected_sector == $sector['sector_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($sector['sector_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- თანამშრომლის არჩევა -->
                    <div class="form-group">
                        <label class="form-label">თანამშრომელი</label>
                        <select name="user_id" class="form-input" id="educationEmployeeSelect" required>
                            <option value="">აირჩიეთ თანამშრომელი</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['user_id']; ?>" 
                                        data-sector="<?php echo $employee['sector_id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . 
                                          $employee['last_name'] . ' (' . $employee['sector_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">სასწავლო დაწესებულება</label>
                        <input type="text" name="institution" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">დიპლომი</label>
                        <input type="text" name="degree" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">სასწავლო მიმართულება</label>
                        <input type="text" name="field_of_study" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">დაწყების თარიღი</label>
                        <input type="date" name="start_date" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">დასრულების თარიღი</label>
                        <input type="date" name="end_date" class="form-input">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">განათლების დამატება</button>
            </form>
        </div>

        <!-- ჩანაწერების ჩვენება -->
        <div class="records-section">
            <h2>ბოლო ჩანაწერები</h2>
            
            <!-- გამოცდილების ჩანაწერები -->
            <div id="experienceRecords" class="records-grid">
                <?php if (empty($experiences)): ?>
                    <div class="no-records">არჩეულ სექტორში გამოცდილების ჩანაწერები ვერ მოიძებნა.</div>
                <?php else: ?>
                    <?php foreach ($experiences as $exp): ?>
                    <div class="record-card">
                        <div class="record-header">
                            <h3><?php echo htmlspecialchars($exp['company_name']); ?></h3>
                            <span class="sector-badge"><?php echo htmlspecialchars($exp['sector_name']); ?></span>
                        </div>
                        <p class="employee-name"><?php echo htmlspecialchars($exp['employee_name']); ?></p>
                        <p><strong>პოზიცია:</strong> <?php echo htmlspecialchars($exp['position']); ?></p>
                        <p><strong>პერიოდი:</strong> <?php echo htmlspecialchars($exp['start_date']); ?> - 
                           <?php echo $exp['end_date'] ? htmlspecialchars($exp['end_date']) : 'მიმდინარე'; ?></p>
                        <p><?php echo htmlspecialchars($exp['description']); ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- განათლების ჩანაწერები -->
            <div id="educationRecords" class="records-grid" style="display: none;">
                <?php if (empty($education_records)): ?>
                    <div class="no-records">არჩეულ სექტორში განათლების ჩანაწერები ვერ მოიძებნა.</div>
                <?php else: ?>
                    <?php foreach ($education_records as $edu): ?>
                    <div class="record-card">
                        <div class="record-header">
                            <h3><?php echo htmlspecialchars($edu['institution']); ?></h3>
                            <span class="sector-badge"><?php echo htmlspecialchars($edu['sector_name']); ?></span>
                        </div>
                        <p class="employee-name"><?php echo htmlspecialchars($edu['employee_name']); ?></p>
                        <p><strong>დიპლომი:</strong> <?php echo htmlspecialchars($edu['degree']); ?></p>
                        <p><strong>სასწავლო მიმართულება:</strong> <?php echo htmlspecialchars($edu['field_of_study']); ?></p>
                        <p><strong>პერიოდი:</strong> <?php echo htmlspecialchars($edu['start_date']); ?> - 
                           <?php echo $edu['end_date'] ? htmlspecialchars($edu['end_date']) : 'მიმდინარე'; ?></p>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
    function filterEmployees(sectorId, formType) {
        const selectElement = document.getElementById(formType + 'EmployeeSelect');
        const options = selectElement.getElementsByTagName('option');
        
        for (let option of options) {
            if (option.value === "") continue; // თანადგომა: ადგილობრივი ოფცია გამოტოვეთ
            
            const employeeSector = option.getAttribute('data-sector');
            if (sectorId === "0" || employeeSector === sectorId) {
                option.style.display = "";
            } else {
                option.style.display = "none";
            }
        }
        
        // აღადგინე არჩევანი, თუ მიმდინარე არჩევანი დამალულია
        if (selectElement.selectedOptions[0].style.display === "none") {
            selectElement.value = "";
        }
    }

    function switchTab(tab) {
        const tabs = document.querySelectorAll('.tab');
        const forms = document.querySelectorAll('.form-section');
        const currentSector = new URLSearchParams(window.location.search).get('sector') || '0';
        
        tabs.forEach(t => t.classList.remove('active'));
        event.target.classList.add('active');
        
        if (tab === 'experience') {
            document.getElementById('experienceForm').style.display = 'block';
            document.getElementById('educationForm').style.display = 'none';
            document.getElementById('experienceRecords').style.display = 'grid';
            document.getElementById('educationRecords').style.display = 'none';
        } else {
            document.getElementById('experienceForm').style.display = 'none';
            document.getElementById('educationForm').style.display = 'block';
            document.getElementById('experienceRecords').style.display = 'none';
            document.getElementById('educationRecords').style.display = 'grid';
        }
    }

    function updateDateTime() {
        document.getElementById('currentDateTime').textContent = 'UTC: 2025-03-13 12:39:10';
    }

    // გვერდის ინიციალიზაცია
    updateDateTime();
    </script>
</body>
</html>
