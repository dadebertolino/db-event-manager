// @ts-check
const fs = require( 'fs' );
const path = require( 'path' );
const { test: setup, expect } = require( '@playwright/test' );
const { ADMIN_STATE, resetState } = require( './helpers' );

/**
 * Progetto "setup": gira una volta prima degli spec. Porta il plugin allo stato
 * baseline e salva la sessione admin in ADMIN_STATE.
 * Credenziali di default di wp-env, sovrascrivibili via env.
 */
setup( 'baseline e login admin', async ( { page, request } ) => {
	await resetState( request );

	await page.goto( '/wp-login.php' );
	await page.locator( '#user_login' ).fill( process.env.WP_ADMIN_USER || 'admin' );
	await page.locator( '#user_pass' ).fill( process.env.WP_ADMIN_PASS || 'password' );
	await page.locator( '#wp-submit' ).click();
	await page.waitForURL( /\/wp-admin\// );
	const cookies = await page.context().cookies();
	expect( cookies.some( ( c ) => c.name.startsWith( 'wordpress_logged_in_' ) ) ).toBe( true );

	fs.mkdirSync( path.dirname( ADMIN_STATE ), { recursive: true } );
	await page.context().storageState( { path: ADMIN_STATE } );
} );
