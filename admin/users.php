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
                $username = sanitizeInput($_POST['username']);
                $email = sanitizeInput($_POST['email']);
                $first_name = sanitizeInput($_POST['first_name']);
                $last_name = sanitizeInput($_POST['last_name']);
                $role_id = $_POST['role_id'];
                $sector_id = $_POST['sector_id'];
                $password = sanitizeInput($_POST['password']);
                $hashed_password = hashPassword($password);
                
                $query = "INSERT INTO users (username, password, email, first_name, last_name, role_id, sector_id) 
                         VALUES (:username, :password, :email, :first_name, :last_name, :role_id, :sector_id)";
                         
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':role_id', $role_id);
                $stmt->bindParam(':sector_id', $sector_id);
                
                if ($stmt->execute()) {
                    $_SESSION['success_message'] = "მომხმარებელი წარმატებით შეიქმნა!";
                }
                break;
                
            case 'edit':
                $user_id = $_POST['user_id'];
                $email = sanitizeInput($_POST['email']);
                $first_name = sanitizeInput($_POST['first_name']);
                $last_name = sanitizeInput($_POST['last_name']);
                $role_id = $_POST['role_id'];
                $sector_id = $_POST['sector_id'];
                $status = $_POST['status'];
                
                $query = "UPDATE users SET 
                         email = :email,
                         first_name = :first_name,
                         last_name = :last_name,
                         role_id = :role_id,
                         sector_id = :sector_id,
                         status = :status";
                
                $params = [
                    ':email' => $email,
                    ':first_name' => $first_name,
                    ':last_name' => $last_name,
                    ':role_id' => $role_id,
                    ':sector_id' => $sector_id,
                    ':status' => $status,
                    ':user_id' => $user_id
                ];
                
                if (isset($_POST['password']) && !empty($_POST['password'])) {
                    $new_password = sanitizeInput($_POST['password']);
                    $hashed_password = hashPassword($new_password);
                    $query .= ", password = :password";
                    $params[':password'] = $hashed_password;
                }
                
                $query .= " WHERE user_id = :user_id";
                
                $stmt = $db->prepare($query);
                if ($stmt->execute($params)) {
                    $_SESSION['success_message'] = "მომხმარებელი წარმატებით განახლდა!";
                }
                break;
        }
        
        header("Location: users.php");
        exit();
    }
}

// Get roles and sectors for dropdowns
$roles = $db->query("SELECT * FROM roles")->fetchAll(PDO::FETCH_ASSOC);
$sectors = $db->query("SELECT * FROM sectors")->fetchAll(PDO::FETCH_ASSOC);
?>

<?php include '../includes/header.php'; ?>
<!DOCTYPE html>
<html lang="ka">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/users.css">
</head>

