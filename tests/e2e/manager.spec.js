// @ts-check
const { test, expect } = require( '@playwright/test' );
const { resetState } = require( './helpers' );

/**
 * Gestore delegato (Utenti → Gestione eventi): gestisce eventi e partecipanti, non le
 * impostazioni del plugin
 */
test.describe( 'Gestore delegato', () => {
	test.use( { storageState: { cookies: [], origins: [] } } );

	test( 'vede eventi e partecipanti ma non le Impostazioni', async ( { page, request } ) => {
		const { events } = await resetState( request, { manager: true, events: [ { key: 'a', title: 'Evento del gestore' } ] } );

		await page.goto( '/wp-login.php' );
		await page.locator( '#user_login' ).fill( 'gestore' );
		await page.locator( '#user_pass' ).fill( 'password' );
		await page.locator( '#wp-submit' ).click();
		await page.waitForURL( /\/wp-admin\// );

		await page.goto( '/wp-admin/edit.php?post_type=dbem_event' );
		await expect( page.getByRole( 'link', { name: 'Evento del gestore', exact: true } ) ).toBeVisible();

		await page.goto( `/wp-admin/edit.php?post_type=dbem_event&page=dbem-participants&event_id=${ events.a.id }` );
		await expect( page.locator( '#wpbody-content h1' ).first() ).toBeVisible();
		await expect( page.locator( 'body' ) ).not.toContainText( /non hai i permessi|not allowed|Accesso negato/i );

		const settings = await page.goto( '/wp-admin/edit.php?post_type=dbem_event&page=dbem-settings' );
		expect( settings?.status() ).toBe( 403 );
	} );
} );
