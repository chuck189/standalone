<?php
/**
 * User Registration Handler - Auto-create accounts for mobile money purchases
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * User Registration Handler Class
 */
class Tutor_Zoyktech_User_Registration {

    /**
     * Constructor
     */
    public function __construct() {
        // Only initialize if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Handle user registration for mobile money payments
        add_action('woocommerce_checkout_order_processed', array($this, 'handle_mobile_money_registration'), 10, 1);
        
        // Auto-login after registration
        add_action('woocommerce_created_customer', array($this, 'auto_login_new_customer'), 10, 1);
        
        // Simplify registration for mobile money
        add_filter('woocommerce_registration_generate_username', array($this, 'generate_username_from_phone'), 10, 2);
        add_filter('woocommerce_registration_generate_password', array($this, 'generate_simple_password'), 10, 1);
    }

    /**
     * Handle user registration for mobile money payments
     */
    public function handle_mobile_money_registration($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order || $order->get_payment_method() !== 'zoyktech') {
            return;
        }

        // Skip if user is already logged in
        if (is_user_logged_in()) {
            return;
        }

        // Get order details
        $email = $order->get_billing_email();
        $phone = $order->get_meta('_zoyktech_phone_number') ?: $order->get_billing_phone();
        $first_name = $order->get_billing_first_name();
        $last_name = $order->get_billing_last_name();

        // Check if user already exists
        $user = get_user_by('email', $email);
        
        if (!$user) {
            // Create new user
            $user_id = $this->create_mobile_money_user($email, $phone, $first_name, $last_name);
            
            if ($user_id) {
                // Update order with user ID
                $order->set_customer_id($user_id);
                $order->save();
                
                // Send welcome email
                $this->send_welcome_email($user_id, $order);
                
                error_log("ZOYKTECH_USER: Created user {$user_id} for mobile money order {$order_id}");
            }
        } else {
            // Update existing user
            $order->set_customer_id($user->ID);
            $order->save();
        }
    }

    /**
     * Create new user for mobile money payment
     */
    private function create_mobile_money_user($email, $phone, $first_name, $last_name) {
        // Generate username from phone number
        $username = $this->generate_username_from_phone($phone);
        
        // Generate simple password
        $password = $this->generate_simple_password();
        
        // Create user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            error_log("ZOYKTECH_USER: Failed to create user: " . $user_id->get_error_message());
            return false;
        }

        // Update user meta
        update_user_meta($user_id, 'first_name', $first_name);
        update_user_meta($user_id, 'last_name', $last_name);
        update_user_meta($user_id, 'nickname', $first_name . ' ' . $last_name);
        update_user_meta($user_id, 'display_name', $first_name . ' ' . $last_name);
        update_user_meta($user_id, 'billing_phone', $phone);
        update_user_meta($user_id, 'mobile_money_phone', $phone);
        update_user_meta($user_id, 'created_via_mobile_money', 'yes');
        update_user_meta($user_id, 'registration_date', current_time('mysql'));

        return $user_id;
    }

    /**
     * Generate username from phone number
     */
    public function generate_username_from_phone($phone = '', $email = '') {
        if (!empty($phone)) {
            // Clean phone number
            $clean_phone = preg_replace('/[^\d]/', '', $phone);
            
            // Use last 9 digits
            $username = 'user' . substr($clean_phone, -9);
            
            // Check if username exists
            if (!username_exists($username)) {
                return $username;
            }
            
            // Add suffix if exists
            $counter = 1;
            while (username_exists($username . $counter)) {
                $counter++;
            }
            
            return $username . $counter;
        }
        
        // Fallback to email-based username
        if (!empty($email)) {
            return sanitize_user(substr($email, 0, strpos($email, '@')));
        }
        
        return 'user' . time();
    }

    /**
     * Generate simple password
     */
    public function generate_simple_password($length = 8) {
        // Generate a simple memorable password
        $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[rand(0, strlen($chars) - 1)];
        }
        
        return $password;
    }

    /**
     * Auto-login new customer
     */
    public function auto_login_new_customer($user_id) {
        // Only auto-login for mobile money registrations
        $created_via_mobile = get_user_meta($user_id, 'created_via_mobile_money', true);
        
        if ($created_via_mobile === 'yes') {
            // Log in the user
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id);
            
            error_log("ZOYKTECH_USER: Auto-logged in user {$user_id}");
        }
    }

    /**
     * Send welcome email to new user
     */
    private function send_welcome_email($user_id, $order) {
        $user = get_userdata($user_id);
        
        if (!$user) {
            return;
        }

        $subject = sprintf(__('[%s] Welcome - Your Account Has Been Created', 'tutor-zoyktech'), get_bloginfo('name'));

        $message = sprintf(
            __('Hello %s,

Welcome to %s! Your account has been created automatically when you purchased a course via mobile money.

Account Details:
- Username: %s
- Email: %s
- Phone: %s

You can now:
✅ Access your purchased courses
✅ Track your learning progress
✅ Manage your account settings

Access your dashboard: %s

Your recent order #%s has been processed successfully.

If you have any questions, please contact our support team.

Happy learning!
%s Team', 'tutor-zoyktech'),
            $user->display_name,
            get_bloginfo('name'),
            $user->user_login,
            $user->user_email,
            get_user_meta($user_id, 'billing_phone', true),
            home_url('/my-account'),
            $order->get_order_number(),
            get_bloginfo('name')
        );

        wp_mail($user->user_email, $subject, $message);
    }
}

// Initialize user registration handler
new Tutor_Zoyktech_User_Registration();