<div class="container">
    <div class="system-info">
        <div class="datetime-display">
            <span>მიმდინარე დრო:</span>
            <span id="currentUTC"></span>
        </div>
        <div class="user-info">
            <span>მომხმარებელი:</span>
            <span><?php echo htmlspecialchars($_SESSION['username'] ?? 'Gurijajo'); ?></span>
        </div>
    </div>

    <div class="page-header flex-between">
        <h1>მომხმარებლების მართვა</h1>
        <button class="btn btn-primary" onclick="showAddForm()">
            <i class="fas fa-plus"></i> ახალი მომხმარებლის დამატება
        </button>
    </div>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success fade-in">
            <?php 
            echo $_SESSION['success_message'];
            unset($_SESSION['success_message']);
            ?>
        </div>
    <?php endif; ?>

    <!-- Add/Edit User Form -->
    <div id="userForm" class="card" style="display: none;">
        <form method="POST" action="" class="form-custom">
            <input type="hidden" name="action" id="formAction" value="add">
            <input type="hidden" name="user_id" id="userId">
            
            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label" for="username">მომხმარებლის სახელი</label>
                    <input type="text" id="username" name="username" class="form-input" required>
                </div>

                <div class="form-group" id="passwordGroup">
                    <label class="form-label" for="password">
                        პაროლი
                        <span id="passwordRequired">*</span>
                        <small id="passwordHint" style="display: none;">(დატოვეთ ცარიელი მიმდინარე პაროლის შესანარჩუნებლად)</small>
                    </label>
                    <input type="password" id="password" name="password" class="form-input">
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">ელ.ფოსტა</label>
                    <input type="email" id="email" name="email" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="first_name">სახელი</label>
                    <input type="text" id="first_name" name="first_name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="last_name">გვარი</label>
                    <input type="text" id="last_name" name="last_name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="role_id">როლი</label>
                    <select id="role_id" name="role_id" class="form-input" required>
                        <?php foreach ($roles as $role): ?>
                            <option value="<?php echo $role['role_id']; ?>">
                                <?php echo htmlspecialchars($role['role_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="sector_id">სექტორი</label>
                    <select id="sector_id" name="sector_id" class="form-input" required>
                        <?php foreach ($sectors as $sector): ?>
                            <option value="<?php echo $sector['sector_id']; ?>">
                                <?php echo htmlspecialchars($sector['sector_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group" id="statusGroup" style="display: none;">
                    <label class="form-label" for="status">სტატუსი</label>
                    <select id="status" name="status" class="form-input">
                        <option value="active">აქტიური</option>
                        <option value="inactive">არააქტიური</option>
                    </select>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">ცვლილებების შენახვა</button>
                <button type="button" class="btn btn-danger" onclick="hideForm()">გაუქმება</button>
            </div>
        </form>
    </div>

    <!-- Users List -->
    <div class="table-container mt-3">
        <table class="table">
            <thead>
                <tr>
                    <th>მომხმარებლის სახელი</th>
                    <th>სახელი</th>
                    <th>ელ.ფოსტა</th>
                    <th>როლი</th>
                    <th>სექტორი</th>
                    <th>სტატუსი</th>
                    <th>მოქმედებები</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $query = "SELECT u.*, r.role_name, s.sector_name 
                         FROM users u 
                         JOIN roles r ON u.role_id = r.role_id 
                         JOIN sectors s ON u.sector_id = s.sector_id
                         ORDER BY u.username";
                $stmt = $db->query($query);
                
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['role_name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['sector_name']) . "</td>";
                    echo "<td><span class='badge badge-" . 
                         ($row['status'] === 'active' ? 'success' : 'danger') . "'>" . 
                         ($row['status'] === 'active' ? 'აქტიური' : 'არააქტიური') . "</span></td>";
                    echo "<td class='actions'>";
                    echo "<button class='btn btn-primary btn-sm' onclick='editUser(" . json_encode($row) . ")'>";
                    echo "<i class='fas fa-edit'></i> რედაქტირება</button>";
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
    document.getElementById('userId').value = '';
    document.getElementById('username').value = '';
    document.getElementById('password').value = '';
    document.getElementById('email').value = '';
    document.getElementById('first_name').value = '';
    document.getElementById('last_name').value = '';
    document.getElementById('username').disabled = false;
    document.getElementById('password').required = true;
    document.getElementById('passwordRequired').style.display = 'inline';
    document.getElementById('passwordHint').style.display = 'none';
    document.getElementById('statusGroup').style.display = 'none';
    document.getElementById('userForm').style.display = 'block';
}

function hideForm() {
    document.getElementById('userForm').style.display = 'none';
}

function editUser(user) {
    document.getElementById('formAction').value = 'edit';
    document.getElementById('userId').value = user.user_id;
    document.getElementById('username').value = user.username;
    document.getElementById('password').value = '';
    document.getElementById('password').required = false;
    document.getElementById('passwordRequired').style.display = 'none';
    document.getElementById('passwordHint').style.display = 'inline';
    document.getElementById('email').value = user.email;
    document.getElementById('first_name').value = user.first_name;
    document.getElementById('last_name').value = user.last_name;
    document.getElementById('role_id').value = user.role_id;
    document.getElementById('sector_id').value = user.sector_id;
    document.getElementById('status').value = user.status;
    document.getElementById('username').disabled = true;
    document.getElementById('statusGroup').style.display = 'block';
    document.getElementById('userForm').style.display = 'block';
}

// Initialize datetime
updateDateTime();
</script>

<?php include '../includes/footer.php'; ?>