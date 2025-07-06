<?php
/**
 * Payment History Manager for Tutor LMS Zoyktech Integration
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Payment History Manager Class
 */
class Tutor_Zoyktech_Payment_History {

    /**
     * Constructor
     */
    public function __construct() {
        // Initialize payment history features
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize
     */
    public function init() {
        // Add AJAX handlers
        add_action('wp_ajax_tutor_zoyktech_get_payment_history', array($this, 'get_payment_history_ajax'));
        add_action('wp_ajax_tutor_zoyktech_export_payments', array($this, 'export_payments_ajax'));
    }

    /**
     * Get user payment history
     */
    public function get_user_payment_history($user_id, $limit = 20, $offset = 0) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';

        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*, p.post_title as course_title
                FROM $table_name t
                LEFT JOIN {$wpdb->posts} p ON t.course_id = p.ID
                WHERE t.user_id = %d
                ORDER BY t.created_at DESC
                LIMIT %d OFFSET %d",
                $user_id,
                $limit,
                $offset
            )
        );

        return $results;
    }

    /**
     * Get payment statistics
     */
    public function get_payment_statistics($user_id = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';

        $where_clause = '';
        $prepare_args = array();

        if ($user_id) {
            $where_clause = 'WHERE user_id = %d';
            $prepare_args[] = $user_id;
        }

        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_transactions,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_transactions,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_transactions,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_transactions,
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_amount,
                    AVG(CASE WHEN status = 'completed' THEN amount ELSE NULL END) as average_amount
                FROM $table_name 
                $where_clause",
                ...$prepare_args
            )
        );

        return $stats;
    }

    /**
     * Handle payment history AJAX request
     */
    public function get_payment_history_ajax() {
        check_ajax_referer('tutor_zoyktech_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $page = intval($_POST['page'] ?? 1);
        $per_page = intval($_POST['per_page'] ?? 10);
        $offset = ($page - 1) * $per_page;

        $history = $this->get_user_payment_history($user_id, $per_page, $offset);
        $stats = $this->get_payment_statistics($user_id);

        wp_send_json_success(array(
            'history' => $history,
            'stats' => $stats,
            'page' => $page,
            'per_page' => $per_page
        ));
    }

    /**
     * Export payments to CSV
     */
    public function export_payments_ajax() {
        check_ajax_referer('tutor_zoyktech_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $payments = $this->get_user_payment_history($user_id, 1000);
        
        $csv_content = $this->generate_csv($payments);
        
        wp_send_json_success(array(
            'csv_content' => $csv_content,
            'filename' => 'payment-history-' . date('Y-m-d') . '.csv'
        ));
    }

    /**
     * Generate CSV content
     */
    private function generate_csv($payments) {
        $csv = "Date,Course,Amount,Currency,Provider,Status,Order ID\n";
        
        foreach ($payments as $payment) {
            $api = new Tutor_Zoyktech_API();
            $provider_name = $api->get_provider_name($payment->provider_id);
            
            $csv .= sprintf(
                '"%s","%s","%s","%s","%s","%s","%s"' . "\n",
                $payment->created_at,
                $payment->course_title ?: 'Unknown Course',
                $payment->amount,
                $payment->currency,
                $provider_name,
                $payment->status,
                $payment->order_id
            );
        }
        
        return $csv;
    }

    /**
     * Get payment by order ID
     */
    public function get_payment_by_order_id($order_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';

        return $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_name WHERE order_id = %s",
                $order_id
            )
        );
    }

    /**
     * Get recent payments
     */
    public function get_recent_payments($limit = 5) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*, p.post_title as course_title
                FROM $table_name t
                LEFT JOIN {$wpdb->posts} p ON t.course_id = p.ID
                ORDER BY t.created_at DESC
                LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Get payments by date range
     */
    public function get_payments_by_date_range($start_date, $end_date, $user_id = null) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';

        $where_clause = 'WHERE created_at BETWEEN %s AND %s';
        $prepare_args = array($start_date, $end_date);

        if ($user_id) {
            $where_clause .= ' AND user_id = %d';
            $prepare_args[] = $user_id;
        }

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*, p.post_title as course_title
                FROM $table_name t
                LEFT JOIN {$wpdb->posts} p ON t.course_id = p.ID
                $where_clause
                ORDER BY t.created_at DESC",
                ...$prepare_args
            )
        );
    }
}