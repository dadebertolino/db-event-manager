// @ts-check
const { test, expect } = require( '@playwright/test' );
const { PIN, PUBLIC_PAGES, resetState, getState } = require( './helpers' );

/**
 * Pagina partecipanti da telefono: aggiunta manuale e annullamento con avviso (D6)
 */
async function openParticipants( page ) {
	await page.goto( PUBLIC_PAGES.participants );
	await page.locator( '#pp-pin-input' ).fill( PIN );
	await page.locator( '#pp-pin-btn' ).click();
	await expect( page.locator( '#pp-toolbar' ) ).toBeVisible();
}

test.describe( 'Partecipanti da telefono', () => {
	test( 'aggiunta manuale: iscrizione confermata ed email di conferma', async ( { page, request } ) => {
		await resetState( request, { events: [ { key: 'a' } ] } );
		await openParticipants( page );

		await page.locator( '#pp-add-btn' ).click();
		await page.locator( '#pp-add-name' ).fill( 'Elena Gialli' );
		await page.locator( '#pp-add-email' ).fill( 'Elena@Example.com' );
		await page.locator( '#pp-add-submit' ).click();
		await expect( page.getByText( 'Elena Gialli' ) ).toBeVisible();

		const state = await getState( request );
		expect( state.registrations[ 0 ] ).toMatchObject( { email: 'elena@example.com', status: 'confirmed' } );
		expect( state.mails.map( ( m ) => m.to ) ).toContain( 'elena@example.com' );
	} );

	test( 'annullamento con conferma e avviso via email (D6)', async ( { page, request } ) => {
		await resetState( request, { events: [ { key: 'a', registrations: [ { name: 'Franco Viola', email: 'franco@example.com' } ] } ] } );
		await openParticipants( page );

		page.once( 'dialog', ( d ) => d.accept() );
		await page.getByRole( 'button', { name: 'Annulla iscrizione — Franco Viola' } ).click();
		await expect( page.locator( '#pp-feedback' ) ).toContainText( 'annullato' );

		const state = await getState( request );
		expect( state.registrations[ 0 ].status ).toBe( 'cancelled' );
		expect( state.mails.find( ( m ) => m.to === 'franco@example.com' )?.subject ).toContain( 'Iscrizione annullata' );
	} );
} );
