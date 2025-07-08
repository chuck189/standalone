<?php
/**
 * Simple Gateway Integration for Tutor LMS Zoyktech
 * 
 * This provides a minimal, safe integration that won't cause conflicts
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple Gateway Integration Class
 */
class Tutor_Zoyktech_Simple_Gateway_Integration {

    /**
     * Gateway ID
     */
    const GATEWAY_ID = 'zoyktech_mobile_money';

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize very late to avoid conflicts
        add_action('admin_init', array($this, 'maybe_add_gateway'), 50);
        add_action('wp_loaded', array($this, 'init_frontend_features'), 50);
    }

    /**
     * Maybe add gateway to Tutor LMS
     */
    public function maybe_add_gateway() {
        // Only in admin area
        if (!is_admin()) {
            return;
        }

        // Check if we should try to integrate with Tutor monetization
        if ($this->should_integrate_with_tutor()) {
            $this->try_tutor_integration();
        } else {
            // Fallback to standalone mode
            $this->init_standalone_mode();
        }
    }

    /**
     * Check if we should integrate with Tutor monetization
     */
    private function should_integrate_with_tutor() {
        // Check if Tutor LMS Pro monetization features are available
        return (
            function_exists('tutor_pro') ||
            class_exists('TUTOR\Ecommerce') ||
            class_exists('TUTOR_ECOM\Ecom')
        );
    }

    /**
     * Try to integrate with Tutor LMS monetization
     */
    private function try_tutor_integration() {
        // Try different hook points for different versions
        $hooks = array(
            'tutor_monetization_gateways',
            'tutor_payment_gateways', 
            'tutor_available_payment_gateways',
            'tutor_ecommerce_payment_gateways'
        );

        foreach ($hooks as $hook) {
            if (has_filter($hook)) {
                add_filter($hook, array($this, 'register_gateway'), 999, 1);
            }
        }

        // Add configuration hooks
        add_action('tutor_monetization_gateway_config_' . self::GATEWAY_ID, array($this, 'show_config'));
        add_action('tutor_monetization_gateway_form_' . self::GATEWAY_ID, array($this, 'show_payment_form'));
    }

    /**
     * Initialize standalone mode
     */
    private function init_standalone_mode() {
        // Add our own payment system independent of Tutor monetization
        add_action('tutor_course/single/enrolled/before', array($this, 'add_standalone_payment_section'), 5);
        add_filter('tutor_dashboard/nav_ui_items', array($this, 'add_dashboard_menu'));
    }

    /**
     * Initialize frontend features
     */
    public function init_frontend_features() {
        // Frontend payment handling
        add_action('wp_ajax_tutor_zoyktech_process_payment', array($this, 'handle_payment'));
        add_action('wp_ajax_nopriv_tutor_zoyktech_process_payment', array($this, 'handle_payment'));
        
        // Payment status checking
        add_action('wp_ajax_tutor_zoyktech_check_status', array($this, 'check_payment_status'));
        add_action('wp_ajax_nopriv_tutor_zoyktech_check_status', array($this, 'check_payment_status'));
    }

    /**
     * Register gateway with Tutor LMS
     */
    public function register_gateway($gateways) {
        if (!is_array($gateways)) {
            $gateways = array();
        }

        try {
            $options = get_option('tutor_zoyktech_options', array());
            
            $gateways[self::GATEWAY_ID] = array(
                'label' => 'Mobile Money (Zoyktech)',
                'admin_label' => 'Zoyktech Mobile Money',
                'description' => 'Mobile money payments for Zambian learners',
                'icon' => TUTOR_ZOYKTECH_PLUGIN_URL . 'assets/images/zoyktech-icon.png',
                'supported_currencies' => array('ZMW', 'USD'),
                'enabled' => !empty($options['enable_zoyktech']),
                'method_key' => self::GATEWAY_ID,
                'supports' => array('single_payment'),
                'settings' => $options
            );
        } catch (Exception $e) {
            // Silently fail to avoid breaking the admin
            error_log('Tutor Zoyktech: Gateway registration failed - ' . $e->getMessage());
        }

        return $gateways;
    }

    /**
     * Show gateway configuration
     */
    public function show_config() {
        $settings_url = admin_url('admin.php?page=tutor-zoyktech-settings');
        ?>
        <div class="tutor-gateway-config">
            <h3>Zoyktech Mobile Money Configuration</h3>
            <p>Configure your Zoyktech mobile money payment gateway settings.</p>
            <p>
                <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary">
                    Configure Zoyktech Settings
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Show payment form
     */
    public function show_payment_form($order) {
        if (class_exists('Tutor_Zoyktech_Gateway')) {
            $gateway = new Tutor_Zoyktech_Gateway();
            if (method_exists($gateway, 'gateway_form')) {
                $gateway->gateway_form($order);
            }
        }
    }

    /**
     * Add standalone payment section
     */
    public function add_standalone_payment_section() {
        global $post;

        if (!$post || !is_user_logged_in()) {
            return;
        }

        $course_id = $post->ID;
        $user_id = get_current_user_id();

        // Check if already enrolled
        if (function_exists('tutor_utils') && tutor_utils()->is_enrolled($course_id, $user_id)) {
            return;
        }

        // Check if course has price
        $course_price = get_post_meta($course_id, '_tutor_course_price', true);
        if (empty($course_price) || $course_price <= 0) {
            return;
        }

        // Check if Zoyktech is enabled
        $options = get_option('tutor_zoyktech_options', array());
        if (empty($options['enable_zoyktech'])) {
            return;
        }

        // Include payment form template
        $template_path = TUTOR_ZOYKTECH_PLUGIN_PATH . 'templates/payment-form.php';
        if (file_exists($template_path)) {
            $price = $course_price; // For template compatibility
            include $template_path;
        }
    }

    /**
     * Add dashboard menu
     */
    public function add_dashboard_menu($nav_items) {
        $options = get_option('tutor_zoyktech_options', array());
        if (!empty($options['enable_zoyktech'])) {
            $nav_items['payment-history'] = array(
                'title' => 'Payment History',
                'icon' => 'tutor-icon-purchase',
                'auth_cap' => tutor()->student_role ?? 'subscriber',
            );
        }
        return $nav_items;
    }

    /**
     * Handle payment processing
     */
    public function handle_payment() {
        try {
            check_ajax_referer('tutor_zoyktech_nonce', 'nonce');

            if (class_exists('Tutor_Zoyktech_Frontend_Payment')) {
                $frontend_payment = new Tutor_Zoyktech_Frontend_Payment();
                $frontend_payment->process_payment_ajax();
            } else {
                wp_send_json_error(array('message' => 'Payment handler not available'));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Check payment status
     */
    public function check_payment_status() {
        try {
            check_ajax_referer('tutor_zoyktech_nonce', 'nonce');

            if (class_exists('Tutor_Zoyktech_Frontend_Payment')) {
                $frontend_payment = new Tutor_Zoyktech_Frontend_Payment();
                $frontend_payment->check_payment_status_ajax();
            } else {
                wp_send_json_error(array('message' => 'Status checker not available'));
            }
        } catch (Exception $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Check if gateway is properly configured
     */
    public function is_configured() {
        $options = get_option('tutor_zoyktech_options', array());
        
        return !empty($options['zoyktech_merchant_id']) &&
               !empty($options['zoyktech_public_id']) &&
               !empty($options['zoyktech_secret_key']);
    }
}

// Initialize the simple integration
new Tutor_Zoyktech_Simple_Gateway_Integration();