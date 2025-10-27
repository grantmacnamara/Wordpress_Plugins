jQuery(document).ready(function($) {
    // Tab switching
    $('.nav-tab').on('click', function() {
        const tab = $(this).data('tab');
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.tab-content').removeClass('active');
        $('#' + tab).addClass('active');
    });

    // Single product description generation
    $('.generate-single-description').on('click', function() {
        const button = $(this);
        const productId = button.data('product-id');
        const modal = $('#whisky-debug-modal');
        const debugSteps = modal.find('.debug-steps');
        const debugResponse = modal.find('.debug-response');
        const spinner = modal.find('.whisky-spinner-container');
        const debugContent = modal.find('.whisky-debug-content');

        // Show modal and clear previous content
        modal.css('display', 'block');
        debugSteps.empty();
        debugResponse.empty();
        debugContent.hide();
        spinner.show();

        button.prop('disabled', true).text('Generating...');
        
        addDebugStep('Starting description generation...', 'info');

        $.ajax({
            url: whiskyAiSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_whisky_descriptions',
                nonce: whiskyAiSettings.nonce,
                product_ids: [productId]
            },
            success: function(response) {
                if (response.success) {
                    // Auto-close modal and reload
                    modal.css('display', 'none');
                    location.reload();
                } else {
                    let errorMessage = (response.data && response.data.message) ? response.data.message : 'An unknown error occurred.';
                    addDebugStep('Error: ' + errorMessage, 'error');
                    if (response.data && response.data.errors) {
                        debugResponse.text(JSON.stringify(response.data.errors, null, 2));
                    }
                }
            },
            error: function(xhr, status, error) {
                addDebugStep('Ajax error: ' + error, 'error');
                debugResponse.text(xhr.responseText);
            },
            complete: function(jqXHR, textStatus) {
                spinner.hide();
                debugContent.show();
                if (textStatus !== 'success') {
                    button.prop('disabled', false).text('Generate');
                }
            }
        });
    });

    // Single product categories generation
    $('.generate-single-categories').on('click', function() {
        const button = $(this);
        const productId = button.data('product-id');
        const modal = $('#whisky-debug-modal');
        const debugSteps = modal.find('.debug-steps');
        const debugResponse = modal.find('.debug-response');
        const spinner = modal.find('.whisky-spinner-container');
        const debugContent = modal.find('.whisky-debug-content');

        // Show modal and clear previous content
        modal.css('display', 'block');
        debugSteps.empty();
        debugResponse.empty();
        debugContent.hide();
        spinner.show();

        button.prop('disabled', true).text('Generating...');
        
        addDebugStep('Starting category generation...', 'info');

        $.ajax({
            url: whiskyAiSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_whisky_categories',
                nonce: whiskyAiSettings.nonce,
                product_ids: [productId]
            },
            success: function(response) {
                if (response.success) {
                    addDebugStep('Categories generated successfully!', 'success');

                    // Auto-close modal and reload
                    modal.css('display', 'none');
                    location.reload();
                } else {
                    let errorMessage = (response.data && response.data.message) ? response.data.message : 'An unknown error occurred.';
                    addDebugStep('Error: ' + errorMessage, 'error');
                    if (response.data && response.data.errors) {
                        debugResponse.text(JSON.stringify(response.data.errors, null, 2));
                    }
                }
            },
            error: function(xhr, status, error) {
                addDebugStep('Ajax error: ' + error, 'error');
                debugResponse.text(xhr.responseText);
            },
            complete: function(jqXHR, textStatus) {
                spinner.hide();
                debugContent.show();
                if (textStatus !== 'success') {
                    button.prop('disabled', false).text('Generate');
                }
            }
        });
    });

    // Generate All (Description and Categories) for a single product
    $('.generate-all-single').on('click', function() {
        const button = $(this);
        const productId = button.data('product-id');
        const modal = $('#whisky-debug-modal');
        const debugContent = modal.find('.whisky-debug-content');
        const spinner = modal.find('.whisky-spinner-container');
        const debugSteps = modal.find('.debug-steps');
        const otherButtons = $('.generate-single-description, .generate-single-categories');

        // Show modal and prepare for generation
        modal.css('display', 'block');
        debugSteps.empty();
        debugContent.hide();
        spinner.show();
        
        button.prop('disabled', true).text('Updating...');
        otherButtons.prop('disabled', true);

        // 1. Generate Description
        $.ajax({
            url: whiskyAiSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_whisky_descriptions',
                nonce: whiskyAiSettings.nonce,
                product_ids: [productId]
            }
        })
        .then(function(descResponse) {
            if (!descResponse.success) {
                return $.Deferred().reject(descResponse);
            }
            addDebugStep('✔ Description generated successfully!', 'success');
            
            // 2. Generate Categories
            return $.ajax({
                url: whiskyAiSettings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'generate_whisky_categories',
                    nonce: whiskyAiSettings.nonce,
                    product_ids: [productId]
                }
            });
        })
        .then(function(catResponse) {
            if (!catResponse.success) {
                return $.Deferred().reject(catResponse);
            }
            addDebugStep('✔ Categories generated successfully!', 'success');
            const catNames = catResponse.data.results[productId].category_names;
            if (catNames && catNames.length > 0) {
                addDebugStep('Added categories: ' + catNames.join(', '), 'info');
            }
            
            addDebugStep('Update complete! Reloading...', 'success');

            // 3. Reload page
            modal.css('display', 'none');
            location.reload();
        })
        .fail(function(response) {
            let errorMessage = 'An unknown error occurred.';
            if (response && response.data && response.data.message) {
                errorMessage = response.data.message;
            } else if (response && response.data) {
                errorMessage = response.data;
            }
            addDebugStep('Error: ' + errorMessage, 'error');
        })
        .always(function(response, status) {
            spinner.hide();
            debugContent.show();
            // Re-enable buttons only on failure
            if (status === 'error' || (response && response.success === false)) {
                button.prop('disabled', false).text('Update All');
                otherButtons.prop('disabled', false);
            }
        });
    });

    // Modal close buttons
    $('.whisky-modal-close, .whisky-modal-close-btn').on('click', function() {
        $(this).closest('.whisky-modal').css('display', 'none');
    });

    // Close modal when clicking outside
    $(window).on('click', function(event) {
        if ($(event.target).hasClass('whisky-modal')) {
            $('.whisky-modal').css('display', 'none');
        }
    });

    function addDebugStep(message, type = 'info') {
        const debugSteps = $('.debug-steps');
        const step = $('<div>')
            .addClass('debug-step')
            .addClass(type)
            .text(message);
        debugSteps.append(step);
        if (debugSteps.length && debugSteps[0]) {
            debugSteps.scrollTop(debugSteps[0].scrollHeight);
        }
    }

    // Tab switching
    $('.nav-tab').on('click', function() {
        const tab = $(this).data('tab');
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.tab-content').removeClass('active');
        $('#' + tab).addClass('active');
    });

    // --- Bulk Generation ---
    $('#generate-all-descriptions').on('click', function() {
        if (confirm('Are you sure you want to generate descriptions for all products? This may be slow and expensive.')) {
            startBulkGeneration(false, 'description');
        }
    });

    $('#generate-remaining-descriptions').on('click', function() {
        startBulkGeneration(true, 'description');
    });

    $('#generate-all-categories').on('click', function() {
        if (confirm('Are you sure you want to generate categories for all products? This may be slow and expensive.')) {
            startBulkGeneration(false, 'category');
        }
    });

    $('#generate-remaining-categories').on('click', function() {
        startBulkGeneration(true, 'category');
    });

    $('#fix-all-missing').on('click', function() {
        const modal = $('#whisky-debug-modal');
        const debugSteps = modal.find('.debug-steps');
        const spinner = modal.find('.whisky-spinner-container');
        const debugContent = modal.find('.whisky-debug-content');
        const allButtons = $('#generate-all-descriptions, #generate-remaining-descriptions, #generate-all-categories, #generate-remaining-categories, #fix-all-missing');

        // Show modal and prepare for generation
        modal.css('display', 'block');
        debugSteps.empty();
        debugContent.hide();
        spinner.show();

        allButtons.prop('disabled', true);
        addDebugStep('Starting to fix all missing descriptions and categories...', 'info');

        $.ajax({
            url: whiskyAiSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'fix_all_missing',
                nonce: whiskyAiSettings.nonce
            },
            success: function(response) {
                if (response.success) {
                    addDebugStep(response.data + ' Reloading...', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    addDebugStep('An error occurred: ' + (response.data || 'Unknown error'), 'error');
                }
            },
            error: function() {
                addDebugStep('A critical error occurred. Check the browser console.', 'error');
            },
            complete: function(jqXHR) {
                spinner.hide();
                debugContent.show();
                if (!jqXHR.responseJSON || !jqXHR.responseJSON.success) {
                    allButtons.prop('disabled', false);
                }
            }
        });
    });

    function startBulkGeneration(remainingOnly, type) {
        const modal = $('#whisky-debug-modal');
        const debugSteps = modal.find('.debug-steps');
        const spinner = modal.find('.whisky-spinner-container');
        const debugContent = modal.find('.whisky-debug-content');
        const allButtons = $('#generate-all-descriptions, #generate-remaining-descriptions, #generate-all-categories, #generate-remaining-categories, #fix-all-missing');

        // Show modal and prepare for generation
        modal.css('display', 'block');
        debugSteps.empty();
        debugContent.hide();
        spinner.show();
        
        allButtons.prop('disabled', true);
        addDebugStep('Fetching products to process...', 'info');

        $.ajax({
            url: whiskyAiSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_whisky_products',
                nonce: whiskyAiSettings.nonce,
                remaining_only: remainingOnly,
                generation_type: type
            },
            success: function(response) {
                if (response.success) {
                    const products = response.data;
                    const productIds = products.map(p => p.id);
                    addDebugStep(`Found ${productIds.length} products. Starting generation...`, 'info');
                    if (type === 'description') {
                        generateDescriptionsInBulk(productIds);
                    } else {
                        generateCategoriesInBulk(productIds);
                    }
                } else {
                    addDebugStep('Error: ' + (response.data || 'Could not fetch products.'), 'error');
                    spinner.hide();
                    debugContent.show();
                    allButtons.prop('disabled', false);
                }
            },
            error: function() {
                addDebugStep('An error occurred while fetching products.', 'error');
                spinner.hide();
                debugContent.show();
                allButtons.prop('disabled', false);
            }
        });
    }

    function generateDescriptionsInBulk(productIds) {
        const modal = $('#whisky-debug-modal');
        const spinner = modal.find('.whisky-spinner-container');
        const debugContent = modal.find('.whisky-debug-content');
        const allButtons = $('#generate-all-descriptions, #generate-remaining-descriptions, #generate-all-categories, #generate-remaining-categories, #fix-all-missing');

        $.ajax({
            url: whiskyAiSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_whisky_descriptions',
                nonce: whiskyAiSettings.nonce,
                product_ids: productIds
            },
            success: function(response) {
                if (response.success) {
                    addDebugStep('All descriptions generated successfully! Reloading...', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    let errorMessage = (response.data && response.data.message) ? response.data.message : 'Some descriptions failed to update.';
                    addDebugStep(errorMessage, 'error');
                    if (response.data && response.data.errors) {
                        $('.debug-response').text(JSON.stringify(response.data.errors, null, 2));
                    }
                }
            },
            error: function(xhr) {
                addDebugStep('A critical error occurred during bulk generation.', 'error');
                $('.debug-response').text(xhr.responseText);
            },
            complete: function(jqXHR) {
                spinner.hide();
                debugContent.show();
                if (!jqXHR.responseJSON || !jqXHR.responseJSON.success) {
                    allButtons.prop('disabled', false);
                }
            }
        });
    }

    function generateCategoriesInBulk(productIds) {
        const modal = $('#whisky-debug-modal');
        const spinner = modal.find('.whisky-spinner-container');
        const debugContent = modal.find('.whisky-debug-content');
        const allButtons = $('#generate-all-descriptions, #generate-remaining-descriptions, #generate-all-categories, #generate-remaining-categories, #fix-all-missing');

        $.ajax({
            url: whiskyAiSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_whisky_categories',
                nonce: whiskyAiSettings.nonce,
                product_ids: productIds
            },
            success: function(response) {
                if (response.success) {
                    addDebugStep('All categories generated successfully! Reloading...', 'success');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    let errorMessage = (response.data && response.data.message) ? response.data.message : 'Some categories failed to update.';
                    addDebugStep(errorMessage, 'error');
                    if (response.data && response.data.errors) {
                        $('.debug-response').text(JSON.stringify(response.data.errors, null, 2));
                    }
                }
            },
            error: function(xhr) {
                addDebugStep('A critical error occurred during bulk generation.', 'error');
                $('.debug-response').text(xhr.responseText);
            },
            complete: function(jqXHR) {
                spinner.hide();
                debugContent.show();
                if (!jqXHR.responseJSON || !jqXHR.responseJSON.success) {
                    allButtons.prop('disabled', false);
                }
            }
        });
    }

    // Stats page functionality
    if ($('#whisky-stats').length) {
        var loadStats = function() { // Make it available in the scope
            $.ajax({
                url: whiskyAiSettings.ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_whisky_stats',
                    nonce: whiskyAiSettings.nonce
                },
                success: function(response) {
                    if (response.success) {
                        updateStats(response.data);
                    }
                }
            });
        }

        function updateStats(stats) {
            const statsContainer = $('#whisky-stats');
            const totalMissing = stats.missing_descriptions + stats.missing_categories;
            const totalPossible = stats.total * 2;
            const completed = totalPossible - totalMissing;
            const percentage = (totalPossible > 0) ? Math.round((completed / totalPossible) * 100) : 0;
            
            statsContainer.html(`
                <p style="font-size: 16px; margin: 10px 0;"><strong>Total products:</strong> ${stats.total}</p>
                <p style="font-size: 16px; margin: 10px 0;"><strong>Missing Descriptions:</strong> ${stats.missing_descriptions}</p>
                <p style="font-size: 16px; margin: 10px 0;"><strong>Missing Categories:</strong> ${stats.missing_categories}</p>
                <div class="progress-bar" style="margin-top: 20px; background: #f0f0f0; height: 20px; border: 1px solid #ccc;">
                    <div style="width: ${percentage}%; background: #0073aa; height: 100%;"></div>
                </div>
                <p style="text-align: center; margin-top: 5px;">${percentage}% Complete</p>
            `);
        }

        // Initial load
        loadStats();

        // Refresh stats every 30 seconds
        setInterval(loadStats, 30000);
    }

    // Tab switching
    $('.nav-tab').on('click', function() {
        const tab = $(this).data('tab');
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.tab-content').removeClass('active');
        $('#' + tab).addClass('active');
    });
}); 