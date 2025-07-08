<?php
/**
 * Payment Completion Handler - Ensures Automatic Payment Processing
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment Completion Handler Class
 */
class Tutor_Zoyktech_Payment_Completion_Handler {

    /**
     * Constructor
     */
    public function __construct() {
        // Only initialize if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Handle Zoyktech payment callbacks with highest priority
        add_action('woocommerce_api_wc_zoyktech_gateway', array($this, 'handle_zoyktech_callback'), 5);
        
        // Force order completion for successful Zoyktech payments
        add_action('woocommerce_payment_complete', array($this, 'force_order_completion'), 10, 1);
        
        // Immediate enrollment after payment completion
        add_action('woocommerce_order_status_completed', array($this, 'immediate_course_enrollment'), 5, 1);
        
        // Unlock course content after enrollment
        add_action('tutor_after_enrolled', array($this, 'unlock_course_content'), 10, 2);
        
        // Fix pending payment status
        add_action('wp_loaded', array($this, 'check_pending_payments'));
    }

    /**
     * Handle Zoyktech payment callback with improved reliability
     */
    public function handle_zoyktech_callback() {
        error_log('ZOYKTECH_CALLBACK: Received payment callback');
        
        try {
            // Get callback data
            $callback_data = $this->get_callback_data();
            
            if (empty($callback_data)) {
                error_log('ZOYKTECH_CALLBACK: No callback data received');
                wp_die('No callback data', 'Callback Error', array('response' => 400));
            }

            error_log('ZOYKTECH_CALLBACK: Data - ' . print_r($callback_data, true));

            // Find the order
            $order = $this->find_order_from_callback($callback_data);
            
            if (!$order) {
                error_log('ZOYKTECH_CALLBACK: Order not found');
                wp_die('Order not found', 'Order Error', array('response' => 404));
            }

            // Determine payment status
            $payment_status = $this->determine_payment_status($callback_data);
            error_log("ZOYKTECH_CALLBACK: Payment status for order {$order->get_id()}: {$payment_status}");

            // Process based on status
            switch ($payment_status) {
                case 'completed':
                case 'success':
                    $this->complete_payment($order, $callback_data);
                    break;
                    
                case 'failed':
                case 'cancelled':
                case 'expired':
                    $this->fail_payment($order, $callback_data, $payment_status);
                    break;
                    
                default:
                    $this->update_payment_status($order, $payment_status, $callback_data);
                    break;
            }

            // Send success response
            wp_send_json_success(array(
                'message' => 'Callback processed successfully',
                'order_id' => $order->get_id(),
                'status' => $payment_status
            ));

        } catch (Exception $e) {
            error_log('ZOYKTECH_CALLBACK: Error - ' . $e->getMessage());
            wp_send_json_error(array('message' => $e->getMessage()));
        }
    }

    /**
     * Complete payment and trigger immediate enrollment
     */
    private function complete_payment($order, $callback_data) {
        $order_id = $order->get_id();
        
        // Check if already completed
        if ($order->has_status('completed')) {
            error_log("ZOYKTECH_CALLBACK: Order {$order_id} already completed");
            return;
        }

        // Get transaction ID
        $transaction_id = $this->extract_transaction_id($callback_data);
        
        // Complete the payment
        $order->payment_complete($transaction_id);
        
        // Add order note
        $order->add_order_note(
            sprintf(
                __('Mobile money payment completed via Zoyktech. Transaction ID: %s', 'tutor-zoyktech'),
                $transaction_id ?: 'N/A'
            )
        );

        // Force order status to completed
        $order->update_status('completed', __('Payment completed via mobile money', 'tutor-zoyktech'));
        
        // Update payment log
        $this->update_payment_log($order, 'completed', $callback_data);
        
        // Trigger immediate enrollment
        $this->process_immediate_enrollment($order);
        
        error_log("ZOYKTECH_CALLBACK: Order {$order_id} completed successfully");
    }

    /**
     * Process immediate course enrollment
     */
    private function process_immediate_enrollment($order) {
        $user_id = $order->get_user_id();
        
        if (!$user_id) {
            error_log('ZOYKTECH_ENROLLMENT: No user ID for order ' . $order->get_id());
            return;
        }

        $enrolled_courses = array();
        
        // Process each item in the order
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $course_id = $this->get_course_by_product($product_id);
            
            if ($course_id) {
                $success = $this->enroll_user_immediately($course_id, $user_id, $order->get_id());
                
                if ($success) {
                    $enrolled_courses[] = $course_id;
                    error_log("ZOYKTECH_ENROLLMENT: User {$user_id} enrolled in course {$course_id}");
                } else {
                    error_log("ZOYKTECH_ENROLLMENT: Failed to enroll user {$user_id} in course {$course_id}");
                }
            }
        }

