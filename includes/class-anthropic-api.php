<?php

class AI_Practice_Anthropic
{
    private string $api_key;
    private string $api_url   = 'https://api.groq.com/openai/v1/chat/completions';
    private string $model     = 'llama-3.1-8b-instant';
    private int    $max_tokens = 1024;

    public function __construct(int $max_tokens = 1024)
    {
        $this->api_key    = get_option('ai_practice_api_key', '');
        $this->max_tokens = $max_tokens;
    }

    /**
     * Send a message to Groq (OpenAI-compatible format).
     *
     * @param string $user_message
     * @param string $system_prompt
     * @return string|WP_Error
     */
    public function send_message(string $user_message, string $system_prompt = ''): string|WP_Error
    {
        if (empty($this->api_key)) {
            return new WP_Error('missing_key', 'Groq API key is not set.');
        }

        $messages = [];

        if (! empty($system_prompt)) {
            $messages[] = ['role' => 'system', 'content' => $system_prompt];
        }

        $messages[] = ['role' => 'user', 'content' => $user_message];

        $body = [
            'model'      => $this->model,
            'max_tokens' => $this->max_tokens,
            'messages'   => $messages,
        ];

        $response = wp_remote_post($this->api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode($body),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code($response);
        $data   = json_decode(wp_remote_retrieve_body($response), true);

        if (200 !== $status) {
            return new WP_Error('api_error', $data['error']['message'] ?? 'Unknown API error');
        }

        return $data['choices'][0]['message']['content'] ?? '';
    }
}
