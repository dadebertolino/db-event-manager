// @ts-check
const { test, expect } = require( '@playwright/test' );
const { ADMIN_STATE, resetState, getState, submitRegistration } = require( './helpers' );

/**
 * Smoke test dell'infrastruttura E2E: se questi falliscono, i fallimenti degli
 * altri spec non sono attendibili.
 */
test.describe( 'Infrastruttura E2E', () => {
	test( 'il reset crea gli eventi richiesti e azzera email e iscrizioni', async ( { request } ) => {
		const { events, pin } = await resetState( request, { events: [ { key: 'a', title: 'Evento A' } ] } );

		expect( pin ).toBe( '123456' );
		expect( events.a.id ).toBeGreaterThan( 0 );
		const state = await getState( request );
		expect( state.mails ).toEqual( [] );
		expect( state.registrations ).toEqual( [] );
	} );

	test( 'archivio e pagina evento rispondono (permalink dopo l\'attivazione, bug #6)', async ( { page, request } ) => {
		const { events } = await resetState( request, { events: [ { key: 'a', title: 'Evento A' } ] } );

		const archive = await page.goto( '/eventi/' );
		expect( archive?.status() ).toBe( 200 );
		await expect( page.getByText( 'Evento A' ).first() ).toBeVisible();

		const single = await page.goto( events.a.url );
		expect( single?.status() ).toBe( 200 );
		await expect( page.locator( '.dbem-form' ) ).toBeVisible();
	} );

	test( 'un\'iscrizione anonima arriva al database e l\'email viene catturata', async ( { page, request } ) => {
		const { events } = await resetState( request, { events: [ { key: 'a', title: 'Evento A' } ] } );

		await page.goto( events.a.url );
		const message = await submitRegistration( page, { name: 'Anna Rossi', email: 'anna@example.com' } );
		await expect( message ).toContainText( 'Richiesta ricevuta' );

		const state = await getState( request );
		expect( state.registrations ).toHaveLength( 1 );
		expect( state.registrations[ 0 ] ).toMatchObject( { email: 'anna@example.com', status: 'confirmed' } );
		expect( state.mails.map( ( m ) => m.to ) ).toContain( 'anna@example.com' );
		expect( state.mails.find( ( m ) => m.to === 'anna@example.com' )?.subject ).toBe( 'Iscrizione confermata: Evento A' );
	} );

	test.describe( 'admin', () => {
		test.use( { storageState: ADMIN_STATE } );

		test( 'la sessione admin apre l\'elenco eventi', async ( { page } ) => {
			await page.goto( '/wp-admin/edit.php?post_type=dbem_event' );
			await expect( page.locator( '#wpbody-content h1' ).first() ).toBeVisible();
			await expect( page ).not.toHaveURL( /wp-login\.php/ );
		} );
	} );
} );
