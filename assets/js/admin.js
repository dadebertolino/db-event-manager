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

    /* === Helpers === */
    function escHtml(s) { return $('<span>').text(s || '').html(); }
    function escAttr(s) { return (s || '').replace(/"/g, '&quot;').replace(/'/g, '&#39;'); }

    /* === Init === */
    $(document).ready(function() {
        initFieldsBuilder('#dbem-custom-fields', '#dbem_custom_fields_json');
        initFieldsBuilder('#dbem-survey-fields', '#dbem_survey_fields_json');
        initParticipants();
        initSurvey();
    });

})(jQuery);
