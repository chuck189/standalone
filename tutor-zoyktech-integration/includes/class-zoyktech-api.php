<?php
/**
 * Zoyktech API Integration for Tutor LMS
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Zoyktech API Class for Tutor LMS
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
     * Currency
     */
    private $currency;

    /**
     * Debug mode
     */
    private $debug;

    /**
     * Constructor
     */
    public function __construct() {
        $this->environment = tutor_utils()->get_option('zoyktech_environment', 'sandbox');
        $this->merchant_id = tutor_utils()->get_option('zoyktech_merchant_id');
        $this->public_id = tutor_utils()->get_option('zoyktech_public_id');
        $this->secret_key = tutor_utils()->get_option('zoyktech_secret_key');
        $this->currency = tutor_utils()->get_option('zoyktech_currency', 'ZMW');
        $this->debug = tutor_utils()->get_option('zoyktech_debug', false);
    }

    /**
     * Get API base URL
     */
    private function get_api_url() {
        return $this->environment === 'live' ? self::LIVE_URL : self::SANDBOX_URL;
    }

    /**
     * Initiate C2B payment for course enrollment
     */
    public function initiate_course_payment($course_id, $user_id, $phone_number, $provider_id = null) {
        // Get course price
        $course_price = get_post_meta($course_id, '_tutor_course_price', true);
        if (empty($course_price) || $course_price <= 0) {
            throw new Exception(__('Course price not found or invalid.', 'tutor-zoyktech'));
        }

        // Auto-detect provider if not provided
        if (empty($provider_id)) {
            $provider_id = $this->detect_provider($phone_number);
        }

        // Generate unique order ID
        $order_id = 'TUTOR_' . $course_id . '_' . $user_id . '_' . time();

        // Prepare payment data
        $payment_data = array(
            'merchant_id' => $this->merchant_id,
            'customer_id' => $phone_number,
            'order_id' => $order_id,
            'amount' => number_format((float) $course_price, 2, '.', ''),
            'currency' => $this->currency,
            'country' => $this->detect_country($phone_number),
            'callback_url' => home_url('/tutor-zoyktech-callback/'),
            'provider_id' => (int) $provider_id,
            'extra' => array(
                'course_id' => $course_id,
                'user_id' => $user_id,
                'course_title' => get_the_title($course_id),
                'customer_name' => $this->get_user_name($user_id)
            )
        );

        $this->log('Initiating course payment: ' . print_r($payment_data, true));

        // Add signature
        $payment_data['signature'] = $this->generate_signature($payment_data);

        // Make API request
        $response = $this->make_request('/payment_c2b', $payment_data);

        // Store transaction in database
        $this->store_transaction($order_id, $course_id, $user_id, $payment_data, $response);

        return array(
            'order_id' => $order_id,
            'response' => $response
        );
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
    private function detect_country($phone) {
        if (preg_match('/^\+260/', $phone)) {
            return 'ZM'; // Zambia
        }
        return 'ZM'; // Default to Zambia
    }

    /**
     * Get user display name
     */
    private function get_user_name($user_id) {
        $user = get_userdata($user_id);
        if ($user) {
            return $user->display_name ?: $user->user_login;
        }
        return 'Student';
    }

    /**
     * Validate phone number
     */
    public function validate_phone_number($phone_number) {
        $cleaned = preg_replace('/[^\d+]/', '', $phone_number);
        return preg_match('/^\+\d{10,15}$/', $cleaned);
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
     * Store transaction in database
     */
    private function store_transaction($order_id, $course_id, $user_id, $payment_data, $response) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';

        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'course_id' => $course_id,
                'order_id' => $order_id,
                'amount' => $payment_data['amount'],
                'currency' => $payment_data['currency'],
                'phone_number' => $payment_data['customer_id'],
                'provider_id' => $payment_data['provider_id'],
                'status' => 'pending',
                'payment_data' => json_encode(array(
                    'request' => $payment_data,
                    'response' => $response
                ))
            ),
            array('%d', '%d', '%s', '%f', '%s', '%s', '%d', '%s', '%s')
        );

        if ($wpdb->last_error) {
            $this->log('Database error: ' . $wpdb->last_error);
        }
    }

    /**
     * Update transaction status
     */
    public function update_transaction_status($order_id, $status, $transaction_id = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';

        $update_data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );

        if ($transaction_id) {
            $update_data['transaction_id'] = $transaction_id;
        }

        $result = $wpdb->update(
            $table_name,
            $update_data,
            array('order_id' => $order_id),
            array('%s', '%s', '%s'),
            array('%s')
        );

        $this->log("Updated transaction $order_id to status $status");

        return $result !== false;
    }

    /**
     * Get transaction by order ID
     */
    public function get_transaction($order_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE order_id = %s", $order_id)
        );
    }

    /**
     * Get user transactions
     */
    public function get_user_transactions($user_id, $limit = 20) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE user_id = %d ORDER BY created_at DESC LIMIT %d",
                $user_id,
                $limit
            )
        );
    }

    /**
     * Get provider name
     */
    public function get_provider_name($provider_id) {
        $providers = array(
            237 => 'MTN Zambia',
            289 => 'Airtel Money Zambia',
            14 => 'Simulator'
        );

        return isset($providers[$provider_id]) ? $providers[$provider_id] : 'Unknown Provider';
    }

    /**
     * Get status message
     */
    public function get_status_message($status) {
        $statuses = array(
            'pending' => __('Pending', 'tutor-zoyktech'),
            'processing' => __('Processing', 'tutor-zoyktech'),
            'completed' => __('Completed', 'tutor-zoyktech'),
            'failed' => __('Failed', 'tutor-zoyktech'),
            'cancelled' => __('Cancelled', 'tutor-zoyktech')
        );

        return isset($statuses[$status]) ? $statuses[$status] : ucfirst($status);
    }

    /**
     * Log messages
     */
    private function log($message) {
        if ($this->debug) {
            error_log('TUTOR_ZOYKTECH: ' . $message);
            
            // Also log to file
            $log_file = WP_CONTENT_DIR . '/tutor-zoyktech-debug.log';
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
        }
    }
}

