<?php
defined('ABSPATH') || exit;

// Popup is enabled site-wide via a global option
// Usage: set option 'ww_quiz_popup_id' to a quiz post ID to enable

add_action('wp_footer', 'ww_quiz_popup_output');
function ww_quiz_popup_output() {
    $popup_id = intval(get_option('ww_quiz_popup_id', 0));
    if (!$popup_id) return;

    $post = get_post($popup_id);
    if (!$post || $post->post_type !== 'ww_quiz') return;

    $delay = intval(get_option('ww_quiz_popup_delay', 5)); // seconds before popup can trigger
    ?>
    <style>
    #ww-popup-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:99998;align-items:center;justify-content:center;padding:1rem}
    #ww-popup-overlay.open{display:flex}
    #ww-popup-box{background:#fff;border-radius:16px;max-width:740px;width:100%;max-height:90vh;overflow-y:auto;position:relative;padding:0}
    #ww-popup-close{position:absolute;top:12px;right:14px;background:rgba(255,255,255,0.15);border:none;color:#e8d5b0;font-size:20px;cursor:pointer;z-index:2;border-radius:50%;width:32px;height:32px;display:flex;align-items:center;justify-content:center;line-height:1}
    #ww-popup-close:hover{background:rgba(255,255,255,0.25)}
    #ww-popup-box .ww-hero{border-radius:16px 16px 0 0}
    @media(max-width:500px){
        #ww-popup-overlay{padding:0.5rem}
        #ww-popup-box{max-height:95vh}
    }
    </style>

    <div id="ww-popup-overlay" role="dialog" aria-modal="true">
        <div id="ww-popup-box">
            <button id="ww-popup-close" onclick="wwClosePopup()" aria-label="Schliessen">&times;</button>
            <?php echo do_shortcode('[woonwoon_quiz id="' . $popup_id . '"]'); ?>
        </div>
    </div>

    <script>
    (function(){
        var delay    = <?php echo $delay * 1000; ?>;
        var shown    = false;
        var ready    = false;
        var lastY    = window.scrollY;
        var isMobile = window.innerWidth < 768;

        // Only activate after delay
        setTimeout(function(){ ready = true; }, delay);

        function showPopup() {
            if (shown) return;
            // Don't show if user has dismissed in this session
            if (sessionStorage.getItem('ww_popup_dismissed')) return;
            shown = true;
            document.getElementById('ww-popup-overlay').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        window.wwClosePopup = function() {
            document.getElementById('ww-popup-overlay').classList.remove('open');
            document.body.style.overflow = '';
            sessionStorage.setItem('ww_popup_dismissed', '1');
        };

        // Close on overlay click
        document.getElementById('ww-popup-overlay').addEventListener('click', function(e){
            if (e.target === this) wwClosePopup();
        });

        // MOBILE: Scroll-up intent (fast scroll upward)
        if (isMobile) {
            var scrollHistory = [];
            window.addEventListener('scroll', function(){
                if (!ready || shown) return;
                var currentY = window.scrollY;
                scrollHistory.push({ y: currentY, t: Date.now() });
                if (scrollHistory.length > 5) scrollHistory.shift();

                if (scrollHistory.length >= 3) {
                    var oldest = scrollHistory[0];
                    var newest = scrollHistory[scrollHistory.length - 1];
                    var dy = oldest.y - newest.y; // positive = scrolled up
                    var dt = newest.t - oldest.t;
                    var speed = dy / dt; // px/ms

                    // Trigger if: scrolled up fast (>0.8px/ms), moved at least 60px, and not near top
                    if (speed > 0.8 && dy > 60 && currentY > 200) {
                        showPopup();
                    }
                }
                lastY = currentY;
            }, { passive: true });
        }

        // DESKTOP: Mouse leaves window at top
        if (!isMobile) {
            document.addEventListener('mouseleave', function(e){
                if (!ready || shown) return;
                if (e.clientY <= 0) showPopup();
            });
        }

        // ESC key to close
        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape') wwClosePopup();
        });
    })();
    </script>
    <?php
}
