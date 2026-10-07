// @ts-check
const { test, expect } = require( '@playwright/test' );
const { PIN, resetState } = require( './helpers' );

const BASE = process.env.WP_BASE_URL || 'http://localhost:8888';

function pinCheck( request, pin ) {
	return request.post( '/wp-admin/admin-ajax.php', {
		headers: { Origin: BASE },
		form: { action: 'dbem_public_pin_check', pin },
	} );
}

test.describe( 'PIN delle pagine pubbliche', () => {
	test( 'una raffica di PIN errati non supera i 10 tentativi (bug #25)', async ( { request } ) => {
		await resetState( request );

		const responses = await Promise.all( Array.from( { length: 15 }, () => pinCheck( request, '000000' ) ) );
		const statuses = responses.map( ( r ) => r.status() );

		expect( statuses.filter( ( s ) => s === 403 ) ).toHaveLength( 10 );
		expect( statuses.filter( ( s ) => s === 429 ) ).toHaveLength( 5 );
		// Bloccato: anche il PIN giusto aspetta la fine del blocco
		expect( ( await pinCheck( request, PIN ) ).status() ).toBe( 429 );
	} );

	test( 'il PIN dedicato apre solo il suo evento (D1)', async ( { request } ) => {
		const { events } = await resetState( request, {
			events: [ { key: 'a', title: 'Evento A' }, { key: 'b', title: 'Evento B', meta: { _dbem_checkin_pin: '4321' } } ],
		} );

		const system = await ( await pinCheck( request, PIN ) ).json();
		expect( system.data.events.map( ( e ) => e.id ) ).toEqual( [ events.a.id ] );

		const dedicated = await ( await pinCheck( request, '4321' ) ).json();
		expect( dedicated.data.events.map( ( e ) => e.id ) ).toEqual( [ events.b.id ] );

		const other = await request.post( '/wp-admin/admin-ajax.php', {
			headers: { Origin: BASE },
			form: { action: 'dbem_public_participants', pin: '4321', event_id: events.a.id },
		} );
		expect( other.status() ).toBe( 403 );
	} );
} );
