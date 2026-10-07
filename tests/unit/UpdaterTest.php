<?php
/**
 * DB_GitHub_Updater 1.1.0: lettura della release da GitHub, scelta dello ZIP,
 * notifica di aggiornamento e riattivazione dopo l'installazione solo se il
 * plugin era attivo. Le chiamate HTTP e le funzioni dei plugin sono simulate.
 * Stessi casi di UpdaterTest in DB Privacy Hub.
 */

use PHPUnit\Framework\TestCase;

if (!defined('WP_PLUGIN_DIR')) {
    define('WP_PLUGIN_DIR', '/var/www/wp-content/plugins');
}

$GLOBALS['__dbem_http'] = null; // Risposta di wp_remote_get, o WP_Error
$GLOBALS['__dbem_http_hits'] = 0;
$GLOBALS['__dbem_activated'] = array();
$GLOBALS['__dbem_active'] = array('site' => false, 'network' => false);
$GLOBALS['__dbem_multisite'] = false;

if (!class_exists('WP_Error')) {
    class WP_Error {
        public $code;
        public function __construct($code = '', $message = '') {
            $this->code = $code;
        }
    }
}
if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}
if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return basename(dirname($file)) . '/' . basename($file);
    }
}
if (!function_exists('untrailingslashit')) {
    function untrailingslashit($value) {
        return rtrim($value, '/\\');
    }
}
if (!function_exists('wp_remote_get')) {
    function wp_remote_get($url, $args = array()) {
        ++$GLOBALS['__dbem_http_hits'];
        return $GLOBALS['__dbem_http'];
    }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response) {
        return is_array($response) ? $response['code'] : '';
    }
}
if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response) {
        return is_array($response) ? $response['body'] : '';
    }
}
if (!function_exists('get_plugin_data')) {
    function get_plugin_data($file) {
        return array('Name' => 'DB Event Manager', 'Version' => '1.8.0');
    }
}
if (!function_exists('is_plugin_active')) {
    function is_plugin_active($plugin) {
        return $GLOBALS['__dbem_active']['site'];
    }
}
if (!function_exists('is_multisite')) {
    function is_multisite() {
        return $GLOBALS['__dbem_multisite'];
    }
}
if (!function_exists('is_plugin_active_for_network')) {
    function is_plugin_active_for_network($plugin) {
        return $GLOBALS['__dbem_active']['network'];
    }
}
if (!function_exists('activate_plugin')) {
    function activate_plugin($plugin, $redirect = '', $network_wide = false) {
        $GLOBALS['__dbem_activated'][] = array($plugin, $network_wide);
        return null;
    }
}

class DBEM_Test_Filesystem {
    public $moves = array();
    public $ok = true;

    public function move($from, $to) {
        $this->moves[] = array($from, $to);
        return $this->ok;
    }
}

final class UpdaterTest extends TestCase {

    const FILE = '/var/www/wp-content/plugins/db-event-manager/db-event-manager.php';
    const BASENAME = 'db-event-manager/db-event-manager.php';

    protected function setUp(): void {
        $GLOBALS['__dbem_transients'] = array();
        $GLOBALS['__dbem_http'] = null;
        $GLOBALS['__dbem_http_hits'] = 0;
        $GLOBALS['__dbem_activated'] = array();
        $GLOBALS['__dbem_active'] = array('site' => false, 'network' => false);
        $GLOBALS['__dbem_multisite'] = false;
        $GLOBALS['wp_filesystem'] = new DBEM_Test_Filesystem();
    }

    protected function tearDown(): void {
        unset($GLOBALS['wp_filesystem']);
    }

    private function updater() {
        return new DB_GitHub_Updater(self::FILE, 'dadebertolino', 'db-event-manager');
    }

    private function github(array $release, $code = 200) {
        $GLOBALS['__dbem_http'] = array('code' => $code, 'body' => json_encode($release));
    }

    private function check($updater, $installed = '1.8.0') {
        $transient = new stdClass();
        $transient->checked = array(self::BASENAME => $installed);
        return $updater->check_update($transient);
    }

    private function updates_to($transient) {
        return $transient->response[self::BASENAME] ?? null;
    }

    /* --- Release e ZIP --- */

