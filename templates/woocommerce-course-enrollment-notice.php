<?php
/**
 * Course Enrollment Notice for WooCommerce Orders
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// This template can be overridden by copying it to your theme's woocommerce folder
?>

<div class="woocommerce-course-enrollment-notice">
    <div class="enrollment-success-message">
        <div class="enrollment-icon">🎓</div>
        <div class="enrollment-content">
            <h3><?php _e('Course Access Granted!', 'tutor-zoyktech'); ?></h3>
            <p><?php _e('You have been automatically enrolled in your purchased courses.', 'tutor-zoyktech'); ?></p>
            
            <?php if (!empty($enrolled_courses)): ?>
            <div class="enrolled-courses-list">
                <h4><?php _e('Your Courses:', 'tutor-zoyktech'); ?></h4>
                <ul>
                    <?php foreach ($enrolled_courses as $course_id): ?>
                    <li>
                        <a href="<?php echo get_permalink($course_id); ?>" class="course-link">
                            <?php echo get_the_title($course_id); ?>
                        </a>
                        <span class="course-action">
                            <a href="<?php echo get_permalink($course_id); ?>" class="button button-small">
                                <?php _e('Start Learning', 'tutor-zoyktech'); ?>
                            </a>
                        </span>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>
            
            <div class="enrollment-actions">
                <a href="<?php echo esc_url(tutor_utils()->tutor_dashboard_url()); ?>" class="button button-primary">
                    <?php _e('Go to Dashboard', 'tutor-zoyktech'); ?>
                </a>
                <a href="<?php echo esc_url(home_url('/courses')); ?>" class="button">
                    <?php _e('Browse More Courses', 'tutor-zoyktech'); ?>
                </a>
            </div>
        </div>
    </div>
</div>

<style>
.woocommerce-course-enrollment-notice {
    margin: 20px 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 12px;
    padding: 2px;
}

.enrollment-success-message {
    background: white;
    border-radius: 10px;
    padding: 24px;
    text-align: center;
}

.enrollment-icon {
    font-size: 48px;
    margin-bottom: 16px;
}

.enrollment-content h3 {
    margin: 0 0 12px 0;
    color: #2c3e50;
    font-size: 24px;
}

.enrollment-content p {
    margin: 0 0 20px 0;
    color: #7f8c8d;
    font-size: 16px;
}

.enrolled-courses-list {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
    text-align: left;
}

.enrolled-courses-list h4 {
    margin: 0 0 12px 0;
    color: #2c3e50;
}

.enrolled-courses-list ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.enrolled-courses-list li {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid #dee2e6;
}

.enrolled-courses-list li:last-child {
    border-bottom: none;
}

.course-link {
    font-weight: 600;
    color: #2c3e50;
    text-decoration: none;
    flex: 1;
}

.course-link:hover {
    color: #667eea;
}

.enrollment-actions {
    margin-top: 20px;
}

.enrollment-actions .button {
    margin: 0 8px;
    padding: 12px 24px;
    text-decoration: none;
    border-radius: 6px;
    font-weight: 600;
    display: inline-block;
}

.enrollment-actions .button-primary {
    background: #667eea;
    color: white;
}

.enrollment-actions .button-primary:hover {
    background: #5a6fd8;
}

.button-small {
    padding: 6px 12px !important;
    font-size: 14px !important;
}

@media (max-width: 768px) {
    .enrolled-courses-list li {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
    
    .enrollment-actions .button {
        display: block;
        margin: 8px 0;
        text-align: center;
    }
}
</style>