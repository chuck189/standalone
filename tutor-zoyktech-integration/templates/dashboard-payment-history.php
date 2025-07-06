<?php
/**
 * Payment History Dashboard Template
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$dashboard = new Tutor_Zoyktech_Student_Dashboard();
$user_id = get_current_user_id();
$stats = $dashboard->get_user_payment_stats($user_id);
$recent_activity = $dashboard->get_recent_activity($user_id, 10);
$payment_methods = $dashboard->get_user_payment_methods($user_id);
$has_pending = $dashboard->has_pending_payments($user_id);
?>

<div class="tutor-zoyktech-dashboard">
    <div class="dashboard-header">
        <h2 class="dashboard-title">
            <span class="title-icon">💳</span>
            <?php _e('Payment History', 'tutor-zoyktech'); ?>
        </h2>
        <p class="dashboard-subtitle">
            <?php _e('Track your course payments and download receipts', 'tutor-zoyktech'); ?>
        </p>
    </div>

    <?php if ($has_pending): ?>
    <div class="pending-payments-alert">
        <div class="alert-content">
            <span class="alert-icon">⏳</span>
            <div class="alert-text">
                <strong><?php _e('Pending Payments', 'tutor-zoyktech'); ?></strong>
                <p><?php _e('You have pending payments. Please check your mobile device for payment prompts.', 'tutor-zoyktech'); ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Payment Statistics -->
    <div class="payment-stats-grid">
        <div class="stat-card">
            <div class="stat-icon">📊</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo esc_html($stats->total_payments ?: 0); ?></div>
                <div class="stat-label"><?php _e('Total Payments', 'tutor-zoyktech'); ?></div>
            </div>
        </div>

        <div class="stat-card success">
            <div class="stat-icon">✅</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo esc_html($stats->successful_payments ?: 0); ?></div>
                <div class="stat-label"><?php _e('Successful', 'tutor-zoyktech'); ?></div>
            </div>
        </div>

        <div class="stat-card amount">
            <div class="stat-icon">💰</div>
            <div class="stat-content">
                <div class="stat-number">K<?php echo number_format($stats->total_spent ?: 0, 2); ?></div>
                <div class="stat-label"><?php _e('Total Spent', 'tutor-zoyktech'); ?></div>
            </div>
        </div>

        <div class="stat-card courses">
            <div class="stat-icon">📚</div>
            <div class="stat-content">
                <div class="stat-number"><?php echo esc_html($stats->courses_purchased ?: 0); ?></div>
                <div class="stat-label"><?php _e('Courses Purchased', 'tutor-zoyktech'); ?></div>
            </div>
        </div>
    </div>

    <!-- Payment Methods Used -->
    <?php if (!empty($payment_methods)): ?>
    <div class="payment-methods-section">
        <h3 class="section-title">
            <span class="section-icon">📱</span>
            <?php _e('Your Payment Methods', 'tutor-zoyktech'); ?>
        </h3>
        <div class="payment-methods-grid">
            <?php foreach ($payment_methods as $method): ?>
            <div class="payment-method-card">
                <div class="method-icon">
                    <?php if ($method['provider_id'] == 289): ?>
                        🟠
                    <?php elseif ($method['provider_id'] == 237): ?>
                        🟡
                    <?php else: ?>
                        📱
                    <?php endif; ?>
                </div>
                <div class="method-info">
                    <div class="method-name"><?php echo esc_html($method['provider_name']); ?></div>
                    <div class="method-usage"><?php echo sprintf(_n('%d payment', '%d payments', $method['usage_count'], 'tutor-zoyktech'), $method['usage_count']); ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Recent Transactions -->
    <div class="transactions-section">
        <div class="section-header">
            <h3 class="section-title">
                <span class="section-icon">📋</span>
                <?php _e('Recent Transactions', 'tutor-zoyktech'); ?>
            </h3>
            <div class="section-actions">
                <button class="btn btn-secondary" id="refresh-transactions">
                    <span class="btn-icon">🔄</span>
                    <?php _e('Refresh', 'tutor-zoyktech'); ?>
                </button>
            </div>
        </div>

        <?php if (empty($transactions)): ?>
        <div class="empty-state">
            <div class="empty-icon">💳</div>
            <h4 class="empty-title"><?php _e('No Payments Yet', 'tutor-zoyktech'); ?></h4>
            <p class="empty-description">
                <?php _e('You haven\'t made any course payments yet. Start learning by purchasing a course!', 'tutor-zoyktech'); ?>
            </p>
            <a href="<?php echo esc_url(home_url('/courses')); ?>" class="btn btn-primary">
                <span class="btn-icon">🔍</span>
                <?php _e('Browse Courses', 'tutor-zoyktech'); ?>
            </a>
        </div>
        <?php else: ?>
        <div class="transactions-table-wrapper">
            <table class="transactions-table">
                <thead>
                    <tr>
                        <th><?php _e('Course', 'tutor-zoyktech'); ?></th>
                        <th><?php _e('Amount', 'tutor-zoyktech'); ?></th>
                        <th><?php _e('Provider', 'tutor-zoyktech'); ?></th>
                        <th><?php _e('Status', 'tutor-zoyktech'); ?></th>
                        <th><?php _e('Date', 'tutor-zoyktech'); ?></th>
                        <th><?php _e('Actions', 'tutor-zoyktech'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transactions as $transaction): ?>
                    <tr class="transaction-row" data-order-id="<?php echo esc_attr($transaction->order_id); ?>">
                        <td class="course-cell">
                            <div class="course-info">
                                <div class="course-title">
                                    <?php 
                                    $course = get_post($transaction->course_id);
                                    echo $course ? esc_html($course->post_title) : __('Course not found', 'tutor-zoyktech');
                                    ?>
                                </div>
                                <div class="order-id">
                                    <small><?php echo esc_html($transaction->order_id); ?></small>
                                </div>
                            </div>
                        </td>
                        <td class="amount-cell">
                            <div class="amount">
                                K<?php echo number_format($transaction->amount, 2); ?>
                            </div>
                            <div class="currency">
                                <small><?php echo esc_html($transaction->currency); ?></small>
                            </div>
                        </td>
                        <td class="provider-cell">
                            <div class="provider-info">
                                <span class="provider-icon">
                                    <?php if ($transaction->provider_id == 289): ?>
                                        🟠
                                    <?php elseif ($transaction->provider_id == 237): ?>
                                        🟡
                                    <?php else: ?>
                                        📱
                                    <?php endif; ?>
                                </span>
                                <span class="provider-name">
                                    <?php 
                                    $api = new Tutor_Zoyktech_API();
                                    echo esc_html($api->get_provider_name($transaction->provider_id));
                                    ?>
                                </span>
                            </div>
                        </td>
                        <td class="status-cell">
                            <span class="status-badge status-<?php echo esc_attr($transaction->status); ?>">
                                <?php 
                                $api = new Tutor_Zoyktech_API();
                                echo esc_html($api->get_status_message($transaction->status));
                                ?>
                            </span>
                        </td>
                        <td class="date-cell">
                            <div class="date">
                                <?php echo date('M j, Y', strtotime($transaction->created_at)); ?>
                            </div>
                            <div class="time">
                                <small><?php echo date('g:i A', strtotime($transaction->created_at)); ?></small>
                            </div>
                        </td>
                        <td class="actions-cell">
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-outline view-details" 
                                        data-order-id="<?php echo esc_attr($transaction->order_id); ?>"
                                        title="<?php esc_attr_e('View Details', 'tutor-zoyktech'); ?>">
                                    <span class="btn-icon">👁️</span>
                                </button>
                                <?php if ($transaction->status === 'completed'): ?>
                                <button class="btn btn-sm btn-outline download-receipt" 
                                        data-order-id="<?php echo esc_attr($transaction->order_id); ?>"
                                        title="<?php esc_attr_e('Download Receipt', 'tutor-zoyktech'); ?>">
                                    <span class="btn-icon">📄</span>
                                </button>
                                <?php endif; ?>
                                <?php if ($course && $transaction->status === 'completed'): ?>
                                <a href="<?php echo esc_url(get_permalink($course->ID)); ?>" 
                                   class="btn btn-sm btn-primary"
                                   title="<?php esc_attr_e('Go to Course', 'tutor-zoyktech'); ?>">
                                    <span class="btn-icon">📚</span>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Payment Details Modal -->
<div id="payment-details-modal" class="modal" style="display: none;">
    <div class="modal-overlay"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title"><?php _e('Payment Details', 'tutor-zoyktech'); ?></h3>
            <button class="modal-close" type="button">×</button>
        </div>
        <div class="modal-body">
            <div class="payment-details-content">
                <!-- Content will be loaded via AJAX -->
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary modal-close"><?php _e('Close', 'tutor-zoyktech'); ?></button>
        </div>
    </div>
</div>

<style>
/* Dashboard Styles */
.tutor-zoyktech-dashboard {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.dashboard-header {
    margin-bottom: 30px;
    text-align: center;
}

.dashboard-title {
    font-size: 28px;
    font-weight: 700;
    color: #2c3e50;
    margin: 0 0 8px 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.title-icon {
    font-size: 32px;
}

.dashboard-subtitle {
    color: #7f8c8d;
    font-size: 16px;
    margin: 0;
}

/* Pending Payments Alert */
.pending-payments-alert {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 24px;
}

.alert-content {
    display: flex;
    align-items: flex-start;
    gap: 12px;
}

.alert-icon {
    font-size: 24px;
    flex-shrink: 0;
}

.alert-text strong {
    color: #856404;
    display: block;
    margin-bottom: 4px;
}

.alert-text p {
    color: #856404;
    margin: 0;
    font-size: 14px;
}

/* Payment Statistics */
.payment-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border: 1px solid #e8ecef;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-card.success {
    border-left: 4px solid #27ae60;
}

.stat-card.amount {
    border-left: 4px solid #f39c12;
}

.stat-card.courses {
    border-left: 4px solid #8e44ad;
}

.stat-icon {
    font-size: 32px;
    opacity: 0.8;
}

.stat-number {
    font-size: 24px;
    font-weight: 700;
    color: #2c3e50;
    line-height: 1;
}

.stat-label {
    font-size: 14px;
    color: #7f8c8d;
    margin-top: 4px;
}

/* Payment Methods */
.payment-methods-section {
    margin-bottom: 30px;
}

.section-title {
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.section-icon {
    font-size: 24px;
}

.payment-methods-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
}

.payment-method-card {
    background: white;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border: 1px solid #e8ecef;
    display: flex;
    align-items: center;
    gap: 16px;
}

.method-icon {
    font-size: 32px;
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border-radius: 50%;
}

.method-name {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 4px;
}

.method-usage {
    font-size: 14px;
    color: #7f8c8d;
}

/* Transactions Section */
.transactions-section {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    border: 1px solid #e8ecef;
    overflow: hidden;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px;
    border-bottom: 1px solid #e8ecef;
}

.section-actions {
    display: flex;
    gap: 12px;
}

/* Transactions Table */
.transactions-table-wrapper {
    overflow-x: auto;
}

.transactions-table {
    width: 100%;
    border-collapse: collapse;
}

.transactions-table th {
    background: #f8f9fa;
    padding: 16px;
    text-align: left;
    font-weight: 600;
    color: #2c3e50;
    border-bottom: 1px solid #e8ecef;
    font-size: 14px;
}

.transactions-table td {
    padding: 16px;
    border-bottom: 1px solid #f8f9fa;
    vertical-align: top;
}

.transaction-row:hover {
    background: #f8f9fa;
}

.course-info .course-title {
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 4px;
}

.course-info .order-id {
    color: #7f8c8d;
}

.amount {
    font-weight: 600;
    color: #2c3e50;
    font-size: 16px;
}

.currency {
    color: #7f8c8d;
}

.provider-info {
    display: flex;
    align-items: center;
    gap: 8px;
}

.provider-icon {
    font-size: 20px;
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.status-completed {
    background: #d4edda;
    color: #155724;
}

.status-badge.status-pending {
    background: #fff3cd;
    color: #856404;
}

.status-badge.status-processing {
    background: #cce5ff;
    color: #004085;
}

.status-badge.status-failed {
    background: #f8d7da;
    color: #721c24;
}

.date {
    font-weight: 500;
    color: #2c3e50;
}

.time {
    color: #7f8c8d;
}

.action-buttons {
    display: flex;
    gap: 8px;
}

.btn {
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    text-decoration: none;
}

.btn-sm {
    padding: 6px 10px;
    font-size: 12px;
}

.btn-primary {
    background: #667eea;
    color: white;
}

.btn-primary:hover {
    background: #5a6fd8;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-outline {
    background: transparent;
    border: 1px solid #dee2e6;
    color: #6c757d;
}

.btn-outline:hover {
    background: #f8f9fa;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-icon {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.5;
}

.empty-title {
    font-size: 24px;
    font-weight: 600;
    color: #2c3e50;
    margin: 0 0 12px 0;
}

.empty-description {
    color: #7f8c8d;
    font-size: 16px;
    margin: 0 0 24px 0;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

/* Modal */
.modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1000;
}

.modal-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.5);
}

