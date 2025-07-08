<?php
/**
 * Simplified Hooks for WooCommerce Integration
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add course price field to course settings
 */
add_action('tutor_course_builder_form_field_after', 'tutor_zoyktech_add_price_field');
function tutor_zoyktech_add_price_field() {
    global $post;
    
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
                <?php _e('Set the price for this course. A WooCommerce product will be created automatically.', 'tutor-zoyktech'); ?>
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
 * Add price badge to course cards
 */
add_action('tutor_course/loop/thumbnail/after', 'tutor_zoyktech_add_price_badge');
function tutor_zoyktech_add_price_badge() {
    global $post;
    
    $course_price = get_post_meta($post->ID, '_tutor_course_price', true);
    
    if (!empty($course_price) && $course_price > 0) {
        $formatted_price = 'K' . number_format($course_price, 2);
        
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
 * Add course price badge styles
 */
add_action('wp_head', 'tutor_zoyktech_course_badge_styles');
function tutor_zoyktech_course_badge_styles() {
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
    
    .tutor-course-loop-wrap {
        position: relative;
    }
    </style>
    <?php
}

/**
 * Add enrollment notification after successful payment
 */
add_action('tutor_zoyktech_course_enrollment_completed', 'tutor_zoyktech_enrollment_notification', 10, 3);
function tutor_zoyktech_enrollment_notification($course_id, $user_id, $order_id) {
    $course = get_post($course_id);
    $user = get_userdata($user_id);
    $order = wc_get_order($order_id);
    
    if (!$course || !$user || !$order) {
        return;
    }
    
    // Send notification to admin
    $subject = sprintf(
        __('New Course Enrollment: %s', 'tutor-zoyktech'),
        $course->post_title
    );
    
    $message = sprintf(
        __('A new student has enrolled in a course via mobile money payment.

Course: %s
Student: %s (%s)
Order: #%s
Amount: %s
Date: %s

View order: %s', 'tutor-zoyktech'),
        $course->post_title,
        $user->display_name,
        $user->user_email,
        $order->get_order_number(),
        $order->get_formatted_order_total(),
        current_time('mysql'),
        $order->get_edit_order_url()
    );
    
    wp_mail(get_option('admin_email'), $subject, $message);
}