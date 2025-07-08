@@ .. @@
     * Constructor
      */
     public function __construct() {
         add_action('admin_menu', array($this, 'add_admin_menu'));
         add_action('admin_init', array($this, 'admin_init'));
     }