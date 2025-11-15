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
        $table_name = $wpdb->prefix . 'jobs';
        
        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM $table_name WHERE status = %s",
                $status
            )
        );
    }
}

