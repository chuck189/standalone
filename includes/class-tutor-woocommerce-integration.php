<?php
/**
 * Tutor LMS + WooCommerce Integration for Zoyktech
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Tutor WooCommerce Integration Class
 */
class Tutor_Zoyktech_WooCommerce_Integration {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize integration
     */
    public function init() {
        // Check if both Tutor LMS and WooCommerce are active
        if (!$this->is_tutor_active() || !$this->is_woocommerce_active()) {
            return;
        }

        // Hook into WooCommerce order completion
        add_action('woocommerce_order_status_completed', array($this, 'handle_course_enrollment'), 10, 1);
        add_action('woocommerce_payment_complete', array($this, 'handle_course_enrollment'), 10, 1);
        
        // Add course products integration
        add_action('add_meta_boxes', array($this, 'add_course_meta_box'));
        add_action('save_post', array($this, 'save_course_product_meta'));
        
        // Modify product display for courses
        add_filter('woocommerce_product_get_price', array($this, 'get_course_price'), 10, 2);
        add_filter('woocommerce_product_get_regular_price', array($this, 'get_course_price'), 10, 2);
        
        // Auto-create products for paid courses
        add_action('save_post', array($this, 'auto_create_course_product'), 20, 1);
        
        // Custom checkout fields for course purchases
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_course_checkout_data'));
        
        // Prevent multiple purchases of same course
        add_filter('woocommerce_add_to_cart_validation', array($this, 'prevent_duplicate_course_purchase'), 10, 3);
    }

    /**
     * Check if Tutor LMS is active
     */
    private function is_tutor_active() {
        return function_exists('tutor');
    }

    /**
     * Check if WooCommerce is active
     */
    private function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Handle course enrollment after successful payment
     */
    public function handle_course_enrollment($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }

        // Check if payment was made with Zoyktech
        $payment_method = $order->get_payment_method();
        if ($payment_method !== 'zoyktech') {
            return; // Only handle Zoyktech payments
        }

        $user_id = $order->get_user_id();
        if (!$user_id) {
            error_log('TUTOR_ZOYKTECH: No user ID found for order ' . $order_id);
            return;
        }

        // Process each item in the order
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $course_id = $this->get_course_by_product($product_id);
            
