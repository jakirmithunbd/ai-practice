<?php

defined( 'ABSPATH' ) || exit;

// ─────────────────────────────────────────────
// 1. Register Category
// ─────────────────────────────────────────────
add_action( 'wp_abilities_api_categories_init', function () {

    wp_register_ability_category( 'ai-practice', [
        'label'       => __( 'AI Practice', 'ai-practice' ),
        'description' => __( 'Abilities registered by the AI Practice plugin.', 'ai-practice' ),
    ] );
} );


// ─────────────────────────────────────────────
// 2. Register Abilities
// ─────────────────────────────────────────────
add_action( 'wp_abilities_api_init', function () {

    // Ability 1: Site Info
    wp_register_ability( 'ai-practice/site-info', [
        'label'            => __( 'Get Site Info', 'ai-practice' ),
        'description'      => __( 'Returns basic information about this WordPress site.', 'ai-practice' ),
        'category'         => 'ai-practice',
        'input_schema'     => [],
        'output_schema'    => [
            'type'       => 'object',
            'properties' => [
                'site_name'         => [ 'type' => 'string', 'description' => 'Name of the site' ],
                'site_url'          => [ 'type' => 'string', 'description' => 'URL of the site' ],
                'tagline'           => [ 'type' => 'string', 'description' => 'Site tagline' ],
                'wordpress_version' => [ 'type' => 'string', 'description' => 'WordPress version' ],
                'active_theme'      => [ 'type' => 'string', 'description' => 'Active theme name' ],
                'post_count'        => [ 'type' => 'integer', 'description' => 'Number of published posts' ],
                'page_count'        => [ 'type' => 'integer', 'description' => 'Number of published pages' ],
            ],
        ],
        'execute_callback'    => 'ai_practice_ability_site_info',
        'permission_callback' => fn() => current_user_can( 'manage_options' ),
        'meta'                => [ 'show_in_rest' => true ],
    ] );

    // Ability 2: Summarize Post
    wp_register_ability( 'ai-practice/summarize-post', [
        'label'            => __( 'Summarize Post', 'ai-practice' ),
        'description'      => __( 'Generates a short AI summary of a WordPress post.', 'ai-practice' ),
        'category'         => 'ai-practice',
        'input_schema'     => [
            'type'       => 'object',
            'required'   => [ 'post_id' ],
            'properties' => [
                'post_id' => [ 'type' => 'integer', 'description' => 'The ID of the post to summarize' ],
            ],
        ],
        'output_schema'    => [
            'type'       => 'object',
            'properties' => [
                'title'   => [ 'type' => 'string', 'description' => 'Post title' ],
                'summary' => [ 'type' => 'string', 'description' => 'AI-generated summary' ],
            ],
        ],
        'execute_callback'    => 'ai_practice_ability_summarize_post',
        'permission_callback' => fn() => current_user_can( 'edit_posts' ),
        'meta'                => [ 'show_in_rest' => true ],
    ] );

    // Ability 3: Generate SEO Description
    wp_register_ability( 'ai-practice/seo-description', [
        'label'            => __( 'Generate SEO Description', 'ai-practice' ),
        'description'      => __( 'Generates a 155-character SEO meta description for a post.', 'ai-practice' ),
        'category'         => 'ai-practice',
        'input_schema'     => [
            'type'       => 'object',
            'required'   => [ 'post_id' ],
            'properties' => [
                'post_id' => [ 'type' => 'integer', 'description' => 'The ID of the post' ],
            ],
        ],
        'output_schema'    => [
            'type'       => 'object',
            'properties' => [
                'title'       => [ 'type' => 'string', 'description' => 'Post title' ],
                'description' => [ 'type' => 'string', 'description' => 'SEO meta description (max 155 chars)' ],
                'length'      => [ 'type' => 'integer', 'description' => 'Character count' ],
            ],
        ],
        'execute_callback'    => 'ai_practice_ability_seo_description',
        'permission_callback' => fn() => current_user_can( 'edit_posts' ),
        'meta'                => [ 'show_in_rest' => true ],
    ] );

    // Ability 4: Generate Text
    wp_register_ability( 'ai-practice/generate-text', [
        'label'            => __( 'Generate Text', 'ai-practice' ),
        'description'      => __( 'Sends a prompt to AI and returns generated text.', 'ai-practice' ),
        'category'         => 'ai-practice',
        'input_schema'     => [
            'type'       => 'object',
            'required'   => [ 'prompt' ],
            'properties' => [
                'prompt'     => [ 'type' => 'string', 'description' => 'The prompt to send to AI' ],
                'system'     => [ 'type' => 'string', 'description' => 'Optional system prompt' ],
                'max_tokens' => [ 'type' => 'integer', 'description' => 'Max tokens (default 512)' ],
            ],
        ],
        'output_schema'    => [
            'type'       => 'object',
            'properties' => [
                'response' => [ 'type' => 'string', 'description' => 'AI-generated response' ],
                'model'    => [ 'type' => 'string', 'description' => 'Model used' ],
            ],
        ],
        'execute_callback'    => 'ai_practice_ability_generate_text',
        'permission_callback' => fn() => current_user_can( 'edit_posts' ),
        'meta'                => [ 'show_in_rest' => true ],
    ] );
} );


