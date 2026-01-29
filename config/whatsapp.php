<?php
/**
 * WhatsApp Business API Integration
 * Handles sending messages via WhatsApp Business API
 * NOTE: This is disabled by default. Define constants in config.php to enable.
 */

// Define WhatsApp API credentials if not already defined
if (!defined('WHATSAPP_API_URL')) {
    define('WHATSAPP_API_URL', '');
}
if (!defined('WHATSAPP_PHONE_NUMBER_ID')) {
    define('WHATSAPP_PHONE_NUMBER_ID', '');
}
if (!defined('WHATSAPP_ACCESS_TOKEN')) {
    define('WHATSAPP_ACCESS_TOKEN', '');
}

class WhatsAppService {
    private $apiUrl;
    private $phoneNumberId;
    private $accessToken;
    
    public function __construct() {
        $this->apiUrl = WHATSAPP_API_URL;
        $this->phoneNumberId = WHATSAPP_PHONE_NUMBER_ID;
        $this->accessToken = WHATSAPP_ACCESS_TOKEN;
    }
    
    /**
     * Send WhatsApp message to customer
     * 
     * @param string $phone_number Customer's WhatsApp phone number (with country code, no +)
     * @param string $message Message to send
     * @return array ['success' => bool, 'message_id' => string|null, 'error' => string|null]
     */
    public function sendMessage($phone_number, $message) {
        // Validate credentials
        if (empty($this->phoneNumberId) || empty($this->accessToken)) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'WhatsApp credentials not configured'
            ];
        }
        
        // Validate phone number (should be 10-15 digits)
        $clean_phone = preg_replace('/\D/', '', $phone_number);
        if (strlen($clean_phone) < 10 || strlen($clean_phone) > 15) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'Invalid phone number format'
            ];
        }
        
        try {
            $url = "{$this->apiUrl}/{$this->phoneNumberId}/messages";
            
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $clean_phone,
                'type' => 'text',
                'text' => [
                    'body' => $message
                ]
            ];
            
            $response = $this->makeRequest($url, $payload);
            
            if (isset($response['messages']) && !empty($response['messages'])) {
                return [
                    'success' => true,
                    'message_id' => $response['messages'][0]['id'] ?? null,
                    'error' => null
                ];
            } else {
                $error = $response['error']['message'] ?? 'Unknown error';
                return [
                    'success' => false,
                    'message_id' => null,
                    'error' => $error
                ];
            }
        } catch (Exception $e) {
            error_log('WhatsApp send error: ' . $e->getMessage());
            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send template message (pre-approved WhatsApp template)
     * 
     * @param string $phone_number Customer's phone number
     * @param string $template_name Template name
     * @param array $parameters Template parameters
     * @return array Response with success status
     */
    public function sendTemplateMessage($phone_number, $template_name, $parameters = []) {
        if (empty($this->phoneNumberId) || empty($this->accessToken)) {
            return [
                'success' => false,
                'message_id' => null,
                'error' => 'WhatsApp credentials not configured'
            ];
        }
        
        $clean_phone = preg_replace('/\D/', '', $phone_number);
        
        try {
            $url = "{$this->apiUrl}/{$this->phoneNumberId}/messages";
            
            $payload = [
                'messaging_product' => 'whatsapp',
                'recipient_type' => 'individual',
                'to' => $clean_phone,
                'type' => 'template',
                'template' => [
                    'name' => $template_name,
                    'language' => [
                        'code' => 'en_US'
                    ]
                ]
            ];
            
            if (!empty($parameters)) {
                $payload['template']['components'] = [
                    [
                        'type' => 'body',
                        'parameters' => array_map(function($param) {
                            return ['type' => 'text', 'text' => $param];
                        }, $parameters)
                    ]
                ];
            }
            
            $response = $this->makeRequest($url, $payload);
            
            if (isset($response['messages']) && !empty($response['messages'])) {
                return [
                    'success' => true,
                    'message_id' => $response['messages'][0]['id'] ?? null,
                    'error' => null
                ];
            } else {
                $error = $response['error']['message'] ?? 'Unknown error';
                return [
                    'success' => false,
                    'message_id' => null,
                    'error' => $error
                ];
            }
        } catch (Exception $e) {
            error_log('WhatsApp template send error: ' . $e->getMessage());
            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Make HTTP request to WhatsApp API
     */
    private function makeRequest($url, $payload) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->accessToken
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception('cURL Error: ' . $error);
        }
        
        $result = json_decode($response, true);
        
        if ($httpCode >= 400) {
            error_log('WhatsApp API Error (' . $httpCode . '): ' . $response);
            return $result ?? ['error' => ['message' => 'HTTP ' . $httpCode]];
        }
        
        return $result;
    }
    
    /**
     * Get message status
     */
    public function getMessageStatus($message_id) {
        if (empty($this->accessToken)) {
            return ['status' => 'unknown', 'error' => 'Credentials not configured'];
        }
        
        try {
            $url = "{$this->apiUrl}/{$message_id}";
            $response = $this->makeRequest($url, []);
            
            return [
                'status' => $response['status'] ?? 'unknown',
                'error' => null
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
}

?>
