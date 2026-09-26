/* DB Event Manager — Frontend JS */
(function($) {
    'use strict';

    // Testi da wp_localize_script; i valori qui sotto servono solo se una cache separa JS e HTML
    var i18n = $.extend({
        error: 'Errore. Riprova.',
        required: 'Questo campo è obbligatorio.',
        invalid_email: 'Inserisci un indirizzo email valido.',
        replace_yes: 'Sì, sostituisci',
        replace_no: 'No, mantieni la precedente',
        replace_kept: 'Nessuna modifica: la prenotazione precedente resta valida.'
    }, (window.dbem_front && dbem_front.i18n) || {});

    $(document).on('submit', '.dbem-form', function(e) {
        e.preventDefault();
        var $form = $(this);

        // Reset errori
        $form.find('.dbem-error').text('');
        $form.find('[aria-invalid]').removeAttr('aria-invalid');

        // Validazione base
        var valid = true;
        $form.find('[required]').each(function() {
            var $el = $(this);
            if ($el.is(':checkbox') && !$el.is(':checked')) {
                $el.closest('.dbem-field, .dbem-field-checkbox').find('.dbem-error').text(i18n.required);
                $el.attr('aria-invalid', 'true');
                valid = false;
            } else if (!$el.val() || !$el.val().trim()) {
                $el.attr('aria-invalid', 'true');
                $el.closest('.dbem-field').find('.dbem-error').text(i18n.required);
                valid = false;
            }
        });

        var $email = $form.find('[name="dbem_email"]');
        if ($email.length && $email.val()) {
            var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRe.test($email.val())) {
                $email.attr('aria-invalid', 'true');
                $email.closest('.dbem-field').find('.dbem-error').text(i18n.invalid_email);
                valid = false;
            }
        }

        if (!valid) {
            // Focus primo errore
            $form.find('[aria-invalid="true"]').first().focus();
            return;
        }

        sendRegistration($form, false);
    });

    function sendRegistration($form, confirmReplace) {
        var $btn = $form.find('.dbem-submit');
        var $msg = $form.find('.dbem-message');
        var $btnText = $form.find('.dbem-submit-text');
        var $btnLoading = $form.find('.dbem-submit-loading');

        function resetButton() {
            $btn.prop('disabled', false);
            $btnText.show();
            $btnLoading.hide();
        }

        $btn.prop('disabled', true);
        $btnText.hide();
        $btnLoading.show();
        $msg.hide().empty().removeClass('dbem-message-success dbem-message-error dbem-message-confirm');

        var data = $form.serialize();
        if (confirmReplace) data += '&dbem_confirm_replace=1';

        $.ajax({
            url: dbem_front.ajax_url,
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    $msg.addClass('dbem-message-success').text(resp.data.message).show();
                    $form.find('input:not([type="hidden"]), textarea, select').val('');
                    $form.find(':checkbox, :radio').prop('checked', false);
                    // Nascondi form dopo successo
                    $form.find('.dbem-field').slideUp(300);
                    $btn.hide();
                    // Focus messaggio
                    $msg.attr('tabindex', '-1').focus();
                } else if (resp.data && resp.data.code === 'confirm_replace') {
                    resetButton();
                    askReplace($msg, resp.data.message, function() {
                        sendRegistration($form, true);
                    });
                } else {
                    var d = resp && resp.data;
                    $msg.addClass('dbem-message-error').text((d && d.message) || (typeof d === 'string' && d) || i18n.error).show();
                    $msg.attr('tabindex', '-1').focus();
                    resetButton();
                }
            },
            error: function(xhr) {
                // Risposte 403/429 (origine non valida, sessione scaduta, troppe richieste):
                // il messaggio del server va mostrato, non inghiottito
                var d = xhr && xhr.responseJSON && xhr.responseJSON.data;
                if (window.console && console.warn) {
                    console.warn('[DB Event Manager] iscrizione non riuscita: HTTP ' + (xhr ? xhr.status : '?'), d || '');
                }
                $msg.addClass('dbem-message-error').text((d && d.message) || (typeof d === 'string' && d) || i18n.error).show();
                $msg.attr('tabindex', '-1').focus();
                resetButton();
            }
        });
    }

    // Domanda di conferma dentro il riquadro messaggi, con le due risposte possibili
    function askReplace($msg, question, onConfirm) {
        var $yes = $('<button type="button" class="dbem-confirm-yes"></button>').text(i18n.replace_yes);
        var $no = $('<button type="button" class="dbem-confirm-no"></button>').text(i18n.replace_no);

        $yes.on('click', function() {
            onConfirm();
        });
        $no.on('click', function() {
            $msg.empty().removeClass('dbem-message-confirm').addClass('dbem-message-success')
                .text(i18n.replace_kept).attr('tabindex', '-1').focus();
        });

        $msg.empty().addClass('dbem-message-confirm')
            .append($('<p class="dbem-confirm-question"></p>').text(question))
            .append($('<div class="dbem-confirm-actions"></div>').append($yes, $no))
            .show();
        $yes.focus();
    }

})(jQuery);