// ─────────────────────────────────────────────
// 3. Execute Callbacks
// ─────────────────────────────────────────────

function ai_practice_ability_site_info(): array {
    $posts = wp_count_posts( 'post' );
    $pages = wp_count_posts( 'page' );

    return [
        'site_name'         => get_bloginfo( 'name' ),
        'site_url'          => get_bloginfo( 'url' ),
        'tagline'           => get_bloginfo( 'description' ),
        'wordpress_version' => get_bloginfo( 'version' ),
        'active_theme'      => wp_get_theme()->get( 'Name' ),
        'post_count'        => (int) $posts->publish,
        'page_count'        => (int) $pages->publish,
    ];
}

function ai_practice_ability_summarize_post( array $input ): array|WP_Error {
    $post = get_post( absint( $input['post_id'] ) );

    if ( ! $post ) {
        return new WP_Error( 'not_found', 'Post not found.' );
    }

    $content = substr( wp_strip_all_tags( $post->post_content ), 0, 3000 );
    $api     = new AI_Practice_Anthropic();
    $result  = $api->send_message(
        "Summarize this article in 2-3 sentences:\n\n" . $content,
        'You are a helpful writing assistant. Be concise and clear.'
    );

    if ( is_wp_error( $result ) ) {
        return $result;
    }

    return [
        'title'   => $post->post_title,
        'summary' => $result,
    ];
}

function ai_practice_ability_seo_description( array $input ): array|WP_Error {
    $post = get_post( absint( $input['post_id'] ) );

    if ( ! $post ) {
        return new WP_Error( 'not_found', 'Post not found.' );
    }

    $content = substr( wp_strip_all_tags( $post->post_content ), 0, 2000 );
    $api     = new AI_Practice_Anthropic();
    $result  = $api->send_message(
        "Post title: {$post->post_title}\n\nContent: {$content}",
        'Write a single SEO meta description under 155 characters. No quotes, no label, just the description.'
    );

    if ( is_wp_error( $result ) ) {
        return $result;
    }

    $description = substr( trim( $result ), 0, 155 );

    return [
        'title'       => $post->post_title,
        'description' => $description,
        'length'      => strlen( $description ),
    ];
}

function ai_practice_ability_generate_text( array $input ): array|WP_Error {
    $max_tokens = isset( $input['max_tokens'] ) ? (int) $input['max_tokens'] : 512;
    $api        = new AI_Practice_Anthropic( $max_tokens );
    $result     = $api->send_message(
        $input['prompt'],
        $input['system'] ?? ''
    );

    if ( is_wp_error( $result ) ) {
        return $result;
    }

    return [
        'response' => $result,
        'model'    => 'llama-3.1-8b-instant',
    ];
}
