<?php
/**
 * Course Access Manager - Ensures Enrolled Students Can Access All Content
 *
 * @package TutorZoyktech
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Course Access Manager Class
 */
class Tutor_Zoyktech_Course_Access_Manager {

    /**
     * Constructor
     */
    public function __construct() {
        // Only initialize if Tutor LMS is active
        if (!function_exists('tutor')) {
            return;
        }
        
        // Override Tutor LMS access checks for paid students
        add_filter('tutor_lesson_video_player', array($this, 'ensure_video_access'), 10, 2);
        add_filter('tutor_course_content_access', array($this, 'check_paid_access'), 10, 3);
        add_filter('tutor_lesson_access', array($this, 'check_lesson_access'), 10, 2);
        
        // Remove restrictions for enrolled students
        add_action('wp', array($this, 'remove_content_restrictions'));
        
        // Ensure enrollment is properly recognized
        add_filter('tutor_is_enrolled', array($this, 'verify_paid_enrollment'), 10, 3);
    }

    /**
     * Ensure video access for paid enrollments
     */
    public function ensure_video_access($player_html, $video_info) {
        if (!is_user_logged_in()) {
            return $player_html;
        }

        $lesson_id = get_the_ID();
        $course_id = tutor_utils()->get_course_id_by($lesson_id);
        $user_id = get_current_user_id();

        // Check if user has paid access
        if ($this->has_paid_course_access($course_id, $user_id)) {
            // Remove any video restrictions
            $this->unlock_video_content($lesson_id, $user_id);
            
            // Ensure player shows
            if (empty($player_html) && !empty($video_info)) {
                $player_html = $this->generate_video_player($video_info);
            }
        }

        return $player_html;
    }

