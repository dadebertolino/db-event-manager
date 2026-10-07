<?php
/**
 * Bootstrap dei test di INTEGRAZIONE.
 *
 * A differenza degli unit test (tests/unit, WordPress simulato), questi girano
 * contro un WordPress vero con MySQL, tramite la WordPress test suite
 * (WP_UnitTestCase). Servono per ciò che dipende dal core e dal database:
 * tabelle e query delle iscrizioni, cron, attivazione, DSAR, disinstallazione.
 *
 * Il percorso della test suite arriva da WP_TESTS_DIR (bin/install-wp-tests.sh,
 * eseguito dal job CI di integrazione).
 */

$_tests_dir = getenv('WP_TESTS_DIR');
if (!$_tests_dir) {
    $_tests_dir = rtrim(sys_get_temp_dir(), '/\\') . '/wordpress-tests-lib';
}

$_phpunit_polyfills = getenv('WP_TESTS_PHPUNIT_POLYFILLS_PATH');
if (false !== $_phpunit_polyfills) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills);
} elseif (!defined('WP_TESTS_PHPUNIT_POLYFILLS_PATH')) {
    define('WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname(__DIR__, 2) . '/vendor/yoast/phpunit-polyfills');
}

if (!file_exists("{$_tests_dir}/includes/functions.php")) {
    echo "Impossibile trovare {$_tests_dir}/includes/functions.php" . PHP_EOL;
    echo 'Esegui prima bin/install-wp-tests.sh.' . PHP_EOL;
    exit(1);
}

require_once "{$_tests_dir}/includes/functions.php";

/**
 * Carica il plugin come farebbe WordPress. Le tabelle si creano subito dopo
 * plugins_loaded, fuori dalla transazione dei test: dentro un test la suite
 * trasforma CREATE TABLE in CREATE TEMPORARY TABLE, che SHOW TABLES non vede.
 */
function _dbem_manually_load_plugin() {
    require dirname(__DIR__, 2) . '/db-event-manager.php';
}
tests_add_filter('muplugins_loaded', '_dbem_manually_load_plugin');

tests_add_filter('plugins_loaded', function () {
    DBEM_DB::create_tables();
    DBEM_DB::maybe_upgrade();
}, 20);

require "{$_tests_dir}/includes/bootstrap.php";
