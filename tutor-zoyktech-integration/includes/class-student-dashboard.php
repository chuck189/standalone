<?php
/**
 * Student Dashboard for Tutor LMS Zoyktech Integration
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Student Dashboard Class
 */
class Tutor_Zoyktech_Student_Dashboard {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', array($this, 'init'));
    }

    /**
     * Initialize dashboard features
     */
    public function init() {
        // Add payment history tab to Tutor dashboard
        add_filter('tutor_dashboard/nav_items', array($this, 'add_payment_history_tab'));
        
        // Handle payment history page
        add_action('tutor_load_dashboard_template_before', array($this, 'load_payment_history_template'));
        
        // Add AJAX handlers for dashboard
        add_action('wp_ajax_tutor_zoyktech_get_payment_details', array($this, 'get_payment_details'));
        add_action('wp_ajax_tutor_zoyktech_download_receipt', array($this, 'download_receipt'));
    }

    /**
     * Add payment history tab to Tutor dashboard
     */
    public function add_payment_history_tab($nav_items) {
        $nav_items['payment-history'] = array(
            'title' => __('Payment History', 'tutor-zoyktech'),
            'icon' => 'tutor-icon-purchase',
            'auth_cap' => tutor()->student_role,
        );

        return $nav_items;
    }

    /**
     * Load payment history template
     */
    public function load_payment_history_template($template_name) {
        if ($template_name === 'payment-history') {
            $this->display_payment_history();
            return;
        }
    }

    /**
     * Display payment history page
     */
    public function display_payment_history() {
        $user_id = get_current_user_id();
        $api = new Tutor_Zoyktech_API();
        $transactions = $api->get_user_transactions($user_id, 50);
        
        include TUTOR_ZOYKTECH_PLUGIN_PATH . 'templates/dashboard-payment-history.php';
    }

    /**
     * Get payment details via AJAX
     */
    public function get_payment_details() {
        check_ajax_referer('tutor_zoyktech_nonce', 'nonce');

        $order_id = sanitize_text_field($_POST['order_id']);
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error(array('message' => 'Not authenticated'));
        }

        $api = new Tutor_Zoyktech_API();
        $transaction = $api->get_transaction($order_id);

        if (!$transaction || $transaction->user_id != $user_id) {
            wp_send_json_error(array('message' => 'Transaction not found'));
        }

        // Get course details
        $course = get_post($transaction->course_id);
        $payment_data = json_decode($transaction->payment_data, true);

        $details = array(
            'transaction' => $transaction,
            'course' => $course,
            'payment_data' => $payment_data,
            'provider_name' => $api->get_provider_name($transaction->provider_id),
            'status_message' => $api->get_status_message($transaction->status),
            'formatted_amount' => $this->format_amount($transaction->amount, $transaction->currency)
        );

        wp_send_json_success($details);
    }

    /**
     * Download payment receipt
     */
    public function download_receipt() {
        check_ajax_referer('tutor_zoyktech_nonce', 'nonce');

        $order_id = sanitize_text_field($_POST['order_id']);
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_die('Not authenticated');
        }

        $api = new Tutor_Zoyktech_API();
        $transaction = $api->get_transaction($order_id);

        if (!$transaction || $transaction->user_id != $user_id) {
            wp_die('Transaction not found');
        }

        $this->generate_receipt_pdf($transaction);
    }

    /**
     * Generate PDF receipt
     */
    private function generate_receipt_pdf($transaction) {
        // Get course and user details
        $course = get_post($transaction->course_id);
        $user = get_userdata($transaction->user_id);
        $api = new Tutor_Zoyktech_API();

        // Create receipt content
        $receipt_content = $this->get_receipt_content($transaction, $course, $user, $api);

        // Set headers for PDF download
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="receipt-' . $transaction->order_id . '.pdf"');
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');

        // Generate PDF (simplified version - you might want to use a proper PDF library)
        echo $this->create_simple_pdf($receipt_content);
        exit;
    }

    /**
     * Get receipt content
     */
    private function get_receipt_content($transaction, $course, $user, $api) {
        return array(
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'receipt_title' => __('Payment Receipt', 'tutor-zoyktech'),
            'order_id' => $transaction->order_id,
            'transaction_id' => $transaction->transaction_id,
            'date' => date('F j, Y', strtotime($transaction->created_at)),
            'customer_name' => $user->display_name,
            'customer_email' => $user->user_email,
            'course_title' => $course->post_title,
            'amount' => $this->format_amount($transaction->amount, $transaction->currency),
            'currency' => $transaction->currency,
            'provider' => $api->get_provider_name($transaction->provider_id),
            'status' => $api->get_status_message($transaction->status),
            'phone_number' => $transaction->phone_number
        );
    }

    /**
     * Create simple PDF (basic implementation)
     */
    private function create_simple_pdf($content) {
        // This is a very basic PDF implementation
        // In production, you should use a proper PDF library like TCPDF or FPDF
        
        $pdf_content = "%PDF-1.4\n";
        $pdf_content .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdf_content .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $pdf_content .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>\nendobj\n";
        
        $text_content = "BT\n/F1 12 Tf\n50 750 Td\n";
        $text_content .= "(" . $content['site_name'] . " - " . $content['receipt_title'] . ") Tj\n";
        $text_content .= "0 -20 Td\n(Order ID: " . $content['order_id'] . ") Tj\n";
        $text_content .= "0 -20 Td\n(Date: " . $content['date'] . ") Tj\n";
        $text_content .= "0 -20 Td\n(Customer: " . $content['customer_name'] . ") Tj\n";
        $text_content .= "0 -20 Td\n(Course: " . $content['course_title'] . ") Tj\n";
        $text_content .= "0 -20 Td\n(Amount: " . $content['amount'] . ") Tj\n";
        $text_content .= "0 -20 Td\n(Provider: " . $content['provider'] . ") Tj\n";
        $text_content .= "0 -20 Td\n(Status: " . $content['status'] . ") Tj\n";
        $text_content .= "ET\n";
        
        $pdf_content .= "4 0 obj\n<< /Length " . strlen($text_content) . " >>\nstream\n" . $text_content . "endstream\nendobj\n";
        $pdf_content .= "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $pdf_content .= "xref\n0 6\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000251 00000 n \n0000000500 00000 n \n";
        $pdf_content .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n565\n%%EOF";
        
        return $pdf_content;
    }

    /**
     * Format amount for display
     */
    private function format_amount($amount, $currency = 'ZMW') {
        $symbols = array(
            'ZMW' => 'K',
            'USD' => '$'
        );

        $symbol = isset($symbols[$currency]) ? $symbols[$currency] : $currency;
        return $symbol . number_format($amount, 2);
    }

    /**
     * Get payment statistics for user
     */
    public function get_user_payment_stats($user_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';

        $stats = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT 
                    COUNT(*) as total_payments,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as successful_payments,
                    COUNT(CASE WHEN status = 'failed' THEN 1 END) as failed_payments,
                    SUM(CASE WHEN status = 'completed' THEN amount ELSE 0 END) as total_spent,
                    COUNT(DISTINCT course_id) as courses_purchased
                FROM $table_name 
                WHERE user_id = %d",
                $user_id
            )
        );

        return $stats;
    }

    /**
     * Get recent payment activity
     */
    public function get_recent_activity($user_id, $limit = 5) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';

        $activities = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*, p.post_title as course_title
                FROM $table_name t
                LEFT JOIN {$wpdb->posts} p ON t.course_id = p.ID
                WHERE t.user_id = %d
                ORDER BY t.created_at DESC
                LIMIT %d",
                $user_id,
                $limit
            )
        );

        return $activities;
    }

    /**
     * Check if user has any pending payments
     */
    public function has_pending_payments($user_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';

        $pending_count = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name 
                WHERE user_id = %d AND status IN ('pending', 'processing')",
                $user_id
            )
        );

        return intval($pending_count) > 0;
    }

    /**
     * Get payment methods used by user
     */
    public function get_user_payment_methods($user_id) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'tutor_zoyktech_transactions';
        $api = new Tutor_Zoyktech_API();

        $providers = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT provider_id, COUNT(*) as usage_count
                FROM $table_name 
                WHERE user_id = %d AND status = 'completed'
                GROUP BY provider_id
                ORDER BY usage_count DESC",
                $user_id
            )
        );

        $payment_methods = array();
        foreach ($providers as $provider) {
            $payment_methods[] = array(
                'provider_id' => $provider->provider_id,
                'provider_name' => $api->get_provider_name($provider->provider_id),
                'usage_count' => $provider->usage_count
            );
        }

        return $payment_methods;
    }
}

