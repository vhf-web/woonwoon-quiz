<?php
defined('ABSPATH') || exit;

add_action('admin_menu', function () {
    add_menu_page('Quizze', 'Quizze', WW_QUIZ_CAP, 'ww-quizze', 'ww_admin_list', 'dashicons-location-alt', 30);
    add_submenu_page('ww-quizze', 'Alle Quizze', 'Alle Quizze', WW_QUIZ_CAP, 'ww-quizze', 'ww_admin_list');
    add_submenu_page('ww-quizze', 'Neues Quiz', 'Neues Quiz', WW_QUIZ_CAP, 'ww-quiz-new', 'ww_admin_new');
    add_submenu_page('ww-quizze', 'Einstellungen', 'Popup / Global', WW_QUIZ_CAP, 'ww-quiz-settings', 'ww_admin_settings');
});

add_action('admin_init', 'ww_admin_save');
function ww_admin_save() {
    if (!isset($_POST['ww_nonce'])) return;
    if (!wp_verify_nonce($_POST['ww_nonce'], 'ww_quiz_save')) return;
    if (!current_user_can(WW_QUIZ_CAP)) return;

    if (isset($_POST['ww_save_global'])) {
        update_option('ww_quiz_popup_id', intval($_POST['popup_id'] ?? 0));
        update_option('ww_quiz_popup_delay', intval($_POST['popup_delay'] ?? 5));
        wp_redirect(admin_url('admin.php?page=ww-quiz-settings&saved=1'));
        exit;
    }

    if (isset($_POST['ww_create_quiz'])) {
        $title = sanitize_text_field($_POST['quiz_title'] ?? 'Neues Quiz');
        $pid   = wp_insert_post(['post_title' => $title, 'post_type' => 'ww_quiz', 'post_status' => 'publish']);
        if ($pid && !is_wp_error($pid)) {
            wp_redirect(admin_url('admin.php?page=ww-quiz-edit&quiz_id=' . $pid . '&tab=settings'));
            exit;
        }
    }

    if (isset($_POST['ww_save_quiz'])) {
        $qid = intval($_POST['quiz_id'] ?? 0);
        if (!$qid || !get_post($qid)) return;
        $tab = sanitize_key($_POST['tab'] ?? 'settings');

        if ($tab === 'settings') {
            $settings = [
                'eyebrow'     => sanitize_text_field($_POST['s_eyebrow'] ?? ''),
                'title'       => sanitize_text_field($_POST['s_title'] ?? ''),
                'title_after' => sanitize_textarea_field($_POST['s_title_after'] ?? ''),
                'subtitle'    => sanitize_text_field($_POST['s_subtitle'] ?? ''),
                'color1'      => sanitize_hex_color($_POST['s_color1'] ?? '#3d5a80'),
                'color2'      => sanitize_hex_color($_POST['s_color2'] ?? '#e8d5b0'),
            ];
            update_post_meta($qid, '_ww_settings', $settings);
            wp_update_post(['ID' => $qid, 'post_title' => $settings['title']]);
        }

        if ($tab === 'questions') {
            $questions = [];
            $raw = $_POST['questions'] ?? [];
            foreach ($raw as $qdata) {
                $opts = array_map('sanitize_text_field', $qdata['opts'] ?? []);
                $pts  = array_map('intval', $qdata['pts'] ?? []);
                $questions[] = [
                    'q'    => sanitize_text_field($qdata['q'] ?? ''),
                    'opts' => $opts,
                    'pts'  => $pts,
                ];
            }
            update_post_meta($qid, '_ww_questions', $questions);
        }

        if ($tab === 'results') {
            $results = [];
            $raw = $_POST['results'] ?? [];
            foreach ($raw as $rdata) {
                $traits_str = sanitize_text_field($rdata['traits'] ?? '');
                $traits     = array_filter(array_map('trim', explode(',', $traits_str)));
                $results[]  = [
                    'min'    => intval($rdata['min'] ?? 0),
                    'name'   => sanitize_text_field($rdata['name'] ?? ''),
                    'traits' => array_values($traits),
                    'desc'   => sanitize_textarea_field($rdata['desc'] ?? ''),
                    'url'    => esc_url_raw($rdata['url'] ?? ''),
                ];
            }
            usort($results, fn($a, $b) => intval($b['min']) - intval($a['min']));
            update_post_meta($qid, '_ww_results', $results);
        }

        wp_redirect(admin_url('admin.php?page=ww-quiz-edit&quiz_id=' . $qid . '&tab=' . $tab . '&saved=1'));
        exit;
    }

    if (isset($_POST['ww_delete_quiz'])) {
        $qid = intval($_POST['quiz_id'] ?? 0);
        if ($qid) wp_delete_post($qid, true);
        wp_redirect(admin_url('admin.php?page=ww-quizze&deleted=1'));
        exit;
    }
}

