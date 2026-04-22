<?php
if (!defined('ABSPATH')) exit;

class DBEM_Frontend {

    public static function enqueue_scripts() {
        wp_register_style('dbem-frontend', DBEM_PLUGIN_URL . 'assets/css/frontend.css', array(), DBEM_VERSION);
        wp_register_script('dbem-frontend', DBEM_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), DBEM_VERSION, true);
        wp_localize_script('dbem-frontend', 'dbem_front', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'i18n'     => array(
                'sending'    => __('Invio in corso...', 'db-event-manager'),
                'success'    => __('Iscrizione completata!', 'db-event-manager'),
                'error'      => __('Errore', 'db-event-manager'),
                'required'   => __('Questo campo è obbligatorio', 'db-event-manager'),
                'invalid_email' => __('Inserisci un email valido', 'db-event-manager'),
            ),
        ));

        // Script per adattare padding-top all'header del tema
        wp_register_script('dbem-header-fix', '', array(), DBEM_VERSION, true);
        wp_add_inline_script('dbem-header-fix', '
            (function() {
                var wrap = document.querySelector(".dbem-single-wrap, .dbem-archive-wrap");
                if (!wrap) return;
                function fix() {
                    var header = document.querySelector("header, [role=banner], .site-header, #masthead, .header, nav.navbar");
                    if (!header) return;
                    var style = window.getComputedStyle(header);
                    if (style.position === "fixed" || style.position === "sticky") {
                        wrap.style.paddingTop = (header.offsetHeight + 24) + "px";
                    }
                }
                if (document.readyState === "loading") {
                    document.addEventListener("DOMContentLoaded", fix);
                } else {
                    fix();
                }
                window.addEventListener("resize", fix);
            })();
        ');
    }

    /**
     * Render dettagli evento
     */
    public static function render_event_details($event_id) {
        $start = get_post_meta($event_id, '_dbem_date_start', true);
        $end = get_post_meta($event_id, '_dbem_date_end', true);
        $location = get_post_meta($event_id, '_dbem_location', true);
        $max = (int) get_post_meta($event_id, '_dbem_max_participants', true);
        $count = DBEM_DB::count_registrations($event_id);
        $status = DBEM_CPT::get_event_status($event_id);

        // Formato data: il valore da datetime-local è già in ora locale
        // Se l'evento ha assegnazione orario, mostra solo la data (senza ora)
        $time_slot_enabled = get_post_meta($event_id, '_dbem_time_slot_enabled', true);
        if ($time_slot_enabled === '1') {
            $start_fmt = $start ? date('d/m/Y', strtotime($start)) : '';
            $end_fmt = $end ? date('d/m/Y', strtotime($end)) : '';
        } else {
            $start_fmt = $start ? date('d/m/Y H:i', strtotime($start)) : '';
            $end_fmt = $end ? date('d/m/Y H:i', strtotime($end)) : '';
        }

        ob_start();
        ?>
        <div class="dbem-event-details" role="region" aria-label="<?php esc_attr_e('Dettagli evento', 'db-event-manager'); ?>">
            <div class="dbem-event-meta">
                <?php if ($start_fmt): ?>
                <div class="dbem-meta-item">
                    <span class="dbem-meta-icon" aria-hidden="true">📅</span>
                    <span class="dbem-meta-label"><?php esc_html_e('Data:', 'db-event-manager'); ?></span>
                    <span class="dbem-meta-value">
                        <time datetime="<?php echo esc_attr($start); ?>"><?php echo esc_html($start_fmt); ?></time>
                        <?php if ($end_fmt): ?> — <time datetime="<?php echo esc_attr($end); ?>"><?php echo esc_html($end_fmt); ?></time><?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if ($location): ?>
                <div class="dbem-meta-item">
                    <span class="dbem-meta-icon" aria-hidden="true">📍</span>
                    <span class="dbem-meta-label"><?php esc_html_e('Luogo:', 'db-event-manager'); ?></span>
                    <span class="dbem-meta-value"><?php echo esc_html($location); ?></span>
                </div>
                <?php endif; ?>

                <?php
                $categories = get_the_terms($event_id, 'dbem_category');
                if ($categories && !is_wp_error($categories)):
                ?>
                <div class="dbem-meta-item">
                    <span class="dbem-meta-icon" aria-hidden="true">🏷️</span>
                    <span class="dbem-meta-label"><?php esc_html_e('Categoria:', 'db-event-manager'); ?></span>
                    <span class="dbem-meta-value">
                        <?php echo esc_html(implode(', ', wp_list_pluck($categories, 'name'))); ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if ($max > 0): ?>
                <div class="dbem-meta-item">
                    <span class="dbem-meta-icon" aria-hidden="true">👥</span>
                    <span class="dbem-meta-label"><?php esc_html_e('Posti:', 'db-event-manager'); ?></span>
                    <span class="dbem-meta-value">
                        <?php echo esc_html($count); ?> / <?php echo esc_html($max); ?>
                        <?php esc_html_e('iscritti', 'db-event-manager'); ?>
                    </span>
                </div>
                <div class="dbem-progress-bar" role="progressbar" aria-valuenow="<?php echo esc_attr($count); ?>" aria-valuemin="0" aria-valuemax="<?php echo esc_attr($max); ?>">
                    <div class="dbem-progress-fill" style="width: <?php echo esc_attr(min(100, ($count / max(1, $max)) * 100)); ?>%"></div>
                </div>
                <?php endif; ?>
            </div>

            <?php
            $remaining = DBEM_CPT::get_remaining_spots($event_id);
            $reg_open = DBEM_CPT::are_registrations_open($event_id);

            if ($status === 'past'):
            ?>
                <div class="dbem-notice dbem-notice-info" role="status">
                    <span aria-hidden="true">📌</span> <?php esc_html_e('Questo evento è già concluso.', 'db-event-manager'); ?>
                </div>
            <?php elseif ($remaining === -1): ?>
                <div class="dbem-notice dbem-notice-warning" role="status">
                    <span aria-hidden="true">🚫</span> <?php esc_html_e('Posti esauriti.', 'db-event-manager'); ?>
                </div>
            <?php elseif (!$reg_open): ?>
                <div class="dbem-notice dbem-notice-info" role="status">
                    <span aria-hidden="true">🔒</span> <?php esc_html_e('Iscrizioni chiuse.', 'db-event-manager'); ?>
                </div>
            <?php else:
                if ($remaining > 0 && $remaining <= 10): ?>
                    <div class="dbem-notice dbem-notice-spots" role="status">
                        <span aria-hidden="true">⚡</span>
                        <?php printf(
                            esc_html(_n('%d posto disponibile', '%d posti disponibili', $remaining, 'db-event-manager')),
                            $remaining
                        ); ?>
                    </div>
                <?php endif;
                $form_source = get_post_meta($event_id, '_dbem_form_source', true) ?: 'builtin';
                if ($form_source === 'dbfb' && class_exists('DB_Form_Builder')) {
                    echo self::render_dbfb_registration_form($event_id);
                } else {
                    echo self::render_registration_form($event_id);
                }
            endif;
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render form iscrizione
     */
    public static function render_registration_form($event_id) {
        wp_enqueue_style('dbem-frontend');
        wp_enqueue_script('dbem-frontend');

        $custom_fields = get_post_meta($event_id, '_dbem_custom_fields', true);
        if (!is_array($custom_fields)) $custom_fields = array();

        $nonce = wp_create_nonce('dbem_registration_nonce');

        ob_start();
        ?>
        <form class="dbem-form" id="dbem-form-<?php echo esc_attr($event_id); ?>" method="post" novalidate>
            <input type="hidden" name="action" value="dbem_register">
            <input type="hidden" name="event_id" value="<?php echo esc_attr($event_id); ?>">
            <input type="hidden" name="dbem_nonce" value="<?php echo esc_attr($nonce); ?>">

            <!-- Honeypot -->
            <div class="dbem-hp" aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;">
                <label for="dbem_website_url_<?php echo esc_attr($event_id); ?>">Website</label>
                <input type="text" name="dbem_website_url" id="dbem_website_url_<?php echo esc_attr($event_id); ?>" tabindex="-1" autocomplete="off">
            </div>

            <div class="dbem-field">
                <label for="dbem_name_<?php echo esc_attr($event_id); ?>" class="dbem-label">
                    <?php esc_html_e('Nome e Cognome', 'db-event-manager'); ?> <span class="dbem-required" aria-hidden="true">*</span>
                </label>
                <input type="text" id="dbem_name_<?php echo esc_attr($event_id); ?>" name="dbem_name" class="dbem-input" required aria-required="true" autocomplete="name">
                <span class="dbem-error" role="alert" aria-live="polite"></span>
            </div>

            <div class="dbem-field">
                <label for="dbem_email_<?php echo esc_attr($event_id); ?>" class="dbem-label">
                    <?php esc_html_e('Email', 'db-event-manager'); ?> <span class="dbem-required" aria-hidden="true">*</span>
                </label>
                <input type="email" id="dbem_email_<?php echo esc_attr($event_id); ?>" name="dbem_email" class="dbem-input" required aria-required="true" autocomplete="email">
                <span class="dbem-error" role="alert" aria-live="polite"></span>
            </div>

            <?php foreach ($custom_fields as $i => $field): ?>
                <?php echo self::render_form_field($field, $i, $event_id); ?>
            <?php endforeach; ?>

            <div class="dbem-field dbem-field-checkbox">
                <label class="dbem-checkbox-label">
                    <input type="checkbox" name="dbem_privacy" value="1" required aria-required="true">
                    <span><?php esc_html_e('Accetto l\'informativa sulla privacy e il trattamento dei dati personali', 'db-event-manager'); ?> <span class="dbem-required" aria-hidden="true">*</span></span>
                </label>
                <span class="dbem-error" role="alert" aria-live="polite"></span>
            </div>

            <div class="dbem-field">
                <button type="submit" class="dbem-submit">
                    <span class="dbem-submit-text"><?php esc_html_e('Iscriviti', 'db-event-manager'); ?></span>
                    <span class="dbem-submit-loading" style="display:none;" aria-hidden="true">⏳ <?php esc_html_e('Invio in corso...', 'db-event-manager'); ?></span>
                </button>
            </div>

            <div class="dbem-message" role="alert" aria-live="polite" style="display:none;"></div>
        </form>
        <?php
        return ob_get_clean();
    }

    /**
     * Render form iscrizione via DB Form Builder
     */
    public static function render_dbfb_registration_form($event_id) {
        $dbfb_form_id = get_post_meta($event_id, '_dbem_dbfb_form_id', true);
        if (!$dbfb_form_id) {
            return '<p class="dbem-error-msg">' . esc_html__('Nessun form configurato per questo evento.', 'db-event-manager') . '</p>';
        }

        $name_field = get_post_meta($event_id, '_dbem_dbfb_name_field', true) ?: 'nome';
        $email_field = get_post_meta($event_id, '_dbem_dbfb_email_field', true) ?: 'email';
        $nonce = wp_create_nonce('dbem_registration_nonce');

        wp_enqueue_style('dbem-frontend');

        ob_start();
        ?>
        <div class="dbem-dbfb-wrap" data-event-id="<?php echo esc_attr($event_id); ?>"
             data-name-field="<?php echo esc_attr($name_field); ?>"
             data-email-field="<?php echo esc_attr($email_field); ?>"
             data-nonce="<?php echo esc_attr($nonce); ?>"
             data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>">
            <?php echo do_shortcode('[dbfb_form id="' . absint($dbfb_form_id) . '"]'); ?>
            <div class="dbem-message dbem-dbfb-message" role="alert" aria-live="polite" style="display:none;"></div>
        </div>

        <script>
        (function() {
            var wrap = document.querySelector('.dbem-dbfb-wrap[data-event-id="<?php echo esc_js($event_id); ?>"]');
            if (!wrap) return;
            var form = wrap.querySelector('.dbfb-form');
            if (!form) return;

            var eventId = wrap.dataset.eventId;
            var nameField = wrap.dataset.nameField;
            var emailField = wrap.dataset.emailField;
            var nonce = wrap.dataset.nonce;
            var ajaxUrl = wrap.dataset.ajaxUrl;
            var dbemMsg = wrap.querySelector('.dbem-dbfb-message');
            var registered = false;

            // Osserva quando DBFB mostra il messaggio di successo
            var observer = new MutationObserver(function() {
                if (registered) return;
                var msgRegion = form.querySelector('.dbfb-messages-region');
                if (!msgRegion) return;
                // DBFB inserisce un div con classe dbfb-message-success quando il form è inviato
                var successMsg = msgRegion.querySelector('.dbfb-message-success') || msgRegion.querySelector('[class*="success"]');
                if (!successMsg && msgRegion.textContent.trim().length < 5) return;

                // Raccogli dati dal form (prima che venga resettato)
                var inputs = form.querySelectorAll('input, select, textarea');
                var data = {};
                inputs.forEach(function(el) {
                    if (!el.name || el.name.indexOf('dbfb_') === 0) return;
                    if (el.type === 'checkbox' || el.type === 'radio') {
                        if (el.checked) data[el.name] = el.value;
                    } else {
                        data[el.name] = el.value;
                    }
                });

                var name = data[nameField] || '';
                var email = data[emailField] || '';
                if (!name || !email) return;

                registered = true;

                // Registra iscrizione evento
                var body = new URLSearchParams();
                body.append('action', 'dbem_register_dbfb');
                body.append('event_id', eventId);
                body.append('dbem_name', name);
                body.append('dbem_email', email);
                body.append('dbem_data', JSON.stringify(data));
                body.append('dbem_nonce', nonce);

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: body.toString()
                })
                .then(function(r) { return r.json(); })
                .then(function(resp) {
                    if (resp.success) {
                        dbemMsg.className = 'dbem-message dbem-dbfb-message dbem-message-success';
                        dbemMsg.textContent = resp.data.message;
                    } else {
                        dbemMsg.className = 'dbem-message dbem-dbfb-message dbem-message-error';
                        dbemMsg.textContent = resp.data || 'Errore iscrizione evento';
                    }
                    dbemMsg.style.display = 'block';
                });
            });

            observer.observe(form, { childList: true, subtree: true, characterData: true });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Render campo custom
     */
    private static function render_form_field($field, $index, $event_id) {
        $field_id = 'dbem_custom_' . $index . '_' . $event_id;
        $field_name = 'dbem_custom_' . $index;
        $required = !empty($field['required']);
        $req_attr = $required ? ' required aria-required="true"' : '';
        $req_star = $required ? ' <span class="dbem-required" aria-hidden="true">*</span>' : '';

        ob_start();
        ?>
        <div class="dbem-field">
        <?php
        switch ($field['type']) {
            case 'text':
            case 'email':
            case 'tel':
            case 'number':
            case 'url':
            case 'date':
                ?>
                <label for="<?php echo esc_attr($field_id); ?>" class="dbem-label">
                    <?php echo esc_html($field['label']); ?><?php echo $req_star; ?>
                </label>
                <input type="<?php echo esc_attr($field['type']); ?>" id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name); ?>" class="dbem-input" placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"<?php echo $req_attr; ?>>
                <?php
                break;

            case 'textarea':
                ?>
                <label for="<?php echo esc_attr($field_id); ?>" class="dbem-label">
                    <?php echo esc_html($field['label']); ?><?php echo $req_star; ?>
                </label>
                <textarea id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name); ?>" class="dbem-textarea" rows="4" placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"<?php echo $req_attr; ?>></textarea>
                <?php
                break;

            case 'select':
                ?>
                <label for="<?php echo esc_attr($field_id); ?>" class="dbem-label">
                    <?php echo esc_html($field['label']); ?><?php echo $req_star; ?>
                </label>
                <select id="<?php echo esc_attr($field_id); ?>" name="<?php echo esc_attr($field_name); ?>" class="dbem-select"<?php echo $req_attr; ?>>
                    <option value=""><?php esc_html_e('— Seleziona —', 'db-event-manager'); ?></option>
                    <?php foreach (($field['options'] ?? array()) as $opt): ?>
                        <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                    <?php endforeach; ?>
                </select>
                <?php
                break;

            case 'radio':
                ?>
                <fieldset>
                    <legend class="dbem-label"><?php echo esc_html($field['label']); ?><?php echo $req_star; ?></legend>
                    <?php foreach (($field['options'] ?? array()) as $j => $opt): ?>
                        <label class="dbem-radio-label">
                            <input type="radio" name="<?php echo esc_attr($field_name); ?>" value="<?php echo esc_attr($opt); ?>"<?php echo $req_attr; ?>>
                            <span><?php echo esc_html($opt); ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <?php
                break;

            case 'checkbox':
                ?>
                <fieldset>
                    <legend class="dbem-label"><?php echo esc_html($field['label']); ?><?php echo $req_star; ?></legend>
                    <?php foreach (($field['options'] ?? array()) as $j => $opt): ?>
                        <label class="dbem-checkbox-label">
                            <input type="checkbox" name="<?php echo esc_attr($field_name); ?>[]" value="<?php echo esc_attr($opt); ?>">
                            <span><?php echo esc_html($opt); ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <?php
                break;
        }
        ?>
            <span class="dbem-error" role="alert" aria-live="polite"></span>
        </div>
        <?php
        return ob_get_clean();
    }
}
