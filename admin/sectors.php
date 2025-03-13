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
            case 'add':
                $name = sanitizeInput($_POST['sector_name']);
                $description = sanitizeInput($_POST['description']);
                
                $query = "INSERT INTO sectors (sector_name, description) VALUES (:name, :description)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "სექტორი წარმატებით დაემატა!";
                }
                break;
                
            case 'edit':
                $id = $_POST['sector_id'];
                $name = sanitizeInput($_POST['sector_name']);
                $description = sanitizeInput($_POST['description']);
                
                $query = "UPDATE sectors SET sector_name = :name, description = :description WHERE sector_id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':id', $id);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "სექტორი წარმატებით განახლდა!";
                }
                break;
                
            case 'delete':
                $id = $_POST['sector_id'];
                $query = "DELETE FROM sectors WHERE sector_id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $id);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "სექტორი წარმატებით წაიშალა!";
                }
                break;
        }
        
        header("Location: sectors.php");
        exit();
    }
}
?>

<?php include '../includes/header.php'; ?>

<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/sectors.css">
</head>

<div class="container">
    <div class="system-info">
        <div class="datetime-display">
            <span>მიმდინარე დრო UTC:</span>
            <span id="currentUTC">2025-03-13 10:56:32</span>
        </div>
        <div class="user-info">
            <span>მომხმარებელი:</span>
            <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'Gurijajo'); ?></span>
        </div>
    </div>

    <header class="page-header">
        <h1>სექტორების მართვა</h1>
        <button class="btn btn-primary" onclick="showAddForm()">
            <i class="fas fa-plus"></i> ახალი სექტორის დამატება
        </button>
    </header>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success fade-in">
            <?php 
            echo $_SESSION['success_message'];
            unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Add/Edit Form -->
    <div id="sectorForm" class="card" style="display: none;">
        <form method="POST" action="">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="sector_id" id="sectorId">
            
            <div class="form-group">
                <label class="form-label" for="sector_name">სექტორის სახელი</label>
                <input type="text" id="sector_name" name="sector_name" class="form-input" required>
            </div>
            
            <div class="form-group">
                <label class="form-label" for="description">აღწერა</label>
                <textarea id="description" name="description" class="form-input" rows="3"></textarea>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">შენახვა</button>
                <button type="button" class="btn btn-danger" onclick="hideForm()">გაუქმება</button>
            </div>
        </form>
    </div>

    <!-- Sectors List -->
    <div class="table-container mt-2">
        <table class="table">
            <thead>
                <tr>
                    <th>სექტორის სახელი</th>
                    <th>აღწერა</th>
                    <th>თანამშრომლები</th>
                    <th>მოქმედებები</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $query = "SELECT s.*, COUNT(u.user_id) as employee_count 
                         FROM sectors s 
                         LEFT JOIN users u ON s.sector_id = u.sector_id 
                         GROUP BY s.sector_id";
                $stmt = $db->query($query);
                
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['sector_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['description']) . "</td>";
                    echo "<td>" . $row['employee_count'] . "</td>";
                    echo "<td class='actions'>";
                    echo "<button class='btn btn-primary btn-sm' onclick='editSector(" . json_encode($row) . ")'>";
                    echo "<i class='fas fa-edit'></i> რედაქტირება</button>";
                    echo "<form method='POST' action='' style='display: inline;'>";
                    echo "<input type='hidden' name='action' value='delete'>";
                    echo "<input type='hidden' name='sector_id' value='" . $row['sector_id'] . "'>";
                    echo "<button type='submit' class='btn btn-danger btn-sm' onclick='return confirm(\"დარწმუნებული ხართ?\")'>";
                    echo "<i class='fas fa-trash'></i> წაშლა</button>";
                    echo "</form>";
                    echo "</td>";
                    echo "</tr>";
                }
                ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function updateDateTime() {
    const now = new Date();
    const utcString = now.toISOString().slice(0, 19).replace('T', ' ');
    document.getElementById('currentUTC').textContent = utcString;
}

setInterval(updateDateTime, 1000);

function showAddForm() {
    document.getElementById('formAction').value = 'add';
    document.getElementById('sectorId').value = '';
    document.getElementById('sector_name').value = '';
    document.getElementById('description').value = '';
    document.getElementById('sectorForm').style.display = 'block';
}

function hideForm() {
    document.getElementById('sectorForm').style.display = 'none';
}

function editSector(sector) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('sectorId').value = sector.sector_id;
    document.getElementById('sector_name').value = sector.sector_name;
    document.getElementById('description').value = sector.description;
    document.getElementById('sectorForm').style.display = 'block';
}

// Initialize datetime
updateDateTime();
</script>

<?php include '../includes/footer.php'; ?>