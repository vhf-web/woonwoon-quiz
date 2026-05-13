<?php
/**
 * Plugin Name: Woonwoon Quiz
 * Description: Interaktive Quizze mit mehreren Ergebnissen, Drag & Drop, Popup und GA4 Tracking.
 * Version: 2.0.0
 * Author: woonwoon.de
 * Text Domain: woonwoon-quiz
 */

defined('ABSPATH') || exit;

define('WW_QUIZ_VERSION', '2.0.0');
define('WW_QUIZ_PATH', plugin_dir_path(__FILE__));
define('WW_QUIZ_URL', plugin_dir_url(__FILE__));
define('WW_QUIZ_CAP', 'edit_woonwoon_quiz');

add_action('init', 'ww_quiz_load_textdomain');
function ww_quiz_load_textdomain() {
    load_plugin_textdomain('woonwoon-quiz', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

require_once WW_QUIZ_PATH . 'includes/post-type.php';
require_once WW_QUIZ_PATH . 'includes/roles.php';
require_once WW_QUIZ_PATH . 'includes/shortcode.php';
require_once WW_QUIZ_PATH . 'includes/popup.php';
require_once WW_QUIZ_PATH . 'admin/admin.php';

add_action('wp_enqueue_scripts', 'ww_quiz_enqueue_frontend_styles');
function ww_quiz_enqueue_frontend_styles() {
    if (is_admin()) {
        return;
    }
    $path = WW_QUIZ_PATH . 'assets/css/quiz-frontend.css';
    if (!is_readable($path)) {
        return;
    }
    wp_enqueue_style(
        'ww-quiz-frontend',
        WW_QUIZ_URL . 'assets/css/quiz-frontend.css',
        [],
        (string) filemtime($path)
    );
}

add_action('admin_enqueue_scripts', 'ww_quiz_enqueue_admin_preview_styles');
function ww_quiz_enqueue_admin_preview_styles($hook_suffix) {
    if (strpos($hook_suffix, 'ww-quiz-edit') === false) {
        return;
    }
    $path = WW_QUIZ_PATH . 'assets/css/quiz-frontend.css';
    if (!is_readable($path)) {
        return;
    }
    wp_enqueue_style(
        'ww-quiz-frontend',
        WW_QUIZ_URL . 'assets/css/quiz-frontend.css',
        [],
        (string) filemtime($path)
    );
}

register_activation_hook(__FILE__, 'ww_quiz_activate');
function ww_quiz_activate() {
    ww_quiz_register_post_type();
    flush_rewrite_rules();
    ww_quiz_setup_roles();
}

register_deactivation_hook(__FILE__, 'ww_quiz_deactivate');
function ww_quiz_deactivate() {
    flush_rewrite_rules();
    ww_quiz_remove_roles();
}
