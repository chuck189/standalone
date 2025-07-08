<?php
/**
 * Zoyktech API Integration
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Zoyktech API Class
 */
class Tutor_Zoyktech_API {

    /**
     * API URLs
     */
    const SANDBOX_URL = 'https://sandbox.zoyktech.com';
    const LIVE_URL = 'https://api.zoyktech.com';

    /**
     * Environment
     */
    private $environment;

    /**
     * Merchant ID
     */
    private $merchant_id;

    /**
     * Public ID
     */
    private $public_id;

    /**
     * Secret Key
     */
    private $secret_key;

    /**
     * Debug mode
     */
    private $debug;

    /**
     * Constructor
     */
    public function __construct($merchant_id, $public_id, $secret_key, $environment = 'sandbox', $debug = false) {
        $this->merchant_id = $merchant_id;
        $this->public_id = $public_id;
        $this->secret_key = $secret_key;
        $this->environment = $environment;
        $this->debug = $debug;
    }

    /**
     * Get API base URL
     */
    private function get_api_url() {
        return $this->environment === 'live' ? self::LIVE_URL : self::SANDBOX_URL;
    }

    /**
     * Initiate payment
     */
    public function initiate_payment($payment_data) {
        // Add signature
        $payment_data['signature'] = $this->generate_signature($payment_data);

        $this->log('Initiating payment: ' . print_r($payment_data, true));

        // Make API request
        $response = $this->make_request('/payment_c2b', $payment_data);

        return $response;
    }

    /**
     * Detect provider from phone number
     */
    public function detect_provider($phone_number) {
        $this->log('Detecting provider for: ' . $phone_number);
        
        // Clean phone number
        $cleaned = preg_replace('/[^\d+]/', '', $phone_number);
        
        // Zambian providers
        if (preg_match('/^\+260(95|96|97)/', $cleaned)) {
            $this->log('Detected: Airtel Money Zambia (289)');
            return 289;
        } elseif (preg_match('/^\+260(76|77)/', $cleaned)) {
            $this->log('Detected: MTN Zambia (237)');
            return 237;
        }
        
        // Default to Airtel for Zambian numbers
        if (preg_match('/^\+260/', $cleaned)) {
            $this->log('Unknown Zambian number, defaulting to Airtel (289)');
            return 289;
        }

        // Default fallback
        $this->log('No provider detected, using default (289)');
        return 289;
    }

    /**
     * Detect country from phone number
     */
    public function detect_country($phone) {
        if (preg_match('/^\+260/', $phone)) {
            return 'ZM'; // Zambia
        }
        return 'ZM'; // Default to Zambia
    }

    /**
     * Generate signature
     */
    private function generate_signature($data) {
        // Remove signature if exists
        unset($data['signature']);
        
        // Sort data
        ksort($data);
        
        // Build query string
        $query_string = '';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value);
            }
            $query_string .= $key . '=' . $value . '&';
        }
        $query_string = rtrim($query_string, '&');
        
        $this->log('Signature query string: ' . $query_string);
        
        // Generate signature
        $signature = hash_hmac('sha512', $query_string, $this->secret_key);
        
        $this->log('Generated signature: ' . $signature);
        
        return $signature;
    }

    /**
     * Verify callback signature
     */
    public function verify_callback_signature($callback_data) {
        if (!isset($callback_data['signature'])) {
            return false;
        }

        $received_signature = $callback_data['signature'];
        $expected_signature = $this->generate_signature($callback_data);

        return hash_equals($expected_signature, $received_signature);
    }

    /**
     * Make API request
     */
    private function make_request($endpoint, $data = array()) {
        $url = $this->get_api_url() . '/' . $this->public_id . $endpoint;
        
        $this->log("API Request URL: $url");
        $this->log("API Request Data: " . print_r($data, true));

        $args = array(
            'method' => 'POST',
            'timeout' => 30,
            'sslverify' => true,
            'headers' => array(
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ),
            'body' => json_encode($data)
        );

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $error_msg = $response->get_error_message();
            $this->log('Request failed: ' . $error_msg);
            throw new Exception('API request failed: ' . $error_msg);
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $this->log("API Response Code: $response_code");
        $this->log("API Response Body: $body");

        if (empty($body)) {
            throw new Exception('Empty response from API');
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Invalid JSON response: ' . json_last_error_msg());
        }

        return $decoded;
    }

    /**
     * Log messages
     */
    private function log($message) {
        if ($this->debug) {
            error_log('ZOYKTECH_API: ' . $message);
        }
    }
}