    public function testNewVersionUsesZipAsset(): void {
        $this->github(array(
            'tag_name'    => 'v1.9.0',
            'zipball_url' => 'https://api.github.com/zipball',
            'assets'      => array(
                array('name' => 'note.txt', 'browser_download_url' => 'https://x/note.txt'),
                array('name' => 'db-event-manager-1.9.0.zip', 'browser_download_url' => 'https://x/db-event-manager-1.9.0.zip'),
            ),
        ));

        $update = $this->updates_to($this->check($this->updater()));

        $this->assertSame('1.9.0', $update->new_version);
        $this->assertSame('https://x/db-event-manager-1.9.0.zip', $update->package);
        $this->assertSame('db-event-manager', $update->slug);
    }

    public function testWithoutAssetsFallsBackToZipball(): void {
        $this->github(array('tag_name' => '1.9.0', 'zipball_url' => 'https://api.github.com/zipball'));

        $this->assertSame('https://api.github.com/zipball', $this->updates_to($this->check($this->updater()))->package);
    }

    public function testNoZipAndNoZipballMeansNoUpdate(): void {
        $this->github(array(
            'tag_name' => 'v1.9.0',
            'assets'   => array(array('name' => 'rotto.zip'), 'non oggetto'),
        ));

        $this->assertNull($this->updates_to($this->check($this->updater())));
    }

    public function testSameOrOlderVersionMeansNoUpdate(): void {
        $this->github(array('tag_name' => 'v1.8.0', 'zipball_url' => 'https://z'));

        $this->assertNull($this->updates_to($this->check($this->updater())));
        $this->assertNull($this->updates_to($this->check($this->updater(), '2.0.0')));
    }

    public function testHttpErrorIsCached(): void {
        $GLOBALS['__dbem_http'] = new WP_Error('http');
        $updater = $this->updater();

        $this->assertNull($this->updates_to($this->check($updater)));
        $this->assertNull($this->updates_to($this->check($updater)));
        $this->assertSame(1, $GLOBALS['__dbem_http_hits']);
    }

    public function testResponseWithoutTag(): void {
        $this->github(array('message' => 'Not Found'));

        $this->assertNull($this->updates_to($this->check($this->updater())));
    }

    public function testTransientWithoutCheckedIsUntouched(): void {
        $transient = new stdClass();

        $this->assertSame($transient, $this->updater()->check_update($transient));
        $this->assertSame(0, $GLOBALS['__dbem_http_hits']);
    }

    /* --- Installazione --- */

    private function install($updater, $destination = '/var/www/wp-content/plugins/dadebertolino-db-event-manager-abc123/') {
        $extra = array('plugin' => self::BASENAME);
        $updater->pre_install(true, $extra);
        return $updater->post_install(true, $extra, array('destination' => $destination));
    }

    public function testInactivePluginStaysInactive(): void {
        $result = $this->install($this->updater());

        $this->assertSame(array(), $GLOBALS['__dbem_activated']);
        $this->assertSame(WP_PLUGIN_DIR . '/db-event-manager', $result['destination']);
    }

    public function testActivePluginIsReactivated(): void {
        $GLOBALS['__dbem_active']['site'] = true;

        $this->install($this->updater());

        $this->assertSame(array(array(self::BASENAME, false)), $GLOBALS['__dbem_activated']);
    }

    public function testNetworkActivePluginIsReactivatedNetworkWide(): void {
        $GLOBALS['__dbem_multisite'] = true;
        $GLOBALS['__dbem_active']['network'] = true;

        $this->install($this->updater());

        $this->assertSame(array(array(self::BASENAME, true)), $GLOBALS['__dbem_activated']);
    }

    public function testCorrectFolderIsNotMoved(): void {
        $this->install($this->updater(), WP_PLUGIN_DIR . '/db-event-manager/');

        $this->assertSame(array(), $GLOBALS['wp_filesystem']->moves);
    }

    public function testFailedMoveOrMissingFilesystemKeepsDestination(): void {
        $GLOBALS['wp_filesystem']->ok = false;
        $this->assertStringContainsString('abc123', $this->install($this->updater())['destination']);

        $GLOBALS['wp_filesystem'] = null;
        $this->assertStringContainsString('abc123', $this->install($this->updater())['destination']);
    }

    public function testOtherPluginsAreIgnored(): void {
        $updater = $this->updater();
        $extra = array('plugin' => 'altro/altro.php');
        $updater->pre_install(true, $extra);
        $result = $updater->post_install(true, $extra, array('destination' => '/tmp/x'));

        $this->assertSame(array('destination' => '/tmp/x'), $result);
        $this->assertSame(array(), $GLOBALS['__dbem_activated']);
    }
}
