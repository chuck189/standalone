<?php
/**
 * Order Completion Notice for Students
 * Shows immediate access confirmation
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get order details
$order = isset($order) ? $order : wc_get_order(get_query_var('order-received'));

if (!$order || $order->get_payment_method() !== 'zoyktech') {
    return;
}

// Get enrolled courses
$enrolled_courses = $order->get_meta('_enrolled_courses');

if (empty($enrolled_courses)) {
    return;
}
?>

<div class="zoyktech-order-completion-notice">
    <div class="completion-header">
        <div class="completion-icon">🎉</div>
        <h2><?php _e('Payment Confirmed - Course Access Granted!', 'tutor-zoyktech'); ?></h2>
        <p><?php _e('Your mobile money payment has been processed successfully.', 'tutor-zoyktech'); ?></p>
    </div>

    <div class="enrolled-courses-section">
        <h3><?php _e('You now have access to:', 'tutor-zoyktech'); ?></h3>
        
        <div class="courses-grid">
            <?php foreach ($enrolled_courses as $course_id): ?>
            <?php 
            $course = get_post($course_id);
            if (!$course) continue;
            $course_url = get_permalink($course_id);
            ?>
            <div class="course-access-card">
                <div class="course-info">
                    <h4><?php echo esc_html($course->post_title); ?></h4>
                    <p><?php echo esc_html(wp_trim_words($course->post_excerpt ?: $course->post_content, 20)); ?></p>
                </div>
                <div class="course-actions">
                    <a href="<?php echo esc_url($course_url); ?>" class="btn btn-primary">
                        🚀 <?php _e('Start Learning', 'tutor-zoyktech'); ?>
                    </a>
                    <div class="access-status">
                        <span class="status-badge active">
                            ✅ <?php _e('Full Access', 'tutor-zoyktech'); ?>
                        </span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="quick-actions">
        <div class="action-grid">
            <a href="<?php echo esc_url(tutor_utils()->tutor_dashboard_url()); ?>" class="action-card">
                <div class="action-icon">📚</div>
                <div class="action-text">
                    <strong><?php _e('My Courses', 'tutor-zoyktech'); ?></strong>
                    <span><?php _e('View all your courses', 'tutor-zoyktech'); ?></span>
                </div>
            </a>
            
            <a href="<?php echo esc_url(tutor_utils()->tutor_dashboard_url('dashboard')); ?>" class="action-card">
                <div class="action-icon">📊</div>
                <div class="action-text">
                    <strong><?php _e('Progress', 'tutor-zoyktech'); ?></strong>
                    <span><?php _e('Track your learning', 'tutor-zoyktech'); ?></span>
                </div>
            </a>
            
            <a href="<?php echo esc_url(home_url('/courses')); ?>" class="action-card">
                <div class="action-icon">🔍</div>
                <div class="action-text">
                    <strong><?php _e('More Courses', 'tutor-zoyktech'); ?></strong>
                    <span><?php _e('Discover new topics', 'tutor-zoyktech'); ?></span>
                </div>
            </a>
        </div>
    </div>

    <div class="support-info">
        <p>
            <strong><?php _e('Need Help?', 'tutor-zoyktech'); ?></strong>
            <?php _e('If you have any issues accessing your courses, please contact our support team.', 'tutor-zoyktech'); ?>
        </p>
    </div>
</div>

<style>
.zoyktech-order-completion-notice {
    max-width: 800px;
    margin: 30px auto;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 16px;
    padding: 3px;
    box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
}

.zoyktech-order-completion-notice > div {
    background: white;
    border-radius: 13px;
    padding: 30px;
}

.completion-header {
    text-align: center;
    margin-bottom: 30px;
}

.completion-icon {
    font-size: 64px;
    margin-bottom: 20px;
}

.completion-header h2 {
    margin: 0 0 12px 0;
    color: #2c3e50;
    font-size: 28px;
    font-weight: 700;
}

.completion-header p {
    margin: 0;
    color: #7f8c8d;
    font-size: 18px;
}

.enrolled-courses-section h3 {
    margin: 0 0 20px 0;
    color: #2c3e50;
    font-size: 20px;
    font-weight: 600;
}

.courses-grid {
    display: grid;
    gap: 20px;
    margin-bottom: 30px;
}

.course-access-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 24px;
    border: 2px solid #e9ecef;
    transition: all 0.3s ease;
}

.course-access-card:hover {
    border-color: #667eea;
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.course-info h4 {
    margin: 0 0 8px 0;
    color: #2c3e50;
    font-size: 18px;
    font-weight: 600;
}

.course-info p {
    margin: 0 0 16px 0;
    color: #7f8c8d;
    line-height: 1.5;
}

.course-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
}

.btn {
    padding: 12px 24px;
    text-decoration: none;
    border-radius: 8px;
    font-weight: 600;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
}

.status-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
}

.status-badge.active {
    background: #d4edda;
    color: #155724;
}

.action-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.action-card {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 12px;
    text-decoration: none;
    transition: all 0.3s ease;
    border: 2px solid transparent;
}

.action-card:hover {
    background: #e9ecef;
    border-color: #667eea;
    transform: translateY(-2px);
}

.action-icon {
    font-size: 32px;
    width: 48px;
    text-align: center;
}

.action-text strong {
    display: block;
    color: #2c3e50;
    font-size: 16px;
    margin-bottom: 4px;
}

.action-text span {
    color: #7f8c8d;
    font-size: 14px;
}

.support-info {
    background: #fff3cd;
    border: 1px solid #ffeaa7;
    border-radius: 8px;
    padding: 16px;
    text-align: center;
}

.support-info p {
    margin: 0;
    color: #856404;
}

@media (max-width: 768px) {
    .zoyktech-order-completion-notice {
        margin: 20px 10px;
    }
    
    .zoyktech-order-completion-notice > div {
        padding: 20px;
    }
    
    .completion-header h2 {
        font-size: 24px;
    }
    
    .completion-icon {
        font-size: 48px;
    }
    
    .course-actions {
        flex-direction: column;
        align-items: stretch;
    }
    
    .action-grid {
        grid-template-columns: 1fr;
    }
}
</style>