            if ($course_id) {
                $this->enroll_user_in_course($course_id, $user_id, $order_id);
            }
        }
    }

    /**
     * Get course ID from product ID
     */
    private function get_course_by_product($product_id) {
        // First check if product has course meta
        $course_id = get_post_meta($product_id, '_tutor_course_id', true);
        
        if ($course_id) {
            return $course_id;
        }

        // Check if this product was auto-created for a course
        $courses = get_posts(array(
            'post_type' => 'courses',
            'meta_key' => '_tutor_wc_product_id',
            'meta_value' => $product_id,
            'posts_per_page' => 1
        ));

        return !empty($courses) ? $courses[0]->ID : false;
    }

    /**
     * Enroll user in course
     */
    private function enroll_user_in_course($course_id, $user_id, $order_id) {
        // Check if already enrolled
        if (tutor_utils()->is_enrolled($course_id, $user_id)) {
            error_log("TUTOR_ZOYKTECH: User $user_id already enrolled in course $course_id");
            return;
        }

        // Enroll user
        $enrollment = tutor_utils()->do_enroll($course_id, $order_id, $user_id);
        
        if ($enrollment) {
            // Add meta to mark as paid enrollment
            add_post_meta($enrollment, '_tutor_zoyktech_paid_enrollment', 'yes');
            add_post_meta($enrollment, '_tutor_wc_order_id', $order_id);
            
            // Send enrollment email
            $this->send_enrollment_email($course_id, $user_id, $order_id);
            
            error_log("TUTOR_ZOYKTECH: User $user_id enrolled in course $course_id via order $order_id");
            
            do_action('tutor_zoyktech_course_enrollment_completed', $course_id, $user_id, $order_id);
        } else {
            error_log("TUTOR_ZOYKTECH: Failed to enroll user $user_id in course $course_id");
        }
    }

    /**
     * Send enrollment email
     */
    private function send_enrollment_email($course_id, $user_id, $order_id) {
        $user = get_userdata($user_id);
        $course = get_post($course_id);
        $order = wc_get_order($order_id);
        
        if (!$user || !$course || !$order) {
            return;
        }

        $subject = sprintf(
            __('[%s] Course Enrollment Confirmation', 'tutor-zoyktech'),
            get_bloginfo('name')
        );

        $message = sprintf(
            __('Dear %s,

Congratulations! You have successfully enrolled in the course "%s" via mobile money payment.

Course Details:
- Course: %s
- Order Number: %s
- Amount Paid: %s
- Payment Method: Mobile Money (Zoyktech)

You can access your course here: %s

Thank you for your purchase!

Best regards,
%s Team', 'tutor-zoyktech'),
            $user->display_name,
            $course->post_title,
            $course->post_title,
            $order->get_order_number(),
            $order->get_formatted_order_total(),
            get_permalink($course_id),
            get_bloginfo('name')
        );

        wp_mail($user->user_email, $subject, $message);
    }

    /**
     * Add course meta box to products
     */
    public function add_course_meta_box() {
        add_meta_box(
            'tutor_course_product',
            __('Course Settings', 'tutor-zoyktech'),
            array($this, 'course_meta_box_content'),
            'product',
            'normal',
            'high'
        );
    }

    /**
     * Course meta box content
     */
    public function course_meta_box_content($post) {
        wp_nonce_field('tutor_course_product_meta', 'tutor_course_product_nonce');
        
        $course_id = get_post_meta($post->ID, '_tutor_course_id', true);
        
        // Get all courses
        $courses = get_posts(array(
            'post_type' => 'courses',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        ?>
        <table class="form-table">
            <tr>
                <th scope="row">
                    <label for="tutor_course_id"><?php _e('Associated Course', 'tutor-zoyktech'); ?></label>
                </th>
                <td>
                    <select name="tutor_course_id" id="tutor_course_id" class="regular-text">
                        <option value=""><?php _e('Select a course', 'tutor-zoyktech'); ?></option>
                        <?php foreach ($courses as $course): ?>
                        <option value="<?php echo esc_attr($course->ID); ?>" <?php selected($course_id, $course->ID); ?>>
                            <?php echo esc_html($course->post_title); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        <?php _e('Select the course that customers will be enrolled in when they purchase this product.', 'tutor-zoyktech'); ?>
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save course product meta
     */
    public function save_course_product_meta($post_id) {
        if (!isset($_POST['tutor_course_product_nonce']) || 
            !wp_verify_nonce($_POST['tutor_course_product_nonce'], 'tutor_course_product_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        if (get_post_type($post_id) !== 'product') {
            return;
        }

        if (isset($_POST['tutor_course_id'])) {
            $course_id = sanitize_text_field($_POST['tutor_course_id']);
            
            if (!empty($course_id)) {
                update_post_meta($post_id, '_tutor_course_id', $course_id);
                // Also save the reverse relationship
                update_post_meta($course_id, '_tutor_wc_product_id', $post_id);
            } else {
                delete_post_meta($post_id, '_tutor_course_id');
            }
        }
    }

    /**
     * Get course price for product
     */
    public function get_course_price($price, $product) {
        $course_id = get_post_meta($product->get_id(), '_tutor_course_id', true);
        
        if ($course_id) {
            $course_price = get_post_meta($course_id, '_tutor_course_price', true);
            if (!empty($course_price)) {
                return $course_price;
            }
        }
        
        return $price;
    }

    /**
     * Auto-create product for paid courses
     */
    public function auto_create_course_product($post_id) {
        if (get_post_type($post_id) !== 'courses') {
            return;
        }

        // Only create product if course has a price and doesn't have a product yet
        $course_price = get_post_meta($post_id, '_tutor_course_price', true);
        $existing_product = get_post_meta($post_id, '_tutor_wc_product_id', true);
        
        if (empty($course_price) || $course_price <= 0 || !empty($existing_product)) {
            return;
        }

        // Check if auto-creation is enabled
        $options = get_option('tutor_zoyktech_options', array());
        if (empty($options['auto_create_products'])) {
            return;
        }

        $course = get_post($post_id);
        
        // Create WooCommerce product
        $product_data = array(
            'post_title' => $course->post_title,
            'post_content' => $course->post_excerpt ?: $course->post_content,
            'post_status' => 'publish',
            'post_type' => 'product'
        );

        $product_id = wp_insert_post($product_data);
        
        if ($product_id && !is_wp_error($product_id)) {
            // Set product as simple product
            wp_set_object_terms($product_id, 'simple', 'product_type');
            
            // Set product meta
            update_post_meta($product_id, '_price', $course_price);
            update_post_meta($product_id, '_regular_price', $course_price);
            update_post_meta($product_id, '_virtual', 'yes');
            update_post_meta($product_id, '_downloadable', 'no');
            update_post_meta($product_id, '_manage_stock', 'no');
            update_post_meta($product_id, '_stock_status', 'instock');
            update_post_meta($product_id, '_visibility', 'visible');
            
            // Link course and product
            update_post_meta($product_id, '_tutor_course_id', $post_id);
            update_post_meta($post_id, '_tutor_wc_product_id', $product_id);
            
            error_log("TUTOR_ZOYKTECH: Auto-created product $product_id for course $post_id");
        }
    }

    /**
     * Save additional checkout data for course purchases
     */
    public function save_course_checkout_data($order_id) {
        $order = wc_get_order($order_id);
        
        if (!$order) {
            return;
        }

        // Check if any items are course products
        $has_courses = false;
        foreach ($order->get_items() as $item) {
            $product_id = $item->get_product_id();
            $course_id = $this->get_course_by_product($product_id);
            
            if ($course_id) {
                $has_courses = true;
                break;
            }
        }

        if ($has_courses) {
            $order->update_meta_data('_contains_courses', 'yes');
            $order->save();
        }
    }

    /**
     * Prevent duplicate course purchases
     */
    public function prevent_duplicate_course_purchase($valid, $product_id, $quantity) {
        if (!is_user_logged_in()) {
            return $valid;
        }

        $course_id = $this->get_course_by_product($product_id);
        
        if ($course_id) {
            $user_id = get_current_user_id();
            
            if (tutor_utils()->is_enrolled($course_id, $user_id)) {
                wc_add_notice(
                    __('You are already enrolled in this course.', 'tutor-zoyktech'),
                    'error'
                );
                return false;
            }
        }

        return $valid;
    }

    /**
     * Get enrolled courses for user via WooCommerce
     */
    public function get_user_course_orders($user_id) {
        $orders = wc_get_orders(array(
            'customer' => $user_id,
            'status' => array('completed', 'processing'),
            'meta_key' => '_contains_courses',
            'meta_value' => 'yes',
            'limit' => -1
        ));

        $course_orders = array();
        
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $course_id = $this->get_course_by_product($product_id);
                
                if ($course_id) {
                    $course_orders[] = array(
                        'course_id' => $course_id,
                        'order_id' => $order->get_id(),
                        'order_date' => $order->get_date_created(),
                        'order_total' => $order->get_total(),
                        'payment_method' => $order->get_payment_method()
                    );
                }
            }
        }

        return $course_orders;
    }
}

// Initialize the integration
new Tutor_Zoyktech_WooCommerce_Integration();