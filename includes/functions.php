<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Database {
    private $host = "localhost";
    private $db_name = "agroco_db";
    private $username = "root";
    private $password = "";
    private $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            echo "Connection error: " . $e->getMessage();
        }

        return $this->conn;
    }
}

function logActivity($db, $user_id, $action_type, $action_details, 
                    $affected_record_id = null, $affected_table = null, 
                    $old_values = null, $new_values = null) {
    $query = "INSERT INTO activity_logs 
              (user_id, action_type, action_details, ip_address, user_agent,
               affected_record_id, affected_table, old_values, new_values) 
              VALUES 
              (:user_id, :action_type, :action_details, :ip_address, :user_agent,
               :affected_record_id, :affected_table, :old_values, :new_values)";
              
    $stmt = $db->prepare($query);
    return $stmt->execute([
        ':user_id' => $user_id,
        ':action_type' => $action_type,
        ':action_details' => $action_details,
        ':ip_address' => $_SERVER['REMOTE_ADDR'],
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'],
        ':affected_record_id' => $affected_record_id,
        ':affected_table' => $affected_table,
        ':old_values' => $old_values ? json_encode($old_values) : null,
        ':new_values' => $new_values ? json_encode($new_values) : null
    ]);
}

function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        // Get the current script path
        $current_path = $_SERVER['SCRIPT_NAME'];
        
        // Check if we're already on the login page
        if (strpos($current_path, 'index.php') === false) {
            header("Location: /index.php");
            exit();
        }
    } else {
        // If we're on the login page but already logged in
        if (strpos($_SERVER['SCRIPT_NAME'], 'index.php') !== false) {
            header("Location: /admin/dashboard.php");
            exit();
        }
    }
}

function checkAdminAccess() {
    // First ensure user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: /index.php");
        exit();
    }
    
    // Then check role
    if (!isset($_SESSION['role_id']) || $_SESSION['role_id'] > 2) {
        // Store the current URL in session for redirect after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        header("Location: /unauthorized.php");
        exit();
    }
}

function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function getCurrentUTC() {
    return '2025-03-13 09:04:21';
}

function getCurrentUser() {
    return isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
}

function hashPassword($password) {
    // Use BCRYPT algorithm with cost factor of 12
    $options = [
        'cost' => 12
    ];
    
    // Generate the hash
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT, $options);
    
    // Check if hashing was successful
    if ($hashedPassword === false) {
        throw new Exception('Password hashing failed');
    }
    
    return $hashedPassword;
}

function sanitizeString($str) {
    return htmlspecialchars(strip_tags(trim($str)), ENT_QUOTES, 'UTF-8');
}