<?php
/**
 * Frontend profile page template
 * - Preferences (job categories, tech stack, focus, transition stage)
 * - Resume upload (stored per logged-in user)
 */

if (!defined('ABSPATH')) {
    exit;
}

// Ensure user is logged in (checked in shortcode as well)
if (!is_user_logged_in()) {
    echo '<p>' . esc_html__('You must be logged in to access this page.', 'atw-semantic-search') . '</p>';
    return;
}

// Load existing profile if available
global $wpdb;
$user_id = get_current_user_id();
$profiles_table = $wpdb->prefix . 'atw_semantic_profiles';
$profile = $wpdb->get_row(
    $wpdb->prepare("SELECT * FROM $profiles_table WHERE user_id = %d", $user_id),
    ARRAY_A
);

$saved_categories = array();
$saved_tech_stack = array();
$saved_focus = '';
$saved_transition = '';
$current_resume_id = 0;

if ($profile) {
    $saved_categories = !empty($profile['job_categories']) ? json_decode($profile['job_categories'], true) : array();
    if (!is_array($saved_categories)) {
        $saved_categories = array();
    }
    $saved_tech_stack = !empty($profile['tech_stack']) ? json_decode($profile['tech_stack'], true) : array();
    if (!is_array($saved_tech_stack)) {
        $saved_tech_stack = array();
    }
    $saved_focus = isset($profile['focus']) ? $profile['focus'] : '';
    $saved_transition = isset($profile['transition_stage']) ? $profile['transition_stage'] : '';
    if (!empty($profile['resume_id'])) {
        $current_resume_id = (int) $profile['resume_id'];
    }
}

// Common job categories & tech stack suggestions (for UI only)
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

$common_tech_stack = array(
    'JavaScript', 'TypeScript', 'Python', 'Java', 'PHP', 'Ruby', 'Go', 'Rust',
    'React', 'Vue.js', 'Angular', 'Node.js', 'Express', 'Django', 'Laravel',
    'MySQL', 'PostgreSQL', 'MongoDB', 'Redis',
    'AWS', 'Azure', 'GCP', 'Docker', 'Kubernetes',
    'Git', 'CI/CD', 'Agile', 'Scrum',
);
?>

