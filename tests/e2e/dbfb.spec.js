// @ts-check
const { test, expect } = require( '@playwright/test' );
const { resetState, getState } = require( './helpers' );

/**
 * Iscrizione con un form DB Form Builder collegato all'evento (DBFB attivo in wp-env).
 * DBFB tratta come bot un invio entro 3 secondi dal caricamento: si aspetta prima di inviare.
 */
test.describe( 'DB Form Builder', () => {
	test( 'l\'invio riuscito del form iscrive all\'evento', async ( { page, request } ) => {
		const { events } = await resetState( request, { events: [ { key: 'a', title: 'Evento DBFB', dbfb: true } ] } );

		await page.goto( events.a.url );
		const form = page.locator( '.dbem-dbfb-wrap .dbfb-form' );
		await form.locator( '[name="nome"]' ).fill( 'Dario Neri' );
		await form.locator( '[name="email"]' ).fill( 'dario@example.com' );
		await form.locator( '[name="telefono"]' ).fill( '012345' );
		await page.waitForTimeout( 3500 );
		await form.locator( 'button[type="submit"]' ).click();

		await expect( page.locator( '.dbem-dbfb-message' ) ).toContainText( 'Richiesta ricevuta' );
		const state = await getState( request );
		expect( state.registrations ).toHaveLength( 1 );
		expect( state.registrations[ 0 ] ).toMatchObject( { name: 'Dario Neri', email: 'dario@example.com' } );
		expect( JSON.parse( state.registrations[ 0 ].data ).telefono ).toBe( '012345' );
	} );

	test( 'un errore di DBFB non iscrive all\'evento (bug #22)', async ( { page, request } ) => {
		const { events } = await resetState( request, { events: [ { key: 'a', dbfb: true } ] } );

		await page.goto( events.a.url );
		const form = page.locator( '.dbem-dbfb-wrap .dbfb-form' );
		// Email non valida: DBFB risponde con un errore nella sua zona messaggi
		await form.locator( '[name="nome"]' ).fill( 'Dario Neri' );
		await form.locator( '[name="email"]' ).fill( 'non-una-email' );
		await page.waitForTimeout( 3500 );
		await form.locator( 'button[type="submit"]' ).click();

		await expect( form.locator( '.dbfb-messages-region, .dbfb-error, [aria-invalid="true"]' ).first() ).toBeVisible();
		await page.waitForTimeout( 1000 );
		expect( ( await getState( request ) ).registrations ).toHaveLength( 0 );
		await expect( page.locator( '.dbem-dbfb-message' ) ).toBeHidden();
	} );
} );
