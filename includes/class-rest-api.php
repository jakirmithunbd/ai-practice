<?php

class AI_Practice_REST_API
{
    private string $namespace = 'ai-practice/v1';

    public function __construct()
    {
        add_action('rest_api_init', [ $this, 'register_routes' ]);
    }

    public function register_routes(): void
    {

        // POST /wp-json/ai-practice/v1/generate
        register_rest_route($this->namespace, '/generate', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_generate' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args'                => $this->get_generate_args(),
        ]);

        // POST /wp-json/ai-practice/v1/summarize-post
        register_rest_route($this->namespace, '/summarize-post', [
            'methods'             => WP_REST_Server::CREATABLE,
            'callback'            => [ $this, 'handle_summarize_post' ],
            'permission_callback' => [ $this, 'check_permission' ],
            'args'                => [
                'post_id' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'sanitize_callback' => 'absint',
                    'validate_callback' => function ($value) {
                        return get_post($value) !== null;
                    },
                ],
            ],
        ]);

        // GET /wp-json/ai-practice/v1/status
        register_rest_route($this->namespace, '/status', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'handle_status' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ]);

        // GET /wp-json/ai-practice/v1/site-info
        register_rest_route($this->namespace, '/site-info', [
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => [ $this, 'handle_site_info' ],
            'permission_callback' => [ $this, 'check_permission' ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Permission
    // -------------------------------------------------------------------------

    public function check_permission(WP_REST_Request $request): bool|WP_Error
    {
        // Open for local practice — lock this down before going to production
        return true;
    }

    // -------------------------------------------------------------------------
    // Args schema
    // -------------------------------------------------------------------------

    private function get_generate_args(): array
    {
        return [
            'prompt' => [
                'required'          => true,
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'validate_callback' => function ($value) {
                    return strlen(trim($value)) >= 3;
                },
                'description'       => 'The user prompt to send to Claude.',
            ],
            'system' => [
                'required'          => false,
                'type'              => 'string',
                'default'           => '',
                'sanitize_callback' => 'sanitize_textarea_field',
                'description'       => 'Optional system prompt.',
            ],
            'max_tokens' => [
                'required'          => false,
                'type'              => 'integer',
                'default'           => 512,
                'minimum'           => 1,
                'maximum'           => 4096,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    // -------------------------------------------------------------------------
    // Handlers
    // -------------------------------------------------------------------------

    public function handle_generate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Rate limit: 1 call per 5 seconds per user
        $user_id    = get_current_user_id();
        $cache_key  = 'ai_practice_rate_' . $user_id;

        if (get_transient($cache_key)) {
            return new WP_Error(
                'rate_limited',
                'Please wait a moment before making another request.',
                [ 'status' => 429 ]
            );
        }

        set_transient($cache_key, true, 5);

        $api    = new AI_Practice_Anthropic($request->get_param('max_tokens'));
        $result = $api->send_message(
            $request->get_param('prompt'),
            $request->get_param('system')
        );

        if (is_wp_error($result)) {
            return new WP_Error(
                $result->get_error_code(),
                $result->get_error_message(),
                [ 'status' => 502 ]
            );
        }

        return rest_ensure_response([
            'success'  => true,
            'response' => $result,
            'model'    => 'claude-sonnet-4-6',
            'prompt'   => $request->get_param('prompt'),
        ]);
    }

    public function handle_summarize_post(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $post = get_post($request->get_param('post_id'));

        $content = wp_strip_all_tags($post->post_content);
        $content = substr($content, 0, 3000); // stay within token limits

        $api    = new AI_Practice_Anthropic();
        $result = $api->send_message(
            "Summarize this article in 2-3 sentences:\n\n" . $content,
            'You are a helpful writing assistant. Be concise and clear.'
        );

        if (is_wp_error($result)) {
            return new WP_Error($result->get_error_code(), $result->get_error_message(), [ 'status' => 502 ]);
        }

        return rest_ensure_response([
            'success'  => true,
            'post_id'  => $post->ID,
            'title'    => $post->post_title,
            'summary'  => $result,
        ]);
    }

    public function handle_site_info(WP_REST_Request $request): WP_REST_Response
    {
        $post_counts    = wp_count_posts('post');
        $page_counts    = wp_count_posts('page');
        $product_counts = wp_count_posts('product');

        return rest_ensure_response([
            'success'  => true,
            'site'     => [
                'name'    => get_bloginfo('name'),
                'url'     => get_site_url(),
                'tagline' => get_bloginfo('description'),
            ],
            'counts'   => [
                'posts'    => (int) $post_counts->publish,
                'pages'    => (int) $page_counts->publish,
                'products' => class_exists('WooCommerce') ? (int) $product_counts->publish : 'WooCommerce not active',
            ],
            'woocommerce' => class_exists('WooCommerce'),
        ]);
    }

    public function handle_status(WP_REST_Request $request): WP_REST_Response
    {
        $key = get_option('ai_practice_api_key', '');

        return rest_ensure_response([
            'success'   => true,
            'api_ready' => ! empty($key),
            'endpoints' => [
                'generate'       => rest_url($this->namespace . '/generate'),
                'summarize_post' => rest_url($this->namespace . '/summarize-post'),
                'status'         => rest_url($this->namespace . '/status'),
            ],
        ]);
    }
}