        // Update order meta with enrolled courses
        if (!empty($enrolled_courses)) {
            $order->update_meta_data('_enrolled_courses', $enrolled_courses);
            $order->save();
        }
    }

    /**
     * Enroll user immediately with full access
     */
    private function enroll_user_immediately($course_id, $user_id, $order_id) {
        // Check if already enrolled
        if (tutor_utils()->is_enrolled($course_id, $user_id)) {
            error_log("ZOYKTECH_ENROLLMENT: User {$user_id} already enrolled in course {$course_id}");
            return true;
        }

        // Enroll user
        $enrollment_id = tutor_utils()->do_enroll($course_id, $order_id, $user_id);
        
        if ($enrollment_id) {
            // Mark as paid enrollment
            add_post_meta($enrollment_id, '_tutor_enrolled_by_order', $order_id);
            add_post_meta($enrollment_id, '_tutor_enrolled_by_zoyktech', 'yes');
            add_post_meta($enrollment_id, '_enrollment_date', current_time('mysql'));
            
            // Ensure course content is accessible
            $this->ensure_course_access($course_id, $user_id);
            
            // Send enrollment notification
            $this->send_enrollment_notification($course_id, $user_id, $order_id);
            
            return true;
        }

        return false;
    }

    /**
     * Ensure course content is accessible (unlock videos)
     */
    private function ensure_course_access($course_id, $user_id) {
        // Get all course lessons and topics
        $lessons = tutor_utils()->get_course_content_list($course_id);
        
        if (!empty($lessons)) {
            foreach ($lessons as $lesson) {
                // Unlock lesson
                $this->unlock_lesson_content($lesson->ID, $user_id);
                
                // Get topics for this lesson
                $topics = tutor_utils()->get_topics_by_lesson($lesson->ID);
                
                if (!empty($topics)) {
                    foreach ($topics as $topic) {
                        $this->unlock_lesson_content($topic->ID, $user_id);
                    }
                }
            }
        }

        // Clear any content restrictions
        delete_user_meta($user_id, "_tutor_course_restriction_{$course_id}");
        
        // Set course progress to allow access
        tutor_utils()->mark_lesson_complete($course_id, $user_id);
        
        error_log("ZOYKTECH_ACCESS: Unlocked content for user {$user_id} in course {$course_id}");
    }

    /**
     * Unlock individual lesson/topic content
     */
    private function unlock_lesson_content($content_id, $user_id) {
        // Remove any access restrictions
        delete_post_meta($content_id, "_tutor_restricted_for_user_{$user_id}");
        
        // Mark as accessible
        update_user_meta($user_id, "_tutor_content_access_{$content_id}", 'granted');
    }

    /**
     * Force order completion for Zoyktech payments
     */
    public function force_order_completion($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order || $order->get_payment_method() !== 'zoyktech') {
            return;
        }

        // If payment is complete but order isn't, force completion
        if (!$order->has_status('completed')) {
            $order->update_status('completed', __('Forced completion for mobile money payment', 'tutor-zoyktech'));
            error_log("ZOYKTECH_FORCE: Forced completion for order {$order_id}");
        }
    }

    /**
     * Immediate course enrollment handler
     */
    public function immediate_course_enrollment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order || $order->get_payment_method() !== 'zoyktech') {
            return;
        }

        // Double-check enrollment happened
        $enrolled_courses = $order->get_meta('_enrolled_courses');
        
        if (empty($enrolled_courses)) {
            error_log("ZOYKTECH_DOUBLE_CHECK: Re-processing enrollment for order {$order_id}");
            $this->process_immediate_enrollment($order);
        }
    }

    /**
     * Unlock course content after enrollment
     */
    public function unlock_course_content($enrollment_id, $course_id) {
        $enrollment = get_post($enrollment_id);
        
        if (!$enrollment) {
            return;
        }

        $user_id = $enrollment->post_author;
        
        // Check if this was a Zoyktech enrollment
        $is_zoyktech = get_post_meta($enrollment_id, '_tutor_enrolled_by_zoyktech', true);
        
        if ($is_zoyktech === 'yes') {
            $this->ensure_course_access($course_id, $user_id);
            error_log("ZOYKTECH_UNLOCK: Content unlocked for enrollment {$enrollment_id}");
        }
    }

    /**
     * Check and fix pending payments
     */
    public function check_pending_payments() {
        // Only run occasionally to avoid performance issues
        if (rand(1, 100) !== 1) {
            return;
        }

        // Find Zoyktech orders that are stuck in pending
        $pending_orders = wc_get_orders(array(
            'status' => 'pending',
            'payment_method' => 'zoyktech',
            'date_created' => '>=' . (time() - 3600), // Last hour only
            'limit' => 10
        ));

        foreach ($pending_orders as $order) {
            $this->check_order_payment_status($order);
        }
    }

    /**
     * Check individual order payment status
     */
    private function check_order_payment_status($order) {
        $zoyktech_order_id = $order->get_meta('_zoyktech_order_id');
        
        if (empty($zoyktech_order_id)) {
            return;
        }

        // Check with Zoyktech API if payment was actually completed
        try {
            $gateway = new WC_Zoyktech_Gateway();
            $api_status = $gateway->api->check_payment_status($zoyktech_order_id);
            
            if ($api_status === 'completed') {
                error_log("ZOYKTECH_CHECK: Found completed payment for pending order {$order->get_id()}");
                $this->complete_payment($order, array('status' => 'completed'));
            }
            
        } catch (Exception $e) {
            error_log("ZOYKTECH_CHECK: Error checking status for order {$order->get_id()}: " . $e->getMessage());
        }
    }

    /**
     * Get callback data from request
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
     * Find order from callback data
     */
    private function find_order_from_callback($callback_data) {
        $zoyktech_order_id = isset($callback_data['order_id']) ? $callback_data['order_id'] : '';
        
        if (empty($zoyktech_order_id)) {
            return false;
        }

        $orders = wc_get_orders(array(
            'meta_key' => '_zoyktech_order_id',
            'meta_value' => $zoyktech_order_id,
            'limit' => 1
        ));

        return !empty($orders) ? $orders[0] : false;
    }

    /**
     * Determine payment status from callback
     */
    private function determine_payment_status($callback_data) {
        // Check for explicit status
        if (isset($callback_data['status'])) {
            $status = intval($callback_data['status']);
            
            switch ($status) {
                case 1:
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
            $code = intval($callback_data['result']['code']);
            return $code === 0 ? 'completed' : 'failed';
        }

        // Check for success flag
        if (isset($callback_data['success'])) {
            return $callback_data['success'] ? 'completed' : 'failed';
        }

        return 'processing';
    }

    /**
     * Extract transaction ID from callback
     */
    private function extract_transaction_id($callback_data) {
        if (isset($callback_data['transaction_id'])) {
            return sanitize_text_field($callback_data['transaction_id']);
        }
        
        if (isset($callback_data['txn_id'])) {
            return sanitize_text_field($callback_data['txn_id']);
        }
        
        if (isset($callback_data['reference'])) {
            return sanitize_text_field($callback_data['reference']);
        }

        return '';
    }

    /**
     * Get course ID from product ID
     */
    private function get_course_by_product($product_id) {
        // Check product meta first
        $course_id = get_post_meta($product_id, '_tutor_course_id', true);
        
        if ($course_id) {
            return $course_id;
        }

        // Check reverse relationship
        $courses = get_posts(array(
            'post_type' => 'courses',
            'meta_key' => '_tutor_wc_product_id',
            'meta_value' => $product_id,
            'posts_per_page' => 1
        ));

        return !empty($courses) ? $courses[0]->ID : false;
    }

    /**
     * Send enrollment notification
     */
    private function send_enrollment_notification($course_id, $user_id, $order_id) {
        $user = get_userdata($user_id);
        $course = get_post($course_id);
        $order = wc_get_order($order_id);
        
        if (!$user || !$course || !$order) {
            return;
        }

        $subject = sprintf(__('[%s] Course Access Granted', 'tutor-zoyktech'), get_bloginfo('name'));

        $message = sprintf(
            __('Great news %s!

Your mobile money payment has been confirmed and you now have full access to "%s".

🎓 Course: %s
💳 Order: #%s
✅ Status: Enrolled and Active

Start learning now: %s

Need help? Contact our support team.

Happy learning!
%s Team', 'tutor-zoyktech'),
            $user->display_name,
            $course->post_title,
            $course->post_title,
            $order->get_order_number(),
            get_permalink($course_id),
            get_bloginfo('name')
        );

        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Update payment log
     */
    private function update_payment_log($order, $status, $callback_data) {
        global $wpdb;

        $zoyktech_order_id = $order->get_meta('_zoyktech_order_id');
        
        if (empty($zoyktech_order_id)) {
            return;
        }

        $table_name = $wpdb->prefix . 'zoyktech_payment_logs';

        $update_data = array(
            'status' => $status,
            'updated_at' => current_time('mysql')
        );

        $transaction_id = $this->extract_transaction_id($callback_data);
        if ($transaction_id) {
            $update_data['transaction_id'] = $transaction_id;
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
     * Handle failed payment
     */
    private function fail_payment($order, $callback_data, $status) {
        $order->update_status('failed', sprintf(
            __('Mobile money payment %s via Zoyktech', 'tutor-zoyktech'),
            $status
        ));
        
        $this->update_payment_log($order, $status, $callback_data);
        
        error_log("ZOYKTECH_CALLBACK: Payment {$status} for order {$order->get_id()}");
    }

    /**
     * Update payment status for processing payments
     */
    private function update_payment_status($order, $status, $callback_data) {
        $order->add_order_note(sprintf(
            __('Payment status updated to: %s', 'tutor-zoyktech'),
            $status
        ));
        
        $this->update_payment_log($order, $status, $callback_data);
        
        error_log("ZOYKTECH_CALLBACK: Payment status {$status} for order {$order->get_id()}");
    }
}

// Initialize the payment completion handler
new Tutor_Zoyktech_Payment_Completion_Handler();