.modal-content {
    position: relative;
    background: white;
    border-radius: 12px;
    max-width: 600px;
    margin: 50px auto;
    max-height: calc(100vh - 100px);
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 24px;
    border-bottom: 1px solid #e8ecef;
}

.modal-title {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #7f8c8d;
    padding: 0;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.modal-close:hover {
    background: #f8f9fa;
}

.modal-body {
    padding: 24px;
    max-height: 400px;
    overflow-y: auto;
}

.modal-footer {
    padding: 24px;
    border-top: 1px solid #e8ecef;
    text-align: right;
}

/* Responsive Design */
@media (max-width: 768px) {
    .tutor-zoyktech-dashboard {
        padding: 10px;
    }
    
    .payment-stats-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .stat-card {
        padding: 16px;
    }
    
    .section-header {
        flex-direction: column;
        gap: 16px;
        align-items: flex-start;
    }
    
    .transactions-table th,
    .transactions-table td {
        padding: 12px 8px;
        font-size: 14px;
    }
    
    .action-buttons {
        flex-direction: column;
        gap: 4px;
    }
    
    .modal-content {
        margin: 20px;
        max-width: calc(100% - 40px);
    }
}
</style>

<script>
jQuery(document).ready(function($) {
    // View payment details
    $('.view-details').on('click', function() {
        const orderId = $(this).data('order-id');
        
        $.ajax({
            url: tutorZoyktech.ajax_url,
            type: 'POST',
            data: {
                action: 'tutor_zoyktech_get_payment_details',
                nonce: tutorZoyktech.nonce,
                order_id: orderId
            },
            success: function(response) {
                if (response.success) {
                    showPaymentDetails(response.data);
                }
            }
        });
    });
    
    // Download receipt
    $('.download-receipt').on('click', function() {
        const orderId = $(this).data('order-id');
        
        // Create form and submit for download
        const form = $('<form>', {
            method: 'POST',
            action: tutorZoyktech.ajax_url
        });
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'action',
            value: 'tutor_zoyktech_download_receipt'
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'nonce',
            value: tutorZoyktech.nonce
        }));
        
        form.append($('<input>', {
            type: 'hidden',
            name: 'order_id',
            value: orderId
        }));
        
        $('body').append(form);
        form.submit();
        form.remove();
    });
    
    // Modal functionality
    $('.modal-close, .modal-overlay').on('click', function() {
        $('.modal').hide();
    });
    
    // Refresh transactions
    $('#refresh-transactions').on('click', function() {
        location.reload();
    });
    
    function showPaymentDetails(data) {
        const content = `
            <div class="payment-detail-grid">
                <div class="detail-row">
                    <span class="detail-label">Order ID:</span>
                    <span class="detail-value">${data.transaction.order_id}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Course:</span>
                    <span class="detail-value">${data.course.post_title}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Amount:</span>
                    <span class="detail-value">${data.formatted_amount}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Provider:</span>
                    <span class="detail-value">${data.provider_name}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Status:</span>
                    <span class="detail-value status-${data.transaction.status}">${data.status_message}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Date:</span>
                    <span class="detail-value">${new Date(data.transaction.created_at).toLocaleString()}</span>
                </div>
                ${data.transaction.transaction_id ? `
                <div class="detail-row">
                    <span class="detail-label">Transaction ID:</span>
                    <span class="detail-value">${data.transaction.transaction_id}</span>
                </div>
                ` : ''}
            </div>
        `;
        
        $('.payment-details-content').html(content);
        $('#payment-details-modal').show();
    }
});
</script>

