// @ts-check
const { test, expect } = require( '@playwright/test' );
const { ADMIN_STATE, resetState, getState } = require( './helpers' );

/**
 * Scheda dell'evento nell'editor a blocchi: Duplica (#23) e conferma delle opzioni
 * rinominate (D5). I riquadri del plugin sono metabox, fuori dall'iframe dell'editor.
 */
test.use( { storageState: ADMIN_STATE } );

async function openEditor( page, eventId ) {
	await page.goto( `/wp-admin/post.php?post=${ eventId }&action=edit` );
	await page.waitForFunction( () => window.wp && window.wp.data && window.wp.data.select( 'core/editor' ) );
	// Guida di benvenuto dell'editor: chiusa e disattivata
	await page.evaluate( () => {
		const prefs = window.wp.data.dispatch( 'core/preferences' );
		if ( prefs ) prefs.set( 'core/edit-post', 'welcomeGuide', false );
	} );
	const guide = page.getByRole( 'dialog', { name: /Welcome|Benvenuto/ } );
	if ( await guide.isVisible().catch( () => false ) ) await page.keyboard.press( 'Escape' );
}

async function saveEditor( page ) {
	await page.evaluate( () => window.wp.data.dispatch( 'core/editor' ).savePost() );
	// I metabox si salvano dopo il post: si aspetta che anche loro abbiano finito
	await page.waitForFunction( () => {
		const editPost = window.wp.data.select( 'core/edit-post' );
		const editor = window.wp.data.select( 'core/editor' );
		return ! editor.isSavingPost() && ! editPost.isSavingMetaBoxes();
	} );
}

const LAB_FIELD = { id: 'f_lab', type: 'radio', label: 'Laboratorio', required: true, options: [ 'Lab 10 ott', 'Lab 17 ott' ] };

test.describe( 'Editor dell\'evento', () => {
	test( 'Duplica dalla colonna laterale crea una bozza senza partecipanti', async ( { page, request } ) => {
		const { events } = await resetState( request, {
			events: [ { key: 'a', title: 'Corso di robotica', registrations: [ { name: 'Anna', email: 'anna@example.com' } ] } ],
		} );

		await openEditor( page, events.a.id );
		await page.getByRole( 'link', { name: 'Duplica evento' } ).click();
		await page.waitForURL( /dbem_duplicated=1/ );
		await expect( page.getByText( 'Evento duplicato in bozza' ) ).toBeVisible();

		const state = await getState( request );
		const copy = state.events.find( ( e ) => e.id !== events.a.id );
		expect( copy ).toMatchObject( { status: 'draft', title: 'Corso di robotica (copia)' } );
		expect( state.registrations.filter( ( r ) => Number( r.event_id ) === copy.id ) ).toHaveLength( 0 );
		expect( page.url() ).toContain( `post=${ copy.id }` );
	} );

	test( 'un\'opzione rinominata aggiorna le iscrizioni solo dopo la conferma (D5)', async ( { page, request } ) => {
		const { events } = await resetState( request, {
			events: [ {
				key: 'a',
				meta: { _dbem_custom_fields: [ LAB_FIELD ] },
				registrations: [ { name: 'Anna', email: 'anna@example.com', data: { Laboratorio: 'Lab 10 ott' } } ],
			} ],
		} );

		await openEditor( page, events.a.id );
		await page.locator( '.dbem-f-options' ).first().fill( 'Lab 24 ott\nLab 17 ott' );
		await saveEditor( page );

		// Salvato: le iscrizioni non sono cambiate, il riquadro chiede cosa fare
		const box = page.locator( '#dbem-pending-renames' );
		await expect( box ).toContainText( 'Lab 10 ott' );
		let data = JSON.parse( ( await getState( request ) ).registrations[ 0 ].data );
		expect( data.Laboratorio ).toBe( 'Lab 10 ott' );

		await box.getByRole( 'button', { name: /Aggiorna 1 iscrizione/ } ).click();
		await expect( page.locator( '#dbem-pending-renames' ) ).toContainText( '1 iscrizione aggiornata' );
		data = JSON.parse( ( await getState( request ) ).registrations[ 0 ].data );
		expect( data.Laboratorio ).toBe( 'Lab 24 ott' );
	} );

	test( '"Lascia com\'è" non tocca le iscrizioni (D5)', async ( { page, request } ) => {
		const { events } = await resetState( request, {
			events: [ {
				key: 'a',
				meta: { _dbem_custom_fields: [ LAB_FIELD ] },
				registrations: [ { name: 'Anna', email: 'anna@example.com', data: { Laboratorio: 'Lab 10 ott' } } ],
			} ],
		} );

		await openEditor( page, events.a.id );
		await page.locator( '.dbem-f-options' ).first().fill( 'Lab 24 ott\nLab 17 ott' );
		await saveEditor( page );
		await page.locator( '#dbem-pending-renames' ).getByRole( 'button', { name: /Lascia com/ } ).click();

		await expect( page.locator( '#dbem-pending-renames' ) ).toContainText( 'com\'erano' );
		const data = JSON.parse( ( await getState( request ) ).registrations[ 0 ].data );
		expect( data.Laboratorio ).toBe( 'Lab 10 ott' );
	} );
} );
