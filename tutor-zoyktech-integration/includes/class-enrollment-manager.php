<?php
/**
 * Enrollment Manager for Tutor LMS Zoyktech Integration
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enrollment Manager Class
 */
class Tutor_Zoyktech_Enrollment_Manager {

    /**
     * Enroll user in course after successful payment
     */
    public function enroll_user($course_id, $user_id) {
        // Validate inputs
        if (empty($course_id) || empty($user_id)) {
            return false;
        }

        // Check if user is already enrolled
        if (tutor_utils()->is_enrolled($course_id, $user_id)) {
            return true; // Already enrolled
        }

        // Check if course exists
        $course = get_post($course_id);
        if (!$course || $course->post_type !== 'courses') {
            return false;
        }

        // Enroll user using Tutor LMS function
        $enrollment_result = tutor_utils()->do_enroll($course_id, 0, $user_id);

        if ($enrollment_result) {
            // Add enrollment meta data
            $this->add_enrollment_meta($course_id, $user_id);

            // Trigger enrollment actions
            do_action('tutor_zoyktech_after_enrollment', $course_id, $user_id);

            // Log enrollment
            error_log("TUTOR_ZOYKTECH: User $user_id enrolled in course $course_id");

            return true;
        }

        return false;
    }

    /**
     * Add enrollment meta data
     */
    private function add_enrollment_meta($course_id, $user_id) {
        global $wpdb;

        // Get enrollment ID
        $enrollment_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'tutor_enrolled' 
                AND post_parent = %d 
                AND post_author = %d",
                $course_id,
                $user_id
            )
        );

        if ($enrollment_id) {
            // Add meta to indicate this was a paid enrollment via Zoyktech
            add_post_meta($enrollment_id, '_tutor_zoyktech_paid_enrollment', 'yes');
            add_post_meta($enrollment_id, '_tutor_zoyktech_enrollment_date', current_time('mysql'));
        }
    }

    /**
     * Check if user has paid access to course
     */
    public function has_paid_access($course_id, $user_id) {
        global $wpdb;

        // Check if user is enrolled
        if (!tutor_utils()->is_enrolled($course_id, $user_id)) {
            return false;
        }

        // Check if it was a paid enrollment
        $enrollment_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'tutor_enrolled' 
                AND post_parent = %d 
                AND post_author = %d",
                $course_id,
                $user_id
            )
        );

        if ($enrollment_id) {
            $is_paid = get_post_meta($enrollment_id, '_tutor_zoyktech_paid_enrollment', true);
            return $is_paid === 'yes';
        }

        return false;
    }

    /**
     * Get user's enrolled courses with payment info
     */
    public function get_user_paid_courses($user_id) {
        global $wpdb;

        $courses = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT p.ID as course_id, p.post_title, e.post_date as enrollment_date,
                        pm.meta_value as is_paid_enrollment
                FROM {$wpdb->posts} e
                INNER JOIN {$wpdb->posts} p ON e.post_parent = p.ID
                LEFT JOIN {$wpdb->postmeta} pm ON e.ID = pm.post_id AND pm.meta_key = '_tutor_zoyktech_paid_enrollment'
                WHERE e.post_type = 'tutor_enrolled'
                AND e.post_author = %d
                AND p.post_status = 'publish'
                ORDER BY e.post_date DESC",
                $user_id
            )
        );

        return $courses;
    }

    /**
     * Get course enrollment statistics
     */
    public function get_course_enrollment_stats($course_id) {
        global $wpdb;

        // Total enrollments
        $total_enrollments = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} 
                WHERE post_type = 'tutor_enrolled' 
                AND post_parent = %d",
                $course_id
            )
        );

        // Paid enrollments via Zoyktech
        $paid_enrollments = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->posts} e
                INNER JOIN {$wpdb->postmeta} pm ON e.ID = pm.post_id
                WHERE e.post_type = 'tutor_enrolled' 
                AND e.post_parent = %d
                AND pm.meta_key = '_tutor_zoyktech_paid_enrollment'
                AND pm.meta_value = 'yes'",
                $course_id
            )
        );

        // Revenue from Zoyktech payments
        $revenue = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT SUM(amount) FROM {$wpdb->prefix}tutor_zoyktech_transactions 
                WHERE course_id = %d 
                AND status = 'completed'",
                $course_id
            )
        );

        return array(
            'total_enrollments' => intval($total_enrollments),
            'paid_enrollments' => intval($paid_enrollments),
            'free_enrollments' => intval($total_enrollments) - intval($paid_enrollments),
            'revenue' => floatval($revenue)
        );
    }

    /**
     * Unenroll user from course (for refunds)
     */
    public function unenroll_user($course_id, $user_id) {
        global $wpdb;

        // Find enrollment post
        $enrollment_id = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                WHERE post_type = 'tutor_enrolled' 
                AND post_parent = %d 
                AND post_author = %d",
                $course_id,
                $user_id
            )
        );

        if ($enrollment_id) {
            // Delete enrollment post
            $result = wp_delete_post($enrollment_id, true);

            if ($result) {
                // Trigger unenrollment actions
                do_action('tutor_zoyktech_after_unenrollment', $course_id, $user_id);

                // Log unenrollment
                error_log("TUTOR_ZOYKTECH: User $user_id unenrolled from course $course_id");

                return true;
            }
        }

        return false;
    }

    /**
     * Check if course allows enrollment
     */
    public function can_enroll_in_course($course_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        // Check if user is logged in
        if (!$user_id) {
            return false;
        }

        // Check if course exists and is published
        $course = get_post($course_id);
        if (!$course || $course->post_status !== 'publish') {
            return false;
        }

        // Check if user is already enrolled
        if (tutor_utils()->is_enrolled($course_id, $user_id)) {
            return false;
        }

        // Check enrollment limit
        $enrollment_limit = get_post_meta($course_id, '_tutor_course_max_students', true);
        if (!empty($enrollment_limit)) {
            $current_enrollments = tutor_utils()->count_enrolled_users_by_course($course_id);
            if ($current_enrollments >= $enrollment_limit) {
                return false;
            }
        }

        // Check enrollment deadline
        $enrollment_deadline = get_post_meta($course_id, '_tutor_course_enrollment_deadline', true);
        if (!empty($enrollment_deadline)) {
            $deadline_timestamp = strtotime($enrollment_deadline);
            if (time() > $deadline_timestamp) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get enrollment requirements for course
     */
    public function get_enrollment_requirements($course_id) {
        $requirements = array();

        // Check if course has a price
        $course_price = get_post_meta($course_id, '_tutor_course_price', true);
        if (!empty($course_price) && $course_price > 0) {
            $requirements['payment'] = array(
                'required' => true,
                'amount' => $course_price,
                'currency' => tutor_utils()->get_option('zoyktech_currency', 'ZMW')
            );
        }

        // Check prerequisites
        $prerequisites = get_post_meta($course_id, '_tutor_course_prerequisites', true);
        if (!empty($prerequisites)) {
            $requirements['prerequisites'] = $prerequisites;
        }

        // Check enrollment limit
        $enrollment_limit = get_post_meta($course_id, '_tutor_course_max_students', true);
        if (!empty($enrollment_limit)) {
            $current_enrollments = tutor_utils()->count_enrolled_users_by_course($course_id);
            $requirements['enrollment_limit'] = array(
                'limit' => intval($enrollment_limit),
                'current' => intval($current_enrollments),
                'available' => max(0, intval($enrollment_limit) - intval($current_enrollments))
            );
        }

        // Check enrollment deadline
        $enrollment_deadline = get_post_meta($course_id, '_tutor_course_enrollment_deadline', true);
        if (!empty($enrollment_deadline)) {
            $requirements['deadline'] = array(
                'date' => $enrollment_deadline,
                'timestamp' => strtotime($enrollment_deadline),
                'expired' => time() > strtotime($enrollment_deadline)
            );
        }

        return $requirements;
    }
}

