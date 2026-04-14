<?php
/**
 * WhiskyAI Async Task Handler
 * 
 * Handles background processing of AI descriptions and categories
 * with throttling, caching, and error handling to prevent 503 and deadlock errors
 */

class WhiskyAIAsyncTask {
    const TRANSIENT_PREFIX = 'whisky_ai_proc_';
    const TRANSIENT_TTL = 60; // 60 seconds cache
    const COOLDOWN_SECONDS = 5;
    const ERROR_RETRY_MINUTES = 10;
    const MAX_RETRIES = 3;
    
    private $gemini;
    private $options;
    private $flavor_categories;
    private $task_type; // 'description' or 'category'
    private $product_id;

    public function __construct($gemini, $options, $flavor_categories) {
        $this->gemini = $gemini;
        $this->options = $options;
        $this->flavor_categories = $flavor_categories;
    }

    /**
     * Queue a single product for background processing
     * 
     * @param int $product_id
     * @param string $task_type 'description' or 'category'
     * @return bool
     */
    public static function queue_product($product_id, $task_type = 'description', $delay = 0) {
        error_log("[WhiskyAI] Queuing async task: product_id=$product_id, type=$task_type, delay=$delay");
        
        // Use Action Scheduler if available (preferred), otherwise fall back to wp_schedule_single_event
        if (function_exists('as_schedule_single_action')) {
            // Action Scheduler available
            as_schedule_single_action(
                time() + $delay,
                'whisky_ai_process_async_task',
                array($product_id, $task_type),
                'whisky-ai'
            );
        } else {
            // Fall back to WordPress scheduling
            wp_schedule_single_event(
                time() + $delay,
                'whisky_ai_process_async_task',
                array($product_id, $task_type)
            );
        }
        
        return true;
    }

    /**
     * Check if a product is currently being processed
     * Returns status info including whether it's done, failed, or still processing
     * 
     * @param int $product_id
     * @param string $task_type
     * @return array
     */
    public static function get_processing_status($product_id, $task_type = 'description') {
        $cache_key_desc = self::TRANSIENT_PREFIX . 'desc_' . $product_id;
        $cache_key_cat = self::TRANSIENT_PREFIX . 'cat_' . $product_id;
        
        // Check if still processing (transient exists)
        $is_processing = false;
        if ($task_type === 'description' && get_transient($cache_key_desc)) {
            $is_processing = true;
        } elseif ($task_type === 'category' && get_transient($cache_key_cat)) {
            $is_processing = true;
        }
        
        // Check for errors
        $error = get_post_meta($product_id, '_whisky_ai_error_' . $task_type, true);
        $error_time = get_post_meta($product_id, '_whisky_ai_error_time_' . $task_type, true);
        
        // Check for success
        $success_time = get_post_meta($product_id, '_whisky_ai_last_' . $task_type, true);
        
        return array(
            'product_id' => $product_id,
            'task_type' => $task_type,
            'is_processing' => $is_processing,
            'has_error' => !empty($error),
            'error_message' => $error,
            'error_time' => $error_time,
            'success_time' => $success_time,
            'is_complete' => !$is_processing && (!empty($success_time) || !empty($error))
        );
    }

    /**
     * Process a single product asynchronously
     * Called by scheduled event hook
     * 
     * @param int $product_id
     * @param string $task_type
     */
    public function process_async_task($product_id, $task_type = 'description') {
        $this->product_id = $product_id;
        $this->task_type = $task_type;
        
        error_log("[WhiskyAI] Starting async task: product_id=$product_id, type=$task_type");
        
        // Raise memory limit only when necessary
        if (function_exists('wp_raise_memory_limit')) {
            wp_raise_memory_limit('admin');
        }
        
        try {
            // Get product
            $product = wc_get_product($product_id);
            if (!$product) {
                error_log("[WhiskyAI] Product not found: {$product_id}");
                $this->log_error('Product not found', $product_id);
                return;
            }
            
            $product_name = $product->get_name();
            error_log("[WhiskyAI] Processing product: {$product_name} (ID: {$product_id})");
            
            // Check if post is locked before proceeding
            if ($this->is_post_locked($product_id)) {
                error_log("[WhiskyAI] Post is locked, rescheduling: {$product_id}");
                self::queue_product($product_id, $task_type, self::COOLDOWN_SECONDS);
                return;
            }
            
            if ($task_type === 'description') {
                $this->process_description_async($product_id, $product, $product_name);
            } else if ($task_type === 'category') {
                $this->process_category_async($product_id, $product, $product_name);
            }
            
        } catch (Exception $e) {
            error_log("[WhiskyAI] Exception in async task: " . $e->getMessage());
            $this->handle_async_error($product_id, $e, $task_type);
        }
    }

