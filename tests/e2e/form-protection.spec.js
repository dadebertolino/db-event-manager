// @ts-check
const { test, expect } = require( '@playwright/test' );
const { resetState, getState, submitRegistration, registerViaAjax } = require( './helpers' );

/**
 * Protezioni del modulo pubblico, compatibili con la cache di pagina (1.8.0):
 * niente nonce per gli anonimi, origine dello stesso sito, honeypot, limite per IP
 */
test.describe( 'Protezioni del modulo', () => {
	test( 'la pagina vista da un anonimo non contiene un nonce che possa scadere in cache', async ( { page, request } ) => {
		const { events } = await resetState( request, { events: [ { key: 'a' } ] } );

		await page.goto( events.a.url );
		await expect( page.locator( '.dbem-form input[name="dbem_nonce"]' ) ).toHaveValue( '' );
	} );

	test( 'senza Origin né Referer dello stesso sito la richiesta è rifiutata (403)', async ( { request } ) => {
		const { events } = await resetState( request, { events: [ { key: 'a' } ] } );

		const noOrigin = await request.post( '/wp-admin/admin-ajax.php', {
			form: { action: 'dbem_register', event_id: events.a.id, dbem_name: 'Anna', dbem_email: 'anna@example.com' },
		} );
		const otherSite = await request.post( '/wp-admin/admin-ajax.php', {
			headers: { Origin: 'https://altro-sito.example' },
			form: { action: 'dbem_register', event_id: events.a.id, dbem_name: 'Anna', dbem_email: 'anna@example.com' },
		} );

		expect( noOrigin.status() ).toBe( 403 );
		expect( otherSite.status() ).toBe( 403 );
		expect( ( await getState( request ) ).registrations ).toHaveLength( 0 );
	} );

	test( 'il campo nascosto compilato (bot) non iscrive', async ( { request } ) => {
		const { events } = await resetState( request, { events: [ { key: 'a' } ] } );

		const res = await registerViaAjax( request, { event_id: events.a.id, dbem_name: 'Bot', dbem_email: 'bot@example.com', dbem_website_url: 'https://spam.example' } );

		expect( ( await res.json() ).success ).toBe( false );
		expect( ( await getState( request ) ).registrations ).toHaveLength( 0 );
	} );

	test( 'oltre il limite per IP il messaggio compare a video (429)', async ( { page, request } ) => {
		const { events } = await resetState( request, { rate_limit: 2, events: [ { key: 'a' } ] } );
		await registerViaAjax( request, { event_id: events.a.id, dbem_name: 'Uno', dbem_email: 'uno@example.com' } );
		await registerViaAjax( request, { event_id: events.a.id, dbem_name: 'Due', dbem_email: 'due@example.com' } );

		await page.goto( events.a.url );
		const message = await submitRegistration( page, { name: 'Tre', email: 'tre@example.com' } );

		await expect( message ).toContainText( 'Troppe richieste' );
		expect( ( await getState( request ) ).registrations ).toHaveLength( 2 );
	} );
} );
