<?php

class WhiskyAIException extends Exception {
    private $debug_info;

    public function __construct($message, $code = 0, Exception $previous = null, $debug_info = null) {
        parent::__construct($message, $code, $previous);
        $this->debug_info = $debug_info;
    }

    public function getDebugInfo() {
        return $this->debug_info;
    }
}

class WhiskyAICore {
    private $gemini_key;
    private $flavor_categories;
    private $options;
    private $gemini;

    public function __construct() {
        $this->options = get_option('whisky_ai_settings');
        $this->gemini_key = $this->options['gemini_api_key'] ?? '';

        if (!empty($this->gemini_key)) {
            $this->gemini = new Gemini($this->gemini_key);
        }
        
        // Set the correct flavor categories
        $this->flavor_categories = array(
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

        // If no category prompt is set, create a more specific one
        if (empty($this->options['category_prompt'])) {
            $this->options['category_prompt'] = 'You are analyzing Scottish Malt Whisky flavors. ' .
                'ONLY use the following specific categories to describe the whisky: ' .
                'Floral, Fruity, Vanilla, Honey, Spicy, Peated, Salty, Woody, Nutty, Chocolatey. ' .
                'Respond ONLY with the matching categories, one per line. ' .
                'Do not add any other words or categories.';
        }

        // Add AJAX handlers - these now queue async tasks instead of processing directly
        add_action('wp_ajax_generate_whisky_descriptions', array($this, 'queue_descriptions'));
        add_action('wp_ajax_generate_whisky_categories', array($this, 'queue_categories'));
        add_action('wp_ajax_check_whisky_processing_status', array($this, 'check_processing_status'));
        
        // Register async task processor hook
        add_action('whisky_ai_process_async_task', array($this, 'process_async_task_hook'), 10, 2);
    }

    /**
     * Queue descriptions for async processing instead of processing directly
     * This handler is called by AJAX and immediately returns to avoid timeout issues
     */
    public function queue_descriptions() {
        check_ajax_referer('whisky_ai_nonce', 'nonce');

        if (empty($this->gemini_key) || !$this->gemini) {
            wp_send_json_error(array('message' => 'Gemini API key is not set or invalid.'));
            return;
        }

        $product_ids = isset($_POST['product_ids']) ? array_map('intval', (array)$_POST['product_ids']) : array();

        if (empty($product_ids)) {
            wp_send_json_error(array('message' => 'No product IDs provided.'));
            return;
        }

        error_log('[WhiskyAI] Queuing ' . count($product_ids) . ' products for description processing');

        // Queue each product with staggered delays to prevent server overload
        $delay = 0;
        foreach ($product_ids as $product_id) {
            WhiskyAIAsyncTask::queue_product($product_id, 'description', $delay);
            $delay += 5; // 5 second delay between each product queued
        }

        wp_send_json_success(array(
            'message' => 'Successfully queued ' . count($product_ids) . ' product(s) for background processing.',
            'queued_count' => count($product_ids)
        ));
    }

    /**
     * Queue categories for async processing instead of processing directly
     */
    public function queue_categories() {
        check_ajax_referer('whisky_ai_nonce', 'nonce');

        if (empty($this->gemini_key) || !$this->gemini) {
            wp_send_json_error(array('message' => 'Gemini API key is not set or invalid.'));
            return;
        }

        $product_ids = isset($_POST['product_ids']) ? array_map('intval', (array)$_POST['product_ids']) : array();

        if (empty($product_ids)) {
            wp_send_json_error(array('message' => 'No product IDs provided.'));
            return;
        }

        error_log('[WhiskyAI] Queuing ' . count($product_ids) . ' products for category processing');

        // Queue each product with staggered delays to prevent server overload
        $delay = 0;
        foreach ($product_ids as $product_id) {
            WhiskyAIAsyncTask::queue_product($product_id, 'category', $delay);
            $delay += 5; // 5 second delay between each product queued
        }

        wp_send_json_success(array(
            'message' => 'Successfully queued ' . count($product_ids) . ' product(s) for background processing.',
            'queued_count' => count($product_ids)
        ));
    }

    /**
     * Hook callback for processing async tasks
     * Called by WordPress scheduled events
     */
    public function process_async_task_hook($product_id, $task_type) {
        // Initialize the async task handler
        $async_handler = new WhiskyAIAsyncTask($this->gemini, $this->options, $this->flavor_categories);
        $async_handler->process_async_task($product_id, $task_type);
    }

    /**
     * AJAX handler to check processing status of a product
     * Frontend polls this to know when to refresh the page
     */
    public function check_processing_status() {
        check_ajax_referer('whisky_ai_nonce', 'nonce');

        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
        $task_type = isset($_POST['task_type']) ? sanitize_text_field($_POST['task_type']) : 'category';

        if (empty($product_id)) {
            wp_send_json_error(array('message' => 'No product ID provided.'));
            return;
        }

        $status = WhiskyAIAsyncTask::get_processing_status($product_id, $task_type);
        wp_send_json_success($status);
    }

    /**
     * Generate descriptions - kept for backward compatibility but now queues async instead of processing directly
     * @deprecated Use queue_descriptions() instead
     */
    public function generate_descriptions() {
        $this->queue_descriptions();
    }

    /**
     * Generate categories endpoint - kept for backward compatibility but now queues async instead of processing directly
     * @deprecated Use queue_categories() instead
     */
    public function generate_categories_endpoint() {
        $this->queue_categories();
    }

    public function process_descriptions($product_ids) {
        $results = array();
        $errors = array();

        error_log('[WhiskyAI] Processing descriptions for ' . count($product_ids) . ' products');

        foreach ($product_ids as $product_id) {
            error_log('[WhiskyAI] Processing product ID: ' . $product_id);
            
            $product = wc_get_product($product_id);

            if (!$product) {
                error_log('[WhiskyAI] Product not found: ' . $product_id);
                $errors[$product_id] = 'Product not found.';
                continue;
            }

            error_log('[WhiskyAI] Product found: ' . $product->get_name());

            try {
                // Generate description
                error_log('[WhiskyAI] Calling generate_description for product: ' . $product->get_name());
                $description_result = $this->generate_description($product->get_name());
                $description = $description_result['content'];

                error_log('[WhiskyAI] Description generated, length: ' . strlen($description));

                // Update product
                $product->set_description($description);
                
                // Add DescUpdated tag
                wp_set_object_terms($product_id, 'DescUpdated', 'product_tag', true);
                
                $product->save();

                error_log('[WhiskyAI] Product saved successfully: ' . $product_id);

                $results[$product_id] = array(
                    'success' => true,
                    'description' => $description,
                    'debug' => $description_result['debug']
                );

            } catch (Exception $e) {
                error_log('[WhiskyAI] Exception for product ' . $product_id . ': ' . $e->getMessage());
                $errors[$product_id] = $e->getMessage();
            }
        }

        error_log('[WhiskyAI] Processing complete. Results: ' . count($results) . ', Errors: ' . count($errors));
        return ['results' => $results, 'errors' => $errors];
    }

    public function process_categories($product_ids) {
        $results = array();
        $errors = array();

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);

            if (!$product) {
                $errors[$product_id] = 'Product not found.';
                continue;
            }

            try {
                // Generate categories
                $categories_result = $this->generate_categories($product->get_name());
                $category_ids = $categories_result['category_ids'];

                // Update categories
                $current_categories = $product->get_category_ids();
                $new_categories = array_unique(array_merge($current_categories, $category_ids));
                $product->set_category_ids($new_categories);
                
                // Add CatUpdated tag
                wp_set_object_terms($product_id, 'CatUpdated', 'product_tag', true);
                
                $product->save();

                $category_names = array();
                foreach ($category_ids as $cat_id) {
                    $term = get_term($cat_id, 'product_cat');
                    if ($term && !is_wp_error($term)) {
                        $category_names[] = $term->name;
                    }
                }

                $results[$product_id] = array(
                    'success' => true,
                    'categories' => $category_ids,
                    'category_names' => $category_names,
                    'debug' => $categories_result['debug']
                );

            } catch (Exception $e) {
                $errors[$product_id] = $e->getMessage();
            }
        }

