<?php
/**
 * Course Payment Handler for Tutor LMS Zoyktech Integration
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Course Payment Handler Class
 */
class Tutor_Zoyktech_Course_Payment {

    /**
     * Zoyktech API instance
     */
    private $api;

    /**
     * Constructor
     */
    public function __construct() {
        $this->api = new Tutor_Zoyktech_API();
    }

    /**
     * Process course payment
     */
    public function process_payment($course_id, $phone_number, $provider_id = null) {
        // Validate inputs
        $this->validate_payment_data($course_id, $phone_number);

        $user_id = get_current_user_id();
        if (!$user_id) {
            throw new Exception(__('You must be logged in to purchase a course.', 'tutor-zoyktech'));
        }

        // Check if user is already enrolled
        if (tutor_utils()->is_enrolled($course_id, $user_id)) {
            throw new Exception(__('You are already enrolled in this course.', 'tutor-zoyktech'));
        }

        // Check if course exists and has a price
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'courses') {
            throw new Exception(__('Course not found.', 'tutor-zoyktech'));
        }

        $course_price = get_post_meta($course_id, '_tutor_course_price', true);
        if (empty($course_price) || $course_price <= 0) {
            throw new Exception(__('This course is free or price not set.', 'tutor-zoyktech'));
        }

        // Initiate payment via Zoyktech API
        try {
            $payment_result = $this->api->initiate_course_payment(
                $course_id,
                $user_id,
                $phone_number,
                $provider_id
            );

            // Return success response
            return array(
                'success' => true,
                'message' => __('Payment initiated successfully. Please check your mobile device for the payment prompt.', 'tutor-zoyktech'),
                'order_id' => $payment_result['order_id'],
                'provider' => $this->api->get_provider_name($provider_id ?: $this->api->detect_provider($phone_number))
            );

        } catch (Exception $e) {
            throw new Exception(
                sprintf(
                    __('Payment failed: %s', 'tutor-zoyktech'),
                    $e->getMessage()
                )
            );
        }
    }

    /**
     * Validate payment data
     */
    private function validate_payment_data($course_id, $phone_number) {
        // Validate course ID
        if (empty($course_id) || !is_numeric($course_id)) {
            throw new Exception(__('Invalid course ID.', 'tutor-zoyktech'));
        }

        // Validate phone number
        if (empty($phone_number)) {
            throw new Exception(__('Phone number is required.', 'tutor-zoyktech'));
        }

        if (!$this->api->validate_phone_number($phone_number)) {
            throw new Exception(__('Please enter a valid mobile money number with country code (e.g., +260971234567).', 'tutor-zoyktech'));
        }
    }

    /**
     * Handle payment callback
     */
    public function handle_payment_callback($callback_data) {
        if (empty($callback_data['order_id'])) {
            throw new Exception('Order ID missing in callback');
        }

        $order_id = sanitize_text_field($callback_data['order_id']);
        $transaction = $this->api->get_transaction($order_id);

        if (!$transaction) {
            throw new Exception('Transaction not found: ' . $order_id);
        }

        // Determine payment status
        $status = $this->determine_payment_status($callback_data);
        
        // Update transaction status
        $transaction_id = isset($callback_data['transaction_id']) ? $callback_data['transaction_id'] : null;
        $this->api->update_transaction_status($order_id, $status, $transaction_id);

        // Handle successful payment
        if ($status === 'completed') {
            $this->complete_course_enrollment($transaction);
        }

        return array(
            'status' => $status,
            'transaction' => $transaction
        );
    }

    /**
     * Determine payment status from callback data
     */
    private function determine_payment_status($callback_data) {
        // Check for status field in callback
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

        // Default to processing if status unclear
        return 'processing';
    }

    /**
     * Complete course enrollment after successful payment
     */
    private function complete_course_enrollment($transaction) {
        $course_id = $transaction->course_id;
        $user_id = $transaction->user_id;

        // Enroll user in course
        $enrollment_manager = new Tutor_Zoyktech_Enrollment_Manager();
        $enrollment_result = $enrollment_manager->enroll_user($course_id, $user_id);

        if ($enrollment_result) {
            // Send enrollment confirmation email
            $this->send_enrollment_confirmation($course_id, $user_id, $transaction);

            // Log successful enrollment
            error_log("TUTOR_ZOYKTECH: User $user_id successfully enrolled in course $course_id via payment {$transaction->order_id}");
        } else {
            // Log enrollment failure
            error_log("TUTOR_ZOYKTECH: Failed to enroll user $user_id in course $course_id after successful payment {$transaction->order_id}");
        }

        return $enrollment_result;
    }

    /**
     * Send enrollment confirmation email
     */
    private function send_enrollment_confirmation($course_id, $user_id, $transaction) {
        $user = get_userdata($user_id);
        $course = get_post($course_id);

        if (!$user || !$course) {
            return false;
        }

        $subject = sprintf(
            __('Course Enrollment Confirmation - %s', 'tutor-zoyktech'),
            $course->post_title
        );

        $message = sprintf(
            __('Dear %s,

Congratulations! You have successfully enrolled in the course "%s".

Course Details:
- Course: %s
- Amount Paid: %s %s
- Transaction ID: %s
- Payment Method: %s

You can now access your course at: %s

Thank you for your purchase!

Best regards,
%s', 'tutor-zoyktech'),
            $user->display_name,
            $course->post_title,
            $course->post_title,
            $transaction->amount,
            $transaction->currency,
            $transaction->order_id,
            $this->api->get_provider_name($transaction->provider_id),
            get_permalink($course_id),
            get_bloginfo('name')
        );

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        return wp_mail($user->user_email, $subject, $message, $headers);
    }

    /**
     * Get course payment status
     */
    public function get_course_payment_status($course_id, $user_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';

        $transaction = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name 
                WHERE course_id = %d AND user_id = %d 
                ORDER BY created_at DESC LIMIT 1",
                $course_id,
                $user_id
            )
        );

        if (!$transaction) {
            return null;
        }

        return array(
            'status' => $transaction->status,
            'order_id' => $transaction->order_id,
            'amount' => $transaction->amount,
            'currency' => $transaction->currency,
            'created_at' => $transaction->created_at
        );
    }

    /**
     * Check if payment is required for course
     */
    public function is_payment_required($course_id) {
        $course_price = get_post_meta($course_id, '_tutor_course_price', true);
        return !empty($course_price) && $course_price > 0;
    }

    /**
     * Get course price
     */
    public function get_course_price($course_id) {
        return get_post_meta($course_id, '_tutor_course_price', true);
    }

    /**
     * Format price for display
     */
    public function format_price($amount, $currency = 'ZMW') {
        $currency_symbols = array(
            'ZMW' => 'K',
            'USD' => '$'
        );

        $symbol = isset($currency_symbols[$currency]) ? $currency_symbols[$currency] : $currency;
        
        return $symbol . number_format($amount, 2);
    }
}

