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
            'timeout' => 30
        );

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return array(
                'error' => array(
                    'message' => $response->get_error_message()
                )
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!$data) {
            return array(
                'error' => array(
                    'message' => 'Invalid response from Gemini API'
                )
            );
        }

        // Handle Gemini error responses
        if (isset($data['error'])) {
            return array(
                'error' => array(
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
