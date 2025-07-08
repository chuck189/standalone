@@ .. @@
 /**
  * Plugin Name: Tutor LMS Zoyktech WooCommerce Gateway
  * Plugin URI: https://github.com/your-repo/tutor-zoyktech-woocommerce
- * Description: Zoyktech Mobile Money payment gateway for WooCommerce that works seamlessly with Tutor LMS course purchases.
+ * Description: Complete WooCommerce payment gateway for Tutor LMS courses with Zoyktech mobile money integration. Supports Airtel Money and MTN Mobile Money.
  * Version: 1.0.0
@@ .. @@
     private function includes() {
         // Core gateway class
         require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-wc-zoyktech-gateway.php';
         
         // API handler
         require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-zoyktech-api.php';
         
         // Admin settings
         require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-admin-settings.php';
+        
+        // Tutor LMS + WooCommerce integration
+        require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-tutor-woocommerce-integration.php';
+        
+        // Setup wizard
+        require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-admin-setup-wizard.php';
        
        // Payment completion handler (most important!)
        require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-payment-completion-handler.php';
        
        // Course access manager
        require_once TUTOR_ZOYKTECH_PLUGIN_PATH . 'includes/class-course-access-manager.php';
     }