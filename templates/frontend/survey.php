<?php
if (!defined('ABSPATH')) exit;

$event_title = DBEM_CPT::get_event_name($reg->event_id);
$survey_fields = get_post_meta($reg->event_id, '_dbem_survey_fields', true);
if (!is_array($survey_fields)) $survey_fields = array();
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html(sprintf(__('Survey — %s', 'db-event-manager'), $event_title)); ?></title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f0f0f0; color: #333; line-height: 1.6; }
        .dbem-survey-wrap { max-width: 600px; margin: 40px auto; padding: 0 16px; }
        .dbem-survey-header { background: #2271b1; color: #fff; padding: 24px; border-radius: 12px 12px 0 0; text-align: center; }
        .dbem-survey-header h1 { font-size: 22px; margin-bottom: 4px; }
        .dbem-survey-header p { opacity: 0.85; font-size: 14px; }
        .dbem-survey-body { background: #fff; padding: 24px; border-radius: 0 0 12px 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
        .dbem-survey-info { background: #f7f7f7; padding: 12px 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px; }
        .dbem-survey-info strong { display: block; }
        .dbem-field { margin-bottom: 16px; }
        .dbem-label { display: block; font-weight: 600; margin-bottom: 4px; font-size: 14px; }
        .dbem-required { color: #d63638; }
        .dbem-input, .dbem-textarea, .dbem-select { width: 100%; padding: 10px 12px; border: 1px solid #ccc; border-radius: 6px; font-size: 15px; font-family: inherit; transition: border-color 0.2s; }
        .dbem-input:focus, .dbem-textarea:focus, .dbem-select:focus { border-color: #2271b1; outline: 3px solid rgba(34,113,177,0.3); }
        .dbem-radio-label, .dbem-checkbox-label { display: flex; align-items: center; gap: 8px; padding: 6px 0; cursor: pointer; font-size: 14px; }
        .dbem-radio-label input, .dbem-checkbox-label input { width: 18px; height: 18px; min-width: 18px; }
        fieldset { border: none; padding: 0; }
        legend { font-weight: 600; margin-bottom: 4px; font-size: 14px; }
        .dbem-submit { display: block; width: 100%; padding: 14px; background: #2271b1; color: #fff; border: none; border-radius: 8px; font-size: 16px; font-weight: 600; cursor: pointer; transition: background 0.2s; }
        .dbem-submit:hover { background: #135e96; }
        .dbem-submit:focus-visible { outline: 3px solid #2271b1; outline-offset: 2px; }
        .dbem-error { color: #d63638; font-size: 13px; min-height: 18px; display: block; }
        .dbem-message { padding: 12px; border-radius: 8px; margin-top: 16px; text-align: center; font-weight: 500; }
        .dbem-message-success { background: #e7f5e7; color: #1d6e3f; }
        .dbem-message-error { background: #fce4e4; color: #d63638; }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
        @media (forced-colors: active) { .dbem-submit { border: 2px solid ButtonText; } }
    </style>
</head>
<body>
    <div class="dbem-survey-wrap">
        <div class="dbem-survey-header">
            <h1>📋 <?php echo esc_html($event_title); ?></h1>
            <p><?php esc_html_e('Questionario post-evento', 'db-event-manager'); ?></p>
        </div>
        <div class="dbem-survey-body">
            <div class="dbem-survey-info">
                <strong><?php echo esc_html($reg->name); ?></strong>
                <?php echo esc_html($reg->email); ?>
            </div>

            <form id="dbem-survey-form" method="post" novalidate>
                <input type="hidden" name="action" value="dbem_submit_survey">
                <input type="hidden" name="token" value="<?php echo esc_attr($reg->token); ?>">
                <input type="hidden" name="dbem_survey_nonce" value="<?php echo esc_attr(wp_create_nonce('dbem_survey_submit')); ?>">

                <?php foreach ($survey_fields as $i => $field):
                    $fid = 'dbem_survey_' . $i;
                    $required = !empty($field['required']);
                    $req_attr = $required ? ' required aria-required="true"' : '';
                    $req_star = $required ? ' <span class="dbem-required" aria-hidden="true">*</span>' : '';
                ?>
                <div class="dbem-field">
                    <?php switch ($field['type']):
                        case 'text': case 'email': case 'number': case 'date': ?>
                            <label for="<?php echo esc_attr($fid); ?>" class="dbem-label"><?php echo esc_html($field['label']); ?><?php echo $req_star; ?></label>
                            <input type="<?php echo esc_attr($field['type']); ?>" id="<?php echo esc_attr($fid); ?>" name="<?php echo esc_attr($fid); ?>" class="dbem-input" placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"<?php echo $req_attr; ?>>
                        <?php break;
                        case 'textarea': ?>
                            <label for="<?php echo esc_attr($fid); ?>" class="dbem-label"><?php echo esc_html($field['label']); ?><?php echo $req_star; ?></label>
                            <textarea id="<?php echo esc_attr($fid); ?>" name="<?php echo esc_attr($fid); ?>" class="dbem-textarea" rows="4" placeholder="<?php echo esc_attr($field['placeholder'] ?? ''); ?>"<?php echo $req_attr; ?>></textarea>
                        <?php break;
                        case 'select': ?>
                            <label for="<?php echo esc_attr($fid); ?>" class="dbem-label"><?php echo esc_html($field['label']); ?><?php echo $req_star; ?></label>
                            <select id="<?php echo esc_attr($fid); ?>" name="<?php echo esc_attr($fid); ?>" class="dbem-select"<?php echo $req_attr; ?>>
                                <option value=""><?php esc_html_e('— Seleziona —', 'db-event-manager'); ?></option>
                                <?php foreach (($field['options'] ?? array()) as $opt): ?>
                                    <option value="<?php echo esc_attr($opt); ?>"><?php echo esc_html($opt); ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php break;
                        case 'radio': ?>
                            <fieldset>
                                <legend class="dbem-label"><?php echo esc_html($field['label']); ?><?php echo $req_star; ?></legend>
                                <?php foreach (($field['options'] ?? array()) as $opt): ?>
                                    <label class="dbem-radio-label"><input type="radio" name="<?php echo esc_attr($fid); ?>" value="<?php echo esc_attr($opt); ?>"<?php echo $req_attr; ?>><span><?php echo esc_html($opt); ?></span></label>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php break;
                        case 'checkbox': ?>
                            <fieldset>
                                <legend class="dbem-label"><?php echo esc_html($field['label']); ?><?php echo $req_star; ?></legend>
                                <?php foreach (($field['options'] ?? array()) as $opt): ?>
                                    <label class="dbem-checkbox-label"><input type="checkbox" name="<?php echo esc_attr($fid); ?>[]" value="<?php echo esc_attr($opt); ?>"><span><?php echo esc_html($opt); ?></span></label>
                                <?php endforeach; ?>
                            </fieldset>
                        <?php break;
                    endswitch; ?>
                    <span class="dbem-error" role="alert" aria-live="polite"></span>
                </div>
                <?php endforeach; ?>

                <div class="dbem-field">
                    <button type="submit" class="dbem-submit">
                        <span class="dbem-submit-text"><?php esc_html_e('Invia risposte', 'db-event-manager'); ?></span>
                    </button>
                </div>

                <div class="dbem-message" role="alert" aria-live="polite" style="display:none;"></div>
            </form>
        </div>
    </div>

    <script>
    (function(){
        var form = document.getElementById('dbem-survey-form');
        if (!form) return;
        form.addEventListener('submit', function(e) {
            e.preventDefault();
            var btn = form.querySelector('.dbem-submit');
            var msg = form.querySelector('.dbem-message');
            btn.disabled = true;
            btn.textContent = '⏳ Invio...';
            msg.style.display = 'none';

            var fd = new FormData(form);
            fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                body: fd
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    msg.className = 'dbem-message dbem-message-success';
                    msg.textContent = data.data.message;
                    msg.style.display = 'block';
                    form.reset();
                    btn.style.display = 'none';
                } else {
                    msg.className = 'dbem-message dbem-message-error';
                    msg.textContent = data.data || 'Errore';
                    msg.style.display = 'block';
                    btn.disabled = false;
                    btn.textContent = '<?php echo esc_js(__('Invia risposte', 'db-event-manager')); ?>';
                }
            })
            .catch(function() {
                msg.className = 'dbem-message dbem-message-error';
                msg.textContent = 'Errore di rete.';
                msg.style.display = 'block';
                btn.disabled = false;
                btn.textContent = '<?php echo esc_js(__('Invia risposte', 'db-event-manager')); ?>';
            });
        });
    })();
    </script>
</body>
</html>
