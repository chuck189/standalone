<?php
/**
 * Checkout Customizer - Streamline checkout for mobile money payments
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Checkout Customizer Class
 */
class Tutor_Zoyktech_Checkout_Customizer {

    /**
     * Constructor
     */
    public function __construct() {
        // Only initialize if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Customize checkout form
        add_action('init', array($this, 'init_checkout_customizations'));
    }

    /**
     * Initialize checkout customizations
     */
    public function init_checkout_customizations() {
        // Hide billing fields for mobile money payments
        add_filter('woocommerce_checkout_fields', array($this, 'customize_checkout_fields'), 20);
        
        // Pre-populate billing fields with dummy data
        add_action('woocommerce_checkout_init', array($this, 'pre_populate_billing_fields'));
        
        // Auto-fill billing fields during checkout
        add_action('woocommerce_checkout_update_order_meta', array($this, 'auto_fill_billing_data'), 10, 1);
        
        // Hide billing details section if only mobile money is available
        add_action('wp_enqueue_scripts', array($this, 'enqueue_checkout_customizations'));
        
        // Show only mobile money payment method for course products
        add_filter('woocommerce_available_payment_gateways', array($this, 'filter_payment_methods_for_courses'));
        
        // Remove unnecessary checkout fields
        add_filter('woocommerce_enable_order_notes_field', '__return_false');
        add_filter('woocommerce_checkout_show_terms', '__return_false');
        
        // Simplify checkout process
        add_action('woocommerce_checkout_process', array($this, 'validate_mobile_money_checkout'));
    }

    /**
     * Customize checkout fields
     */
    public function customize_checkout_fields($fields) {
        // Check if cart contains only course products
        if (!$this->cart_contains_only_courses()) {
            return $fields;
        }

        // Remove or modify billing fields
        $fields['billing']['billing_company']['required'] = false;
        $fields['billing']['billing_address_1']['required'] = false;
        $fields['billing']['billing_address_2']['required'] = false;
        $fields['billing']['billing_city']['required'] = false;
        $fields['billing']['billing_state']['required'] = false;
        $fields['billing']['billing_postcode']['required'] = false;
        $fields['billing']['billing_country']['required'] = false;
        
        // Keep only essential fields
        $essential_fields = array(
            'billing_first_name',
            'billing_last_name',
            'billing_email',
            'billing_phone'
        );

        foreach ($fields['billing'] as $key => $field) {
            if (!in_array($key, $essential_fields)) {
                unset($fields['billing'][$key]);
            }
        }

        // Remove shipping fields entirely for course products
        unset($fields['shipping']);
        
        // Remove order notes
        unset($fields['order']['order_comments']);

        return $fields;
    }

    /**
     * Pre-populate billing fields with dummy data
     */
    public function pre_populate_billing_fields($checkout) {
        // Only for course products
        if (!$this->cart_contains_only_courses()) {
            return;
        }

        // Get user data if logged in
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $first_name = $user->first_name ?: 'Student';
            $last_name = $user->last_name ?: 'User';
            $email = $user->user_email;
        } else {
            $first_name = 'Student';
            $last_name = 'User';
            $email = 'student@example.com';
        }

        // Pre-fill with dummy data
        $dummy_data = array(
            'billing_first_name' => $first_name,
            'billing_last_name' => $last_name,
            'billing_email' => $email,
            'billing_phone' => '+260971234567',
            'billing_company' => '',
            'billing_address_1' => 'Lusaka',
            'billing_address_2' => '',
            'billing_city' => 'Lusaka',
            'billing_state' => 'Lusaka',
            'billing_postcode' => '10101',
            'billing_country' => 'ZM'
        );

