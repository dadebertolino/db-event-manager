<?php
if (!defined('ABSPATH')) exit;

$pin_required = (bool) get_option('dbem_checkin_pin', '');
$preloaded_token = sanitize_text_field($_GET['token'] ?? '');
$site_name = get_bloginfo('name');
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo esc_html(sprintf(__('Check-in — %s', 'db-event-manager'), $site_name)); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #f5f5f5; color: #333;
            min-height: 100vh; min-height: 100dvh;
            display: flex; flex-direction: column;
        }

        /* Header */
        .ci-header {
            background: #2271b1; color: #fff;
            padding: 16px 20px;
            text-align: center;
            position: sticky; top: 0; z-index: 100;
        }
        .ci-header h1 { font-size: 20px; margin: 0; }
        .ci-header small { opacity: 0.8; font-size: 13px; }

        /* Container */
        .ci-body { flex: 1; padding: 16px; max-width: 500px; margin: 0 auto; width: 100%; }

        /* PIN screen */
        .ci-pin-screen { text-align: center; padding-top: 40px; }
        .ci-pin-screen h2 { font-size: 22px; margin-bottom: 16px; }
        .ci-pin-screen input {
            font-size: 32px; text-align: center; letter-spacing: 12px;
            width: 200px; padding: 12px;
            border: 2px solid #ccc; border-radius: 12px;
            -webkit-text-security: disc;
        }
        .ci-pin-screen input:focus { border-color: #2271b1; outline: none; }
        .ci-pin-screen .ci-pin-btn {
            display: block; width: 200px; margin: 16px auto 0;
            padding: 14px; background: #2271b1; color: #fff;
            border: none; border-radius: 10px; font-size: 18px; font-weight: 600;
            cursor: pointer;
        }
        .ci-pin-error { color: #d63638; margin-top: 12px; font-weight: 600; }

        /* Scanner area */
        .ci-scanner { text-align: center; margin-bottom: 16px; }
        .ci-scan-btn {
            display: block; width: 100%; padding: 18px;
            background: #2271b1; color: #fff;
            border: none; border-radius: 12px;
            font-size: 20px; font-weight: 700;
            cursor: pointer; touch-action: manipulation;
            min-height: 60px;
        }
        .ci-scan-btn:active { background: #135e96; }
        .ci-scan-btn.ci-scanning { background: #d63638; }
        #ci-qr-reader { margin: 12px auto; max-width: 100%; border-radius: 12px; overflow: hidden; }
        #ci-qr-reader video { border-radius: 12px; }

        /* Feedback */
        .ci-feedback {
            padding: 24px; border-radius: 16px; text-align: center;
            margin: 16px 0; display: none;
            animation: ci-pop 0.3s ease;
        }
        .ci-feedback-icon { font-size: 72px; display: block; margin-bottom: 8px; }
        .ci-feedback-name { font-size: 28px; font-weight: 800; display: block; }
        .ci-feedback-event { font-size: 16px; display: block; margin-top: 4px; opacity: 0.8; }
        .ci-feedback-msg { font-size: 18px; display: block; margin-top: 8px; }
        .ci-fb-success { background: #d4edda; border: 3px solid #1d6e3f; color: #1d6e3f; }
        .ci-fb-warning { background: #fff3cd; border: 3px solid #856404; color: #856404; }
        .ci-fb-error { background: #f8d7da; border: 3px solid #d63638; color: #d63638; }

        @keyframes ci-pop { from { opacity: 0; transform: scale(0.9); } to { opacity: 1; transform: scale(1); } }

        /* Ricerca manuale */
        .ci-search { margin-top: 16px; }
        .ci-search-row { display: flex; gap: 8px; }
        .ci-search-input {
            flex: 1; padding: 14px; font-size: 16px;
            border: 2px solid #ddd; border-radius: 10px;
        }
        .ci-search-input:focus { border-color: #2271b1; outline: none; }
        .ci-search-btn {
            padding: 14px 20px; background: #666; color: #fff;
            border: none; border-radius: 10px; font-size: 16px; font-weight: 600;
            cursor: pointer; white-space: nowrap;
        }

        /* Risultati ricerca */
        .ci-result {
            display: flex; align-items: center; gap: 12px;
            padding: 14px; margin-top: 8px;
            background: #fff; border: 2px solid #e0e0e0; border-radius: 10px;
            cursor: pointer; transition: background 0.1s;
        }
        .ci-result:active { background: #e8f0fe; }
        .ci-result-icon { font-size: 28px; }
        .ci-result-name { font-weight: 700; font-size: 16px; }
        .ci-result-detail { font-size: 13px; color: #666; }

        /* Separator */
        .ci-or { text-align: center; color: #999; margin: 16px 0; font-size: 14px; }

        @media (prefers-reduced-motion: reduce) {
            .ci-feedback { animation: none; }
        }

        @media (prefers-color-scheme: dark) {
            body { background: #1a1a1a; color: #eee; }
            .ci-search-input { background: #2a2a2a; color: #eee; border-color: #444; }
            .ci-result { background: #2a2a2a; border-color: #444; }
            .ci-result-detail { color: #aaa; }
        }
    </style>
</head>
<body>

<div class="ci-header">
    <h1>📋 <?php esc_html_e('Check-in', 'db-event-manager'); ?></h1>
    <small><?php echo esc_html($site_name); ?></small>
</div>

<div class="ci-body">

    <!-- PIN Screen -->
    <?php if ($pin_required): ?>
    <div id="ci-pin-screen" class="ci-pin-screen">
        <h2>🔒 <?php esc_html_e('Inserisci il PIN', 'db-event-manager'); ?></h2>
        <input type="tel" id="ci-pin-input" maxlength="10" autocomplete="off" autofocus
               aria-label="<?php esc_attr_e('PIN di accesso', 'db-event-manager'); ?>">
        <button type="button" class="ci-pin-btn" id="ci-pin-btn"><?php esc_html_e('Accedi', 'db-event-manager'); ?></button>
        <p class="ci-pin-error" id="ci-pin-error" style="display:none;"></p>
    </div>
    <?php endif; ?>

    <!-- Main check-in (nascosto se serve PIN) -->
    <div id="ci-main" style="<?php echo $pin_required ? 'display:none;' : ''; ?>">

        <!-- Feedback -->
        <div id="ci-feedback" class="ci-feedback" role="alert" aria-live="assertive"></div>

        <!-- Scanner -->
        <div class="ci-scanner">
            <button type="button" class="ci-scan-btn" id="ci-scan-btn">
                📷 <?php esc_html_e('Scansiona QR Code', 'db-event-manager'); ?>
            </button>
            <div id="ci-qr-reader" style="display:none;"></div>
        </div>

        <div class="ci-or">— <?php esc_html_e('oppure', 'db-event-manager'); ?> —</div>

        <!-- Ricerca manuale -->
        <div class="ci-search">
            <div class="ci-search-row">
                <input type="text" id="ci-search-input" class="ci-search-input"
                       placeholder="<?php esc_attr_e('Nome, email o token...', 'db-event-manager'); ?>"
                       aria-label="<?php esc_attr_e('Cerca partecipante', 'db-event-manager'); ?>">
                <button type="button" class="ci-search-btn" id="ci-search-btn"><?php esc_html_e('Cerca', 'db-event-manager'); ?></button>
            </div>
            <div id="ci-search-results"></div>
        </div>

    </div>
</div>

<script src="<?php echo esc_url(DBEM_PLUGIN_URL . 'assets/js/vendor/html5-qrcode.min.js'); ?>"></script>
<script>
(function() {
    var ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
    var pin = '';
    var scanner = null;
    var scanning = false;
    var preloadedToken = '<?php echo esc_js($preloaded_token); ?>';
    var pinRequired = <?php echo $pin_required ? 'true' : 'false'; ?>;

    /* === PIN === */
    if (pinRequired) {
        var pinInput = document.getElementById('ci-pin-input');
        var pinBtn = document.getElementById('ci-pin-btn');
        var pinError = document.getElementById('ci-pin-error');

        function tryPin() {
            pin = pinInput.value.trim();
            if (!pin) return;
            // Verifica PIN con una richiesta di test
            fetch(ajaxUrl, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=dbem_public_checkin&pin=' + encodeURIComponent(pin) + '&token=__pin_test__'
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.data && data.data.status === 'pin_error') {
                    pinError.textContent = data.data.message;
                    pinError.style.display = 'block';
                    pinInput.select();
                } else {
                    // PIN corretto (token non trovato è ok, il PIN ha passato)
                    document.getElementById('ci-pin-screen').style.display = 'none';
                    document.getElementById('ci-main').style.display = 'block';
                    if (preloadedToken) processToken(preloadedToken);
                }
            });
        }

        pinBtn.addEventListener('click', tryPin);
        pinInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') tryPin(); });
    } else {
        if (preloadedToken) {
            setTimeout(function() { processToken(preloadedToken); }, 300);
        }
    }

    /* === Scanner === */
    var scanBtn = document.getElementById('ci-scan-btn');
    scanBtn.addEventListener('click', function() {
        if (scanning) {
            stopScanner();
        } else {
            startScanner();
        }
    });

    function startScanner() {
        if (typeof Html5Qrcode === 'undefined') {
            alert('Scanner non disponibile');
            return;
        }
        var reader = document.getElementById('ci-qr-reader');
        reader.style.display = 'block';
        scanBtn.textContent = '⏹ Chiudi scanner';
        scanBtn.classList.add('ci-scanning');
        scanning = true;

        scanner = new Html5Qrcode('ci-qr-reader');
        scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            function(text) {
                var token = extractToken(text);
                if (token) {
                    stopScanner();
                    processToken(token);
                }
            },
            function() {}
        ).catch(function(err) {
            alert('Impossibile accedere alla fotocamera. Verifica i permessi.');
            stopScanner();
        });
    }

    function stopScanner() {
        if (scanner) { scanner.stop().catch(function(){}); scanner = null; }
        document.getElementById('ci-qr-reader').style.display = 'none';
        scanBtn.textContent = '📷 Scansiona QR Code';
        scanBtn.classList.remove('ci-scanning');
        scanning = false;
    }

    function extractToken(url) {
        try { var u = new URL(url); var t = u.searchParams.get('dbem_checkin'); if (t) return t; } catch(e) {}
        if (/^[a-f0-9]{64}$/i.test(url)) return url;
        return null;
    }

    /* === Check-in === */
    function processToken(token) {
        showFeedback('loading', '⏳', '', '', 'Verifica...');

        var body = 'action=dbem_public_checkin&token=' + encodeURIComponent(token);
        if (pin) body += '&pin=' + encodeURIComponent(pin);

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var d = data.success ? data.data : (data.data || {});
            var cls = 'error';
            if (d.status === 'checked_in') cls = 'success';
            else if (d.status === 'already') cls = 'warning';

            showFeedback(cls, d.icon || '❌', d.name || '', d.event || '', d.message || 'Errore');

            // Dopo 3 secondi, riattiva lo scanner automaticamente
            if (cls === 'success') {
                setTimeout(function() {
                    hideFeedback();
                    startScanner();
                }, 2500);
            }
        })
        .catch(function() {
            showFeedback('error', '❌', '', '', 'Errore di rete');
        });
    }

    function showFeedback(type, icon, name, event, msg) {
        var fb = document.getElementById('ci-feedback');
        fb.className = 'ci-feedback ci-fb-' + type;
        fb.innerHTML = '<span class="ci-feedback-icon">' + icon + '</span>'
            + (name ? '<span class="ci-feedback-name">' + escHtml(name) + '</span>' : '')
            + (event ? '<span class="ci-feedback-event">' + escHtml(event) + '</span>' : '')
            + '<span class="ci-feedback-msg">' + escHtml(msg) + '</span>';
        fb.style.display = 'block';
    }

    function hideFeedback() {
        document.getElementById('ci-feedback').style.display = 'none';
    }

    /* === Ricerca === */
    var searchBtn = document.getElementById('ci-search-btn');
    var searchInput = document.getElementById('ci-search-input');

    searchBtn.addEventListener('click', doSearch);
    searchInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') doSearch(); });

    function doSearch() {
        var q = searchInput.value.trim();
        if (!q) return;

        // Token diretto
        if (/^[a-f0-9]{64}$/i.test(q)) {
            processToken(q);
            return;
        }

        if (q.length < 2) return;

        var resultsDiv = document.getElementById('ci-search-results');
        resultsDiv.innerHTML = '<p style="text-align:center;padding:12px;">⏳ Ricerca...</p>';

        var body = 'action=dbem_public_search&search=' + encodeURIComponent(q);
        if (pin) body += '&pin=' + encodeURIComponent(pin);

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            resultsDiv.innerHTML = '';
            if (!data.success || !data.data.length) {
                resultsDiv.innerHTML = '<p style="text-align:center;padding:12px;color:#999;">Nessun risultato</p>';
                return;
            }
            data.data.forEach(function(r) {
                var statusIcon = r.status === 'checked_in' ? '✅' : (r.status === 'cancelled' ? '❌' : '⏳');
                var statusText = r.status === 'checked_in' ? ' (presente' + (r.time ? ' ' + r.time : '') + ')' : '';
                var div = document.createElement('div');
                div.className = 'ci-result';
                div.setAttribute('role', 'button');
                div.setAttribute('tabindex', '0');
                div.innerHTML = '<span class="ci-result-icon">' + statusIcon + '</span>'
                    + '<div><span class="ci-result-name">' + escHtml(r.name) + '</span>'
                    + '<span class="ci-result-detail">' + escHtml(r.email) + ' — ' + escHtml(r.event) + escHtml(statusText) + '</span></div>';

                if (r.status === 'confirmed') {
                    div.addEventListener('click', function() { processToken(r.token); resultsDiv.innerHTML = ''; });
                    div.addEventListener('keydown', function(e) { if (e.key === 'Enter') { processToken(r.token); resultsDiv.innerHTML = ''; } });
                }
                resultsDiv.appendChild(div);
            });
        })
        .catch(function() {
            resultsDiv.innerHTML = '<p style="text-align:center;padding:12px;color:#d63638;">Errore di rete</p>';
        });
    }

    function escHtml(s) { var d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }
})();
</script>
</body>
</html>
