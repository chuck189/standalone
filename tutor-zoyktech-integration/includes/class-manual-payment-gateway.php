<?php
/**
 * Manual Payment Gateway for Tutor LMS Zoyktech Integration
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Manual Payment Gateway Class
 */
class Tutor_Zoyktech_Manual_Payment_Gateway {

    /**
     * Gateway ID
     */
    const GATEWAY_ID = 'zoyktech_manual';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'), 20);
    }

    /**
     * Initialize the gateway
     */
    public function init() {
        // Only proceed if Tutor LMS is active
        if (!function_exists('tutor')) {
            return;
        }

        // Register with Tutor LMS monetization
        add_filter('tutor_monetization_gateways', array($this, 'register_gateway'));
        
        // Gateway configuration
        add_action('tutor_monetization_gateway_config_' . self::GATEWAY_ID, array($this, 'gateway_config'));
        
        // Payment form
        add_action('tutor_monetization_gateway_form_' . self::GATEWAY_ID, array($this, 'payment_form'));
        
        // Process payment
        add_action('tutor_process_checkout_' . self::GATEWAY_ID, array($this, 'process_checkout'));
        
        // Admin payment management
        add_action('wp_ajax_approve_zoyktech_payment', array($this, 'approve_payment'));
        add_action('wp_ajax_reject_zoyktech_payment', array($this, 'reject_payment'));
    }

    /**
     * Register gateway with Tutor LMS
     */
    public function register_gateway($gateways) {
        $gateways[self::GATEWAY_ID] = array(
            'label' => __('Mobile Money (Manual)', 'tutor-zoyktech'),
            'admin_label' => __('Mobile Money - Manual Approval', 'tutor-zoyktech'),
            'supported_currencies' => array('ZMW', 'USD'),
            'icon' => TUTOR_ZOYKTECH_PLUGIN_URL . 'assets/images/zoyktech-icon.png',
            'method_key' => self::GATEWAY_ID,
            'supports' => array('single_payment')
        );

        return $gateways;
    }

    /**
     * Gateway configuration
     */
    public function gateway_config() {
        $config = array(
            'title' => array(
                'type' => 'text',
                'label' => __('Gateway Title', 'tutor-zoyktech'),
                'default' => 'Mobile Money Payment',
                'desc' => __('Title shown to students', 'tutor-zoyktech')
            ),
            'description' => array(
                'type' => 'textarea',
                'label' => __('Description', 'tutor-zoyktech'),
                'default' => 'Pay using Airtel Money or MTN Mobile Money. Payment will be verified manually.',
                'desc' => __('Description shown to students', 'tutor-zoyktech')
            ),
            'instructions' => array(
                'type' => 'textarea',
                'label' => __('Payment Instructions', 'tutor-zoyktech'),
                'default' => 'Please send money to +260123456789 and enter your transaction details below.',
                'desc' => __('Instructions for making payment', 'tutor-zoyktech')
            ),
            'admin_phone' => array(
                'type' => 'text',
                'label' => __('Admin Phone Number', 'tutor-zoyktech'),
                'desc' => __('Phone number where students send payments', 'tutor-zoyktech')
            ),
            'auto_approve' => array(
                'type' => 'checkbox',
                'label' => __('Auto Approve Payments', 'tutor-zoyktech'),
                'desc' => __('Automatically approve all payments (not recommended)', 'tutor-zoyktech')
            )
        );

        return $config;
    }

    /**
     * Payment form for checkout
     */
    public function payment_form($order) {
        $settings = $this->get_gateway_settings();
        $instructions = isset($settings['instructions']) ? $settings['instructions'] : '';
        $admin_phone = isset($settings['admin_phone']) ? $settings['admin_phone'] : '';
        ?>
        <div class="tutor-manual-payment-form">
            <h4><?php _e('Mobile Money Payment Instructions', 'tutor-zoyktech'); ?></h4>
            
            <?php if ($instructions): ?>
            <div class="payment-instructions">
                <p><?php echo wp_kses_post(wpautop($instructions)); ?></p>
            </div>
            <?php endif; ?>

            <?php if ($admin_phone): ?>
            <div class="admin-phone-number">
                <strong><?php _e('Send payment to:', 'tutor-zoyktech'); ?></strong>
                <span class="phone-number"><?php echo esc_html($admin_phone); ?></span>
            </div>
            <?php endif; ?>

            <div class="payment-details-form">
                <h5><?php _e('Payment Details', 'tutor-zoyktech'); ?></h5>
                
                <p>
                    <label for="zoyktech_phone_number">
                        <?php _e('Your Phone Number', 'tutor-zoyktech'); ?> <span class="required">*</span>
                    </label>
                    <input type="tel" 
                           id="zoyktech_phone_number" 
                           name="zoyktech_phone_number" 
                           placeholder="+260971234567"
                           required />
                </p>

                <p>
                    <label for="zoyktech_provider">
                        <?php _e('Mobile Money Provider', 'tutor-zoyktech'); ?> <span class="required">*</span>
                    </label>
                    <select id="zoyktech_provider" name="zoyktech_provider" required>
                        <option value=""><?php _e('Select provider', 'tutor-zoyktech'); ?></option>
                        <option value="airtel">🟠 <?php _e('Airtel Money', 'tutor-zoyktech'); ?></option>
                        <option value="mtn">🟡 <?php _e('MTN Mobile Money', 'tutor-zoyktech'); ?></option>
                    </select>
                </p>

                <p>
                    <label for="zoyktech_transaction_id">
                        <?php _e('Transaction ID', 'tutor-zoyktech'); ?> <span class="required">*</span>
                    </label>
                    <input type="text" 
                           id="zoyktech_transaction_id" 
                           name="zoyktech_transaction_id" 
                           placeholder="e.g., MP240108.1234.A12345"
                           required />
                    <small><?php _e('Enter the transaction ID from your mobile money receipt', 'tutor-zoyktech'); ?></small>
                </p>

                <p>
                    <label for="zoyktech_amount_sent">
                        <?php _e('Amount Sent', 'tutor-zoyktech'); ?> <span class="required">*</span>
                    </label>
                    <input type="number" 
                           id="zoyktech_amount_sent" 
                           name="zoyktech_amount_sent" 
                           step="0.01"
                           value="<?php echo esc_attr($order->total_price); ?>"
                           required />
                </p>

                <p>
                    <label for="zoyktech_payment_notes">
                        <?php _e('Additional Notes', 'tutor-zoyktech'); ?>
                    </label>
                    <textarea id="zoyktech_payment_notes" 
                              name="zoyktech_payment_notes" 
                              rows="3"
                              placeholder="<?php esc_attr_e('Any additional information about your payment...', 'tutor-zoyktech'); ?>"></textarea>
                </p>
            </div>

            <div class="payment-notice">
                <p><strong><?php _e('Important:', 'tutor-zoyktech'); ?></strong></p>
                <ul>
                    <li><?php _e('Your enrollment will be pending until payment is verified', 'tutor-zoyktech'); ?></li>
                    <li><?php _e('Please ensure all details are correct', 'tutor-zoyktech'); ?></li>
                    <li><?php _e('You will receive confirmation once payment is approved', 'tutor-zoyktech'); ?></li>
                </ul>
            </div>
        </div>

        <style>
        .tutor-manual-payment-form {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            margin: 15px 0;
            background: #fff;
        }
        
        .payment-instructions {
            background: #f0f8ff;
            border: 1px solid #0073aa;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
        }
        
        .admin-phone-number {
            background: #e7f5e7;
            border: 1px solid #28a745;
            border-radius: 4px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
        }
        
        .phone-number {
            font-size: 18px;
            font-weight: bold;
            color: #28a745;
            margin-left: 10px;
        }
        
        .payment-details-form {
            margin-top: 20px;
        }
        
        .payment-details-form p {
            margin-bottom: 15px;
        }
        
        .payment-details-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .payment-details-form input,
        .payment-details-form select,
        .payment-details-form textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .payment-details-form small {
            color: #666;
            font-size: 12px;
        }
        
        .required {
            color: #e74c3c;
        }
        
        .payment-notice {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .payment-notice ul {
            margin: 10px 0 0 20px;
        }
        
        .payment-notice li {
            margin-bottom: 5px;
        }
        </style>
        <?php
    }

    /**
     * Process checkout
     */
    public function process_checkout($order) {
        // Validate required fields
        $required_fields = array(
            'zoyktech_phone_number' => __('Phone number is required', 'tutor-zoyktech'),
            'zoyktech_provider' => __('Provider is required', 'tutor-zoyktech'),
            'zoyktech_transaction_id' => __('Transaction ID is required', 'tutor-zoyktech'),
            'zoyktech_amount_sent' => __('Amount is required', 'tutor-zoyktech')
        );

        foreach ($required_fields as $field => $message) {
            if (empty($_POST[$field])) {
                tutor()->redirect_with_message(
                    tutor()->get_current_url(),
                    $message,
                    'error'
                );
                return;
            }
        }

        // Save payment details
        $payment_data = array(
            'phone_number' => sanitize_text_field($_POST['zoyktech_phone_number']),
            'provider' => sanitize_text_field($_POST['zoyktech_provider']),
            'transaction_id' => sanitize_text_field($_POST['zoyktech_transaction_id']),
            'amount_sent' => floatval($_POST['zoyktech_amount_sent']),
            'notes' => sanitize_textarea_field($_POST['zoyktech_payment_notes'] ?? ''),
            'order_total' => $order->total_price,
            'currency' => 'ZMW',
            'status' => 'pending'
        );

        // Store in database
        $this->store_payment_record($order, $payment_data);

        // Check auto-approve setting
        $settings = $this->get_gateway_settings();
        if (!empty($settings['auto_approve'])) {
            $this->approve_payment_for_order($order);
            $status = 'completed';
            $message = __('Payment submitted and automatically approved! You now have access to the course.', 'tutor-zoyktech');
        } else {
            $status = 'pending';
            $message = __('Payment submitted successfully! Your enrollment is pending verification.', 'tutor-zoyktech');
        }

        // Redirect with success message
        tutor()->redirect_with_message(
            tutor()->get_page_permalink('dashboard'),
            $message,
            'success'
        );
    }

    /**
     * Store payment record
     */
    private function store_payment_record($order, $payment_data) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';

        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $order->user_id,
                'course_id' => $order->course_id,
                'order_id' => 'MANUAL_' . time() . '_' . $order->course_id,
                'transaction_id' => $payment_data['transaction_id'],
                'amount' => $payment_data['amount_sent'],
                'currency' => $payment_data['currency'],
                'phone_number' => $payment_data['phone_number'],
                'provider_id' => $payment_data['provider'] === 'airtel' ? 289 : 237,
                'status' => $payment_data['status'],
                'payment_data' => json_encode($payment_data)
            ),
            array('%d', '%d', '%s', '%s', '%f', '%s', '%s', '%d', '%s', '%s')
        );
    }

    /**
     * Approve payment for order
     */
    private function approve_payment_for_order($order) {
        // Enroll user in course
        if (function_exists('tutor_utils')) {
            tutor_utils()->do_enroll($order->course_id, 0, $order->user_id);
        }

        // Send approval notification
        $this->send_approval_notification($order);
    }

    /**
     * Get gateway settings
     */
    private function get_gateway_settings() {
        $monetize_settings = get_option('tutor_option', array());
        return isset($monetize_settings['monetize_by'][self::GATEWAY_ID]) 
            ? $monetize_settings['monetize_by'][self::GATEWAY_ID] 
            : array();
    }

    /**
     * Send approval notification
     */
    private function send_approval_notification($order) {
        $user = get_userdata($order->user_id);
        $course = get_post($order->course_id);

        if ($user && $course) {
            $subject = sprintf(__('Payment Approved - Access to %s', 'tutor-zoyktech'), $course->post_title);
            $message = sprintf(
                __('Your mobile money payment has been approved. You now have access to the course: %s', 'tutor-zoyktech'),
                $course->post_title
            );

            wp_mail($user->user_email, $subject, $message);
        }
    }

    /**
     * Approve payment (admin action)
     */
    public function approve_payment() {
        check_ajax_referer('tutor_admin_nonce', 'nonce');

        if (!current_user_can('manage_tutor')) {
            wp_send_json_error(__('Permission denied', 'tutor-zoyktech'));
        }

        $transaction_id = intval($_POST['transaction_id']);
        
        // Update transaction status
        global $wpdb;
        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';
        
        $transaction = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $transaction_id)
        );

        if ($transaction) {
            // Update status
            $wpdb->update(
                $table_name,
                array('status' => 'completed'),
                array('id' => $transaction_id),
                array('%s'),
                array('%d')
            );

            // Enroll user
            if (function_exists('tutor_utils')) {
                tutor_utils()->do_enroll($transaction->course_id, 0, $transaction->user_id);
            }

            wp_send_json_success(__('Payment approved and user enrolled', 'tutor-zoyktech'));
        } else {
            wp_send_json_error(__('Transaction not found', 'tutor-zoyktech'));
        }
    }

    /**
     * Reject payment (admin action)
     */
    public function reject_payment() {
        check_ajax_referer('tutor_admin_nonce', 'nonce');

        if (!current_user_can('manage_tutor')) {
            wp_send_json_error(__('Permission denied', 'tutor-zoyktech'));
        }

        $transaction_id = intval($_POST['transaction_id']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';
        
        $result = $wpdb->update(
            $table_name,
            array('status' => 'rejected'),
            array('id' => $transaction_id),
            array('%s'),
            array('%d')
        );

        if ($result) {
            wp_send_json_success(__('Payment rejected', 'tutor-zoyktech'));
        } else {
            wp_send_json_error(__('Failed to reject payment', 'tutor-zoyktech'));
        }
    }
}

// Initialize the manual gateway
new Tutor_Zoyktech_Manual_Payment_Gateway();