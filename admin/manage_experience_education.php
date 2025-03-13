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
                          "Added experience record for user ID: $user_id at $company");
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
                          "Added education record for user ID: $user_id at $institution");
                break;
        }
        
        header("Location: experience_education.php");
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Experience & Education Management - Agroco HRMS</title>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #34495e;
            --accent: #3498db;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f1c40f;
            --light: #ecf0f1;
            --border: #ddd;
            --shadow: 0 2px 4px rgba(0,0,0,0.1);
            --radius: 8px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background: var(--light);
            color: var(--primary);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .system-header {
            background: var(--primary);
            color: white;
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .datetime {
            font-family: 'Roboto Mono', monospace;
            font-size: 1.1rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .page-title {
            margin-bottom: 2rem;
            border-bottom: 2px solid var(--accent);
            padding-bottom: 0.5rem;
        }

        .tabs {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .tab {
            padding: 1rem 2rem;
            background: white;
            border-radius: var(--radius);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .tab.active {
            background: var(--accent);
            color: white;
        }

        .form-section {
            background: white;
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            transition: all 0.3s ease;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }

        .records-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .record-card {
            background: white;
            padding: 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .record-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--border);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: var(--radius);
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="system-header">
            <div class="datetime" id="currentDateTime">
                UTC: 2025-03-13 07:21:29
            </div>
            <div class="user-info">
                <span>User:</span>
                <strong>Gurijajo</strong>
            </div>
        </div>

        <h1 class="page-title">Experience & Education Management</h1>

        <div class="tabs">
            <div class="tab active" onclick="switchTab('experience')">Work Experience</div>
            <div class="tab" onclick="switchTab('education')">Education</div>
        </div>

        <!-- Experience Form -->
        <div id="experienceForm" class="form-section">
            <h2>Add Work Experience</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_experience">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Employee</label>
                        <select name="user_id" class="form-input" required>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['user_id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . 
                                          $employee['last_name'] . ' (' . $employee['sector_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Company Name</label>
                        <input type="text" name="company_name" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Position</label>
                        <input type="text" name="position" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-input">
                    </div>

                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-input" rows="4"></textarea>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Add Experience</button>
            </form>
        </div>

        <!-- Education Form -->
        <div id="educationForm" class="form-section" style="display: none;">
            <h2>Add Education</h2>
            <form method="POST" action="">
                <input type="hidden" name="action" value="add_education">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Employee</label>
                        <select name="user_id" class="form-input" required>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee['user_id']; ?>">
                                    <?php echo htmlspecialchars($employee['first_name'] . ' ' . 
                                          $employee['last_name'] . ' (' . $employee['sector_name'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Institution</label>
                        <input type="text" name="institution" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Degree</label>
                        <input type="text" name="degree" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Field of Study</label>
                        <input type="text" name="field_of_study" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-input" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-input">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Add Education</button>
            </form>
        </div>

        <!-- Records Display -->
        <div class="records-section">
            <h2>Recent Records</h2>
            <div class="records-grid">
                <?php
                // Get recent experience records
                $query = "SELECT e.*, 
                                CONCAT(u.first_name, ' ', u.last_name) as employee_name 
                         FROM experience e
                         JOIN users u ON e.user_id = u.user_id
                         ORDER BY e.created_at DESC LIMIT 5";
                $experiences = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);

                foreach ($experiences as $exp):
                ?>
                <div class="record-card">
                    <div class="record-header">
                        <h3><?php echo htmlspecialchars($exp['company_name']); ?></h3>
                        <span><?php echo htmlspecialchars($exp['employee_name']); ?></span>
                    </div>
                    <p><strong>Position:</strong> <?php echo htmlspecialchars($exp['position']); ?></p>
                    <p><strong>Period:</strong> <?php echo htmlspecialchars($exp['start_date']); ?> - 
                       <?php echo $exp['end_date'] ? htmlspecialchars($exp['end_date']) : 'Present'; ?></p>
                    <p><?php echo htmlspecialchars($exp['description']); ?></p>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
    function switchTab(tab) {
        const tabs = document.querySelectorAll('.tab');
        const forms = document.querySelectorAll('.form-section');
        
        tabs.forEach(t => t.classList.remove('active'));
        event.target.classList.add('active');
        
        if (tab === 'experience') {
            document.getElementById('experienceForm').style.display = 'block';
            document.getElementById('educationForm').style.display = 'none';
        } else {
            document.getElementById('experienceForm').style.display = 'none';
            document.getElementById('educationForm').style.display = 'block';
        }
    }

    // Initialize datetime display
    function updateDateTime() {
        document.getElementById('currentDateTime').textContent = 'UTC: 2025-03-13 07:21:29';
    }

    // Initialize page
    updateDateTime();
    </script>
</body>
</html>