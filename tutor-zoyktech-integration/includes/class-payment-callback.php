<?php
/**
 * Payment Callback Handler for Tutor LMS Zoyktech Integration
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Callback Handler Class
 */
class Tutor_Zoyktech_Payment_Callback {

    /**
     * Handle payment callback from Zoyktech
     */
    public function handle_callback() {
        // Log the callback
        error_log('TUTOR_ZOYKTECH: Payment callback received');
        
        try {
            // Get callback data
            $callback_data = $this->get_callback_data();
            
            if (empty($callback_data)) {
                throw new Exception('No callback data received');
            }
            
            // Process the callback
            $payment_handler = new Tutor_Zoyktech_Course_Payment();
            $result = $payment_handler->handle_payment_callback($callback_data);
            
            // Send response
            $this->send_callback_response($result);
            
        } catch (Exception $e) {
            error_log('TUTOR_ZOYKTECH: Callback error - ' . $e->getMessage());
            $this->send_error_response($e->getMessage());
        }
    }

    /**
     * Get callback data from request
     */
    private function get_callback_data() {
        $callback_data = array();
        
        // Try to get data from different sources
        if (!empty($_POST)) {
            $callback_data = $_POST;
        } elseif (!empty($_GET)) {
            $callback_data = $_GET;
        } else {
            // Try to get JSON data from input
            $json_input = file_get_contents('php://input');
            if (!empty($json_input)) {
                $decoded = json_decode($json_input, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $callback_data = $decoded;
                }
            }
        }
        
        // Log the received data
        error_log('TUTOR_ZOYKTECH: Callback data - ' . print_r($callback_data, true));
        
        return $callback_data;
    }

    /**
     * Send successful callback response
     */
    private function send_callback_response($result) {
        header('Content-Type: application/json');
        http_response_code(200);
        
        echo json_encode(array(
            'status' => 'success',
            'message' => 'Callback processed successfully',
            'data' => $result
        ));
        
        exit;
    }

    /**
     * Send error response
     */
    private function send_error_response($message) {
        header('Content-Type: application/json');
        http_response_code(400);
        
        echo json_encode(array(
            'status' => 'error',
            'message' => $message
        ));
        
        exit;
    }
}

