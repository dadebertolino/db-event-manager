<?php
if (!defined('ABSPATH')) exit;

class DBEM_Admin {

    public static function register_menus() {
        // Sottomenu sotto il CPT
        add_submenu_page(
            'edit.php?post_type=dbem_event',
            __('Check-in', 'db-event-manager'),
            __('Check-in', 'db-event-manager'),
            'manage_options',
            'dbem-checkin',
            array('DBEM_Checkin', 'render_page')
        );

        add_submenu_page(
            'edit.php?post_type=dbem_event',
            __('Partecipanti', 'db-event-manager'),
            __('Partecipanti', 'db-event-manager'),
            'manage_options',
            'dbem-participants',
            array(__CLASS__, 'render_participants_page')
        );

        add_submenu_page(
            'edit.php?post_type=dbem_event',
            __('Survey', 'db-event-manager'),
            __('Survey', 'db-event-manager'),
            'manage_options',
            'dbem-survey',
            array('DBEM_Survey', 'render_admin_page')
        );

        add_submenu_page(
            'edit.php?post_type=dbem_event',
            __('Impostazioni', 'db-event-manager'),
            __('Impostazioni', 'db-event-manager'),
            'manage_options',
            'dbem-settings',
            array(__CLASS__, 'render_settings_page')
        );
    }

    public static function enqueue_scripts($hook) {
        $screen = get_current_screen();
        if (!$screen) return;

        // Solo nelle pagine del plugin
        $is_plugin_page = (
            $screen->post_type === 'dbem_event' ||
            (isset($_GET['page']) && strpos($_GET['page'], 'dbem') !== false)
        );

        if (!$is_plugin_page) return;

        wp_enqueue_style('db-admin-ui', DBEM_PLUGIN_URL . 'assets/css/db-admin-ui.css', array(), '1.0.0');
        wp_enqueue_style('dbem-admin', DBEM_PLUGIN_URL . 'assets/css/admin.css', array('db-admin-ui'), DBEM_VERSION);
        wp_enqueue_script('dbem-sortable', DBEM_PLUGIN_URL . 'assets/js/vendor/Sortable.min.js', array(), '1.15.0', true);
        wp_enqueue_script('dbem-admin', DBEM_PLUGIN_URL . 'assets/js/admin.js', array('jquery', 'dbem-sortable'), DBEM_VERSION, true);

        wp_localize_script('dbem-admin', 'dbem_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('dbem_admin_nonce'),
            'i18n'     => array(
                'confirm_delete'   => __('Sei sicuro di voler eliminare?', 'db-event-manager'),
                'confirm_cancel'   => __('Sei sicuro di voler annullare questa iscrizione?', 'db-event-manager'),
                'checked_in'       => __('Check-in effettuato', 'db-event-manager'),
                'already_checked'  => __('Già registrato', 'db-event-manager'),
                'cancelled'        => __('Iscrizione annullata', 'db-event-manager'),
                'invalid_token'    => __('QR code non valido', 'db-event-manager'),
                'no_results'       => __('Nessun risultato', 'db-event-manager'),
                'email_sent'       => __('Email inviata', 'db-event-manager'),
                'error'            => __('Errore', 'db-event-manager'),
                'loading'          => __('Caricamento...', 'db-event-manager'),
                'add_field'        => __('Aggiungi campo', 'db-event-manager'),
                'remove_field'     => __('Rimuovi campo', 'db-event-manager'),
            ),
        ));

