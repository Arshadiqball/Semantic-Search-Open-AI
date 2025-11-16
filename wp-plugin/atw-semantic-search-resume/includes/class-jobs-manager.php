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
     * Resolve jobs table and column mapping from plugin settings.
     *
     * Returns an array with keys:
     *  - table
     *  - id, title, company, description, required_skills, preferred_skills,
     *    experience_years, location, salary_range, employment_type, status, status_active
     */
    protected static function get_schema() {
        global $wpdb;

        // Default schema for plugin-managed table wp_jobs
        $schema = array(
            'table'           => $wpdb->prefix . 'jobs',
            'id'              => 'id',
            'title'           => 'title',
            'company'         => 'company',
            'description'     => 'description',
            'required_skills' => 'required_skills',
            'preferred_skills'=> 'preferred_skills',
            'experience_years'=> 'experience_years',
            'location'        => 'location',
            'salary_range'    => 'salary_range',
            'employment_type' => 'employment_type',
            'status'          => 'status',
            'status_active'   => 'active',
        );

        if ( class_exists( 'ATW_Semantic_Search_Resume' ) ) {
            $plugin = ATW_Semantic_Search_Resume::get_instance();
            if ( method_exists( $plugin, 'get_jobs_schema' ) ) {
                $configured = $plugin->get_jobs_schema();
                if ( is_array( $configured ) ) {
                    $schema = array_merge( $schema, $configured );
                }
            }
        }

        return $schema;
    }

    /**
     * Create default wp_jobs table (used only when no custom table is configured)
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
            KEY idx_comp (company),
            KEY idx_created_at (created_at)
        ) $charset_collate;";
        
        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );
    }
    
    /**
     * Get all active jobs
     */
    public static function get_jobs( $status = 'active' ) {
        global $wpdb;

        $schema = self::get_schema();
        $table  = esc_sql( $schema['table'] );

        $select_parts = array();
        $select_parts[] = sprintf( '%s AS id', esc_sql( $schema['id'] ) );
        $select_parts[] = $schema['title']           ? sprintf( '%s AS title', esc_sql( $schema['title'] ) ) : \"'' AS title\";
        $select_parts[] = $schema['company']         ? sprintf( '%s AS company', esc_sql( $schema['company'] ) ) : \"'' AS company\";
        $select_parts[] = $schema['description']     ? sprintf( '%s AS description', esc_sql( $schema['description'] ) ) : \"'' AS description\";
        $select_parts[] = $schema['required_skills'] ? sprintf( '%s AS required_skills', esc_sql( $schema['required_skills'] ) ) : \"'' AS required_skills\";
        $select_parts[] = $schema['preferred_skills']? sprintf( '%s AS preferred_skills', esc_sql( $schema['preferred_skills'] ) ) : \"'' AS preferred_skills\";
        $select_parts[] = $schema['experience_years']? sprintf( '%s AS experience_years', esc_sql( $schema['experience_years'] ) ) : 'NULL AS experience_years';
        $select_parts[] = $schema['location']        ? sprintf( '%s AS location', esc_sql( $schema['location'] ) ) : \"'' AS location\";
        $select_parts[] = $schema['salary_range']    ? sprintf( '%s AS salary_range', esc_sql( $schema['salary_range'] ) ) : \"'' AS salary_range\";
        $select_parts[] = $schema['employment_type'] ? sprintf( '%s AS employment_type', esc_sql( $schema['employment_type'] ) ) : \"'' AS employment_type\";

        $where_clauses = array();
        $params = array();

        if ( ! empty( $schema['status'] ) && $status !== '' ) {
            $where_clauses[] = sprintf( '%s = %%s', esc_sql( $schema['status'] ) );
            $params[] = $status;
        }

        $where_sql = '';
        if ( ! empty( $where_clauses ) ) {
            $where_sql = 'WHERE ' . implode( ' AND ', $where_clauses );
        }

        $order_col = ! empty( $schema['id'] ) ? esc_sql( $schema['id'] ) : '1';

        $sql = sprintf(
            'SELECT %s FROM %s %s ORDER BY %s DESC',
            implode( \",\\n       \", $select_parts ),
            $table,
            $where_sql,
            $order_col
        );

        if ( ! empty( $params ) ) {
            $query = $wpdb->prepare( $sql, $params );
        } else {
            $query = $sql;
        }

        return $wpdb->get_results( $query, ARRAY_A );
    }
    
    /**
     * Get job by ID
     */
    public static function get_job( $job_id ) {
        global $wpdb;

        $schema = self::get_schema();
        $table  = esc_sql( $schema['table'] );
        $id_col = esc_sql( $schema['id'] );

        $sql = "SELECT * FROM $table WHERE $id_col = %s LIMIT 1";

        return $wpdb->get_row(
            $wpdb->prepare( $sql, $job_id ),
            ARRAY_A
        );
    }
    
    /**
     * Insert or update job
     */
    public static function save_job( $job_data ) {
        global $wpdb;

        $schema = self::get_schema();
        $table  = esc_sql( $schema['table'] );

        $defaults = array(
            'id'              => null,
            'title'           => '',
            'company'         => '',
            'description'     => '',
            'required_skills' => '',
            'preferred_skills'=> '',
            'experience_years'=> null,
            'location'        => '',
            'salary_range'    => '',
            'employment_type' => 'Full-time',
            'status'          => 'active',
        );

        $data = wp_parse_args( $job_data, $defaults );

        $column_map = array(
            'id'              => $schema['id'],
            'title'           => $schema['title'],
            'company'         => $schema['company'],
            'description'     => $schema['description'],
            'required_skills' => $schema['required_skills'],
            'preferred_skills'=> $schema['preferred_skills'],
            'experience_years'=> $schema['experience_years'],
            'location'        => $schema['location'],
            'salary_range'    => $schema['salary_range'],
            'employment_type' => $schema['employment_type'],
            'status'          => $schema['status'],
        );

        $insert_data    = array();
        $insert_formats = array();
        $update_data    = array();
        $where          = array();

        foreach ( $column_map as $logical => $column ) {
            if ( empty( $column ) ) {
                continue;
            }

            if ( $logical === 'id' ) {
                if ( ! empty( $data['id'] ) ) {
                    $where[ $column ] = $data['id'];
                }
                continue;
            }

            if ( array_key_exists( $logical, $data ) ) {
                $value = $data[ $logical ];

                if ( in_array( $logical, array( 'required_skills', 'preferred_skills' ), true ) && is_array( $value ) ) {
                    $value = implode( ',', $value );
                }

                $insert_data[ $column ] = $value;
                $insert_formats[]       = ( $logical === 'experience_years' ) ? '%d' : '%s';

                if ( ! empty( $where ) ) {
                    $update_data[ $column ] = $value;
                }
            }
        }

        if ( ! empty( $where ) ) {
            $wpdb->update(
                $table,
                $update_data,
                $where
            );

            return (int) $data['id'];
        }

        $wpdb->insert(
            $table,
            $insert_data,
            $insert_formats
        );

        return (int) $wpdb->insert_id;
    }
    
    /**
     * Delete job
     */
    public static function delete_job( $job_id ) {
        global $wpdb;

        $schema = self::get_schema();
        $table  = esc_sql( $schema['table'] );
        $id_col = esc_sql( $schema['id'] );

        return $wpdb->delete(
            $table,
            array( $id_col => $job_id ),
            array( '%s' )
        );
    }
    
    /**
     * Get jobs count
     */
    public static function get_jobs_count( $status = 'active' ) {
        global $wpdb;

        $schema = self::get_schema();
        $table  = esc_sql( $schema['table'] );
        $status_col = $schema['status'];

        if ( ! empty( $status_col ) && $status !== '' ) {
            return (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE {$status_col} = %s",
                    $status
                )
            );
        }

        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
    }
}

