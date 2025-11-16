<?php
/**
 * Admin Analytics Page Template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get plugin instance
$plugin = ATW_Semantic_Search_Resume::get_instance();

// Get API config
$api_base = $plugin->get_setting('api_base_url');
if (empty($api_base)) {
    $api_base = ATW_SEMANTIC_API_BASE;
}
$api_key = $plugin->get_setting('api_key', '');

$analytics_data = null;
$analytics_error = '';

if (empty($api_key)) {
    $analytics_error = __('API key not configured. Please register with the Node.js API in the Search Settings page first.', 'atw-semantic-search');
} else {
    // Call Node.js /api/analytics endpoint
    $response = wp_remote_get($api_base . '/api/analytics', array(
        'headers' => array(
            'X-API-Key' => $api_key,
        ),
        'timeout' => 30,
        'sslverify' => false, // Set to true in production with valid SSL
    ));

    if (is_wp_error($response)) {
        $analytics_error = sprintf(
            __('Failed to load analytics: %s', 'atw-semantic-search'),
            $response->get_error_message()
        );
    } else {
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code !== 200) {
            $analytics_error = sprintf(
                __('Server returned HTTP %d when fetching analytics.', 'atw-semantic-search'),
                $code
            );
        } else {
            $data = json_decode($body, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $analytics_error = __('Invalid JSON response from analytics API.', 'atw-semantic-search');
            } elseif (empty($data['success'])) {
                $analytics_error = isset($data['message']) ? $data['message'] : __('Unknown error from analytics API.', 'atw-semantic-search');
            } else {
                $analytics_data = $data;
            }
        }
    }
}

// Helper for safe value access
function atw_semantic_get($array, $key, $default = 0) {
    return isset($array[$key]) ? $array[$key] : $default;
}
?>

<div class="wrap">
    <h1><?php esc_html_e('ATW Semantic Analytics', 'atw-semantic-search'); ?></h1>

    <?php if (!empty($analytics_error)) : ?>
        <div class="notice notice-error">
            <p><strong><?php esc_html_e('Error:', 'atw-semantic-search'); ?></strong> <?php echo esc_html($analytics_error); ?></p>
        </div>
    <?php endif; ?>

    <?php if ($analytics_data) : 
        $stats           = isset($analytics_data['statistics']) ? $analytics_data['statistics'] : array();
        $recent_uploads  = isset($analytics_data['recentUploads']) ? $analytics_data['recentUploads'] : array();
        $uploads_by_date = isset($analytics_data['uploadsByDate']) ? $analytics_data['uploadsByDate'] : array();
        $uploads_by_ip   = isset($analytics_data['uploadsByIP']) ? $analytics_data['uploadsByIP'] : array();
        $uploads_by_email= isset($analytics_data['uploadsByEmail']) ? $analytics_data['uploadsByEmail'] : array();
    ?>

        <h2><?php esc_html_e('Overview', 'atw-semantic-search'); ?></h2>

        <div class="atw-semantic-analytics-grid">
            <div class="atw-semantic-analytics-card">
                <h3><?php esc_html_e('Total Uploads', 'atw-semantic-search'); ?></h3>
                <p class="atw-semantic-analytics-value">
                    <?php echo esc_html(atw_semantic_get($stats, 'total_uploads', 0)); ?>
                </p>
            </div>

            <div class="atw-semantic-analytics-card">
                <h3><?php esc_html_e('Unique IPs', 'atw-semantic-search'); ?></h3>
                <p class="atw-semantic-analytics-value">
                    <?php echo esc_html(atw_semantic_get($stats, 'unique_ips', 0)); ?>
                </p>
            </div>

            <div class="atw-semantic-analytics-card">
                <h3><?php esc_html_e('Unique Emails', 'atw-semantic-search'); ?></h3>
                <p class="atw-semantic-analytics-value">
                    <?php echo esc_html(atw_semantic_get($stats, 'unique_emails', 0)); ?>
                </p>
            </div>

            <div class="atw-semantic-analytics-card">
                <h3><?php esc_html_e('Uploads with Email', 'atw-semantic-search'); ?></h3>
                <p class="atw-semantic-analytics-value">
                    <?php echo esc_html(atw_semantic_get($stats, 'uploads_with_email', 0)); ?>
                </p>
            </div>

            <div class="atw-semantic-analytics-card">
                <h3><?php esc_html_e('Uploads with IP', 'atw-semantic-search'); ?></h3>
                <p class="atw-semantic-analytics-value">
                    <?php echo esc_html(atw_semantic_get($stats, 'uploads_with_ip', 0)); ?>
                </p>
            </div>
        </div>

        <hr />

        <h2><?php esc_html_e('Recent Uploads', 'atw-semantic-search'); ?></h2>
        <?php if (empty($recent_uploads)) : ?>
            <p><em><?php esc_html_e('No uploads yet.', 'atw-semantic-search'); ?></em></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('ID', 'atw-semantic-search'); ?></th>
                        <th><?php esc_html_e('Filename', 'atw-semantic-search'); ?></th>
                        <th><?php esc_html_e('Email', 'atw-semantic-search'); ?></th>
                        <th><?php esc_html_e('IP Address', 'atw-semantic-search'); ?></th>
                        <th><?php esc_html_e('Matches', 'atw-semantic-search'); ?></th>
                        <th><?php esc_html_e('Uploaded At', 'atw-semantic-search'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_uploads as $upload) : 
                        $date = isset($upload['created_at']) ? strtotime($upload['created_at']) : false;
                    ?>
                        <tr>
                            <td><?php echo esc_html($upload['id']); ?></td>
                            <td><?php echo esc_html($upload['filename']); ?></td>
                            <td><?php echo !empty($upload['email']) ? esc_html($upload['email']) : '<span style="color:#999;">' . esc_html__('N/A', 'atw-semantic-search') . '</span>'; ?></td>
                            <td><?php echo !empty($upload['ip_address']) ? esc_html($upload['ip_address']) : '<span style="color:#999;">' . esc_html__('N/A', 'atw-semantic-search') . '</span>'; ?></td>
                            <td><?php echo esc_html(isset($upload['match_count']) ? $upload['match_count'] : 0); ?></td>
                            <td>
                                <?php
                                echo $date ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $date)) : '';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <hr />

        <h2><?php esc_html_e('Uploads by Date (Last 30 Days)', 'atw-semantic-search'); ?></h2>
        <?php if (empty($uploads_by_date)) : ?>
            <p><em><?php esc_html_e('No uploads for the selected period.', 'atw-semantic-search'); ?></em></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Date', 'atw-semantic-search'); ?></th>
                        <th><?php esc_html_e('Uploads', 'atw-semantic-search'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($uploads_by_date as $row) : 
                        $date = isset($row['date']) ? strtotime($row['date']) : false;
                    ?>
                        <tr>
                            <td><?php echo $date ? esc_html(date_i18n(get_option('date_format'), $date)) : ''; ?></td>
                            <td><?php echo esc_html($row['count']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <hr />

        <h2><?php esc_html_e('Uploads by IP Address', 'atw-semantic-search'); ?></h2>
        <?php if (empty($uploads_by_ip)) : ?>
            <p><em><?php esc_html_e('No IP data available.', 'atw-semantic-search'); ?></em></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('IP Address', 'atw-semantic-search'); ?></th>
                        <th><?php esc_html_e('Uploads', 'atw-semantic-search'); ?></th>
                        <th><?php esc_html_e('Last Upload', 'atw-semantic-search'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($uploads_by_ip as $row) : 
                        $date = isset($row['last_upload']) ? strtotime($row['last_upload']) : false;
                    ?>
                        <tr>
                            <td><?php echo esc_html($row['ip_address']); ?></td>
                            <td><?php echo esc_html($row['count']); ?></td>
                            <td><?php echo $date ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $date)) : ''; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <hr />

        <h2><?php esc_html_e('Uploads by Email', 'atw-semantic-search'); ?></h2>
        <?php if (empty($uploads_by_email)) : ?>
            <p><em><?php esc_html_e('No email data available.', 'atw-semantic-search'); ?></em></p>
        <?php else : ?>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Email', 'atw-semantic-search'); ?></th>
                        <th><?php esc_html_e('Uploads', 'atw-semantic-search'); ?></th>
                        <th><?php esc_html_e('Last Upload', 'atw-semantic-search'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($uploads_by_email as $row) : 
                        $date = isset($row['last_upload']) ? strtotime($row['last_upload']) : false;
                    ?>
                        <tr>
                            <td><?php echo esc_html($row['email']); ?></td>
                            <td><?php echo esc_html($row['count']); ?></td>
                            <td><?php echo $date ? esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $date)) : ''; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

    <?php endif; ?>

    <style>
        .atw-semantic-analytics-grid {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin: 20px 0;
        }
        .atw-semantic-analytics-card {
            flex: 1 1 180px;
            min-width: 180px;
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 16px 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,0.04);
        }
        .atw-semantic-analytics-card h3 {
            margin: 0 0 8px;
            font-size: 14px;
            text-transform: uppercase;
            color: #555d66;
        }
        .atw-semantic-analytics-value {
            margin: 0;
            font-size: 24px;
            font-weight: 600;
            color: #23282d;
        }
    </style>
</div>


