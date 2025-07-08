<?php
/**
 * Admin Setup Wizard for Tutor + WooCommerce Integration
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Setup Wizard Class
 */
class Tutor_Zoyktech_Setup_Wizard {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_setup_page'));
        add_action('admin_init', array($this, 'handle_setup_actions'));
        add_action('admin_notices', array($this, 'show_setup_notice'));
    }

    /**
     * Add setup page
     */
    public function add_setup_page() {
        add_submenu_page(
            null, // Parent slug (null means hidden)
            __('Zoyktech Setup Wizard', 'tutor-zoyktech'),
            __('Setup Wizard', 'tutor-zoyktech'),
            'manage_options',
            'tutor-zoyktech-setup',
            array($this, 'setup_page')
        );
    }

    /**
     * Show setup notice
     */
    public function show_setup_notice() {
        $setup_completed = get_option('tutor_zoyktech_setup_completed');
        
        if ($setup_completed) {
            return;
        }

        $screen = get_current_screen();
        if ($screen && $screen->id === 'admin_page_tutor-zoyktech-setup') {
            return;
        }

        ?>
        <div class="notice notice-info is-dismissible">
            <p>
                <strong><?php _e('Tutor LMS Zoyktech Gateway', 'tutor-zoyktech'); ?></strong>
            </p>
            <p>
                <?php _e('Complete the setup to start accepting mobile money payments for your courses.', 'tutor-zoyktech'); ?>
            </p>
            <p>
                <a href="<?php echo admin_url('admin.php?page=tutor-zoyktech-setup'); ?>" class="button button-primary">
                    <?php _e('Run Setup Wizard', 'tutor-zoyktech'); ?>
                </a>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?tutor_zoyktech_dismiss_setup=1'), 'dismiss_setup'); ?>" class="button">
                    <?php _e('Skip Setup', 'tutor-zoyktech'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Handle setup actions
     */
    public function handle_setup_actions() {
        // Handle setup dismissal
        if (isset($_GET['tutor_zoyktech_dismiss_setup']) && wp_verify_nonce($_GET['_wpnonce'], 'dismiss_setup')) {
            update_option('tutor_zoyktech_setup_completed', true);
            wp_redirect(admin_url());
            exit;
        }

        // Handle setup form submission
        if (isset($_POST['tutor_zoyktech_setup']) && wp_verify_nonce($_POST['_wpnonce'], 'tutor_zoyktech_setup')) {
            $this->process_setup();
        }
    }

    /**
     * Setup page
     */
    public function setup_page() {
        $step = isset($_GET['step']) ? intval($_GET['step']) : 1;
        
        ?>
        <div class="wrap">
            <h1><?php _e('Tutor LMS Zoyktech Gateway Setup', 'tutor-zoyktech'); ?></h1>
            
            <div class="setup-wizard">
                <div class="setup-steps">
                    <div class="step <?php echo $step >= 1 ? 'active' : ''; ?>">
                        <span class="step-number">1</span>
                        <span class="step-title"><?php _e('Prerequisites', 'tutor-zoyktech'); ?></span>
                    </div>
                    <div class="step <?php echo $step >= 2 ? 'active' : ''; ?>">
                        <span class="step-number">2</span>
                        <span class="step-title"><?php _e('API Configuration', 'tutor-zoyktech'); ?></span>
                    </div>
                    <div class="step <?php echo $step >= 3 ? 'active' : ''; ?>">
                        <span class="step-number">3</span>
                        <span class="step-title"><?php _e('Course Setup', 'tutor-zoyktech'); ?></span>
                    </div>
                    <div class="step <?php echo $step >= 4 ? 'active' : ''; ?>">
                        <span class="step-number">4</span>
                        <span class="step-title"><?php _e('Complete', 'tutor-zoyktech'); ?></span>
                    </div>
                </div>

                <div class="setup-content">
                    <?php
                    switch ($step) {
                        case 1:
                            $this->step_prerequisites();
                            break;
                        case 2:
                            $this->step_api_config();
                            break;
                        case 3:
                            $this->step_course_setup();
                            break;
                        case 4:
                            $this->step_complete();
                            break;
                    }
                    ?>
                </div>
            </div>
        </div>

        <style>
        .setup-wizard {
            max-width: 800px;
            margin: 20px 0;
        }

        .setup-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            padding: 0 20px;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            opacity: 0.5;
        }

        .step.active {
            opacity: 1;
        }

        .step:not(:last-child)::after {
            content: '';
            position: absolute;
            top: 15px;
            left: 100%;
            width: 100px;
            height: 2px;
            background: #ddd;
            z-index: -1;
        }

        .step.active:not(:last-child)::after {
            background: #2271b1;
        }

        .step-number {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: #ddd;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .step.active .step-number {
            background: #2271b1;
        }

        .step-title {
            font-size: 12px;
            text-align: center;
            max-width: 80px;
        }

        .setup-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .setup-section {
            margin-bottom: 30px;
        }

        .setup-section h3 {
            margin-top: 0;
            color: #2271b1;
        }

        .status-check {
            display: flex;
            align-items: center;
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }

        .status-check.success {
            background: #d4edda;
            color: #155724;
        }

        .status-check.error {
            background: #f8d7da;
            color: #721c24;
        }

        .status-check .dashicons {
            margin-right: 10px;
        }

        .navigation-buttons {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        </style>
        <?php
    }

    /**
     * Step 1: Prerequisites
     */
    private function step_prerequisites() {
        ?>
        <h2><?php _e('Prerequisites Check', 'tutor-zoyktech'); ?></h2>
        <p><?php _e('Let\'s make sure your site has everything needed for mobile money payments.', 'tutor-zoyktech'); ?></p>

        <div class="setup-section">
            <h3><?php _e('Required Plugins', 'tutor-zoyktech'); ?></h3>
            
            <div class="status-check <?php echo class_exists('WooCommerce') ? 'success' : 'error'; ?>">
                <span class="dashicons <?php echo class_exists('WooCommerce') ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
                <span>
                    <strong>WooCommerce</strong>
                    <?php if (class_exists('WooCommerce')): ?>
                        - <?php _e('Installed and active', 'tutor-zoyktech'); ?>
                    <?php else: ?>
                        - <?php _e('Not found. Please install and activate WooCommerce.', 'tutor-zoyktech'); ?>
                    <?php endif; ?>
                </span>
            </div>

            <div class="status-check <?php echo function_exists('tutor') ? 'success' : 'error'; ?>">
                <span class="dashicons <?php echo function_exists('tutor') ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
                <span>
                    <strong>Tutor LMS</strong>
                    <?php if (function_exists('tutor')): ?>
                        - <?php _e('Installed and active', 'tutor-zoyktech'); ?>
                    <?php else: ?>
                        - <?php _e('Not found. Please install and activate Tutor LMS.', 'tutor-zoyktech'); ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <div class="setup-section">
            <h3><?php _e('Server Requirements', 'tutor-zoyktech'); ?></h3>
            
            <div class="status-check <?php echo function_exists('curl_init') ? 'success' : 'error'; ?>">
                <span class="dashicons <?php echo function_exists('curl_init') ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
                <span>
                    <strong>cURL</strong>
                    <?php if (function_exists('curl_init')): ?>
                        - <?php _e('Available', 'tutor-zoyktech'); ?>
                    <?php else: ?>
                        - <?php _e('Not available. Please enable cURL extension.', 'tutor-zoyktech'); ?>
                    <?php endif; ?>
                </span>
            </div>

            <div class="status-check <?php echo is_ssl() ? 'success' : 'error'; ?>">
                <span class="dashicons <?php echo is_ssl() ? 'dashicons-yes' : 'dashicons-no'; ?>"></span>
                <span>
                    <strong>SSL Certificate</strong>
                    <?php if (is_ssl()): ?>
                        - <?php _e('Active', 'tutor-zoyktech'); ?>
                    <?php else: ?>
                        - <?php _e('Not detected. SSL is recommended for secure payments.', 'tutor-zoyktech'); ?>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <div class="navigation-buttons">
            <div></div>
            <a href="<?php echo admin_url('admin.php?page=tutor-zoyktech-setup&step=2'); ?>" class="button button-primary">
                <?php _e('Next: API Configuration', 'tutor-zoyktech'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Step 2: API Configuration
     */
    private function step_api_config() {
        ?>
        <h2><?php _e('API Configuration', 'tutor-zoyktech'); ?></h2>
        <p><?php _e('Configure your Zoyktech API credentials to enable mobile money payments.', 'tutor-zoyktech'); ?></p>

        <form method="post">
            <?php wp_nonce_field('tutor_zoyktech_setup'); ?>
            <input type="hidden" name="tutor_zoyktech_setup" value="api_config">

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="merchant_id"><?php _e('Merchant ID', 'tutor-zoyktech'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="merchant_id" name="merchant_id" class="regular-text" required>
                        <p class="description">
                            <?php _e('Get your Merchant ID from your Zoyktech dashboard.', 'tutor-zoyktech'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="public_id"><?php _e('Public ID', 'tutor-zoyktech'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="public_id" name="public_id" class="regular-text" required>
                        <p class="description">
                            <?php _e('Get your Public ID from your Zoyktech dashboard.', 'tutor-zoyktech'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="secret_key"><?php _e('Secret Key', 'tutor-zoyktech'); ?></label>
                    </th>
                    <td>
                        <input type="password" id="secret_key" name="secret_key" class="regular-text" required>
                        <p class="description">
                            <?php _e('Get your Secret Key from your Zoyktech dashboard.', 'tutor-zoyktech'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="environment"><?php _e('Environment', 'tutor-zoyktech'); ?></label>
                    </th>
                    <td>
                        <select id="environment" name="environment">
                            <option value="sandbox"><?php _e('Sandbox (Testing)', 'tutor-zoyktech'); ?></option>
                            <option value="live"><?php _e('Live (Production)', 'tutor-zoyktech'); ?></option>
                        </select>
                        <p class="description">
                            <?php _e('Use sandbox for testing, live for actual payments.', 'tutor-zoyktech'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <div class="navigation-buttons">
                <a href="<?php echo admin_url('admin.php?page=tutor-zoyktech-setup&step=1'); ?>" class="button">
                    <?php _e('Previous', 'tutor-zoyktech'); ?>
                </a>
                <button type="submit" class="button button-primary">
                    <?php _e('Save & Continue', 'tutor-zoyktech'); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * Step 3: Course Setup
     */
    private function step_course_setup() {
        ?>
        <h2><?php _e('Course Setup', 'tutor-zoyktech'); ?></h2>
        <p><?php _e('Configure how courses and payments work together.', 'tutor-zoyktech'); ?></p>

        <form method="post">
            <?php wp_nonce_field('tutor_zoyktech_setup'); ?>
            <input type="hidden" name="tutor_zoyktech_setup" value="course_config">

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="auto_create_products"><?php _e('Auto-create Products', 'tutor-zoyktech'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="auto_create_products" name="auto_create_products" value="1" checked>
                            <?php _e('Automatically create WooCommerce products for paid courses', 'tutor-zoyktech'); ?>
                        </label>
                        <p class="description">
                            <?php _e('When you set a price for a course, a WooCommerce product will be created automatically.', 'tutor-zoyktech'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="default_currency"><?php _e('Currency', 'tutor-zoyktech'); ?></label>
                    </th>
                    <td>
                        <select id="default_currency" name="default_currency">
                            <option value="ZMW">ZMW (Zambian Kwacha)</option>
                            <option value="USD">USD (US Dollar)</option>
                        </select>
                        <p class="description">
                            <?php _e('Default currency for course prices.', 'tutor-zoyktech'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="enrollment_email"><?php _e('Enrollment Emails', 'tutor-zoyktech'); ?></label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox" id="enrollment_email" name="enrollment_email" value="1" checked>
                            <?php _e('Send enrollment confirmation emails', 'tutor-zoyktech'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Students will receive an email when they successfully enroll in a course.', 'tutor-zoyktech'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <div class="navigation-buttons">
                <a href="<?php echo admin_url('admin.php?page=tutor-zoyktech-setup&step=2'); ?>" class="button">
                    <?php _e('Previous', 'tutor-zoyktech'); ?>
                </a>
                <button type="submit" class="button button-primary">
                    <?php _e('Save & Continue', 'tutor-zoyktech'); ?>
                </button>
            </div>
        </form>
        <?php
    }

    /**
     * Step 4: Complete
     */
    private function step_complete() {
        ?>
        <h2><?php _e('Setup Complete!', 'tutor-zoyktech'); ?></h2>
        <p><?php _e('Congratulations! Your Tutor LMS Zoyktech integration is now ready.', 'tutor-zoyktech'); ?></p>

        <div class="setup-section">
            <h3><?php _e('What\'s Next?', 'tutor-zoyktech'); ?></h3>
            <ol>
                <li>
                    <strong><?php _e('Create or edit a course', 'tutor-zoyktech'); ?></strong>
                    <p><?php _e('Set a price for your course in the course settings.', 'tutor-zoyktech'); ?></p>
                </li>
                <li>
                    <strong><?php _e('Test the payment flow', 'tutor-zoyktech'); ?></strong>
                    <p><?php _e('Use sandbox mode to test purchases before going live.', 'tutor-zoyktech'); ?></p>
                </li>
                <li>
                    <strong><?php _e('Go live', 'tutor-zoyktech'); ?></strong>
                    <p><?php _e('Switch to live mode in WooCommerce > Settings > Payments > Zoyktech.', 'tutor-zoyktech'); ?></p>
                </li>
            </ol>
        </div>

        <div class="setup-section">
            <h3><?php _e('Useful Links', 'tutor-zoyktech'); ?></h3>
            <ul>
                <li><a href="<?php echo admin_url('edit.php?post_type=courses'); ?>"><?php _e('Manage Courses', 'tutor-zoyktech'); ?></a></li>
                <li><a href="<?php echo admin_url('admin.php?page=wc-settings&tab=checkout&section=zoyktech'); ?>"><?php _e('Payment Settings', 'tutor-zoyktech'); ?></a></li>
                <li><a href="<?php echo admin_url('edit.php?post_type=shop_order'); ?>"><?php _e('View Orders', 'tutor-zoyktech'); ?></a></li>
            </ul>
        </div>

        <div class="navigation-buttons">
            <a href="<?php echo admin_url(); ?>" class="button button-primary button-large">
                <?php _e('Go to Dashboard', 'tutor-zoyktech'); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Process setup
     */
    private function process_setup() {
        $step = $_POST['tutor_zoyktech_setup'];

        switch ($step) {
            case 'api_config':
                $this->save_api_config();
                wp_redirect(admin_url('admin.php?page=tutor-zoyktech-setup&step=3'));
                exit;

            case 'course_config':
                $this->save_course_config();
                wp_redirect(admin_url('admin.php?page=tutor-zoyktech-setup&step=4'));
                exit;
        }
    }

    /**
     * Save API configuration
     */
    private function save_api_config() {
        // Save to WooCommerce gateway settings
        $settings = get_option('woocommerce_zoyktech_settings', array());
        
        $settings['enabled'] = 'yes';
        $settings['merchant_id'] = sanitize_text_field($_POST['merchant_id']);
        $settings['public_id'] = sanitize_text_field($_POST['public_id']);
        $settings['secret_key'] = sanitize_text_field($_POST['secret_key']);
        $settings['testmode'] = $_POST['environment'] === 'sandbox' ? 'yes' : 'no';
        
        update_option('woocommerce_zoyktech_settings', $settings);
    }

    /**
     * Save course configuration
     */
    private function save_course_config() {
        $options = array(
            'auto_create_products' => isset($_POST['auto_create_products']),
            'default_currency' => sanitize_text_field($_POST['default_currency']),
            'enrollment_email' => isset($_POST['enrollment_email'])
        );
        
        update_option('tutor_zoyktech_options', $options);
        update_option('tutor_zoyktech_setup_completed', true);
    }
}

// Initialize setup wizard
new Tutor_Zoyktech_Setup_Wizard();