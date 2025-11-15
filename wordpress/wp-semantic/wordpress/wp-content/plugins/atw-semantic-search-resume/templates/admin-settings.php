<?php
/**
 * Admin Settings Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['atw_semantic_save_settings']) && check_admin_referer('atw_semantic_settings_nonce')) {
    update_option('atw_semantic_api_base_url', esc_url_raw($_POST['atw_semantic_api_base_url']));
    update_option('atw_semantic_threshold', floatval($_POST['atw_semantic_threshold']));
    update_option('atw_semantic_recommended_jobs_count', intval($_POST['atw_semantic_recommended_jobs_count']));
    
    // Handle job categories
    $categories = isset($_POST['atw_semantic_job_categories']) ? array_map('sanitize_text_field', $_POST['atw_semantic_job_categories']) : array();
    update_option('atw_semantic_job_categories', $categories);
    
    // Handle tech stack
    $tech_stack = isset($_POST['atw_semantic_tech_stack']) ? sanitize_textarea_field($_POST['atw_semantic_tech_stack']) : '';
    $tech_stack_array = array_filter(array_map('trim', explode("\n", $tech_stack)));
    update_option('atw_semantic_tech_stack', $tech_stack_array);
    
    echo '<div class="notice notice-success"><p>' . __('Settings saved successfully!', 'atw-semantic-search') . '</p></div>';
}

// Handle re-registration
if (isset($_POST['atw_semantic_reregister']) && check_admin_referer('atw_semantic_settings_nonce')) {
    $plugin = ATW_Semantic_Search_Resume::get_instance();
    $result = $plugin->register_with_api();
    
    if ($result) {
        echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('Success!', 'atw-semantic-search') . '</strong> ' . __('Successfully registered with API!', 'atw-semantic-search') . '</p></div>';
    } else {
        $error = get_option('atw_semantic_registration_error', __('Unknown error occurred.', 'atw-semantic-search'));
        echo '<div class="notice notice-error is-dismissible"><p><strong>' . __('Error:', 'atw-semantic-search') . '</strong> ' . esc_html($error) . '</p></div>';
    }
}

// Show registration error if exists
$registration_error = get_option('atw_semantic_registration_error');
if ($registration_error && !isset($_POST['atw_semantic_reregister'])) {
    echo '<div class="notice notice-warning is-dismissible"><p><strong>' . __('Warning:', 'atw-semantic-search') . '</strong> ' . esc_html($registration_error) . '</p></div>';
}

// Get current settings
$api_base_url = get_option('atw_semantic_api_base_url', ATW_SEMANTIC_API_BASE);
$threshold = get_option('atw_semantic_threshold', 0.5);
$recommended_jobs_count = get_option('atw_semantic_recommended_jobs_count', 10);
$job_categories = get_option('atw_semantic_job_categories', array());
$tech_stack = get_option('atw_semantic_tech_stack', array());
$client_id = get_option('atw_semantic_client_id', '');
$api_key = get_option('atw_semantic_api_key', '');
$is_registered = get_option('atw_semantic_is_registered', false);

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
                                       value="<?php echo esc_attr($api_base_url); ?>" 
                                       class="regular-text" 
                                       required />
                                <p class="description">
                                    <?php _e('Base URL of your Node.js API server', 'atw-semantic-search'); ?><br>
                                    <?php _e('Default:', 'atw-semantic-search'); ?> <code>http://localhost:3000</code><br>
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
                                <code><?php echo esc_html($client_id ?: __('Not set', 'atw-semantic-search')); ?></code>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('API Key', 'atw-semantic-search'); ?></th>
                            <td>
                                <code style="word-break: break-all;"><?php echo esc_html($api_key ?: __('Not set', 'atw-semantic-search')); ?></code>
                                <p class="description"><?php _e('Keep this secure. Do not share it publicly.', 'atw-semantic-search'); ?></p>
                            </td>
                        </tr>
                    </table>
                    
                    <p>
                        <button type="submit" 
                                name="atw_semantic_reregister" 
                                class="button button-secondary"
                                onclick="return confirm('<?php _e('This will register this WordPress site as a new client. Continue?', 'atw-semantic-search'); ?>');">
                            <?php _e('Re-register with API', 'atw-semantic-search'); ?>
                        </button>
                    </p>
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
                                       class="small-text" 
                                       required />
                                <p class="description"><?php _e('Minimum similarity score (0.0 - 1.0) for job matches. Higher values = more strict matching.', 'atw-semantic-search'); ?></p>
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
                                       class="small-text" 
                                       required />
                                <p class="description"><?php _e('Number of recommended jobs to show (1-50)', 'atw-semantic-search'); ?></p>
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
                                       <?php checked(in_array($category, $job_categories)); ?> />
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
                              placeholder="<?php _e('JavaScript&#10;React&#10;Node.js&#10;Python&#10;...', 'atw-semantic-search'); ?>"><?php echo esc_textarea(implode("\n", $tech_stack)); ?></textarea>
                    
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

