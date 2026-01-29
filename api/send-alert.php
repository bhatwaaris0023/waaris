<?php
/**
 * API Endpoint - Send Customer Alerts
 * Allows admins to programmatically send alerts to customers
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../admin/auth.php';

header('Content-Type: application/json');

// Check admin authentication
if (!AdminAuth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON input']);
    exit;
}

// Validate required fields
$message = $input['message'] ?? '';
$alert_type = $input['alert_type'] ?? 'update';
$user_ids = $input['user_ids'] ?? [];

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit;
}

if (!in_array($alert_type, ['payment', 'service', 'promotion', 'update'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid alert type']);
    exit;
}

try {
    if (empty($user_ids)) {
        // Send to all active users
        $users = $db->query('SELECT id FROM users WHERE status = "active"')->fetchAll();
    } else {
        // Send to specific users
        $placeholders = implode(',', array_fill(0, count($user_ids), '?'));
        $stmt = $db->prepare('SELECT id FROM users WHERE id IN (' . $placeholders . ') AND status = "active"');
        $stmt->execute($user_ids);
        $users = $stmt->fetchAll();
    }
    
    if (empty($users)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No valid users found']);
        exit;
    }
    
    // Insert alerts for each user
    $insert_stmt = $db->prepare('INSERT INTO customer_alerts (user_id, alert_type, message, is_read) VALUES (?, ?, ?, false)');
    
    $count = 0;
    foreach ($users as $user) {
        $insert_stmt->execute([$user['id'], $alert_type, $message]);
        $count++;
    }
    
    // Log the event
    Security::logSecurityEvent('API_ALERTS_SENT', [
        'admin_id' => $_SESSION['admin_id'],
        'count' => $count,
        'type' => $alert_type
    ]);
    
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => "Alert sent to $count customer(s)",
        'count' => $count
    ]);
    
} catch (PDOException $e) {
    error_log('Alert API error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}
