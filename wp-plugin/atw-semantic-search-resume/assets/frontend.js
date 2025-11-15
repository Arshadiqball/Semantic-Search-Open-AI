/**
 * Frontend JavaScript for ATW Semantic Search Resume Plugin
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        const $form = $('#atw-semantic-resume-form');
        const $results = $('#atw-semantic-results');
        const $error = $('#atw-semantic-error');
        const $submitBtn = $('.atw-semantic-submit-btn');
        const $btnText = $('.atw-semantic-btn-text');
        const $btnLoader = $('.atw-semantic-btn-loader');
        
        // Handle form submission
        $form.on('submit', function(e) {
            e.preventDefault();
            
            // Reset UI
            $results.hide();
            $error.hide();
            
            // Validate form
            const email = $('#atw-semantic-email').val();
            const fileInput = $('#atw-semantic-resume-file')[0];
            
            if (!email || !fileInput.files.length) {
                showError('Please fill in all required fields.');
                return;
            }
            
            // Validate file type
            const file = fileInput.files[0];
            if (file.type !== 'application/pdf' && !file.name.toLowerCase().endsWith('.pdf')) {
                showError('Only PDF files are allowed.');
                return;
            }
            
            // Validate file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                showError('File size exceeds 5MB limit.');
                return;
            }
            
            // Show loading state
            $submitBtn.prop('disabled', true);
            $btnText.hide();
            $btnLoader.show();
            
            // Prepare form data
            const formData = new FormData();
            formData.append('action', 'atw_upload_resume');
            formData.append('nonce', atwSemantic.nonce);
            formData.append('email', email);
            formData.append('resume', file);
            
            // Send AJAX request
            $.ajax({
                url: atwSemantic.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        displayResults(response.data);
                    } else {
                        showError(response.data.message || 'An error occurred while processing your resume.');
                    }
                },
                error: function(xhr, status, error) {
                    showError('Network error. Please try again later.');
                    console.error('AJAX Error:', error);
                },
                complete: function() {
                    // Reset loading state
                    $submitBtn.prop('disabled', false);
                    $btnText.show();
                    $btnLoader.hide();
                }
            });
        });
        
        /**
         * Display job search results
         */
        function displayResults(data) {
            const $resultsContent = $('#atw-semantic-results-content');
            $resultsContent.empty();
            
            if (!data.matches || data.matches.length === 0) {
                $resultsContent.html('<p>No matching jobs found. Try adjusting your resume or search criteria.</p>');
                $results.show();
                return;
            }
            
            // Display match count
            const matchCount = data.matchCount || data.matches.length;
            const matchText = matchCount === 1 ? 'job found' : 'jobs found';
            
            // Create job cards
            data.matches.forEach(function(job) {
                const $card = $('<div>').addClass('atw-semantic-job-card');
                
                // Title and company
                const $title = $('<h4>').addClass('atw-semantic-job-title')
                    .text(job.title || 'Job Title');
                
                if (job.semanticSimilarity) {
                    const score = Math.round(job.semanticSimilarity * 100);
                    $title.append($('<span>').addClass('atw-semantic-match-score')
                        .text(score + '% Match'));
                }
                
                const $company = $('<div>').addClass('atw-semantic-job-company')
                    .text(job.company || 'Company Name');
                
                // Meta information
                const $meta = $('<div>').addClass('atw-semantic-job-meta');
                
                if (job.location) {
                    $meta.append($('<div>').addClass('atw-semantic-job-meta-item')
                        .html('<span>📍</span> ' + job.location));
                }
                
                if (job.experienceYears) {
                    $meta.append($('<div>').addClass('atw-semantic-job-meta-item')
                        .html('<span>💼</span> ' + job.experienceYears + ' years experience'));
                }
                
                if (job.employmentType) {
                    $meta.append($('<div>').addClass('atw-semantic-job-meta-item')
                        .html('<span>⏰</span> ' + job.employmentType));
                }
                
                if (job.salaryRange) {
                    $meta.append($('<div>').addClass('atw-semantic-job-meta-item')
                        .html('<span>💰</span> ' + job.salaryRange));
                }
                
                // Description
                const $description = $('<div>').addClass('atw-semantic-job-description')
                    .text(job.description ? job.description.substring(0, 300) + '...' : '');
                
                // Skills
                let $skills = $('<div>');
                if (job.requiredSkills && job.requiredSkills.length > 0) {
                    const $skillsContainer = $('<div>').addClass('atw-semantic-job-skills');
                    const $skillsLabel = $('<div>').addClass('atw-semantic-job-skills-label')
                        .text('Required Skills:');
                    const $skillTags = $('<div>').addClass('atw-semantic-skill-tags');
                    
                    job.requiredSkills.forEach(function(skill) {
                        $skillTags.append($('<span>').addClass('atw-semantic-skill-tag')
                            .text(skill));
                    });
                    
                    $skillsContainer.append($skillsLabel).append($skillTags);
                    $skills = $skillsContainer;
                }
                
                // Assemble card
                $card.append($title)
                    .append($company)
                    .append($meta)
                    .append($description)
                    .append($skills);
                
                $resultsContent.append($card);
            });
            
            // Show results
            $results.show();
            
            // Scroll to results
            $('html, body').animate({
                scrollTop: $results.offset().top - 100
            }, 500);
        }
        
        /**
         * Show error message
         */
        function showError(message) {
            $('#atw-semantic-error-content').text(message);
            $error.show();
            
            // Scroll to error
            $('html, body').animate({
                scrollTop: $error.offset().top - 100
            }, 500);
        }
    });
    
})(jQuery);

