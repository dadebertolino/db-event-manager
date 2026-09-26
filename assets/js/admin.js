/* DB Event Manager — Admin JS */
(function($) {
    'use strict';

    /* === Custom Fields Builder === */
    function initFieldsBuilder(containerId, jsonInputId) {
        var $container = $(containerId);
        if (!$container.length) return;

        var $list = $container.find('[id$="-list"]');
        var $input = $(jsonInputId);
        var fields = [];

        try { fields = JSON.parse($container.attr('data-fields')) || []; } catch(e) { fields = []; }

        var fieldTypes = [
            { value: 'text', label: 'Testo' },
            { value: 'email', label: 'Email' },
            { value: 'tel', label: 'Telefono' },
            { value: 'number', label: 'Numero' },
            { value: 'date', label: 'Data' },
            { value: 'textarea', label: 'Area di testo' },
            { value: 'select', label: 'Selezione' },
            { value: 'radio', label: 'Scelta singola' },
            { value: 'checkbox', label: 'Scelta multipla' }
        ];

        function renderFields() {
            $list.empty();
            fields.forEach(function(f, i) {
                var hasOptions = ['select', 'radio', 'checkbox'].indexOf(f.type) !== -1;
                var optionsText = (f.options || []).join('\n');

                var html = '<div class="dbem-field-item" data-index="' + i + '">'
                    + '<div class="dbem-field-header"><span class="dbem-drag-handle">☰</span>'
                    + '<strong>' + escHtml(f.label || 'Campo ' + (i+1)) + '</strong></div>'
                    + '<button type="button" class="dbem-field-remove" data-index="' + i + '" title="Rimuovi">✕</button>'
                    + '<div class="dbem-field-row">'
                    + '<label>Tipo</label><select class="dbem-f-type" data-index="' + i + '">';
                fieldTypes.forEach(function(t) {
                    html += '<option value="' + t.value + '"' + (t.value === f.type ? ' selected' : '') + '>' + t.label + '</option>';
                });
                html += '</select>'
                    + '<label>Etichetta</label><input type="text" class="dbem-f-label" data-index="' + i + '" value="' + escAttr(f.label) + '">'
                    + '<label><input type="checkbox" class="dbem-f-required" data-index="' + i + '"' + (f.required ? ' checked' : '') + '> Obbligatorio</label>'
                    + '</div>'
                    + '<div class="dbem-field-row">'
                    + '<label>Placeholder</label><input type="text" class="dbem-f-placeholder" data-index="' + i + '" value="' + escAttr(f.placeholder || '') + '">'
                    + '</div>';
                if (hasOptions) {
                    html += '<div class="dbem-field-options">'
                        + '<label>Opzioni (una per riga)</label>'
                        + '<textarea class="dbem-f-options" data-index="' + i + '" rows="3">' + escHtml(optionsText) + '</textarea>'
                        + '</div>';
                }
                html += '</div>';
                $list.append(html);
            });
            updateJSON();
            initSortable();
        }

        function updateJSON() {
            $input.val(JSON.stringify(fields));
        }

        function initSortable() {
            if (typeof Sortable === 'undefined') return;
            Sortable.create($list[0], {
                handle: '.dbem-drag-handle',
                animation: 150,
                onEnd: function() {
                    var newOrder = [];
                    $list.find('.dbem-field-item').each(function() {
                        newOrder.push(fields[$(this).data('index')]);
                    });
                    fields = newOrder;
                    renderFields();
                }
            });
        }

        // Events
        $list.on('change input', '.dbem-f-type, .dbem-f-label, .dbem-f-required, .dbem-f-placeholder, .dbem-f-options', function() {
            var i = $(this).data('index');
            if ($(this).hasClass('dbem-f-type')) fields[i].type = $(this).val();
            if ($(this).hasClass('dbem-f-label')) fields[i].label = $(this).val();
            if ($(this).hasClass('dbem-f-required')) fields[i].required = $(this).is(':checked');
            if ($(this).hasClass('dbem-f-placeholder')) fields[i].placeholder = $(this).val();
            if ($(this).hasClass('dbem-f-options')) {
                fields[i].options = $(this).val().split('\n').map(function(s) { return s.trim(); }).filter(Boolean);
            }
            // Re-render solo per cambio tipo (per mostrare/nascondere opzioni)
            if ($(this).hasClass('dbem-f-type')) renderFields();
            else updateJSON();
        });

        $list.on('click', '.dbem-field-remove', function() {
            var i = $(this).data('index');
            fields.splice(i, 1);
            renderFields();
        });

        $container.find('[id$="-field"]').on('click', function() {
            fields.push({ type: 'text', label: '', required: false, options: [], placeholder: '' });
            renderFields();
            $list.find('.dbem-field-item:last .dbem-f-label').focus();
        });

        renderFields();
    }

    /* === Participants Page === */
    function initParticipants() {
        // Select all
        $('#dbem-select-all').on('change', function() {
            $('.dbem-row-check').prop('checked', this.checked);
        });

        // Bulk action
        $('#dbem-bulk-apply').on('click', function() {
            var action = $('#dbem-bulk-select').val();
            var ids = [];
            $('.dbem-row-check:checked').each(function() { ids.push($(this).val()); });
            if (!action || !ids.length) return;
            if (action === 'delete' && !confirm(dbem_admin.i18n.confirm_delete)) return;
            if (action === 'cancel' && !confirm(dbem_admin.i18n.confirm_cancel)) return;

            $.post(dbem_admin.ajax_url, {
                action: 'dbem_bulk_action',
                nonce: dbem_admin.nonce,
                bulk_action: action,
                ids: ids
            }, function(resp) {
                if (resp.success) location.reload();
                else alert(resp.data || dbem_admin.i18n.error);
            });
        });

        // Single actions
        $('.dbem-action-btn').on('click', function() {
            var btn = $(this);
            var act = btn.data('action');
            var id = btn.data('id');
            if (act === 'delete' && !confirm(dbem_admin.i18n.confirm_delete)) return;
            if (act === 'cancel' && !confirm(dbem_admin.i18n.confirm_cancel)) return;

            $.post(dbem_admin.ajax_url, {
                action: 'dbem_bulk_action',
                nonce: dbem_admin.nonce,
                bulk_action: act,
                ids: [id]
            }, function(resp) {
                if (resp.success) location.reload();
                else alert(resp.data || dbem_admin.i18n.error);
            });
        });

        // Resend email
        $('.dbem-resend-btn').on('click', function() {
            var btn = $(this);
            var id = btn.data('id');
            btn.prop('disabled', true);
            $.post(dbem_admin.ajax_url, {
                action: 'dbem_resend_email',
                nonce: dbem_admin.nonce,
                registration_id: id
            }, function(resp) {
                btn.prop('disabled', false);
                if (resp.success) {
                    btn.text('✅');
                    setTimeout(function() { btn.text('📧'); }, 2000);
                } else {
                    alert(resp.data || dbem_admin.i18n.error);
                }
            });
        });

        function getVisibleRegistrationIds() {
            var ids = [];
            $('tbody tr[data-id]:visible').each(function() {
                ids.push($(this).data('id'));
            });
            return ids;
        }

        function updateExportLink() {
            var link = $('#dbem-export-csv');
            if (!link.length) return;

            var baseUrl = link.data('base');
            if ($('#dbem-export-scope').val() !== 'visible') {
                link.attr('href', baseUrl).removeAttr('aria-disabled').removeClass('disabled');
                return;
            }

            var ids = getVisibleRegistrationIds();
            if (!ids.length) {
                link.removeAttr('href').attr('aria-disabled', 'true').addClass('disabled');
                return;
            }

            link.attr('href', baseUrl + '&' + ids.map(function(id) {
                return 'registration_ids%5B%5D=' + encodeURIComponent(id);
            }).join('&')).removeAttr('aria-disabled').removeClass('disabled');
        }

        $('#dbem-export-scope').on('change', updateExportLink);
        $('#dbem-export-csv').on('click', function(event) {
            if ($(this).attr('aria-disabled') === 'true') event.preventDefault();
        });
        updateExportLink();

        function updateReminderButton() {
            var visible = $('#dbem-reminder-scope').val() === 'visible';
            $('#dbem-send-reminder').text('📧 ' + (visible ? 'Invia reminder ai visualizzati' : 'Invia reminder a tutti'));
        }

        $('#dbem-reminder-scope').on('change', updateReminderButton);
        updateReminderButton();

        // Destinatari scelti nel selettore: null = tutti i validi, [] = nessuno visualizzato
        function reminderRecipientIds() {
            if ($('#dbem-reminder-scope').val() !== 'visible') return null;
            var ids = [];
            $('tbody tr[data-id]:visible').each(function() { ids.push($(this).data('id')); });
            return ids;
        }

        function reminderRequest(action, ids) {
            var request = {
                action: action,
                nonce: dbem_admin.nonce,
                event_id: $('#dbem-send-reminder').data('event')
            };
            if (ids) request.registration_ids = ids;
            return request;
        }

        function sendReminder() {
            var btn = $('#dbem-send-reminder');
            var ids = reminderRecipientIds();
            if (ids && !ids.length) {
                $('#dbem-reminder-feedback').text('❌ ' + dbem_admin.i18n.no_results);
                return;
            }

            if (!confirm(ids ? dbem_admin.i18n.confirm_reminder_visible : dbem_admin.i18n.confirm_reminder)) return;
            btn.prop('disabled', true).text('⏳ Invio...');
            $('#dbem-reminder-feedback').text('');

            $.post(dbem_admin.ajax_url, reminderRequest('dbem_send_reminder', ids), function(resp) {
                btn.prop('disabled', false);
                updateReminderButton();
                $('#dbem-reminder-feedback').text(resp.success ? '✅ ' + resp.data.message : '❌ ' + (resp.data || dbem_admin.i18n.error));
            });
        }

        $('#dbem-send-reminder').on('click', sendReminder);

        /* Anteprima reminder, con modifica del testo */
        var dialog = document.getElementById('dbem-reminder-preview');
        var previewIds = null;
        var previewIndex = 0;
        var templateLoaded = false;
        var templateDirty = false;
        var refreshTimer = null;

        function setTemplateStatus(text) {
            $('#dbem-template-status').text(text);
        }

        function fillTemplate(template) {
            $('#dbem-template-subject').val(template.subject);
            $('#dbem-template-message').val(template.message);
            $('#dbem-template-kind').text(template.custom ? '(personalizzato)' : '(predefinito)');
            templateLoaded = true;
            templateDirty = false;
        }

        function loadPreview(index) {
            $('#dbem-preview-prev, #dbem-preview-next').prop('disabled', true);
            $('#dbem-preview-counter').text(dbem_admin.i18n.loading);

            var request = reminderRequest('dbem_preview_reminder', previewIds);
            request.index = index;
            if (templateDirty) {
                request.template_subject = $('#dbem-template-subject').val();
                request.template_message = $('#dbem-template-message').val();
            }

            $.post(dbem_admin.ajax_url, request, function(resp) {
                if (!resp.success) {
                    if (dialog.open) dialog.close();
                    $('#dbem-reminder-feedback').text('❌ ' + (resp.data || dbem_admin.i18n.error));
                    return;
                }

                var data = resp.data;
                previewIndex = data.index;
                if (!templateLoaded) fillTemplate(data.template);
                $('#dbem-preview-to').text(data.to);
                $('#dbem-preview-subject').text(data.subject);
                $('#dbem-preview-attachment').text(data.attachment ? 'QR code (PNG)' : '—');
                $('#dbem-preview-frame').attr('srcdoc', data.html);
                $('#dbem-preview-counter').text(dbem_admin.i18n.preview_of.replace('%1$d', data.index + 1).replace('%2$d', data.total));
                $('#dbem-preview-prev').prop('disabled', data.index === 0);
                $('#dbem-preview-next').prop('disabled', data.index >= data.total - 1);
                $('#dbem-preview-send').text('📧 ' + dbem_admin.i18n.send_to.replace('%d', data.total));

                if (!dialog.open) dialog.showModal();
            }).fail(function() {
                $('#dbem-reminder-feedback').text('❌ ' + dbem_admin.i18n.error);
            });
        }

        function saveTemplate(extra, done) {
            var request = reminderRequest('dbem_save_reminder_template', null);
            request.template_subject = $('#dbem-template-subject').val();
            request.template_message = $('#dbem-template-message').val();
            $.extend(request, extra || {});

            $.post(dbem_admin.ajax_url, request, function(resp) {
                if (!resp.success) {
                    setTemplateStatus('❌ ' + (resp.data || dbem_admin.i18n.error));
                    return;
                }
                fillTemplate(resp.data.template);
                setTemplateStatus('✅ ' + resp.data.message);
                if (done) done();
            }).fail(function() {
                setTemplateStatus('❌ ' + dbem_admin.i18n.error);
            });
        }

        $('#dbem-preview-reminder').on('click', function() {
            previewIds = reminderRecipientIds();
            if (previewIds && !previewIds.length) {
                $('#dbem-reminder-feedback').text('❌ ' + dbem_admin.i18n.no_results);
                return;
            }
            $('#dbem-reminder-feedback').text('');
            templateLoaded = false;
            templateDirty = false;
            setTemplateStatus('');
            loadPreview(0);
        });

        // L'anteprima segue il testo mentre lo modifichi
        $('#dbem-template-subject, #dbem-template-message').on('input', function() {
            templateDirty = true;
            setTemplateStatus(dbem_admin.i18n.template_unsaved);
            clearTimeout(refreshTimer);
            refreshTimer = setTimeout(function() { loadPreview(previewIndex); }, 600);
        });

        $('#dbem-template-save').on('click', function() {
            saveTemplate(null, function() { loadPreview(previewIndex); });
        });

        $('#dbem-template-reset').on('click', function() {
            if (!confirm(dbem_admin.i18n.confirm_reset)) return;
            saveTemplate({ reset: 1 }, function() { loadPreview(previewIndex); });
        });

        $('#dbem-preview-prev').on('click', function() { loadPreview(previewIndex - 1); });
        $('#dbem-preview-next').on('click', function() { loadPreview(previewIndex + 1); });
        $('.dbem-preview-close').on('click', function() { dialog.close(); });
        $('#dbem-preview-send').on('click', function() {
            // Si invia sempre il testo salvato: le modifiche in corso si salvano prima
            if (!templateDirty) {
                dialog.close();
                sendReminder();
                return;
            }
            saveTemplate(null, function() {
                dialog.close();
                sendReminder();
            });
        });
    }

    /* === Survey Send === */
    function initSurvey() {
        $('[id^="dbem-send-survey"]').on('click', function() {
            var btn = $(this);
            var eventId = btn.data('event');
            var target = btn.data('target');
            btn.prop('disabled', true).text('⏳ Invio...');

            $.post(dbem_admin.ajax_url, {
                action: 'dbem_send_survey',
                nonce: dbem_admin.nonce,
                event_id: eventId,
                target: target
            }, function(resp) {
                btn.prop('disabled', false);
                if (resp.success) {
                    $('#dbem-survey-feedback').text('✅ ' + resp.data.message);
                } else {
                    $('#dbem-survey-feedback').text('❌ ' + (resp.data || 'Errore'));
                }
                btn.text(btn.attr('id') === 'dbem-send-survey' ? '📧 Invia survey ai presenti' : '📧 Invia survey a tutti');
            });
        });
    }

    /* === Opzioni rinominate: avviso nell'editor a blocchi === */
    function initOptionRenamesNotice() {
        if (!$('#dbem_custom_fields_json').length || !window.wp || !wp.data || !wp.data.select('core/edit-post')) return;

        var wasSaving = false;
        wp.data.subscribe(function() {
            var saving = wp.data.select('core/edit-post').isSavingMetaBoxes();
            if (wasSaving && !saving) {
                $.post(dbem_admin.ajax_url, { action: 'dbem_option_renames_notice', nonce: dbem_admin.nonce }, function(resp) {
                    if (!resp.success || !resp.data.lines.length) return;
                    var text = resp.data.title + ': ' + resp.data.lines.join('; ') + '. ' + resp.data.hint;
                    wp.data.dispatch('core/notices').createNotice('info', text, { isDismissible: true, id: 'dbem-option-renames' });
                });
            }
            wasSaving = saving;
        });
    }

    /* === Colori: anteprima e avviso di contrasto (stessa logica di DBEM_Appearance) === */
    var Appearance = {
        DEFAULT_PRIMARY: '#2271b1',

        init: function() {
            if (!$.fn.wpColorPicker) return;
            $('.dbem-appearance').each(function() {
                var $box = $(this);
                var update = function() { Appearance.update($box); };
                // Il valore dell'input si aggiorna dopo i callback del color picker
                $box.find('.dbem-color-input').wpColorPicker({
                    change: function() { setTimeout(update, 0); },
                    clear: function() { setTimeout(update, 0); }
                });
                $box.on('input change', '.dbem-color-input', update);
                update();
            });
        },

        normalize: function(c) {
            c = String(c || '').trim().toLowerCase();
            if (/^#[0-9a-f]{3}$/.test(c)) c = '#' + c[1] + c[1] + c[2] + c[2] + c[3] + c[3];
            return /^#[0-9a-f]{6}$/.test(c) ? c : '';
        },

        value: function($box, key) {
            return this.normalize($box.find('[data-color-key="' + key + '"]').val())
                || this.normalize($box.attr('data-inherit-' + key));
        },

        luminance: function(hex) {
            var rgb = [1, 3, 5].map(function(i) { return parseInt(hex.substr(i, 2), 16) / 255; })
                .map(function(c) { return c <= 0.03928 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4); });
            return 0.2126 * rgb[0] + 0.7152 * rgb[1] + 0.0722 * rgb[2];
        },

        contrast: function(a, b) {
            var la = this.luminance(a), lb = this.luminance(b);
            return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05);
        },

        bestText: function(bg) {
            return this.contrast(bg, '#ffffff') >= this.contrast(bg, '#000000') ? '#ffffff' : '#000000';
        },

        effective: function($box) {
            var bg = this.value($box, 'bg');
            var pageBg = bg || '#ffffff';
            var primary = this.value($box, 'primary') || this.DEFAULT_PRIMARY;
            // Senza sfondo né testo vale il colore del tema: per l'anteprima si assume un testo scuro
            var text = this.value($box, 'text') || (bg ? this.bestText(bg) : '');
            var shownText = text || '#1d2327';
            var link = this.contrast(primary, pageBg) >= 4.5 ? primary : shownText;
            return { bg: bg, pageBg: pageBg, primary: primary, text: text, shownText: shownText, buttonText: this.bestText(primary), link: link };
        },

        update: function($box) {
            var c = this.effective($box);
            var $preview = $box.find('.dbem-appearance-preview');
            $preview.css({ background: c.pageBg, color: c.shownText });
            $preview.find('.dbem-appearance-preview-link').css('color', c.link);
            $preview.find('.dbem-appearance-preview-button, .dbem-appearance-preview-date').css({ background: c.primary, color: c.buttonText });

            var msg = '';
            if (c.text) {
                var ratio = this.contrast(c.text, c.pageBg);
                if (ratio < 4.5) {
                    var tpl = c.bg ? dbem_admin.i18n.contrast_low : dbem_admin.i18n.contrast_low_white;
                    msg = tpl.replace('%s', ratio.toFixed(1).replace('.', ','));
                }
            }
            $box.find('.dbem-appearance-warning').text(msg).toggle(msg !== '');
        }
    };

    /* === Segnaposto cliccabili negli editor email === */
    var Placeholders = {
        init: function() {
            // Ultimo campo usato (oggetto o messaggio) per ogni elenco di segnaposto
            $(document).on('focusin', 'input, textarea', function() {
                var id = this.id;
                if (!id) return;
                $('.dbem-placeholders').each(function() {
                    var $list = $(this);
                    if ($list.data('subject') === id || $list.data('message') === id) $list.data('last', id);
                });
            });

            $(document).on('click', '.dbem-placeholder', function() {
                var $list = $(this).closest('.dbem-placeholders');
                var target = document.getElementById($list.data('last') || $list.data('message'));
                if (target) Placeholders.insert(target, $(this).data('token'));
            });
        },

        insert: function(field, token) {
            var start = typeof field.selectionStart === 'number' ? field.selectionStart : field.value.length;
            var end = typeof field.selectionEnd === 'number' ? field.selectionEnd : start;
            field.value = field.value.slice(0, start) + token + field.value.slice(end);
            field.focus();
            field.setSelectionRange(start + token.length, start + token.length);
            // L'anteprima del reminder si aggiorna sull'evento input
            $(field).trigger('input');
        }
    };

    /* === Helpers === */
    function escHtml(s) { return $('<span>').text(s || '').html(); }
    function escAttr(s) {
        return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    /* === Init === */
    $(document).ready(function() {
        initFieldsBuilder('#dbem-custom-fields', '#dbem_custom_fields_json');
        initFieldsBuilder('#dbem-survey-fields', '#dbem_survey_fields_json');
        initParticipants();
        initSurvey();
        initOptionRenamesNotice();
        Appearance.init();
        Placeholders.init();
    });

})(jQuery);