    /**
     * Process description generation asynchronously
     */
    private function process_description_async($product_id, $product, $product_name) {
        // Check transient cache to prevent double-processing
        $cache_key = self::TRANSIENT_PREFIX . 'desc_' . $product_id;
        if (get_transient($cache_key)) {
            error_log("[WhiskyAI] Description already processed recently for product {$product_id}, skipping");
            return;
        }
        
        // Mark as processing
        set_transient($cache_key, true, self::TRANSIENT_TTL);
        
        try {
            // Generate description via Gemini
            error_log("[WhiskyAI] Calling Gemini API for description: {$product_name}");
            $description = $this->call_gemini_api(
                $this->options['description_prompt'] ?? 'You are a helpful Scottish Whisky chat assistant. You will answer in UK English spelling.',
                "Do not start the sentence with 'The Whisky'. Give me three sentences to describe the Whisky {$product_name}"
            );
            
            if (empty($description)) {
                error_log("[WhiskyAI] Empty description received for product {$product_id}");
                $this->log_error('Empty description from API', $product_id);
                return;
            }
            
            error_log("[WhiskyAI] Description generated, length: " . strlen($description));
            
            // Update product with lock check
            if (!$this->update_post_with_lock_check($product_id, array(
                'post_content' => $description
            ))) {
                error_log("[WhiskyAI] Failed to update post (locked), rescheduling: {$product_id}");
                self::queue_product($product_id, 'description', self::COOLDOWN_SECONDS);
                return;
            }
            
            // Add DescUpdated tag
            wp_set_object_terms($product_id, 'DescUpdated', 'product_tag', true);
            
            error_log("[WhiskyAI] Description processing complete for product {$product_id}");
            $this->log_success('Description generated', $product_id);
            
        } catch (Exception $e) {
            error_log("[WhiskyAI] Description processing failed: " . $e->getMessage());
            $this->handle_async_error($product_id, $e, 'description');
        }
    }

    /**
     * Process category generation asynchronously
     */
    private function process_category_async($product_id, $product, $product_name) {
        // Check transient cache to prevent double-processing
        $cache_key = self::TRANSIENT_PREFIX . 'cat_' . $product_id;
        if (get_transient($cache_key)) {
            error_log("[WhiskyAI] Categories already processed recently for product {$product_id}, skipping");
            return;
        }
        
        // Mark as processing
        set_transient($cache_key, true, self::TRANSIENT_TTL);
        
        try {
            // Generate categories via Gemini
            error_log("[WhiskyAI] Calling Gemini API for categories: {$product_name}");
            $categories_text = $this->call_gemini_api(
                $this->options['category_prompt'] ?? 'We are describing the tasting notes of Scottish Malt Whisky. Only respond using these words to describe Scottish Malt Whisky. Only use these categories to describe if appropriate. Reply only in a list.',
                "List the flavor categories that match this whisky: {$product_name}. Only use the allowed categories."
            );
            
            if (empty($categories_text)) {
                error_log("[WhiskyAI] Empty categories received for product {$product_id}");
                $this->log_error('Empty categories from API', $product_id);
                return;
            }
            
            error_log("[WhiskyAI] Raw categories response: " . substr($categories_text, 0, 200));
            
            // Parse categories with synonym matching
            $category_ids = $this->parse_categories($categories_text);
            error_log("[WhiskyAI] Parsed " . count($category_ids) . " categories for product {$product_id}");
            
            if (empty($category_ids)) {
                error_log("[WhiskyAI] No categories parsed from API response");
                $this->log_error('No categories matched', $product_id);
                return;
            }
            
            // Get current categories
            $current_categories = $product->get_category_ids();
            $new_categories = array_unique(array_merge($current_categories, $category_ids));
            
            // Update product with lock check
            if (!$this->update_post_with_lock_check($product_id, array(
                'tax_input' => array('product_cat' => $new_categories)
            ))) {
                error_log("[WhiskyAI] Failed to update post (locked), rescheduling: {$product_id}");
                self::queue_product($product_id, 'category', self::COOLDOWN_SECONDS);
                return;
            }
            
            // Add CatUpdated tag
            wp_set_object_terms($product_id, 'CatUpdated', 'product_tag', true);
            
            error_log("[WhiskyAI] Category processing complete for product {$product_id}");
            $this->log_success('Categories generated', $product_id);
            
        } catch (Exception $e) {
            error_log("[WhiskyAI] Category processing failed: " . $e->getMessage());
            $this->handle_async_error($product_id, $e, 'category');
        }
    }

