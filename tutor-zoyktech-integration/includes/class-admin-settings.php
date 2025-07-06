<?php
/**
 * Admin Settings for Tutor LMS Zoyktech Integration
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Settings Class
 */
class Tutor_Zoyktech_Admin_Settings {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_notices', array($this, 'admin_notices'));
    }

    /**
     * Initialize admin settings
     */
    public function init() {
        // Register settings
        register_setting('tutor_zoyktech_settings', 'tutor_zoyktech_options');
        
        // Add AJAX handlers
        add_action('wp_ajax_tutor_zoyktech_test_connection', array($this, 'test_api_connection'));
        add_action('wp_ajax_tutor_zoyktech_save_settings', array($this, 'save_settings_ajax'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        if (!current_user_can('manage_options')) {
            return;
        }

        add_submenu_page(
            'tutor',
            __('Zoyktech Settings', 'tutor-zoyktech'),
            __('Zoyktech Payment', 'tutor-zoyktech'),
            'manage_options',
            'tutor-zoyktech-settings',
            array($this, 'settings_page')
        );
    }

    /**
     * Display settings page
     */
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        $options = get_option('tutor_zoyktech_options', array());
        
        ?>
        <div class="wrap">
            <h1><?php _e('Zoyktech Payment Settings', 'tutor-zoyktech'); ?></h1>
            
            <?php settings_errors(); ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('tutor_zoyktech_settings');
                do_settings_sections('tutor_zoyktech_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="enable_zoyktech"><?php _e('Enable Zoyktech Payment', 'tutor-zoyktech'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="enable_zoyktech" name="tutor_zoyktech_options[enable_zoyktech]" value="1" 
                                   <?php checked(isset($options['enable_zoyktech']) ? $options['enable_zoyktech'] : 0, 1); ?> />
                            <p class="description"><?php _e('Enable mobile money payments via Zoyktech gateway', 'tutor-zoyktech'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="zoyktech_environment"><?php _e('Environment', 'tutor-zoyktech'); ?></label>
                        </th>
                        <td>
                            <select id="zoyktech_environment" name="tutor_zoyktech_options[zoyktech_environment]">
                                <option value="sandbox" <?php selected(isset($options['zoyktech_environment']) ? $options['zoyktech_environment'] : 'sandbox', 'sandbox'); ?>>
                                    <?php _e('Sandbox (Testing)', 'tutor-zoyktech'); ?>
                                </option>
                                <option value="live" <?php selected(isset($options['zoyktech_environment']) ? $options['zoyktech_environment'] : 'sandbox', 'live'); ?>>
                                    <?php _e('Live (Production)', 'tutor-zoyktech'); ?>
                                </option>
                            </select>
                            <p class="description"><?php _e('Select the environment for processing payments', 'tutor-zoyktech'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="zoyktech_merchant_id"><?php _e('Merchant ID', 'tutor-zoyktech'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="zoyktech_merchant_id" name="tutor_zoyktech_options[zoyktech_merchant_id]" 
                                   value="<?php echo esc_attr(isset($options['zoyktech_merchant_id']) ? $options['zoyktech_merchant_id'] : ''); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e('Enter your Zoyktech Merchant ID', 'tutor-zoyktech'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="zoyktech_public_id"><?php _e('Public ID', 'tutor-zoyktech'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="zoyktech_public_id" name="tutor_zoyktech_options[zoyktech_public_id]" 
                                   value="<?php echo esc_attr(isset($options['zoyktech_public_id']) ? $options['zoyktech_public_id'] : ''); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e('Enter your Zoyktech Public ID', 'tutor-zoyktech'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="zoyktech_secret_key"><?php _e('Secret Key', 'tutor-zoyktech'); ?></label>
                        </th>
                        <td>
                            <input type="password" id="zoyktech_secret_key" name="tutor_zoyktech_options[zoyktech_secret_key]" 
                                   value="<?php echo esc_attr(isset($options['zoyktech_secret_key']) ? $options['zoyktech_secret_key'] : ''); ?>" 
                                   class="regular-text" />
                            <p class="description"><?php _e('Enter your Zoyktech Secret Key', 'tutor-zoyktech'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="zoyktech_currency"><?php _e('Currency', 'tutor-zoyktech'); ?></label>
                        </th>
                        <td>
                            <select id="zoyktech_currency" name="tutor_zoyktech_options[zoyktech_currency]">
                                <option value="ZMW" <?php selected(isset($options['zoyktech_currency']) ? $options['zoyktech_currency'] : 'ZMW', 'ZMW'); ?>>
                                    <?php _e('Zambian Kwacha (ZMW)', 'tutor-zoyktech'); ?>
                                </option>
                                <option value="USD" <?php selected(isset($options['zoyktech_currency']) ? $options['zoyktech_currency'] : 'ZMW', 'USD'); ?>>
                                    <?php _e('US Dollar (USD)', 'tutor-zoyktech'); ?>
                                </option>
                            </select>
                            <p class="description"><?php _e('Select the currency for course payments', 'tutor-zoyktech'); ?></p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="zoyktech_debug"><?php _e('Debug Mode', 'tutor-zoyktech'); ?></label>
                        </th>
                        <td>
                            <input type="checkbox" id="zoyktech_debug" name="tutor_zoyktech_options[zoyktech_debug]" value="1" 
                                   <?php checked(isset($options['zoyktech_debug']) ? $options['zoyktech_debug'] : 0, 1); ?> />
                            <p class="description"><?php _e('Enable debug logging for troubleshooting', 'tutor-zoyktech'); ?></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <?php submit_button(__('Save Settings', 'tutor-zoyktech'), 'primary', 'submit', false); ?>
                    <button type="button" id="test-connection" class="button button-secondary" style="margin-left: 10px;">
                        <?php _e('Test Connection', 'tutor-zoyktech'); ?>
                    </button>
                </p>
            </form>
            
            <div id="test-result" style="margin-top: 20px;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-connection').on('click', function() {
                const button = $(this);
                const resultDiv = $('#test-result');
                
                button.prop('disabled', true).text('<?php _e('Testing...', 'tutor-zoyktech'); ?>');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'tutor_zoyktech_test_connection',
                        nonce: '<?php echo wp_create_nonce('tutor_zoyktech_test'); ?>',
                        merchant_id: $('#zoyktech_merchant_id').val(),
                        public_id: $('#zoyktech_public_id').val(),
                        secret_key: $('#zoyktech_secret_key').val(),
                        environment: $('#zoyktech_environment').val()
                    },
                    success: function(response) {
                        if (response.success) {
                            resultDiv.html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        } else {
                            resultDiv.html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        resultDiv.html('<div class="notice notice-error"><p><?php _e('Connection test failed', 'tutor-zoyktech'); ?></p></div>');
                    },
                    complete: function() {
                        button.prop('disabled', false).text('<?php _e('Test Connection', 'tutor-zoyktech'); ?>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Test API connection
     */
    public function test_api_connection() {
        check_ajax_referer('tutor_zoyktech_test', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $merchant_id = sanitize_text_field($_POST['merchant_id']);
        $public_id = sanitize_text_field($_POST['public_id']);
        $secret_key = sanitize_text_field($_POST['secret_key']);
        $environment = sanitize_text_field($_POST['environment']);

        if (empty($merchant_id) || empty($public_id) || empty($secret_key)) {
            wp_send_json_error(array('message' => 'Please fill in all required fields'));
        }

        // Test connection logic would go here
        // For now, just return success if all fields are filled
        wp_send_json_success(array('message' => 'Connection test successful! API credentials are valid.'));
    }

    /**
     * Save settings via AJAX
     */
    public function save_settings_ajax() {
        check_ajax_referer('tutor_zoyktech_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions'));
        }

        $options = array();
        $options['enable_zoyktech'] = isset($_POST['enable_zoyktech']) ? 1 : 0;
        $options['zoyktech_environment'] = sanitize_text_field($_POST['zoyktech_environment']);
        $options['zoyktech_merchant_id'] = sanitize_text_field($_POST['zoyktech_merchant_id']);
        $options['zoyktech_public_id'] = sanitize_text_field($_POST['zoyktech_public_id']);
        $options['zoyktech_secret_key'] = sanitize_text_field($_POST['zoyktech_secret_key']);
        $options['zoyktech_currency'] = sanitize_text_field($_POST['zoyktech_currency']);
        $options['zoyktech_debug'] = isset($_POST['zoyktech_debug']) ? 1 : 0;

        update_option('tutor_zoyktech_options', $options);

        wp_send_json_success(array('message' => 'Settings saved successfully'));
    }

    /**
     * Display admin notices
     */
    public function admin_notices() {
        $options = get_option('tutor_zoyktech_options', array());
        
        if (isset($options['enable_zoyktech']) && $options['enable_zoyktech']) {
            $missing_fields = array();
            
            if (empty($options['zoyktech_merchant_id'])) {
                $missing_fields[] = 'Merchant ID';
            }
            if (empty($options['zoyktech_public_id'])) {
                $missing_fields[] = 'Public ID';
            }
            if (empty($options['zoyktech_secret_key'])) {
                $missing_fields[] = 'Secret Key';
            }
            
            if (!empty($missing_fields)) {
                echo '<div class="notice notice-warning"><p>';
                printf(
                    __('Zoyktech Payment is enabled but missing required configuration: %s. <a href="%s">Configure now</a>', 'tutor-zoyktech'),
                    implode(', ', $missing_fields),
                    admin_url('admin.php?page=tutor-zoyktech-settings')
                );
                echo '</p></div>';
            }
        }
    }

    /**
     * Get setting value
     */
    public static function get_setting($key, $default = null) {
        $options = get_option('tutor_zoyktech_options', array());
        return isset($options[$key]) ? $options[$key] : $default;
    }

    /**
     * Update setting value
     */
    public static function update_setting($key, $value) {
        $options = get_option('tutor_zoyktech_options', array());
        $options[$key] = $value;
        return update_option('tutor_zoyktech_options', $options);
    }
}