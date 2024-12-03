jQuery(document).ready(function($) {
    // Single product description generation
    $('.generate-single-description').on('click', function() {
        const button = $(this);
        const productId = button.data('product-id');
        const statusDiv = button.closest('.whisky-ai-status').find('.single-product-status');
        const modal = $('#whisky-debug-modal');
        const debugSteps = modal.find('.debug-steps');
        const debugResponse = modal.find('.debug-response');

        // Show modal and clear previous content
        modal.css('display', 'block');
        debugSteps.empty();
        debugResponse.empty();

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
                    addDebugStep('Description generated successfully!', 'success');
                    button.closest('.status-item').addClass('processed');
                    location.reload();
                } else {
                    addDebugStep('Error: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                addDebugStep('Ajax error: ' + error, 'error');
                debugResponse.text(xhr.responseText);
            },
            complete: function() {
                button.prop('disabled', false).text('Generate Description');
            }
        });
    });

    // Single product categories generation
    $('.generate-single-categories').on('click', function() {
        const button = $(this);
        const productId = button.data('product-id');
        const statusDiv = button.closest('.whisky-ai-status').find('.single-product-status');
        const modal = $('#whisky-debug-modal');
        const debugSteps = modal.find('.debug-steps');
        const debugResponse = modal.find('.debug-response');

        // Show modal and clear previous content
        modal.css('display', 'block');
        debugSteps.empty();
        debugResponse.empty();

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
                    button.closest('.status-item').addClass('processed');
                    location.reload();
                } else {
                    addDebugStep('Error: ' + response.data, 'error');
                }
            },
            error: function(xhr, status, error) {
                addDebugStep('Ajax error: ' + error, 'error');
                debugResponse.text(xhr.responseText);
            },
            complete: function() {
                button.prop('disabled', false).text('Generate Categories');
            }
        });
    });

    // Modal close button
    $('.whisky-modal-close').on('click', function() {
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
        debugSteps.scrollTop(debugSteps[0].scrollHeight);
    }

    // Stats page functionality
    if ($('#whisky-stats').length) {
        function loadStats() {
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
            $('#total-products').text(stats.total);
            $('#desc-updated').text(stats.description_updated);
            $('#cat-updated').text(stats.categories_updated);
            
            const descPercent = (stats.description_updated / stats.total) * 100;
            const catPercent = (stats.categories_updated / stats.total) * 100;
            
            $('#desc-progress').css('width', descPercent + '%');
            $('#cat-progress').css('width', catPercent + '%');
        }

        // Initial load
        loadStats();
    }
}); 