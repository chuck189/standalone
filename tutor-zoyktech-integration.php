<?php
/**
 * Plugin Name: Tutor LMS Zoyktech WooCommerce Gateway
 * Plugin URI: https://github.com/your-repo/tutor-zoyktech-woocommerce
 * Description: Complete WooCommerce payment gateway for Tutor LMS courses with Zoyktech mobile money integration. Supports Airtel Money and MTN Mobile Money.
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
 * WC requires at least: 5.0
 * WC tested up to: 8.5
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
 * Main Tutor Zoyktech WooCommerce Gateway Class
 */
class Tutor_Zoyktech_WooCommerce_Gateway {

    /**
     * Plugin instance
     */
    private static $instance = null;

    /**
     * Initialization flag
     */
    private static $initialized = false;

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
        // Register activation/deactivation hooks early
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Defer all initialization until plugins_loaded
        add_action('plugins_loaded', array($this, 'init'), 10);
    }

    /**
     * Initialize the plugin
     */
    public function init() {
        // Prevent multiple initialization
        if (self::$initialized) {
            return;
        }
        
        // Check if WooCommerce is active
        if (!$this->is_woocommerce_active()) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }

        // Load plugin textdomain
        add_action('init', array($this, 'load_textdomain'));

        // Include required files
        $this->includes();

        // Initialize hooks
        $this->init_hooks();

        // Mark as initialized
        self::$initialized = true;
    }

    /**
     * Load plugin textdomain
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'tutor-zoyktech',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages'
        );
    }

    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Include required files
     */
    private function includes() {
        // Core files - include but don't instantiate
        $files = array(
            'includes/class-zoyktech-api.php',
            'includes/class-wc-zoyktech-gateway.php',
            'includes/class-payment-completion-handler.php',
            'includes/class-course-access-manager.php',
            'includes/class-tutor-woocommerce-integration.php',
            'includes/class-admin-settings.php',
            'includes/class-admin-setup-wizard.php'
        );

        foreach ($files as $file) {
            $file_path = TUTOR_ZOYKTECH_PLUGIN_PATH . $file;
            if (file_exists($file_path)) {
                require_once $file_path;
            }
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add gateway to WooCommerce
        add_filter('woocommerce_payment_gateways', array($this, 'add_gateway'));
        
        // Add settings link
        add_filter('plugin_action_links_' . TUTOR_ZOYKTECH_PLUGIN_BASENAME, array($this, 'plugin_action_links'));
        
        // Handle payment callbacks
        add_action('woocommerce_api_wc_zoyktech_gateway', array($this, 'handle_callback'));
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }

    /**
     * Add gateway to WooCommerce
     */
    public function add_gateway($gateways) {
        $gateways[] = 'WC_Zoyktech_Gateway';
        return $gateways;
    }

    /**
     * Add settings link to plugin page
     */
    public function plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=zoyktech') . '">' . __('Settings', 'tutor-zoyktech') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * Handle payment callbacks
     */
    public function handle_callback() {
        if (class_exists('WC_Zoyktech_Gateway')) {
            $gateway = new WC_Zoyktech_Gateway();
            $gateway->handle_callback();
        }
    }

    /**
     * Enqueue scripts
     */
    public function enqueue_scripts() {
        if (is_checkout() || is_cart()) {
            wp_enqueue_style(
                'tutor-zoyktech-checkout',
                TUTOR_ZOYKTECH_PLUGIN_URL . 'assets/css/checkout.css',
                array(),
                TUTOR_ZOYKTECH_VERSION
            );

            wp_enqueue_script(
                'tutor-zoyktech-checkout',
                TUTOR_ZOYKTECH_PLUGIN_URL . 'assets/js/checkout.js',
                array('jquery'),
                TUTOR_ZOYKTECH_VERSION,
                true
            );

            wp_localize_script('tutor-zoyktech-checkout', 'zoyktechCheckout', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'messages' => array(
                    'phone_invalid' => __('Please enter a valid mobile money number with country code (+260...)', 'tutor-zoyktech'),
                    'provider_detected' => __('Provider detected automatically', 'tutor-zoyktech')
                )
            ));
        }
    }

    /**
     * Plugin activation - completely silent
     */
    public function activate() {
        // Capture ALL output during activation
        ob_start();
        
        // Disable error reporting temporarily
        $old_error_reporting = error_reporting(0);
        $old_display_errors = ini_get('display_errors');
        ini_set('display_errors', 0);
        
        try {
            // Set a flag to prevent any class instantiation during activation
            define('TUTOR_ZOYKTECH_ACTIVATING', true);
            
            // Create database tables
            $this->create_tables_safe();
            
            // Set default options
            $this->set_default_options_safe();
            
            // Clear rewrite rules
            delete_option('rewrite_rules');
            
        } catch (Exception $e) {
            // Log error silently
            error_log('Tutor Zoyktech Activation Error: ' . $e->getMessage());
        } catch (Error $e) {
            // Log fatal errors silently
            error_log('Tutor Zoyktech Activation Fatal Error: ' . $e->getMessage());
        }
        
        // Restore error reporting
        error_reporting($old_error_reporting);
        ini_set('display_errors', $old_display_errors);
        
        // Discard any captured output
        ob_end_clean();
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Capture any output
        ob_start();
        
        try {
            // Clean up
            delete_option('rewrite_rules');
        } catch (Exception $e) {
            // Silent error handling
            error_log('Tutor Zoyktech Deactivation Error: ' . $e->getMessage());
        }
        
        // Discard output
        ob_end_clean();
    }

    /**
     * Create database tables safely
     */
    private function create_tables_safe() {
        global $wpdb;

        // Suppress any MySQL warnings
        $wpdb->hide_errors();

        $charset_collate = $wpdb->get_charset_collate();

        // Payment logs table
        $table_name = $wpdb->prefix . 'zoyktech_payment_logs';
        
        // Check if table exists first
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'");
        
        if (!$table_exists) {
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                order_id bigint(20) NOT NULL,
                transaction_id varchar(100) DEFAULT NULL,
                zoyktech_order_id varchar(100) NOT NULL,
                amount decimal(10,2) NOT NULL,
                currency varchar(3) NOT NULL DEFAULT 'ZMW',
                phone_number varchar(20) NOT NULL,
                provider_id int(11) NOT NULL,
                status varchar(20) NOT NULL DEFAULT 'pending',
                request_data longtext DEFAULT NULL,
                response_data longtext DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY zoyktech_order_id (zoyktech_order_id),
                KEY order_id (order_id),
                KEY status (status)
            ) $charset_collate;";

            if (!function_exists('dbDelta')) {
                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            }
            
            // Execute silently
            @dbDelta($sql);
        }
        
        // Restore error display
        $wpdb->show_errors();
    }

    /**
     * Set default options safely
     */
    private function set_default_options_safe() {
        $option_name = 'tutor_zoyktech_options';
        
        // Only set if doesn't exist
        if (!get_option($option_name)) {
            $default_options = array(
                'auto_create_products' => true,
                'default_currency' => 'ZMW',
                'enrollment_email' => true
            );
            
            add_option($option_name, $default_options, '', 'no');
        }
    }

    /**
     * Show notice if WooCommerce is not active
     */
    public function woocommerce_missing_notice() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        echo '<div class="notice notice-error"><p>';
        echo __('Tutor LMS Zoyktech WooCommerce Gateway requires WooCommerce to be installed and activated.', 'tutor-zoyktech');
        echo '</p></div>';
    }
}

// Initialize the plugin - but only if not activating
if (!defined('TUTOR_ZOYKTECH_ACTIVATING')) {
    Tutor_Zoyktech_WooCommerce_Gateway::get_instance();
}