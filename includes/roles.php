<?php
defined('ABSPATH') || exit;

function ww_quiz_setup_roles() {
    $admin = get_role('administrator');
    if ($admin) {
        $admin->add_cap(WW_QUIZ_CAP);
    }

    $editor = get_role('editor');
    if ($editor) {
        $editor->add_cap(WW_QUIZ_CAP);
    }

    if (!get_role('quiz_editor')) {
        add_role('quiz_editor', 'Quiz Editor', [
            'read'      => true,
            WW_QUIZ_CAP => true,
        ]);
    }
}

function ww_quiz_remove_roles() {
    remove_role('quiz_editor');
    $editor = get_role('editor');
    if ($editor) {
        $editor->remove_cap(WW_QUIZ_CAP);
    }
    $admin = get_role('administrator');
    if ($admin) {
        $admin->remove_cap(WW_QUIZ_CAP);
    }
}

// Re-check on every load
add_action('init', function () {
    $admin = get_role('administrator');
    if ($admin && !$admin->has_cap(WW_QUIZ_CAP)) {
        ww_quiz_setup_roles();
    }
});
