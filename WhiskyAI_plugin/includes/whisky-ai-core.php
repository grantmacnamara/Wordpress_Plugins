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
    private $openai;

    public function __construct() {
        $this->options = get_option('whisky_ai_settings');
        $this->openai_key = $this->options['openai_api_key'] ?? '';

        if (!empty($this->openai_key)) {
            $this->openai = new OpenAI($this->openai_key);
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

        // Add AJAX handlers
        add_action('wp_ajax_generate_whisky_descriptions', array($this, 'generate_descriptions'));
        add_action('wp_ajax_generate_whisky_categories', array($this, 'generate_categories_endpoint'));
    }

    public function generate_descriptions() {
        check_ajax_referer('whisky_ai_nonce', 'nonce');

        if (empty($this->openai_key) || !$this->openai) {
            wp_send_json_error(array('message' => 'OpenAI API key is not set or invalid.'));
            return;
        }

        $product_ids = isset($_POST['product_ids']) ? array_map('intval', (array)$_POST['product_ids']) : array();

        if (empty($product_ids)) {
            wp_send_json_error(array('message' => 'No product IDs provided.'));
            return;
        }

        $result = $this->process_descriptions($product_ids);

        if (empty($result['errors'])) {
            wp_send_json_success(array(
                'message' => 'Descriptions updated successfully.',
                'results' => $result['results']
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Some descriptions failed to update.',
                'errors' => $result['errors'],
                'results' => $result['results']
            ));
        }
    }

    public function process_descriptions($product_ids) {
        $results = array();
        $errors = array();

        foreach ($product_ids as $product_id) {
            $product = wc_get_product($product_id);

            if (!$product) {
                $errors[$product_id] = 'Product not found.';
                continue;
            }

            try {
                // Generate description
                $description_result = $this->generate_description($product->get_name());
                $description = $description_result['content'];

                // Update product
                $product->set_description($description);
                
                // Add DescUpdated tag
                wp_set_object_terms($product_id, 'DescUpdated', 'product_tag', true);
                
                $product->save();

                $results[$product_id] = array(
                    'success' => true,
                    'description' => $description,
                    'debug' => $description_result['debug']
                );

            } catch (Exception $e) {
                $errors[$product_id] = $e->getMessage();
            }
        }

        return ['results' => $results, 'errors' => $errors];
    }

    public function generate_categories_endpoint() {
        check_ajax_referer('whisky_ai_nonce', 'nonce');

        if (empty($this->openai_key) || !$this->openai) {
            wp_send_json_error(array('message' => 'OpenAI API key is not set or invalid.'));
            return;
        }

        $product_ids = isset($_POST['product_ids']) ? array_map('intval', (array)$_POST['product_ids']) : array();

        if (empty($product_ids)) {
            wp_send_json_error(array('message' => 'No product IDs provided.'));
            return;
        }
        
        $result = $this->process_categories($product_ids);

        if (empty($result['errors'])) {
            wp_send_json_success(array(
                'message' => 'Categories updated successfully.',
                'results' => $result['results']
            ));
        } else {
            wp_send_json_error(array(
                'message' => 'Some categories failed to update.',
                'errors' => $result['errors'],
                'results' => $result['results']
            ));
        }
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

    private function generate_description($product_name) {
        $request_data = array(
            'model' => $this->options['openai_model'] ?? 'gpt-4o-mini',
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

        $response = $this->openai->chat($request_data);

        if (isset($response['error'])) {
            throw new WhiskyAIException(
                'OpenAI API Error: ' . $response['error']['message'],
                0,
                null,
                array('api_request' => $request_data, 'api_response' => $response)
            );
        }

        if (empty($response['choices'][0]['message']['content'])) {
            throw new WhiskyAIException(
                'No description generated from OpenAI',
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
            'model' => $this->options['openai_model'] ?? 'gpt-3.5-turbo',
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

        $response = $this->openai->chat($request_data);

        if (isset($response['error'])) {
            throw new WhiskyAIException(
                'OpenAI API Error: ' . $response['error']['message'],
                0,
                null,
                array('api_request' => $request_data, 'api_response' => $response)
            );
        }

        if (empty($response['choices'][0]['message']['content'])) {
            throw new WhiskyAIException(
                'No categories generated from OpenAI',
                0,
                null,
                array('api_request' => $request_data, 'api_response' => $response)
            );
        }
        
        $categories_text = $response['choices'][0]['message']['content'];
        
        // Improved category parsing
        $category_ids = array();
        $lines = explode("
", $categories_text);
        foreach ($lines as $line) {
            // Clean up the line
            $category = trim($line, " -	
 ");
            if (isset($this->flavor_categories[$category])) {
                $category_ids[] = $this->flavor_categories[$category];
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