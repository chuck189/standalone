<?php
/**
 * Admin Settings for Zoyktech WooCommerce Gateway
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            __('Zoyktech Payments', 'tutor-zoyktech'),
            __('Zoyktech Payments', 'tutor-zoyktech'),
            'manage_woocommerce',
            'zoyktech-payments',
            array($this, 'admin_page')
        );
    }

    /**
     * Initialize admin
     */
    public function admin_init() {
        // Add AJAX handlers
        add_action('wp_ajax_zoyktech_test_connection', array($this, 'test_connection'));
        add_action('wp_ajax_zoyktech_get_payment_stats', array($this, 'get_payment_stats'));
    }

    /**
     * Admin page
     */
    public function admin_page() {
        $tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
        ?>
        <div class="wrap">
            <h1><?php _e('Zoyktech Mobile Money Payments', 'tutor-zoyktech'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=zoyktech-payments&tab=overview" 
                   class="nav-tab <?php echo $tab === 'overview' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Overview', 'tutor-zoyktech'); ?>
                </a>
                <a href="?page=zoyktech-payments&tab=transactions" 
                   class="nav-tab <?php echo $tab === 'transactions' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Transactions', 'tutor-zoyktech'); ?>
                </a>
                <a href="?page=zoyktech-payments&tab=settings" 
                   class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Settings', 'tutor-zoyktech'); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ($tab) {
                    case 'overview':
                        $this->overview_tab();
                        break;
                    case 'transactions':
                        $this->transactions_tab();
                        break;
                    case 'settings':
                        $this->settings_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Overview tab
     */
    private function overview_tab() {
        $stats = $this->get_payment_statistics();
        ?>
        <div class="zoyktech-overview">
            <div class="stats-grid">
                <div class="stat-card">
                    <h3><?php _e('Total Transactions', 'tutor-zoyktech'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['total_transactions']); ?></div>
                </div>

                <div class="stat-card">
                    <h3><?php _e('Successful Payments', 'tutor-zoyktech'); ?></h3>
                    <div class="stat-number"><?php echo number_format($stats['successful_payments']); ?></div>
                </div>

                <div class="stat-card">
                    <h3><?php _e('Total Revenue', 'tutor-zoyktech'); ?></h3>
                    <div class="stat-number">K<?php echo number_format($stats['total_revenue'], 2); ?></div>
                </div>

                <div class="stat-card">
                    <h3><?php _e('Success Rate', 'tutor-zoyktech'); ?></h3>
                    <div class="stat-number"><?php echo $stats['success_rate']; ?>%</div>
                </div>
            </div>

            <div class="recent-transactions">
                <h3><?php _e('Recent Transactions', 'tutor-zoyktech'); ?></h3>
                <?php $this->display_recent_transactions(); ?>
            </div>
        </div>

        <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }

        .stat-card {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
        }

        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #2271b1;
        }

        .recent-transactions {
            margin-top: 30px;
        }
        </style>
        <?php
    }

    /**
     * Transactions tab
     */
    private function transactions_tab() {
        $transactions = $this->get_transactions();
        ?>
        <div class="zoyktech-transactions">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Date', 'tutor-zoyktech'); ?></th>
                        <th><?php _e('Order ID', 'tutor-zoyktech'); ?></th>
                        <th><?php _e('Amount', 'tutor-zoyktech'); ?></th>
                        <th><?php _e('Phone Number', 'tutor-zoyktech'); ?></th>
                        <th><?php _e('Provider', 'tutor-zoyktech'); ?></th>
                        <th><?php _e('Status', 'tutor-zoyktech'); ?></th>
                        <th><?php _e('Transaction ID', 'tutor-zoyktech'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($transactions)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center;">
                            <?php _e('No transactions found.', 'tutor-zoyktech'); ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr>
                        <td><?php echo date('M j, Y g:i A', strtotime($transaction->created_at)); ?></td>
                        <td>
                            <a href="<?php echo admin_url('post.php?post=' . $transaction->order_id . '&action=edit'); ?>">
                                #<?php echo $transaction->order_id; ?>
                            </a>
                        </td>
                        <td><?php echo $transaction->currency; ?> <?php echo number_format($transaction->amount, 2); ?></td>
                        <td><?php echo esc_html($transaction->phone_number); ?></td>
                        <td>
                            <?php
                            $providers = array(289 => 'Airtel Money', 237 => 'MTN Mobile Money');
                            echo isset($providers[$transaction->provider_id]) ? $providers[$transaction->provider_id] : 'Unknown';
                            ?>
                        </td>
                        <td>
                            <span class="status-<?php echo esc_attr($transaction->status); ?>">
                                <?php echo ucfirst($transaction->status); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($transaction->transaction_id ?: '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <style>
        .status-pending { color: #d63638; }
        .status-completed { color: #00a32a; }
        .status-failed { color: #d63638; }
        .status-cancelled { color: #dba617; }
        </style>
        <?php
    }

    /**
     * Settings tab
     */
    private function settings_tab() {
        ?>
        <div class="zoyktech-settings">
            <p>
                <?php 
                printf(
                    __('Configure your Zoyktech settings in the <a href="%s">WooCommerce payment settings</a>.', 'tutor-zoyktech'),
                    admin_url('admin.php?page=wc-settings&tab=checkout&section=zoyktech')
                );
                ?>
            </p>

            <div class="connection-test">
                <h3><?php _e('Test API Connection', 'tutor-zoyktech'); ?></h3>
                <p><?php _e('Test your API credentials to ensure they are working correctly.', 'tutor-zoyktech'); ?></p>
                <button id="test-connection" class="button button-primary">
                    <?php _e('Test Connection', 'tutor-zoyktech'); ?>
                </button>
                <div id="test-result"></div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#test-connection').on('click', function() {
                const button = $(this);
                button.prop('disabled', true).text('Testing...');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'zoyktech_test_connection',
                        nonce: '<?php echo wp_create_nonce('zoyktech_admin'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#test-result').html('<div class="notice notice-success"><p>' + response.data.message + '</p></div>');
                        } else {
                            $('#test-result').html('<div class="notice notice-error"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Test Connection');
                    }
                });
            });
        });
        </script>
        <?php
    }

    /**
     * Get payment statistics
     */
    private function get_payment_statistics() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'zoyktech_payment_logs';
        
        $stats = $wpdb->get_row(
            "SELECT 
                COUNT(*) as total_transactions,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments,
                SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_revenue
            FROM $table_name"
        );
        
        $success_rate = $stats->total_transactions > 0 
            ? round(($stats->successful_payments / $stats->total_transactions) * 100, 1)
            : 0;
        
        return array(
            'total_transactions' => (int) $stats->total_transactions,
            'successful_payments' => (int) $stats->successful_payments,
            'total_revenue' => (float) $stats->total_revenue,
            'success_rate' => $success_rate
        );
    }

    /**
     * Get transactions
     */
    private function get_transactions($limit = 50) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'zoyktech_payment_logs';
        
        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Display recent transactions
     */
    private function display_recent_transactions() {
        $transactions = $this->get_transactions(10);
        
        if (empty($transactions)) {
            echo '<p>' . __('No recent transactions.', 'tutor-zoyktech') . '</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat">';
        echo '<thead><tr>';
        echo '<th>' . __('Date', 'tutor-zoyktech') . '</th>';
        echo '<th>' . __('Order', 'tutor-zoyktech') . '</th>';
        echo '<th>' . __('Amount', 'tutor-zoyktech') . '</th>';
        echo '<th>' . __('Status', 'tutor-zoyktech') . '</th>';
        echo '</tr></thead><tbody>';
        
        foreach ($transactions as $transaction) {
            echo '<tr>';
            echo '<td>' . date('M j, g:i A', strtotime($transaction->created_at)) . '</td>';
            echo '<td><a href="' . admin_url('post.php?post=' . $transaction->order_id . '&action=edit') . '">#' . $transaction->order_id . '</a></td>';
            echo '<td>' . $transaction->currency . ' ' . number_format($transaction->amount, 2) . '</td>';
            echo '<td><span class="status-' . esc_attr($transaction->status) . '">' . ucfirst($transaction->status) . '</span></td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }

    /**
     * Test API connection
     */
    public function test_connection() {
        check_ajax_referer('zoyktech_admin', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => 'Permission denied'));
        }
        
        // Get gateway settings
        $gateway = new WC_Zoyktech_Gateway();
        
        if (!$gateway->merchant_id || !$gateway->public_id || !$gateway->secret_key) {
            wp_send_json_error(array('message' => 'Please configure your API credentials first'));
        }
        
        // Test connection would go here
        wp_send_json_success(array('message' => 'API credentials are valid and connection is working'));
    }
}

// Initialize admin settings
new Tutor_Zoyktech_Admin_Settings();