<?php
/**
 * Checkout Detector - Identifies course-only checkouts
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Checkout Detector Class
 */
class Tutor_Zoyktech_Checkout_Detector {

    /**
     * Constructor
     */
    public function __construct() {
        // Only initialize if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return;
        }

        // Add body class for course checkouts
        add_filter('body_class', array($this, 'add_checkout_body_class'));
        
        // Add checkout form class
        add_action('woocommerce_checkout_before_customer_details', array($this, 'add_checkout_form_class'));
    }

    /**
     * Add body class for course checkouts
     */
    public function add_checkout_body_class($classes) {
        if (is_checkout() && $this->is_course_checkout()) {
            $classes[] = 'course-checkout';
            $classes[] = 'mobile-money-checkout';
        }
        
        return $classes;
    }

    /**
     * Add checkout form class
     */
    public function add_checkout_form_class() {
        if ($this->is_course_checkout()) {
            echo '<script>jQuery(document).ready(function($) { $(".woocommerce-checkout").addClass("checkout-course-products"); });</script>';
        }
    }

    /**
     * Check if this is a course-only checkout
     */
    private function is_course_checkout() {
        if (!WC()->cart || WC()->cart->is_empty()) {
            return false;
        }

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            $course_id = get_post_meta($product_id, '_tutor_course_id', true);
            
            if (!$course_id) {
                return false;
            }
        }

        return true;
    }
}

// Initialize checkout detector
new Tutor_Zoyktech_Checkout_Detector();