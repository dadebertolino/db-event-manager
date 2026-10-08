// @ts-check
const { test, expect } = require( '@playwright/test' );
const { resetState, getState, linkFromMail, registerViaAjax } = require( './helpers' );

/**
 * Flussi che partono da un link in un'email: approvazione e rifiuto da parte del
 * responsabile, conferma della modifica di un'iscrizione da parte dell'iscritto
 */
const APPROVAL_EVENT = {
	key: 'a',
	title: 'Evento con approvazione',
	meta: { _dbem_approval_mode: 'approval', _dbem_approver_email: 'responsabile@example.com' },
};

async function approvalLink( request, action ) {
	const mail = ( await getState( request ) ).mails.find( ( m ) => m.to === 'responsabile@example.com' );
	expect( mail, 'email di richiesta approvazione' ).toBeTruthy();
	return linkFromMail( mail, `dbem_action=${ action }` );
}

test.describe( 'Link nelle email', () => {
	test( 'il responsabile approva dal link e l\'iscritto riceve la conferma', async ( { page, request } ) => {
		const { events } = await resetState( request, { events: [ APPROVAL_EVENT ] } );
		await registerViaAjax( request, { event_id: events.a.id, dbem_name: 'Anna', dbem_email: 'anna@example.com' } );

		let state = await getState( request );
		expect( state.registrations[ 0 ].status ).toBe( 'pending' );
		expect( state.mails.find( ( m ) => m.to === 'anna@example.com' )?.subject ).toContain( 'Iscrizione ricevuta' );

		await page.goto( await approvalLink( request, 'approve' ) );
		await page.getByRole( 'button', { name: /Approva/ } ).click();
		await expect( page.getByText( /approvata/ ) ).toBeVisible();

		state = await getState( request );
		expect( state.registrations[ 0 ].status ).toBe( 'confirmed' );
		expect( state.mails.filter( ( m ) => m.to === 'anna@example.com' ).map( ( m ) => m.subject ) ).toContain( 'Iscrizione confermata: Evento con approvazione' );
	} );

	test( 'il responsabile rifiuta dal link e l\'iscritto viene avvisato', async ( { page, request } ) => {
		const { events } = await resetState( request, { events: [ APPROVAL_EVENT ] } );
		await registerViaAjax( request, { event_id: events.a.id, dbem_name: 'Bruno', dbem_email: 'bruno@example.com' } );

		await page.goto( await approvalLink( request, 'reject' ) );
		await page.getByRole( 'button', { name: /Rifiuta/ } ).click();
		await expect( page.getByText( /rifiutata/ ) ).toBeVisible();

		const state = await getState( request );
		expect( state.registrations[ 0 ].status ).toBe( 'rejected' );
		expect( state.mails.filter( ( m ) => m.to === 'bruno@example.com' ) ).toHaveLength( 2 );
	} );

	test( 'un link di approvazione alterato non approva', async ( { page, request } ) => {
		const { events } = await resetState( request, { events: [ APPROVAL_EVENT ] } );
		await registerViaAjax( request, { event_id: events.a.id, dbem_name: 'Carla', dbem_email: 'carla@example.com' } );

		const link = ( await approvalLink( request, 'approve' ) ).replace( /key=[^&]+/, 'key=0000' );
		const res = await page.goto( link );
		expect( res?.status() ).toBeGreaterThanOrEqual( 400 );
		expect( ( await getState( request ) ).registrations[ 0 ].status ).toBe( 'pending' );
	} );

	test( 'la modifica di un\'iscrizione vale solo dopo il clic nell\'email, una volta', async ( { page, request } ) => {
		const { events } = await resetState( request, { events: [ { key: 'a', meta: { _dbem_allow_registration_update: '1' } } ] } );
		await registerViaAjax( request, { event_id: events.a.id, dbem_name: 'Anna Rossi', dbem_email: 'anna@example.com' } );
		await registerViaAjax( request, { event_id: events.a.id, dbem_name: 'Anna Bianchi', dbem_email: 'anna@example.com' } );

		let state = await getState( request );
		expect( state.registrations ).toHaveLength( 1 );
		expect( state.registrations[ 0 ].name ).toBe( 'Anna Rossi' );
		const mail = state.mails.find( ( m ) => m.subject.startsWith( 'Conferma la modifica' ) );
		expect( mail?.to ).toBe( 'anna@example.com' );
		const link = linkFromMail( mail, 'dbem_action=confirm_update' );

		// Aprire il link non basta (i programmi di posta lo aprono in anticipo): serve il pulsante
		await page.goto( link );
		expect( ( await getState( request ) ).registrations[ 0 ].name ).toBe( 'Anna Rossi' );
		await page.getByRole( 'button', { name: 'Conferma la modifica' } ).click();

		state = await getState( request );
		expect( state.registrations[ 0 ].name ).toBe( 'Anna Bianchi' );

		const again = await page.goto( link );
		expect( again?.status() ).toBe( 404 );
	} );
} );