    /**
     * Call Gemini API with error handling and retry logic for 5xx errors
     */
    private function call_gemini_api($system_prompt, $user_message) {
        $retry_count = 0;
        
        while ($retry_count < self::MAX_RETRIES) {
            try {
                $request_data = array(
                    'model' => $this->options['gemini_model'] ?? 'gemini-2.5-flash',
                    'messages' => array(
                        array('role' => 'system', 'content' => $system_prompt),
                        array('role' => 'user', 'content' => $user_message)
                    )
                );
                
                error_log("[WhiskyAI] Calling Gemini API, attempt " . ($retry_count + 1));
                $response = $this->gemini->chat($request_data);
                
                // Check for API errors
                if (isset($response['error'])) {
                    $error_code = $response['error']['code'] ?? 0;
                    $error_message = $response['error']['message'] ?? 'Unknown error';
                    
                    error_log("[WhiskyAI] Gemini API error (code: {$error_code}): {$error_message}");
                    
                    // If 5xx error, we'll retry
                    if ($error_code >= 500 && $error_code < 600) {
                        $retry_count++;
                        if ($retry_count < self::MAX_RETRIES) {
                            error_log("[WhiskyAI] 5xx error, retrying in 5 seconds...");
                            sleep(5);
                            continue;
                        }
                    }
                    
                    // After max retries or non-5xx error, throw exception
                    throw new Exception("Gemini API Error: {$error_message}");
                }
                
                if (empty($response['choices'][0]['message']['content'])) {
                    throw new Exception('No content in API response');
                }
                
                return $response['choices'][0]['message']['content'];
                
            } catch (Exception $e) {
                $retry_count++;
                if ($retry_count >= self::MAX_RETRIES) {
                    throw $e;
                }
                error_log("[WhiskyAI] API call failed, retrying: " . $e->getMessage());
                sleep(5);
            }
        }
        
        throw new Exception('Max API retries exceeded');
    }

    /**
     * Check if a post is currently locked
     */
    private function is_post_locked($post_id) {
        global $wpdb;
        
        $lock = get_post_meta($post_id, '_edit_lock', true);
        if (!$lock) {
            return false;
        }
        
        // Lock format: "user_id:timestamp"
        $lock_parts = explode(':', $lock);
        $lock_time = isset($lock_parts[1]) ? intval($lock_parts[1]) : 0;
        $current_time = time();
        
        // If lock is older than 24 hours, consider it expired
        if (($current_time - $lock_time) > 86400) {
            delete_post_meta($post_id, '_edit_lock');
            return false;
        }
        
        return true;
    }

    /**
     * Update post with lock check
     * Returns true on success, false on lock
     */
    private function update_post_with_lock_check($post_id, $update_data) {
        // Check for lock before updating
        if ($this->is_post_locked($post_id)) {
            return false;
        }
        
        // Get current post to update
        $post = get_post($post_id);
        if (!$post) {
            return false;
        }
        
        // Prepare update array
        $update = array(
            'ID' => $post_id,
            'post_content' => $update_data['post_content'] ?? $post->post_content
        );
        
        // Update post
        $result = wp_update_post($update, true);
        
        if (is_wp_error($result)) {
            error_log("[WhiskyAI] Post update error: " . $result->get_error_message());
            return false;
        }
        
        return true;
    }

