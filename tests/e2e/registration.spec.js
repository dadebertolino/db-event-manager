// @ts-check
const { test, expect } = require( '@playwright/test' );
const { resetState, getState, submitRegistration } = require( './helpers' );

const BASE = process.env.WP_BASE_URL || 'http://localhost:8888';

/**
 * Invio diretto all'endpoint, come un visitatore anonimo dalla pagina dell'evento
 *
 * @param {import('@playwright/test').APIRequestContext} request
 * @param {object} form
 */
function register( request, form ) {
	return request.post( '/wp-admin/admin-ajax.php', {
		headers: { Origin: BASE },
		form: { action: 'dbem_register', ...form },
	} );
}

test.describe( 'Iscrizione', () => {
	test( 'due invii insieme sull\'ultimo posto: ne entra uno solo (bug #9)', async ( { request } ) => {
		const { events } = await resetState( request, { events: [ { key: 'a', meta: { _dbem_max_participants: 1 } } ] } );

		const responses = await Promise.all(
			[ 'uno', 'due', 'tre' ].map( ( n ) => register( request, { event_id: events.a.id, dbem_name: n, dbem_email: `${ n }@example.com` } ) )
		);
		const bodies = await Promise.all( responses.map( ( r ) => r.json() ) );

		expect( bodies.filter( ( b ) => b.success ) ).toHaveLength( 1 );
		expect( ( await getState( request ) ).registrations ).toHaveLength( 1 );
	} );

	test( 'un indirizzo già iscritto riceve la stessa risposta di uno nuovo (D2)', async ( { page, request } ) => {
		const { events } = await resetState( request, { events: [ { key: 'a' } ] } );

		await page.goto( events.a.url );
		const first = await submitRegistration( page, { name: 'Anna', email: 'anna@example.com' } );
		await expect( first ).toContainText( 'Richiesta ricevuta' );
		const firstText = await first.textContent();

		await page.goto( events.a.url );
		const second = await submitRegistration( page, { name: 'Qualcun altro', email: 'anna@example.com' } );
		await expect( second ).toHaveText( firstText || '' );

		const state = await getState( request );
		expect( state.registrations ).toHaveLength( 1 );
		expect( state.registrations[ 0 ].name ).toBe( 'Anna' );
		const toAnna = state.mails.filter( ( m ) => m.to === 'anna@example.com' );
		expect( toAnna ).toHaveLength( 2 );
		expect( toAnna[ 1 ].message ).toContain( 'esiste già un' );
	} );

	test( 'l\'endpoint di DB Form Builder non accetta un evento con il form integrato (bug #1)', async ( { request } ) => {
		const { events } = await resetState( request, { events: [ { key: 'a', meta: { _dbem_gdpr_enabled: '1' } } ] } );

		const res = await request.post( '/wp-admin/admin-ajax.php', {
			headers: { Origin: BASE },
			form: { action: 'dbem_register_dbfb', event_id: events.a.id, dbem_name: 'Senza consenso', dbem_email: 'x@example.com' },
		} );

		expect( ( await res.json() ).success ).toBe( false );
		expect( ( await getState( request ) ).registrations ).toHaveLength( 0 );
	} );

	test( 'niente iscrizioni a un evento in bozza (bug #10)', async ( { request } ) => {
		const { events } = await resetState( request, { events: [ { key: 'a', status: 'draft' } ] } );

		const res = await register( request, { event_id: events.a.id, dbem_name: 'Anna', dbem_email: 'anna@example.com' } );

		expect( ( await res.json() ).success ).toBe( false );
		expect( ( await getState( request ) ).registrations ).toHaveLength( 0 );
	} );
} );
