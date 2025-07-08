<?php
/**
 * Hooks and Filters for Tutor LMS Zoyktech Integration
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add payment callback handler
require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-payment-callback.php';

// Initialize missing classes
if (!class_exists('Tutor_Zoyktech_Payment_History')) {
    require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-payment-history.php';
}

if (!class_exists('Tutor_Zoyktech_Admin_Settings')) {
    require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-admin-settings.php';
}

if (!class_exists('Tutor_Zoyktech_Frontend_Payment')) {
    require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-frontend-payment.php';
}

// Initialize components
new Tutor_Zoyktech_Student_Dashboard();
new Tutor_Zoyktech_Payment_History();
new Tutor_Zoyktech_Admin_Settings();
new Tutor_Zoyktech_Frontend_Payment();

/**
 * Force refresh Tutor LMS settings to detect new gateway
 */
add_action('admin_init', 'tutor_zoyktech_refresh_gateway_settings');
function tutor_zoyktech_refresh_gateway_settings() {
    // Clear any cached gateway settings
    if (function_exists('tutor')) {
        delete_transient('tutor_monetization_gateways');
        delete_transient('tutor_payment_gateways');
    }
}

/**
 * Ensure gateway is registered early
 */
add_action('plugins_loaded', 'tutor_zoyktech_register_gateway_early', 5);
function tutor_zoyktech_register_gateway_early() {
    if (class_exists('Tutor_Zoyktech_Monetization_Integration')) {
        new Tutor_Zoyktech_Monetization_Integration();
    }
}

/**
 * Add course price field to course settings
 */
add_action('tutor_course_builder_form_field_after', 'tutor_zoyktech_add_price_field');
function tutor_zoyktech_add_price_field() {
    global $post;
    
    $options = get_option('tutor_zoyktech_options', array());
    if (empty($options['enable_zoyktech'])) {
        return;
    }
    
    $course_price = get_post_meta($post->ID, '_tutor_course_price', true);
    ?>
    <div class="tutor-option-field-row">
        <div class="tutor-option-field-label">
            <label for="tutor_course_price">
                <?php _e('Course Price (ZMW)', 'tutor-zoyktech'); ?>
            </label>
        </div>
        <div class="tutor-option-field">
            <input type="number" 
                   id="tutor_course_price" 
                   name="tutor_course_price" 
                   value="<?php echo esc_attr($course_price); ?>" 
                   step="0.01" 
                   min="0"
                   placeholder="0.00">
            <p class="desc">
                <?php _e('Set the price for this course. Leave empty or 0 for free courses.', 'tutor-zoyktech'); ?>
            </p>
        </div>
    </div>
    <?php
}

/**
 * Save course price
 */
add_action('save_post', 'tutor_zoyktech_save_course_price');
function tutor_zoyktech_save_course_price($post_id) {
    if (get_post_type($post_id) !== 'courses') {
        return;
    }
    
    if (isset($_POST['tutor_course_price'])) {
        $price = sanitize_text_field($_POST['tutor_course_price']);
        update_post_meta($post_id, '_tutor_course_price', $price);
    }
}

/**
 * Add payment status to course enrollment check
 */
add_filter('tutor_is_enrolled', 'tutor_zoyktech_check_paid_enrollment', 10, 3);
function tutor_zoyktech_check_paid_enrollment($is_enrolled, $course_id, $user_id) {
    if (!$is_enrolled) {
        return false;
    }
    
    // Check if course requires payment
    $course_price = get_post_meta($course_id, '_tutor_course_price', true);
    if (empty($course_price) || $course_price <= 0) {
        return $is_enrolled; // Free course
    }
    
    return $is_enrolled;
}

/**
 * Modify course enrollment button
 */
