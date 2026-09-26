/* DB Event Manager — Check-in JS */
(function($) {
    'use strict';

    var scanner = null;
    var currentEventId = 0;

    // Testi da wp_localize_script; i valori qui sotto servono solo se una cache separa JS e HTML
    var i18n = $.extend({
        loading: 'Caricamento...',
        no_results: 'Nessun risultato',
        error: 'Errore',
        network_error: 'Errore di rete',
        scanner_missing: 'Libreria scanner non caricata',
        camera_denied: 'Impossibile accedere alla fotocamera. Verifica i permessi.',
        no_participants: 'Nessun iscritto',
        checkin: 'Segna presente',
        status_confirmed: 'Confermato',
        status_checked_in: 'Presente',
        status_pending: 'In attesa',
        status_rejected: 'Rifiutato',
        status_cancelled: 'Annullato'
    }, (window.dbem_checkin && dbem_checkin.i18n) || {});

    function init() {
        currentEventId = parseInt($('#dbem-event-select').val(), 10) || 0;

        $('#dbem-event-select').on('change', function() {
            currentEventId = parseInt($(this).val(), 10) || 0;
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

        // Check-in dalla lista
        $('#dbem-checkin-tbody').on('click', '.dbem-checkin-row-btn', function() {
            processToken($(this).data('token'));
        });
    }

    /* === Scanner QR === */
    function startScanner() {
        $('#dbem-qr-scanner').show();
        $('#dbem-scan-btn').prop('disabled', true);

        if (typeof Html5Qrcode === 'undefined') {
            showFeedback('error', '❌', '', i18n.scanner_missing);
            stopScanner();
            return;
        }

        scanner = new Html5Qrcode('dbem-qr-reader');
        scanner.start(
            { facingMode: 'environment' },
            { fps: 10, qrbox: { width: 250, height: 250 } },
            function(decodedText) {
                var token = extractToken(decodedText);
                if (token) {
                    stopScanner();
                    processToken(token);
                }
            },
            function() { /* ignore scan errors */ }
        ).catch(function(err) {
            console.error('Scanner error:', err);
            showFeedback('error', '❌', '', i18n.camera_denied);
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
        showFeedback('loading', '⏳', '', i18n.loading);

        $.post(dbem_checkin.ajax_url, {
            action: 'dbem_checkin',
            nonce: dbem_checkin.nonce,
            token: token
        }, function(resp) {
            if (resp.success) {
                var d = resp.data;
                var fbClass = 'success';
                if (d.status === 'already') fbClass = 'warning';
                if (d.status === 'cancelled' || d.status === 'not_admitted') fbClass = 'error';
                showFeedback(fbClass, d.icon, d.name || '', d.message);
                loadParticipants();
            } else {
                var msg = resp.data;
                if (typeof msg === 'object') msg = msg.message || i18n.error;
                showFeedback('error', '❌', '', msg || i18n.error);
            }
        }).fail(function() {
            showFeedback('error', '❌', '', i18n.network_error);
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
            var $results = $('#dbem-results-list').empty();
            if (resp.success && resp.data.length) {
                resp.data.forEach(function(r) {
                    var $item = $('<div class="dbem-result-item" tabindex="0"></div>')
                        .append($('<span class="dbem-result-status"></span>').text(statusIcon(r.status)))
                        .append($('<div class="dbem-result-info"></div>')
                            .append($('<span class="dbem-result-name"></span>').text(r.name))
                            .append($('<span class="dbem-result-email"></span>').text(r.email)));
                    if (r.status === 'confirmed') {
                        $item.on('click keydown', function(e) {
                            if (e.type === 'keydown' && e.key !== 'Enter') return;
                            processToken(r.token);
                        }).css('cursor', 'pointer');
                    }
                    $results.append($item);
                });
            } else {
                $results.append($('<p></p>').text(i18n.no_results));
            }
            $('#dbem-search-results').show();
        });
    }

    /* === Lista partecipanti e contatori === */
    function statusIcon(status) {
        return { checked_in: '✅', cancelled: '❌', rejected: '🚫', pending: '⏳' }[status] || '•';
    }

    function loadParticipants() {
        if (!currentEventId) return;
        var eventId = currentEventId;

        $.post(dbem_checkin.ajax_url, {
            action: 'dbem_checkin_list',
            nonce: dbem_checkin.nonce,
            event_id: eventId
        }, function(resp) {
            // L'utente potrebbe aver cambiato evento nel frattempo
            if (eventId !== currentEventId) return;
            var $tbody = $('#dbem-checkin-tbody').empty();
            if (!resp.success) {
                $tbody.append($('<tr><td colspan="5"></td></tr>').find('td').text(resp.data || i18n.error).end());
                return;
            }

            var rows = resp.data.registrations;
            $('#dbem-counter-checkedin').text(resp.data.checked_in);
            $('#dbem-counter-total').text(resp.data.total);

            if (!rows.length) {
                $tbody.append($('<tr><td colspan="5"></td></tr>').find('td').text(i18n.no_participants).end());
                return;
            }

            rows.forEach(function(r) {
                var $action = $('<td></td>');
                if (r.status === 'confirmed') {
                    $('<button type="button" class="button button-small dbem-checkin-row-btn"></button>')
                        .attr('data-token', r.token).text(i18n.checkin).appendTo($action);
                }
                $('<tr></tr>')
                    .append($('<td></td>').append(
                        $('<span class="dbem-status-badge"></span>')
                            .addClass('dbem-status-' + String(r.status).replace('_', '-'))
                            .text(statusIcon(r.status) + ' ' + (i18n['status_' + r.status] || r.status))
                    ))
                    .append($('<td></td>').text(r.name))
                    .append($('<td></td>').text(r.email))
                    .append($('<td></td>').text(r.time || '—'))
                    .append($action)
                    .appendTo($tbody);
            });
        }).fail(function() {
            if (eventId !== currentEventId) return;
            $('#dbem-checkin-tbody').empty().append($('<tr><td colspan="5"></td></tr>').find('td').text(i18n.network_error).end());
        });
    }

    $(document).ready(init);

})(jQuery);
