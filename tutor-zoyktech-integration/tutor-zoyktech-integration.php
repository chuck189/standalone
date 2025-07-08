<?php
/**
 * Plugin Name: Tutor LMS Zoyktech Payment Integration
 * Plugin URI: https://github.com/your-repo/tutor-zoyktech-integration
 * Description: Direct integration between Tutor LMS and Zoyktech payment gateway for seamless mobile money course payments in Zambia.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://yourwebsite.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: tutor-zoyktech
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * 
 * Tutor LMS Zoyktech Integration is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('TUTOR_ZOYKTECH_VERSION', '1.0.0');
define('TUTOR_ZOYKTECH_PLUGIN_FILE', __FILE__);
define('TUTOR_ZOYKTECH_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('TUTOR_ZOYKTECH_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TUTOR_ZOYKTECH_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main Tutor Zoyktech Integration Class
 */
class Tutor_Zoyktech_Integration {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Get plugin instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Load plugin textdomain first
        $this->load_textdomain();
        
        // Check if Tutor LMS is active
        if (!$this->is_tutor_lms_active()) {
            add_action('admin_notices', array($this, 'tutor_lms_missing_notice'));
            return;
        }

        // Include required files
        $this->includes();

        // Initialize components
        $this->init_hooks();
    }

    /**
     * Check if Tutor LMS is active
     */
    private function is_tutor_lms_active() {
        return class_exists('TUTOR\\Tutor') || function_exists('tutor');
    }

    /**
     * Load plugin textdomain
     */
    private function load_textdomain() {
        load_plugin_textdomain(
            'tutor-zoyktech', 
            false, 
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Include required files
     */
    private function includes() {
        // Core classes
        require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-zoyktech-api.php';
        require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-zoyktech-gateway.php';
        require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-tutor-monetization-integration.php';
        require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-course-payment.php';
        require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-enrollment-manager.php';
        require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-payment-history.php';
        require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-admin-settings.php';
        
        // Frontend classes
        require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-frontend-payment.php';
        require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-student-dashboard.php';
        
        // Initialize monetization integration
        if (class_exists('Tutor_Zoyktech_Monetization_Integration')) {
            new Tutor_Zoyktech_Monetization_Integration();
        }
        
        // Hooks and filters
        require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/hooks.php';
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));

        // Add payment button to course pages
        add_action('tutor_course/single/enrolled/before', array($this, 'add_payment_button'));
        
        // Handle payment callbacks
        add_action('wp_ajax_tutor_zoyktech_payment', array($this, 'handle_payment_ajax'));
        add_action('wp_ajax_nopriv_tutor_zoyktech_payment', array($this, 'handle_payment_ajax'));
        
        // Payment callback endpoint
        add_action('init', array($this, 'add_payment_callback_endpoint'));
        add_action('template_redirect', array($this, 'handle_payment_callback'));

        // Add settings to Tutor LMS
        add_filter('tutor_option_extend_config', array($this, 'add_tutor_settings'));
        
        // Add payment history to student dashboard
        add_action('tutor_dashboard/after', array($this, 'add_payment_history_tab'));
    }

    /**
     * Enqueue frontend scripts and styles
     */
    public function enqueue_scripts() {
        if (is_singular('courses') || tutor_utils()->is_tutor_dashboard()) {
            wp_enqueue_style(
                'tutor-zoyktech-style',
                TUTOR_ZOYKTECH_PLUGIN_URL . 'assets/css/frontend.css',
                array(),
                TUTOR_ZOYKTECH_VERSION
            );

            wp_enqueue_script(
                'tutor-zoyktech-script',
                TUTOR_ZOYKTECH_PLUGIN_URL . 'assets/js/frontend.js',
                array('jquery'),
                TUTOR_ZOYKTECH_VERSION,
                true
            );

            wp_localize_script('tutor-zoyktech-script', 'tutorZoyktech', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('tutor_zoyktech_nonce'),
                'messages' => array(
                    'processing' => __('Processing payment...', 'tutor-zoyktech'),
                    'success' => __('Payment successful! Enrolling you in the course...', 'tutor-zoyktech'),
                    'error' => __('Payment failed. Please try again.', 'tutor-zoyktech'),
                    'phone_required' => __('Please enter your mobile money number.', 'tutor-zoyktech'),
                    'phone_invalid' => __('Please enter a valid mobile money number with country code.', 'tutor-zoyktech')
                )
            ));
        }
    }

    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'tutor') !== false) {
            wp_enqueue_style(
                'tutor-zoyktech-admin-style',
                TUTOR_ZOYKTECH_PLUGIN_URL . 'assets/css/admin.css',
                array(),
                TUTOR_ZOYKTECH_VERSION
            );

            wp_enqueue_script(
                'tutor-zoyktech-admin-script',
                TUTOR_ZOYKTECH_PLUGIN_URL . 'assets/js/admin.js',
                array('jquery'),
                TUTOR_ZOYKTECH_VERSION,
                true
            );
        }
    }

    /**
     * Add payment button to course pages
     */
    public function add_payment_button() {
        global $post;
        
        if (!is_user_logged_in()) {
            return;
        }

        $course_id = get_the_ID();
        $user_id = get_current_user_id();
        
        // Check if user is already enrolled
        if (tutor_utils()->is_enrolled($course_id, $user_id)) {
            return;
        }

        // Check if course has a price
        $course_price = get_post_meta($course_id, '_tutor_course_price', true);
        if (empty($course_price) || $course_price <= 0) {
            return;
        }

        // Display payment form
        $this->display_payment_form($course_id, $course_price);
    }

    /**
     * Display payment form
     */
    private function display_payment_form($course_id, $price) {
        include TUTOR_ZOYKTECH_PLUGIN_PATH . 'templates/payment-form.php';
    }

    /**
     * Handle payment AJAX
     */
    public function handle_payment_ajax() {
        check_ajax_referer('tutor_zoyktech_nonce', 'nonce');

        $course_id = intval($_POST['course_id']);
        $phone_number = sanitize_text_field($_POST['phone_number']);
        $provider_id = sanitize_text_field($_POST['provider_id']);

        try {
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
     * Add payment callback endpoint
     */
    public function add_payment_callback_endpoint() {
        add_rewrite_rule(
            '^tutor-zoyktech-callback/?$',
            'index.php?tutor_zoyktech_callback=1',
            'top'
        );
        add_rewrite_tag('%tutor_zoyktech_callback%', '([^&]+)');
    }

    /**
     * Handle payment callback
     */
    public function handle_payment_callback() {
        if (get_query_var('tutor_zoyktech_callback')) {
            $callback_handler = new Tutor_Zoyktech_Payment_Callback();
            $callback_handler->handle_callback();
            exit;
        }
    }

    /**
     * Add settings to Tutor LMS
     */
    public function add_tutor_settings($config) {
        // Settings will be handled by the admin settings class
        return $config;
    }

    /**
     * Add payment history tab to student dashboard
     */
    public function add_payment_history_tab() {
        include TUTOR_ZOYKTECH_PLUGIN_PATH . 'templates/dashboard-payment-history.php';
    }

    /**
     * Plugin activation
     */
    public function activate() {
        // Create database tables
        $this->create_tables();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Payment transactions table
        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            course_id bigint(20) NOT NULL,
            order_id varchar(100) NOT NULL,
            transaction_id varchar(100) DEFAULT NULL,
            amount decimal(10,2) NOT NULL,
            currency varchar(3) NOT NULL DEFAULT 'ZMW',
            phone_number varchar(20) NOT NULL,
            provider_id int(11) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            payment_data longtext DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_id (order_id),
            KEY user_id (user_id),
            KEY course_id (course_id),
            KEY status (status)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Show notice if Tutor LMS is not active
     */
    public function tutor_lms_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('Tutor LMS Zoyktech Integration requires Tutor LMS to be installed and activated.', 'tutor-zoyktech');
        echo '</p></div>';
    }
}

// Initialize the plugin
Tutor_Zoyktech_Integration::get_instance();

