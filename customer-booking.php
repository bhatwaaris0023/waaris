<?php
/**
 * Service Booking Page
 * Allow customers to book motorcycle services
 */

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/security.php';
require_once __DIR__ . '/includes/auth.php';

Auth::requireLogin();

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get user details
$user_stmt = $db->prepare('SELECT * FROM users WHERE id = ?');
$user_stmt->execute([$user_id]);
$user = $user_stmt->fetch();

// Handle booking submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request. Please try again.';
    } else {
        $service_type = Security::sanitizeInput($_POST['service_type'] ?? '');
        $description = Security::sanitizeInput($_POST['description'] ?? '');
        $preferred_date = Security::sanitizeInput($_POST['preferred_date'] ?? '');
        $preferred_time = Security::sanitizeInput($_POST['preferred_time'] ?? '');
        
        if (empty($service_type) || empty($description) || empty($preferred_date)) {
            $error = 'Please fill in all required fields.';
        } else {
            try {
                // Create a booking record (this can be integrated with a service_bookings table)
                // For now, we'll create an alert to notify admin
                
                $booking_msg = "Service Booking Request:\n";
                $booking_msg .= "Service: " . htmlspecialchars($service_type) . "\n";
                $booking_msg .= "Date: " . htmlspecialchars($preferred_date) . "\n";
                $booking_msg .= "Time: " . htmlspecialchars($preferred_time ?: 'Not specified') . "\n";
                $booking_msg .= "Description: " . htmlspecialchars($description);
                
                // Insert as alert for now (can be replaced with proper booking table)
                $insert_stmt = $db->prepare('INSERT INTO customer_alerts (user_id, alert_type, message, is_read) VALUES (?, ?, ?, true)');
                $insert_stmt->execute([$user_id, 'service', 'Your service booking has been submitted. We will contact you soon.']);
                
                Security::logSecurityEvent('SERVICE_BOOKING_REQUESTED', [
                    'user_id' => $user_id,
                    'service_type' => $service_type,
                    'preferred_date' => $preferred_date
                ]);
                
                $success = 'Service booking request submitted successfully! We will contact you within 24 hours to confirm.';
            } catch (PDOException $e) {
                error_log('Booking error: ' . $e->getMessage());
                $error = 'Failed to submit booking. Please try again.';
            }
        }
    }
}

