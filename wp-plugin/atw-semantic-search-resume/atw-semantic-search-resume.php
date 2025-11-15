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
        
        // AJAX handlers
        add_action('wp_ajax_atw_upload_resume', array($this, 'handle_resume_upload'));
        add_action('wp_ajax_nopriv_atw_upload_resume', array($this, 'handle_resume_upload'));
        add_action('wp_ajax_atw_get_jobs', array($this, 'handle_get_jobs'));
        add_action('wp_ajax_nopriv_atw_get_jobs', array($this, 'handle_get_jobs'));
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
                'setting_key' => $key,
                'setting_value' => $value,
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
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Semantic Search Settings', 'atw-semantic-search'),
            __('Semantic Search', 'atw-semantic-search'),
            'manage_options',
            'atw-semantic-search',
            array($this, 'render_admin_page'),
            'dashicons-search',
            30
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
        if ('toplevel_page_atw-semantic-search' !== $hook) {
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
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        include ATW_SEMANTIC_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    /**
     * Render job search shortcode
     */
    public function render_job_search_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Find Your Dream Job',
            'show_upload' => 'yes',
        ), $atts);
        
        ob_start();
        include ATW_SEMANTIC_PLUGIN_DIR . 'templates/job-search.php';
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
        
        $count = isset($_POST['count']) ? intval($_POST['count']) : 100;
        if ($count < 1 || $count > 500) {
            $count = 100;
        }
        
        // Job templates
        $job_templates = array(
            array(
                'title' => 'Senior Full Stack Developer',
                'company' => 'TechCorp',
                'description' => 'We are looking for an experienced Full Stack Developer to join our team. You will be responsible for developing and maintaining web applications using modern technologies.',
                'required_skills' => 'JavaScript,React,Node.js,PostgreSQL,REST APIs',
                'preferred_skills' => 'TypeScript,AWS,Docker,GraphQL',
                'experience_years' => 5,
                'location' => 'San Francisco, CA',
                'salary_range' => '$120,000 - $180,000',
                'employment_type' => 'Full-time',
            ),
            array(
                'title' => 'Frontend Developer',
                'company' => 'WebSolutions Inc',
                'description' => 'Join our frontend team to build beautiful and responsive user interfaces. Work with cutting-edge technologies and collaborate with designers and backend developers.',
                'required_skills' => 'React,TypeScript,CSS,HTML,JavaScript',
                'preferred_skills' => 'Vue.js,Next.js,Tailwind CSS,Redux',
                'experience_years' => 3,
                'location' => 'New York, NY',
                'salary_range' => '$90,000 - $130,000',
                'employment_type' => 'Full-time',
            ),
            array(
                'title' => 'Backend Developer',
                'company' => 'CloudTech',
                'description' => 'We need a skilled Backend Developer to design and implement scalable server-side applications. Experience with microservices architecture is a plus.',
                'required_skills' => 'Python,Django,PostgreSQL,REST APIs,Linux',
                'preferred_skills' => 'FastAPI,Redis,Kubernetes,Docker,AWS',
                'experience_years' => 4,
                'location' => 'Austin, TX',
                'salary_range' => '$100,000 - $150,000',
                'employment_type' => 'Full-time',
            ),
            array(
                'title' => 'DevOps Engineer',
                'company' => 'Infrastructure Pro',
                'description' => 'Looking for a DevOps Engineer to manage our cloud infrastructure and CI/CD pipelines. Help us scale our systems efficiently.',
                'required_skills' => 'AWS,Docker,Kubernetes,CI/CD,Linux',
                'preferred_skills' => 'Terraform,Ansible,Jenkins,GitLab CI,Monitoring',
                'experience_years' => 4,
                'location' => 'Seattle, WA',
                'salary_range' => '$110,000 - $160,000',
                'employment_type' => 'Full-time',
            ),
            array(
                'title' => 'Data Scientist',
                'company' => 'DataAnalytics Co',
                'description' => 'Join our data science team to build machine learning models and analyze large datasets. Work on exciting projects that drive business decisions.',
                'required_skills' => 'Python,Machine Learning,SQL,Pandas,NumPy',
                'preferred_skills' => 'TensorFlow,PyTorch,Spark,Jupyter,Statistics',
                'experience_years' => 3,
                'location' => 'Boston, MA',
                'salary_range' => '$95,000 - $140,000',
                'employment_type' => 'Full-time',
            ),
            array(
                'title' => 'Mobile App Developer',
                'company' => 'MobileFirst',
                'description' => 'We are seeking a Mobile App Developer to create native and cross-platform mobile applications. Work on iOS and Android platforms.',
                'required_skills' => 'React Native,JavaScript,iOS,Android,REST APIs',
                'preferred_skills' => 'Swift,Kotlin,Flutter,Firebase,App Store',
                'experience_years' => 3,
                'location' => 'Los Angeles, CA',
                'salary_range' => '$85,000 - $125,000',
                'employment_type' => 'Full-time',
            ),
            array(
                'title' => 'UI/UX Designer',
                'company' => 'DesignStudio',
                'description' => 'Looking for a creative UI/UX Designer to design user-friendly interfaces and improve user experience across our products.',
                'required_skills' => 'Figma,Adobe XD,User Research,Wireframing,Prototyping',
                'preferred_skills' => 'Sketch,InVision,HTML/CSS,Design Systems,Accessibility',
                'experience_years' => 2,
                'location' => 'Portland, OR',
                'salary_range' => '$70,000 - $100,000',
                'employment_type' => 'Full-time',
            ),
            array(
                'title' => 'Product Manager',
                'company' => 'ProductLab',
                'description' => 'We need an experienced Product Manager to lead product development, work with cross-functional teams, and drive product strategy.',
                'required_skills' => 'Product Strategy,Agile,Stakeholder Management,Analytics,Roadmapping',
                'preferred_skills' => 'SQL,A/B Testing,User Research,Technical Background,Scrum',
                'experience_years' => 5,
                'location' => 'Chicago, IL',
                'salary_range' => '$100,000 - $150,000',
                'employment_type' => 'Full-time',
            ),
            array(
                'title' => 'QA Engineer',
                'company' => 'QualityAssurance Ltd',
                'description' => 'Join our QA team to ensure software quality through comprehensive testing. Write test cases and automate testing processes.',
                'required_skills' => 'Testing,Selenium,Python,Test Automation,Bug Tracking',
                'preferred_skills' => 'Cypress,Jest,API Testing,Performance Testing,CI/CD',
                'experience_years' => 2,
                'location' => 'Denver, CO',
                'salary_range' => '$65,000 - $95,000',
                'employment_type' => 'Full-time',
            ),
            array(
                'title' => 'Security Engineer',
                'company' => 'SecureTech',
                'description' => 'We are looking for a Security Engineer to protect our systems and applications from security threats. Conduct security audits and implement security measures.',
                'required_skills' => 'Security,Penetration Testing,Linux,Network Security,OWASP',
                'preferred_skills' => 'AWS Security,Kubernetes Security,SIEM,Compliance,Incident Response',
                'experience_years' => 4,
                'location' => 'Washington, DC',
                'salary_range' => '$110,000 - $160,000',
                'employment_type' => 'Full-time',
            ),
        );
        
        $variations = array('Senior', 'Lead', 'Principal', 'Mid-level', 'Junior', 'Associate');
        $companies = array('TechCorp', 'WebSolutions Inc', 'CloudTech', 'Infrastructure Pro', 'DataAnalytics Co', 'MobileFirst', 'DesignStudio', 'ProductLab', 'QualityAssurance Ltd', 'SecureTech', 'InnovationHub', 'StartupXYZ');
        $locations = array('San Francisco, CA', 'New York, NY', 'Austin, TX', 'Seattle, WA', 'Boston, MA', 'Los Angeles, CA', 'Portland, OR', 'Chicago, IL', 'Denver, CO', 'Washington, DC', 'Remote', 'Hybrid');
        $salary_ranges = array('$80,000 - $120,000', '$90,000 - $130,000', '$100,000 - $150,000', '$110,000 - $160,000', '$120,000 - $180,000', '$130,000 - $200,000');
        
        $created = 0;
        $errors = array();
        
        for ($i = 0; $i < $count; $i++) {
            try {
                $template = $job_templates[$i % count($job_templates)];
                $variation = $variations[$i % count($variations)];
                $company = $companies[$i % count($companies)];
                $location = $locations[$i % count($locations)];
                $salary = $salary_ranges[$i % count($salary_ranges)];
                
                // Add variation to title if not already present
                $title = $template['title'];
                if (strpos($title, $variation) === false) {
                    $title = $variation . ' ' . $title;
                }
                
                $job_data = array(
                    'title' => $title,
                    'company' => $company,
                    'description' => $template['description'],
                    'required_skills' => $template['required_skills'],
                    'preferred_skills' => $template['preferred_skills'],
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

