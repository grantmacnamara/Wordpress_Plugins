<?php
/**
 * Plugin Name: WhiskyAI
 * Plugin URI: 
 * Description: This plugin uses OpenAI to generate whisky reviews.
 * Version: 1.0.0
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Author: Grant Macnamara
 * Author URI: 
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: whiskyai
 * Domain Path: /languages
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Include required files
require_once plugin_dir_path(__FILE__) . 'includes/class-openai.php';

// Plugin Class
class WhiskyAI {
    private $options;
    private $flavor_categories;
    private $default_description_prompt = 'You are a helpful Scottish Whisky chat assistant. You will answer in UK English spelling.';
    private $default_category_prompt = 'We are describing the tasting notes of Scottish Malt Whisky. Only respond using these words to describe Scottish Malt Whisky. Only use these categories to describe if appropriate. Reply only in a list.';

    public function __construct() {
        // Initialize hooks
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_get_whisky_products', array($this, 'get_whisky_products'));
        add_action('wp_ajax_get_whisky_stats', array($this, 'get_whisky_stats'));
        add_action('wp_ajax_verify_openai_api', array($this, 'verify_openai_api'));
        
        // Add product page hooks
        add_action('add_meta_boxes', array($this, 'add_product_meta_box'));
        add_action('wp_ajax_generate_whisky_descriptions', array($this, 'generate_descriptions'));
        add_action('wp_ajax_generate_whisky_categories', array($this, 'generate_categories_endpoint'));
        
        // Initialize default categories if they don't exist
        if (false === get_option('whisky_ai_categories')) {
            update_option('whisky_ai_categories', $this->get_default_categories());
        }
        
        // Get options with defaults
        $this->options = get_option('whisky_ai_settings', array(
            'openai_api_key' => '',
            'description_prompt' => $this->default_description_prompt,
            'category_prompt' => $this->default_category_prompt
        ));
        
        $this->flavor_categories = get_option('whisky_ai_categories', $this->get_default_categories());

        // Include core functionality
        require_once plugin_dir_path(__FILE__) . 'includes/whisky-ai-core.php';
    }

    public function enqueue_admin_scripts($hook) {
        // Load on both our plugin page and product edit page
        if ('toplevel_page_whisky-ai' !== $hook && 'post.php' !== $hook) {
            return;
        }

        // Get the current screen
        $screen = get_current_screen();
        if ('post.php' === $hook && 'product' !== $screen->post_type) {
            return;
        }

        wp_enqueue_script(
            'whisky-ai-admin',
            plugins_url('assets/js/whisky-ai-admin.js', __FILE__),
            array('jquery'),
            filemtime(plugin_dir_path(__FILE__) . 'assets/js/whisky-ai-admin.js'),
            true
        );

        wp_localize_script('whisky-ai-admin', 'whiskyAiSettings', array(
            'nonce' => wp_create_nonce('whisky_ai_nonce'),
            'ajaxurl' => admin_url('admin-ajax.php')
        ));

        // Add admin styles
        wp_add_inline_style('whisky-ai-admin-styles', '
            .whisky-stats-box {
                background: #fff;
                padding: 20px;
                margin: 20px 0;
                border: 1px solid #ccc;
                border-radius: 4px;
            }
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
                margin-top: 10px;
            }
            .stat-box {
                background: #f8f9fa;
                padding: 15px;
                border-radius: 4px;
                text-align: center;
            }
            .stat-box h3 {
                margin: 0 0 10px 0;
            }
            .progress-bar {
                width: 100%;
                background: #f0f0f0;
                height: 20px;
                border: 1px solid #ccc;
                margin-top: 20px;
            }
            .progress-bar > div {
                background: #0073aa;
                height: 100%;
                transition: width 0.3s ease-in-out;
            }
        ');
    }

    public function get_whisky_products() {
        check_ajax_referer('whisky_ai_nonce', 'nonce');

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );

        // If remaining_only is true, exclude products that have already been processed
        if (isset($_POST['remaining_only']) && $_POST['remaining_only'] === 'true') {
            $args['meta_query'] = array(
                array(
                    'key' => '_whisky_ai_processed',
                    'compare' => 'NOT EXISTS'
                )
            );
        }

        $products = get_posts($args);
        
        if (empty($products)) {
            wp_send_json_error('No products found');
            return;
        }

        $product_data = array_map(function($id) {
            return array(
                'id' => $id,
                'name' => get_the_title($id)
            );
        }, $products);

        wp_send_json_success($product_data);
    }

    public function get_whisky_stats() {
        check_ajax_referer('whisky_ai_nonce', 'nonce');

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => -1,
            'fields' => 'ids'
        );

        $total_products = count(get_posts($args));

        // Get products with DescUpdated tag
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'product_tag',
                'field' => 'name',
                'terms' => 'DescUpdated'
            )
        );

        $updated_products = count(get_posts($args));

        wp_send_json_success(array(
            'total' => $total_products,
            'updated' => $updated_products,
            'remaining' => $total_products - $updated_products
        ));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Whisky AI Settings',
            'Whisky AI',
            'manage_options',
            'whisky-ai',
            array($this, 'display_admin_page'),
            'dashicons-bottle'
        );
    }

    public function register_settings() {
        register_setting(
            'whisky_ai_settings',
            'whisky_ai_settings',
            array(
                'type' => 'object',
                'sanitize_callback' => array($this, 'sanitize_settings')
            )
        );
        
        register_setting(
            'whisky_ai_settings',
            'whisky_ai_categories',
            array(
                'type' => 'object',
                'sanitize_callback' => array($this, 'sanitize_categories'),
                'default' => $this->get_default_categories()
            )
        );

        // Main Settings Section
        add_settings_section(
            'whisky_ai_main_section',
            'API Settings',
            null,
            'whisky_ai_settings'
        );

        add_settings_field(
            'openai_api_key',
            'OpenAI API Key',
            array($this, 'render_api_key_field'),
            'whisky_ai_settings',
            'whisky_ai_main_section'
        );

        // Prompt Settings Section
        add_settings_section(
            'whisky_ai_prompts_section',
            'AI Prompt Settings',
            array($this, 'render_prompts_section_description'),
            'whisky_ai_settings'
        );

        add_settings_field(
            'description_prompt',
            'Description Generation Prompt',
            array($this, 'render_description_prompt_field'),
            'whisky_ai_settings',
            'whisky_ai_prompts_section'
        );

        add_settings_field(
            'category_prompt',
            'Category Generation Prompt',
            array($this, 'render_category_prompt_field'),
            'whisky_ai_settings',
            'whisky_ai_prompts_section'
        );
    }

    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['openai_api_key'])) {
            $sanitized['openai_api_key'] = sanitize_text_field($input['openai_api_key']);
        }
        
        if (isset($input['description_prompt'])) {
            $sanitized['description_prompt'] = sanitize_textarea_field($input['description_prompt']);
        }
        
        if (isset($input['category_prompt'])) {
            $sanitized['category_prompt'] = sanitize_textarea_field($input['category_prompt']);
        }
        
        return $sanitized;
    }

    public function sanitize_categories($input) {
        $names = $_POST['category_names'] ?? array();
        $ids = $_POST['category_ids'] ?? array();
        
        $sanitized = array();
        
        foreach ($names as $index => $name) {
            if (!empty($name) && isset($ids[$index])) {
                $sanitized[sanitize_text_field($name)] = absint($ids[$index]);
            }
        }

        return !empty($sanitized) ? $sanitized : $this->get_default_categories();
    }

    private function get_default_categories() {
        return array(
            'Floral' => 128,
            'Fruity' => 129,
            'Vanilla' => 130,
            'Honey' => 131,
            'Spicy' => 132,
            'Peated' => 133,
            'Salty' => 134,
            'Woody' => 135,
            'Nutty' => 136,
            'Chocolatey' => 137
        );
    }

    public function render_prompts_section_description() {
        echo '<p>Customize the prompts used to generate whisky descriptions and categories. These prompts guide the AI in generating appropriate content.</p>';
    }

    public function render_api_key_field() {
        $api_key = isset($this->options['openai_api_key']) ? $this->options['openai_api_key'] : '';
        $is_verified = get_option('whisky_ai_api_verified', false);
        
        if ($is_verified) {
            echo '<div class="api-key-verified" style="display: flex; align-items: center;">
                    <span class="dashicons dashicons-yes-alt" style="color: #46b450; font-size: 30px; margin-right: 10px;"></span>
                    <span>API Key verified and working</span>
                    <button type="button" class="button button-secondary" id="change-api-key" style="margin-left: 10px;">Change Key</button>
                  </div>';
            echo '<div class="api-key-input" style="display: none;">';
        } else {
            echo '<div class="api-key-input">';
        }
        
        echo '<input type="password" 
                     name="whisky_ai_settings[openai_api_key]" 
                     value="' . esc_attr($api_key) . '" 
                     class="regular-text"
                     id="openai-api-key">';
        echo '<button type="button" class="button button-secondary" id="verify-api-key" style="margin-left: 10px;">Verify API Key</button>';
        echo '<span class="spinner" style="float: none; margin-top: 0;"></span>';
        echo '<div class="api-key-message"></div>';
        echo '</div>';

        ?>
        <script>
        jQuery(document).ready(function($) {
            $('#verify-api-key').on('click', function() {
                const button = $(this);
                const spinner = button.next('.spinner');
                const messageDiv = $('.api-key-message');
                const apiKey = $('#openai-api-key').val();

                if (!apiKey) {
                    messageDiv.html('<div class="error-message" style="color: #dc3232; margin-top: 5px;">Please enter an API key</div>');
                    return;
                }

                // Show loading state
                button.prop('disabled', true);
                spinner.css('visibility', 'visible');
                messageDiv.empty();

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'verify_openai_api',
                        nonce: '<?php echo wp_create_nonce('whisky_ai_verify_nonce'); ?>',
                        api_key: apiKey
                    },
                    success: function(response) {
                        console.log('API Response:', response);
                        if (response.success) {
                            messageDiv.html('<div class="success-message" style="color: #46b450; margin-top: 5px;">API key verified successfully!</div>');
                            setTimeout(function() {
                                location.reload();
                            }, 1000);
                        } else {
                            messageDiv.html('<div class="error-message" style="color: #dc3232; margin-top: 5px;">Error: ' + (response.data || 'Unknown error') + '</div>');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('AJAX Error:', {xhr, status, error});
                        messageDiv.html('<div class="error-message" style="color: #dc3232; margin-top: 5px;">Error connecting to server. Check console for details.</div>');
                    },
                    complete: function() {
                        button.prop('disabled', false);
                        spinner.css('visibility', 'hidden');
                    }
                });
            });

            $('#change-api-key').on('click', function() {
                $('.api-key-verified').hide();
                $('.api-key-input').show();
            });
        });
        </script>
        <?php
    }

    public function verify_openai_api() {
        if (!check_ajax_referer('whisky_ai_verify_nonce', 'nonce', false)) {
            wp_send_json_error('Invalid nonce. Please refresh the page and try again.');
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('You do not have permission to perform this action.');
            return;
        }

        if (!isset($_POST['api_key']) || empty($_POST['api_key'])) {
            wp_send_json_error('API key is required.');
            return;
        }
        
        $api_key = sanitize_text_field($_POST['api_key']);
        
        error_log('Attempting to verify OpenAI API key...');
        
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
            'sslverify' => true,
            'user-agent' => 'WordPress/' . get_bloginfo('version') . '; ' . get_bloginfo('url')
        );

        error_log('Making request to OpenAI API...');
        
        $response = wp_remote_get('https://api.openai.com/v1/models', $args);

        if (is_wp_error($response)) {
            error_log('OpenAI API Error: ' . $response->get_error_message());
            wp_send_json_error('Connection failed: ' . $response->get_error_message());
            return;
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);

        error_log('OpenAI API Response Code: ' . $response_code);
        error_log('OpenAI API Response Body: ' . $response_body);

        if ($response_code !== 200) {
            $body = json_decode($response_body, true);
            $error_message = isset($body['error']['message']) ? $body['error']['message'] : 'Unknown error';
            wp_send_json_error('API Error: ' . $error_message);
            return;
        }

        // If we get here, the API key is valid
        update_option('whisky_ai_api_verified', true);
        
        // Update the API key in settings
        $options = get_option('whisky_ai_settings', array());
        $options['openai_api_key'] = $api_key;
        update_option('whisky_ai_settings', $options);

        wp_send_json_success('API key verified successfully');
    }

    public function render_description_prompt_field() {
        $prompt = isset($this->options['description_prompt']) ? $this->options['description_prompt'] : $this->default_description_prompt;
        echo '<textarea name="whisky_ai_settings[description_prompt]" rows="4" class="large-text">' . esc_textarea($prompt) . '</textarea>';
        echo '<p class="description">This prompt guides the AI in generating whisky descriptions. Default: "' . esc_html($this->default_description_prompt) . '"</p>';
        echo '<button type="button" class="button reset-prompt" data-default="' . esc_attr($this->default_description_prompt) . '">Reset to Default</button>';
    }

    public function render_category_prompt_field() {
        $prompt = isset($this->options['category_prompt']) ? $this->options['category_prompt'] : $this->default_category_prompt;
        echo '<textarea name="whisky_ai_settings[category_prompt]" rows="4" class="large-text">' . esc_textarea($prompt) . '</textarea>';
        echo '<p class="description">This prompt guides the AI in categorizing whiskies. Default: "' . esc_html($this->default_category_prompt) . '"</p>';
        echo '<button type="button" class="button reset-prompt" data-default="' . esc_attr($this->default_category_prompt) . '">Reset to Default</button>';
    }

    public function display_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="whisky-stats-box">
                <h2>Product Statistics</h2>
                <div id="whisky-stats">
                    <p>Loading statistics...</p>
                </div>
            </div>

            <form action="options.php" method="post">
                <?php
                settings_fields('whisky_ai_settings');
                do_settings_sections('whisky_ai_settings');
                submit_button();
                ?>
            </form>

            <h2>Current Flavor Categories</h2>
            <div class="flavor-categories-list" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 4px;">
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>Category Name</th>
                            <th>Category ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $categories = $this->get_default_categories();
                        foreach ($categories as $name => $id): 
                        ?>
                        <tr>
                            <td><?php echo esc_html($name); ?></td>
                            <td><?php echo esc_html($id); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <hr>

            <h2>Generate Descriptions</h2>
            <div class="generate-buttons" style="display: flex; gap: 10px;">
                <button type="button" class="button button-primary" id="generate-descriptions">
                    Generate All Descriptions
                </button>
                <button type="button" class="button button-secondary" id="generate-remaining">
                    Generate Remaining Only
                </button>
            </div>
            <div id="generation-progress"></div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            // Load statistics on page load
            loadStats();

            function loadStats() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'get_whisky_stats',
                        nonce: '<?php echo wp_create_nonce('whisky_ai_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            const stats = response.data;
                            const percentage = Math.round((stats.updated / stats.total) * 100) || 0;
                            
                            $('#whisky-stats').html(`
                                <div class="stats-grid">
                                    <div class="stat-box">
                                        <h3>Total Products</h3>
                                        <p style="font-size: 24px; margin: 0;">${stats.total}</p>
                                    </div>
                                    <div class="stat-box">
                                        <h3>Updated Products</h3>
                                        <p style="font-size: 24px; margin: 0;">${stats.updated}</p>
                                    </div>
                                    <div class="stat-box">
                                        <h3>Remaining</h3>
                                        <p style="font-size: 24px; margin: 0;">${stats.remaining}</p>
                                    </div>
                                </div>
                                <div class="progress-bar">
                                    <div style="width: ${percentage}%;"></div>
                                </div>
                                <p style="text-align: center; margin-top: 5px;">${percentage}% Complete</p>
                            `);
                        } else {
                            $('#whisky-stats').html('<p class="error">Error loading statistics</p>');
                        }
                    },
                    error: function() {
                        $('#whisky-stats').html('<p class="error">Error loading statistics</p>');
                    }
                });
            }

            // Refresh stats every 30 seconds
            setInterval(loadStats, 30000);
        });
        </script>
        <?php
    }

    public function add_product_meta_box() {
        add_meta_box(
            'whisky_ai_meta_box',
            'Whisky AI Description Generator',
            array($this, 'render_product_meta_box'),
            'product',
            'side',
            'default'
        );
    }

    public function render_product_meta_box($post) {
        $desc_tag = has_term('DescUpdated', 'product_tag', $post->ID);
        $cat_tag = has_term('CatUpdated', 'product_tag', $post->ID);
        
        ?>
        <div class="whisky-ai-status">
            <div class="status-item <?php echo $desc_tag ? 'processed' : 'not-processed'; ?>" style="margin-bottom: 10px;">
                <p><strong>Description Status:</strong> <?php echo $desc_tag ? 'Generated' : 'Not Generated'; ?></p>
                <button type="button" 
                        class="button button-primary generate-single-description" 
                        data-product-id="<?php echo esc_attr($post->ID); ?>"
                        style="width: 100%;">
                    Generate Description
                </button>
            </div>
            
            <div class="status-item <?php echo $cat_tag ? 'processed' : 'not-processed'; ?>">
                <p><strong>Categories Status:</strong> <?php echo $cat_tag ? 'Generated' : 'Not Generated'; ?></p>
                <button type="button" 
                        class="button button-primary generate-single-categories" 
                        data-product-id="<?php echo esc_attr($post->ID); ?>"
                        style="width: 100%;">
                    Generate Categories
                </button>
            </div>
        </div>
        <div class="single-product-status"></div>

        <!-- Debug Modal -->
        <div id="whisky-debug-modal" class="whisky-modal">
            <div class="whisky-modal-content">
                <span class="whisky-modal-close">&times;</span>
                <h2>AI Generation Progress</h2>
                <div class="whisky-debug-content">
                    <div class="debug-steps"></div>
                    <div class="debug-response"></div>
                </div>
            </div>
        </div>

        <style>
            .whisky-modal {
                display: none;
                position: fixed;
                z-index: 999999;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.4);
            }
            
            .whisky-modal-content {
                background-color: #fefefe;
                margin: 5% auto;
                padding: 20px;
                border: 1px solid #888;
                width: 80%;
                max-width: 600px;
                border-radius: 4px;
                max-height: 80vh;
                overflow-y: auto;
            }
            
            .whisky-modal-close {
                color: #aaa;
                float: right;
                font-size: 28px;
                font-weight: bold;
                cursor: pointer;
            }
            
            .whisky-modal-close:hover {
                color: black;
            }
            
            .status-item {
                padding: 10px;
                border-radius: 4px;
                background: #f8f9fa;
                border: 1px solid #ddd;
            }
            
            .status-item.processed {
                background: #d4edda;
                border-color: #c3e6cb;
            }
            
            .status-item.not-processed {
                background: #fff3cd;
                border-color: #ffeeba;
            }
            
            .status-item p {
                margin: 0 0 5px 0;
            }

            .debug-steps {
                margin-bottom: 20px;
            }
            
            .debug-step {
                padding: 10px;
                margin: 5px 0;
                border-radius: 4px;
                background: #f8f9fa;
            }
            
            .debug-step.success {
                background: #d4edda;
                border-color: #c3e6cb;
            }
            
            .debug-step.error {
                background: #f8d7da;
                border-color: #f5c6cb;
            }
            
            .debug-response {
                background: #f8f9fa;
                padding: 10px;
                border-radius: 4px;
                margin-top: 10px;
                white-space: pre-wrap;
                font-family: monospace;
            }
        </style>
        <?php
    }

    public function generate_descriptions() {
        check_ajax_referer('whisky_ai_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $product_ids = isset($_POST['product_ids']) ? array_map('intval', (array)$_POST['product_ids']) : array();
        
        if (empty($product_ids)) {
            wp_send_json_error('No product IDs provided');
            return;
        }

        $debug_info = array();
        $success = true;
        $errors = array();

        foreach ($product_ids as $product_id) {
            try {
                $product = wc_get_product($product_id);
                if (!$product) {
                    throw new Exception("Product not found: " . $product_id);
                }

                // Get product name and current description
                $product_name = $product->get_name();
                $current_description = $product->get_description();

                // Prepare the prompt
                $system_prompt = $this->options['description_prompt'] ?: $this->default_description_prompt;
                $user_prompt = "Product Name: {$product_name}\nCurrent Description: {$current_description}";

                // Call OpenAI API
                $openai = new OpenAI($this->options['openai_api_key']);
                $response = $openai->chat([
                    'model' => 'gpt-4',
                    'messages' => [
                        ['role' => 'system', 'content' => $system_prompt],
                        ['role' => 'user', 'content' => $user_prompt]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 1000
                ]);

                if (isset($response['error'])) {
                    throw new Exception("OpenAI API Error: " . $response['error']['message']);
                }

                $new_description = $response['choices'][0]['message']['content'];

                // Update product description
                $product->set_description($new_description);
                $product->save();

                // Add the DescUpdated tag
                wp_set_object_terms($product_id, 'DescUpdated', 'product_tag', true);

                $debug_info[$product_id] = array(
                    'success' => true,
                    'original_description' => $current_description,
                    'new_description' => $new_description,
                    'openai_response' => $response
                );

            } catch (Exception $e) {
                $success = false;
                $errors[$product_id] = $e->getMessage();
                $debug_info[$product_id] = array(
                    'success' => false,
                    'error' => $e->getMessage()
                );
            }
        }

        if ($success) {
            wp_send_json_success(array(
                'message' => 'Descriptions updated successfully',
                'debug' => $debug_info
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Some descriptions failed to update',
                'errors' => $errors,
                'debug' => $debug_info
            ));
        }
    }

    public function generate_categories_endpoint() {
        check_ajax_referer('whisky_ai_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
            return;
        }

        $product_ids = isset($_POST['product_ids']) ? array_map('intval', (array)$_POST['product_ids']) : array();
        
        if (empty($product_ids)) {
            wp_send_json_error('No product IDs provided');
            return;
        }

        $debug_info = array();
        $success = true;
        $errors = array();

        foreach ($product_ids as $product_id) {
            try {
                $product = wc_get_product($product_id);
                if (!$product) {
                    throw new Exception("Product not found: " . $product_id);
                }

                // Get product name and current description
                $product_name = $product->get_name();
                $current_description = $product->get_description();

                // Get available categories
                $categories = $this->get_default_categories();
                $category_list = implode(", ", array_keys($categories));

                // Prepare the prompt
                $system_prompt = $this->options['category_prompt'] ?: $this->default_category_prompt;
                $system_prompt = str_replace('[CATEGORIES]', $category_list, $system_prompt);
                
                $user_prompt = "Product Name: {$product_name}\nDescription: {$current_description}";

                // Call OpenAI API
                $openai = new OpenAI($this->options['openai_api_key']);
                $response = $openai->chat([
                    'model' => 'gpt-4',
                    'messages' => [
                        ['role' => 'system', 'content' => $system_prompt],
                        ['role' => 'user', 'content' => $user_prompt]
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 500
                ]);

                if (isset($response['error'])) {
                    throw new Exception("OpenAI API Error: " . $response['error']['message']);
                }

                $ai_response = $response['choices'][0]['message']['content'];
                
                // Parse the response to get categories
                $detected_categories = array();
                foreach ($categories as $category => $subcategories) {
                    if (stripos($ai_response, $category) !== false) {
                        $detected_categories[] = $category;
                        // Check for subcategories
                        foreach ($subcategories as $subcategory) {
                            if (stripos($ai_response, $subcategory) !== false) {
                                $detected_categories[] = $subcategory;
                            }
                        }
                    }
                }

                // Get current categories
                $current_terms = wp_get_object_terms($product_id, 'product_cat', array('fields' => 'names'));

                // Update product categories
                $term_ids = array();
                foreach ($detected_categories as $category_name) {
                    $term = get_term_by('name', $category_name, 'product_cat');
                    if ($term) {
                        $term_ids[] = $term->term_id;
                    }
                }
                wp_set_object_terms($product_id, $term_ids, 'product_cat');

                // Add the CatUpdated tag
                wp_set_object_terms($product_id, 'CatUpdated', 'product_tag', true);

                $debug_info[$product_id] = array(
                    'success' => true,
                    'original_categories' => $current_terms,
                    'detected_categories' => $detected_categories,
                    'ai_response' => $ai_response,
                    'openai_response' => $response
                );

            } catch (Exception $e) {
                $success = false;
                $errors[$product_id] = $e->getMessage();
                $debug_info[$product_id] = array(
                    'success' => false,
                    'error' => $e->getMessage()
                );
            }
        }

        if ($success) {
            wp_send_json_success(array(
                'message' => 'Categories updated successfully',
                'debug' => $debug_info
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Some categories failed to update',
                'errors' => $errors,
                'debug' => $debug_info
            ));
        }
    }
}

// Initialize the plugin
$whisky_ai = new WhiskyAI();