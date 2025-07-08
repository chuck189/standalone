<?php
/**
 * WooCommerce Zoyktech Payment Gateway
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * WooCommerce Zoyktech Gateway Class
 */
class WC_Zoyktech_Gateway extends WC_Payment_Gateway {

    /**
     * Constructor
     */
    public function __construct() {
        $this->id = 'zoyktech';
        $this->icon = TUTOR_ZOYKTECH_PLUGIN_URL . 'assets/images/zoyktech-icon.png';
        $this->has_fields = true;
        $this->method_title = __('Zoyktech Mobile Money', 'tutor-zoyktech');
        $this->method_description = __('Accept mobile money payments via Zoyktech gateway (Airtel Money and MTN Mobile Money)', 'tutor-zoyktech');
        $this->supports = array(
            'products'
        );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title = $this->get_option('title');
        $this->description = $this->get_option('description');
        $this->enabled = $this->get_option('enabled');
        $this->testmode = 'yes' === $this->get_option('testmode');
        $this->merchant_id = $this->get_option('merchant_id');
        $this->public_id = $this->get_option('public_id');
        $this->secret_key = $this->get_option('secret_key');
        $this->debug = 'yes' === $this->get_option('debug');

        // API instance
        $this->api = new Tutor_Zoyktech_API(
            $this->merchant_id,
            $this->public_id,
            $this->secret_key,
            $this->testmode ? 'sandbox' : 'live',
            $this->debug
        );

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
    }

