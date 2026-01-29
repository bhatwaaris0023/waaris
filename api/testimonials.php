<?php
/**
 * Testimonials API Endpoints
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'get-all':
        getTestimonials();
        break;
    case 'get':
        getTestimonial();
        break;
    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get all active testimonials
 */
function getTestimonials() {
    global $db;
    
    try {
        $stmt = $db->prepare('
            SELECT id, customer_name, customer_image, customer_title, rating, testimonial_text
            FROM testimonials
            WHERE is_active = TRUE
            ORDER BY display_order ASC, created_at DESC
        ');
        $stmt->execute();
        $testimonials = $stmt->fetchAll();
        
        echo json_encode([
            'success' => true,
            'data' => $testimonials,
            'count' => count($testimonials)
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching testimonials'
        ]);
    }
}

/**
 * Get single testimonial by ID
 */
function getTestimonial() {
    global $db;
    
    $id = (int)($_GET['id'] ?? 0);
    
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid testimonial ID']);
        return;
    }
    
    try {
        $stmt = $db->prepare('
            SELECT * FROM testimonials WHERE id = ? AND is_active = TRUE
        ');
        $stmt->execute([$id]);
        $testimonial = $stmt->fetch();
        
        if (!$testimonial) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Testimonial not found']);
            return;
        }
        
        echo json_encode([
            'success' => true,
            'data' => $testimonial
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fetching testimonial'
        ]);
    }
}