        // Apply dummy data to checkout
        foreach ($dummy_data as $key => $value) {
            if (empty($checkout->get_value($key))) {
                $checkout->set_value($key, $value);
            }
        }
    }

    /**
     * Auto-fill billing data during checkout
     */
    public function auto_fill_billing_data($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }

        // Only for course products
        if (!$this->order_contains_only_courses($order)) {
            return;
        }

        // Ensure billing data is present
        $billing_data = array(
            'billing_company' => '',
            'billing_address_1' => 'Lusaka',
            'billing_address_2' => '',
            'billing_city' => 'Lusaka',
            'billing_state' => 'Lusaka',
            'billing_postcode' => '10101',
            'billing_country' => 'ZM'
        );

        foreach ($billing_data as $key => $value) {
            if (empty($order->get_meta('_' . $key))) {
                $order->update_meta_data('_' . $key, $value);
            }
        }

        $order->save();
    }

    /**
     * Enqueue checkout customizations
     */
    public function enqueue_checkout_customizations() {
        if (!is_checkout()) {
            return;
        }

        // Add custom CSS and JavaScript
        wp_add_inline_style('tutor-zoyktech-checkout', $this->get_checkout_styles());
        wp_add_inline_script('tutor-zoyktech-checkout', $this->get_checkout_scripts());
    }

    /**
     * Filter payment methods for course products
     */
    public function filter_payment_methods_for_courses($available_gateways) {
        // Only filter on checkout page
        if (!is_checkout()) {
            return $available_gateways;
        }

        // Only for course products
        if (!$this->cart_contains_only_courses()) {
            return $available_gateways;
        }

        // Show only Zoyktech mobile money payment
        if (isset($available_gateways['zoyktech'])) {
            return array('zoyktech' => $available_gateways['zoyktech']);
        }

        return $available_gateways;
    }

    /**
     * Validate mobile money checkout
     */
    public function validate_mobile_money_checkout() {
        // Only validate for course products
        if (!$this->cart_contains_only_courses()) {
            return;
        }

        // Check if Zoyktech is selected
        $payment_method = WC()->session->get('chosen_payment_method');
        
        if ($payment_method !== 'zoyktech') {
            wc_add_notice(__('Please select mobile money payment for course purchases.', 'tutor-zoyktech'), 'error');
        }
    }

    /**
     * Check if cart contains only course products
     */
    private function cart_contains_only_courses() {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $course_id = get_post_meta($product_id, '_tutor_course_id', true);
            
            if (!$course_id) {
                return false; // Not a course product
            }
        }

        return true;
    }

    /**
     * Check if order contains only course products
     */
    private function order_contains_only_courses($order) {
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $course_id = get_post_meta($product_id, '_tutor_course_id', true);
            
            if (!$course_id) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get checkout styles
     */
    private function get_checkout_styles() {
        return "
        /* Hide billing details section for course purchases */
        .woocommerce-checkout .checkout-course-products .woocommerce-billing-fields h3 {
            display: none;
        }
        
        .woocommerce-checkout .checkout-course-products .woocommerce-billing-fields {
            display: none;
        }
        
        .woocommerce-checkout .checkout-course-products .woocommerce-additional-fields {
            display: none;
        }
        
        /* Simplify checkout layout */
        .woocommerce-checkout .checkout-course-products #customer_details {
            width: 100%;
        }
        
        .woocommerce-checkout .checkout-course-products #order_review {
            width: 100%;
            margin-top: 20px;
        }
        
        /* Mobile money focus */
        .woocommerce-checkout .checkout-course-products .wc_payment_methods {
            background: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .woocommerce-checkout .checkout-course-products .wc_payment_method_zoyktech {
            border: 2px solid #667eea;
            border-radius: 8px;
            padding: 15px;
            background: white;
        }
        
        .woocommerce-checkout .checkout-course-products .payment_method_zoyktech label {
            font-weight: 600;
            font-size: 16px;
            color: #2c3e50;
        }
        
        /* Course checkout header */
        .course-checkout-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .course-checkout-header h2 {
            margin: 0;
            font-size: 24px;
        }
        
        .course-checkout-header p {
            margin: 8px 0 0 0;
            opacity: 0.9;
        }
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            .woocommerce-checkout .checkout-course-products #customer_details,
            .woocommerce-checkout .checkout-course-products #order_review {
                width: 100%;
                float: none;
            }
            
            .course-checkout-header {
                padding: 15px;
            }
            
            .course-checkout-header h2 {
                font-size: 20px;
            }
        }
        ";
    }

    /**
     * Get checkout scripts
     */
    private function get_checkout_scripts() {
        return "
        jQuery(document).ready(function($) {
            // Check if this is a course-only checkout
            if ($('.woocommerce-checkout').length && $('.checkout-course-products').length) {
                // Hide billing fields
                $('#customer_details .woocommerce-billing-fields').hide();
                $('#customer_details .woocommerce-additional-fields').hide();
                
                // Auto-select mobile money payment
                $('#payment_method_zoyktech').prop('checked', true);
                
                // Add course checkout header
                if (!$('.course-checkout-header').length) {
                    $('#customer_details').before('<div class=\"course-checkout-header\"><h2>📱 Mobile Money Payment</h2><p>Complete your course purchase with mobile money</p></div>');
                }
                
                // Focus on mobile money fields
                $('.payment_method_zoyktech').addClass('active');
                
                // Trigger payment method change
                $('body').trigger('update_checkout');
            }
            
            // Handle form submission
            $('form.checkout').on('submit', function() {
                // Ensure billing fields are populated
                if (!$('#billing_first_name').val()) {
                    $('#billing_first_name').val('Student');
                }
                if (!$('#billing_last_name').val()) {
                    $('#billing_last_name').val('User');
                }
                if (!$('#billing_email').val()) {
                    $('#billing_email').val('student@example.com');
                }
                if (!$('#billing_phone').val()) {
                    $('#billing_phone').val('+260971234567');
                }
                
                // Set default address fields
                $('#billing_address_1').val('Lusaka');
                $('#billing_city').val('Lusaka');
                $('#billing_state').val('Lusaka');
                $('#billing_postcode').val('10101');
                $('#billing_country').val('ZM');
            });
        });
        ";
    }
}

// Initialize checkout customizer
new Tutor_Zoyktech_Checkout_Customizer();