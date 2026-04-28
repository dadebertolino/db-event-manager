<?php
if (!defined('ABSPATH')) exit;

$pin_required = (bool) get_option('dbem_checkin_pin', '');
$site_name = get_bloginfo('name');

// Eventi pubblicati
$events = get_posts(array(
    'post_type'      => 'dbem_event',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'meta_key'       => '_dbem_date_start',
    'orderby'        => 'meta_value',
    'order'          => 'DESC',
));
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(sprintf(__('Partecipanti — %s', 'db-event-manager'), $site_name)); ?></title>
    <style>
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f5f5f5;color:#333;min-height:100vh}
        .pp-header{background:#2271b1;color:#fff;padding:16px 20px;text-align:center;position:sticky;top:0;z-index:100}
        .pp-header h1{font-size:20px;margin:0}
        .pp-header small{opacity:.8;font-size:13px}
        .pp-body{padding:16px;max-width:800px;margin:0 auto;width:100%}

        /* PIN */
        .pp-pin{text-align:center;padding-top:40px}
        .pp-pin h2{font-size:22px;margin-bottom:16px}
        .pp-pin input{font-size:32px;text-align:center;letter-spacing:12px;width:200px;padding:12px;border:2px solid #ccc;border-radius:12px;-webkit-text-security:disc}
        .pp-pin input:focus{border-color:#2271b1;outline:none}
        .pp-pin-btn{display:block;width:200px;margin:16px auto 0;padding:14px;background:#2271b1;color:#fff;border:none;border-radius:10px;font-size:18px;font-weight:600;cursor:pointer}
        .pp-pin-error{color:#d63638;margin-top:12px;font-weight:600;display:none}

        /* Selettore evento */
        .pp-select{margin-bottom:20px}
        .pp-select select{width:100%;padding:12px;font-size:16px;border:1px solid #ccc;border-radius:8px;background:#fff}
        .pp-select select:focus{border-color:#2271b1;outline:none;box-shadow:0 0 0 2px rgba(34,113,177,.2)}

        /* Contatore */
        .pp-counter{text-align:center;padding:16px;background:#fff;border-radius:12px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.1)}
        .pp-counter-numbers{font-size:36px;font-weight:800;color:#2271b1}
        .pp-counter-label{font-size:14px;color:#666;margin-top:4px}

        /* Filtri */
        .pp-filters{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px}
        .pp-filter{padding:8px 14px;border:1px solid #ddd;border-radius:20px;background:#fff;font-size:13px;cursor:pointer;font-weight:500}
        .pp-filter:hover{border-color:#2271b1;color:#2271b1}
        .pp-filter.active{background:#2271b1;color:#fff;border-color:#2271b1}

        /* Tabella */
        .pp-table{width:100%;border-collapse:collapse;background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.1)}
        .pp-table th{background:#f8f9fa;padding:10px 12px;text-align:left;font-size:13px;color:#666;font-weight:600;border-bottom:2px solid #e9ecef}
        .pp-table td{padding:10px 12px;border-bottom:1px solid #f0f0f0;font-size:14px;vertical-align:middle}
        .pp-table tr:last-child td{border-bottom:none}

        /* Status badge */
        .pp-badge{display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600}
        .pp-badge-pending{background:#fff3cd;color:#856404}
        .pp-badge-confirmed{background:#d1ecf1;color:#0c5460}
        .pp-badge-checked_in{background:#d4edda;color:#155724}
        .pp-badge-cancelled{background:#f8d7da;color:#721c24}
        .pp-badge-rejected{background:#e2e3e5;color:#383d41}

        /* Azioni — sempre visibili, dimensioni fisse */
        .pp-actions{display:flex;gap:6px;flex-wrap:nowrap}
        .pp-action{width:40px;height:40px;border:none;border-radius:8px;cursor:pointer;font-size:18px;background:#f0f0f0;display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;transition:background .15s}
        .pp-action:hover{background:#ddd}
        .pp-action:active{background:#ccc}
        .pp-action:disabled{opacity:.4;cursor:default}

        /* Toolbar (export) */
        .pp-toolbar{display:flex;gap:8px;justify-content:flex-end;margin-bottom:12px}
        .pp-toolbar-btn{padding:10px 18px;border:1px solid #ddd;border-radius:8px;background:#fff;font-size:14px;font-weight:500;cursor:pointer;display:flex;align-items:center;gap:6px}
        .pp-toolbar-btn:hover{border-color:#2271b1;color:#2271b1}

        /* Feedback */
        .pp-feedback{position:fixed;top:70px;left:50%;transform:translateX(-50%);padding:12px 24px;border-radius:10px;font-weight:600;z-index:200;display:none;animation:pp-slide .3s ease}
        .pp-fb-success{background:#d4edda;color:#155724;border:1px solid #c3e6cb}
        .pp-fb-error{background:#f8d7da;color:#721c24;border:1px solid #f5c6cb}
        @keyframes pp-slide{from{opacity:0;transform:translateX(-50%) translateY(-10px)}to{opacity:1;transform:translateX(-50%) translateY(0)}}

        /* Loading */
        .pp-loading{text-align:center;padding:40px;color:#999}

        /* Empty */
        .pp-empty{text-align:center;padding:40px;color:#999;font-size:15px}

        /* Responsive */
        @media(max-width:600px){
            .pp-table th:nth-child(4),.pp-table td:nth-child(4){display:none}
            .pp-counter-numbers{font-size:28px}
        }
        @media(prefers-color-scheme:dark){
            body{background:#1a1a1a;color:#eee}
            .pp-table{background:#2a2a2a}.pp-table th{background:#333;color:#ccc;border-color:#444}.pp-table td{border-color:#333}
            .pp-select select,.pp-counter,.pp-filter{background:#2a2a2a;border-color:#444;color:#eee}
        }
    </style>
</head>
<body>

<div class="pp-header">
    <h1>👥 <?php esc_html_e('Partecipanti', 'db-event-manager'); ?></h1>
    <small><?php echo esc_html($site_name); ?></small>
</div>

<div class="pp-body">

    <!-- PIN Screen -->
    <?php if ($pin_required): ?>
    <div id="pp-pin-screen" class="pp-pin">
        <h2>🔒 <?php esc_html_e('Inserisci il PIN', 'db-event-manager'); ?></h2>
        <input type="tel" id="pp-pin-input" maxlength="10" autocomplete="off" autofocus
               aria-label="<?php esc_attr_e('PIN di accesso', 'db-event-manager'); ?>">
        <button type="button" class="pp-pin-btn" id="pp-pin-btn"><?php esc_html_e('Accedi', 'db-event-manager'); ?></button>
        <p class="pp-pin-error" id="pp-pin-error"></p>
    </div>
    <?php endif; ?>

    <!-- Main -->
    <div id="pp-main" style="<?php echo $pin_required ? 'display:none;' : ''; ?>">

        <!-- Selettore evento -->
        <div class="pp-select">
            <select id="pp-event-select" aria-label="<?php esc_attr_e('Seleziona evento', 'db-event-manager'); ?>">
                <option value=""><?php esc_html_e('— Seleziona un evento —', 'db-event-manager'); ?></option>
                <?php foreach ($events as $ev):
                    $ev_name = get_post_meta($ev->ID, '_dbem_event_name', true) ?: $ev->post_title;
                    $ev_start = get_post_meta($ev->ID, '_dbem_date_start', true);
                    $ev_date = $ev_start ? date('d/m/Y', strtotime($ev_start)) : '';
                ?>
                <option value="<?php echo esc_attr($ev->ID); ?>">
                    <?php echo esc_html($ev_name . ($ev_date ? ' — ' . $ev_date : '')); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Contatore -->
        <div class="pp-counter" id="pp-counter" style="display:none">
            <div class="pp-counter-numbers" id="pp-counter-text">0</div>
            <div class="pp-counter-label" id="pp-counter-label"><?php esc_html_e('iscritti', 'db-event-manager'); ?></div>
        </div>

        <!-- Filtri stato -->
        <div class="pp-filters" id="pp-filters" style="display:none">
            <button class="pp-filter active" data-filter="all"><?php esc_html_e('Tutti', 'db-event-manager'); ?></button>
            <button class="pp-filter" data-filter="pending">🕐 <?php esc_html_e('In attesa', 'db-event-manager'); ?></button>
            <button class="pp-filter" data-filter="confirmed">⏳ <?php esc_html_e('Confermati', 'db-event-manager'); ?></button>
            <button class="pp-filter" data-filter="checked_in">✅ <?php esc_html_e('Presenti', 'db-event-manager'); ?></button>
            <button class="pp-filter" data-filter="cancelled">❌ <?php esc_html_e('Annullati', 'db-event-manager'); ?></button>
        </div>

        <!-- Feedback -->
        <div id="pp-feedback" class="pp-feedback" role="alert" aria-live="polite"></div>

        <!-- Toolbar -->
        <div class="pp-toolbar" id="pp-toolbar" style="display:none">
            <button type="button" class="pp-toolbar-btn" id="pp-add-btn">➕ <?php esc_html_e('Aggiungi', 'db-event-manager'); ?></button>
            <button type="button" class="pp-toolbar-btn" id="pp-export-btn">📥 <?php esc_html_e('Export CSV', 'db-event-manager'); ?></button>
        </div>

        <!-- Form iscrizione manuale -->
        <div id="pp-add-form" style="display:none;background:#fff;padding:20px;border-radius:12px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.1)">
            <h3 style="margin:0 0 16px;font-size:16px">➕ <?php esc_html_e('Iscrizione manuale', 'db-event-manager'); ?></h3>
            <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:end">
                <div style="flex:1;min-width:150px">
                    <label for="pp-add-name" style="display:block;font-size:13px;font-weight:600;margin-bottom:4px"><?php esc_html_e('Nome *', 'db-event-manager'); ?></label>
                    <input type="text" id="pp-add-name" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;font-size:15px" required>
                </div>
                <div style="flex:1;min-width:180px">
                    <label for="pp-add-email" style="display:block;font-size:13px;font-weight:600;margin-bottom:4px"><?php esc_html_e('Email *', 'db-event-manager'); ?></label>
                    <input type="email" id="pp-add-email" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;font-size:15px" required>
                </div>
                <div style="flex:0 0 140px">
                    <label for="pp-add-time" style="display:block;font-size:13px;font-weight:600;margin-bottom:4px"><?php esc_html_e('Orario', 'db-event-manager'); ?></label>
                    <input type="text" id="pp-add-time" placeholder="<?php esc_attr_e('Es. 10:30', 'db-event-manager'); ?>" style="width:100%;padding:10px;border:1px solid #ccc;border-radius:6px;font-size:15px">
                </div>
                <div style="flex:0 0 auto;display:flex;gap:6px">
                    <button type="button" id="pp-add-submit" style="padding:10px 20px;background:#1d6e3f;color:#fff;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer">✅ <?php esc_html_e('Iscrivere', 'db-event-manager'); ?></button>
                    <button type="button" id="pp-add-cancel" style="padding:10px 16px;background:#f0f0f0;border:none;border-radius:8px;font-size:15px;cursor:pointer">✕</button>
                </div>
            </div>
        </div>

        <!-- Modal modifica orario -->
        <div id="pp-time-modal" style="display:none;position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.5);z-index:300;justify-content:center;align-items:center">
            <div style="background:#fff;padding:24px;border-radius:12px;width:90%;max-width:400px;text-align:center">
                <h3 style="margin:0 0 8px;font-size:18px">🕐 <?php esc_html_e('Modifica orario', 'db-event-manager'); ?></h3>
                <p id="pp-time-modal-name" style="color:#666;margin-bottom:16px"></p>
                <input type="text" id="pp-time-modal-input" placeholder="<?php esc_attr_e('Es. 10:30, 14:00-14:30', 'db-event-manager'); ?>" style="width:100%;padding:12px;border:1px solid #ccc;border-radius:8px;font-size:16px;text-align:center;margin-bottom:16px">
                <input type="hidden" id="pp-time-modal-id">
                <div style="display:flex;gap:10px;justify-content:center">
                    <button type="button" id="pp-time-save" style="padding:12px 24px;background:#2271b1;color:#fff;border:none;border-radius:8px;font-size:16px;font-weight:600;cursor:pointer">💾 <?php esc_html_e('Salva', 'db-event-manager'); ?></button>
                    <button type="button" id="pp-time-cancel" style="padding:12px 24px;background:#f0f0f0;border:none;border-radius:8px;font-size:16px;cursor:pointer"><?php esc_html_e('Annulla', 'db-event-manager'); ?></button>
                </div>
            </div>
        </div>

        <!-- Tabella -->
        <div id="pp-table-wrap"></div>

    </div>
</div>

<script>
(function() {
    var ajaxUrl = '<?php echo esc_url(admin_url('admin-ajax.php')); ?>';
    var pin = '';
    var pinRequired = <?php echo $pin_required ? 'true' : 'false'; ?>;
    var currentEvent = 0;
    var allData = [];
    var currentFilter = 'all';

    var statusLabels = {
        pending: {icon: '🕐', label: '<?php echo esc_js(__('In attesa', 'db-event-manager')); ?>', cls: 'pending'},
        confirmed: {icon: '⏳', label: '<?php echo esc_js(__('Confermato', 'db-event-manager')); ?>', cls: 'confirmed'},
        checked_in: {icon: '✅', label: '<?php echo esc_js(__('Presente', 'db-event-manager')); ?>', cls: 'checked_in'},
        cancelled: {icon: '❌', label: '<?php echo esc_js(__('Annullato', 'db-event-manager')); ?>', cls: 'cancelled'},
        rejected: {icon: '🚫', label: '<?php echo esc_js(__('Rifiutato', 'db-event-manager')); ?>', cls: 'rejected'}
    };

    /* === PIN === */
    if (pinRequired) {
        var pinInput = document.getElementById('pp-pin-input');
        var pinBtn = document.getElementById('pp-pin-btn');
        var pinError = document.getElementById('pp-pin-error');

        function tryPin() {
            pin = pinInput.value.trim();
            if (!pin) return;
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
                    document.getElementById('pp-pin-screen').style.display = 'none';
                    document.getElementById('pp-main').style.display = 'block';
                }
            });
        }
        pinBtn.addEventListener('click', tryPin);
        pinInput.addEventListener('keydown', function(e) { if (e.key === 'Enter') tryPin(); });
    }

    /* === Evento === */
    document.getElementById('pp-event-select').addEventListener('change', function() {
        currentEvent = parseInt(this.value) || 0;
        if (currentEvent) {
            loadParticipants();
        } else {
            document.getElementById('pp-counter').style.display = 'none';
            document.getElementById('pp-filters').style.display = 'none';
            document.getElementById('pp-toolbar').style.display = 'none';
            document.getElementById('pp-table-wrap').innerHTML = '';
        }
    });

    /* === Carica partecipanti === */
    function loadParticipants() {
        document.getElementById('pp-table-wrap').innerHTML = '<div class="pp-loading">⏳ <?php echo esc_js(__('Caricamento...', 'db-event-manager')); ?></div>';

        var body = 'action=dbem_public_participants&event_id=' + currentEvent;
        if (pin) body += '&pin=' + encodeURIComponent(pin);

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body
        })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            if (!resp.success) {
                document.getElementById('pp-table-wrap').innerHTML = '<div class="pp-empty">❌ ' + escHtml(resp.data.message || 'Errore') + '</div>';
                return;
            }
            allData = resp.data.registrations;
            updateCounter(resp.data.stats);
            document.getElementById('pp-counter').style.display = 'block';
            document.getElementById('pp-filters').style.display = 'flex';
            document.getElementById('pp-toolbar').style.display = 'flex';
            renderTable();
        })
        .catch(function() {
            document.getElementById('pp-table-wrap').innerHTML = '<div class="pp-empty">❌ Errore di rete</div>';
        });
    }

    function updateCounter(stats) {
        var total = stats.total || 0;
        var checked = stats.checked_in || 0;
        var max = stats.max || 0;

        var text = checked + ' / ' + total;
        if (max > 0) text += ' (max ' + max + ')';
        document.getElementById('pp-counter-text').textContent = text;
        document.getElementById('pp-counter-label').textContent =
            '<?php echo esc_js(__('presenti / iscritti', 'db-event-manager')); ?>';
    }

    /* === Filtri === */
    document.querySelectorAll('.pp-filter').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.pp-filter').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            currentFilter = this.dataset.filter;
            renderTable();
        });
    });

    /* === Render tabella === */
    function renderTable() {
        var filtered = currentFilter === 'all' ? allData : allData.filter(function(r) { return r.status === currentFilter; });

        if (filtered.length === 0) {
            document.getElementById('pp-table-wrap').innerHTML = '<div class="pp-empty"><?php echo esc_js(__('Nessun partecipante', 'db-event-manager')); ?></div>';
            return;
        }

        var html = '<table class="pp-table"><thead><tr>'
            + '<th><?php echo esc_js(__('Stato', 'db-event-manager')); ?></th>'
            + '<th><?php echo esc_js(__('Nome', 'db-event-manager')); ?></th>'
            + '<th><?php echo esc_js(__('Email', 'db-event-manager')); ?></th>'
            + '<th><?php echo esc_js(__('Orario', 'db-event-manager')); ?></th>'
            + '<th><?php echo esc_js(__('Azioni', 'db-event-manager')); ?></th>'
            + '</tr></thead><tbody>';

        filtered.forEach(function(r) {
            var s = statusLabels[r.status] || statusLabels.confirmed;
            html += '<tr data-id="' + r.id + '">'
                + '<td><span class="pp-badge pp-badge-' + s.cls + '">' + s.icon + ' ' + escHtml(s.label) + '</span></td>'
                + '<td><strong>' + escHtml(r.name) + '</strong></td>'
                + '<td>' + escHtml(r.email) + '</td>'
                + '<td>' + escHtml(r.assigned_time || '—') + '</td>'
                + '<td class="pp-actions">' + getActions(r) + '</td>'
                + '</tr>';
        });

        html += '</tbody></table>';
        document.getElementById('pp-table-wrap').innerHTML = html;

        // Bind azioni
        document.querySelectorAll('.pp-action').forEach(function(btn) {
            btn.addEventListener('click', function() { doAction(this.dataset.action, this.dataset.id, this); });
        });
    }

    function getActions(r) {
        var btns = '';
        if (r.status === 'pending') {
            btns += '<button class="pp-action" data-action="confirm" data-id="' + r.id + '" title="<?php echo esc_js(__('Approva', 'db-event-manager')); ?>">✅</button>';
            btns += '<button class="pp-action" data-action="reject" data-id="' + r.id + '" title="<?php echo esc_js(__('Rifiuta', 'db-event-manager')); ?>">🚫</button>';
        } else if (r.status === 'confirmed') {
            btns += '<button class="pp-action" data-action="checkin" data-id="' + r.id + '" title="<?php echo esc_js(__('Segna presente', 'db-event-manager')); ?>">✅</button>';
            btns += '<button class="pp-action" data-action="cancel" data-id="' + r.id + '" title="<?php echo esc_js(__('Annulla', 'db-event-manager')); ?>">❌</button>';
        } else if (r.status === 'cancelled' || r.status === 'rejected') {
            btns += '<button class="pp-action" data-action="confirm" data-id="' + r.id + '" title="<?php echo esc_js(__('Riconferma', 'db-event-manager')); ?>">🔄</button>';
        }
        btns += '<button class="pp-action" data-action="resend" data-id="' + r.id + '" title="<?php echo esc_js(__('Reinvia email', 'db-event-manager')); ?>">📧</button>';
        btns += '<button class="pp-action" data-action="edit_time" data-id="' + r.id + '" data-name="' + escHtml(r.name) + '" data-time="' + escHtml(r.assigned_time || '') + '" title="<?php echo esc_js(__('Modifica orario', 'db-event-manager')); ?>">🕐</button>';
        return btns;
    }

    /* === Azioni === */
    function doAction(action, regId, btn) {
        // Modifica orario: apri modal
        if (action === 'edit_time') {
            var modal = document.getElementById('pp-time-modal');
            document.getElementById('pp-time-modal-id').value = regId;
            document.getElementById('pp-time-modal-name').textContent = btn ? btn.dataset.name : '';
            document.getElementById('pp-time-modal-input').value = btn ? btn.dataset.time : '';
            modal.style.display = 'flex';
            document.getElementById('pp-time-modal-input').focus();
            return;
        }

        var body = 'action=dbem_public_participant_action&participant_action=' + action
            + '&registration_id=' + regId + '&event_id=' + currentEvent;
        if (pin) body += '&pin=' + encodeURIComponent(pin);

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body
        })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            if (resp.success) {
                showFeedback('success', resp.data.message);
                loadParticipants();
            } else {
                showFeedback('error', resp.data.message || resp.data || 'Errore');
            }
        })
        .catch(function() {
            showFeedback('error', 'Errore di rete');
        });
    }

    /* === Feedback === */
    function showFeedback(type, msg) {
        var fb = document.getElementById('pp-feedback');
        fb.className = 'pp-feedback pp-fb-' + type;
        fb.textContent = msg;
        fb.style.display = 'block';
        setTimeout(function() { fb.style.display = 'none'; }, 3000);
    }

    function escHtml(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    /* === Export CSV === */
    document.getElementById('pp-export-btn').addEventListener('click', function() {
        if (!allData.length) return;

        var statusMap = {
            pending: '<?php echo esc_js(__('In attesa', 'db-event-manager')); ?>',
            confirmed: '<?php echo esc_js(__('Confermato', 'db-event-manager')); ?>',
            checked_in: '<?php echo esc_js(__('Presente', 'db-event-manager')); ?>',
            cancelled: '<?php echo esc_js(__('Annullato', 'db-event-manager')); ?>',
            rejected: '<?php echo esc_js(__('Rifiutato', 'db-event-manager')); ?>'
        };

        var headers = ['Nome', 'Email', 'Stato', 'Orario assegnato', 'Data iscrizione', 'Check-in'];
        var rows = [headers.join(';')];

        // Usa dati filtrati o tutti
        var data = currentFilter === 'all' ? allData : allData.filter(function(r) { return r.status === currentFilter; });

        data.forEach(function(r) {
            var row = [
                csvEsc(r.name),
                csvEsc(r.email),
                csvEsc(statusMap[r.status] || r.status),
                csvEsc(r.assigned_time || ''),
                csvEsc(r.registered_at || ''),
                csvEsc(r.checked_in_at || '')
            ];
            rows.push(row.join(';'));
        });

        var bom = '\uFEFF';
        var csv = bom + rows.join('\n');
        var blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
        var url = URL.createObjectURL(blob);
        var a = document.createElement('a');
        a.href = url;

        var eventName = document.getElementById('pp-event-select');
        var fileName = 'partecipanti';
        if (eventName && eventName.selectedIndex > 0) {
            fileName = eventName.options[eventName.selectedIndex].text.replace(/[^a-zA-Z0-9àèìòùé\s-]/g, '').trim().replace(/\s+/g, '_');
        }
        a.download = fileName + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        URL.revokeObjectURL(url);

        showFeedback('success', '<?php echo esc_js(__('CSV scaricato', 'db-event-manager')); ?>');
    });

    function csvEsc(s) {
        s = (s || '').toString();
        if (s.indexOf(';') > -1 || s.indexOf('"') > -1 || s.indexOf('\n') > -1) {
            return '"' + s.replace(/"/g, '""') + '"';
        }
        return s;
    }

    /* === Form iscrizione manuale === */
    var addForm = document.getElementById('pp-add-form');
    document.getElementById('pp-add-btn').addEventListener('click', function() {
        addForm.style.display = addForm.style.display === 'none' ? 'block' : 'none';
        if (addForm.style.display === 'block') document.getElementById('pp-add-name').focus();
    });
    document.getElementById('pp-add-cancel').addEventListener('click', function() {
        addForm.style.display = 'none';
    });
    document.getElementById('pp-add-submit').addEventListener('click', function() {
        var name = document.getElementById('pp-add-name').value.trim();
        var email = document.getElementById('pp-add-email').value.trim();
        var time = document.getElementById('pp-add-time').value.trim();

        if (!name || !email) {
            showFeedback('error', '<?php echo esc_js(__('Nome e email sono obbligatori', 'db-event-manager')); ?>');
            return;
        }

        var body = 'action=dbem_public_add_participant&event_id=' + currentEvent
            + '&name=' + encodeURIComponent(name)
            + '&email=' + encodeURIComponent(email)
            + '&assigned_time=' + encodeURIComponent(time);
        if (pin) body += '&pin=' + encodeURIComponent(pin);

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body
        })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            if (resp.success) {
                showFeedback('success', resp.data.message);
                document.getElementById('pp-add-name').value = '';
                document.getElementById('pp-add-email').value = '';
                document.getElementById('pp-add-time').value = '';
                addForm.style.display = 'none';
                loadParticipants();
            } else {
                showFeedback('error', resp.data.message || resp.data || 'Errore');
            }
        })
        .catch(function() { showFeedback('error', 'Errore di rete'); });
    });

    /* === Modal modifica orario === */
    var timeModal = document.getElementById('pp-time-modal');
    document.getElementById('pp-time-cancel').addEventListener('click', function() {
        timeModal.style.display = 'none';
    });
    timeModal.addEventListener('click', function(e) {
        if (e.target === timeModal) timeModal.style.display = 'none';
    });
    document.getElementById('pp-time-modal-input').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') document.getElementById('pp-time-save').click();
        if (e.key === 'Escape') timeModal.style.display = 'none';
    });
    document.getElementById('pp-time-save').addEventListener('click', function() {
        var regId = document.getElementById('pp-time-modal-id').value;
        var newTime = document.getElementById('pp-time-modal-input').value.trim();

        var body = 'action=dbem_public_update_time&registration_id=' + regId
            + '&assigned_time=' + encodeURIComponent(newTime)
            + '&event_id=' + currentEvent;
        if (pin) body += '&pin=' + encodeURIComponent(pin);

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: body
        })
        .then(function(r) { return r.json(); })
        .then(function(resp) {
            timeModal.style.display = 'none';
            if (resp.success) {
                showFeedback('success', resp.data.message);
                loadParticipants();
            } else {
                showFeedback('error', resp.data.message || 'Errore');
            }
        })
        .catch(function() { showFeedback('error', 'Errore di rete'); });
    });
})();
</script>

</body>
</html>