        return ['results' => $results, 'errors' => $errors];
    }

    /**
     * @deprecated This method should not be called directly
     * Use process_async_task_hook() for background processing instead
     */

    private function generate_description($product_name) {
        $request_data = array(
            'model' => $this->options['gemini_model'] ?? 'gemini-1.5-flash',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $this->options['description_prompt']
                ),
                array(
                    'role' => 'user',
                    'content' => "Do not start the sentence with 'The Whisky'. Give me three sentences to describe the Whisky {$product_name}"
                )
            )
        );

        $response = $this->gemini->chat($request_data);

        if (isset($response['error'])) {
            throw new WhiskyAIException(
                'Gemini API Error: ' . $response['error']['message'],
                0,
                null,
                array('api_request' => $request_data, 'api_response' => $response)
            );
        }

        if (empty($response['choices'][0]['message']['content'])) {
            throw new WhiskyAIException(
                'No description generated from Gemini',
                0,
                null,
                array('api_request' => $request_data, 'api_response' => $response)
            );
        }

        return array(
            'content' => $response['choices'][0]['message']['content'],
            'debug' => array('api_request' => $request_data, 'api_response' => $response)
        );
    }

    private function generate_categories($product_name) {
        $request_data = array(
            'model' => $this->options['gemini_model'] ?? 'gemini-1.5-flash',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $this->options['category_prompt']
                ),
                array(
                    'role' => 'user',
                    'content' => "List the flavor categories that match this whisky: {$product_name}. Only use the allowed categories."
                )
            )
        );

        $response = $this->gemini->chat($request_data);

        if (isset($response['error'])) {
            throw new WhiskyAIException(
                'Gemini API Error: ' . $response['error']['message'],
                0,
                null,
                array('api_request' => $request_data, 'api_response' => $response)
            );
        }

        if (empty($response['choices'][0]['message']['content'])) {
            throw new WhiskyAIException(
                'No categories generated from Gemini',
                0,
                null,
                array('api_request' => $request_data, 'api_response' => $response)
            );
        }
        
        $categories_text = $response['choices'][0]['message']['content'];
        
        // Category synonyms for flexible matching
        $category_synonyms = array(
            'Floral' => array('Floral', 'Flower', 'Flowers', 'Flowerery'),
            'Fruity' => array('Fruity', 'Fruit', 'Fruits'),
            'Vanilla' => array('Vanilla', 'Vanillin'),
            'Honey' => array('Honey', 'Honeyed', 'Honeycomb'),
            'Spicy' => array('Spicy', 'Spice', 'Spices', 'Peppery', 'Pepper'),
            'Peated' => array('Peated', 'Peat', 'Peaty', 'Smokey', 'Smoke', 'Smoked', 'Smoky'),
            'Salty' => array('Salty', 'Salt', 'Saline'),
            'Woody' => array('Woody', 'Wood', 'Woodsy'),
            'Nutty' => array('Nutty', 'Nuts', 'Nut', 'Almond', 'Hazelnut'),
            'Chocolatey' => array('Chocolatey', 'Chocolate', 'Cocoa')
        );
        
        // Improved category parsing with synonym matching
        $category_ids = array();
        $lines = explode("
", $categories_text);
        foreach ($lines as $line) {
            // Clean up the line
            $category = trim($line, " -	

 ");
            
            // First try exact match
            if (isset($this->flavor_categories[$category])) {
                $category_ids[] = $this->flavor_categories[$category];
                continue;
            }
            
            // Then try case-insensitive exact match
            foreach ($this->flavor_categories as $cat_name => $cat_id) {
                if (strtolower($category) === strtolower($cat_name)) {
                    $category_ids[] = $cat_id;
                    break;
                }
            }
            
            // Finally try synonym matching
            foreach ($category_synonyms as $main_category => $synonyms) {
                foreach ($synonyms as $synonym) {
                    if (strtolower($category) === strtolower($synonym)) {
                        $category_ids[] = $this->flavor_categories[$main_category];
                        break 2; // Break both loops
                    }
                }
            }
        }

        return array(
            'category_ids' => array_unique($category_ids),
            'raw_text' => $categories_text,
            'debug' => array(
                'api_request' => $request_data, 
                'api_response' => $response,
                'parsed_categories' => array_keys(array_filter($this->flavor_categories, function($id) use ($category_ids) {
                    return in_array($id, $category_ids);
                }))
            )
        );
    }
}

// End of WhiskyAICore class