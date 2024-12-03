<?php

class OpenAI {
    private $api_key;
    private $api_base = 'https://api.openai.com/v1';

    public function __construct($api_key) {
        $this->api_key = $api_key;
    }

    public function chat($params) {
        return $this->request('chat/completions', $params);
    }

    public function models() {
        return $this->request('models', [], 'GET');
    }

    private function request($endpoint, $params = [], $method = 'POST') {
        $url = $this->api_base . '/' . $endpoint;

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        );

        if ($method === 'POST') {
            $args['body'] = json_encode($params);
            $args['method'] = 'POST';
            $response = wp_remote_post($url, $args);
        } else {
            $args['method'] = 'GET';
            $response = wp_remote_get($url, $args);
        }

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
                    'message' => 'Invalid response from OpenAI API'
                )
            );
        }

        return $data;
    }
} 