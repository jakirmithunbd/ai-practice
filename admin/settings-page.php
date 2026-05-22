<?php

function ai_practice_register_settings(): void
{
    register_setting('ai_practice_group', 'ai_practice_api_key', [
        'sanitize_callback' => 'sanitize_text_field',
    ]);
}
add_action('admin_init', 'ai_practice_register_settings');

function ai_practice_add_menu(): void
{
    add_options_page('AI Practice', 'AI Practice', 'manage_options', 'ai-practice', 'ai_practice_render_page');
}
add_action('admin_menu', 'ai_practice_add_menu');

function ai_practice_render_page(): void
{
    ?>
    <div class="wrap">
        <h1>AI Practice Settings (Groq)</h1>
        <form method="post" action="options.php">
            <?php settings_fields('ai_practice_group'); ?>
            <table class="form-table">
                <tr>
                    <th>Groq API Key</th>
                    <td>
                        <input type="password" name="ai_practice_api_key"
                               value="<?php echo esc_attr(get_option('ai_practice_api_key')); ?>"
                               class="regular-text" />
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>

        <hr>
        <h2>Quick Test</h2>
        <p>
            <a href="<?php echo esc_url(admin_url('options-general.php?page=ai-practice&test=1')); ?>"
               class="button button-secondary">Run Test Call</a>
        </p>
        <?php
        if (isset($_GET['test'])) {
            $api    = new AI_Practice_Anthropic();
            $result = $api->send_message('Say hello and tell me what you are in one sentence.');

            if (is_wp_error($result)) {
                echo '<div class="notice notice-error"><p>' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>' . esc_html($result) . '</p></div>';
            }
        }
    ?>
    </div>
    <?php
}
