<?php

class Gemini {
    private $api_key;
    private $api_base = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct($api_key) {
        $this->api_key = $api_key;
    }

    public function chat($params) {
        // Extract model from params
        $model = $params['model'] ?? 'gemini-2.5-flash';
        
        // Convert OpenAI format to Gemini format
        $gemini_params = array(
            'contents' => $this->convert_messages_to_contents($params['messages'] ?? array())
        );
        
        // Add generation config if temperature is specified
        if (isset($params['temperature'])) {
            $gemini_params['generationConfig'] = array(
                'temperature' => $params['temperature']
            );
        }
        
        return $this->request($model, $gemini_params);
    }

    private function convert_messages_to_contents($messages) {
        $contents = array();
        
        foreach ($messages as $message) {
            $role = $message['role'] === 'user' ? 'user' : 'model';
            
            $contents[] = array(
                'role' => $role,
                'parts' => array(
                    array(
                        'text' => $message['content']
                    )
                )
            );
        }
        
        return $contents;
    }

    private function request($model, $params) {
        $url = $this->api_base . '/' . $model . ':generateContent?key=' . $this->api_key;

        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($params),
            'method' => 'POST',
            'timeout' => 20,  // Reduced from 30 to 20 seconds
            'sslverify' => true
        );

        error_log('[WhiskyAI] Gemini API Request: ' . $url);
        error_log('[WhiskyAI] Request body size: ' . strlen($args['body']) . ' bytes');
$error_msg = $response->get_error_message();
            error_log('[WhiskyAI] Gemini API WP_Error: ' . $error_msg);
            return array(
                'error' => array(
                    'message' => 'API Connection Error: ' . $error_msg
                )
            );
        }

        $body = wp_remote_retrieve_body($response);
        $code = wp_remote_retrieve_response_code($response);
        
        error_log('[WhiskyAI] Gemini API Response Code: ' . $code);
        error_log('[WhiskyAI] Gemini API Response Body size: ' . strlen($body) . ' bytes');
        
        // Check for server errors
        if ($code >= 500) {
            error_log('[WhiskyAI] Server Error: ' . $body);
            return array(
                'error' => array(
                    'message' => 'Server Error (HTTP ' . $code . '): Please try again later'
                )
            );
        }
        $error_message = $data['error']['message'] ?? 'Unknown Gemini API error';
            error_log('[WhiskyAI] Gemini API Error: ' . $error_message);
            return array(
                'error' => array(
                    'message' => $error_message
                )
            );
        }

        // Convert Gemini response to OpenAI-like format for compatibility
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            $output_text = $data['candidates'][0]['content']['parts'][0]['text'];
            error_log('[WhiskyAI] Success: Generated ' . strlen($output_text) . ' chars');
            return array(
                'choices' => array(
                    array(
                        'message' => array(
                            'content' => $output_text
                        )
                    )
                )
            );
        }

        error_log('[WhiskyAI] Unexpected response format: ' . json_encode($data));                'error' => array(
                    'message' => $data['error']['message'] ?? 'Unknown Gemini API error'
                )
            );
        }

        // Convert Gemini response to OpenAI-like format for compatibility
        if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
            return array(
                'choices' => array(
                    array(
                        'message' => array(
                            'content' => $data['candidates'][0]['content']['parts'][0]['text']
                        )
                    )
                )
            );
        }

        return array(
            'error' => array(
                'message' => 'Unexpected response format from Gemini API'
            )
        );
    }
}
