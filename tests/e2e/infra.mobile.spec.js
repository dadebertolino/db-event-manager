// @ts-check
const { test, expect } = require( '@playwright/test' );
const { PIN, PUBLIC_PAGES, resetState } = require( './helpers' );

/**
 * Smoke test delle pagine pubbliche da telefono (progetto "mobile", Pixel 7).
 */
test.describe( 'Pagine pubbliche da telefono', () => {
	test( 'il PIN di sistema apre la pagina partecipanti con l\'evento pubblicato', async ( { page, request } ) => {
		await resetState( request, { events: [ { key: 'a', title: 'Evento A' } ] } );

		await page.goto( PUBLIC_PAGES.participants );
		await page.locator( '#pp-pin-input' ).fill( PIN );
		await page.locator( '#pp-pin-btn' ).click();

		await expect( page.locator( '#pp-main' ) ).toBeVisible();
		// Un solo evento aperto dal PIN: selezionato direttamente
		await expect( page.locator( '#pp-event-select' ) ).toHaveValue( /\d+/ );
	} );

	test( 'un PIN sbagliato resta sulla schermata di accesso', async ( { page, request } ) => {
		await resetState( request );

		await page.goto( PUBLIC_PAGES.checkin );
		await page.locator( '#ci-pin-input' ).fill( '000000' );
		await page.locator( '#ci-pin-btn' ).click();

		await expect( page.locator( '#ci-pin-error' ) ).toBeVisible();
		await expect( page.locator( '#ci-main' ) ).toBeHidden();
	} );
} );
