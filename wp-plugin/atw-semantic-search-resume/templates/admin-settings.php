<?php
/**
 * Admin Settings Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get plugin instance to access methods
$plugin = ATW_Semantic_Search_Resume::get_instance();

// Handle form submission
if (isset($_POST['atw_semantic_save_settings']) && check_admin_referer('atw_semantic_settings_nonce')) {
    // Save API base URL
    if (!empty($_POST['atw_semantic_api_base_url'])) {
        $plugin->save_setting('api_base_url', esc_url_raw($_POST['atw_semantic_api_base_url']));
    } else {
        $plugin->delete_setting('api_base_url');
    }
    
    // Save threshold
    if (!empty($_POST['atw_semantic_threshold']) && $_POST['atw_semantic_threshold'] !== '') {
        $plugin->save_setting('threshold', floatval($_POST['atw_semantic_threshold']));
    } else {
        $plugin->delete_setting('threshold');
    }
    
    // Save recommended jobs count
    if (!empty($_POST['atw_semantic_recommended_jobs_count']) && $_POST['atw_semantic_recommended_jobs_count'] !== '') {
        $plugin->save_setting('recommended_jobs_count', intval($_POST['atw_semantic_recommended_jobs_count']));
    } else {
        $plugin->delete_setting('recommended_jobs_count');
    }
    
    // Handle job categories
    if (isset($_POST['atw_semantic_job_categories']) && !empty($_POST['atw_semantic_job_categories'])) {
        $categories = array_map('sanitize_text_field', $_POST['atw_semantic_job_categories']);
        $plugin->save_setting('job_categories', $categories);
    } else {
        $plugin->delete_setting('job_categories');
    }
    
    // Handle tech stack
    if (isset($_POST['atw_semantic_tech_stack']) && !empty(trim($_POST['atw_semantic_tech_stack']))) {
        $tech_stack = sanitize_textarea_field($_POST['atw_semantic_tech_stack']);
        $tech_stack_array = array_filter(array_map('trim', explode("\n", $tech_stack)));
        $plugin->save_setting('tech_stack', $tech_stack_array);
    } else {
        $plugin->delete_setting('tech_stack');
    }
    
    echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('Success!', 'atw-semantic-search') . '</strong> ' . __('Settings saved successfully!', 'atw-semantic-search') . '</p></div>';
}

// Handle re-registration
if (isset($_POST['atw_semantic_reregister']) && check_admin_referer('atw_semantic_settings_nonce')) {
    $result = $plugin->register_with_api();
    
    if ($result) {
        echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('Success!', 'atw-semantic-search') . '</strong> ' . __('Successfully registered with API!', 'atw-semantic-search') . '</p></div>';
    } else {
        $error = $plugin->get_setting('registration_error', __('Unknown error occurred.', 'atw-semantic-search'));
        echo '<div class="notice notice-error is-dismissible"><p><strong>' . __('Error:', 'atw-semantic-search') . '</strong> ' . esc_html($error) . '</p></div>';
    }
}

// Show registration error if exists
$registration_error = $plugin->get_setting('registration_error');
if ($registration_error && !isset($_POST['atw_semantic_reregister'])) {
    echo '<div class="notice notice-warning is-dismissible"><p><strong>' . __('Warning:', 'atw-semantic-search') . '</strong> ' . esc_html($registration_error) . '</p></div>';
}

// Get current settings from custom table (all defaults are empty/null)
$api_base_url = $plugin->get_setting('api_base_url', '');
$threshold = $plugin->get_setting('threshold', '');
$recommended_jobs_count = $plugin->get_setting('recommended_jobs_count', '');
$job_categories = $plugin->get_setting('job_categories', array());
$tech_stack = $plugin->get_setting('tech_stack', array());
$client_id = $plugin->get_setting('client_id', '');
$api_key = $plugin->get_setting('api_key', '');
$is_registered = $plugin->get_setting('is_registered', false);

// Common job categories
$common_categories = array(
    'Software Development',
    'Web Development',
    'Mobile Development',
    'Data Science',
    'DevOps',
    'UI/UX Design',
    'Product Management',
    'Marketing',
    'Sales',
    'Customer Support',
    'Finance',
    'HR',
    'Operations',
    'Other',
);

// Common tech stack items
$common_tech_stack = array(
    'JavaScript', 'TypeScript', 'Python', 'Java', 'PHP', 'Ruby', 'Go', 'Rust',
    'React', 'Vue.js', 'Angular', 'Node.js', 'Express', 'Django', 'Laravel',
    'MySQL', 'PostgreSQL', 'MongoDB', 'Redis',
    'AWS', 'Azure', 'GCP', 'Docker', 'Kubernetes',
    'Git', 'CI/CD', 'Agile', 'Scrum',
);
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="atw-semantic-admin-container">
        <div class="atw-semantic-admin-main">
            <form method="post" action="">
                <?php wp_nonce_field('atw_semantic_settings_nonce'); ?>
                
                <!-- API Configuration -->
                <div class="atw-semantic-section">
                    <h2><?php _e('API Configuration', 'atw-semantic-search'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="atw_semantic_api_base_url"><?php _e('API Base URL', 'atw-semantic-search'); ?></label>
                            </th>
                            <td>
                                <input type="url" 
                                       id="atw_semantic_api_base_url" 
                                       name="atw_semantic_api_base_url" 
                                       value="<?php echo esc_attr($api_base_url ?: ATW_SEMANTIC_API_BASE); ?>" 
                                       placeholder="<?php echo esc_attr(ATW_SEMANTIC_API_BASE); ?>"
                                       class="regular-text" />
                                <p class="description">
                                    <?php _e('Base URL of your Node.js API server', 'atw-semantic-search'); ?><br>
                                    <?php _e('Default:', 'atw-semantic-search'); ?> <code>https://54.183.65.104:3002</code><br>
                                    <?php _e('Make sure your Node.js API is running and accessible at this URL.', 'atw-semantic-search'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Client Information (Read-only) -->
                <div class="atw-semantic-section">
                    <h2><?php _e('Client Information', 'atw-semantic-search'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Registration Status', 'atw-semantic-search'); ?></th>
                            <td>
                                <?php if ($is_registered): ?>
                                    <span class="dashicons dashicons-yes-alt" style="color: green;"></span>
                                    <strong><?php _e('Registered', 'atw-semantic-search'); ?></strong>
                                <?php else: ?>
                                    <span class="dashicons dashicons-warning" style="color: orange;"></span>
                                    <strong><?php _e('Not Registered', 'atw-semantic-search'); ?></strong>
                                    <p class="description"><?php _e('Click "Re-register with API" below to register this site.', 'atw-semantic-search'); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Client ID', 'atw-semantic-search'); ?></th>
                            <td>
                                <code><?php echo esc_html(!empty($client_id) ? $client_id : __('Not set', 'atw-semantic-search')); ?></code>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('API Key', 'atw-semantic-search'); ?></th>
                            <td>
                                <code style="word-break: break-all;"><?php echo esc_html(!empty($api_key) ? $api_key : __('Not set', 'atw-semantic-search')); ?></code>
                                <p class="description"><?php _e('Keep this secure. Do not share it publicly.', 'atw-semantic-search'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Database Configuration', 'atw-semantic-search'); ?></th>
                            <td>
                                <?php
                                global $wpdb;
                                $db_host = DB_HOST;
                                $db_port = 3307;
                                if (strpos($db_host, ':') !== false) {
                                    $parts = explode(':', $db_host);
                                    $db_host = $parts[0];
                                    $db_port = isset($parts[1]) ? intval($parts[1]) : 3307;
                                }
                                if (in_array($db_host, array('db', 'mysql', 'mariadb'))) {
                                    $db_host = 'localhost';
                                    if ($db_port == 3306) {
                                        $db_port = 3307;
                                    }
                                }
                                ?>
                                <p>
                                    <strong><?php _e('Host:', 'atw-semantic-search'); ?></strong> 
                                    <code><?php echo esc_html($db_host); ?></code><br>
                                    <strong><?php _e('Port:', 'atw-semantic-search'); ?></strong> 
                                    <code><?php echo esc_html($db_port); ?></code><br>
                                    <strong><?php _e('Database:', 'atw-semantic-search'); ?></strong> 
                                    <code><?php echo esc_html(DB_NAME); ?></code><br>
                                    <strong><?php _e('Table Prefix:', 'atw-semantic-search'); ?></strong> 
                                    <code><?php echo esc_html($wpdb->prefix); ?></code>
                                </p>
                                <p class="description">
                                    <?php _e('Database configuration is stored in Node.js server during registration. This allows automatic job syncing without re-entering credentials.', 'atw-semantic-search'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p>
                        <button type="submit" 
                                name="atw_semantic_reregister" 
                                class="button button-secondary"
                                onclick="return confirm('<?php _e('This will re-register this WordPress site and update database configuration. Continue?', 'atw-semantic-search'); ?>');">
                            <?php _e('Re-register with API', 'atw-semantic-search'); ?>
                        </button>
                    </p>
                </div>
                
                <!-- WordPress Jobs Management -->
                <div class="atw-semantic-section">
                    <h2><?php _e('WordPress Jobs Management', 'atw-semantic-search'); ?></h2>
                    <p class="description">
                        <?php _e('Your jobs are stored in the WordPress database table: <code>wp_jobs</code>. Sync these jobs to the Node.js server for semantic search processing.', 'atw-semantic-search'); ?>
                    </p>
                    
                    <?php
                    require_once(plugin_dir_path(__FILE__) . '../includes/class-jobs-manager.php');
                    $jobs_count = ATW_Jobs_Manager::get_jobs_count('active');
                    ?>
                    <p>
                        <strong><?php _e('Active Jobs in WordPress:', 'atw-semantic-search'); ?></strong> 
                        <span id="atw_wp_jobs_count"><?php echo esc_html($jobs_count); ?></span>
                    </p>
                    
                    <p>
                        <button type="button" 
                                id="atw_sync_wordpress_jobs_btn" 
                                class="button button-primary"
                                data-nonce="<?php echo wp_create_nonce('atw_semantic_nonce'); ?>">
                            <span class="dashicons dashicons-update" style="vertical-align: middle;"></span>
                            <?php _e('Sync Jobs to Node.js Server', 'atw-semantic-search'); ?>
                        </button>
                        <span id="atw_sync_jobs_status" style="margin-left: 10px;"></span>
                    </p>
                    
                    <div id="atw_sync_jobs_message" style="margin-top: 10px;"></div>
                </div>
                
                <!-- Generate Dummy Jobs -->
                <div class="atw-semantic-section">
                    <h2><?php _e('Generate Dummy Jobs (Testing)', 'atw-semantic-search'); ?></h2>
                    <p class="description">
                        <?php _e('Generate 500 dummy jobs (Full Stack, SEO, Data Analytics, Mobile, and more) for testing the semantic search model. These jobs will be created in your WordPress database (wp_jobs table).', 'atw-semantic-search'); ?>
                    </p>
                    
                    <p>
                        <button type="button" 
                                id="atw_generate_dummy_jobs_btn" 
                                class="button button-secondary"
                                data-nonce="<?php echo wp_create_nonce('atw_semantic_nonce'); ?>">
                            <span class="dashicons dashicons-admin-generic" style="vertical-align: middle;"></span>
                            <?php _e('Generate 500 Dummy Jobs in WordPress', 'atw-semantic-search'); ?>
                        </button>
                        <span id="atw_dummy_jobs_status" style="margin-left: 10px;"></span>
                    </p>
                    
                    <div id="atw_dummy_jobs_message" style="margin-top: 10px;"></div>
                </div>
                
                <!-- Search Preferences -->
                <div class="atw-semantic-section">
                    <h2><?php _e('Search Preferences', 'atw-semantic-search'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="atw_semantic_threshold"><?php _e('Similarity Threshold', 'atw-semantic-search'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="atw_semantic_threshold" 
                                       name="atw_semantic_threshold" 
                                       value="<?php echo esc_attr($threshold); ?>" 
                                       min="0" 
                                       max="1" 
                                       step="0.1" 
                                       placeholder="0.5"
                                       class="small-text" />
                                <p class="description"><?php _e('Minimum similarity score (0.0 - 1.0) for job matches. Higher values = more strict matching. Leave empty for default.', 'atw-semantic-search'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="atw_semantic_recommended_jobs_count"><?php _e('Recommended Jobs Count', 'atw-semantic-search'); ?></label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="atw_semantic_recommended_jobs_count" 
                                       name="atw_semantic_recommended_jobs_count" 
                                       value="<?php echo esc_attr($recommended_jobs_count); ?>" 
                                       min="1" 
                                       max="50" 
                                       placeholder="10"
                                       class="small-text" />
                                <p class="description"><?php _e('Number of recommended jobs to show (1-50). Leave empty for default.', 'atw-semantic-search'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <!-- Job Categories -->
                <div class="atw-semantic-section">
                    <h2><?php _e('Job Categories', 'atw-semantic-search'); ?></h2>
                    <p class="description"><?php _e('Select default job categories to filter by', 'atw-semantic-search'); ?></p>
                    
                    <div class="atw-semantic-checkbox-group">
                        <?php foreach ($common_categories as $category): ?>
                            <label style="display: block; margin: 5px 0;">
                                <input type="checkbox" 
                                       name="atw_semantic_job_categories[]" 
                                       value="<?php echo esc_attr($category); ?>"
                                       <?php checked(is_array($job_categories) && in_array($category, $job_categories)); ?> />
                                <?php echo esc_html($category); ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Tech Stack -->
                <div class="atw-semantic-section">
                    <h2><?php _e('Tech Stack Preferences', 'atw-semantic-search'); ?></h2>
                    <p class="description"><?php _e('Enter preferred technologies (one per line). These will be used as default filters.', 'atw-semantic-search'); ?></p>
                    
                    <textarea id="atw_semantic_tech_stack" 
                              name="atw_semantic_tech_stack" 
                              rows="10" 
                              class="large-text code"
                              placeholder="<?php _e('JavaScript&#10;React&#10;Node.js&#10;Python&#10;...', 'atw-semantic-search'); ?>"><?php echo esc_textarea(is_array($tech_stack) && !empty($tech_stack) ? implode("\n", $tech_stack) : ''); ?></textarea>
                    
                    <p class="description">
                        <strong><?php _e('Common technologies:', 'atw-semantic-search'); ?></strong><br>
                        <?php echo esc_html(implode(', ', $common_tech_stack)); ?>
                    </p>
                </div>
                
                <?php submit_button(__('Save Settings', 'atw-semantic-search'), 'primary', 'atw_semantic_save_settings'); ?>
            </form>
        </div>
        
        <!-- Sidebar -->
        <div class="atw-semantic-admin-sidebar">
            <div class="atw-semantic-widget">
                <h3><?php _e('Shortcode', 'atw-semantic-search'); ?></h3>
                <p><?php _e('Use this shortcode to display the job search form on any page:', 'atw-semantic-search'); ?></p>
                <code>[atw_semantic_job_search]</code>
                <p class="description"><?php _e('Options:', 'atw-semantic-search'); ?></p>
                <ul>
                    <li><code>title</code> - Custom title (default: "Find Your Dream Job")</li>
                    <li><code>show_upload</code> - Show resume upload (yes/no, default: yes)</li>
                </ul>
            </div>
            
            <div class="atw-semantic-widget">
                <h3><?php _e('Documentation', 'atw-semantic-search'); ?></h3>
                <p><?php _e('For more information, visit:', 'atw-semantic-search'); ?></p>
                <p><a href="https://atwebtechnologies.com" target="_blank">AT Web Technologies</a></p>
            </div>
        </div>
    </div>
</div>