add_action('admin_menu', function(){
    add_submenu_page(null, 'Quiz bearbeiten', '', WW_QUIZ_CAP, 'ww-quiz-edit', 'ww_admin_edit');
});

function ww_admin_header($title) {
    $saved   = isset($_GET['saved']);
    $deleted = isset($_GET['deleted']);
    ?>
    <div class="wrap">
    <h1 style="display:flex;align-items:center;gap:12px">
        <span style="font-size:24px">Quiz</span> <?php echo esc_html($title); ?>
    </h1>
    <?php if ($saved): ?><div class="notice notice-success is-dismissible"><p>Gespeichert.</p></div><?php endif; ?>
    <?php if ($deleted): ?><div class="notice notice-success is-dismissible"><p>Quiz geloescht.</p></div><?php endif; ?>
    <?php
}

function ww_admin_list() {
    ww_admin_header('Alle Quizze');
    $quizzes = get_posts(['post_type' => 'ww_quiz', 'posts_per_page' => -1, 'post_status' => 'publish']);
    ?>
    <p><a href="<?php echo admin_url('admin.php?page=ww-quiz-new'); ?>" class="button button-primary">+ Neues Quiz erstellen</a></p>
    <?php if (empty($quizzes)): ?>
        <p style="color:#888">Noch keine Quizze vorhanden.</p>
    <?php else: ?>
    <table class="widefat striped" style="max-width:800px">
        <thead><tr><th>Name</th><th>Shortcode</th><th>Fragen</th><th>Ergebnisse</th><th>Aktionen</th></tr></thead>
        <tbody>
        <?php foreach ($quizzes as $q):
            $qs = get_post_meta($q->ID, '_ww_questions', true) ?: [];
            $rs = get_post_meta($q->ID, '_ww_results', true) ?: [];
            $edit_url = admin_url('admin.php?page=ww-quiz-edit&quiz_id=' . $q->ID);
        ?>
            <tr>
                <td><strong><?php echo esc_html($q->post_title); ?></strong></td>
                <td><code>[woonwoon_quiz id="<?php echo $q->ID; ?>"]</code></td>
                <td><?php echo count($qs); ?></td>
                <td><?php echo count($rs); ?></td>
                <td>
                    <a href="<?php echo esc_url($edit_url . '&tab=settings'); ?>" class="button button-small">Bearbeiten</a>
                    <a href="<?php echo esc_url($edit_url . '&tab=preview'); ?>" class="button button-small">Vorschau</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
    </div>
    <?php
}

function ww_admin_new() {
    ww_admin_header('Neues Quiz erstellen');
    ?>
    <form method="post" style="max-width:500px">
        <?php wp_nonce_field('ww_quiz_save', 'ww_nonce'); ?>
        <input type="hidden" name="ww_create_quiz" value="1">
        <table class="form-table">
            <tr>
                <th><label for="quiz_title">Quiz-Name (intern)</label></th>
                <td><input type="text" id="quiz_title" name="quiz_title" class="large-text" required></td>
            </tr>
        </table>
        <p class="submit"><input type="submit" class="button button-primary button-large" value="Quiz erstellen"></p>
    </form>
    </div>
    <?php
}

