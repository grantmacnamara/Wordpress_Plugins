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
    private $openai_key;
    private $flavor_categories;
    private $options;

    public function __construct() {
        $this->options = get_option('whisky_ai_settings');
        $this->openai_key = $this->options['openai_api_key'] ?? '';
        
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

        // Add AJAX handlers
        add_action('wp_ajax_generate_whisky_descriptions', array($this, 'generate_descriptions'));
        add_action('wp_ajax_generate_whisky_categories', array($this, 'generate_categories_endpoint'));
    }

    public function generate_descriptions() {
        check_ajax_referer('whisky_ai_nonce', 'nonce');

        if (empty($this->openai_key)) {
            wp_send_json_error(array(
                'message' => 'OpenAI API key is not set',
                'debug' => array(
                    'error' => 'Missing API Key',
                    'key_length' => 0
                )
            ));
            return;
        }

        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);

        if (!$product) {
            wp_send_json_error(array(
                'message' => 'Product not found',
                'debug' => array(
                    'error' => 'Invalid Product ID',
                    'product_id' => $product_id
                )
            ));
            return;
        }

        try {
            // Generate description
            $description_result = $this->generate_description($product->get_name());
            $description = $description_result['content'];

            // Update product
            $product->set_description($description);
            
            // Add DescUpdated tag
            $tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'ids'));
            $desc_updated_tag = get_term_by('name', 'DescUpdated', 'product_tag');
            if ($desc_updated_tag) {
                if (!in_array($desc_updated_tag->term_id, $tags)) {
                    $tags[] = $desc_updated_tag->term_id;
                }
            } else {
                $new_tag = wp_insert_term('DescUpdated', 'product_tag');
                if (!is_wp_error($new_tag)) {
                    $tags[] = $new_tag['term_id'];
                }
            }
            wp_set_post_terms($product_id, array_unique($tags), 'product_tag');
            
            $product->save();

            wp_send_json_success(array(
                'message' => 'Description updated successfully',
                'description' => $description,
                'debug' => array(
                    'product' => array(
                        'id' => $product_id,
                        'name' => $product->get_name(),
                        'original_description' => $product->get_description(),
                        'new_description' => $description
                    ),
                    'openai' => $description_result['debug'],
                    'tags' => array(
                        'before' => wp_get_post_terms($product_id, 'product_tag', array('fields' => 'names')),
                        'after' => wp_get_object_terms($product_id, 'product_tag', array('fields' => 'names'))
                    )
                )
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'debug' => array(
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'openai_debug' => method_exists($e, 'getDebugInfo') ? $e->getDebugInfo() : null
                )
            ));
        }
    }

    public function generate_categories_endpoint() {
        check_ajax_referer('whisky_ai_nonce', 'nonce');

        if (empty($this->openai_key)) {
            wp_send_json_error(array(
                'message' => 'OpenAI API key is not set',
                'debug' => array(
                    'error' => 'Missing API Key',
                    'key_length' => 0
                )
            ));
            return;
        }

        $product_id = intval($_POST['product_id']);
        $product = wc_get_product($product_id);

        if (!$product) {
            wp_send_json_error(array(
                'message' => 'Product not found',
                'debug' => array(
                    'error' => 'Invalid Product ID',
                    'product_id' => $product_id
                )
            ));
            return;
        }

        try {
            // Generate categories
            $categories_result = $this->generate_categories($product->get_name());
            $categories = $categories_result['category_ids'];

            // Update categories
            $current_categories = $product->get_category_ids();
            $new_categories = array_merge($current_categories, $categories);
            $product->set_category_ids($new_categories);
            
            // Add CatUpdated tag
            $tags = wp_get_post_terms($product_id, 'product_tag', array('fields' => 'ids'));
            $cat_updated_tag = get_term_by('name', 'CatUpdated', 'product_tag');
            if ($cat_updated_tag) {
                if (!in_array($cat_updated_tag->term_id, $tags)) {
                    $tags[] = $cat_updated_tag->term_id;
                }
            } else {
                $new_tag = wp_insert_term('CatUpdated', 'product_tag');
                if (!is_wp_error($new_tag)) {
                    $tags[] = $new_tag['term_id'];
                }
            }
            wp_set_post_terms($product_id, array_unique($tags), 'product_tag');
            
            $product->save();

            wp_send_json_success(array(
                'message' => 'Categories updated successfully',
                'categories' => $categories,
                'debug' => array(
                    'product' => array(
                        'id' => $product_id,
                        'name' => $product->get_name(),
                        'original_categories' => $current_categories,
                        'new_categories' => $new_categories,
                        'detected_categories' => array_keys(array_filter($this->flavor_categories, function($id) use ($categories) {
                            return in_array($id, $categories);
                        }))
                    ),
                    'openai' => $categories_result['debug'],
                    'raw_response' => $categories_result['raw_text'],
                    'tags' => array(
                        'before' => wp_get_post_terms($product_id, 'product_tag', array('fields' => 'names')),
                        'after' => wp_get_object_terms($product_id, 'product_tag', array('fields' => 'names'))
                    )
                )
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'debug' => array(
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'openai_debug' => method_exists($e, 'getDebugInfo') ? $e->getDebugInfo() : null
                )
            ));
        }
    }

    private function generate_description($product_name) {
        $request_data = array(
            'model' => 'gpt-4o-mini',
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

        $api_response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->openai_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($request_data)
        ));

        if (is_wp_error($api_response)) {
            throw new WhiskyAIException(
                'OpenAI API Connection Error: ' . $api_response->get_error_message(),
                0,
                null,
                array('wp_error' => $api_response->get_error_messages())
            );
        }

        $response_code = wp_remote_retrieve_response_code($api_response);
        $response_body = json_decode(wp_remote_retrieve_body($api_response), true);

        // Prepare debug info
        $debug_info = array(
            'api_request' => array(
                'endpoint' => 'https://api.openai.com/v1/chat/completions',
                'model' => 'gpt-3.5-turbo',
                'messages' => $request_data['messages']
            ),
            'api_response' => array(
                'status_code' => $response_code,
                'body' => $response_body
            )
        );

        if ($response_code !== 200) {
            throw new WhiskyAIException(
                'OpenAI API Error: ' . ($response_body['error']['message'] ?? 'Unknown error') . 
                ' (Status Code: ' . $response_code . ')',
                $response_code,
                null,
                $debug_info
            );
        }

        if (empty($response_body['choices'][0]['message']['content'])) {
            throw new WhiskyAIException(
                'No description generated from OpenAI',
                0,
                null,
                $debug_info
            );
        }

        return array(
            'content' => $response_body['choices'][0]['message']['content'],
            'debug' => $debug_info
        );
    }

    private function generate_categories($product_name) {
        $request_data = array(
            'model' => 'gpt-3.5-turbo',
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

        $api_response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->openai_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($request_data)
        ));

        if (is_wp_error($api_response)) {
            throw new WhiskyAIException(
                'OpenAI API Connection Error: ' . $api_response->get_error_message(),
                0,
                null,
                array('wp_error' => $api_response->get_error_messages())
            );
        }

        $response_code = wp_remote_retrieve_response_code($api_response);
        $response_body = json_decode(wp_remote_retrieve_body($api_response), true);

        // Prepare debug info
        $debug_info = array(
            'api_request' => array(
                'endpoint' => 'https://api.openai.com/v1/chat/completions',
                'model' => 'gpt-3.5-turbo',
                'messages' => $request_data['messages']
            ),
            'api_response' => array(
                'status_code' => $response_code,
                'body' => $response_body
            )
        );

        if ($response_code !== 200) {
            throw new WhiskyAIException(
                'OpenAI API Error: ' . ($response_body['error']['message'] ?? 'Unknown error') . 
                ' (Status Code: ' . $response_code . ')',
                $response_code,
                null,
                $debug_info
            );
        }

        if (empty($response_body['choices'][0]['message']['content'])) {
            throw new WhiskyAIException(
                'No categories generated from OpenAI',
                0,
                null,
                $debug_info
            );
        }
        
        $categories_text = $response_body['choices'][0]['message']['content'];
        
        // Improved category parsing
        $category_ids = array();
        $lines = explode("\n", $categories_text);
        foreach ($lines as $line) {
            // Clean up the line
            $category = trim($line, " -\t\n\r\0\x0B");
            if (isset($this->flavor_categories[$category])) {
                $category_ids[] = $this->flavor_categories[$category];
            }
        }

        return array(
            'category_ids' => $category_ids,
            'raw_text' => $categories_text,
            'debug' => array_merge($debug_info, array(
                'parsed_categories' => array_keys(array_filter($this->flavor_categories, function($id) use ($category_ids) {
                    return in_array($id, $category_ids);
                }))
            ))
        );
    }
}

// Initialize the core functionality
new WhiskyAICore(); 