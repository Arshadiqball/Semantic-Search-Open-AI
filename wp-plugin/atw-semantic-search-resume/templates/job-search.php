<?php
/**
 * Job Search Frontend Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$title = isset($atts['title']) ? $atts['title'] : 'Find Your Dream Job';
?>

<div class="atw-semantic-job-search-container">
    <div class="atw-semantic-job-search-wrapper">
        <h2 class="atw-semantic-title"><?php echo esc_html($title); ?></h2>
        
        <!-- Jobs page is read-only; upload is handled on the profile page -->

        <!-- Loading / analysing state -->
        <div id="atw-semantic-loading" class="atw-semantic-loading" style="display:none;">
            <div class="atw-semantic-loading-icon">✨</div>
            <h3 class="atw-semantic-loading-title">
                <?php _e('Almost there! We’re analysing your CV to personalise your journey.', 'atw-semantic-search'); ?>
            </h3>
            <div class="atw-semantic-loading-bar">
                <div class="atw-semantic-loading-bar-inner"></div>
            </div>
            <p class="atw-semantic-loading-note">
                <?php _e('Did you know? Limiting your CV to one or two pages increases the likelihood of it being read thoroughly by hiring managers.', 'atw-semantic-search'); ?>
            </p>
        </div>

        <!-- Analysis summary (sections completeness) -->
        <div id="atw-semantic-analysis" class="atw-semantic-analysis" style="display:none;">
            <h3 class="atw-semantic-analysis-title">
                <?php _e('We’ve successfully analysed your CV! However some sections are missing.', 'atw-semantic-search'); ?>
            </h3>
            <p class="atw-semantic-analysis-subtitle">
                <?php _e('We recommend filling in the empty sections to get more precise recommendations.', 'atw-semantic-search'); ?>
            </p>
            <div id="atw-semantic-analysis-sections" class="atw-semantic-analysis-sections"></div>
        </div>

        <!-- Results Section -->
        <div id="atw-semantic-results" class="atw-semantic-results" style="display: none;">
            <h3><?php _e('Recommended Jobs', 'atw-semantic-search'); ?></h3>
            <div id="atw-semantic-results-content" class="atw-semantic-results-content"></div>
        </div>
        
        <!-- Error Section -->
        <div id="atw-semantic-error" class="atw-semantic-error" style="display: none;">
            <div class="atw-semantic-error-content"></div>
        </div>
    </div>
</div>

