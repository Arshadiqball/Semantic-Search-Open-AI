<?php
/**
 * Job Search Frontend Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$title = isset($atts['title']) ? $atts['title'] : 'Find Your Dream Job';
$show_upload = isset($atts['show_upload']) && $atts['show_upload'] === 'yes';
?>

<div class="atw-semantic-job-search-container">
    <div class="atw-semantic-job-search-wrapper">
        <h2 class="atw-semantic-title"><?php echo esc_html($title); ?></h2>
        
        <?php if ($show_upload): ?>
        <div class="atw-semantic-upload-section">
            <h3><?php _e('Upload Your Resume', 'atw-semantic-search'); ?></h3>
            <p class="atw-semantic-description">
                <?php _e('Upload your resume to get AI-powered job recommendations based on your skills and experience.', 'atw-semantic-search'); ?>
            </p>
            
            <form id="atw-semantic-resume-form" class="atw-semantic-form">
                <div class="atw-semantic-form-group">
                    <label for="atw-semantic-email">
                        <?php _e('Email Address', 'atw-semantic-search'); ?>
                        <span class="atw-semantic-required">*</span>
                    </label>
                    <input type="email" 
                           id="atw-semantic-email" 
                           name="email" 
                           class="atw-semantic-input" 
                           required 
                           placeholder="<?php _e('your.email@example.com', 'atw-semantic-search'); ?>" />
                </div>
                
                <div class="atw-semantic-form-group">
                    <label for="atw-semantic-resume-file">
                        <?php _e('Resume (PDF)', 'atw-semantic-search'); ?>
                        <span class="atw-semantic-required">*</span>
                    </label>
                    <input type="file" 
                           id="atw-semantic-resume-file" 
                           name="resume" 
                           accept=".pdf,application/pdf" 
                           class="atw-semantic-file-input" 
                           required />
                    <p class="atw-semantic-help-text">
                        <?php _e('Maximum file size: 5MB. Only PDF files are accepted.', 'atw-semantic-search'); ?>
                    </p>
                </div>
                
                <button type="submit" class="atw-semantic-submit-btn">
                    <span class="atw-semantic-btn-text"><?php _e('Find Matching Jobs', 'atw-semantic-search'); ?></span>
                    <span class="atw-semantic-btn-loader" style="display: none;">
                        <span class="atw-semantic-spinner"></span>
                        <?php _e('Processing...', 'atw-semantic-search'); ?>
                    </span>
                </button>
            </form>
        </div>
        <?php endif; ?>
        
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

