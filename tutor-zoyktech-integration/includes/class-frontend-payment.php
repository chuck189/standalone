<?php
/**
 * Frontend Payment Handler for Tutor LMS Zoyktech Integration
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend Payment Handler Class
 */
class Tutor_Zoyktech_Frontend_Payment {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize frontend payment features
     */
    public function init() {
        // Handle payment form submission
        add_action('wp_ajax_tutor_zoyktech_process_payment', array($this, 'process_payment_ajax'));
        add_action('wp_ajax_nopriv_tutor_zoyktech_process_payment', array($this, 'process_payment_ajax'));
        
        // Handle payment status checks
        add_action('wp_ajax_tutor_zoyktech_check_payment_status', array($this, 'check_payment_status_ajax'));
        add_action('wp_ajax_nopriv_tutor_zoyktech_check_payment_status', array($this, 'check_payment_status_ajax'));
        
        // Add payment form to course pages
        add_action('tutor_course/single/content/after', array($this, 'maybe_show_payment_form'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        if (is_singular('courses') || $this->is_tutor_dashboard()) {
            wp_enqueue_style(
                'tutor-zoyktech-frontend',
                TUTOR_ZOYKTECH_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                TUTOR_ZOYKTECH_VERSION
            );

            wp_enqueue_script(
                'tutor-zoyktech-frontend',
                TUTOR_ZOYKTECH_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                TUTOR_ZOYKTECH_VERSION,
                true
            );

            wp_localize_script('tutor-zoyktech-frontend', 'tutorZoyktech', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tutor_zoyktech_nonce'),
                'messages' => array(
                    'processing' => __('Processing payment, please wait...', 'tutor-zoyktech'),
                    'success' => __('Payment successful! You will be enrolled shortly.', 'tutor-zoyktech'),
                    'error' => __('Payment failed. Please try again.', 'tutor-zoyktech'),
                    'phone_required' => __('Please enter your mobile money number.', 'tutor-zoyktech'),
                    'phone_invalid' => __('Please enter a valid mobile money number with country code.', 'tutor-zoyktech'),
                    'confirming' => __('Please check your phone and confirm the payment.', 'tutor-zoyktech')
                )
            ));
        }
    }

    /**
     * Check if current page is Tutor dashboard
     */
    private function is_tutor_dashboard() {
        if (function_exists('tutor')) {
            return tutor()->is_tutor_dashboard;
        }
        return false;
    }

    /**
     * Maybe show payment form on course pages
     */
    public function maybe_show_payment_form() {
        if (!$this->should_show_payment_form()) {
            return;
        }

        global $post;
        $course_id = $post->ID;
        $course_price = get_post_meta($course_id, '_tutor_course_price', true);
        
        if (empty($course_price) || $course_price <= 0) {
            return;
        }

        $this->display_payment_form($course_id, $course_price);
    }

    /**
     * Check if payment form should be shown
     */
    private function should_show_payment_form() {
        // Check if Zoyktech is enabled
        $options = get_option('tutor_zoyktech_options', array());
        if (empty($options['enable_zoyktech'])) {
            return false;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            return false;
        }

        // Check if this is a course page
        if (!is_singular('courses')) {
            return false;
        }

        global $post;
        $course_id = $post->ID;
        $user_id = get_current_user_id();

        // Check if user is already enrolled
        if (function_exists('tutor_utils') && tutor_utils()->is_enrolled($course_id, $user_id)) {
            return false;
        }

        return true;
    }

    /**
     * Display payment form
     */
    private function display_payment_form($course_id, $price) {
        include TUTOR_ZOYKTECH_PLUGIN_PATH . 'templates/payment-form.php';
    }

    /**
     * Process payment via AJAX
     */
    public function process_payment_ajax() {
        check_ajax_referer('tutor_zoyktech_nonce', 'nonce');

        // Validate user
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Please log in to make a payment.'));
        }

        // Get and validate input
        $course_id = intval($_POST['course_id'] ?? 0);
        $phone_number = sanitize_text_field($_POST['phone_number'] ?? '');
        $provider_id = sanitize_text_field($_POST['provider_id'] ?? '');

        if (empty($course_id)) {
            wp_send_json_error(array('message' => 'Invalid course ID.'));
        }

        if (empty($phone_number)) {
            wp_send_json_error(array('message' => 'Please enter your phone number.'));
        }

        try {
            // Process payment
            $payment_handler = new Tutor_Zoyktech_Course_Payment();
            $result = $payment_handler->process_payment($course_id, $phone_number, $provider_id);

            wp_send_json_success($result);

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Check payment status via AJAX
     */
    public function check_payment_status_ajax() {
        check_ajax_referer('tutor_zoyktech_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $order_id = sanitize_text_field($_POST['order_id'] ?? '');
        if (empty($order_id)) {
            wp_send_json_error(array('message' => 'Order ID is required'));
        }

        try {
            $api = new Tutor_Zoyktech_API();
            $transaction = $api->get_transaction($order_id);

            if (!$transaction || $transaction->user_id != $user_id) {
                wp_send_json_error(array('message' => 'Transaction not found'));
            }

            wp_send_json_success(array(
                'status' => $transaction->status,
                'transaction_id' => $transaction->transaction_id,
                'message' => $this->get_status_message($transaction->status)
            ));

        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage()
            ));
        }
    }

    /**
     * Get user-friendly status message
     */
    private function get_status_message($status) {
        $messages = array(
            'pending' => __('Payment is being processed...', 'tutor-zoyktech'),
            'processing' => __('Please check your phone for payment confirmation.', 'tutor-zoyktech'),
            'completed' => __('Payment completed successfully! You now have access to the course.', 'tutor-zoyktech'),
            'failed' => __('Payment failed. Please try again.', 'tutor-zoyktech'),
            'cancelled' => __('Payment was cancelled.', 'tutor-zoyktech'),
            'expired' => __('Payment has expired. Please try again.', 'tutor-zoyktech')
        );

        return isset($messages[$status]) ? $messages[$status] : $status;
    }

    /**
     * Get course price for display
     */
    public function get_formatted_course_price($course_id) {
        $price = get_post_meta($course_id, '_tutor_course_price', true);
        
        if (empty($price) || $price <= 0) {
            return __('Free', 'tutor-zoyktech');
        }

        $options = get_option('tutor_zoyktech_options', array());
        $currency = isset($options['zoyktech_currency']) ? $options['zoyktech_currency'] : 'ZMW';
        
        $currency_symbols = array(
            'ZMW' => 'K',
            'USD' => '$'
        );

        $symbol = isset($currency_symbols[$currency]) ? $currency_symbols[$currency] : $currency;
        
        return $symbol . number_format($price, 2);
    }

    /**
     * Check if course requires payment
     */
    public function course_requires_payment($course_id) {
        $price = get_post_meta($course_id, '_tutor_course_price', true);
        return !empty($price) && $price > 0;
    }

    /**
     * Get supported providers
     */
    public function get_supported_providers() {
        return array(
            '289' => array(
                'name' => __('Airtel Money', 'tutor-zoyktech'),
                'icon' => '🟠',
                'prefixes' => array('+260 95', '+260 96', '+260 97')
            ),
            '237' => array(
                'name' => __('MTN Mobile Money', 'tutor-zoyktech'),
                'icon' => '🟡',
                'prefixes' => array('+260 76', '+260 77')
            )
        );
    }

    /**
     * Validate phone number format
     */
    public function validate_phone_number($phone_number) {
        // Remove all non-digit characters except +
        $cleaned = preg_replace('/[^\d+]/', '', $phone_number);
        
        // Check if it's a valid Zambian number
        return preg_match('/^\+260[0-9]{9}$/', $cleaned);
    }

    /**
     * Auto-detect provider from phone number
     */
    public function detect_provider($phone_number) {
        $cleaned = preg_replace('/[^\d+]/', '', $phone_number);
        
        if (preg_match('/^\+260(95|96|97)/', $cleaned)) {
            return '289'; // Airtel
        } elseif (preg_match('/^\+260(76|77)/', $cleaned)) {
            return '237'; // MTN
        }
        
        return null;
    }
}