<div class="wrap atw-semantic-profile-wrap">
    <h1><?php echo esc_html($atts['title']); ?></h1>

    <form id="atw-semantic-profile-form" method="post" enctype="multipart/form-data">
        <?php wp_nonce_field('atw_semantic_nonce', 'atw_semantic_nonce'); ?>
        <input type="hidden"
               name="existing_resume_id"
               id="atw_existing_resume_id"
               value="<?php echo $current_resume_id ? intval($current_resume_id) : ''; ?>" />

        <div class="atw-semantic-section">
            <h2><?php esc_html_e('Basic Information', 'atw-semantic-search'); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="atw_profile_email"><?php esc_html_e('Email', 'atw-semantic-search'); ?></label></th>
                    <td>
                        <input type="email"
                               id="atw_profile_email"
                               name="email"
                               class="regular-text"
                               value="<?php echo esc_attr(wp_get_current_user()->user_email); ?>"
                               required />
                        <p class="description">
                            <?php esc_html_e('We use your email to link your profile and resume with the semantic search.', 'atw-semantic-search'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <!-- Action Plan questions (moved from jobs page to profile) -->
        <div id="atw-semantic-action-plan" class="atw-semantic-section atw-semantic-action-plan">
            <h2 class="atw-semantic-action-title"><?php esc_html_e('Action Plan', 'atw-semantic-search'); ?></h2>
            <p class="atw-semantic-action-subtitle">
                <?php esc_html_e('Answer a couple of quick questions to refine your profile. The more precise your answers, the better the suggestions.', 'atw-semantic-search'); ?>
            </p>

            <div class="atw-semantic-action-question">
                <div class="atw-semantic-action-q-label">
                    <?php esc_html_e('Where are you in your military-to-civilian transition journey?', 'atw-semantic-search'); ?>
                </div>
                <div class="atw-semantic-action-options">
                    <label class="atw-semantic-radio">
                        <input type="radio"
                               name="transition_stage"
                               value="pre_transition"
                            <?php checked($saved_transition, 'pre_transition'); ?> />
                        <span><?php esc_html_e('I’m getting ready to transition out of military service.', 'atw-semantic-search'); ?></span>
                    </label>
                    <label class="atw-semantic-radio">
                        <input type="radio"
                               name="transition_stage"
                               value="post_transition"
                            <?php checked($saved_transition, 'post_transition'); ?> />
                        <span><?php esc_html_e('I’ve already transitioned to civilian life.', 'atw-semantic-search'); ?></span>
                    </label>
                </div>
            </div>

            <div class="atw-semantic-action-question">
                <div class="atw-semantic-action-q-label">
                    <?php esc_html_e('What would you like to focus on first?', 'atw-semantic-search'); ?>
                    <span class="atw-semantic-badge"><?php esc_html_e('(Select one)', 'atw-semantic-search'); ?></span>
                </div>
                <div class="atw-semantic-action-options">
                    <label class="atw-semantic-radio">
                        <input type="radio"
                               name="focus"
                               value="job_search"
                            <?php checked($saved_focus, 'job_search'); ?> />
                        <span><?php esc_html_e('I’m looking for a new job 💼', 'atw-semantic-search'); ?></span>
                    </label>
                    <label class="atw-semantic-radio">
                        <input type="radio"
                               name="focus"
                               value="upskilling"
                            <?php checked($saved_focus, 'upskilling'); ?> />
                        <span><?php esc_html_e('I need help with upskilling or education 🎓', 'atw-semantic-search'); ?></span>
                    </label>
                </div>
            </div>
        </div>

        <div class="atw-semantic-section">
            <h2><?php esc_html_e('Job Categories', 'atw-semantic-search'); ?></h2>
            <p class="description">
                <?php esc_html_e('Select the types of roles you are most interested in.', 'atw-semantic-search'); ?>
            </p>
            <div class="atw-semantic-checkbox-group">
                <?php foreach ($common_categories as $category) : ?>
                    <label style="display:block;margin:4px 0;">
                        <input type="checkbox"
                               name="job_categories[]"
                               value="<?php echo esc_attr($category); ?>"
                               <?php checked(is_array($saved_categories) && in_array($category, $saved_categories, true)); ?> />
                        <?php echo esc_html($category); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="atw-semantic-section">
            <h2><?php esc_html_e('Tech Stack', 'atw-semantic-search'); ?></h2>
            <p class="description">
                <?php esc_html_e('List your core technologies and skills (one per line). These are used to match you with the best jobs.', 'atw-semantic-search'); ?>
            </p>
            <textarea id="atw_profile_tech_stack"
                      name="tech_stack"
                      rows="8"
                      class="large-text code"
                      placeholder="<?php esc_attr_e('JavaScript&#10;React&#10;Node.js&#10;Python&#10;...', 'atw-semantic-search'); ?>"><?php echo esc_textarea(is_array($saved_tech_stack) && !empty($saved_tech_stack) ? implode("\n", $saved_tech_stack) : ''); ?></textarea>
            <p class="description">
                <strong><?php esc_html_e('Suggestions:', 'atw-semantic-search'); ?></strong><br />
                <?php echo esc_html(implode(', ', $common_tech_stack)); ?>
            </p>
        </div>

        <div class="atw-semantic-section">
            <h2><?php esc_html_e('Upload Your Resume', 'atw-semantic-search'); ?></h2>
            <p class="description">
                <?php esc_html_e('Upload your latest resume. We will use it to understand your skills and experience for better job recommendations.', 'atw-semantic-search'); ?>
            </p>
            <input type="file" name="resume" id="atw_profile_resume" accept="application/pdf" />
            <p class="description"><?php esc_html_e('PDF only, max 5 MB.', 'atw-semantic-search'); ?></p>
            <p class="description" id="atw_profile_resume_status">
                <?php
                if ($current_resume_id) {
                    /* translators: %d: resume ID */
                    printf(
                        esc_html__('Resume is already uploaded and linked (ID: %d). Upload a new PDF to replace it.', 'atw-semantic-search'),
                        $current_resume_id
                    );
                } else {
                    esc_html_e('No resume uploaded yet. Upload a PDF to start getting personalised matches.', 'atw-semantic-search');
                }
                ?>
            </p>
        </div>

        <p>
            <button type="submit" class="button button-primary" id="atw_profile_save_btn">
                <?php esc_html_e('Save Profile & Update Recommendations', 'atw-semantic-search'); ?>
            </button>
            <span id="atw_profile_status" style="margin-left:10px;"></span>
        </p>
    </form>
</div>


