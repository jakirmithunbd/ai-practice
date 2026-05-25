<?php

defined( 'ABSPATH' ) || exit;

class AI_Practice_Agent
{
    private string $api_key;
    private string $api_url = 'https://api.anthropic.com/v1/messages';
    private string $model   = 'claude-sonnet-4-6';

    public function __construct()
    {
        $this->api_key = get_option( 'ai_practice_api_key', '' );
    }

    /**
     * Run the agent — sends message to Claude with WordPress abilities as tools.
     * Claude automatically decides which abilities to call, executes them, and returns a final answer.
     */
    public function run( string $message ): array|WP_Error
    {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'missing_key', 'API key is not set.' );
        }

        $tools    = $this->get_tools();
        $messages = [
            [ 'role' => 'user', 'content' => $message ],
        ];

        $tools_used = [];

        // Agentic loop — keeps going until Claude gives a final answer
        for ( $i = 0; $i < 5; $i++ ) {

            $response = $this->call_api( $messages, $tools );

            if ( is_wp_error( $response ) ) {
                return $response;
            }

            // Claude is done — no more tool calls
            if ( 'end_turn' === $response['stop_reason'] ) {
                $text = '';
                foreach ( $response['content'] as $block ) {
                    if ( 'text' === $block['type'] ) {
                        $text = $block['text'];
                        break;
                    }
                }

                return [
                    'answer'     => $text,
                    'tools_used' => $tools_used,
                    'iterations' => $i + 1,
                ];
            }

            // Claude wants to use tools
            if ( 'tool_use' === $response['stop_reason'] ) {

                // Add Claude's response (with tool_use blocks) to message history
                $messages[] = [ 'role' => 'assistant', 'content' => $response['content'] ];

                // Execute each tool call and collect results
                $tool_results = [];

                foreach ( $response['content'] as $block ) {
                    if ( 'tool_use' !== $block['type'] ) {
                        continue;
                    }

                    $tools_used[] = $block['name'];
                    $result       = $this->execute_ability( $block['name'], $block['input'] );

                    $tool_results[] = [
                        'type'        => 'tool_result',
                        'tool_use_id' => $block['id'],
                        'content'     => wp_json_encode( $result ),
                    ];
                }

                // Send all tool results back to Claude in one user message
                $messages[] = [ 'role' => 'user', 'content' => $tool_results ];
            }
        }

        return new WP_Error( 'max_iterations', 'Agent exceeded maximum iterations.' );
    }

    // ─────────────────────────────────────────────
    // Tools — WordPress Abilities in Claude's format
    // Claude uses "input_schema" (not "parameters" like OpenAI)
    // ─────────────────────────────────────────────

    private function get_tools(): array
    {
        return [
            [
                'name'         => 'site_info',
                'description'  => 'Get information about this WordPress site: name, URL, tagline, WordPress version, active theme, post count, page count.',
                'input_schema' => [ 'type' => 'object', 'properties' => [] ],
            ],
            [
                'name'         => 'summarize_post',
                'description'  => 'Generate an AI summary of a WordPress post by its ID.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'post_id' ],
                    'properties' => [
                        'post_id' => [ 'type' => 'integer', 'description' => 'The ID of the post to summarize' ],
                    ],
                ],
            ],
            [
                'name'         => 'seo_description',
                'description'  => 'Generate a 155-character SEO meta description for a WordPress post.',
                'input_schema' => [
                    'type'       => 'object',
                    'required'   => [ 'post_id' ],
                    'properties' => [
                        'post_id' => [ 'type' => 'integer', 'description' => 'The ID of the post' ],
                    ],
                ],
            ],
        ];
    }

    // ─────────────────────────────────────────────
    // Execute — maps tool name → WordPress ability callback
    // ─────────────────────────────────────────────

    private function execute_ability( string $tool_name, array $args ): mixed
    {
        switch ( $tool_name ) {
            case 'site_info':
                return ai_practice_ability_site_info();

            case 'summarize_post':
                return ai_practice_ability_summarize_post( $args );

            case 'seo_description':
                return ai_practice_ability_seo_description( $args );

            default:
                return [ 'error' => "Unknown tool: {$tool_name}" ];
        }
    }

    // ─────────────────────────────────────────────
    // Claude API call — Anthropic format
    // ─────────────────────────────────────────────

    private function call_api( array $messages, array $tools ): array|WP_Error
    {
        $response = wp_remote_post( $this->api_url, [
            'headers' => [
                'x-api-key'         => $this->api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'body' => wp_json_encode( [
                'model'      => $this->model,
                'max_tokens' => 1024,
                'system'     => 'You are a helpful WordPress site assistant. Use the available tools to answer questions about this site accurately. Always use tools when data is needed — do not guess.',
                'messages'   => $messages,
                'tools'      => $tools,
            ] ),
            'timeout' => 60,
        ] );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $status = wp_remote_retrieve_response_code( $response );
        $data   = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( 200 !== $status ) {
            return new WP_Error( 'api_error', $data['error']['message'] ?? 'Unknown API error' );
        }

        return $data;
    }
}
