<?php

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

$GLOBALS['__dbem_options'] = array();
$GLOBALS['__dbem_transients'] = array();

$GLOBALS['__dbem_roles'] = array();
$GLOBALS['__dbem_users'] = array();
$GLOBALS['__dbem_user_meta'] = array();
$GLOBALS['__dbem_current_caps'] = array();

if (!class_exists('WP_Role')) {
    class WP_Role {
        public $capabilities = array();
        public $add_cap_calls = 0;

        public function add_cap($cap) {
            $this->add_cap_calls++;
            $this->capabilities[$cap] = true;
        }

        public function remove_cap($cap) {
            unset($this->capabilities[$cap]);
        }
    }
}

if (!class_exists('WP_User')) {
    class WP_User {
        public $ID;
        public $caps = array();
        public $role_caps = array();

        public function __construct($id = 0) {
            $this->ID = (int) $id;
            if (isset($GLOBALS['__dbem_users'][$this->ID])) {
                $existing = $GLOBALS['__dbem_users'][$this->ID];
                $this->caps = &$existing->caps;
                $this->role_caps = &$existing->role_caps;
            }
            $GLOBALS['__dbem_users'][$this->ID] = $this;
        }

        public function add_cap($cap) {
            $this->caps[$cap] = true;
        }

        public function remove_cap($cap) {
            unset($this->caps[$cap]);
        }

        public function has_cap($cap) {
            return !empty($this->caps[$cap]) || !empty($this->role_caps[$cap]);
        }
    }
}

$GLOBALS['__dbem_post_meta'] = array();
$GLOBALS['__dbem_sent_mail'] = array();

if (!function_exists('get_post_meta')) {
    function get_post_meta($post_id, $key = '', $single = false) {
        return $GLOBALS['__dbem_post_meta'][$post_id][$key] ?? '';
    }
}

if (!function_exists('get_the_title')) {
    function get_the_title($post_id = 0) {
        return 'Evento ' . $post_id;
    }
}

if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir() {
        return array('basedir' => sys_get_temp_dir() . '/dbem-test-uploads', 'baseurl' => 'https://example.com/uploads');
    }
}

if (!function_exists('get_bloginfo')) {
    function get_bloginfo($show = '') {
        return 'Sito di prova';
    }
}

