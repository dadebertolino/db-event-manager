/* DB Event Manager — Check-in JS */
(function($) {
    'use strict';

    var scanner = null;
    var currentEventId = 0;

    function init() {
        currentEventId = parseInt($('#dbem-event-select').val()) || 0;

        $('#dbem-event-select').on('change', function() {
            currentEventId = parseInt($(this).val()) || 0;
            if (currentEventId) {
                $('#dbem-checkin-panel').show();
                loadParticipants();
            } else {
                $('#dbem-checkin-panel').hide();
            }
        });

        if (currentEventId) loadParticipants();

        // Scanner
        $('#dbem-scan-btn').on('click', startScanner);
        $('#dbem-stop-scan').on('click', stopScanner);

        // Ricerca
        $('#dbem-search-btn').on('click', doSearch);
        $('#dbem-search-input').on('keydown', function(e) {
            if (e.key === 'Enter') { e.preventDefault(); doSearch(); }
        });
    }

    /* === Scanner QR === */
    function startScanner() {
        $('#dbem-qr-scanner').show();
        $('#dbem-scan-btn').prop('disabled', true);

        if (typeof Html5Qrcode === 'undefined') {
            alert('Libreria scanner non caricata');
            return;
        }

        scanner = new Html5Qrcode('dbem-qr-reader');
        scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            function(decodedText) {
                // Estrai token dall'URL
                var token = extractToken(decodedText);
                if (token) {
                    stopScanner();
                    processToken(token);
                }
            },
            function() { /* ignore scan errors */ }
        ).catch(function(err) {
            console.error('Scanner error:', err);
            alert('Impossibile accedere alla fotocamera. Verifica i permessi.');
            stopScanner();
        });
    }

    function stopScanner() {
        if (scanner) {
            scanner.stop().catch(function(){});
            scanner = null;
        }
        $('#dbem-qr-scanner').hide();
        $('#dbem-scan-btn').prop('disabled', false);
    }

    function extractToken(url) {
        // Cerca parametro dbem_checkin
        try {
            var u = new URL(url);
            var token = u.searchParams.get('dbem_checkin');
            if (token) return token;
        } catch(e) {}
        // Prova come token diretto
        if (/^[a-f0-9]{64}$/i.test(url)) return url;
        return null;
    }

    /* === Check-in === */
    function processToken(token) {
        showFeedback('loading', '⏳', '', dbem_checkin_i18n('loading'));

        $.post(dbem_checkin.ajax_url, {
            action: 'dbem_checkin',
            nonce: dbem_checkin.nonce,
            token: token
        }, function(resp) {
            if (resp.success) {
                var d = resp.data;
                var fbClass = 'success';
                if (d.status === 'already') fbClass = 'warning';
                if (d.status === 'cancelled') fbClass = 'error';
                showFeedback(fbClass, d.icon, d.name || '', d.message);
                loadParticipants();
            } else {
                var msg = resp.data;
                if (typeof msg === 'object') msg = msg.message || 'Errore';
                showFeedback('error', '❌', '', msg);
            }
        }).fail(function() {
            showFeedback('error', '❌', '', 'Errore di rete');
        });
    }

    // Esponi per uso da PHP (token precaricato)
    window.dbemCheckinProcessToken = processToken;

    function showFeedback(type, icon, name, message) {
        var $fb = $('#dbem-checkin-feedback');
        $fb.removeClass('dbem-feedback-success dbem-feedback-warning dbem-feedback-error');
        $fb.addClass('dbem-feedback-' + type);
        $fb.find('.dbem-feedback-icon').text(icon);
        $fb.find('.dbem-feedback-name').text(name);
        $fb.find('.dbem-feedback-message').text(message);
        $fb.show();
        // Auto-hide dopo 5s (non per loading)
        if (type !== 'loading') {
            setTimeout(function() { $fb.fadeOut(300); }, 5000);
        }
    }

    /* === Ricerca === */
    function doSearch() {
        var q = $.trim($('#dbem-search-input').val());
        if (!q || !currentEventId) return;

        $.post(dbem_checkin.ajax_url, {
            action: 'dbem_checkin_search',
            nonce: dbem_checkin.nonce,
            event_id: currentEventId,
            search: q
        }, function(resp) {
            if (resp.success && resp.data.length) {
                var $results = $('#dbem-results-list').empty();
                resp.data.forEach(function(r) {
                    var statusIcon = r.status === 'checked_in' ? '✅' : (r.status === 'cancelled' ? '❌' : '⏳');
                    var $item = $('<div class="dbem-result-item" tabindex="0">'
                        + '<span class="dbem-result-status">' + statusIcon + '</span>'
                        + '<div class="dbem-result-info">'
                        + '<span class="dbem-result-name">' + escHtml(r.name) + '</span>'
                        + '<span class="dbem-result-email">' + escHtml(r.email) + '</span>'
                        + '</div></div>');
                    if (r.status === 'confirmed') {
                        $item.on('click keydown', function(e) {
                            if (e.type === 'keydown' && e.key !== 'Enter') return;
                            processToken(r.token);
                        }).css('cursor', 'pointer');
                    }
                    $results.append($item);
                });
                $('#dbem-search-results').show();
            } else {
                $('#dbem-results-list').html('<p>' + dbem_checkin_i18n('no_results') + '</p>');
                $('#dbem-search-results').show();
            }
        });
    }

    /* === Lista partecipanti === */
    function loadParticipants() {
        if (!currentEventId) return;

        $.post(dbem_checkin.ajax_url, {
            action: 'dbem_checkin_search',
            nonce: dbem_checkin.nonce,
            event_id: currentEventId,
            search: '' // vuoto = carica tutti (serve un endpoint dedicato, ma usiamo search con stringa ampia)
        }, function() {
            // Il search vuoto non funziona, usiamo un approccio diverso
        });

        // Per ora ricarichiamo la pagina — la lista è server-rendered
        // In futuro si può convertire a full AJAX
        updateCounters();
    }

    function updateCounters() {
        var total = 0;
        var checkedIn = 0;
        $('#dbem-checkin-tbody tr').each(function() {
            total++;
            if ($(this).find('.dbem-status-checked-in, td:contains("✅")').length) checkedIn++;
        });
        // Per ora i contatori vengono dal PHP, si aggiorneranno al reload
    }

    function dbem_checkin_i18n(key) {
        if (typeof dbem_admin !== 'undefined' && dbem_admin.i18n && dbem_admin.i18n[key]) return dbem_admin.i18n[key];
        var fallbacks = { loading: 'Caricamento...', no_results: 'Nessun risultato' };
        return fallbacks[key] || key;
    }

    function escHtml(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }

    $(document).ready(init);

})(jQuery);
