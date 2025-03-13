<?php
require_once './includes/functions.php';
require_once './config/database.php';

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: admin/dashboard.php");
    exit();
}

$database = new Database();
$db = $database->getConnection();

$login_error = '';
$is_blocked = false;
$current_utc = '2025-03-13 08:50:34';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username']);
    
    // Check if user exists and get their status
    $query = "SELECT user_id, username, password, role_id, first_name, last_name, 
                     status, failed_attempts, blocked_until 
              FROM users 
              WHERE username = :username";
    $stmt = $db->prepare($query);
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Check if user is blocked
    if ($user && $user['blocked_until'] && strtotime($user['blocked_until']) > time()) {
        $is_blocked = true;
        $block_time = strtotime($user['blocked_until']) - time();
        $login_error = "Account is temporarily blocked. Please try again in " . 
                      ceil($block_time / 60) . " minutes.";
    } else {
        if ($user && $user['status'] === 'active' && 
            verifyPassword($_POST['password'], $user['password'])) {
            // Successful login
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            
            // Reset failed attempts
            $query = "UPDATE users SET 
                     failed_attempts = 0,
                     blocked_until = NULL,
                     last_login = CURRENT_TIMESTAMP 
                     WHERE user_id = :user_id";
            $stmt = $db->prepare($query);
            $stmt->execute([':user_id' => $user['user_id']]);
            
            // Log successful login
            logActivity(
                $db, 
                $user['user_id'], 
                'LOGIN', 
                'Successful login'
            );
            
            header("Location: admin/dashboard.php");
            exit();
        } else if ($user) {
            // Failed login attempt
            $failed_attempts = ($user['failed_attempts'] ?? 0) + 1;
            
            if ($failed_attempts >= 5) {
                // Block user for 30 minutes
                $query = "UPDATE users SET 
                         failed_attempts = :attempts,
                         blocked_until = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 MINUTE)
                         WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':attempts' => $failed_attempts,
                    ':user_id' => $user['user_id']
                ]);
                
                $login_error = "Too many failed attempts. Account blocked for 30 minutes.";
                $is_blocked = true;
            } else {
                // Update failed attempts
                $query = "UPDATE users SET failed_attempts = :attempts 
                         WHERE user_id = :user_id";
                $stmt = $db->prepare($query);
                $stmt->execute([
                    ':attempts' => $failed_attempts,
                    ':user_id' => $user['user_id']
                ]);
                
                $login_error = "Invalid username or password. " . 
                             (5 - $failed_attempts) . " attempts remaining.";
            }
        } else {
            $login_error = "Invalid username or password.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Agroco HRMS</title>
    <style>
        :root {
            --primary: #2c3e50;
            --secondary: #95a5a6;
            --success: #27ae60;
            --danger: #e74c3c;
            --warning: #f1c40f;
            --info: #3498db;
            --light: #ecf0f1;
            --dark: #34495e;
            --radius: 8px;
            --shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .system-info {
            background: var(--primary);
            color: white;
            padding: 0.5rem 1rem;
            text-align: right;
            font-size: 0.875rem;
        }

        .login-container {
            max-width: 400px;
            margin: auto;
            padding: 2rem;
            background: white;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .login-header img {
            max-width: 150px;
            margin-bottom: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--dark);
        }

        .form-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--secondary);
            border-radius: var(--radius);
            font-size: 1rem;
            box-sizing: border-box;
        }

        .btn-login {
            width: 100%;
            padding: 0.75rem;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .btn-login:hover {
            background: var(--dark);
        }

        .btn-login:disabled {
            background: var(--secondary);
            cursor: not-allowed;
        }

        .error-message {
            background: var(--danger);
            color: white;
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
        }

        .forgot-password {
            text-align: center;
            margin-top: 1rem;
        }

        .forgot-password a {
            color: var(--info);
            text-decoration: none;
        }

        .forgot-password a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="system-info">
        <div class="datetime" id="currentDateTime">
            UTC: <?php echo $current_utc; ?>
        </div>
    </div>

    <div class="login-container">
        <div class="login-header">
            <img src="assets/images/logo.png" alt="Agroco HRMS" onerror="this.style.display='none'">
            <h1>Agroco HRMS</h1>
        </div>

        <?php if ($login_error): ?>
            <div class="error-message">
                <?php echo $login_error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="form-group">
                <label class="form-label" for="username">Username</label>
                <input type="text" id="username" name="username" class="form-input" 
                       required <?php echo $is_blocked ? 'disabled' : ''; ?>>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-input" 
                       required <?php echo $is_blocked ? 'disabled' : ''; ?>>
            </div>

            <button type="submit" class="btn-login" <?php echo $is_blocked ? 'disabled' : ''; ?>>
                Login
            </button>
        </form>

        <div class="forgot-password">
            <a href="forgot-password.php">Forgot your password?</a>
        </div>
    </div>

    <script>
    function updateDateTime() {
        document.getElementById('currentDateTime').textContent = 'UTC: <?php echo $current_utc; ?>';
    }

    // Initialize datetime
    updateDateTime();
    </script>
</body>
</html>