add_action('tutor_course/single/enrolled/before', 'tutor_zoyktech_modify_enrollment_button', 5);
function tutor_zoyktech_modify_enrollment_button() {
    global $post;
    
    $options = get_option('tutor_zoyktech_options', array());
    if (empty($options['enable_zoyktech'])) {
        return;
    }
    
    $course_id = $post->ID;
    $user_id = get_current_user_id();
    
    // Check if user is already enrolled
    if (tutor_utils()->is_enrolled($course_id, $user_id)) {
        return;
    }
    
    // Check if course has a price
    $course_price = get_post_meta($course_id, '_tutor_course_price', true);
    if (empty($course_price) || $course_price <= 0) {
        return; // Free course, show default enrollment
    }
    
    // Remove default enrollment button
    remove_action('tutor_course/single/enrolled/before', 'tutor_course_enroll_form');
}

/**
 * Add payment form after course content
 */
add_action('tutor_course/single/content/after', 'tutor_zoyktech_add_payment_form');
function tutor_zoyktech_add_payment_form() {
    global $post;
    
    $options = get_option('tutor_zoyktech_options', array());
    if (empty($options['enable_zoyktech'])) {
        return;
    }
    
    if (!is_user_logged_in()) {
        return;
    }
    
    $course_id = $post->ID;
    $user_id = get_current_user_id();
    
    // Check if user is already enrolled
    if (tutor_utils()->is_enrolled($course_id, $user_id)) {
        return;
    }
    
    // Check if course has a price
    $course_price = get_post_meta($course_id, '_tutor_course_price', true);
    if (empty($course_price) || $course_price <= 0) {
        return;
    }
    
    // Display payment form
    $payment_handler = new Tutor_Zoyktech_Course_Payment();
    $formatted_price = $payment_handler->format_price($course_price);
    
    include TUTOR_ZOYKTECH_PLUGIN_PATH . 'templates/payment-form.php';
}

/**
 * Add payment status to course cards
 */
add_action('tutor_course/loop/thumbnail/after', 'tutor_zoyktech_add_price_badge');
function tutor_zoyktech_add_price_badge() {
    global $post;
    
    $options = get_option('tutor_zoyktech_options', array());
    if (empty($options['enable_zoyktech'])) {
        return;
    }
    
    $course_price = get_post_meta($post->ID, '_tutor_course_price', true);
    
    if (!empty($course_price) && $course_price > 0) {
        $payment_handler = new Tutor_Zoyktech_Course_Payment();
        $formatted_price = $payment_handler->format_price($course_price);
        
        echo '<div class="tutor-course-price-badge">';
        echo '<span class="price-amount">' . esc_html($formatted_price) . '</span>';
        echo '</div>';
    } else {
        echo '<div class="tutor-course-price-badge free">';
        echo '<span class="price-amount">' . __('Free', 'tutor-zoyktech') . '</span>';
        echo '</div>';
    }
}

/**
 * Add enrollment notification after successful payment
 */
add_action('tutor_zoyktech_after_enrollment', 'tutor_zoyktech_enrollment_notification', 10, 2);
function tutor_zoyktech_enrollment_notification($course_id, $user_id) {
    // Send notification to admin
    $course = get_post($course_id);
    $user = get_userdata($user_id);
    
    $subject = sprintf(
        __('New Course Enrollment: %s', 'tutor-zoyktech'),
        $course->post_title
    );
    
    $message = sprintf(
        __('A new student has enrolled in a course via mobile money payment.

Course: %s
Student: %s (%s)
Date: %s

View course: %s', 'tutor-zoyktech'),
        $course->post_title,
        $user->display_name,
        $user->user_email,
        current_time('mysql'),
        get_permalink($course_id)
    );
    
    wp_mail(get_option('admin_email'), $subject, $message);
}

/**
 * Add custom CSS for course price badges
 */
add_action('wp_head', 'tutor_zoyktech_course_badge_styles');
function tutor_zoyktech_course_badge_styles() {
    $options = get_option('tutor_zoyktech_options', array());
    if (empty($options['enable_zoyktech'])) {
        return;
    }
    ?>
    <style>
    .tutor-course-price-badge {
        position: absolute;
        top: 12px;
        right: 12px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
        z-index: 10;
    }
    
    .tutor-course-price-badge.free {
        background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);
    }
    
    .tutor-course-price-badge .price-amount {
        display: block;
    }
    
    .tutor-course-loop-wrap {
        position: relative;
    }
    </style>
    <?php
}