    /**
     * Parse categories with synonym matching and detailed logging
     */
    private function parse_categories($categories_text) {
        // Log raw API response
        error_log("[WhiskyAI] Raw API response for category parsing: " . json_encode($categories_text));
        error_log("[WhiskyAI] Response length: " . strlen($categories_text) . " bytes");
        
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
        
        $category_ids = array();
        $lines = explode("\n", $categories_text);
        error_log("[WhiskyAI] Split into " . count($lines) . " lines");
        
        foreach ($lines as $idx => $line) {
            $original_line = $line;
            // Remove common list prefixes: *, -, •, numbers with period/paren
            $category = preg_replace('/^[\s*\-•\d.)\s]+/', '', $line);
            $category = trim($category, " \t\n\r");
            
            error_log("[WhiskyAI] Line $idx: Original=" . json_encode($original_line) . " Cleaned=" . json_encode($category));
            
            if (empty($category)) {
                error_log("[WhiskyAI]   -> Skipping empty line");
                continue;
            }
            
            // Try exact match
            if (isset($this->flavor_categories[$category])) {
                error_log("[WhiskyAI]   -> MATCHED (exact): '$category' => " . $this->flavor_categories[$category]);
                $category_ids[] = $this->flavor_categories[$category];
                continue;
            }
            
            // Try case-insensitive match
            $found = false;
            foreach ($this->flavor_categories as $cat_name => $cat_id) {
                if (strtolower($category) === strtolower($cat_name)) {
                    error_log("[WhiskyAI]   -> MATCHED (case-insensitive): '$category' => '$cat_name' (ID: $cat_id)");
                    $category_ids[] = $cat_id;
                    $found = true;
                    break;
                }
            }
            
            if ($found) {
                continue;
            }
            
            // Try synonym matching
            foreach ($category_synonyms as $main_category => $synonyms) {
                foreach ($synonyms as $synonym) {
                    if (strtolower($category) === strtolower($synonym)) {
                        error_log("[WhiskyAI]   -> MATCHED (synonym): '$category' => '$main_category' (ID: " . $this->flavor_categories[$main_category] . ")");
                        $category_ids[] = $this->flavor_categories[$main_category];
                        $found = true;
                        break 2;
                    }
                }
            }
            
            if (!$found) {
                error_log("[WhiskyAI]   -> NOT MATCHED: '$category'");
            }
        }
        
        error_log("[WhiskyAI] Final parsed categories: " . json_encode(array_unique($category_ids)));
        return array_unique($category_ids);
    }

    /**
     * Handle errors with intelligent rescheduling
     */
    private function handle_async_error($product_id, $exception, $task_type) {
        $retry_count = intval(get_post_meta($product_id, '_whisky_ai_retry_' . $task_type, true));
        $error_code = $this->extract_error_code($exception);
        
        error_log("[WhiskyAI] Error handling for product {$product_id}, retry_count={$retry_count}");
        
        // If 5xx error or timeout, reschedule for later
        if ($error_code >= 500 && $error_code < 600) {
            if ($retry_count < self::MAX_RETRIES) {
                error_log("[WhiskyAI] 5xx error detected, rescheduling in " . self::ERROR_RETRY_MINUTES . " minutes");
                self::queue_product($product_id, $task_type, self::ERROR_RETRY_MINUTES * 60);
                update_post_meta($product_id, '_whisky_ai_retry_' . $task_type, $retry_count + 1);
            } else {
                error_log("[WhiskyAI] Max retries exceeded for product {$product_id}");
                delete_post_meta($product_id, '_whisky_ai_retry_' . $task_type);
            }
        } else {
            // For other errors, log and don't reschedule
            delete_post_meta($product_id, '_whisky_ai_retry_' . $task_type);
        }
        
        $this->log_error($exception->getMessage(), $product_id);
    }

    /**
     * Extract error code from exception or message
     */
    private function extract_error_code($exception) {
        if ($exception->getCode()) {
            return $exception->getCode();
        }
        
        // Try to extract from message (e.g., "HTTP 503: ...")
        if (preg_match('/HTTP\s+(\d{3})/', $exception->getMessage(), $matches)) {
            return intval($matches[1]);
        }
        
        return 0;
    }

    /**
     * Log success to post meta
     */
    private function log_success($action, $product_id) {
        update_post_meta($product_id, '_whisky_ai_last_' . $this->task_type, time());
        delete_post_meta($product_id, '_whisky_ai_error_' . $this->task_type);
    }

    /**
     * Log error to post meta
     */
    private function log_error($error_message, $product_id) {
        update_post_meta($product_id, '_whisky_ai_error_' . $this->task_type, $error_message);
        update_post_meta($product_id, '_whisky_ai_error_time_' . $this->task_type, time());
    }
}
