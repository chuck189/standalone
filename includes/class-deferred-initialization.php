<?php
/**
 * Deferred Initialization Handler
 * Ensures no classes are instantiated during plugin activation
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Deferred Initialization Class
 */
class Tutor_Zoyktech_Deferred_Init {

    /**
     * Initialize all plugin components after activation
     */
    public static function initialize() {
        // Only initialize if not activating
        if (defined('TUTOR_ZOYKTECH_ACTIVATING')) {
            return;
        }

        // Initialize components in order
        add_action('init', array(__CLASS__, 'init_components'), 15);
    }

    /**
     * Initialize components
     */
    public static function init_components() {
        // Check if required plugins are active
        if (!class_exists('WooCommerce') || !function_exists('tutor')) {
            return;
        }

        // Initialize core components
        if (class_exists('Tutor_Zoyktech_Payment_Completion_Handler')) {
            new Tutor_Zoyktech_Payment_Completion_Handler();
        }

        if (class_exists('Tutor_Zoyktech_Course_Access_Manager')) {
            new Tutor_Zoyktech_Course_Access_Manager();
        }

        if (class_exists('Tutor_Zoyktech_WooCommerce_Integration')) {
            new Tutor_Zoyktech_WooCommerce_Integration();
        }

        // Initialize admin components
        if (is_admin()) {
            if (class_exists('Tutor_Zoyktech_Admin_Settings')) {
                new Tutor_Zoyktech_Admin_Settings();
            }

            if (class_exists('Tutor_Zoyktech_Setup_Wizard')) {
                new Tutor_Zoyktech_Setup_Wizard();
            }
        }
    }
}

// Initialize deferred components
Tutor_Zoyktech_Deferred_Init::initialize();