if (!function_exists('esc_html')) {
    function esc_html($text) {
        return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('esc_html__')) {
    function esc_html__($text, $domain = null) {
        return esc_html($text);
    }
}

if (!function_exists('esc_url')) {
    function esc_url($url) {
        return (string) $url;
    }
}

if (!function_exists('wp_mail')) {
    function wp_mail($to, $subject, $message, $headers = '', $attachments = array()) {
        $GLOBALS['__dbem_sent_mail'][] = compact('to', 'subject', 'message', 'headers', 'attachments');
        return true;
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10, $accepted_args = 1) {
        return true;
    }
}

if (!function_exists('get_role')) {
    function get_role($role) {
        return $GLOBALS['__dbem_roles'][$role] ?? null;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($cap, ...$args) {
        return in_array($cap, $GLOBALS['__dbem_current_caps'], true);
    }
}

if (!function_exists('get_user_meta')) {
    function get_user_meta($user_id, $key = '', $single = false) {
        return $GLOBALS['__dbem_user_meta'][$user_id][$key] ?? '';
    }
}

if (!function_exists('update_user_meta')) {
    function update_user_meta($user_id, $key, $value) {
        $GLOBALS['__dbem_user_meta'][$user_id][$key] = $value;
        return true;
    }
}

if (!function_exists('delete_user_meta')) {
    function delete_user_meta($user_id, $key) {
        unset($GLOBALS['__dbem_user_meta'][$user_id][$key]);
        return true;
    }
}

if (!function_exists('__')) {
    function __($text, $domain = null) {
        return $text;
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($value) {
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }
        return is_scalar($value) ? trim((string) $value) : '';
    }
}

if (!function_exists('get_option')) {
    function get_option($name, $default = false) {
        return $GLOBALS['__dbem_options'][$name] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($name, $value, $autoload = null) {
        $GLOBALS['__dbem_options'][$name] = $value;
        return true;
    }
}

if (!function_exists('set_transient')) {
    function set_transient($key, $value, $expiration = 0) {
        $GLOBALS['__dbem_transients'][$key] = $value;
        return true;
    }
}

if (!function_exists('get_transient')) {
    function get_transient($key) {
        return $GLOBALS['__dbem_transients'][$key] ?? false;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($key) {
        unset($GLOBALS['__dbem_transients'][$key]);
        return true;
    }
}

if (!function_exists('check_ajax_referer')) {
    function check_ajax_referer($action = -1, $query_arg = false, $die = true) {
        return true;
    }
}

if (!function_exists('wp_send_json_error')) {
    function wp_send_json_error($data = null, $status_code = null) {
        $GLOBALS['__dbem_json_error'] = $data;
        throw new RuntimeException('wp_send_json_error called in test bootstrap');
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null) {
        $GLOBALS['__dbem_json_success'] = $data;
        throw new RuntimeException('wp_send_json_success called in test bootstrap');
    }
}

if (!function_exists('wp_unslash')) {
    function wp_unslash($value) {
        return is_array($value) ? array_map('wp_unslash', $value) : (is_string($value) ? stripslashes($value) : $value);
    }
}

if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field($value) {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}

if (!function_exists('sanitize_email')) {
    function sanitize_email($email) {
        return trim((string) $email);
    }
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

if (!function_exists('wp_generate_password')) {
    function wp_generate_password($length = 12, $special_chars = true) {
        return substr(str_repeat(bin2hex(random_bytes(16)), 2), 0, $length);
    }
}

$GLOBALS['__dbem_cleared_hooks'] = array();

if (!function_exists('wp_clear_scheduled_hook')) {
    function wp_clear_scheduled_hook($hook, $args = array()) {
        $GLOBALS['__dbem_cleared_hooks'][] = array($hook, $args);
        return 0;
    }
}

if (!function_exists('wp_json_encode')) {
    function wp_json_encode($data) {
        return json_encode($data);
    }
}

if (!function_exists('current_time')) {
    function current_time($type = 'mysql') {
        return gmdate('Y-m-d H:i:s');
    }
}

if (!function_exists('is_email')) {
    function is_email($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}

if (!function_exists('absint')) {
    function absint($value) {
        return (int) abs((int) $value);
    }
}

if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce($nonce, $action = -1) {
        return true;
    }
}

if (!function_exists('get_post_type')) {
    function get_post_type($post_id) {
        return 'dbem_event';
    }
}

if (!function_exists('home_url')) {
    function home_url($path = '') {
        return 'https://example.com' . $path;
    }
}

if (!function_exists('wp_salt')) {
    function wp_salt($param = '') {
        return 'test-salt';
    }
}

if (!function_exists('hash_equals')) {
    function hash_equals($known_string, $user_string) {
        return hash_equals($known_string, $user_string);
    }
}

if (!class_exists('wpdb')) {
    class wpdb {
        public $prefix = 'wp_';
        public $insert_id = 0;
        private $tables = array();

        public function __construct() {
            $this->tables[$this->prefix . 'dbem_registrations'] = array(
                array(
                    'id' => 1,
                    'event_id' => 10,
                    'email' => 'alice@example.com',
                    'status' => 'confirmed',
                    'name' => 'Alice',
                    'registered_at' => '2026-01-01 10:00:00',
                ),
                array(
                    'id' => 2,
                    'event_id' => 10,
                    'email' => 'bob@example.com',
                    'status' => 'pending',
                    'name' => 'Bob',
                    'registered_at' => '2026-01-01 10:10:00',
                ),
                array(
                    'id' => 3,
                    'event_id' => 11,
                    'email' => 'alice@example.com',
                    'status' => 'cancelled',
                    'name' => 'Alice other',
                    'registered_at' => '2026-01-02 10:00:00',
                ),
            );
            $this->tables[$this->prefix . 'dbem_survey_responses'] = array();
        }

        public function set_rows($table, $rows) {
            $previous = $this->tables[$table] ?? array();
            $this->tables[$table] = $rows;
            return $previous;
        }

        public function prepare($query, ...$args) {
            $index = 0;
            return preg_replace_callback('/%s|%d/', function ($match) use (&$index, $args) {
                $value = $args[$index++] ?? '';
                if ($match[0] === '%d') {
                    return (string) (int) $value;
                }
                return "'" . str_replace("'", "\\'", (string) $value) . "'";
            }, $query);
        }

        public function get_var($query) {
            if (str_contains($query, "SHOW TABLES LIKE")) {
                return $this->prefix . 'dbem_registrations';
            }
            if (str_contains($query, "SELECT COUNT(*) FROM") && str_contains($query, "event_id = 10 AND status = 'confirmed'")) {
                return 1;
            }
            if (str_contains($query, "SELECT COUNT(*) FROM") && str_contains($query, "email = 'alice@example.com'")) {
                return 2;
            }
            if (str_contains($query, "SELECT COUNT(*) FROM") && str_contains($query, "status != 'cancelled'")) {
                return 1;
            }
            return 0;
        }

        public function get_results($query) {
            $items = $this->tables[$this->prefix . 'dbem_registrations'];
            $filtered = array_values(array_filter($items, function ($row) use ($query) {
                $matches_event = str_contains($query, 'event_id = 10') ? (int) $row['event_id'] === 10 : true;

                if ($matches_event && str_contains($query, "status = 'confirmed'")) {
                    return $row['status'] === 'confirmed';
                }

                if ($matches_event && str_contains($query, "status = 'checked_in'")) {
                    return $row['status'] === 'checked_in';
                }

                if ($matches_event && str_contains($query, "status = 'pending'")) {
                    return $row['status'] === 'pending';
                }

                if ($matches_event && str_contains($query, "status IN ('confirmed', 'checked_in')")) {
                    return in_array($row['status'], array('confirmed', 'checked_in'), true);
                }

                if ($matches_event && str_contains($query, "status != 'cancelled'")) {
                    return $row['status'] !== 'cancelled';
                }

                return $matches_event;
            }));

            return array_map(function ($row) {
                return (object) $row;
            }, $filtered);
        }

        public function get_row($query) {
            if (str_contains($query, "event_id = 10") && str_contains($query, "email = 'alice@example.com'")) {
                return (object) array(
                    'id' => 1,
                    'event_id' => 10,
                    'email' => 'alice@example.com',
                    'name' => 'Alice',
                    'status' => 'confirmed',
                    'token' => 'token-1',
                );
            }

            if (str_contains($query, 'id = 2')) {
                return (object) array(
                    'id' => 2,
                    'event_id' => 10,
                    'email' => 'bob@example.com',
                    'name' => 'Bob',
                    'status' => 'pending',
                    'token' => 'token-2',
                );
            }

            if (str_contains($query, "token = 'token-2'")) {
                return (object) array(
                    'id' => 2,
                    'event_id' => 10,
                    'email' => 'bob@example.com',
                    'name' => 'Bob',
                    'status' => 'pending',
                    'token' => 'token-2',
                );
            }
            return null;
        }

        public $deleted = array();

        public function delete($table, $where, $where_format = null) {
            $this->deleted[] = array($table, $where);
            if (!isset($this->tables[$table])) {
                return 0;
            }
            $before = count($this->tables[$table]);
            $this->tables[$table] = array_values(array_filter($this->tables[$table], function ($row) use ($where) {
                foreach ($where as $key => $value) {
                    if (!isset($row[$key]) || (string) $row[$key] !== (string) $value) return true;
                }
                return false;
            }));
            return $before - count($this->tables[$table]);
        }

        public function get_col($query) {
            if (preg_match('/event_id = (\d+)/', $query, $m)) {
                $ids = array();
                foreach ($this->tables[$this->prefix . 'dbem_registrations'] as $row) {
                    if ((int) $row['event_id'] === (int) $m[1]) $ids[] = $row['id'];
                }
                return $ids;
            }
            return array();
        }

        public function insert($table, $data, $format = null) {
            $this->insert_id = isset($this->tables[$table]) ? count($this->tables[$table]) + 1 : 1;
            $row = $data;
            $row['id'] = $this->insert_id;
            $this->tables[$table][] = $row;
            return true;
        }

        public function update($table, $data, $where, $format = null, $where_format = null) {
            if (!isset($this->tables[$table])) {
                return 0;
            }
            foreach ($this->tables[$table] as &$row) {
                if ((int) $row['id'] === (int) $where['id']) {
                    foreach ($data as $key => $value) {
                        $row[$key] = $value;
                    }
                    return 1;
                }
            }
            return 0;
        }
    }
    $GLOBALS['wpdb'] = new wpdb();
}

require_once dirname(__DIR__) . '/inc/class-security.php';
require_once dirname(__DIR__) . '/inc/class-db.php';
require_once dirname(__DIR__) . '/inc/class-email.php';
require_once dirname(__DIR__) . '/inc/class-cpt.php';
require_once dirname(__DIR__) . '/inc/class-admin.php';
require_once dirname(__DIR__) . '/inc/class-registration.php';
require_once dirname(__DIR__) . '/inc/class-qrcode.php';
require_once dirname(__DIR__) . '/inc/class-checkin.php';
