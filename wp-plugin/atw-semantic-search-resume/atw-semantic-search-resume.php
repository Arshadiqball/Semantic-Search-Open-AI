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
        $this->init_hooks();
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
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create custom database table for plugin settings
        $this->create_settings_table();
        
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
        
        // Register with API
        $response = wp_remote_post($api_base . '/api/admin/clients', array(
            'body' => json_encode(array(
                'name' => $site_name . ' (' . $site_url . ')',
                'apiUrl' => $site_url,
            )),
            'headers' => array(
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
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
     */
    public function handle_generate_dummy_jobs() {
        check_ajax_referer('atw_semantic_nonce', 'nonce');
        
        // Check user permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Insufficient permissions.'));
            return;
        }
        
        $api_key = $this->get_setting('api_key');
        $api_base = $this->get_setting('api_base_url');
        if (empty($api_base)) {
            $api_base = ATW_SEMANTIC_API_BASE;
        }
        
        if (empty($api_key)) {
            wp_send_json_error(array('message' => 'API key not configured. Please register with API first.'));
            return;
        }
        
        $count = isset($_POST['count']) ? intval($_POST['count']) : 100;
        if ($count < 1 || $count > 500) {
            $count = 100;
        }
        
        $response = wp_remote_post($api_base . '/api/generate-dummy-jobs', array(
            'headers' => array(
                'X-API-Key' => $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode(array('count' => $count)),
            'sslverify' => true,
            'timeout' => 300, // 5 minutes timeout for generating jobs
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
            return;
        }
        
        $response_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($response_code === 200) {
            $data = json_decode($body, true);
            wp_send_json_success($data);
        } else {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : 'Failed to generate dummy jobs';
            wp_send_json_error(array('message' => $error_message));
        }
    }
}

// Initialize plugin
ATW_Semantic_Search_Resume::get_instance();

