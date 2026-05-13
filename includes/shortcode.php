<?php
defined('ABSPATH') || exit;

add_shortcode('woonwoon_quiz', 'ww_quiz_shortcode');
function ww_quiz_shortcode($atts) {
    $atts = shortcode_atts(['id' => 0], $atts);
    $id   = intval($atts['id']);
    if (!$id) return '<p style="color:red">Quiz ID fehlt: <code>[woonwoon_quiz id="1"]</code></p>';

    $post = get_post($id);
    if (!$post || $post->post_type !== 'ww_quiz') return '<p style="color:red">Quiz nicht gefunden.</p>';

    $settings  = get_post_meta($id, '_ww_settings',  true) ?: [];
    $questions = get_post_meta($id, '_ww_questions', true) ?: [];
    $results   = get_post_meta($id, '_ww_results',   true) ?: [];

    if (empty($questions) || empty($results)) return '<p style="color:red">Quiz hat noch keine Fragen oder Ergebnisse.</p>';

    usort($results, fn($a, $b) => intval($b['min']) - intval($a['min']));

    $eyebrow     = esc_html($settings['eyebrow']  ?? '');
    $title       = esc_html($settings['title']    ?? 'Welcher Berliner Bezirk passt zu dir?');
    $title_after = trim((string) ($settings['title_after'] ?? ''));
    $subtitle    = esc_html($settings['subtitle'] ?? count($questions) . ' Fragen - sofortiges Ergebnis');
    $color1    = sanitize_hex_color($settings['color1'] ?? '#3d5a80');
    $color2    = sanitize_hex_color($settings['color2'] ?? '#e8d5b0');
    $uid       = 'wwq' . $id;

    $q_json = wp_json_encode($questions);
    $r_json = wp_json_encode($results);

    $vars_style = sprintf(
        '--ww-color1:%s;--ww-color2:%s;',
        esc_attr($color1),
        esc_attr($color2)
    );

    ob_start();
    ?>
    <div id="<?php echo esc_attr($uid); ?>" class="ww-quiz" style="<?php echo esc_attr($vars_style); ?>">
        <div class="ww-hero">
            <?php if ($eyebrow !== ''): ?><p class="ww-eyebrow"><?php echo $eyebrow; ?></p><?php endif; ?>
            <h2 class="elementor-heading-title"><?php echo $title; ?></h2>
            <?php if ($title_after !== ''): ?><p class="ww-after-title"><?php echo nl2br(esc_html($title_after)); ?></p><?php endif; ?>
            <p class="ww-subtitle"><?php echo $subtitle; ?></p>
            <div class="ww-pbar"><div class="ww-pfill" id="<?php echo esc_attr($uid); ?>-prog"></div></div>
            <div class="ww-plbl" id="<?php echo esc_attr($uid); ?>-lbl">Frage 1 von <?php echo count($questions); ?></div>
        </div>
        <div id="<?php echo esc_attr($uid); ?>-area"></div>
    </div>
    <script>
    (function(){
        var UID   = '<?php echo esc_js($uid); ?>';
        var Q     = <?php echo $q_json; ?>;
        var R     = <?php echo $r_json; ?>;
        var QUIZ_ID = <?php echo $id; ?>;
        R.sort(function(a,b){ return parseInt(b.min) - parseInt(a.min); });
        var cur = 0, ans = [], score = 0;
        var letters = ['A','B','C','D'];
        function $id(id){ return document.getElementById(id); }
        function area(){ return $id(UID+'-area'); }
        function getTopResults(s) {
            var matched = [];
            for (var i = 0; i < R.length; i++) {
                if (s >= parseInt(R[i].min)) matched.push(R[i]);
            }
            if (!matched.length) matched.push(R[R.length-1]);
            return matched.slice(0, 3);
        }
        function escH(s) {
            return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
        }
        function render() {
            var pct = Math.round((cur / Q.length) * 100);
            $id(UID+'-prog').style.width = pct + '%';
            $id(UID+'-lbl').textContent = 'Frage ' + (cur+1) + ' von ' + Q.length;
            var q = Q[cur];
            var html = '<div class="ww-card"><p class="ww-q">' + escH(q.q) + '</p><div class="ww-opts">';
            for (var i = 0; i < q.opts.length; i++) {
                if (!q.opts[i]) continue;
                html += '<button type="button" class="ww-opt" onclick="' + UID + '_sel(' + i + ')" id="' + UID + 'o' + i + '">';
                html += '<span class="ww-let">' + letters[i] + '</span>' + escH(q.opts[i]) + '</button>';
            }
            html += '</div><div class="ww-nav"><button type="button" class="ww-btn" id="' + UID + '-next" onclick="' + UID + '_next()">';
            html += (cur < Q.length - 1 ? 'Weiter' : 'Ergebnis anzeigen');
            html += '</button></div></div>';
            area().innerHTML = html;
        }
        window[UID+'_sel'] = function(i) {
            var btns = document.querySelectorAll('#'+UID+' .ww-opt');
            for (var b = 0; b < btns.length; b++) btns[b].classList.remove('sel');
            $id(UID+'o'+i).classList.add('sel');
            ans[cur] = i;
            $id(UID+'-next').classList.add('on');
        };
        window[UID+'_next'] = function() {
            if (ans[cur] == null) return;
            score += (parseInt(Q[cur].pts[ans[cur]]) || 0);
            cur++;
            if (cur >= Q.length) { showResult(); } else { render(); }
        };
        function showResult() {
            $id(UID+'-prog').style.width = '100%';
            $id(UID+'-lbl').textContent = 'Dein Ergebnis';
            var top = getTopResults(score);
            var main = top[0];
            var secondary = top.slice(1);
            if (typeof gtag === 'function') {
                gtag('event', 'quiz_completed', {
                    quiz_id: QUIZ_ID,
                    quiz_result: main.name,
                    quiz_score: score
                });
            }
            var traits = '';
            var traitList = Array.isArray(main.traits) ? main.traits : String(main.traits).split(',');
            for (var i = 0; i < traitList.length; i++) {
                if (traitList[i].trim()) traits += '<span class="ww-trait">' + escH(traitList[i].trim()) + '</span>';
            }
            var secHtml = '';
            for (var j = 0; j < secondary.length; j++) {
                var s = secondary[j];
                var sDesc = escH(s.desc || '');
                secHtml += '<div class="ww-sec-card"><p class="ww-sec-name">' + escH(s.name) + '</p><p class="ww-sec-desc">' + sDesc + '</p><a class="ww-cta-sec" href="' + escH(s.url) + '">Bezirk ' + escH(s.name) + ' entdecken</a></div>';
            }
            var alsoBlock = secondary.length > 0 ? '<div class="ww-also"><p class="ww-also-title">Passt auch zu dir</p><div class="ww-secondary-results">' + secHtml + '</div></div>' : '';
            area().innerHTML =
                '<div class="ww-result">' +
                '<div class="ww-main-result">' +
                '<p class="ww-rlbl">Dein Bezirk ist</p>' +
                '<div class="ww-badge">' + escH(main.name) + '</div>' +
                '<div class="ww-traits">' + traits + '</div>' +
                '<p class="ww-desc">' + escH(main.desc) + '</p>' +
                '<a class="ww-cta-main" href="' + escH(main.url) + '">Bezirk ' + escH(main.name) + ' entdecken</a>' +
                '</div>' + alsoBlock +
                '<button type="button" class="ww-restart" onclick="' + UID + '_restart()">Quiz neu starten</button>' +
                '</div>';
        }
        window[UID+'_restart'] = function() {
            cur = 0; ans = []; score = 0;
            $id(UID+'-prog').style.width = '0%';
            render();
        };
        render();
    })();
    </script>
    <?php
    return ob_get_clean();
}
