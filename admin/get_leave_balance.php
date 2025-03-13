<?php
require_once '../includes/functions.php';
checkLogin();

$database = new Database();
$db = $database->getConnection();

$user_id = $_GET['user_id'] ?? 0;
$type_id = $_GET['type_id'] ?? 0;
$year = date('Y');

$query = "SELECT * FROM leave_balances 
          WHERE user_id = ? AND type_id = ? AND year = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id, $type_id, $year]);
$balance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$balance) {
    // Get default days from leave type
    $query = "SELECT days_per_year FROM leave_types WHERE type_id = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$type_id]);
    $leave_type = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Create new balance record
    $query = "INSERT INTO leave_balances 
              (user_id, type_id, year, total_days) 
              VALUES (?, ?, ?, ?)";
    $stmt = $db->prepare($query);
    $stmt->execute([
        $user_id, 
        $type_id, 
        $year, 
        $leave_type['days_per_year']
    ]);
    
    $balance = [
        'total_days' => $leave_type['days_per_year'],
        'used_days' => 0,
        'pending_days' => 0
    ];
}

header('Content-Type: application/json');
echo json_encode([
    'total_days' => (float)$balance['total_days'],
    'used_days' => (float)$balance['used_days'],
    'pending_days' => (float)$balance['pending_days'],
    'available_days' => (float)($balance['total_days'] - $balance['used_days'] - $balance['pending_days'])
]);