// @ts-check
const { defineConfig, devices } = require( '@playwright/test' );

/**
 * Config Playwright per gli E2E di DB Event Manager.
 *
 * baseURL punta all'ambiente "development" di wp-env (porta 8888), quello su
 * cui opera `wp-env run cli` (bin/setup-e2e.sh).
 */
module.exports = defineConfig( {
	testDir: './tests/e2e',
	fullyParallel: false, // i test condividono eventi, tabelle e opzioni: sequenziali.
	forbidOnly: !! process.env.CI,
	retries: process.env.CI ? 1 : 0,
	workers: 1,
	reporter: process.env.CI ? [ [ 'list' ], [ 'html', { open: 'never' } ] ] : 'list',

	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8888',
		trace: 'on-first-retry',
		screenshot: 'only-on-failure',
	},

	projects: [
		// Login admin (sessione in tests/e2e/.auth/), una volta prima degli spec.
		{
			name: 'setup',
			testMatch: /.*\.setup\.js/,
		},
		{
			name: 'chromium',
			use: { ...devices[ 'Desktop Chrome' ] },
			dependencies: [ 'setup' ],
			testIgnore: /.*\.mobile\.spec\.js/,
		},
		// Pagine pubbliche di check-in e partecipanti: si usano dal telefono.
		{
			name: 'mobile',
			use: { ...devices[ 'Pixel 7' ] },
			dependencies: [ 'setup' ],
			testMatch: /.*\.mobile\.spec\.js/,
		},
	],
} );
