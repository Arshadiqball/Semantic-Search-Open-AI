/**
 * Frontend JavaScript for ATW Semantic Search Resume Plugin
 */

(function($) {
    'use strict';
    
    $(document).ready(function() {
        const $profileForm = $('#atw-semantic-profile-form');
        const $results = $('#atw-semantic-results');
        const $error = $('#atw-semantic-error');
        const $loading = $('#atw-semantic-loading');
        const $analysis = $('#atw-semantic-analysis');
        const $analysisSections = $('#atw-semantic-analysis-sections');
        const $actionPlan = $('#atw-semantic-action-plan');
        const $submitBtn = $('.atw-semantic-submit-btn');
        const $btnText = $('.atw-semantic-btn-text');
        const $btnLoader = $('.atw-semantic-btn-loader');
        const hasProfileForm = $profileForm.length > 0;

        // Jobs page: no upload form, just load jobs from stored profile
        if (!hasProfileForm && $results.length) {
            loadJobsFromProfile();
            return;
        }

        // Profile page: handle resume upload + profile save
        if (!hasProfileForm) {
            return;
        }

        // Handle profile form submission (with optional resume upload)
        $profileForm.on('submit', function(e) {
            e.preventDefault();
            
            // Reset UI
            $results.hide();
            $error.hide();
            $analysis.hide();
            $actionPlan.hide();
            
            const email = $('#atw_profile_email').val();
            const fileInputEl = $('#atw_profile_resume')[0];
            const hasFile = fileInputEl && fileInputEl.files && fileInputEl.files.length > 0;

            if (!email) {
                showError('Please enter your email.');
                return;
            }

            if (hasFile) {
                const file = fileInputEl.files[0];

                // Validate file type
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
                $loading.show();

                // Prepare form data for resume upload
                const formData = new FormData();
                formData.append('action', 'atw_upload_resume');
                formData.append('nonce', atwSemantic.nonce);
                formData.append('email', email);
                formData.append('resume', file);

                $.ajax({
                    url: atwSemantic.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            // Show results based on new upload
                            displayResults(response.data);
                            // Save profile with fresh resumeId + preferences
                            saveProfileAfterUpload(response.data);
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
                        $loading.hide();
                    }
                });
            } else {
                // No new resume, just save preferences using existing resume_id (if any)
                const existingResumeId = $('#atw_existing_resume_id').val() || null;
                saveProfileAfterUpload({ resumeId: existingResumeId });
            }
        });
        
        /**
         * Save profile (preferences + resumeId) after successful upload
         */
        function saveProfileAfterUpload(data) {
            const $profileFormEl = $('#atw-semantic-profile-form');
            if (!$profileFormEl.length) {
                return;
            }

            const resumeId = data && data.resumeId ? data.resumeId : null;

            const serialized = $profileFormEl.serializeArray();
            const payload = {
                action: 'atw_save_profile',
                nonce: atwSemantic.nonce,
                resume_id: resumeId
            };

            serialized.forEach(function(field) {
                if (field.name === 'atw_semantic_nonce') {
                    return;
                }
                if (payload[field.name] !== undefined) {
                    if (!Array.isArray(payload[field.name])) {
                        payload[field.name] = [payload[field.name]];
                    }
                    payload[field.name].push(field.value);
                } else {
                    payload[field.name] = field.value;
                }
            });

            $.post(atwSemantic.ajaxUrl, payload)
                .done(function(response) {
                    if (response && response.success) {
                        // Update stored resume id in the form if backend returned one
                        if (response.data && response.data.resumeId) {
                            $('#atw_existing_resume_id').val(response.data.resumeId);
                            $('#atw_profile_resume_status').text('Resume uploaded and linked to your profile.');
                        }

                        $('#atw_profile_status').text(
                            response.data && response.data.message
                                ? response.data.message
                                : 'Profile saved.'
                        );
                    } else if (response && response.data && response.data.message) {
                        $('#atw_profile_status').text(response.data.message);
                    }
                })
                .fail(function() {
                    $('#atw_profile_status').text('Failed to save profile. Please try again.');
                });
        }

        /**
         * Load jobs for current user from stored profile (jobs page)
         */
        function loadJobsFromProfile() {
            const $resultsContent = $('#atw-semantic-results-content');
            if ($resultsContent.length) {
                $resultsContent.html('<p>Loading personalised jobs...</p>');
            }

            $.ajax({
                url: atwSemantic.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'atw_get_profile_jobs',
                    nonce: atwSemantic.nonce
                },
                success: function(response) {
                    if (response && response.success) {
                        displayResults(response.data);
                    } else {
                        const msg = response && response.data && response.data.message
                            ? response.data.message
                            : 'Unable to load jobs. Please complete your profile first.';
                        showError(msg);
                    }
                },
                error: function() {
                    showError('Network error while loading jobs. Please try again.');
                }
            });
        }

        /**
         * Display job search results
         */
        function displayResults(data) {
            const $resultsContent = $('#atw-semantic-results-content');
            $resultsContent.empty();

            // Build analysis / sections UI if backend provided section info
            if (data.sections) {
                buildAnalysisSections(data.sections);
                $analysis.show();
            }

            // Always show action plan after analysis (profile page only)
            if ($actionPlan.length) {
                $actionPlan.show();
            }
            
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
         * Build section completeness rows (Personal, Military, Education, etc.)
         */
        function buildAnalysisSections(sections) {
            $analysisSections.empty();

            const config = [
                { key: 'personalDetails', label: 'Personal Details', optional: false },
                { key: 'militaryExperience', label: 'Military Experience', optional: true },
                { key: 'civilianExperience', label: 'Recent Civilian Experience', optional: true },
                { key: 'education', label: 'Education', optional: true },
                { key: 'skills', label: 'Skills', optional: false },
                { key: 'misc', label: 'Misc', optional: true },
            ];

            config.forEach(function (section) {
                const isComplete = !!sections[section.key];

                const $row = $('<div>').addClass('atw-semantic-section-row');
                const $left = $('<div>').addClass('atw-semantic-section-left');

                const $status = $('<div>')
                    .addClass('atw-semantic-section-status')
                    .addClass(isComplete ? 'complete' : 'missing')
                    .text(isComplete ? '✔' : '!');

                const $label = $('<div>').addClass('atw-semantic-section-title').text(section.label);

                if (section.optional) {
                    const $opt = $('<span>').addClass('atw-semantic-section-optional').text('(Optional)');
                    $label.append($opt);
                }

                $left.append($status).append($label);

                const $btn = $('<button>')
                    .attr('type', 'button')
                    .addClass('atw-semantic-section-action')
                    .toggleClass('secondary', isComplete)
                    .text(isComplete ? 'Edit' : 'Fill');

                $row.append($left).append($btn);
                $analysisSections.append($row);
            });
        }
        
        /**
         * Show error message
         */
        function showError(message) {
            $('.atw-semantic-error-content').text(message);
            $error.show();
            
            // Scroll to error
            $('html, body').animate({
                scrollTop: $error.offset().top - 100
            }, 500);
        }
    });
    
})(jQuery);

