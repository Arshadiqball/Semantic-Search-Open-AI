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

        <!-- Action Plan questions -->
        <div id="atw-semantic-action-plan" class="atw-semantic-action-plan" style="display:none;">
            <h3 class="atw-semantic-action-title"><?php _e('Action Plan', 'atw-semantic-search'); ?></h3>
            <p class="atw-semantic-action-subtitle">
                <?php _e('Answer a couple of quick questions to refine your profile. The more precise your answers, the better the suggestions.', 'atw-semantic-search'); ?>
            </p>

            <div class="atw-semantic-action-question">
                <div class="atw-semantic-action-q-label">
                    <?php _e('Where are you in your military-to-civilian transition journey?', 'atw-semantic-search'); ?>
                </div>
                <div class="atw-semantic-action-options">
                    <label class="atw-semantic-radio">
                        <input type="radio" name="atw_q_transition" value="pre_transition">
                        <span><?php _e('I’m getting ready to transition out of military service.', 'atw-semantic-search'); ?></span>
                    </label>
                    <label class="atw-semantic-radio">
                        <input type="radio" name="atw_q_transition" value="post_transition">
                        <span><?php _e('I’ve already transitioned to civilian life.', 'atw-semantic-search'); ?></span>
                    </label>
                </div>
            </div>

            <div class="atw-semantic-action-question">
                <div class="atw-semantic-action-q-label">
                    <?php _e('What would you like to focus on first?', 'atw-semantic-search'); ?>
                    <span class="atw-semantic-badge"><?php _e('(Select one)', 'atw-semantic-search'); ?></span>
                </div>
                <div class="atw-semantic-action-options">
                    <label class="atw-semantic-radio">
                        <input type="radio" name="atw_q_focus" value="job_search">
                        <span><?php _e('I’m looking for a new job 💼', 'atw-semantic-search'); ?></span>
                    </label>
                    <label class="atw-semantic-radio">
                        <input type="radio" name="atw_q_focus" value="upskilling">
                        <span><?php _e('I need help with upskilling or education 🎓', 'atw-semantic-search'); ?></span>
                    </label>
                </div>
            </div>
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

