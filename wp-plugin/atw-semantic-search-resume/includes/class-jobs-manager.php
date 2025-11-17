<?php
/**
 * Jobs Manager Class
 * Handles job management in WordPress database
 */

if (!defined('ABSPATH')) {
    exit;
}

class ATW_Jobs_Manager {
    
    /**
     * Detect whether to use custom wp_jobs table or WordPress posts as job source.
     *
     * - If wp_jobs table exists and has rows, we keep using it (backwards compatible).
     * - Otherwise, if there are published govjob/contjob posts, we use posts+meta.
     */
    protected static function use_posts_source() {
        global $wpdb;

        // Check if wp_jobs table exists
        $jobs_table = $wpdb->prefix . 'jobs';
        $table_exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s",
                DB_NAME,
                $jobs_table
            )
        );

        if ($table_exists) {
            // If table exists and has any rows, prefer it
            $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$jobs_table}");
            if ($count > 0) {
                return false;
            }
        }

        // Fallback: use posts if there are govjob/contjob posts
        $post_types = array('govjob', 'contjob');
        $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
        $sql = "
            SELECT COUNT(*)
            FROM {$wpdb->posts}
            WHERE post_type IN ($placeholders)
              AND post_status = 'publish'
        ";

        $count = (int) $wpdb->get_var($wpdb->prepare($sql, $post_types));

        return $count > 0;
    }

    /**
     * Map a govjob/contjob post + meta to canonical job structure.
     */
    protected static function map_post_to_job($post) {
        $post_id = is_object($post) ? $post->ID : (int) $post['ID'];

        // Basic fields
        $title = is_object($post) ? $post->post_title : $post['post_title'];
        $description = is_object($post) ? $post->post_content : $post['post_content'];

        // Meta
        $meta = get_post_meta($post_id);

        $get_meta = function($key) use ($meta) {
            if (!isset($meta[$key]) || !is_array($meta[$key]) || !isset($meta[$key][0])) {
                return '';
            }
            return maybe_unserialize($meta[$key][0]);
        };

        // Company / organisation name (best-effort, common keys)
        $company_candidates = array(
            'company',
            'employer',
            'organisation',
            'organization',
            'gov_agency',
            'gov_department',
        );
        $company = '';
        foreach ($company_candidates as $key) {
            $val = trim((string) $get_meta($key));
            if ($val !== '') {
                $company = $val;
                break;
            }
        }

        // Location (best-effort)
        $location_candidates = array(
            'location',
            'job_location',
            'gov_location',
            'cont_location',
            'city',
        );
        $location = '';
        foreach ($location_candidates as $key) {
            $val = trim((string) $get_meta($key));
            if ($val !== '') {
                $location = $val;
                break;
            }
        }

        // Salary range (combine min / max if available)
        $salary_min_candidates = array('salary_min', 'gov_salary_min', 'cont_salary_min');
        $salary_max_candidates = array('salary_max', 'gov_salary_max', 'cont_salary_max');

        $salary_min = '';
        $salary_max = '';

        foreach ($salary_min_candidates as $key) {
            $val = trim((string) $get_meta($key));
            if ($val !== '') {
                $salary_min = $val;
                break;
            }
        }
        foreach ($salary_max_candidates as $key) {
            $val = trim((string) $get_meta($key));
            if ($val !== '') {
                $salary_max = $val;
                break;
            }
        }

        $salary_range = '';
        if ($salary_min !== '' && $salary_max !== '') {
            $salary_range = $salary_min . ' - ' . $salary_max;
        } elseif ($salary_min !== '') {
            $salary_range = $salary_min;
        } elseif ($salary_max !== '') {
            $salary_range = $salary_max;
        }

        // Employment type
        $employment_type = trim((string) $get_meta('employment_type'));
        if ($employment_type === '') {
            $employment_type = 'Full-time';
        }

        // Experience years
        $experience_years_raw = $get_meta('experience_years');
        $experience_years = is_numeric($experience_years_raw) ? (int) $experience_years_raw : null;

        // Skills (try a few common keys; can be comma-separated or array)
        $skills_candidates = array(
            'skills',
            'job_skills',
            'required_skills',
            'gov_skills',
            'cont_skills',
        );
        $required_skills = array();
        foreach ($skills_candidates as $key) {
            $val = $get_meta($key);
            if (empty($val)) {
                continue;
            }
            if (is_array($val)) {
                $required_skills = array_filter(array_map('trim', $val));
                break;
            }
            $parts = array_filter(array_map('trim', explode(',', (string) $val)));
            if (!empty($parts)) {
                $required_skills = $parts;
                break;
            }
        }

        // Preferred skills (optional; fall back to empty)
        $preferred_skills = array();

        return array(
            'id'               => $post_id,
            'title'            => $title,
            'company'          => $company,
            'description'      => $description,
            'required_skills'  => $required_skills,
            'preferred_skills' => $preferred_skills,
            'experience_years' => $experience_years,
            'location'         => $location,
            'salary_range'     => $salary_range,
            'employment_type'  => $employment_type,
            'status'           => 'active',
        );
    }

    /**
     * Create wp_jobs table
     */
    public static function create_jobs_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'jobs';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            company varchar(255) NOT NULL,
            description longtext NOT NULL,
            required_skills text,
            preferred_skills text,
            experience_years int(11) DEFAULT NULL,
            location varchar(255) DEFAULT NULL,
            salary_range varchar(100) DEFAULT NULL,
            employment_type varchar(50) DEFAULT 'Full-time',
            status varchar(20) DEFAULT 'active',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Get all active jobs
     */
    public static function get_jobs($status = 'active') {
        global $wpdb;
        
        if (self::use_posts_source()) {
            // Use WordPress posts (govjob / contjob) as job source
            $post_types = array('govjob', 'contjob');
            $placeholders = implode(',', array_fill(0, count($post_types), '%s'));

            // Map "active" status to "publish" for posts
            $post_status = $status === 'active' ? 'publish' : $status;

            $sql = "
                SELECT ID, post_title, post_content
                FROM {$wpdb->posts}
                WHERE post_type IN ($placeholders)
                  AND post_status = %s
                ORDER BY post_date DESC
            ";

            $prepared = $wpdb->prepare($sql, array_merge($post_types, array($post_status)));
            $posts = $wpdb->get_results($prepared);

            $jobs = array();
            foreach ($posts as $post) {
                $jobs[] = self::map_post_to_job($post);
            }

            return $jobs;
        }

        // Default: use custom wp_jobs table
        $table_name = $wpdb->prefix . 'jobs';
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE status = %s ORDER BY created_at DESC",
            $status
        );
        return $wpdb->get_results($query, ARRAY_A);
    }
    
    /**
     * Get job by ID
     */
    public static function get_job($job_id) {
        global $wpdb;
        
        if (self::use_posts_source()) {
            $post = get_post($job_id);
            if (!$post || !in_array($post->post_type, array('govjob', 'contjob'), true)) {
                return null;
            }
            return self::map_post_to_job($post);
        }

        $table_name = $wpdb->prefix . 'jobs';
        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $job_id),
            ARRAY_A
        );
    }
    
    /**
     * Insert or update job
     */
    public static function save_job($job_data) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jobs';
        
        $defaults = array(
            'title' => '',
            'company' => '',
            'description' => '',
            'required_skills' => '',
            'preferred_skills' => '',
            'experience_years' => null,
            'location' => '',
            'salary_range' => '',
            'employment_type' => 'Full-time',
            'status' => 'active',
        );
        
        $job_data = wp_parse_args($job_data, $defaults);
        
        // Convert skills arrays to comma-separated strings if needed
        if (is_array($job_data['required_skills'])) {
            $job_data['required_skills'] = implode(',', $job_data['required_skills']);
        }
        if (is_array($job_data['preferred_skills'])) {
            $job_data['preferred_skills'] = implode(',', $job_data['preferred_skills']);
        }
        
        if (isset($job_data['id']) && $job_data['id']) {
            // Update existing job
            $job_id = intval($job_data['id']);
            unset($job_data['id']);
            
            $wpdb->update(
                $table_name,
                $job_data,
                array('id' => $job_id),
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s'),
                array('%d')
            );
            
            return $job_id;
        } else {
            // Insert new job
            $wpdb->insert(
                $table_name,
                $job_data,
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
            );
            
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Delete job
     */
    public static function delete_job($job_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'jobs';
        
        return $wpdb->delete(
            $table_name,
            array('id' => $job_id),
            array('%d')
        );
    }
    
    /**
     * Get jobs count
     */
    public static function get_jobs_count($status = 'active') {
        global $wpdb;
        
        if (self::use_posts_source()) {
            $post_types = array('govjob', 'contjob');
            $placeholders = implode(',', array_fill(0, count($post_types), '%s'));
            $post_status = $status === 'active' ? 'publish' : $status;

            $sql = "
                SELECT COUNT(*)
                FROM {$wpdb->posts}
                WHERE post_type IN ($placeholders)
                  AND post_status = %s
            ";

            return (int) $wpdb->get_var($wpdb->prepare($sql, array_merge($post_types, array($post_status))));
        }

        $table_name = $wpdb->prefix . 'jobs';
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE status = %s",
                $status
            )
        );
    }
}

