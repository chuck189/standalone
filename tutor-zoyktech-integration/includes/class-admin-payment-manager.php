<?php
/**
 * Admin Payment Manager for Manual Payments
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin Payment Manager Class
 */
class Tutor_Zoyktech_Admin_Payment_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'tutor',
            __('Mobile Money Payments', 'tutor-zoyktech'),
            __('Mobile Money', 'tutor-zoyktech'),
            'manage_tutor',
            'tutor-mobile-money',
            array($this, 'admin_page')
        );
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'tutor_page_tutor-mobile-money') {
            return;
        }

        wp_enqueue_script(
            'tutor-mobile-money-admin',
            TUTOR_ZOYKTECH_PLUGIN_URL . 'assets/js/admin-payments.js',
            array('jquery'),
            TUTOR_ZOYKTECH_VERSION,
            true
        );

        wp_localize_script('tutor-mobile-money-admin', 'tutorMobileMoney', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('tutor_admin_nonce'),
            'messages' => array(
                'confirm_approve' => __('Are you sure you want to approve this payment?', 'tutor-zoyktech'),
                'confirm_reject' => __('Are you sure you want to reject this payment?', 'tutor-zoyktech')
            )
        ));
    }

    /**
     * Admin page
     */
    public function admin_page() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'pending';
        ?>
        <div class="wrap">
            <h1><?php _e('Mobile Money Payments', 'tutor-zoyktech'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=tutor-mobile-money&tab=pending" 
                   class="nav-tab <?php echo $tab === 'pending' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Pending Payments', 'tutor-zoyktech'); ?>
                    <?php
                    $pending_count = $this->get_payments_count('pending');
                    if ($pending_count > 0) {
                        echo '<span class="awaiting-mod count-' . $pending_count . '"><span class="pending-count">' . $pending_count . '</span></span>';
                    }
                    ?>
                </a>
                <a href="?page=tutor-mobile-money&tab=completed" 
                   class="nav-tab <?php echo $tab === 'completed' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Approved Payments', 'tutor-zoyktech'); ?>
                </a>
                <a href="?page=tutor-mobile-money&tab=rejected" 
                   class="nav-tab <?php echo $tab === 'rejected' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Rejected Payments', 'tutor-zoyktech'); ?>
                </a>
                <a href="?page=tutor-mobile-money&tab=all" 
                   class="nav-tab <?php echo $tab === 'all' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('All Payments', 'tutor-zoyktech'); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php $this->display_payments_table($tab); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get payments count by status
     */
    private function get_payments_count($status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';
        
        return $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE status = %s",
                $status
            )
        );
    }

    /**
     * Display payments table
     */
    private function display_payments_table($status) {
        $payments = $this->get_payments($status);
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('Date', 'tutor-zoyktech'); ?></th>
                    <th><?php _e('Student', 'tutor-zoyktech'); ?></th>
                    <th><?php _e('Course', 'tutor-zoyktech'); ?></th>
                    <th><?php _e('Amount', 'tutor-zoyktech'); ?></th>
                    <th><?php _e('Provider', 'tutor-zoyktech'); ?></th>
                    <th><?php _e('Transaction ID', 'tutor-zoyktech'); ?></th>
                    <th><?php _e('Phone', 'tutor-zoyktech'); ?></th>
                    <th><?php _e('Status', 'tutor-zoyktech'); ?></th>
                    <th><?php _e('Actions', 'tutor-zoyktech'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($payments)): ?>
                <tr>
                    <td colspan="9" style="text-align: center; padding: 20px;">
                        <?php _e('No payments found.', 'tutor-zoyktech'); ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?php echo date('M j, Y g:i A', strtotime($payment->created_at)); ?></td>
                    <td>
                        <?php
                        $user = get_userdata($payment->user_id);
                        echo $user ? esc_html($user->display_name) : __('Unknown', 'tutor-zoyktech');
                        ?>
                    </td>
                    <td>
                        <?php
                        $course = get_post($payment->course_id);
                        if ($course) {
                            echo '<a href="' . get_permalink($course) . '" target="_blank">';
                            echo esc_html($course->post_title);
                            echo '</a>';
                        } else {
                            _e('Course not found', 'tutor-zoyktech');
                        }
                        ?>
                    </td>
                    <td>K<?php echo number_format($payment->amount, 2); ?></td>
                    <td>
                        <?php
                        $provider_names = array(289 => 'Airtel Money', 237 => 'MTN Mobile Money');
                        echo isset($provider_names[$payment->provider_id]) 
                            ? $provider_names[$payment->provider_id] 
                            : __('Unknown', 'tutor-zoyktech');
                        ?>
                    </td>
                    <td><code><?php echo esc_html($payment->transaction_id); ?></code></td>
                    <td><?php echo esc_html($payment->phone_number); ?></td>
                    <td>
                        <span class="status-badge status-<?php echo esc_attr($payment->status); ?>">
                            <?php echo ucfirst($payment->status); ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($payment->status === 'pending'): ?>
                        <button class="button button-primary approve-payment" 
                                data-id="<?php echo $payment->id; ?>">
                            <?php _e('Approve', 'tutor-zoyktech'); ?>
                        </button>
                        <button class="button reject-payment" 
                                data-id="<?php echo $payment->id; ?>">
                            <?php _e('Reject', 'tutor-zoyktech'); ?>
                        </button>
                        <?php else: ?>
                        <button class="button view-details" 
                                data-id="<?php echo $payment->id; ?>">
                            <?php _e('Details', 'tutor-zoyktech'); ?>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <style>
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        
        .status-rejected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .approve-payment {
            margin-right: 5px;
        }
        </style>
        <?php
    }

    /**
     * Get payments by status
     */
    private function get_payments($status) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';
        
        if ($status === 'all') {
            return $wpdb->get_results(
                "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 100"
            );
        } else {
            return $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM $table_name WHERE status = %s ORDER BY created_at DESC LIMIT 100",
                    $status
                )
            );
        }
    }
}

// Initialize admin payment manager
new Tutor_Zoyktech_Admin_Payment_Manager();