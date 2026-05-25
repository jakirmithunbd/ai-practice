<?php

/**
 * Plugin Name: AI Practice
 * Description: Anthropic API practice plugin
 * Version: 1.0.0
 */

defined('ABSPATH') || exit;


require_once plugin_dir_path(__FILE__) . 'includes/class-anthropic-api.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-abilities.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-agent.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-rest-api.php';
require_once plugin_dir_path(__FILE__) . 'admin/settings-page.php';

new AI_Practice_REST_API();
