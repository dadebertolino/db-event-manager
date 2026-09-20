<?php

if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . '/');
}

$GLOBALS['__dbem_options'] = array();
$GLOBALS['__dbem_transients'] = array();

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
        throw new RuntimeException('wp_send_json_error called in test bootstrap');
    }
}

if (!function_exists('wp_send_json_success')) {
    function wp_send_json_success($data = null, $status_code = null) {
        throw new RuntimeException('wp_send_json_success called in test bootstrap');
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