$csrf_token = Security::generateCSRFToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Service - Ahanger MotoCorp</title>
    <link rel="stylesheet" href="/waaris/assets/css/style.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
        }
        
        .page-header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .page-header p {
            font-size: 14px;
            opacity: 0.9;
            margin-bottom: 15px;
        }
        
        .back-btn {
            color: white;
            text-decoration: none;
            font-size: 14px;
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            transition: all 0.3s;
            display: inline-block;
        }
        
        .back-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background: #fee;
            color: #c00;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background: #efe;
            color: #060;
            border: 1px solid #cfc;
        }
        
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        
        .service-card {
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .service-card:hover {
            border-color: #667eea;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.2);
        }
        
        .service-card.selected {
            background: #f0f3ff;
            border-color: #667eea;
        }
        
        .service-card input[type="radio"] {
            display: none;
        }
        
        .service-icon {
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .service-card label {
            font-weight: 600;
            color: #333;
            cursor: pointer;
            display: block;
            margin-bottom: 5px;
        }
        
        .service-card p {
            font-size: 12px;
            color: #999;
        }
        
        .booking-form {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
        
        .form-row.full {
            grid-template-columns: 1fr;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 14px;
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-group textarea {
            resize: vertical;
            min-height: 120px;
        }
        
        .button-group {
            display: flex;
            gap: 12px;
            margin-top: 25px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn-secondary:hover {
            background: #e0e0e0;
        }
        
        .info-box {
            background: #f9f9ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-size: 14px;
            color: #666;
        }
        
        .info-box strong {
            color: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="page-header">
            <h1>🔧 Book a Service</h1>
            <p>Schedule maintenance or repair services for your motorcycle</p>
            <a href="customer-dashboard.php" class="back-btn">← Back to Dashboard</a>
        </div>
        
        <!-- Alerts -->
        <?php if (!empty($error)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if (!empty($success)): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        
        <!-- Info Box -->
        <div class="info-box">
            <strong>ℹ️ How it works:</strong> Select the service you need, choose your preferred date and time, and our team will contact you within 24 hours to confirm your booking.
        </div>
        
        <!-- Booking Form -->
        <div class="booking-form">
            <h2 style="margin-bottom: 25px; color: #333; font-size: 22px;">Book Your Service</h2>
            
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                
                <!-- Service Selection -->
                <h3 style="color: #333; margin-bottom: 15px; font-size: 16px;">Select Service Type</h3>
                <div class="services-grid">
                    <div class="service-card" onclick="selectService(this)">
                        <input type="radio" name="service_type" value="Regular Maintenance" required>
                        <label>
                            <div class="service-icon">🔧</div>
                            Regular Maintenance
                        </label>
                        <p>Oil change, filter replacement, inspection</p>
                    </div>
                    
                    <div class="service-card" onclick="selectService(this)">
                        <input type="radio" name="service_type" value="Engine Repair" required>
                        <label>
                            <div class="service-icon">⚙️</div>
                            Engine Repair
                        </label>
                        <p>Engine diagnostics and repair</p>
                    </div>
                    
                    <div class="service-card" onclick="selectService(this)">
                        <input type="radio" name="service_type" value="Brake Service" required>
                        <label>
                            <div class="service-icon">🛑</div>
                            Brake Service
                        </label>
                        <p>Brake pad replacement, adjustment</p>
                    </div>
                    
                    <div class="service-card" onclick="selectService(this)">
                        <input type="radio" name="service_type" value="Tire Service" required>
                        <label>
                            <div class="service-icon">🛞</div>
                            Tire Service
                        </label>
                        <p>Tire replacement, balancing</p>
                    </div>
                    
                    <div class="service-card" onclick="selectService(this)">
                        <input type="radio" name="service_type" value="Battery Service" required>
                        <label>
                            <div class="service-icon">🔋</div>
                            Battery Service
                        </label>
                        <p>Battery replacement, charging</p>
                    </div>
                    
                    <div class="service-card" onclick="selectService(this)">
                        <input type="radio" name="service_type" value="Other" required>
                        <label>
                            <div class="service-icon">❓</div>
                            Other
                        </label>
                        <p>Custom service request</p>
                    </div>
                </div>
                
                <!-- Date & Time -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="preferred_date">Preferred Date <span style="color: red;">*</span></label>
                        <input type="date" id="preferred_date" name="preferred_date" required>
                    </div>
                    <div class="form-group">
                        <label for="preferred_time">Preferred Time</label>
                        <input type="time" id="preferred_time" name="preferred_time">
                    </div>
                </div>
                
                <!-- Description -->
                <div class="form-row full">
                    <div class="form-group">
                        <label for="description">Service Description <span style="color: red;">*</span></label>
                        <textarea id="description" name="description" placeholder="Describe the issue or service needed in detail..." required></textarea>
                    </div>
                </div>
                
                <!-- Contact Info (Pre-filled) -->
                <h3 style="color: #333; margin-bottom: 15px; font-size: 16px; margin-top: 25px;">Your Contact Information</h3>
                <div class="form-row">
                    <div class="form-group">
                        <label>Name</label>
                        <input type="text" value="<?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>" disabled style="background: #f9f9f9;">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" value="<?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?>" disabled style="background: #f9f9f9;">
                    </div>
                </div>
                
                <div class="form-row full">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled style="background: #f9f9f9;">
                    </div>
                </div>
                
                <!-- Buttons -->
                <div class="button-group">
                    <button type="submit" class="btn-primary">Submit Booking Request</button>
                    <a href="customer-dashboard.php" class="btn-secondary">Cancel</a>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function selectService(card) {
            // Remove selected class from all cards
            document.querySelectorAll('.service-card').forEach(c => c.classList.remove('selected'));
            
            // Add selected class to clicked card
            card.classList.add('selected');
            
            // Select the radio button
            card.querySelector('input[type="radio"]').checked = true;
        }
        
        // Set minimum date to today
        document.getElementById('preferred_date').min = new Date().toISOString().split('T')[0];
    </script>
</body>
</html>
