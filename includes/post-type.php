<?php
defined('ABSPATH') || exit;

add_action('init', 'ww_quiz_register_post_type');
function ww_quiz_register_post_type() {
    register_post_type('ww_quiz', [
        'labels' => [
            'name'          => 'Quizze',
            'singular_name' => 'Quiz',
            'add_new'       => 'Neues Quiz',
            'add_new_item'  => 'Neues Quiz erstellen',
            'edit_item'     => 'Quiz bearbeiten',
            'all_items'     => 'Alle Quizze',
        ],
        'public'       => false,
        'show_ui'      => false, // wir bauen eigene Admin-Seiten
        'show_in_menu' => false,
        'supports'     => ['title'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
    ]);
}
