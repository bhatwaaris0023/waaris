<?php
/**
 * API - Search Products
 */

require_once __DIR__ . '/../config/db.php';

header('Content-Type: application/json');

$search = Security::sanitizeInput($_GET['q'] ?? '');

if (strlen($search) < 2) {
    echo json_encode([
        'success' => false,
        'message' => 'Search term too short'
    ]);
    exit;
}

try {
    $stmt = $db->prepare('SELECT id, name, price, image_url FROM products WHERE status = "active" AND (MATCH(name, description) AGAINST(? IN BOOLEAN MODE) OR name LIKE ?) LIMIT 10');
    $search_pattern = '%' . $search . '%';
    $stmt->execute([$search, $search_pattern]);
    $products = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'products' => $products
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Search failed'
    ]);
}

?>
