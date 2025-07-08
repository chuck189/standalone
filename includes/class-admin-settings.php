@@ .. @@
     * Constructor
      */
     public function __construct() {
+        // Prevent output during construction
+        if (!ob_get_level()) {
+            ob_start();
+        }
+        
         add_action('admin_menu', array($this, 'add_admin_menu'));
         add_action('admin_init', array($this, 'admin_init'));
+        
+        // Clean output buffer
+        if (ob_get_level()) {
+            ob_end_clean();
+        }
     }