        // Pagina check-in: scanner QR
        if (isset($_GET['page']) && $_GET['page'] === 'dbem-checkin') {
            wp_enqueue_script('dbem-html5-qrcode', DBEM_PLUGIN_URL . 'assets/js/vendor/html5-qrcode.min.js', array(), '2.3.8', true);
            wp_enqueue_script('dbem-checkin', DBEM_PLUGIN_URL . 'assets/js/checkin.js', array('jquery', 'dbem-html5-qrcode'), DBEM_VERSION, true);
            wp_localize_script('dbem-checkin', 'dbem_checkin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('dbem_checkin_nonce'),
                'site_url' => home_url('/'),
            ));
        }
    }

    /**
     * Registra metabox
     */
    public static function register_metaboxes() {
        add_meta_box(
            'dbem_event_details',
            __('Dettagli Evento', 'db-event-manager'),
            array(__CLASS__, 'render_details_metabox'),
            'dbem_event',
            'normal',
            'high'
        );
        add_meta_box(
            'dbem_event_registration',
            __('Iscrizioni', 'db-event-manager'),
            array(__CLASS__, 'render_registration_metabox'),
            'dbem_event',
            'normal',
            'high'
        );
        add_meta_box(
            'dbem_event_email',
            __('Email di Conferma', 'db-event-manager'),
            array(__CLASS__, 'render_email_metabox'),
            'dbem_event',
            'normal',
            'default'
        );
        add_meta_box(
            'dbem_event_survey',
            __('Survey Post-Evento', 'db-event-manager'),
            array(__CLASS__, 'render_survey_metabox'),
            'dbem_event',
            'normal',
            'default'
        );
        add_meta_box(
            'dbem_event_stats',
            __('Statistiche', 'db-event-manager'),
            array(__CLASS__, 'render_stats_metabox'),
            'dbem_event',
            'side',
            'default'
        );
    }

    public static function render_details_metabox($post) {
        wp_nonce_field('dbem_save_event', 'dbem_event_nonce');
        $event_name = get_post_meta($post->ID, '_dbem_event_name', true);
        $start = get_post_meta($post->ID, '_dbem_date_start', true);
        $end = get_post_meta($post->ID, '_dbem_date_end', true);
        $location = get_post_meta($post->ID, '_dbem_location', true);
        $max = get_post_meta($post->ID, '_dbem_max_participants', true);
        ?>
        <table class="form-table dbem-metabox-table">
            <tr>
                <th><label for="dbem_event_name"><?php _e('Nome evento', 'db-event-manager'); ?></label></th>
                <td><input type="text" id="dbem_event_name" name="_dbem_event_name" value="<?php echo esc_attr($event_name); ?>" class="large-text" required placeholder="<?php esc_attr_e('Es. Workshop di fotografia digitale', 'db-event-manager'); ?>"></td>
            </tr>
            <tr>
                <th><label for="dbem_date_start"><?php _e('Data/ora inizio', 'db-event-manager'); ?></label></th>
                <td><input type="datetime-local" id="dbem_date_start" name="_dbem_date_start" value="<?php echo esc_attr($start); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="dbem_date_end"><?php _e('Data/ora fine', 'db-event-manager'); ?></label></th>
                <td><input type="datetime-local" id="dbem_date_end" name="_dbem_date_end" value="<?php echo esc_attr($end); ?>" class="regular-text" required></td>
            </tr>
            <tr>
                <th><label for="dbem_location"><?php _e('Luogo', 'db-event-manager'); ?></label></th>
                <td><input type="text" id="dbem_location" name="_dbem_location" value="<?php echo esc_attr($location); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th><label for="dbem_max_participants"><?php _e('Posti disponibili', 'db-event-manager'); ?></label></th>
                <td>
                    <input type="number" id="dbem_max_participants" name="_dbem_max_participants" value="<?php echo esc_attr($max); ?>" class="small-text" min="0">
                    <p class="description"><?php _e('0 = illimitati', 'db-event-manager'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function render_registration_metabox($post) {
        $open = get_post_meta($post->ID, '_dbem_registration_open', true);
        $deadline = get_post_meta($post->ID, '_dbem_registration_deadline', true);
        $approval_mode = get_post_meta($post->ID, '_dbem_approval_mode', true) ?: 'auto';
        $approver_email = get_post_meta($post->ID, '_dbem_approver_email', true);
        $custom_fields = get_post_meta($post->ID, '_dbem_custom_fields', true);
        if (!$custom_fields) $custom_fields = array();
        $form_source = get_post_meta($post->ID, '_dbem_form_source', true) ?: 'builtin';
        $dbfb_form_id = get_post_meta($post->ID, '_dbem_dbfb_form_id', true);

        $dbfb_active = class_exists('DB_Form_Builder');
        $dbfb_forms = array();
        if ($dbfb_active) {
            $dbfb_forms = get_posts(array(
                'post_type'      => 'dbfb_form',
                'posts_per_page' => -1,
                'orderby'        => 'title',
                'order'          => 'ASC',
            ));
        }
        ?>
        <table class="form-table dbem-metabox-table">
            <tr>
                <th><label for="dbem_registration_open"><?php _e('Iscrizioni aperte', 'db-event-manager'); ?></label></th>
                <td>
                    <label><input type="checkbox" id="dbem_registration_open" name="_dbem_registration_open" value="1" <?php checked($open, '1'); ?>> <?php _e('Sì', 'db-event-manager'); ?></label>
                </td>
            </tr>
            <tr>
                <th><label for="dbem_registration_deadline"><?php _e('Scadenza iscrizioni', 'db-event-manager'); ?></label></th>
                <td>
                    <input type="datetime-local" id="dbem_registration_deadline" name="_dbem_registration_deadline" value="<?php echo esc_attr($deadline); ?>" class="regular-text">
                    <p class="description"><?php _e('Opzionale. Dopo questa data le iscrizioni si chiudono automaticamente.', 'db-event-manager'); ?></p>
                </td>
            </tr>
            <tr>
                <th><label><?php _e('Modalità iscrizione', 'db-event-manager'); ?></label></th>
                <td>
                    <label style="margin-right:16px;">
                        <input type="radio" name="_dbem_approval_mode" value="auto" <?php checked($approval_mode, 'auto'); ?>>
                        <?php _e('Accettazione automatica', 'db-event-manager'); ?>
                    </label>
                    <label>
                        <input type="radio" name="_dbem_approval_mode" value="approval" <?php checked($approval_mode, 'approval'); ?>>
                        <?php _e('Richiede approvazione', 'db-event-manager'); ?>
                    </label>
                    <p class="description"><?php _e('Con approvazione: l\'iscritto riceve una conferma solo dopo l\'approvazione manuale.', 'db-event-manager'); ?></p>
                </td>
            </tr>
            <tr class="dbem-approval-row" style="<?php echo $approval_mode !== 'approval' ? 'display:none;' : ''; ?>">
                <th><label for="dbem_approver_email"><?php _e('Email approvatore', 'db-event-manager'); ?></label></th>
                <td>
                    <input type="text" id="dbem_approver_email" name="_dbem_approver_email" value="<?php echo esc_attr($approver_email); ?>" class="regular-text" placeholder="<?php echo esc_attr(get_option('admin_email')); ?>">
                    <p class="description"><?php _e('Chi riceve la richiesta di approvazione. Più indirizzi separati da virgola. Se vuoto, usa l\'email notifica admin.', 'db-event-manager'); ?></p>
                </td>
            </tr>
        </table>

        <script>
        jQuery(function($) {
            $('input[name="_dbem_approval_mode"]').on('change', function() {
                if ($(this).val() === 'approval') {
                    $('.dbem-approval-row').show();
                } else {
                    $('.dbem-approval-row').hide();
                }
            });
        });
        </script>

            <?php if ($dbfb_active): ?>
            <tr>
                <th><label><?php _e('Tipo form', 'db-event-manager'); ?></label></th>
                <td>
                    <label style="margin-right:16px;">
                        <input type="radio" name="_dbem_form_source" value="builtin" <?php checked($form_source, 'builtin'); ?> class="dbem-form-source-radio">
                        <?php _e('Form integrato', 'db-event-manager'); ?>
                    </label>
                    <label>
                        <input type="radio" name="_dbem_form_source" value="dbfb" <?php checked($form_source, 'dbfb'); ?> class="dbem-form-source-radio">
                        <?php _e('DB Form Builder', 'db-event-manager'); ?>
                    </label>
                </td>
            </tr>
            <?php else: ?>
                <input type="hidden" name="_dbem_form_source" value="builtin">
            <?php endif; ?>
        </table>

        <!-- Form integrato -->
        <div id="dbem-form-builtin" style="<?php echo ($dbfb_active && $form_source === 'dbfb') ? 'display:none;' : ''; ?>">
            <h4><?php _e('Campi personalizzati del form iscrizione', 'db-event-manager'); ?></h4>
            <p class="description"><?php _e('Nome e Email sono sempre presenti. Aggiungi qui eventuali campi aggiuntivi.', 'db-event-manager'); ?></p>

            <div id="dbem-custom-fields" data-fields="<?php echo esc_attr(wp_json_encode($custom_fields)); ?>">
                <div id="dbem-fields-list"></div>
                <button type="button" class="button" id="dbem-add-field">
                    <span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;"></span>
                    <?php _e('Aggiungi campo', 'db-event-manager'); ?>
                </button>
            </div>
            <input type="hidden" name="_dbem_custom_fields" id="dbem_custom_fields_json" value="<?php echo esc_attr(wp_json_encode($custom_fields)); ?>">
        </div>

        <!-- DB Form Builder -->
        <?php if ($dbfb_active): ?>
        <div id="dbem-form-dbfb" style="<?php echo $form_source !== 'dbfb' ? 'display:none;' : ''; ?>">
            <h4><?php _e('Seleziona un form di DB Form Builder', 'db-event-manager'); ?></h4>
            <p class="description"><?php _e('I campi Nome e Email devono essere presenti nel form selezionato. I dati compilati verranno salvati come iscrizione all\'evento.', 'db-event-manager'); ?></p>
            <table class="form-table dbem-metabox-table">
                <tr>
                    <th><label for="dbem_dbfb_form_id"><?php _e('Form', 'db-event-manager'); ?></label></th>
                    <td>
                        <select id="dbem_dbfb_form_id" name="_dbem_dbfb_form_id">
                            <option value=""><?php _e('— Seleziona form —', 'db-event-manager'); ?></option>
                            <?php foreach ($dbfb_forms as $f): ?>
                                <option value="<?php echo esc_attr($f->ID); ?>" <?php selected($dbfb_form_id, $f->ID); ?>>
                                    <?php echo esc_html($f->post_title); ?> (ID: <?php echo esc_html($f->ID); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($dbfb_form_id): ?>
                            <a href="<?php echo esc_url(admin_url('admin.php?page=dbfb-forms&action=edit&form_id=' . $dbfb_form_id)); ?>" class="button button-small" style="margin-left:8px;">
                                <?php _e('Modifica form', 'db-event-manager'); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <th><label for="dbem_dbfb_name_field"><?php _e('Campo Nome', 'db-event-manager'); ?></label></th>
                    <td>
                        <input type="text" id="dbem_dbfb_name_field" name="_dbem_dbfb_name_field" value="<?php echo esc_attr(get_post_meta($post->ID, '_dbem_dbfb_name_field', true) ?: 'nome'); ?>" class="regular-text" placeholder="nome">
                        <p class="description"><?php _e('ID del campo nel form DBFB che contiene il nome (es. "nome", "name", "field_1")', 'db-event-manager'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="dbem_dbfb_email_field"><?php _e('Campo Email', 'db-event-manager'); ?></label></th>
                    <td>
                        <input type="text" id="dbem_dbfb_email_field" name="_dbem_dbfb_email_field" value="<?php echo esc_attr(get_post_meta($post->ID, '_dbem_dbfb_email_field', true) ?: 'email'); ?>" class="regular-text" placeholder="email">
                        <p class="description"><?php _e('ID del campo nel form DBFB che contiene l\'email (es. "email", "e-mail", "field_2")', 'db-event-manager'); ?></p>
                    </td>
                </tr>
            </table>
        </div>
        <?php endif; ?>

        <script>
        jQuery(function($) {
            $('.dbem-form-source-radio').on('change', function() {
                if ($(this).val() === 'dbfb') {
                    $('#dbem-form-builtin').hide();
                    $('#dbem-form-dbfb').show();
                } else {
                    $('#dbem-form-builtin').show();
                    $('#dbem-form-dbfb').hide();
                }
            });
        });
        </script>
        <?php
    }

    public static function render_email_metabox($post) {
        $email_data = get_post_meta($post->ID, '_dbem_confirmation_email', true);
        if (!$email_data) {
            $email_data = array(
                'subject' => __('Conferma iscrizione a {evento}', 'db-event-manager'),
                'message' => __("Ciao {nome},\n\nla tua iscrizione all'evento \"{evento}\" è confermata!\n\n📅 Data: {data_evento}\n📍 Luogo: {luogo}\n\n{riepilogo_dati}\n\nPresenta il QR code allegato all'ingresso dell'evento.\n\nA presto!", 'db-event-manager'),
            );
        }
        $notify_admin = get_post_meta($post->ID, '_dbem_notify_admin', true);
        $admin_email = get_post_meta($post->ID, '_dbem_admin_email', true);
        if (!$admin_email) $admin_email = get_option('admin_email');
        ?>
        <table class="form-table dbem-metabox-table">
            <tr>
                <th><label for="dbem_email_subject"><?php _e('Oggetto email', 'db-event-manager'); ?></label></th>
                <td><input type="text" id="dbem_email_subject" name="_dbem_confirmation_email[subject]" value="<?php echo esc_attr($email_data['subject']); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th><label for="dbem_email_message"><?php _e('Messaggio email', 'db-event-manager'); ?></label></th>
                <td>
                    <textarea id="dbem_email_message" name="_dbem_confirmation_email[message]" rows="10" class="large-text"><?php echo esc_textarea($email_data['message']); ?></textarea>
                    <p class="description">
                        <?php _e('Placeholder disponibili: {nome}, {email}, {evento}, {data_evento}, {luogo}, {riepilogo_dati}, {qrcode_url}, {token}, {sito}', 'db-event-manager'); ?>
                    </p>
                </td>
            </tr>
            <tr>
                <th><label for="dbem_notify_admin"><?php _e('Notifica admin', 'db-event-manager'); ?></label></th>
                <td>
                    <label><input type="checkbox" id="dbem_notify_admin" name="_dbem_notify_admin" value="1" <?php checked($notify_admin, '1'); ?>> <?php _e('Invia notifica email ad ogni iscrizione', 'db-event-manager'); ?></label>
                </td>
            </tr>
            <tr>
                <th><label for="dbem_admin_email"><?php _e('Email destinatario', 'db-event-manager'); ?></label></th>
                <td>
                    <input type="email" id="dbem_admin_email" name="_dbem_admin_email" value="<?php echo esc_attr($admin_email); ?>" class="regular-text">
                    <p class="description"><?php _e('Chi riceve la notifica per questo evento. Puoi inserire più indirizzi separati da virgola.', 'db-event-manager'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function render_survey_metabox($post) {
        $survey_enabled = get_post_meta($post->ID, '_dbem_survey_enabled', true);
        $survey_fields = get_post_meta($post->ID, '_dbem_survey_fields', true);
        if (!$survey_fields) $survey_fields = array();
        $survey_email = get_post_meta($post->ID, '_dbem_survey_email', true);
        if (!$survey_email) {
            $survey_email = array(
                'subject' => __('Com\'è andato {evento}? Dicci la tua!', 'db-event-manager'),
                'message' => __("Ciao {nome},\n\ngrazie per aver partecipato a \"{evento}\"!\nCi farebbe piacere sapere cosa ne pensi.\n\nCompila il breve questionario:\n{survey_link}\n\nGrazie!", 'db-event-manager'),
            );
        }
        $survey_auto = get_post_meta($post->ID, '_dbem_survey_auto_hours', true);
        ?>
        <table class="form-table dbem-metabox-table">
            <tr>
                <th><label for="dbem_survey_enabled"><?php _e('Survey attivo', 'db-event-manager'); ?></label></th>
                <td>
                    <label><input type="checkbox" id="dbem_survey_enabled" name="_dbem_survey_enabled" value="1" <?php checked($survey_enabled, '1'); ?>> <?php _e('Sì', 'db-event-manager'); ?></label>
                </td>
            </tr>
            <tr>
                <th><label for="dbem_survey_auto_hours"><?php _e('Invio automatico', 'db-event-manager'); ?></label></th>
                <td>
                    <input type="number" id="dbem_survey_auto_hours" name="_dbem_survey_auto_hours" value="<?php echo esc_attr($survey_auto); ?>" class="small-text" min="0">
                    <span><?php _e('ore dopo la fine evento (0 = invio manuale)', 'db-event-manager'); ?></span>
                </td>
            </tr>
        </table>

        <h4><?php _e('Campi del survey', 'db-event-manager'); ?></h4>
        <div id="dbem-survey-fields" data-fields="<?php echo esc_attr(wp_json_encode($survey_fields)); ?>">
            <div id="dbem-survey-fields-list"></div>
            <button type="button" class="button" id="dbem-add-survey-field">
                <span class="dashicons dashicons-plus-alt2" style="vertical-align:middle;"></span>
                <?php _e('Aggiungi campo survey', 'db-event-manager'); ?>
            </button>
        </div>
        <input type="hidden" name="_dbem_survey_fields" id="dbem_survey_fields_json" value="<?php echo esc_attr(wp_json_encode($survey_fields)); ?>">

        <h4><?php _e('Email survey', 'db-event-manager'); ?></h4>
        <table class="form-table dbem-metabox-table">
            <tr>
                <th><label for="dbem_survey_email_subject"><?php _e('Oggetto', 'db-event-manager'); ?></label></th>
                <td><input type="text" id="dbem_survey_email_subject" name="_dbem_survey_email[subject]" value="<?php echo esc_attr($survey_email['subject']); ?>" class="large-text"></td>
            </tr>
            <tr>
                <th><label for="dbem_survey_email_message"><?php _e('Messaggio', 'db-event-manager'); ?></label></th>
                <td>
                    <textarea id="dbem_survey_email_message" name="_dbem_survey_email[message]" rows="6" class="large-text"><?php echo esc_textarea($survey_email['message']); ?></textarea>
                    <p class="description"><?php _e('Placeholder: {nome}, {email}, {evento}, {survey_link}, {sito}', 'db-event-manager'); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }

    public static function render_stats_metabox($post) {
        $count = DBEM_DB::count_registrations($post->ID);
        $max = (int) get_post_meta($post->ID, '_dbem_max_participants', true);
        $checked_in = DBEM_DB::count_registrations($post->ID, 'checked_in');
        $cancelled = DBEM_DB::count_registrations($post->ID, 'cancelled');
        $status = DBEM_CPT::get_event_status($post->ID);

        $status_labels = array(
            'draft'    => __('Bozza', 'db-event-manager'),
            'upcoming' => __('In programma', 'db-event-manager'),
            'ongoing'  => __('In corso', 'db-event-manager'),
            'past'     => __('Concluso', 'db-event-manager'),
        );
        ?>
        <div class="dbem-stats-box">
            <p><strong><?php _e('Stato:', 'db-event-manager'); ?></strong> <?php echo esc_html($status_labels[$status] ?? $status); ?></p>
            <p><strong><?php _e('Iscritti:', 'db-event-manager'); ?></strong> <?php echo esc_html($count); ?><?php if ($max > 0) echo ' / ' . esc_html($max); ?></p>
            <?php if ($max > 0): ?>
            <div class="dbem-progress-bar">
                <div class="dbem-progress-fill" style="width: <?php echo esc_attr(min(100, ($count / $max) * 100)); ?>%"></div>
            </div>
            <?php endif; ?>
            <p><strong><?php _e('Check-in:', 'db-event-manager'); ?></strong> <?php echo esc_html($checked_in); ?></p>
            <p><strong><?php _e('Annullati:', 'db-event-manager'); ?></strong> <?php echo esc_html($cancelled); ?></p>

            <hr>
            <p>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=dbem_event&page=dbem-participants&event_id=' . $post->ID)); ?>" class="button">
                    <?php _e('Gestisci partecipanti', 'db-event-manager'); ?>
                </a>
            </p>
            <p>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=dbem_event&page=dbem-checkin&event_id=' . $post->ID)); ?>" class="button">
                    <?php _e('Pagina check-in', 'db-event-manager'); ?>
                </a>
            </p>
            <p><strong><?php _e('Shortcode:', 'db-event-manager'); ?></strong><br><code>[dbem_event id="<?php echo esc_html($post->ID); ?>"]</code></p>

            <hr>
            <p><strong><?php _e('Link diretti:', 'db-event-manager'); ?></strong></p>
            <p>
                <a href="<?php echo esc_url(get_permalink($post->ID)); ?>" target="_blank" class="button button-primary" style="width:100%;text-align:center;">
                    🔗 <?php _e('Vedi evento', 'db-event-manager'); ?> <span class="screen-reader-text"><?php _e('(si apre in una nuova finestra)', 'db-event-manager'); ?></span>
                </a>
            </p>
            <p>
                <a href="<?php echo esc_url(get_post_type_archive_link('dbem_event')); ?>" target="_blank" class="button" style="width:100%;text-align:center;">
                    📋 <?php _e('Lista eventi', 'db-event-manager'); ?> <span class="screen-reader-text"><?php _e('(si apre in una nuova finestra)', 'db-event-manager'); ?></span>
                </a>
            </p>
            <p>
                <a href="<?php echo esc_url(home_url('/?dbem_checkin_page=1')); ?>" target="_blank" class="button" style="width:100%;text-align:center;">
                    📱 <?php _e('Check-in da telefono', 'db-event-manager'); ?> <span class="screen-reader-text"><?php _e('(si apre in una nuova finestra)', 'db-event-manager'); ?></span>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Salva metabox
     */
    public static function save_metabox($post_id, $post) {
        if (!isset($_POST['dbem_event_nonce']) || !wp_verify_nonce($_POST['dbem_event_nonce'], 'dbem_save_event')) return;
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('manage_options', $post_id)) return;

        // Nome evento e descrizione
        if (isset($_POST['_dbem_event_name'])) {
            $event_name = sanitize_text_field($_POST['_dbem_event_name']);
            update_post_meta($post_id, '_dbem_event_name', $event_name);

            // Auto-genera titolo WP dal nome evento (per lista admin leggibile)
            if ($event_name && $post->post_title !== $event_name) {
                remove_action('save_post_dbem_event', array(__CLASS__, 'save_metabox'), 10);
                wp_update_post(array('ID' => $post_id, 'post_title' => $event_name));
                add_action('save_post_dbem_event', array(__CLASS__, 'save_metabox'), 10, 2);
            }
        }

        // Dettagli
        $text_fields = array('_dbem_date_start', '_dbem_date_end', '_dbem_location');
        foreach ($text_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }

        $int_fields = array('_dbem_max_participants');
        foreach ($int_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, absint($_POST[$field]));
            }
        }

        // Checkbox
        update_post_meta($post_id, '_dbem_registration_open', isset($_POST['_dbem_registration_open']) ? '1' : '0');
        update_post_meta($post_id, '_dbem_survey_enabled', isset($_POST['_dbem_survey_enabled']) ? '1' : '0');
        update_post_meta($post_id, '_dbem_notify_admin', isset($_POST['_dbem_notify_admin']) ? '1' : '0');

        // Email admin personalizzata
        if (isset($_POST['_dbem_admin_email'])) {
            $emails_raw = sanitize_text_field($_POST['_dbem_admin_email']);
            $emails = array_map('trim', explode(',', $emails_raw));
            $emails = array_filter($emails, 'is_email');
            update_post_meta($post_id, '_dbem_admin_email', implode(', ', $emails));
        }

        // Form source (builtin / dbfb)
        if (isset($_POST['_dbem_form_source'])) {
            update_post_meta($post_id, '_dbem_form_source', sanitize_key($_POST['_dbem_form_source']));
        }

        // Modalità approvazione
        if (isset($_POST['_dbem_approval_mode'])) {
            update_post_meta($post_id, '_dbem_approval_mode', sanitize_key($_POST['_dbem_approval_mode']));
        }
        if (isset($_POST['_dbem_approver_email'])) {
            $emails_raw = sanitize_text_field($_POST['_dbem_approver_email']);
            $emails = array_map('trim', explode(',', $emails_raw));
            $emails = array_filter($emails, 'is_email');
            update_post_meta($post_id, '_dbem_approver_email', implode(', ', $emails));
        }
        if (isset($_POST['_dbem_dbfb_form_id'])) {
            update_post_meta($post_id, '_dbem_dbfb_form_id', absint($_POST['_dbem_dbfb_form_id']));
        }
        if (isset($_POST['_dbem_dbfb_name_field'])) {
            update_post_meta($post_id, '_dbem_dbfb_name_field', sanitize_key($_POST['_dbem_dbfb_name_field']));
        }
        if (isset($_POST['_dbem_dbfb_email_field'])) {
            update_post_meta($post_id, '_dbem_dbfb_email_field', sanitize_key($_POST['_dbem_dbfb_email_field']));
        }

        // Deadline
        if (isset($_POST['_dbem_registration_deadline'])) {
            update_post_meta($post_id, '_dbem_registration_deadline', sanitize_text_field($_POST['_dbem_registration_deadline']));
        }

        // Campi custom (JSON)
        if (isset($_POST['_dbem_custom_fields'])) {
            $fields = json_decode(stripslashes($_POST['_dbem_custom_fields']), true);
            if (is_array($fields)) {
                $fields = self::sanitize_fields_array($fields);
                update_post_meta($post_id, '_dbem_custom_fields', $fields);
            }
        }

        // Email conferma
        if (isset($_POST['_dbem_confirmation_email'])) {
            $email_data = array(
                'subject' => sanitize_text_field($_POST['_dbem_confirmation_email']['subject'] ?? ''),
                'message' => wp_kses_post($_POST['_dbem_confirmation_email']['message'] ?? ''),
            );
            update_post_meta($post_id, '_dbem_confirmation_email', $email_data);
        }

        // Survey
        if (isset($_POST['_dbem_survey_fields'])) {
            $fields = json_decode(stripslashes($_POST['_dbem_survey_fields']), true);
            if (is_array($fields)) {
                $fields = self::sanitize_fields_array($fields);
                update_post_meta($post_id, '_dbem_survey_fields', $fields);
            }
        }

        if (isset($_POST['_dbem_survey_email'])) {
            $email_data = array(
                'subject' => sanitize_text_field($_POST['_dbem_survey_email']['subject'] ?? ''),
                'message' => wp_kses_post($_POST['_dbem_survey_email']['message'] ?? ''),
            );
            update_post_meta($post_id, '_dbem_survey_email', $email_data);
        }

        if (isset($_POST['_dbem_survey_auto_hours'])) {
            update_post_meta($post_id, '_dbem_survey_auto_hours', absint($_POST['_dbem_survey_auto_hours']));
        }

        // Schedule survey automatico
        $survey_auto = absint(get_post_meta($post_id, '_dbem_survey_auto_hours', true));
        $end = get_post_meta($post_id, '_dbem_date_end', true);
        if ($survey_auto > 0 && $end) {
            $send_time = strtotime($end) + ($survey_auto * 3600);
            wp_clear_scheduled_hook('dbem_send_survey_auto', array($post_id));
            if ($send_time > time()) {
                wp_schedule_single_event($send_time, 'dbem_send_survey_auto', array($post_id));
            }
        }
    }

    private static function sanitize_fields_array($fields) {
        $clean = array();
        foreach ($fields as $field) {
            $clean[] = array(
                'type'     => sanitize_key($field['type'] ?? 'text'),
                'label'    => sanitize_text_field($field['label'] ?? ''),
                'required' => !empty($field['required']),
                'options'  => isset($field['options']) ? array_map('sanitize_text_field', (array)$field['options']) : array(),
                'placeholder' => sanitize_text_field($field['placeholder'] ?? ''),
            );
        }
        return $clean;
    }

    /**
     * Pagina impostazioni
     */
    public static function render_settings_page() {
        if (!current_user_can('manage_options')) wp_die(__('Accesso negato', 'db-event-manager'));

        // Salvataggio
        if (isset($_POST['dbem_settings_nonce']) && wp_verify_nonce($_POST['dbem_settings_nonce'], 'dbem_save_settings')) {
            update_option('dbem_events_page_id', absint($_POST['dbem_events_page_id'] ?? 0));
            update_option('dbem_events_page_title', sanitize_text_field($_POST['dbem_events_page_title'] ?? __('Eventi', 'db-event-manager')));
            update_option('dbem_checkin_pin', sanitize_text_field($_POST['dbem_checkin_pin'] ?? ''));
            echo '<div class="notice notice-success"><p>' . esc_html__('Impostazioni salvate.', 'db-event-manager') . '</p></div>';
            flush_rewrite_rules();
        }

        $events_page_id = get_option('dbem_events_page_id', 0);
        $events_page_title = get_option('dbem_events_page_title', __('Eventi', 'db-event-manager'));
        $checkin_pin = get_option('dbem_checkin_pin', '');
        $checkin_url = home_url('/?dbem_checkin_page=1');
        $archive_url = get_post_type_archive_link('dbem_event');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Impostazioni DB Event Manager', 'db-event-manager'); ?></h1>

            <form method="post">
                <?php wp_nonce_field('dbem_save_settings', 'dbem_settings_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th><label for="dbem_events_page_id"><?php _e('Pagina elenco eventi', 'db-event-manager'); ?></label></th>
                        <td>
                            <?php
                            wp_dropdown_pages(array(
                                'name'             => 'dbem_events_page_id',
                                'id'               => 'dbem_events_page_id',
                                'selected'         => $events_page_id,
                                'show_option_none' => __('— Usa archivio automatico (/eventi/) —', 'db-event-manager'),
                                'option_none_value' => 0,
                            ));
                            ?>
                            <p class="description">
                                <?php _e('Seleziona una pagina che contiene lo shortcode <code>[dbem_events]</code>, oppure lascia "archivio automatico" per usare la pagina generata dal plugin.', 'db-event-manager'); ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="dbem_events_page_title"><?php _e('Titolo pagina archivio', 'db-event-manager'); ?></label></th>
                        <td>
                            <input type="text" id="dbem_events_page_title" name="dbem_events_page_title" value="<?php echo esc_attr($events_page_title); ?>" class="regular-text">
                            <p class="description"><?php _e('Titolo mostrato nella pagina archivio automatica.', 'db-event-manager'); ?></p>
                        </td>
                    </tr>
                </table>

                <hr>

                <h2><?php esc_html_e('Check-in', 'db-event-manager'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><label for="dbem_checkin_pin"><?php _e('PIN accesso check-in', 'db-event-manager'); ?></label></th>
                        <td>
                            <input type="text" id="dbem_checkin_pin" name="dbem_checkin_pin" value="<?php echo esc_attr($checkin_pin); ?>" class="regular-text" placeholder="<?php esc_attr_e('Es. 1234', 'db-event-manager'); ?>">
                            <p class="description"><?php _e('PIN richiesto per accedere alla pagina check-in da telefono. Se vuoto, la pagina è accessibile senza PIN.', 'db-event-manager'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Link check-in', 'db-event-manager'); ?></th>
                        <td>
                            <code><?php echo esc_html($checkin_url); ?></code>
                            <p class="description"><?php _e('Apri questo link sul telefono per scansionare i QR code all\'ingresso. Non serve login WordPress.', 'db-event-manager'); ?></p>
                        </td>
                    </tr>
                </table>

                <hr>

                <h2><?php esc_html_e('Link utili', 'db-event-manager'); ?></h2>
                <table class="form-table">
                    <tr>
                        <th><?php _e('Pagina archivio eventi', 'db-event-manager'); ?></th>
                        <td>
                            <?php if ($events_page_id): ?>
                                <a href="<?php echo esc_url(get_permalink($events_page_id)); ?>" target="_blank"><?php echo esc_html(get_the_title($events_page_id)); ?> ↗</a>
                            <?php else: ?>
                                <a href="<?php echo esc_url($archive_url); ?>" target="_blank"><?php echo esc_html($archive_url); ?> ↗</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><?php _e('Shortcode disponibili', 'db-event-manager'); ?></th>
                        <td>
                            <code>[dbem_events]</code> — <?php _e('Lista eventi futuri', 'db-event-manager'); ?><br>
                            <code>[dbem_events past="1"]</code> — <?php _e('Lista eventi passati', 'db-event-manager'); ?><br>
                            <code>[dbem_events limit="5"]</code> — <?php _e('Limita il numero', 'db-event-manager'); ?><br>
                            <code>[dbem_events cols="2"]</code> — <?php _e('Layout a 2 colonne', 'db-event-manager'); ?><br>
                            <code>[dbem_events category="workshop"]</code> — <?php _e('Filtra per categoria (slug)', 'db-event-manager'); ?><br>
                            <code>[dbem_events category="workshop,seminario"]</code> — <?php _e('Più categorie separate da virgola', 'db-event-manager'); ?><br>
                            <code>[dbem_event id="X"]</code> — <?php _e('Evento singolo con form iscrizione', 'db-event-manager'); ?>
                        </td>
                    </tr>
                </table>

                <?php submit_button(__('Salva impostazioni', 'db-event-manager')); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Pagina partecipanti
     */
    public static function render_participants_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Accesso negato', 'db-event-manager'));
        }
        include DBEM_PLUGIN_DIR . 'templates/admin/participants.php';
    }

    /**
     * Bulk action (cambia stato, elimina)
     */
    public static function handle_bulk_action() {
        check_ajax_referer('dbem_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(__('Accesso negato', 'db-event-manager'));

        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $action = sanitize_key($_POST['bulk_action'] ?? '');
        $ids = array_map('absint', (array)($_POST['ids'] ?? array()));

        if (empty($ids) || !$action) wp_send_json_error(__('Parametri mancanti', 'db-event-manager'));

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));

        switch ($action) {
            case 'confirm':
                $wpdb->query($wpdb->prepare("UPDATE $table SET status = 'confirmed' WHERE id IN ($placeholders)", ...$ids));
                // Se erano pending, genera QR e invia conferma
                foreach ($ids as $rid) {
                    $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $rid));
                    if ($r && $r->status === 'confirmed') {
                        DBEM_QRCode::generate($r->token);
                        DBEM_Email::send_confirmation($r->event_id, $r);
                    }
                }
                break;
            case 'cancel':
                $wpdb->query($wpdb->prepare("UPDATE $table SET status = 'cancelled' WHERE id IN ($placeholders)", ...$ids));
                break;
            case 'reject':
                $wpdb->query($wpdb->prepare("UPDATE $table SET status = 'rejected' WHERE id IN ($placeholders)", ...$ids));
                foreach ($ids as $rid) {
                    $r = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $rid));
                    if ($r) DBEM_Email::send_rejection($r->event_id, $r);
                }
                break;
            case 'checkin':
                $wpdb->query($wpdb->prepare("UPDATE $table SET status = 'checked_in', checked_in_at = %s WHERE id IN ($placeholders)", current_time('mysql'), ...$ids));
                break;
            case 'delete':
                $wpdb->query($wpdb->prepare("DELETE FROM $table WHERE id IN ($placeholders)", ...$ids));
                break;
            default:
                wp_send_json_error(__('Azione non valida', 'db-event-manager'));
        }

        wp_send_json_success(array('message' => __('Operazione completata', 'db-event-manager')));
    }

    /**
     * Reinvia email conferma
     */
    public static function handle_resend_email() {
        check_ajax_referer('dbem_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(__('Accesso negato', 'db-event-manager'));

        $reg_id = absint($_POST['registration_id'] ?? 0);
        if (!$reg_id) wp_send_json_error(__('ID mancante', 'db-event-manager'));

        global $wpdb;
        $table = $wpdb->prefix . 'dbem_registrations';
        $reg = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $reg_id));
        if (!$reg) wp_send_json_error(__('Iscrizione non trovata', 'db-event-manager'));

        $sent = DBEM_Email::send_confirmation($reg->event_id, $reg);
        if ($sent) {
            wp_send_json_success(array('message' => __('Email inviata', 'db-event-manager')));
        } else {
            wp_send_json_error(__('Errore invio email', 'db-event-manager'));
        }
    }
}

// Registra metabox (hook separato perché serve add_meta_boxes)
add_action('add_meta_boxes', array('DBEM_Admin', 'register_metaboxes'));
