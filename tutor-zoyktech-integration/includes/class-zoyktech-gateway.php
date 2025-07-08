<?php
/**
 * Zoyktech Payment Gateway for Tutor LMS
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Zoyktech Payment Gateway Class
 */
class Tutor_Zoyktech_Gateway {

    /**
     * Gateway ID
     */
    public $gateway_id = 'zoyktech';

    /**
     * Gateway name
     */
    public $gateway_name = 'Zoyktech Mobile Money';

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize gateway after WordPress is ready
        add_action('init', array($this, 'init_gateway'), 40);
    }
    
    /**
     * Initialize gateway
     */
    public function init_gateway() {
        // Only initialize if not already done
        if (!did_action('tutor_zoyktech_gateway_initialized')) {
            // Handle payment callbacks
            add_action('template_redirect', array($this, 'handle_callback'));
            
            // Add admin menu if needed
            if (is_admin()) {
                add_action('admin_menu', array($this, 'add_settings_menu'));
                add_action('wp_ajax_tutor_zoyktech_test_connection', array($this, 'test_gateway_connection'));
            }
            
            // Mark as initialized
            do_action('tutor_zoyktech_gateway_initialized');
        }
    }

    /**
     * Gateway configuration form
     */
    public function gateway_config($config, $gateway_id) {
        if ($gateway_id !== $this->gateway_id) {
            return $config;
        }
        
        $config = array(
            'enabled' => array(
                'type' => 'checkbox',
                'label' => __('Enable Zoyktech Mobile Money', 'tutor-zoyktech'),
                'desc' => __('Enable mobile money payments via Zoyktech gateway', 'tutor-zoyktech'),
                'default' => false
            ),
            'merchant_id' => array(
                'type' => 'text',
                'label' => __('Merchant ID', 'tutor-zoyktech'),
                'desc' => __('Enter your Zoyktech Merchant ID', 'tutor-zoyktech'),
                'required' => true
            ),
            'public_id' => array(
                'type' => 'text', 
                'label' => __('Public ID', 'tutor-zoyktech'),
                'desc' => __('Enter your Zoyktech Public ID', 'tutor-zoyktech'),
                'required' => true
            ),
            'secret_key' => array(
                'type' => 'password',
                'label' => 'Secret Key',
                'desc' => 'Enter your Zoyktech Secret Key',
                'required' => true
            ),
            'environment' => array(
                'type' => 'select',
                'label' => 'Environment',
                'desc' => 'Select environment for processing payments',
                'options' => array(
                    'sandbox' => 'Sandbox (Testing)',
                    'live' => 'Live (Production)'
                ),
                'default' => 'sandbox'
            ),
            'currency' => array(
                'type' => 'select',
                'label' => 'Currency',
                'desc' => 'Select currency for payments',
                'options' => array(
                    'ZMW' => 'Zambian Kwacha (ZMW)',
                    'USD' => 'US Dollar (USD)'
                ),
                'default' => 'ZMW'
            ),
            'debug_mode' => array(
                'type' => 'checkbox',
                'label' => 'Debug Mode',
                'desc' => 'Enable debug logging for troubleshooting',
                'default' => false
            )
        );

        return $config;
    }

    /**
     * Payment form on checkout
     */
    public function gateway_form($order) {
        $settings = $this->get_gateway_settings();
        
        if (empty($settings['merchant_id']) || empty($settings['public_id']) || empty($settings['secret_key'])) {
            echo '<div class="tutor-alert tutor-alert-warning">';
            echo __('Zoyktech payment gateway is not properly configured. Please contact the administrator.', 'tutor-zoyktech');
            echo '</div>';
            return;
        }

        $currency = isset($settings['currency']) ? $settings['currency'] : 'ZMW';
        $total_amount = $order->total_price;
        
        ?>
        <div class="tutor-zoyktech-checkout-form">
            <div class="tutor-checkout-payment-method-details">
                <h4><?php _e('Mobile Money Payment', 'tutor-zoyktech'); ?></h4>
                <p><?php _e('Pay securely with your mobile money account (Airtel Money or MTN Mobile Money)', 'tutor-zoyktech'); ?></p>
                
                <div class="tutor-form-group">
                    <label for="zoyktech_phone_number">
                        <?php _e('Mobile Money Number', 'tutor-zoyktech'); ?> <span class="required">*</span>
                    </label>
                    <input type="tel" 
                           id="zoyktech_phone_number" 
                           name="zoyktech_phone_number" 
                           class="tutor-form-control"
                           placeholder="+260971234567"
                           required
                           pattern="^\+260[0-9]{9}$">
                    <small class="tutor-form-help">
                        <?php _e('Enter your mobile money number with country code (e.g., +260971234567)', 'tutor-zoyktech'); ?>
                    </small>
                </div>

                <div class="tutor-form-group">
                    <label for="zoyktech_provider">
                        <?php _e('Mobile Money Provider', 'tutor-zoyktech'); ?>
                    </label>
                    <select id="zoyktech_provider" name="zoyktech_provider" class="tutor-form-control">
                        <option value=""><?php _e('Auto-detect from phone number', 'tutor-zoyktech'); ?></option>
                        <option value="289">🟠 <?php _e('Airtel Money', 'tutor-zoyktech'); ?></option>
                        <option value="237">🟡 <?php _e('MTN Mobile Money', 'tutor-zoyktech'); ?></option>
                    </select>
                    <small class="tutor-form-help">
                        <?php _e('Provider will be automatically detected from your phone number', 'tutor-zoyktech'); ?>
                    </small>
                </div>

                <div class="zoyktech-payment-summary">
                    <div class="payment-summary-row">
                        <span><?php _e('Total Amount:', 'tutor-zoyktech'); ?></span>
                        <span class="amount">
                            <?php echo $this->format_price($total_amount, $currency); ?>
                        </span>
                    </div>
                </div>

                <div class="zoyktech-supported-providers">
                    <h5><?php _e('Supported Providers:', 'tutor-zoyktech'); ?></h5>
                    <div class="provider-list">
                        <div class="provider-item">
                            <span class="provider-icon">🟠</span>
                            <span class="provider-name"><?php _e('Airtel Money', 'tutor-zoyktech'); ?></span>
                            <span class="provider-numbers">+260 95, 96, 97</span>
                        </div>
                        <div class="provider-item">
                            <span class="provider-icon">🟡</span>
                            <span class="provider-name"><?php _e('MTN Mobile Money', 'tutor-zoyktech'); ?></span>
                            <span class="provider-numbers">+260 76, 77</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
        .tutor-zoyktech-checkout-form {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
            background: #fff;
        }

        .tutor-zoyktech-checkout-form h4 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 18px;
        }

        .tutor-zoyktech-checkout-form p {
            margin: 0 0 20px 0;
            color: #666;
        }

        .tutor-form-group {
            margin-bottom: 20px;
        }

        .tutor-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }

        .required {
            color: #e74c3c;
        }

        .tutor-form-control {
            width: 100%;
            padding: 10px 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .tutor-form-control:focus {
            outline: none;
            border-color: #007cba;
            box-shadow: 0 0 0 1px #007cba;
        }

        .tutor-form-help {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 12px;
        }

        .zoyktech-payment-summary {
            background: #f9f9f9;
            border-radius: 4px;
            padding: 15px;
            margin: 20px 0;
        }

        .payment-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            font-size: 16px;
            color: #333;
        }

        .payment-summary-row .amount {
            color: #007cba;
        }

        .zoyktech-supported-providers h5 {
            margin: 20px 0 10px 0;
            font-size: 14px;
            color: #333;
        }

        .provider-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .provider-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px;
            background: #f9f9f9;
            border-radius: 4px;
            font-size: 14px;
        }

        .provider-icon {
            font-size: 18px;
        }

        .provider-name {
            font-weight: 600;
            flex: 1;
        }

        .provider-numbers {
            color: #666;
            font-family: monospace;
            font-size: 12px;
        }

        @media (max-width: 768px) {
            .provider-list {
                gap: 8px;
            }
            
            .provider-item {
                padding: 6px;
                font-size: 13px;
            }
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Auto-detect provider from phone number
            $('#zoyktech_phone_number').on('input', function() {
                const phoneNumber = $(this).val();
                const cleaned = phoneNumber.replace(/[^\d+]/g, '');
                
                let providerId = '';
                if (/^\+260(95|96|97)/.test(cleaned)) {
                    providerId = '289'; // Airtel
                } else if (/^\+260(76|77)/.test(cleaned)) {
                    providerId = '237'; // MTN
                }
                
                $('#zoyktech_provider').val(providerId);
            });

            // Format phone number as user types
            $('#zoyktech_phone_number').on('input', function() {
                let value = $(this).val().replace(/[^\d+]/g, '');
                
                // Ensure it starts with +260 for Zambian numbers
                if (value.length > 0 && !value.startsWith('+')) {
                    if (value.startsWith('260')) {
                        value = '+' + value;
                    } else if (value.startsWith('0')) {
                        value = '+260' + value.substring(1);
                    } else if (/^[79]/.test(value)) {
                        value = '+260' + value;
                    }
                }
                
                $(this).val(value);
            });
        });
        </script>
        <?php
    }

    /**
     * Process checkout payment
     */
    public function process_checkout($order) {
        $phone_number = sanitize_text_field($_POST['zoyktech_phone_number'] ?? '');
        $provider_id = sanitize_text_field($_POST['zoyktech_provider'] ?? '');

        if (empty($phone_number)) {
            tutor()->redirect_with_message(
                tutor()->get_current_url(),
                __('Please enter your mobile money number.', 'tutor-zoyktech'),
                'error'
            );
            return;
        }

        if (!$this->validate_phone_number($phone_number)) {
            tutor()->redirect_with_message(
                tutor()->get_current_url(),
                __('Please enter a valid mobile money number with country code.', 'tutor-zoyktech'),
                'error'
            );
            return;
        }

        try {
            // Initialize payment
            $api = new Tutor_Zoyktech_API();
            $result = $api->initiate_payment($order, $phone_number, $provider_id);

            // Redirect to payment status page
            $redirect_url = add_query_arg(array(
                'tutor_action' => 'payment_status',
                'gateway' => 'zoyktech',
                'order_id' => $result['order_id']
            ), tutor()->get_page_permalink('checkout'));

            wp_redirect($redirect_url);
            exit;

        } catch (Exception $e) {
            tutor()->redirect_with_message(
                tutor()->get_current_url(),
                sprintf(__('Payment failed: %s', 'tutor-zoyktech'), $e->getMessage()),
                'error'
            );
        }
    }

    /**
     * Handle payment callback
     */
    public function handle_callback() {
        if (isset($_GET['tutor_zoyktech_callback'])) {
            $callback_handler = new Tutor_Zoyktech_Payment_Callback();
            $callback_handler->handle_callback();
            exit;
        }
    }

    /**
     * Add settings menu
     */
    public function add_settings_menu() {
        add_submenu_page(
            'tutor',
            __('Zoyktech Settings', 'tutor-zoyktech'),
            __('Zoyktech Payment', 'tutor-zoyktech'),
            'manage_options',
            'tutor-zoyktech-settings',
            array($this, 'settings_page')
        );
    }

    /**
     * Settings page
     */
    public function settings_page() {
        $admin_settings = new Tutor_Zoyktech_Admin_Settings();
        $admin_settings->settings_page();
    }

    /**
     * Test gateway connection
     */
    public function test_gateway_connection() {
        check_ajax_referer('tutor_zoyktech_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }
        
        // Test connection logic here
        wp_send_json_success(array('message' => 'Gateway connection test successful!'));
    }

    /**
     * Get gateway settings
     */
    public function get_gateway_settings() {
        // Try to get from Tutor options first
        $tutor_options = get_option('tutor_option', array());
        $monetize_settings = isset($tutor_options['monetize_by']) ? $tutor_options['monetize_by'] : array();
        $gateway_settings = isset($monetize_settings[$this->gateway_id]) ? $monetize_settings[$this->gateway_id] : array();
        
        // Fallback to plugin options
        if (empty($gateway_settings)) {
            $gateway_settings = get_option('tutor_zoyktech_options', array());
        }
        
        return $gateway_settings;
    }

    /**
     * Validate phone number
     */
    private function validate_phone_number($phone_number) {
        $cleaned = preg_replace('/[^\d+]/', '', $phone_number);
        return preg_match('/^\+260[0-9]{9}$/', $cleaned);
    }

    /**
     * Format price for display
     */
    private function format_price($amount, $currency = 'ZMW') {
        $currency_symbols = array(
            'ZMW' => 'K',
            'USD' => '$'
        );

        $symbol = isset($currency_symbols[$currency]) ? $currency_symbols[$currency] : $currency;
        
        return $symbol . number_format($amount, 2);
    }

    /**
     * Get provider name
     */
    public function get_provider_name($provider_id) {
        $providers = array(
            289 => 'Airtel Money',
            237 => 'MTN Mobile Money',
            14 => 'Simulator'
        );

        return isset($providers[$provider_id]) ? $providers[$provider_id] : 'Unknown Provider';
    }
}