function ww_admin_edit() {
    $qid = intval($_GET['quiz_id'] ?? 0);
    $tab = sanitize_key($_GET['tab'] ?? 'settings');
    if (!$qid) { echo '<div class="wrap"><p>Quiz nicht gefunden.</p></div>'; return; }

    $post      = get_post($qid);
    $settings  = get_post_meta($qid, '_ww_settings', true) ?: [];
    $questions = get_post_meta($qid, '_ww_questions', true) ?: [];
    $results   = get_post_meta($qid, '_ww_results', true) ?: [];

    if (empty($questions)) {
        $questions = array_fill(0, 10, ['q' => '', 'opts' => ['','','',''], 'pts' => [3,2,1,0]]);
    }
    if (empty($results)) {
        $results = array_fill(0, 8, ['min' => 0, 'name' => '', 'traits' => [], 'desc' => '', 'url' => '']);
    }

    ww_admin_header('Quiz bearbeiten: ' . esc_html($post->post_title ?? ''));
    $base_url = admin_url('admin.php?page=ww-quiz-edit&quiz_id=' . $qid);
    $tabs = ['settings' => 'Einstellungen', 'questions' => 'Fragen', 'results' => 'Ergebnisse', 'preview' => 'Vorschau'];
    ?>
    <div style="display:flex;gap:8px;margin-bottom:16px">
        <?php foreach ($tabs as $t => $label): ?>
        <a href="<?php echo esc_url($base_url . '&tab=' . $t); ?>" class="button <?php echo $tab === $t ? 'button-primary' : ''; ?>"><?php echo esc_html($label); ?></a>
        <?php endforeach; ?>
    </div>

    <div style="background:#fff;border:1px solid #ddd;padding:20px;max-width:920px">
    <p><strong>Shortcode:</strong> <code>[woonwoon_quiz id="<?php echo $qid; ?>"]</code></p>

    <?php if ($tab === 'settings'): ?>
    <form method="post">
        <?php wp_nonce_field('ww_quiz_save', 'ww_nonce'); ?>
        <input type="hidden" name="ww_save_quiz" value="1">
        <input type="hidden" name="quiz_id" value="<?php echo $qid; ?>">
        <input type="hidden" name="tab" value="settings">
        <table class="form-table">
            <tr><th>Eyebrow (ueber Titel)</th><td><input type="text" name="s_eyebrow" value="<?php echo esc_attr($settings['eyebrow'] ?? ''); ?>" class="large-text"></td></tr>
            <tr><th>Angezeigter Titel</th><td><input type="text" name="s_title" value="<?php echo esc_attr($settings['title'] ?? ''); ?>" class="large-text"></td></tr>
            <tr><th>Text nach Titel</th><td><textarea name="s_title_after" rows="3" class="large-text" placeholder="Optional: ein Absatz direkt unter der Ueberschrift im Hero."><?php echo esc_textarea($settings['title_after'] ?? ''); ?></textarea></td></tr>
            <tr><th>Kleine Beschreibung (unter Titel)</th><td><input type="text" name="s_subtitle" value="<?php echo esc_attr($settings['subtitle'] ?? ''); ?>" class="large-text"></td></tr>
            <tr><th>Primaerfarbe</th><td><input type="color" name="s_color1" value="<?php echo esc_attr($settings['color1'] ?? '#3d5a80'); ?>"></td></tr>
            <tr><th>Akzentfarbe</th><td><input type="color" name="s_color2" value="<?php echo esc_attr($settings['color2'] ?? '#e8d5b0'); ?>"></td></tr>
        </table>
        <p class="submit"><input type="submit" class="button button-primary" value="Speichern"></p>
    </form>
    <hr>
    <form method="post" onsubmit="return confirm('Quiz wirklich loeschen?')">
        <?php wp_nonce_field('ww_quiz_save', 'ww_nonce'); ?>
        <input type="hidden" name="ww_delete_quiz" value="1">
        <input type="hidden" name="quiz_id" value="<?php echo $qid; ?>">
        <input type="submit" class="button" value="Quiz loeschen">
    </form>

    <?php elseif ($tab === 'questions'): ?>
    <form method="post">
        <?php wp_nonce_field('ww_quiz_save', 'ww_nonce'); ?>
        <input type="hidden" name="ww_save_quiz" value="1">
        <input type="hidden" name="quiz_id" value="<?php echo $qid; ?>">
        <input type="hidden" name="tab" value="questions">
        <?php foreach ($questions as $qi => $question): ?>
        <div style="border:1px solid #e0e0e0;padding:12px;margin:0 0 12px;background:#fafafa">
            <p><strong>Frage <?php echo $qi + 1; ?></strong></p>
            <input type="text" name="questions[<?php echo $qi; ?>][q]" value="<?php echo esc_attr($question['q'] ?? ''); ?>" class="large-text" style="margin-bottom:8px">
            <?php $letters = ['A','B','C','D']; foreach ($letters as $oi => $letter): ?>
            <div style="display:flex;gap:8px;margin-bottom:6px">
                <span style="width:20px"><?php echo $letter; ?></span>
                <input type="text" name="questions[<?php echo $qi; ?>][opts][<?php echo $oi; ?>]" value="<?php echo esc_attr($question['opts'][$oi] ?? ''); ?>" style="flex:1">
                <input type="number" name="questions[<?php echo $qi; ?>][pts][<?php echo $oi; ?>]" value="<?php echo esc_attr($question['pts'][$oi] ?? 0); ?>" min="0" max="3" style="width:70px">
            </div>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <p class="submit"><input type="submit" class="button button-primary" value="Fragen speichern"></p>
    </form>

    <?php elseif ($tab === 'results'): ?>
    <form method="post">
        <?php wp_nonce_field('ww_quiz_save', 'ww_nonce'); ?>
        <input type="hidden" name="ww_save_quiz" value="1">
        <input type="hidden" name="quiz_id" value="<?php echo $qid; ?>">
        <input type="hidden" name="tab" value="results">
        <?php foreach ($results as $ri => $result): ?>
        <div style="border:1px solid #ddd;padding:12px;margin:0 0 12px">
            <p><strong>Ergebnis <?php echo $ri + 1; ?></strong></p>
            <p><label>Mindestpunktzahl <input type="number" name="results[<?php echo $ri; ?>][min]" value="<?php echo esc_attr($result['min'] ?? 0); ?>" min="0" max="30"></label></p>
            <p><label>Name <input type="text" name="results[<?php echo $ri; ?>][name]" value="<?php echo esc_attr($result['name'] ?? ''); ?>" class="regular-text"></label></p>
            <p><label>Tags <input type="text" name="results[<?php echo $ri; ?>][traits]" value="<?php echo esc_attr(is_array($result['traits']) ? implode(', ', $result['traits']) : ($result['traits'] ?? '')); ?>" class="regular-text"></label></p>
            <p><label>Beschreibung<br><textarea name="results[<?php echo $ri; ?>][desc]" rows="2" class="large-text"><?php echo esc_textarea($result['desc'] ?? ''); ?></textarea></label></p>
            <p><label>URL <input type="url" name="results[<?php echo $ri; ?>][url]" value="<?php echo esc_attr($result['url'] ?? ''); ?>" class="large-text"></label></p>
        </div>
        <?php endforeach; ?>
        <p class="submit"><input type="submit" class="button button-primary" value="Ergebnisse speichern"></p>
    </form>

    <?php elseif ($tab === 'preview'): ?>
    <div style="max-width:720px"><?php echo do_shortcode('[woonwoon_quiz id="' . $qid . '"]'); ?></div>
    <?php endif; ?>

    </div></div>
    <?php
}

