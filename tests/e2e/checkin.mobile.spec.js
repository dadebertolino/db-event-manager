// @ts-check
const { test, expect } = require( '@playwright/test' );
const { PIN, PUBLIC_PAGES, resetState, getState } = require( './helpers' );

/**
 * Check-in all'ingresso da telefono, senza QR: ricerca per nome
 */
test.describe( 'Check-in da telefono', () => {
	test( 'cerca per nome e registra il check-in, una volta', async ( { page, request } ) => {
		await resetState( request, {
			events: [ { key: 'a', title: 'Serata', registrations: [ { name: 'Carla Verdi', email: 'carla@example.com', status: 'confirmed' } ] } ],
		} );

		await page.goto( PUBLIC_PAGES.checkin );
		await page.locator( '#ci-pin-input' ).fill( PIN );
		await page.locator( '#ci-pin-btn' ).click();
		await page.locator( '#ci-search-input' ).fill( 'Verdi' );
		await page.locator( '#ci-search-btn' ).click();

		await page.getByRole( 'button', { name: /Carla Verdi/ } ).click();
		await expect( page.locator( '#ci-feedback' ) ).toContainText( 'Check-in effettuato' );
		expect( ( await getState( request ) ).registrations[ 0 ].status ).toBe( 'checked_in' );

		// Di nuovo: ora è già presente e non è più un pulsante
		await page.locator( '#ci-search-btn' ).click();
		await expect( page.locator( '#ci-search-results' ) ).toContainText( 'Carla Verdi' );
		await expect( page.getByRole( 'button', { name: /Carla Verdi/ } ) ).toHaveCount( 0 );
	} );
} );
