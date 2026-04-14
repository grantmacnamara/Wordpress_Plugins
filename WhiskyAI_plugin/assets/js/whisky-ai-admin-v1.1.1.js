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
        const productName = button.closest('tr').find('td').eq(1).text() || 'Unknown';
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
        
        addDebugStep('Selected product: ' + productName + ' (ID: ' + productId + ')', 'info');
        addDebugStep('Starting description generation...', 'info');
        console.log('[WhiskyAI] Selected product:', productName, 'ID:', productId);

        $.ajax({
            url: whiskyAiSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_whisky_descriptions',
                nonce: whiskyAiSettings.nonce,
                product_ids: [productId]
            },
            beforeSend: function() {
                addDebugStep('Calling API: generate_whisky_descriptions', 'info');
                console.log('[WhiskyAI] API call started for descriptions', {product_ids: [productId]});
            },
            success: function(response) {
                console.log('[WhiskyAI] API response received:', response);
                addDebugStep('API response received (Status: success)', 'info');
                
                if (response.success) {
                    addDebugStep('Description generated: ' + response.data.results[productId].description.substring(0, 100) + '...', 'success');
                    console.log('[WhiskyAI] Description generated successfully');
                    addDebugStep('Reloading page...', 'info');
                    setTimeout(() => {
                        // Auto-close modal and reload
                        modal.css('display', 'none');
                        location.reload();
                    }, 1000);
                } else {
                    let errorMessage = (response.data && response.data.message) ? response.data.message : 'An unknown error occurred.';
                    addDebugStep('Error: ' + errorMessage, 'error');
                    console.error('[WhiskyAI] API error:', response.data);
                    if (response.data && response.data.errors) {
                        debugResponse.text(JSON.stringify(response.data.errors, null, 2));
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('[WhiskyAI] AJAX error:', {status: status, error: error, xhr: xhr});
                addDebugStep('AJAX error: ' + error + ' (Status: ' + status + ')', 'error');
                debugResponse.text('Status Code: ' + xhr.status + '\nResponse:\n' + xhr.responseText);
            },
            complete: function(jqXHR, textStatus) {
                addDebugStep('Request complete (Status: ' + textStatus + ')', 'info');
                console.log('[WhiskyAI] Request complete:', textStatus);
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
        const productName = button.closest('tr').find('td').eq(1).text() || 'Unknown';
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
        
        addDebugStep('Selected product: ' + productName + ' (ID: ' + productId + ')', 'info');
        addDebugStep('Starting category generation...', 'info');
        console.log('[WhiskyAI] Selected product for categories:', productName, 'ID:', productId);

        $.ajax({
            url: whiskyAiSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_whisky_categories',
                nonce: whiskyAiSettings.nonce,
                product_ids: [productId]
            },
            beforeSend: function() {
                addDebugStep('Calling API: generate_whisky_categories', 'info');
                console.log('[WhiskyAI] API call started for categories', {product_ids: [productId]});
            },
            success: function(response) {
                console.log('[WhiskyAI] API response received:', response);
                addDebugStep('API response received (Status: success)', 'info');
                
                if (response.success) {
                    const categories = response.data.results[productId].category_names || [];
                    addDebugStep('Categories generated: ' + categories.join(', '), 'success');
                    console.log('[WhiskyAI] Categories generated successfully:', categories);
                    addDebugStep('Reloading page...', 'info');
                    setTimeout(() => {
                        modal.css('display', 'none');
                        location.reload();
                    }, 1000);
                } else {
                    let errorMessage = (response.data && response.data.message) ? response.data.message : 'An unknown error occurred.';
                    addDebugStep('Error: ' + errorMessage, 'error');
                    console.error('[WhiskyAI] API error:', response.data);
                    if (response.data && response.data.errors) {
                        debugResponse.text(JSON.stringify(response.data.errors, null, 2));
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('[WhiskyAI] AJAX error:', {status: status, error: error, xhr: xhr});
                addDebugStep('AJAX error: ' + error + ' (Status: ' + status + ')', 'error');
                debugResponse.text('Status Code: ' + xhr.status + '\nResponse:\n' + xhr.responseText);
            },
            complete: function(jqXHR, textStatus) {
                addDebugStep('Request complete (Status: ' + textStatus + ')', 'info');
                console.log('[WhiskyAI] Request complete:', textStatus);
                spinner.hide();
                debugContent.show();
                if (textStatus !== 'success') {
                    button.prop('disabled', false).text('Generate');
                }
            }
        });
    });
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
        const productName = button.closest('tr').find('td').eq(1).text() || 'Unknown';
        const modal = $('#whisky-debug-modal');
        const debugContent = modal.find('.whisky-debug-content');
        const debugSteps = modal.find('.debug-steps');
        const spinner = modal.find('.whisky-spinner-container');
        const otherButtons = $('.generate-single-description, .generate-single-categories');

        // Show modal and prepare for generation
        modal.css('display', 'block');
        debugSteps.empty();
        debugContent.hide();
        spinner.show();
        
        button.prop('disabled', true).text('Updating...');
        otherButtons.prop('disabled', true);

        addDebugStep('Selected product: ' + productName + ' (ID: ' + productId + ')', 'info');
        addDebugStep('Step 1: Generating description...', 'info');
        console.log('[WhiskyAI] Starting generate all for product:', productName, 'ID:', productId);

        // 1. Generate Description
        $.ajax({
            url: whiskyAiSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_whisky_descriptions',
                nonce: whiskyAiSettings.nonce,
                product_ids: [productId]
            },
            beforeSend: function() {
                addDebugStep('Calling API: generate_whisky_descriptions', 'info');
            },
            success: function(descResponse) {
                console.log('[WhiskyAI] Description response:', descResponse);
                if (!descResponse.success) {
                    addDebugStep('Description generation failed', 'error');
                    return $.Deferred().reject(descResponse);
                }
                addDebugStep('Description generated successfully!', 'success');
                if (descResponse.data.results[productId]) {
                    addDebugStep('  ' + descResponse.data.results[productId].description.substring(0, 80) + '...', 'info');
                }
                
                addDebugStep('Step 2: Generating categories...', 'info');
                
                // 2. Generate Categories
                return $.ajax({
                    url: whiskyAiSettings.ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'generate_whisky_categories',
                        nonce: whiskyAiSettings.nonce,
                        product_ids: [productId]
                    },
                    beforeSend: function() {
                        addDebugStep('Calling API: generate_whisky_categories', 'info');
                    }
                });
            }
        })
        .then(function(catResponse) {
            console.log('[WhiskyAI] Categories response:', catResponse);
            if (!catResponse.success) {
                addDebugStep('Category generation failed', 'error');
                return $.Deferred().reject(catResponse);
            }
            addDebugStep('Categories generated successfully!', 'success');
            const catNames = catResponse.data.results[productId].category_names;
            if (catNames && catNames.length > 0) {
                addDebugStep('Added categories: ' + catNames.join(', '), 'info');
            }
            
            addDebugStep('All updates complete! Reloading page...', 'success');

            // 3. Reload page
            modal.css('display', 'none');
            location.reload();
        })
        .fail(function(response) {
            console.error('[WhiskyAI] Generate all failed:', response);
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
        const timestamp = new Date().toLocaleTimeString('en-US', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const fullMessage = '[' + timestamp + '] ' + message;
        const debugSteps = $('.debug-steps');
        const step = $('<div>')
            .addClass('debug-step')
            .addClass(type)
            .text(fullMessage);
        debugSteps.append(step);
        console.log('[WhiskyAI] [' + type.toUpperCase() + '] ' + fullMessage);
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
        console.log('[WhiskyAI] Fix all missing started');

        $.ajax({
            url: whiskyAiSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'fix_all_missing',
                nonce: whiskyAiSettings.nonce
            },
            beforeSend: function() {
                addDebugStep('Calling API: fix_all_missing', 'info');
            },
            success: function(response) {
                console.log('[WhiskyAI] Fix all response:', response);
                if (response.success) {
                    addDebugStep(response.data, 'success');
                    addDebugStep('Reloading page in 1.5 seconds...', 'info');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    addDebugStep('An error occurred: ' + (response.data || 'Unknown error'), 'error');
                    console.error('[WhiskyAI] Fix all error:', response.data);
                }
            },
            error: function(xhr, status, error) {
                console.error('[WhiskyAI] Fix all AJAX error:', {status, error, xhr});
                addDebugStep('A critical error occurred: ' + error + ' (Status: ' + status + ')', 'error');
                if (xhr.responseText) {
                    $('.debug-response').text('Status Code: ' + xhr.status + '\nResponse:\n' + xhr.responseText);
                }
            },
            complete: function(jqXHR) {
                addDebugStep('Fix all request complete', 'info');
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
        const filterText = remainingOnly ? 'remaining' : 'all';
        const typeText = type === 'description' ? 'descriptions' : 'categories';
        addDebugStep('Starting bulk ' + typeText + ' generation (' + filterText + ')...', 'info');
        console.log('[WhiskyAI] Starting bulk generation:', {remainingOnly, type});

        $.ajax({
            url: whiskyAiSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'get_whisky_products',
                nonce: whiskyAiSettings.nonce,
                remaining_only: remainingOnly,
                generation_type: type
            },
            beforeSend: function() {
                addDebugStep('Fetching products from server...', 'info');
            },
            success: function(response) {
                console.log('[WhiskyAI] Product list response:', response);
                if (response.success) {
                    const products = response.data;
                    const productIds = products.map(p => p.id);
                    addDebugStep('Found ' + productIds.length + ' products to process:', 'success');
                    products.forEach(p => {
                        addDebugStep('  - ID: ' + p.id + ', Name: ' + p.name, 'info');
                    });
                    addDebugStep('Starting generation process...', 'info');
                    if (type === 'description') {
                        generateDescriptionsInBulk(productIds);
                    } else {
                        generateCategoriesInBulk(productIds);
                    }
                } else {
                    addDebugStep('Error fetching products: ' + (response.data || 'Could not fetch products.'), 'error');
                    console.error('[WhiskyAI] Error fetching products:', response.data);
                    spinner.hide();
                    debugContent.show();
                    allButtons.prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                console.error('[WhiskyAI] Error fetching products:', {status, error, xhr});
                addDebugStep('Error fetching products: ' + error + ' (Status: ' + status + ')', 'error');
                spinner.hide();
                debugContent.show();
                allButtons.prop('disabled', false);
            }
        });
    }

    function generateDescriptionsInBulk(productIds) {
        const modal = $('#whisky-debug-modal');
        const spinner = modal.find('.whisky-spinner-container');
        addDebugStep('Processing ' + productIds.length + ' products for descriptions', 'info');
        console.log('[WhiskyAI] Bulk generation started for product IDs:', productIds);

        $.ajax({
            url: whiskyAiSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_whisky_descriptions',
                nonce: whiskyAiSettings.nonce,
                product_ids: productIds
            },
            beforeSend: function() {
                addDebugStep('Sending bulk description request to API...', 'info');
            },
            success: function(response) {
                console.log('[WhiskyAI] Bulk API response:', response);
                addDebugStep('API response received', 'info');
                
                if (response.success) {
                    const resultCount = Object.keys(response.data.results || {}).length;
                    addDebugStep('Successfully generated descriptions for ' + resultCount + ' products', 'success');
                    if (response.data.errors && Object.keys(response.data.errors).length > 0) {
                        addDebugStep('Errors occurred for ' + Object.keys(response.data.errors).length + ' products:', 'warning');
                        $.each(response.data.errors, function(productId, error) {
                            addDebugStep('  - Product ' + productId + ': ' + error, 'error');
                        });
                    }
                    addDebugStep('Reloading page...', 'info');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    let errorMessage = (response.data && response.data.message) ? response.data.message : 'Some descriptions failed to update.';
                    addDebugStep(errorMessage, 'error');
                    console.error('[WhiskyAI] API error:', response.data);
                    if (response.data && response.data.errors) {
                        $('.debug-response').text(JSON.stringify(response.data.errors, null, 2));
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('[WhiskyAI] AJAX error during bulk generation:', {status: status, error: error});
                addDebugStep('Critical error: ' + error + ' (Status: ' + status + ')', 'error');
                $('.debug-response').text('Status Code: ' + xhr.status + '\nResponse:\n' + xhr.responseText);
            },
            complete: function(jqXHR) {
                addDebugStep('Bulk generation request complete', 'info');l error occurred during bulk generation.', 'error');
                $('.debug-response').text(xhr.responseText);
            },
            complete: function(jqXHR) {
                spinner.hide();
                debugContent.show();
        addDebugStep('Processing ' + productIds.length + ' products for categories', 'info');
        console.log('[WhiskyAI] Bulk category generation started for product IDs:', productIds);

        $.ajax({
            url: whiskyAiSettings.ajaxurl,
            type: 'POST',
            data: {
                action: 'generate_whisky_categories',
                nonce: whiskyAiSettings.nonce,
                product_ids: productIds
            },
            beforeSend: function() {
                addDebugStep('Sending bulk categories request to API...', 'info');
            },
            success: function(response) {
                console.log('[WhiskyAI] Bulk API response:', response);
                addDebugStep('API response received', 'info');
                
                if (response.success) {
                    const resultCount = Object.keys(response.data.results || {}).length;
                    addDebugStep('Successfully generated categories for ' + resultCount + ' products', 'success');
                    if (response.data.errors && Object.keys(response.data.errors).length > 0) {
                        addDebugStep('Errors occurred for ' + Object.keys(response.data.errors).length + ' products:', 'warning');
                        $.each(response.data.errors, function(productId, error) {
                            addDebugStep('  - Product ' + productId + ': ' + error, 'error');
                        });
                    }
                    addDebugStep('Reloading page...', 'info');
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                } else {
                    let errorMessage = (response.data && response.data.message) ? response.data.message : 'Some categories failed to update.';
                    addDebugStep(errorMessage, 'error');
                    console.error('[WhiskyAI] API error:', response.data);
                    if (response.data && response.data.errors) {
                        $('.debug-response').text(JSON.stringify(response.data.errors, null, 2));
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('[WhiskyAI] AJAX error during bulk category generation:', {status: status, error: error});
                addDebugStep('Critical error: ' + error + ' (Status: ' + status + ')', 'error');
                $('.debug-response').text('Status Code: ' + xhr.status + '\nResponse:\n' + xhr.responseText);
            },
            complete: function(jqXHR) {
                addDebugStep('Bulk request complete', 'info');
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