    /**
     * Check if user has paid access to course
     */
    private function has_paid_course_access($course_id, $user_id) {
        // First check if enrolled
        if (!tutor_utils()->is_enrolled($course_id, $user_id)) {
            return false;
        }

        // Check for Zoyktech payment
        $enrollments = $this->get_user_course_enrollments($course_id, $user_id);
        
        foreach ($enrollments as $enrollment) {
            $is_zoyktech = get_post_meta($enrollment->ID, '_tutor_enrolled_by_zoyktech', true);
            $order_id = get_post_meta($enrollment->ID, '_tutor_enrolled_by_order', true);
            
            if ($is_zoyktech === 'yes' || !empty($order_id)) {
                // Verify order is completed
                if (!empty($order_id)) {
                    $order = wc_get_order($order_id);
                    if ($order && $order->has_status('completed')) {
                        return true;
                    }
                }
                
                // If marked as Zoyktech but no order, assume paid
                if ($is_zoyktech === 'yes') {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get user enrollments for specific course
     */
    private function get_user_course_enrollments($course_id, $user_id) {
        global $wpdb;

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->posts} 
                WHERE post_type = 'tutor_enrolled' 
                AND post_parent = %d 
                AND post_author = %d 
                AND post_status = 'completed'",
                $course_id,
                $user_id
            )
        );
    }

    /**
     * Check course content access
     */
    public function check_paid_access($has_access, $course_id, $user_id) {
        if ($has_access) {
            return true; // Already has access
        }

        // Check if user has paid access
        return $this->has_paid_course_access($course_id, $user_id);
    }

    /**
     * Check lesson access
     */
    public function check_lesson_access($has_access, $lesson_id) {
        if ($has_access) {
            return true;
        }

        if (!is_user_logged_in()) {
            return false;
        }

        $course_id = tutor_utils()->get_course_id_by($lesson_id);
        $user_id = get_current_user_id();

        return $this->has_paid_course_access($course_id, $user_id);
    }

    /**
     * Remove content restrictions for paid users
     */
    public function remove_content_restrictions() {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();

        // Check if this is a course-related page
        if (is_singular('courses') || is_singular(array('lesson', 'tutor_quiz', 'tutor_assignments'))) {
            global $post;
            
            $course_id = $post->post_type === 'courses' 
                ? $post->ID 
                : tutor_utils()->get_course_id_by($post->ID);

            if ($course_id && $this->has_paid_course_access($course_id, $user_id)) {
                // Remove all content restrictions
                remove_all_filters('tutor_lesson_video_player');
                remove_all_filters('the_content');
                
                // Add our unrestricted content filter
                add_filter('the_content', array($this, 'show_full_content'), 999);
                
                // Clear any user-specific restrictions
                delete_user_meta($user_id, "_tutor_course_restriction_{$course_id}");
            }
        }
    }

    /**
     * Show full content for paid users
     */
    public function show_full_content($content) {
        // Return content as-is for paid users
        return $content;
    }

    /**
     * Verify paid enrollment
     */
    public function verify_paid_enrollment($is_enrolled, $course_id, $user_id) {
        if ($is_enrolled) {
            return true;
        }

        // Double-check with our paid access logic
        return $this->has_paid_course_access($course_id, $user_id);
    }

    /**
     * Unlock video content
     */
    private function unlock_video_content($lesson_id, $user_id) {
        // Remove video restrictions
        delete_post_meta($lesson_id, "_tutor_video_restricted_for_{$user_id}");
        delete_user_meta($user_id, "_tutor_lesson_restriction_{$lesson_id}");
        
        // Mark video as accessible
        update_user_meta($user_id, "_tutor_video_access_{$lesson_id}", 'granted');
    }

    /**
     * Generate video player for unrestricted access
     */
    private function generate_video_player($video_info) {
        if (empty($video_info['source']) || empty($video_info['source_video_id'])) {
            return '';
        }

        $video_id = $video_info['source_video_id'];
        
        switch ($video_info['source']) {
            case 'youtube':
                return sprintf(
                    '<iframe width="100%%" height="400" src="https://www.youtube.com/embed/%s" frameborder="0" allowfullscreen></iframe>',
                    esc_attr($video_id)
                );
                
            case 'vimeo':
                return sprintf(
                    '<iframe width="100%%" height="400" src="https://player.vimeo.com/video/%s" frameborder="0" allowfullscreen></iframe>',
                    esc_attr($video_id)
                );
                
            case 'html5':
                if (filter_var($video_id, FILTER_VALIDATE_URL)) {
                    return sprintf(
                        '<video width="100%%" height="400" controls><source src="%s" type="video/mp4">Your browser does not support the video tag.</video>',
                        esc_url($video_id)
                    );
                }
                break;
        }

        return '';
    }

    /**
     * Grant full course access to user
     */
    public function grant_full_course_access($course_id, $user_id) {
        // Get all course content
        $content_items = tutor_utils()->get_course_content_list($course_id);
        
        if (!empty($content_items)) {
            foreach ($content_items as $item) {
                $this->grant_content_access($item->ID, $user_id);
                
                // Handle topics within lessons
                if ($item->post_type === 'lesson') {
                    $topics = tutor_utils()->get_topics_by_lesson($item->ID);
                    
                    if (!empty($topics)) {
                        foreach ($topics as $topic) {
                            $this->grant_content_access($topic->ID, $user_id);
                        }
                    }
                }
            }
        }

        // Mark course as fully accessible
        update_user_meta($user_id, "_tutor_full_access_{$course_id}", 'granted');
        
        error_log("ZOYKTECH_ACCESS: Granted full access to course {$course_id} for user {$user_id}");
    }

    /**
     * Grant access to specific content item
     */
    private function grant_content_access($content_id, $user_id) {
        // Remove restrictions
        delete_post_meta($content_id, "_tutor_restricted_for_{$user_id}");
        delete_user_meta($user_id, "_tutor_content_restriction_{$content_id}");
        
        // Grant explicit access
        update_user_meta($user_id, "_tutor_content_access_{$content_id}", 'granted');
        update_user_meta($user_id, "_tutor_video_access_{$content_id}", 'granted');
    }
}

// Initialize course access manager
new Tutor_Zoyktech_Course_Access_Manager();