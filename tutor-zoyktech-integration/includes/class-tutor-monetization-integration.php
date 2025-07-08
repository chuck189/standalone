<?php
/**
 * Tutor LMS Monetization Integration for Zoyktech
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tutor Monetization Integration Class
 */
class Tutor_Zoyktech_Monetization_Integration {

    /**
     * Gateway ID
     */
    const GATEWAY_ID = 'zoyktech';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('plugins_loaded', array($this, 'init'), 20);
    }

    /**
     * Initialize integration
     */
    public function init() {
        // Make sure text domain is loaded
        if (!did_action('plugins_loaded')) {
            return;
        }

        // Check if Tutor LMS Pro is active and has monetization
        if (!$this->is_tutor_monetization_active()) {
            // Create our own standalone payment system
            $this->init_standalone_payment_system();
            return;
        }

        // Register with Tutor LMS monetization system
        $this->register_with_tutor_monetization();
    }

    /**
     * Check if Tutor LMS monetization is active
     */
    private function is_tutor_monetization_active() {
        return function_exists('tutor') && 
               (class_exists('\TUTOR_ECOM\Ecom') || 
                class_exists('\TUTOR\Ecommerce') ||
                function_exists('tutor_pro'));
    }

    /**
     * Register with Tutor LMS monetization system
     */
    private function register_with_tutor_monetization() {
        // Hook into Tutor LMS payment gateways
        add_filter('tutor_payment_gateways', array($this, 'register_gateway'), 10, 1);
        add_filter('tutor_available_payment_gateways', array($this, 'register_gateway'), 10, 1);
        
        // For newer versions of Tutor LMS
        add_filter('tutor_monetization_gateways', array($this, 'register_gateway'), 10, 1);
        add_filter('tutor_ecommerce_payment_gateways', array($this, 'register_gateway'), 10, 1);
        
        // Gateway configuration
        add_action('tutor_payment_gateway_config_' . self::GATEWAY_ID, array($this, 'gateway_config'));
        add_action('tutor_payment_gateway_form_' . self::GATEWAY_ID, array($this, 'gateway_form'));
        add_action('tutor_process_payment_' . self::GATEWAY_ID, array($this, 'process_payment'));
        
        // Add settings
        add_action('tutor_monetization_settings_after', array($this, 'add_gateway_settings'));
    }

    /**
     * Initialize standalone payment system
     */
    private function init_standalone_payment_system() {
        // Create custom payment system that works without Tutor LMS Pro
        add_action('tutor_course/single/enrolled/before', array($this, 'add_payment_section'), 5);
        add_action('wp_ajax_tutor_zoyktech_standalone_payment', array($this, 'handle_standalone_payment'));
        add_action('wp_ajax_nopriv_tutor_zoyktech_standalone_payment', array($this, 'handle_standalone_payment'));
        
        // Add custom monetization option
        add_filter('tutor_monetization_options', array($this, 'add_monetization_option'), 10, 1);
    }

    /**
     * Register payment gateway
     */
    public function register_gateway($gateways) {
        $options = get_option('tutor_zoyktech_options', array());
        
        $gateways[self::GATEWAY_ID] = array(
            'label' => 'Zoyktech Mobile Money',
            'admin_label' => 'Mobile Money (Zoyktech)',
            'description' => 'Accept mobile money payments for courses in Zambia',
            'logo' => TUTOR_ZOYKTECH_PLUGIN_URL . 'assets/images/zoyktech-icon.png',
            'icon' => '📱',
            'supported_currencies' => array('ZMW', 'USD'),
            'supports' => array(
                'single_payment',
                'instant_enrollment',
                'mobile_payments'
            ),
            'enabled' => !empty($options['enable_zoyktech']),
            'config_url' => admin_url('admin.php?page=tutor-zoyktech-settings'),
            'test_mode' => isset($options['zoyktech_environment']) && $options['zoyktech_environment'] === 'sandbox',
            'settings' => $this->get_gateway_settings()
        );

        return $gateways;
    }

    /**
     * Get gateway settings
     */
    private function get_gateway_settings() {
        $options = get_option('tutor_zoyktech_options', array());
        
        return array(
            'merchant_id' => isset($options['zoyktech_merchant_id']) ? $options['zoyktech_merchant_id'] : '',
            'public_id' => isset($options['zoyktech_public_id']) ? $options['zoyktech_public_id'] : '',
            'secret_key' => isset($options['zoyktech_secret_key']) ? $options['zoyktech_secret_key'] : '',
            'environment' => isset($options['zoyktech_environment']) ? $options['zoyktech_environment'] : 'sandbox',
            'currency' => isset($options['zoyktech_currency']) ? $options['zoyktech_currency'] : 'ZMW',
            'debug' => isset($options['zoyktech_debug']) ? $options['zoyktech_debug'] : false
        );
    }

    /**
     * Gateway configuration form
     */
    public function gateway_config() {
        $options = get_option('tutor_zoyktech_options', array());
        ?>
        <div class="tutor-monetization-gateway-config">
            <h3><?php esc_html_e('Zoyktech Mobile Money Configuration', 'tutor-zoyktech'); ?></h3>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="zoyktech_enable"><?php esc_html_e('Enable Gateway', 'tutor-zoyktech'); ?></label>
                    </th>
                    <td>
                        <input type="checkbox" id="zoyktech_enable" name="tutor_zoyktech_options[enable_zoyktech]" value="1" 
                               <?php checked(!empty($options['enable_zoyktech'])); ?> />
                        <p class="description"><?php esc_html_e('Enable Zoyktech mobile money payments', 'tutor-zoyktech'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="zoyktech_merchant_id"><?php esc_html_e('Merchant ID', 'tutor-zoyktech'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="zoyktech_merchant_id" name="tutor_zoyktech_options[zoyktech_merchant_id]" 
                               value="<?php echo esc_attr($options['zoyktech_merchant_id'] ?? ''); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e('Your Zoyktech merchant ID', 'tutor-zoyktech'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="zoyktech_public_id"><?php esc_html_e('Public ID', 'tutor-zoyktech'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="zoyktech_public_id" name="tutor_zoyktech_options[zoyktech_public_id]" 
                               value="<?php echo esc_attr($options['zoyktech_public_id'] ?? ''); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e('Your Zoyktech public ID', 'tutor-zoyktech'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="zoyktech_secret_key"><?php esc_html_e('Secret Key', 'tutor-zoyktech'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="zoyktech_secret_key" name="tutor_zoyktech_options[zoyktech_secret_key]" 
                               value="<?php echo esc_attr($options['zoyktech_secret_key'] ?? ''); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e('Your Zoyktech secret key', 'tutor-zoyktech'); ?></p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="zoyktech_environment"><?php esc_html_e('Environment', 'tutor-zoyktech'); ?></label>
                    </th>
                    <td>
                        <select id="zoyktech_environment" name="tutor_zoyktech_options[zoyktech_environment]">
                            <option value="sandbox" <?php selected($options['zoyktech_environment'] ?? 'sandbox', 'sandbox'); ?>>
                                <?php esc_html_e('Sandbox (Testing)', 'tutor-zoyktech'); ?>
                            </option>
                            <option value="live" <?php selected($options['zoyktech_environment'] ?? 'sandbox', 'live'); ?>>
                                <?php esc_html_e('Live (Production)', 'tutor-zoyktech'); ?>
                            </option>
                        </select>
                        <p class="description"><?php esc_html_e('Select environment for processing payments', 'tutor-zoyktech'); ?></p>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <button type="button" class="button button-secondary" onclick="testZoyktechConnection()">
                    <?php esc_html_e('Test Connection', 'tutor-zoyktech'); ?>
                </button>
            </p>
            
            <div id="zoyktech-test-result"></div>
        </div>
        
        <script>
        function testZoyktechConnection() {
            const button = document.querySelector('button');
            const resultDiv = document.getElementById('zoyktech-test-result');
            
            button.disabled = true;
            button.textContent = <?php echo json_encode(esc_html__('Testing...', 'tutor-zoyktech')); ?>;
            
            fetch(ajaxurl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'tutor_zoyktech_test_connection',
                    nonce: <?php echo json_encode(wp_create_nonce('tutor_zoyktech_test')); ?>,
                    merchant_id: document.getElementById('zoyktech_merchant_id').value,
                    public_id: document.getElementById('zoyktech_public_id').value,
                    secret_key: document.getElementById('zoyktech_secret_key').value,
                    environment: document.getElementById('zoyktech_environment').value
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    resultDiv.innerHTML = '<div class="notice notice-success"><p>' + data.data.message + '</p></div>';
                } else {
                    resultDiv.innerHTML = '<div class="notice notice-error"><p>' + data.data.message + '</p></div>';
                }
            })
            .catch(error => {
                resultDiv.innerHTML = '<div class="notice notice-error"><p><?php echo esc_js(esc_html__('Connection test failed', 'tutor-zoyktech')); ?></p></div>';
            })
            .finally(() => {
                button.disabled = false;
                button.textContent = <?php echo json_encode(esc_html__('Test Connection', 'tutor-zoyktech')); ?>;
            });
        }
        </script>
        <?php
    }

    /**
     * Gateway payment form
     */
    public function gateway_form($order) {
        if (class_exists('Tutor_Zoyktech_Gateway')) {
            $gateway = new Tutor_Zoyktech_Gateway();
            $gateway->gateway_form($order);
        }
    }

    /**
     * Process payment
     */
    public function process_payment($order) {
        if (class_exists('Tutor_Zoyktech_Gateway')) {
            $gateway = new Tutor_Zoyktech_Gateway();
            $gateway->process_checkout($order);
        }
    }

    /**
     * Add gateway settings to monetization page
     */
    public function add_gateway_settings() {
        ?>
        <div class="tutor-option-field-row">
            <div class="tutor-option-field-label">
                <label><?php esc_html_e('Zoyktech Mobile Money', 'tutor-zoyktech'); ?></label>
            </div>
            <div class="tutor-option-field">
                <a href="<?php echo esc_url(admin_url('admin.php?page=tutor-zoyktech-settings')); ?>" class="button">
                    <?php esc_html_e('Configure Zoyktech Settings', 'tutor-zoyktech'); ?>
                </a>
                <p class="desc"><?php esc_html_e('Configure mobile money payments for Zambian learners', 'tutor-zoyktech'); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Add payment section for standalone mode
     */
    public function add_payment_section() {
        global $post;
        
        if (!is_user_logged_in() || !$post) {
            return;
        }

        $course_id = $post->ID;
        $user_id = get_current_user_id();
        
        // Check if user is already enrolled
        if (function_exists('tutor_utils') && tutor_utils()->is_enrolled($course_id, $user_id)) {
            return;
        }

        // Check if course has a price
        $course_price = get_post_meta($course_id, '_tutor_course_price', true);
        if (empty($course_price) || $course_price <= 0) {
            return;
        }

        // Check if Zoyktech is enabled
        $options = get_option('tutor_zoyktech_options', array());
        if (empty($options['enable_zoyktech'])) {
            return;
        }

        // Display payment form
        $template_path = TUTOR_ZOYKTECH_PLUGIN_PATH . 'templates/payment-form.php';
        if (file_exists($template_path)) {
            include $template_path;
        }
    }

    /**
     * Handle standalone payment
     */
    public function handle_standalone_payment() {
        if (class_exists('Tutor_Zoyktech_Frontend_Payment')) {
            $frontend_payment = new Tutor_Zoyktech_Frontend_Payment();
            $frontend_payment->process_payment_ajax();
        }
    }

    /**
     * Add monetization option
     */
    public function add_monetization_option($options) {
        $options['zoyktech'] = array(
            'label' => 'Zoyktech Mobile Money',
            'description' => 'Enable mobile money payments for courses',
            'icon' => '📱'
        );
        
        return $options;
    }

    /**
     * Check if gateway is configured
     */
    public function is_gateway_configured() {
        $options = get_option('tutor_zoyktech_options', array());
        
        return !empty($options['zoyktech_merchant_id']) &&
               !empty($options['zoyktech_public_id']) &&
               !empty($options['zoyktech_secret_key']);
    }

    /**
     * Get gateway status
     */
    public function get_gateway_status() {
        $options = get_option('tutor_zoyktech_options', array());
        
        if (empty($options['enable_zoyktech'])) {
            return 'disabled';
        }
        
        if (!$this->is_gateway_configured()) {
            return 'not_configured';
        }
        
        return 'active';
    }
}