<?php
/**
 * Plugin Name: ATW Semantic Search Resume
 * Plugin URI: https://atwebtechnologies.com
 * Description: Semantic job matching system with resume upload and AI-powered job recommendations
 * Version: 1.0.0
 * Author: AT Web Technologies
 * Author URI: https://atwebtechnologies.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: atw-semantic-search
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ATW_SEMANTIC_VERSION', '1.0.0');
define('ATW_SEMANTIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ATW_SEMANTIC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ATW_SEMANTIC_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Default API endpoint (can be changed in settings)
define('ATW_SEMANTIC_API_BASE', 'https://54.183.65.104:3002');

/**
 * Main plugin class
 */
class ATW_Semantic_Search_Resume {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Load required classes
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        require_once(plugin_dir_path(__FILE__) . 'includes/class-jobs-manager.php');
    }
    
    private function init_hooks() {
        // Activation/Deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Admin hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // Frontend hooks
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_shortcode('atw_semantic_job_search', array($this, 'render_job_search_shortcode'));
        add_shortcode('atw_semantic_profile', array($this, 'render_profile_shortcode'));
        
        // AJAX handlers
        add_action('wp_ajax_atw_upload_resume', array($this, 'handle_resume_upload'));
        add_action('wp_ajax_nopriv_atw_upload_resume', array($this, 'handle_resume_upload'));
        add_action('wp_ajax_atw_get_jobs', array($this, 'handle_get_jobs'));
        add_action('wp_ajax_nopriv_atw_get_jobs', array($this, 'handle_get_jobs'));
        add_action('wp_ajax_atw_save_profile', array($this, 'handle_save_profile'));
        add_action('wp_ajax_atw_get_profile_jobs', array($this, 'handle_get_profile_jobs'));
        add_action('wp_ajax_atw_generate_dummy_jobs', array($this, 'handle_generate_dummy_jobs'));
        add_action('wp_ajax_atw_sync_wordpress_jobs', array($this, 'handle_sync_wordpress_jobs'));
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create custom database table for plugin settings
        $this->create_settings_table();
        
        // Create wp_jobs table
        require_once(plugin_dir_path(__FILE__) . 'includes/class-jobs-manager.php');
        ATW_Jobs_Manager::create_jobs_table();

        // Create profiles table for per-user preferences and resume linkage
        $this->create_profiles_table();
        
        // Flush rewrite rules if needed
        flush_rewrite_rules();
    }
    
    /**
     * Create custom database table for plugin settings
     */
    private function create_settings_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'atw_semantic_settings';
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create profiles table (per-user preferences and resume mapping)
     */
    private function create_profiles_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'atw_semantic_profiles';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id bigint(20) UNSIGNED NOT NULL,
            resume_id bigint(20) DEFAULT NULL,
            job_categories longtext NULL,
            tech_stack longtext NULL,
            focus varchar(50) DEFAULT NULL,
            transition_stage varchar(50) DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
    
    /**
     * Get setting value from custom table
     */
    public function get_setting($key, $default = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atw_semantic_settings';
        
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT setting_value FROM $table_name WHERE setting_key = %s",
            $key
        ));
        
        if ($value === null) {
            return $default;
        }
        
        // Try to decode JSON, return as-is if not JSON
        $decoded = json_decode($value, true);
        return (json_last_error() === JSON_ERROR_NONE) ? $decoded : $value;
    }
    
    /**
     * Save setting value to custom table
     */
    public function save_setting($key, $value) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atw_semantic_settings';
        
        // Encode arrays/objects as JSON
        if (is_array($value) || is_object($value)) {
            $value = json_encode($value);
        }
        
        $wpdb->replace(
            $table_name,
            array(
                'setting_key'  => $key,
                'setting_value'=> $value,
            ),
            array('%s', '%s')
        );
    }
    
    /**
     * Delete setting from custom table
     */
    public function delete_setting($key) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'atw_semantic_settings';
        
        $wpdb->delete(
            $table_name,
            array('setting_key' => $key),
            array('%s')
        );
    }

    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Optionally deactivate client on API side
        // For now, we'll just clean up local data if needed
    }
    
    /**
     * Register this WordPress site with the Node.js API
     */
    public function register_with_api() {
        $api_base = $this->get_setting('api_base_url');
        if (empty($api_base)) {
            $api_base = ATW_SEMANTIC_API_BASE;
        }
        $site_name = get_bloginfo('name');
        $site_url = get_site_url();
        
        // Get IP address
        $ip_address = $this->get_client_ip();
        
        // Get admin email
        $admin_email = get_option('admin_email');
        
        // Get WordPress database credentials
        global $wpdb;
        $db_host = DB_HOST;
        $db_port = 3307; // Default to Docker mapped port
        
        // Extract port from DB_HOST if present
        if (strpos($db_host, ':') !== false) {
            $parts = explode(':', $db_host);
            $db_host = $parts[0];
            $db_port = isset($parts[1]) ? intval($parts[1]) : 3307;
        }
        
        // Handle Docker service names - convert to localhost for host access
        if (in_array($db_host, array('db', 'mysql', 'mariadb'))) {
            $db_host = 'localhost';
            // If port is 3306 (internal), use 3307 (Docker mapped port)
            if ($db_port == 3306) {
                $db_port = 3307;
            }
        }

        // Prepare database configuration
        $db_config = array(
            'db_host' => $db_host,
            'db_port' => $db_port,
            'db_name' => DB_NAME,
            'db_user' => DB_USER,
            'db_password' => DB_PASSWORD,
            'table_prefix' => $wpdb->prefix,
        );
        
        // Load Jobs Manager class
        require_once(plugin_dir_path(__FILE__) . 'includes/class-jobs-manager.php');
        
        // Fetch all jobs from WordPress database to send to Node.js server
        $jobs = array();
        try {
            $wp_jobs = ATW_Jobs_Manager::get_jobs();
            if ($wp_jobs && is_array($wp_jobs)) {
                foreach ($wp_jobs as $wp_job) {
                    // Handle both object and array formats from get_jobs()
                    $job_id = is_array($wp_job) ? $wp_job['id'] : $wp_job->id;
                    $job_title = is_array($wp_job) ? $wp_job['title'] : $wp_job->title;
                    $job_company = is_array($wp_job) ? $wp_job['company'] : $wp_job->company;
                    $job_description = is_array($wp_job) ? $wp_job['description'] : $wp_job->description;
                    $job_required_skills = is_array($wp_job) ? $wp_job['required_skills'] : $wp_job->required_skills;
                    $job_preferred_skills = is_array($wp_job) ? $wp_job['preferred_skills'] : $wp_job->preferred_skills;
                    $job_experience_years = is_array($wp_job) ? $wp_job['experience_years'] : $wp_job->experience_years;
                    $job_location = is_array($wp_job) ? $wp_job['location'] : $wp_job->location;
                    $job_salary_range = is_array($wp_job) ? $wp_job['salary_range'] : $wp_job->salary_range;
                    $job_employment_type = is_array($wp_job) ? $wp_job['employment_type'] : $wp_job->employment_type;
                    
                    // Format job for Node.js server
                    $jobs[] = array(
                        'id' => $job_id,
                        'title' => $job_title,
                        'company' => $job_company,
                        'description' => $job_description,
                        'required_skills' => is_array($job_required_skills) 
                            ? $job_required_skills 
                            : (!empty($job_required_skills) ? explode(',', $job_required_skills) : array()),
                        'preferred_skills' => is_array($job_preferred_skills) 
                            ? $job_preferred_skills 
                            : (!empty($job_preferred_skills) ? explode(',', $job_preferred_skills) : array()),
                        'experience_years' => $job_experience_years,
                        'location' => $job_location,
                        'salary_range' => $job_salary_range,
                        'employment_type' => $job_employment_type ? $job_employment_type : 'Full-time',
                    );
                }
            }
        } catch (Exception $e) {
            error_log('ATW Semantic: Error fetching jobs during registration - ' . $e->getMessage());
            // Continue registration even if jobs fail to fetch
        }
        
        // Register with API (including database configuration and jobs)
        $response = wp_remote_post($api_base . '/api/admin/clients', array(
            'body' => json_encode(array(
                'name' => $site_name . ' (' . $site_url . ')',
                'apiUrl' => $site_url,
                'db_config' => $db_config,
                'jobs' => $jobs, // Send all WordPress jobs
            )),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 60, // Increased timeout for job processing
            'sslverify' => false, // Set to false for self-signed certificates, true for valid SSL
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('ATW Semantic: Failed to register with API - ' . $error_message);
            
            // Check if it's a connection error and provide helpful message
            if (strpos($error_message, 'Failed to connect') !== false || strpos($error_message, 'Could not connect') !== false) {
                $helpful_message = $error_message . ' Make sure the Node.js API is running on ' . $api_base;
                $this->save_setting('registration_error', $helpful_message);
            } else {
                $this->save_setting('registration_error', $error_message);
            }
            return false;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            error_log('ATW Semantic: API registration failed with status ' . $response_code . ' - ' . $body);
            $this->save_setting('registration_error', 'HTTP ' . $response_code . ': ' . substr($body, 0, 200));
            return false;
        }
        
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ATW Semantic: Invalid JSON response from API - ' . json_last_error_msg());
            $this->save_setting('registration_error', 'Invalid response from API');
            return false;
        }
        
        if (isset($data['success']) && $data['success'] && isset($data['client'])) {
            $client = $data['client'];
            
            // Validate client data
            if (empty($client['clientId']) || empty($client['apiKey'])) {
                error_log('ATW Semantic: Invalid client data received from API');
                $this->save_setting('registration_error', 'Invalid client data received');
                return false;
            }
            
            // Store client credentials
            $this->save_setting('client_id', sanitize_text_field($client['clientId']));
            $this->save_setting('api_key', sanitize_text_field($client['apiKey']));
            $this->save_setting('is_registered', true);
            
            // Store IP and email for analytics
            $this->save_setting('registration_ip', sanitize_text_field($ip_address));
            $this->save_setting('registration_email', sanitize_email($admin_email));
            
            // Clear any previous errors
            $this->delete_setting('registration_error');
            
            return true;
        }
        
        $error_msg = isset($data['message']) ? $data['message'] : 'Unknown error';
        error_log('ATW Semantic: API registration failed - ' . $error_msg);
        $this->save_setting('registration_error', $error_msg);
        return false;
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip_keys = array(
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown';
    }
    
    /**
     * Add admin menu
     * Top-level: ATW
     * Sub-menus: Search Settings, Analytics, Map Structure
     */
    public function add_admin_menu() {
        $parent_slug = 'atw-semantic';
        
        // Top-level menu: ATW
        add_menu_page(
            __('ATW Semantic Search', 'atw-semantic-search'),
            __('ATW', 'atw-semantic-search'),
            'manage_options',
            $parent_slug,
            array($this, 'render_admin_page'),
            'dashicons-search',
            30
        );
        
        // Sub-menu: Search Settings
        add_submenu_page(
            $parent_slug,
            __('Search Settings', 'atw-semantic-search'),
            __('Search Settings', 'atw-semantic-search'),
            'manage_options',
            $parent_slug,
            array($this, 'render_admin_page')
        );
        
        // Sub-menu: Analytics
        add_submenu_page(
            $parent_slug,
            __('Analytics', 'atw-semantic-search'),
            __('Analytics', 'atw-semantic-search'),
            'manage_options',
            'atw-semantic-analytics',
            array($this, 'render_analytics_page')
        );

        // Sub-menu: Map Structure (jobs table/columns mapping)
        add_submenu_page(
            $parent_slug,
            __('Map Structure', 'atw-semantic-search'),
            __('Map Structure', 'atw-semantic-search'),
            'manage_options',
            'atw-semantic-map-structure',
            array($this, 'render_map_structure_page')
        );
    }
    
    /**
     * Register settings
     * Note: Settings are now stored in custom database table, not WordPress options
     */
    public function register_settings() {
        // Settings are stored in custom database table (wp_atw_semantic_settings)
        // This function is kept for compatibility but doesn't register WordPress options
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Load assets on all ATW Semantic admin pages (settings + analytics)
        if (strpos($hook, 'atw-semantic') === false) {
            return;
        }

        wp_enqueue_style('atw-semantic-admin', ATW_SEMANTIC_PLUGIN_URL . 'assets/admin.css', array(), ATW_SEMANTIC_VERSION);
        wp_enqueue_script('atw-semantic-admin', ATW_SEMANTIC_PLUGIN_URL . 'assets/admin.js', array('jquery'), ATW_SEMANTIC_VERSION, true);
    }
    
    /**
     * Enqueue frontend scripts
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('atw-semantic-frontend', ATW_SEMANTIC_PLUGIN_URL . 'assets/frontend.css', array(), ATW_SEMANTIC_VERSION);
        wp_enqueue_script('atw-semantic-frontend', ATW_SEMANTIC_PLUGIN_URL . 'assets/frontend.js', array('jquery'), ATW_SEMANTIC_VERSION, true);
        
        // Localize script with settings (frontend needs fallback defaults)
        $api_base = $this->get_setting('api_base_url');
        $threshold = $this->get_setting('threshold');
        $recommended_jobs = $this->get_setting('recommended_jobs_count');
        
        wp_localize_script('atw-semantic-frontend', 'atwSemantic', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'apiBase' => !empty($api_base) ? $api_base : ATW_SEMANTIC_API_BASE,
            'threshold' => !empty($threshold) ? $threshold : 0.5,
            'recommendedJobsCount' => !empty($recommended_jobs) ? $recommended_jobs : 10,
            'nonce' => wp_create_nonce('atw_semantic_nonce'),
        ));
    }
    
    /**
     * Render admin settings page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        include ATW_SEMANTIC_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    /**
     * Render analytics page inside WordPress admin
     */
    public function render_analytics_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        include ATW_SEMANTIC_PLUGIN_DIR . 'templates/admin-analytics.php';
    }

    /**
     * Render Map Structure page (jobs table / column mapping)
     */
    public function render_map_structure_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;

        $notice = '';

        if (isset($_POST['atw_semantic_save_mapping']) && check_admin_referer('atw_semantic_map_structure_nonce')) {
            // Save selected table
            if (!empty($_POST['atw_jobs_table_name'])) {
                $this->save_setting('jobs_table_name', sanitize_text_field($_POST['atw_jobs_table_name']));
            } else {
                $this->delete_setting('jobs_table_name');
            }

            // Save column mappings
            $mapping_fields = array(
                'jobs_col_id',
                'jobs_col_title',
                'jobs_col_company',
                'jobs_col_description',
                'jobs_col_required_skills',
                'jobs_col_preferred_skills',
                'jobs_col_experience_years',
                'jobs_col_location',
                'jobs_col_salary_range',
                'jobs_col_employment_type',
                'jobs_col_status',
            );

            foreach ($mapping_fields as $field_key) {
                if (isset($_POST[$field_key]) && $_POST[$field_key] !== '') {
                    $this->save_setting($field_key, sanitize_text_field(wp_unslash($_POST[$field_key])));
                } else {
                    $this->delete_setting($field_key);
                }
            }

            // Save active status value
            if (isset($_POST['atw_jobs_status_active_value']) && $_POST['atw_jobs_status_active_value'] !== '') {
                $this->save_setting('jobs_status_active_value', sanitize_text_field($_POST['atw_jobs_status_active_value']));
            } else {
                $this->delete_setting('jobs_status_active_value');
            }

            $notice = __('Mapping saved successfully.', 'atw-semantic-search');
        }

        // Load current mapping
        $jobs_table_name      = $this->get_setting('jobs_table_name', $wpdb->prefix . 'jobs');
        $jobs_col_id          = $this->get_setting('jobs_col_id', 'id');
        $jobs_col_title       = $this->get_setting('jobs_col_title', 'title');
        $jobs_col_company     = $this->get_setting('jobs_col_company', 'company');
        $jobs_col_description = $this->get_setting('jobs_col_description', 'description');
        $jobs_col_required    = $this->get_setting('jobs_col_required_skills', 'required_skills');
        $jobs_col_preferred   = $this->get_setting('jobs_col_preferred_skills', 'preferred_skills');
        $jobs_col_experience  = $this->get_setting('jobs_col_experience_years', 'experience_years');
        $jobs_col_location    = $this->get_setting('jobs_col_location', 'location');
        $jobs_col_salary      = $this->get_setting('jobs_col_salary_range', 'salary_range');
        $jobs_col_employment  = $this->get_setting('jobs_col_employment_type', 'employment_type');
        $jobs_col_status      = $this->get_setting('jobs_col_status', 'status');
        $jobs_status_active   = $this->get_setting('jobs_status_active_value', 'active');

        // Get list of tables
        $tables = $wpdb->get_col('SHOW TABLES');

        // Get columns for selected table
        $columns = array();
        if (!empty($jobs_table_name)) {
            $safe_table = esc_sql($jobs_table_name);
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $columns_raw = $wpdb->get_results("SHOW COLUMNS FROM {$safe_table}", ARRAY_A);
            if (is_array($columns_raw)) {
                foreach ($columns_raw as $col) {
                    if (!empty($col['Field'])) {
                        $columns[] = $col['Field'];
                    }
                }
            }
        }

        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Map Job Structure', 'atw-semantic-search'); ?></h1>

            <?php if ($notice): ?>
                <div class="notice notice-success is-dismissible">
                    <p><strong><?php echo esc_html($notice); ?></strong></p>
                </div>
            <?php endif; ?>

            <p class="description">
                <?php esc_html_e('Map your existing jobs table and columns to the ATW Semantic Search job schema. This allows the plugin to work with your custom job storage.', 'atw-semantic-search'); ?>
            </p>

            <form method="post">
                <?php wp_nonce_field('atw_semantic_map_structure_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="atw_jobs_table_name"><?php esc_html_e('Jobs table', 'atw-semantic-search'); ?></label>
                        </th>
                        <td>
                            <select name="atw_jobs_table_name" id="atw_jobs_table_name">
                                <?php foreach ($tables as $table): ?>
                                    <option value="<?php echo esc_attr($table); ?>" <?php selected($jobs_table_name, $table); ?>>
                                        <?php echo esc_html($table); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">
                                <?php esc_html_e('Select the table where your jobs are stored.', 'atw-semantic-search'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php if (!empty($columns)): ?>
                    <h2><?php esc_html_e('Column Mapping', 'atw-semantic-search'); ?></h2>
                    <table class="form-table">
                        <?php
                        // Helper to render a select row
                        $render_select = function ($label, $name, $current) use ($columns) {
                            ?>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($label); ?></label>
                                </th>
                                <td>
                                    <select name="<?php echo esc_attr($name); ?>" id="<?php echo esc_attr($name); ?>">
                                        <option value=""><?php esc_html_e('— Select column —', 'atw-semantic-search'); ?></option>
                                        <?php foreach ($columns as $column): ?>
                                            <option value="<?php echo esc_attr($column); ?>" <?php selected($current, $column); ?>>
                                                <?php echo esc_html($column); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <?php
                        };

                        $render_select(__('Job ID column', 'atw-semantic-search'), 'jobs_col_id', $jobs_col_id);
                        $render_select(__('Title column', 'atw-semantic-search'), 'jobs_col_title', $jobs_col_title);
                        $render_select(__('Company column', 'atw-semantic-search'), 'jobs_col_company', $jobs_col_company);
                        $render_select(__('Description column', 'atw-semantic-search'), 'jobs_col_description', $jobs_col_description);
                        $render_select(__('Required skills column', 'atw-semantic-search'), 'jobs_col_required_skills', $jobs_col_required);
                        $render_select(__('Preferred skills column', 'atw-semantic-search'), 'jobs_col_preferred_skills', $jobs_col_preferred);
                        $render_select(__('Experience (years) column', 'atw-semantic-search'), 'jobs_col_experience_years', $jobs_col_experience);
                        $render_select(__('Location column', 'atw-semantic-search'), 'jobs_col_location', $jobs_col_location);
                        $render_select(__('Salary range column', 'atw-semantic-search'), 'jobs_col_salary_range', $jobs_col_salary);
                        $render_select(__('Employment type column', 'atw-semantic-search'), 'jobs_col_employment_type', $jobs_col_employment);
                        $render_select(__('Status column', 'atw-semantic-search'), 'jobs_col_status', $jobs_col_status);
                        ?>
                        <tr>
                            <th scope="row">
                                <label for="atw_jobs_status_active_value"><?php esc_html_e('Active status value', 'atw-semantic-search'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="atw_jobs_status_active_value"
                                       name="atw_jobs_status_active_value"
                                       value="<?php echo esc_attr($jobs_status_active); ?>"
                                       class="regular-text" />
                                <p class="description">
                                    <?php esc_html_e('Value in the status column that indicates an active job (e.g. active, publish, 1).', 'atw-semantic-search'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                <?php else: ?>
                    <p class="description">
                        <?php esc_html_e('No columns could be detected for the selected table. Please ensure the table exists and you have sufficient permissions.', 'atw-semantic-search'); ?>
                    </p>
                <?php endif; ?>

                <?php submit_button(__('Save Mapping', 'atw-semantic-search'), 'primary', 'atw_semantic_save_mapping'); ?>
            </form>
        </div>
        <?php
    }
    
    /**
     * Render job search shortcode
     */
    public function render_job_search_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Find Your Dream Job',
            'show_upload' => 'no',
        ), $atts);
        
        ob_start();
        include ATW_SEMANTIC_PLUGIN_DIR . 'templates/job-search.php';
        return ob_get_clean();
    }

    /**
     * Handle saving user profile (preferences + resumeId)
     */
    public function handle_save_profile() {
        check_ajax_referer('atw_semantic_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to save your profile.'));
        }

        $user_id = get_current_user_id();
        global $wpdb;

        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        $resume_id = isset($_POST['resume_id']) ? intval($_POST['resume_id']) : 0;
        $existing_resume_id = isset($_POST['existing_resume_id']) ? intval($_POST['existing_resume_id']) : 0;

        $focus = isset($_POST['focus']) ? sanitize_text_field(wp_unslash($_POST['focus'])) : '';
        $transition_stage = isset($_POST['transition_stage']) ? sanitize_text_field(wp_unslash($_POST['transition_stage'])) : '';

        $job_categories = array();
        if (isset($_POST['job_categories'])) {
            $raw_categories = $_POST['job_categories'];
            if (!is_array($raw_categories)) {
                $raw_categories = array($raw_categories);
            }
            foreach ($raw_categories as $cat) {
                $job_categories[] = sanitize_text_field(wp_unslash($cat));
            }
        }

        $tech_stack_input = isset($_POST['tech_stack']) ? wp_unslash($_POST['tech_stack']) : '';
        $tech_stack_lines = preg_split('/\r\n|\r|\n/', $tech_stack_input);
        $tech_stack = array();
        foreach ($tech_stack_lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $tech_stack[] = sanitize_text_field($line);
            }
        }

        // If no new resume_id was provided, keep the existing one (if any)
        if ($resume_id <= 0 && $existing_resume_id > 0) {
            $resume_id = $existing_resume_id;
        }

        $table = $wpdb->prefix . 'atw_semantic_profiles';

        $data = array(
            'user_id'          => $user_id,
            'resume_id'        => $resume_id,
            'job_categories'   => !empty($job_categories) ? wp_json_encode($job_categories) : null,
            'tech_stack'       => !empty($tech_stack) ? wp_json_encode($tech_stack) : null,
            'focus'            => $focus,
            'transition_stage' => $transition_stage,
        );

        $formats = array('%d', '%d', '%s', '%s', '%s', '%s');

        $existing = $wpdb->get_var(
            $wpdb->prepare("SELECT id FROM $table WHERE user_id = %d", $user_id)
        );

        if ($existing) {
            $wpdb->update(
                $table,
                $data,
                array('user_id' => $user_id),
                $formats,
                array('%d')
            );
        } else {
            $wpdb->insert(
                $table,
                $data,
                $formats
            );
        }

        wp_send_json_success(array(
            'message' => 'Profile saved successfully.',
            'resumeId' => $resume_id,
        ));
    }

    /**
     * Get jobs for current user based on stored resume/profile
     */
    public function handle_get_profile_jobs() {
        check_ajax_referer('atw_semantic_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to see personalised jobs.'));
        }

        $user_id = get_current_user_id();
        global $wpdb;

        $profiles_table = $wpdb->prefix . 'atw_semantic_profiles';
        $profile = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $profiles_table WHERE user_id = %d", $user_id),
            ARRAY_A
        );

        if (!$profile || empty($profile['resume_id'])) {
            wp_send_json_error(array(
                'message' => 'We could not find your resume. Please upload it and set your preferences on your profile page.'
            ));
        }

        $resume_id = intval($profile['resume_id']);

        // Get API config
        $api_key = $this->get_setting('api_key');
        $api_base = $this->get_setting('api_base_url');
        if (empty($api_base)) {
            $api_base = ATW_SEMANTIC_API_BASE;
        }
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API key not configured. Please contact the site administrator.'));
        }

        // Threshold and limit from global settings
        $threshold = $this->get_setting('threshold');
        if (empty($threshold)) {
            $threshold = 0.5;
        }
        $limit = $this->get_setting('recommended_jobs_count');
        if (empty($limit)) {
            $limit = 10;
        }

        $url = trailingslashit($api_base) . 'api/resume/' . $resume_id . '/matches';
        $url = add_query_arg(
            array(
                'limit' => intval($limit),
                'threshold' => floatval($threshold),
            ),
            $url
        );

        $args = array(
            'headers' => array(
                'X-API-Key' => $api_key,
            ),
            'timeout' => 60,
            'sslverify' => false,
        );

        $response = wp_remote_get($url, $args);

        if (is_wp_error($response)) {
            wp_send_json_error(array(
                'message' => 'Failed to fetch jobs from semantic server: ' . $response->get_error_message(),
            ));
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200 || !is_array($data)) {
            wp_send_json_error(array(
                'message' => 'Unexpected response from semantic server when fetching matches.',
            ));
        }

        // We expect data.matches with wp_job_id + similarity
        $matches = isset($data['matches']) && is_array($data['matches']) ? $data['matches'] : array();

        if (empty($matches)) {
            wp_send_json_success(array(
                'matches' => array(),
                'matchCount' => 0,
            ));
        }

        // Enrich with local WP job details
        if (!class_exists('ATW_Jobs_Manager')) {
            require_once plugin_dir_path(__FILE__) . 'includes/class-jobs-manager.php';
        }

        $enriched = array();
        foreach ($matches as $match) {
            if (!isset($match['wp_job_id'])) {
                continue;
            }

            $job = ATW_Jobs_Manager::get_job($match['wp_job_id']);
            if (!$job) {
                continue;
            }

            // Normalise similarity field (support both similarity and similarity_score)
            $similarity = null;
            if (isset($match['similarity']) && is_numeric($match['similarity'])) {
                $similarity = (float) $match['similarity'];
            } elseif (isset($match['similarity_score']) && is_numeric($match['similarity_score'])) {
                $similarity = (float) $match['similarity_score'];
            }

            $skill_match_score = null;
            if ($similarity !== null) {
                // Treat similarity (0-1) as overall skills/semantic match percentage
                $skill_match_score = round($similarity * 100, 1);
            }

            $job_entry = array(
                'id' => $match['wp_job_id'],
                'title' => isset($job['title']) ? $job['title'] : '',
                'company' => isset($job['company']) ? $job['company'] : '',
                'location' => isset($job['location']) ? $job['location'] : '',
                'description' => isset($job['description']) ? $job['description'] : '',
                'requiredSkills' => array(),
                'employmentType' => isset($job['employment_type']) ? $job['employment_type'] : '',
                'salaryRange' => isset($job['salary_range']) ? $job['salary_range'] : '',
                'semanticSimilarity' => $similarity,
                'skillMatchScore' => $skill_match_score,
            );

            if (!empty($job['required_skills'])) {
                if (is_array($job['required_skills'])) {
                    $job_entry['requiredSkills'] = $job['required_skills'];
                } else {
                    $skills = array_map('trim', explode(',', $job['required_skills']));
                    $job_entry['requiredSkills'] = array_filter($skills);
                }
            }

            $enriched[] = $job_entry;
        }

        wp_send_json_success(array(
            'matches' => $enriched,
            'matchCount' => count($enriched),
        ));
    }
    
    /**
     * Render profile shortcode (preferences + resume upload)
     */
    public function render_profile_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<p>' . esc_html__('You must be logged in to manage your profile.', 'atw-semantic-search') . '</p>';
        }

        $atts = shortcode_atts(array(
            'title' => 'Your Career Profile',
        ), $atts);

        ob_start();
        include ATW_SEMANTIC_PLUGIN_DIR . 'templates/profile.php';
        return ob_get_clean();
    }
    
    /**
     * Handle resume upload via AJAX
     */
    public function handle_resume_upload() {
        check_ajax_referer('atw_semantic_nonce', 'nonce');
        
        $api_key = $this->get_setting('api_key');
        $api_base = $this->get_setting('api_base_url');
        if (empty($api_base)) {
            $api_base = ATW_SEMANTIC_API_BASE;
        }
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API key not configured. Please check plugin settings.'));
            return;
        }
        
        if (empty($_FILES['resume'])) {
            wp_send_json_error(array('message' => 'No file uploaded.'));
            return;
        }
        
        $file = $_FILES['resume'];
        
        // Validate file type
        $allowed_types = array('application/pdf');
        $file_type = wp_check_filetype($file['name']);
        
        if (!in_array($file['type'], $allowed_types) && $file_type['ext'] !== 'pdf') {
            wp_send_json_error(array('message' => 'Only PDF files are allowed.'));
            return;
        }
        
        // Validate file size (5MB max)
        if ($file['size'] > 5 * 1024 * 1024) {
            wp_send_json_error(array('message' => 'File size exceeds 5MB limit.'));
            return;
        }
        
        // Get email from request
        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        
        // Get threshold and limit from settings (with fallback defaults)
        $threshold = $this->get_setting('threshold');
        if (empty($threshold)) {
            $threshold = 0.5;
        }
        $limit = $this->get_setting('recommended_jobs_count');
        if (empty($limit)) {
            $limit = 10;
        }
        
        // Prepare file for upload
        $boundary = wp_generate_password(12, false);
        $body = '';
        
        // Add email
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="email"' . "\r\n\r\n";
        $body .= $email . "\r\n";
        
        // Add threshold
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="threshold"' . "\r\n\r\n";
        $body .= $threshold . "\r\n";
        
        // Add limit
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="limit"' . "\r\n\r\n";
        $body .= $limit . "\r\n";
        
        // Add file
        $body .= '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="resume"; filename="' . basename($file['name']) . '"' . "\r\n";
        $body .= 'Content-Type: application/pdf' . "\r\n\r\n";
        $body .= file_get_contents($file['tmp_name']) . "\r\n";
        $body .= '--' . $boundary . '--';
        
        // Send to API
        $response = wp_remote_post($api_base . '/api/upload-resume', array(
            'body' => $body,
            'headers' => array(
                'X-API-Key' => $api_key,
                'Content-Type' => 'multipart/form-data; boundary=' . $boundary,
            ),
            'timeout' => 60,
            'sslverify' => false, // Set to false for self-signed certificates, true for valid SSL
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('ATW Semantic: Resume upload failed - ' . $error_message);
            wp_send_json_error(array('message' => 'Failed to process resume: ' . $error_message));
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            error_log('ATW Semantic: Resume upload failed with status ' . $response_code);
            wp_send_json_error(array('message' => 'Server error (HTTP ' . $response_code . '). Please try again.'));
            return;
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ATW Semantic: Invalid JSON response from API - ' . json_last_error_msg());
            wp_send_json_error(array('message' => 'Invalid response from server. Please try again.'));
            return;
        }
        
        if (isset($data['success']) && $data['success']) {
            // If a WordPress user is logged in, link this resume ID to their profile immediately
            if (is_user_logged_in() && isset($data['resumeId'])) {
                global $wpdb;
                $user_id = get_current_user_id();
                $profiles_table = $wpdb->prefix . 'atw_semantic_profiles';
                $resume_id = intval($data['resumeId']);

                // Upsert resume_id only, do not touch existing preferences
                $existing_profile_id = $wpdb->get_var(
                    $wpdb->prepare("SELECT id FROM $profiles_table WHERE user_id = %d", $user_id)
                );

                if ($existing_profile_id) {
                    $wpdb->update(
                        $profiles_table,
                        array('resume_id' => $resume_id),
                        array('id' => $existing_profile_id),
                        array('%d'),
                        array('%d')
                    );
                } else {
                    $wpdb->insert(
                        $profiles_table,
                        array(
                            'user_id'   => $user_id,
                            'resume_id' => $resume_id,
                        ),
                        array('%d', '%d')
                    );
                }
            }

            // Enrich matches with local WordPress job details so Node.js
            // does NOT need to connect to the client database.
            if (isset($data['matches']) && is_array($data['matches']) && !empty($data['matches'])) {
                // Ensure Jobs Manager is loaded
                if (!class_exists('ATW_Jobs_Manager')) {
                    require_once(plugin_dir_path(__FILE__) . 'includes/class-jobs-manager.php');
                }

                foreach ($data['matches'] as &$match) {
                    // Node returns jobId as the WordPress job ID
                    if (!isset($match['jobId'])) {
                        continue;
                    }

                    $job_id = intval($match['jobId']);
                    if ($job_id <= 0) {
                        continue;
                    }

                    $job = ATW_Jobs_Manager::get_job($job_id);
                    if (!$job || !is_array($job)) {
                        continue;
                    }

                    // Convert comma-separated skills to arrays
                    $required_skills = array();
                    $preferred_skills = array();

                    if (!empty($job['required_skills'])) {
                        $required_skills = array_filter(array_map('trim', explode(',', $job['required_skills'])));
                    }
                    if (!empty($job['preferred_skills'])) {
                        $preferred_skills = array_filter(array_map('trim', explode(',', $job['preferred_skills'])));
                    }

                    // Populate fields expected by the frontend JS
                    $match['title'] = $job['title'];
                    $match['company'] = $job['company'];
                    $match['description'] = $job['description'];
                    $match['requiredSkills'] = $required_skills;
                    $match['preferredSkills'] = $preferred_skills;
                    $match['experienceYears'] = isset($job['experience_years']) ? intval($job['experience_years']) : null;
                    $match['location'] = $job['location'];
                    $match['salaryRange'] = $job['salary_range'];
                    $match['employmentType'] = $job['employment_type'];
                }
                unset($match);
            }

            wp_send_json_success($data);
        } else {
            $error_message = isset($data['message']) ? $data['message'] : (isset($data['error']) ? $data['error'] : 'Unknown error occurred');
            error_log('ATW Semantic: Resume upload API error - ' . $error_message);
            wp_send_json_error(array('message' => $error_message));
        }
    }
    
    /**
     * Handle get jobs via AJAX
     */
    public function handle_get_jobs() {
        check_ajax_referer('atw_semantic_nonce', 'nonce');
        
        $api_key = $this->get_setting('api_key');
        $api_base = $this->get_setting('api_base_url');
        if (empty($api_base)) {
            $api_base = ATW_SEMANTIC_API_BASE;
        }
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API key not configured.'));
            return;
        }
        
        $response = wp_remote_get($api_base . '/api/jobs', array(
            'headers' => array(
                'X-API-Key' => $api_key,
            ),
            'timeout' => 30,
            'sslverify' => false, // Set to false for self-signed certificates, true for valid SSL
        ));
        
        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            error_log('ATW Semantic: Get jobs failed - ' . $error_message);
            wp_send_json_error(array('message' => 'Failed to fetch jobs: ' . $error_message));
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        if ($response_code !== 200) {
            error_log('ATW Semantic: Get jobs failed with status ' . $response_code);
            wp_send_json_error(array('message' => 'Server error (HTTP ' . $response_code . '). Please try again.'));
            return;
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('ATW Semantic: Invalid JSON response from API - ' . json_last_error_msg());
            wp_send_json_error(array('message' => 'Invalid response from server.'));
            return;
        }
        
        if (isset($data['success']) && $data['success']) {
            wp_send_json_success($data);
        } else {
            $error_message = isset($data['message']) ? $data['message'] : (isset($data['error']) ? $data['error'] : 'Failed to fetch jobs.');
            error_log('ATW Semantic: Get jobs API error - ' . $error_message);
            wp_send_json_error(array('message' => $error_message));
        }
    }
    
    /**
     * Handle generate dummy jobs via AJAX
     * Creates jobs directly in WordPress wp_jobs table
     */
    public function handle_generate_dummy_jobs() {
        check_ajax_referer('atw_semantic_nonce', 'nonce');
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
            return;
        }
        
        require_once(plugin_dir_path(__FILE__) . 'includes/class-jobs-manager.php');
        
        // Always generate 500 jobs by default (server-side safety)
        $count = isset($_POST['count']) ? intval($_POST['count']) : 500;
        if ($count < 1 || $count > 500) {
            $count = 500;
        }
        
        // Job templates for multiple roles (full stack, SEO, data analytics, mobile, etc.)
        $job_templates = array(
            // Full Stack / Web
            array(
                'title' => 'Full Stack Developer',
                'company' => 'TechCorp',
                'description' => 'We are looking for a Full Stack Developer to build and maintain modern web applications using React, Node.js and REST APIs.',
                'required_skills' => 'JavaScript,React,Node.js,HTML,CSS,REST APIs',
                'preferred_skills' => 'TypeScript,Next.js,Docker,AWS',
                'experience_years' => 3,
                'location' => 'San Francisco, CA',
                'salary_range' => '$100,000 - $150,000',
                'employment_type' => 'Full-time',
            ),
            array(
                'title' => 'Senior Full Stack Engineer',
                'company' => 'InnovationHub',
                'description' => 'Lead full stack development across frontend and backend services. Mentor junior engineers and contribute to architecture decisions.',
                'required_skills' => 'JavaScript,TypeScript,React,Node.js,PostgreSQL,Design Patterns',
                'preferred_skills' => 'GraphQL,Microservices,AWS,Docker',
                'experience_years' => 6,
                'location' => 'Remote',
                'salary_range' => '$130,000 - $190,000',
                'employment_type' => 'Full-time',
            ),
            // SEO
            array(
                'title' => 'SEO Specialist',
                'company' => 'DigitalGrowth Agency',
                'description' => 'Own on-page and off-page SEO strategy, conduct keyword research, and optimize landing pages to improve organic traffic.',
                'required_skills' => 'SEO,Keyword Research,Google Analytics,On-page Optimization,Technical SEO',
                'preferred_skills' => 'Ahrefs,SEMrush,Content Strategy,Link Building',
                'experience_years' => 2,
                'location' => 'New York, NY',
                'salary_range' => '$60,000 - $90,000',
                'employment_type' => 'Full-time',
            ),
            array(
                'title' => 'SEO Manager',
                'company' => 'MarketingPro',
                'description' => 'Manage the SEO team, define SEO roadmaps, and collaborate with content and product teams to drive search performance.',
                'required_skills' => 'SEO,Team Leadership,Analytics,Content Strategy,Technical SEO',
                'preferred_skills' => 'CRO,AB Testing,Marketing Automation',
                'experience_years' => 5,
                'location' => 'Chicago, IL',
                'salary_range' => '$80,000 - $120,000',
                'employment_type' => 'Full-time',
            ),
            // Data / Analytics
            array(
                'title' => 'Data Analyst',
                'company' => 'DataAnalytics Co',
                'description' => 'Analyze business data, build dashboards, and provide insights to stakeholders to drive data-informed decisions.',
                'required_skills' => 'SQL,Excel,Data Visualization,Power BI,Reporting',
                'preferred_skills' => 'Python,R,Statistics,Tableau',
                'experience_years' => 2,
                'location' => 'Boston, MA',
                'salary_range' => '$70,000 - $100,000',
                'employment_type' => 'Full-time',
            ),
            array(
                'title' => 'Data Scientist',
                'company' => 'AI Labs',
                'description' => 'Build machine learning models, run experiments, and deploy models into production to solve complex business problems.',
                'required_skills' => 'Python,Machine Learning,SQL,Pandas,NumPy',
                'preferred_skills' => 'TensorFlow,PyTorch,Deep Learning,Spark',
                'experience_years' => 3,
                'location' => 'Remote',
                'salary_range' => '$100,000 - $150,000',
                'employment_type' => 'Full-time',
            ),
            // Mobile
            array(
                'title' => 'Mobile App Developer',
                'company' => 'MobileFirst',
                'description' => 'Develop and maintain cross-platform mobile applications for iOS and Android using React Native.',
                'required_skills' => 'React Native,JavaScript,iOS,Android,REST APIs',
                'preferred_skills' => 'Swift,Kotlin,Firebase,App Store,Play Store',
                'experience_years' => 3,
                'location' => 'Los Angeles, CA',
                'salary_range' => '$85,000 - $125,000',
                'employment_type' => 'Full-time',
            ),
            array(
                'title' => 'Senior Android Developer',
                'company' => 'AppStudio',
                'description' => 'Design and build advanced applications for the Android platform and collaborate with cross-functional teams.',
                'required_skills' => 'Kotlin,Android SDK,REST APIs,Git',
                'preferred_skills' => 'Java,Jetpack Compose,CI/CD',
                'experience_years' => 4,
                'location' => 'Austin, TX',
                'salary_range' => '$100,000 - $140,000',
                'employment_type' => 'Full-time',
            ),
            // Other tech roles to diversify
            array(
                'title' => 'DevOps Engineer',
                'company' => 'CloudOps',
                'description' => 'Manage CI/CD pipelines, cloud infrastructure, and monitoring for large-scale web applications.',
                'required_skills' => 'AWS,Docker,Kubernetes,CI/CD,Linux',
                'preferred_skills' => 'Terraform,Ansible,Prometheus,Grafana',
                'experience_years' => 4,
                'location' => 'Seattle, WA',
                'salary_range' => '$110,000 - $160,000',
                'employment_type' => 'Full-time',
            ),
            array(
                'title' => 'QA Automation Engineer',
                'company' => 'QualityAssurance Ltd',
                'description' => 'Create automated test suites and ensure high quality releases for web and mobile products.',
                'required_skills' => 'Testing,Selenium,JavaScript,Test Automation,Bug Tracking',
                'preferred_skills' => 'Cypress,Jest,API Testing,Performance Testing',
                'experience_years' => 3,
                'location' => 'Denver, CO',
                'salary_range' => '$70,000 - $110,000',
                'employment_type' => 'Full-time',
            ),
        );
        
        $variations = array('Junior', 'Mid-level', 'Senior', 'Lead', 'Principal', 'Associate');
        $domains = array('E‑commerce', 'Fintech', 'HealthTech', 'EdTech', 'SaaS', 'AI', 'Analytics', 'Marketplace', 'Media', 'Travel', 'Logistics', 'Gaming');
        $companies = array('TechCorp', 'WebSolutions Inc', 'CloudTech', 'Infrastructure Pro', 'DataAnalytics Co', 'MobileFirst', 'DesignStudio', 'ProductLab', 'QualityAssurance Ltd', 'SecureTech', 'InnovationHub', 'StartupXYZ', 'NextGen Labs', 'BrightFuture', 'CodeWorks');
        $locations = array('San Francisco, CA', 'New York, NY', 'Austin, TX', 'Seattle, WA', 'Boston, MA', 'Los Angeles, CA', 'Portland, OR', 'Chicago, IL', 'Denver, CO', 'Washington, DC', 'Remote', 'Hybrid', 'Miami, FL', 'Toronto, ON', 'London, UK');
        $salary_ranges = array('$80,000 - $120,000', '$90,000 - $130,000', '$100,000 - $150,000', '$110,000 - $160,000', '$120,000 - $180,000', '$130,000 - $200,000');
        
        $created = 0;
        $errors = array();
        
        for ($i = 0; $i < $count; $i++) {
            try {
                $template = $job_templates[$i % count($job_templates)];
                $variation = $variations[$i % count($variations)];
                $domain = $domains[$i % count($domains)];
                $company = $companies[$i % count($companies)];
                $location = $locations[$i % count($locations)];
                $salary = $salary_ranges[$i % count($salary_ranges)];
                
                // Build a UNIQUE and more descriptive title
                $title = $template['title'];
                if (strpos($title, $variation) === false) {
                    $title = $variation . ' ' . $title;
                }
                // Add domain and a unique index to guarantee uniqueness across 500 jobs
                $title = $title . ' - ' . $domain . ' (Job #' . ($i + 1) . ')';

                // Randomize skills per job so skill sets differ
                $required_skills_list = array();
                if (!empty($template['required_skills'])) {
                    $required_skills_list = array_filter(array_map('trim', explode(',', $template['required_skills'])));
                    shuffle($required_skills_list);
                }
                $preferred_skills_list = array();
                if (!empty($template['preferred_skills'])) {
                    $preferred_skills_list = array_filter(array_map('trim', explode(',', $template['preferred_skills'])));
                    shuffle($preferred_skills_list);
                }

                // Take a varying slice so each job has a slightly different combination
                $required_slice_size = min(6, max(3, ($i % 6) + 3));
                $preferred_slice_size = min(5, max(2, ($i % 5) + 2));

                $required_skills = !empty($required_skills_list)
                    ? implode(',', array_slice($required_skills_list, 0, min($required_slice_size, count($required_skills_list))))
                    : $template['required_skills'];

                $preferred_skills = !empty($preferred_skills_list)
                    ? implode(',', array_slice($preferred_skills_list, 0, min($preferred_slice_size, count($preferred_skills_list))))
                    : $template['preferred_skills'];
                
                $job_data = array(
                    'title' => $title,
                    'company' => $company,
                    'description' => $template['description'],
                    'required_skills' => $required_skills,
                    'preferred_skills' => $preferred_skills,
                    'experience_years' => $template['experience_years'] + ($i % 3) - 1,
                    'location' => $location,
                    'salary_range' => $salary,
                    'employment_type' => $template['employment_type'],
                    'status' => 'active',
                );
                
                $job_id = ATW_Jobs_Manager::save_job($job_data);
                if ($job_id) {
                    $created++;
                }
            } catch (Exception $e) {
                $errors[] = 'Error creating job ' . ($i + 1) . ': ' . $e->getMessage();
            }
        }
        
        if ($created > 0) {
            wp_send_json_success(array(
                'count' => $created,
                'message' => "Successfully generated {$created} dummy jobs in WordPress database.",
                'errors' => $errors
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Failed to generate jobs. ' . (!empty($errors) ? implode('; ', $errors) : 'Unknown error.'),
                'errors' => $errors
            ));
        }
    }
    
    /**
     * Handle sync WordPress jobs via AJAX
     * Fetches jobs from WordPress and sends them to Node.js server
     */
    public function handle_sync_wordpress_jobs() {
        try {
            error_log('ATW Semantic: ========== SYNC JOBS STARTED ==========');
            
            check_ajax_referer('atw_semantic_nonce', 'nonce');
            
            // Check user permissions
            if (!current_user_can('manage_options')) {
                error_log('ATW Semantic: Permission denied');
                wp_send_json_error(array('message' => 'Insufficient permissions.'));
                return;
            }
            
            // Ensure Jobs Manager class is loaded
            if (!class_exists('ATW_Jobs_Manager')) {
                $jobs_manager_file = plugin_dir_path(__FILE__) . 'includes/class-jobs-manager.php';
                error_log('ATW Semantic: Loading Jobs Manager from: ' . $jobs_manager_file);
                if (file_exists($jobs_manager_file)) {
                    require_once($jobs_manager_file);
                    error_log('ATW Semantic: Jobs Manager class loaded successfully');
                } else {
                    error_log('ATW Semantic: ERROR - Jobs Manager file not found at: ' . $jobs_manager_file);
                    wp_send_json_error(array('message' => 'Jobs Manager class file not found. Please reinstall the plugin.'));
                    return;
                }
            } else {
                error_log('ATW Semantic: Jobs Manager class already exists');
            }
            
            $api_key = $this->get_setting('api_key');
            $api_base = $this->get_setting('api_base_url');
            if (empty($api_base)) {
                $api_base = ATW_SEMANTIC_API_BASE;
            }
            
            error_log('ATW Semantic: API Base: ' . $api_base);
            error_log('ATW Semantic: API Key: ' . (empty($api_key) ? 'NOT SET' : substr($api_key, 0, 10) . '...'));
            
            if (empty($api_key)) {
                error_log('ATW Semantic: API key not configured');
                wp_send_json_error(array('message' => 'API key not configured. Please register with API first.'));
                return;
            }
            
            // Fetch all jobs from WordPress database
            $jobs = array();
            try {
                error_log('ATW Semantic: Calling ATW_Jobs_Manager::get_jobs()...');
                $wp_jobs = ATW_Jobs_Manager::get_jobs();
                error_log('ATW Semantic: get_jobs() returned: ' . (is_array($wp_jobs) ? count($wp_jobs) . ' jobs' : gettype($wp_jobs)));
            
            if ($wp_jobs && is_array($wp_jobs)) {
                foreach ($wp_jobs as $wp_job) {
                    // Handle both object and array formats from get_jobs()
                    $job_id = is_array($wp_job) ? $wp_job['id'] : $wp_job->id;
                    $job_title = is_array($wp_job) ? $wp_job['title'] : $wp_job->title;
                    $job_company = is_array($wp_job) ? $wp_job['company'] : $wp_job->company;
                    $job_description = is_array($wp_job) ? $wp_job['description'] : $wp_job->description;
                    $job_required_skills = is_array($wp_job) ? $wp_job['required_skills'] : $wp_job->required_skills;
                    $job_preferred_skills = is_array($wp_job) ? $wp_job['preferred_skills'] : $wp_job->preferred_skills;
                    $job_experience_years = is_array($wp_job) ? $wp_job['experience_years'] : $wp_job->experience_years;
                    $job_location = is_array($wp_job) ? $wp_job['location'] : $wp_job->location;
                    $job_salary_range = is_array($wp_job) ? $wp_job['salary_range'] : $wp_job->salary_range;
                    $job_employment_type = is_array($wp_job) ? $wp_job['employment_type'] : $wp_job->employment_type;
                    
                    // Format job for Node.js server
                    $jobs[] = array(
                        'id' => $job_id,
                        'title' => $job_title,
                        'company' => $job_company,
                        'description' => $job_description,
                        'required_skills' => is_array($job_required_skills) 
                            ? $job_required_skills 
                            : (!empty($job_required_skills) ? explode(',', $job_required_skills) : array()),
                        'preferred_skills' => is_array($job_preferred_skills) 
                            ? $job_preferred_skills 
                            : (!empty($job_preferred_skills) ? explode(',', $job_preferred_skills) : array()),
                        'experience_years' => $job_experience_years,
                        'location' => $job_location,
                        'salary_range' => $job_salary_range,
                        'employment_type' => $job_employment_type ? $job_employment_type : 'Full-time',
                    );
                }
            }
            
            error_log('ATW Semantic: Formatted ' . count($jobs) . ' jobs for sync');
            } catch (Exception $e) {
                error_log('ATW Semantic: Exception in get_jobs() - ' . $e->getMessage());
                error_log('ATW Semantic: Exception trace: ' . $e->getTraceAsString());
                wp_send_json_error(array('message' => 'Error fetching jobs from WordPress: ' . $e->getMessage()));
                return;
            } catch (Error $e) {
                error_log('ATW Semantic: Fatal Error in get_jobs() - ' . $e->getMessage());
                error_log('ATW Semantic: Error trace: ' . $e->getTraceAsString());
                wp_send_json_error(array('message' => 'Fatal error fetching jobs: ' . $e->getMessage()));
                return;
            }
            
            // Log API details before sending
            $api_url = $api_base . '/api/sync-wordpress-jobs';
            error_log('ATW Semantic: Syncing jobs to: ' . $api_url);
            error_log('ATW Semantic: Jobs count: ' . count($jobs));
            
            // Send jobs to Node.js server
            $request_body = json_encode(array('jobs' => $jobs));
            error_log('ATW Semantic: Request body size: ' . strlen($request_body) . ' bytes');
            
            $response = wp_remote_post($api_url, array(
                'headers' => array(
                    'X-API-Key' => $api_key,
                    'Content-Type' => 'application/json',
                ),
                'body' => $request_body,
                'sslverify' => false,
                'timeout' => 300, // 5 minutes for large job lists
            ));
            
            // Log response details
            if (is_wp_error($response)) {
                $error_message = $response->get_error_message();
                $error_code = $response->get_error_code();
                error_log('ATW Semantic: wp_remote_post error - ' . $error_message);
                error_log('ATW Semantic: Error code: ' . $error_code);
                wp_send_json_error(array('message' => $error_message));
                return;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            error_log('ATW Semantic: Response code: ' . $response_code);
            error_log('ATW Semantic: Response body: ' . substr($response_body, 0, 500));
            
            if ($response_code === 200) {
                $data = json_decode($response_body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    error_log('ATW Semantic: JSON decode error - ' . json_last_error_msg());
                    wp_send_json_error(array('message' => 'Invalid response from server: ' . json_last_error_msg()));
                    return;
                }
                error_log('ATW Semantic: Sync successful - Processed: ' . (isset($data['processed']) ? $data['processed'] : 0));
                wp_send_json_success($data);
            } else {
                $error_data = json_decode($response_body, true);
                $error_message = isset($error_data['message']) ? $error_data['message'] : 'Failed to sync WordPress jobs (HTTP ' . $response_code . ')';
                error_log('ATW Semantic: Sync failed - ' . $error_message);
                wp_send_json_error(array('message' => $error_message));
            }
        } catch (Exception $e) {
            error_log('ATW Semantic: FATAL EXCEPTION in handle_sync_wordpress_jobs: ' . $e->getMessage());
            error_log('ATW Semantic: Exception file: ' . $e->getFile() . ':' . $e->getLine());
            error_log('ATW Semantic: Exception trace: ' . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Internal error: ' . $e->getMessage()));
        } catch (Error $e) {
            error_log('ATW Semantic: FATAL ERROR in handle_sync_wordpress_jobs: ' . $e->getMessage());
            error_log('ATW Semantic: Error file: ' . $e->getFile() . ':' . $e->getLine());
            error_log('ATW Semantic: Error trace: ' . $e->getTraceAsString());
            wp_send_json_error(array('message' => 'Fatal error: ' . $e->getMessage()));
        }
    }
}

// Initialize plugin
ATW_Semantic_Search_Resume::get_instance();