function ww_admin_settings() {
    ww_admin_header('Popup und globale Einstellungen');
    $popup_id    = intval(get_option('ww_quiz_popup_id', 0));
    $popup_delay = intval(get_option('ww_quiz_popup_delay', 5));
    $quizzes     = get_posts(['post_type' => 'ww_quiz', 'posts_per_page' => -1, 'post_status' => 'publish']);
    ?>
    <form method="post" style="max-width:600px">
        <?php wp_nonce_field('ww_quiz_save', 'ww_nonce'); ?>
        <input type="hidden" name="ww_save_global" value="1">
        <table class="form-table">
            <tr>
                <th>Quiz fuer Popup</th>
                <td>
                    <select name="popup_id">
                        <option value="0">- Kein Popup -</option>
                        <?php foreach ($quizzes as $q): ?>
                        <option value="<?php echo $q->ID; ?>" <?php selected($popup_id, $q->ID); ?>><?php echo esc_html($q->post_title); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Verzoegerung</th>
                <td><input type="number" name="popup_delay" value="<?php echo $popup_delay; ?>" min="1" max="60" style="width:70px"> Sekunden</td>
            </tr>
        </table>
        <p class="submit"><input type="submit" class="button button-primary" value="Speichern"></p>
    </form>
    </div>
    <?php
}