/**
 * Add payment status to student dashboard
 */
add_filter('tutor_dashboard_nav_ui_items', 'tutor_zoyktech_add_dashboard_nav');
function tutor_zoyktech_add_dashboard_nav($nav_items) {
    $options = get_option('tutor_zoyktech_options', array());
    if (empty($options['enable_zoyktech'])) {
        return $nav_items;
    }
    
    $nav_items['payment-history'] = array(
        'title' => __('Payment History', 'tutor-zoyktech'),
        'icon' => 'tutor-icon-purchase',
        'auth_cap' => tutor()->student_role,
    );
    
    return $nav_items;
}

/**
 * Handle payment status check AJAX
 */
add_action('wp_ajax_tutor_zoyktech_check_status', 'tutor_zoyktech_check_payment_status');
function tutor_zoyktech_check_payment_status() {
    check_ajax_referer('tutor_zoyktech_nonce', 'nonce');
    
    $order_id = sanitize_text_field($_POST['order_id']);
    $user_id = get_current_user_id();
    
    if (!$user_id) {
        wp_send_json_error(array('message' => 'Not authenticated'));
    }
    
    $api = new Tutor_Zoyktech_API();
    $transaction = $api->get_transaction($order_id);
    
    if (!$transaction || $transaction->user_id != $user_id) {
        wp_send_json_error(array('message' => 'Transaction not found'));
    }
    
    wp_send_json_success(array(
        'status' => $transaction->status,
        'transaction_id' => $transaction->transaction_id
    ));
}

/**
 * Add course access restriction
 */
add_action('template_redirect', 'tutor_zoyktech_restrict_course_access');
function tutor_zoyktech_restrict_course_access() {
    if (!is_singular('courses')) {
        return;
    }
    
    $options = get_option('tutor_zoyktech_options', array());
    if (empty($options['enable_zoyktech'])) {
        return;
    }
    
    global $post;
    $course_id = $post->ID;
    $user_id = get_current_user_id();
    
    // Allow access if user is not logged in (they'll see the payment form)
    if (!$user_id) {
        return;
    }
    
    // Check if course requires payment
    $course_price = get_post_meta($course_id, '_tutor_course_price', true);
    if (empty($course_price) || $course_price <= 0) {
        return; // Free course
    }
    
    // Check if user has paid access
    $enrollment_manager = new Tutor_Zoyktech_Enrollment_Manager();
    if (!$enrollment_manager->has_paid_access($course_id, $user_id)) {
        // User hasn't paid, but don't redirect - let them see the payment form
        return;
    }
}

/**
 * Add admin menu for payment management
 */
add_action('admin_menu', 'tutor_zoyktech_add_admin_menu');
function tutor_zoyktech_add_admin_menu() {
    $options = get_option('tutor_zoyktech_options', array());
    if (empty($options['enable_zoyktech'])) {
        return;
    }
    
    add_submenu_page(
        'tutor',
        __('Zoyktech Payments', 'tutor-zoyktech'),
        __('Payments', 'tutor-zoyktech'),
        'manage_tutor',
        'tutor-zoyktech-payments',
        'tutor_zoyktech_admin_payments_page'
    );
}

/**
 * Admin payments page
 */
function tutor_zoyktech_admin_payments_page() {
    echo '<div class="wrap">';
    echo '<h1>' . __('Zoyktech Payments', 'tutor-zoyktech') . '</h1>';
    echo '<p>' . __('Payment management functionality coming soon.', 'tutor-zoyktech') . '</p>';
    echo '</div>';
}