    /**
     * Check if gateway is available
     */
    public function is_available() {
        if ('yes' !== $this->enabled) {
            return false;
        }

        if (!$this->merchant_id || !$this->public_id || !$this->secret_key) {
            return false;
        }

        return true;
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'tutor-zoyktech'),
                'type' => 'checkbox',
                'label' => __('Enable Zoyktech Mobile Money', 'tutor-zoyktech'),
                'default' => 'no'
            ),
            'title' => array(
                'title' => __('Title', 'tutor-zoyktech'),
                'type' => 'text',
                'description' => __('This controls the title displayed to customers during checkout.', 'tutor-zoyktech'),
                'default' => __('Mobile Money', 'tutor-zoyktech'),
                'desc_tip' => true,
            ),
            'description' => array(
                'title' => __('Description', 'tutor-zoyktech'),
                'type' => 'textarea',
                'description' => __('Payment method description that customers will see on your checkout.', 'tutor-zoyktech'),
                'default' => __('Pay securely with your mobile money account (Airtel Money or MTN Mobile Money).', 'tutor-zoyktech'),
                'desc_tip' => true,
            ),
            'testmode' => array(
                'title' => __('Test mode', 'tutor-zoyktech'),
                'label' => __('Enable Test Mode', 'tutor-zoyktech'),
                'type' => 'checkbox',
                'description' => __('Place the payment gateway in test mode using sandbox API keys.', 'tutor-zoyktech'),
                'default' => 'yes',
                'desc_tip' => true,
            ),
            'merchant_id' => array(
                'title' => __('Merchant ID', 'tutor-zoyktech'),
                'type' => 'text',
                'description' => __('Get your Merchant ID from your Zoyktech dashboard.', 'tutor-zoyktech'),
                'default' => '',
                'desc_tip' => true,
            ),
            'public_id' => array(
                'title' => __('Public ID', 'tutor-zoyktech'),
                'type' => 'text',
                'description' => __('Get your Public ID from your Zoyktech dashboard.', 'tutor-zoyktech'),
                'default' => '',
                'desc_tip' => true,
            ),
            'secret_key' => array(
                'title' => __('Secret Key', 'tutor-zoyktech'),
                'type' => 'password',
                'description' => __('Get your Secret Key from your Zoyktech dashboard.', 'tutor-zoyktech'),
                'default' => '',
                'desc_tip' => true,
            ),
            'debug' => array(
                'title' => __('Debug logging', 'tutor-zoyktech'),
                'label' => __('Enable logging', 'tutor-zoyktech'),
                'type' => 'checkbox',
                'description' => __('Log Zoyktech events for debugging purposes.', 'tutor-zoyktech'),
                'default' => 'no',
                'desc_tip' => true,
            ),
        );
    }

    /**
     * Payment fields for checkout
     */
    public function payment_fields() {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
        ?>
        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-form" class="wc-payment-form">
            <div class="zoyktech-payment-fields">
                <p class="form-row form-row-wide">
                    <label for="zoyktech-phone-number">
                        <?php esc_html_e('Mobile Money Number', 'tutor-zoyktech'); ?> <abbr class="required" title="required">*</abbr>
                    </label>
                    <input 
                        id="zoyktech-phone-number" 
                        name="zoyktech-phone-number" 
                        type="tel" 
                        placeholder="+260971234567"
                        pattern="^\+260[0-9]{9}$"
                        title="<?php esc_attr_e('Please enter a valid Zambian mobile number with country code', 'tutor-zoyktech'); ?>"
                        autocomplete="tel" 
                        required 
                    />
                    <small class="form-help">
                        <?php esc_html_e('Enter your mobile money number with country code (e.g., +260971234567)', 'tutor-zoyktech'); ?>
                    </small>
                </p>

                <p class="form-row form-row-wide">
                    <label for="zoyktech-provider">
                        <?php esc_html_e('Mobile Money Provider', 'tutor-zoyktech'); ?>
                    </label>
                    <select id="zoyktech-provider" name="zoyktech-provider" class="select">
                        <option value=""><?php esc_html_e('Auto-detect from phone number', 'tutor-zoyktech'); ?></option>
                        <option value="289">🟠 <?php esc_html_e('Airtel Money', 'tutor-zoyktech'); ?></option>
                        <option value="237">🟡 <?php esc_html_e('MTN Mobile Money', 'tutor-zoyktech'); ?></option>
                    </select>
                    <small class="form-help">
                        <?php esc_html_e('Provider will be automatically detected from your phone number', 'tutor-zoyktech'); ?>
                    </small>
                </p>

                <div class="zoyktech-supported-providers">
                    <h5><?php esc_html_e('Supported Providers:', 'tutor-zoyktech'); ?></h5>
                    <div class="provider-list">
                        <div class="provider-item">
                            <span class="provider-icon">🟠</span>
                            <span class="provider-name"><?php esc_html_e('Airtel Money', 'tutor-zoyktech'); ?></span>
                            <span class="provider-numbers">+260 95, 96, 97</span>
                        </div>
                        <div class="provider-item">
                            <span class="provider-icon">🟡</span>
                            <span class="provider-name"><?php esc_html_e('MTN Mobile Money', 'tutor-zoyktech'); ?></span>
                            <span class="provider-numbers">+260 76, 77</span>
                        </div>
                    </div>
                </div>

                <div class="payment-notice">
                    <p><strong><?php esc_html_e('Payment Process:', 'tutor-zoyktech'); ?></strong></p>
                    <ol>
                        <li><?php esc_html_e('Enter your mobile money number above', 'tutor-zoyktech'); ?></li>
                        <li><?php esc_html_e('Click "Place Order" to continue', 'tutor-zoyktech'); ?></li>
                        <li><?php esc_html_e('You will receive a payment prompt on your mobile device', 'tutor-zoyktech'); ?></li>
                        <li><?php esc_html_e('Confirm the payment on your phone', 'tutor-zoyktech'); ?></li>
                        <li><?php esc_html_e('Your order will be completed automatically', 'tutor-zoyktech'); ?></li>
                    </ol>
                </div>
            </div>
        </fieldset>
        <?php
    }

    /**
     * Enqueue payment scripts
     */
    public function payment_scripts() {
        if (!is_cart() && !is_checkout()) {
            return;
        }

        if ('no' === $this->enabled) {
            return;
        }

        wp_enqueue_script(
            'woocommerce_zoyktech',
            TUTOR_ZOYKTECH_PLUGIN_URL . 'assets/js/checkout.js',
            array('jquery'),
            TUTOR_ZOYKTECH_VERSION,
            true
        );
    }

    /**
     * Validate payment fields
     */
    public function validate_fields() {
        if (empty($_POST['zoyktech-phone-number'])) {
            wc_add_notice(__('Mobile money number is required.', 'tutor-zoyktech'), 'error');
            return false;
        }

        $phone_number = sanitize_text_field($_POST['zoyktech-phone-number']);
        
        // Validate phone number format
        if (!preg_match('/^\+260[0-9]{9}$/', $phone_number)) {
            wc_add_notice(__('Please enter a valid mobile money number with country code (e.g., +260971234567).', 'tutor-zoyktech'), 'error');
            return false;
        }

        return true;
    }

    /**
     * Process the payment
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);
        $phone_number = sanitize_text_field($_POST['zoyktech-phone-number']);
        $provider_id = sanitize_text_field($_POST['zoyktech-provider']);

        try {
            // Auto-detect provider if not selected
            if (empty($provider_id)) {
                $provider_id = $this->api->detect_provider($phone_number);
            }

            // Generate unique order ID for Zoyktech
            $zoyktech_order_id = 'WC_' . $order_id . '_' . time();

            // Prepare payment data
            $payment_data = array(
                'merchant_id' => $this->merchant_id,
                'customer_id' => $phone_number,
                'order_id' => $zoyktech_order_id,
                'amount' => number_format((float) $order->get_total(), 2, '.', ''),
                'currency' => $order->get_currency(),
                'country' => $this->api->detect_country($phone_number),
                'callback_url' => WC()->api_request_url('WC_Zoyktech_Gateway'),
                'provider_id' => (int) $provider_id,
                'extra' => array(
                    'wc_order_id' => $order_id,
                    'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'customer_email' => $order->get_billing_email()
                )
            );

            $this->log('Processing payment for order ' . $order_id . ': ' . print_r($payment_data, true));

            // Make API request
            $response = $this->api->initiate_payment($payment_data);

            // Store payment log
            $this->store_payment_log($order_id, $zoyktech_order_id, $payment_data, $response);

            // Update order meta
            $order->update_meta_data('_zoyktech_order_id', $zoyktech_order_id);
            $order->update_meta_data('_zoyktech_phone_number', $phone_number);
            $order->update_meta_data('_zoyktech_provider_id', $provider_id);
            $order->save();

            // Mark order as pending payment
            $order->update_status('pending', __('Awaiting mobile money payment confirmation.', 'tutor-zoyktech'));

            // Reduce stock levels
            wc_reduce_stock_levels($order_id);

            // Remove cart
            WC()->cart->empty_cart();

            // Return successful response
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );

        } catch (Exception $e) {
            $this->log('Payment failed for order ' . $order_id . ': ' . $e->getMessage());
            
            wc_add_notice(__('Payment error: ', 'tutor-zoyktech') . $e->getMessage(), 'error');
            return array(
                'result' => 'fail',
                'redirect' => ''
            );
        }
    }

    /**
     * Handle payment callback
     */
    public function handle_callback() {
        $this->log('Payment callback received');

        try {
            // Get callback data
            $callback_data = $this->get_callback_data();

            if (empty($callback_data)) {
                throw new Exception('No callback data received');
            }

            $this->log('Callback data: ' . print_r($callback_data, true));

            // Verify callback signature if available
            if (isset($callback_data['signature'])) {
                if (!$this->api->verify_callback_signature($callback_data)) {
                    throw new Exception('Invalid callback signature');
                }
            }

            // Get order ID from callback
            $zoyktech_order_id = isset($callback_data['order_id']) ? $callback_data['order_id'] : '';
            
            if (empty($zoyktech_order_id)) {
                throw new Exception('Order ID missing in callback');
            }

            // Find WooCommerce order
            $orders = wc_get_orders(array(
                'meta_key' => '_zoyktech_order_id',
                'meta_value' => $zoyktech_order_id,
                'limit' => 1
            ));

            if (empty($orders)) {
                throw new Exception('Order not found: ' . $zoyktech_order_id);
            }

            $order = $orders[0];

            // Determine payment status
            $status = $this->determine_payment_status($callback_data);
            
            $this->log('Payment status for order ' . $order->get_id() . ': ' . $status);

            // Update payment log
            $this->update_payment_log($zoyktech_order_id, $status, $callback_data);

            // Handle payment result
            switch ($status) {
                case 'completed':
                    $this->handle_successful_payment($order, $callback_data);
                    break;
                    
                case 'failed':
                case 'cancelled':
                case 'expired':
                    $this->handle_failed_payment($order, $callback_data, $status);
                    break;
                    
                default:
                    $this->log('Unknown payment status: ' . $status);
                    break;
            }

            // Send success response
            wp_send_json_success(array(
                'message' => 'Callback processed successfully',
                'order_id' => $order->get_id(),
                'status' => $status
            ));

        } catch (Exception $e) {
            $this->log('Callback error: ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Handle successful payment
     */
    private function handle_successful_payment($order, $callback_data) {
        // Check if already processed
        if ($order->is_paid()) {
            $this->log('Order ' . $order->get_id() . ' already paid');
            return;
        }

        // Get transaction ID from callback
        $transaction_id = isset($callback_data['transaction_id']) ? $callback_data['transaction_id'] : '';
        
        // Complete payment
        $order->payment_complete($transaction_id);
        
        // Add order note
        $order->add_order_note(
            sprintf(
                __('Mobile money payment completed. Transaction ID: %s', 'tutor-zoyktech'),
                $transaction_id
            )
        );

        $this->log('Payment completed for order ' . $order->get_id());
    }

    /**
     * Handle failed payment
     */
    private function handle_failed_payment($order, $callback_data, $status) {
        $order->update_status('failed', sprintf(
            __('Mobile money payment %s.', 'tutor-zoyktech'),
            $status
        ));

        $this->log('Payment ' . $status . ' for order ' . $order->get_id());
    }

    /**
     * Get callback data
     */
    private function get_callback_data() {
        $callback_data = array();
        
        if (!empty($_POST)) {
            $callback_data = $_POST;
        } elseif (!empty($_GET)) {
            $callback_data = $_GET;
        } else {
            $json_input = file_get_contents('php://input');
            if (!empty($json_input)) {
                $decoded = json_decode($json_input, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $callback_data = $decoded;
                }
            }
        }
        
        return $callback_data;
    }

    /**
     * Determine payment status from callback
     */
    private function determine_payment_status($callback_data) {
        // Check for status field
        if (isset($callback_data['status'])) {
            $api_status = intval($callback_data['status']);
            
            switch ($api_status) {
                case 2:
                    return 'completed';
                case 3:
                    return 'failed';
                case 4:
                    return 'cancelled';
                case 5:
                    return 'expired';
                default:
                    return 'processing';
            }
        }

        // Check for result code
        if (isset($callback_data['result']['code'])) {
            $result_code = intval($callback_data['result']['code']);
            
            if ($result_code === 0) {
                return 'completed';
            } else {
                return 'failed';
            }
        }

        return 'processing';
    }

    /**
     * Store payment log
     */
    private function store_payment_log($order_id, $zoyktech_order_id, $request_data, $response_data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'zoyktech_payment_logs';

        $wpdb->insert(
            $table_name,
            array(
                'order_id' => $order_id,
                'zoyktech_order_id' => $zoyktech_order_id,
                'amount' => $request_data['amount'],
                'currency' => $request_data['currency'],
                'phone_number' => $request_data['customer_id'],
                'provider_id' => $request_data['provider_id'],
                'status' => 'pending',
                'request_data' => json_encode($request_data),
                'response_data' => json_encode($response_data)
            ),
            array('%d', '%s', '%f', '%s', '%s', '%d', '%s', '%s', '%s')
        );
    }

    /**
     * Update payment log
     */
    private function update_payment_log($zoyktech_order_id, $status, $callback_data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'zoyktech_payment_logs';

        $update_data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );

        if (isset($callback_data['transaction_id'])) {
            $update_data['transaction_id'] = $callback_data['transaction_id'];
        }

        $wpdb->update(
            $table_name,
            $update_data,
            array('zoyktech_order_id' => $zoyktech_order_id),
            array('%s', '%s', '%s'),
            array('%s')
        );
    }

    /**
     * Log messages
     */
    private function log($message) {
        if ($this->debug) {
            if (empty($this->logger)) {
                $this->logger = wc_get_logger();
            }
            $this->logger->info($message, array('source' => 'zoyktech'));
        }
    }
}