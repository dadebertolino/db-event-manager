// @ts-check
const { test, expect } = require( '@playwright/test' );
const AxeBuilder = require( '@axe-core/playwright' ).default;
const { PIN, PUBLIC_PAGES, resetState } = require( './helpers' );

/**
 * Accessibilità (WCAG 2.1 AA, bug #45–#48 del piano) con axe. Sul form si analizza
 * solo il contenuto del plugin: il tema di wp-env non è sotto test.
 */
const TAGS = [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa' ];

async function violations( page, include ) {
	let builder = new AxeBuilder( { page } ).withTags( TAGS );
	if ( include ) builder = builder.include( include );
	const results = await builder.analyze();
	return results.violations.map( ( v ) => `${ v.id }: ${ v.nodes.map( ( n ) => n.target.join( ' ' ) ).join( ', ' ) }` );
}

const FIELDS = [
	{ id: 'f_lab', type: 'radio', label: 'Laboratorio', required: true, options: [ 'Robotica', 'Chimica' ] },
	{ id: 'f_pasti', type: 'checkbox', label: 'Pasti', required: true, options: [ 'Pranzo', 'Cena' ] },
	{ id: 'f_note', type: 'textarea', label: 'Note', required: false, options: [] },
];

test.describe( 'Accessibilità', () => {
	test( 'form di iscrizione, anche con gli errori mostrati', async ( { page, request } ) => {
		const { events } = await resetState( request, {
			events: [ { key: 'a', meta: { _dbem_custom_fields: FIELDS, _dbem_gdpr_enabled: '1', _dbem_max_participants: 30 } } ],
		} );
		await page.goto( events.a.url );
		expect( await violations( page, '.dbem-single-event' ) ).toEqual( [] );

		// Invio vuoto: radio e gruppo di checkbox obbligatori devono risultare in errore (#45)
		await page.locator( '.dbem-submit' ).click();
		await expect( page.locator( 'input[name="dbem_custom_0"]' ).first() ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( page.locator( 'input[name="dbem_custom_1[]"]' ).first() ).toHaveAttribute( 'aria-invalid', 'true' );
		const describedBy = await page.locator( 'input[name="dbem_name"]' ).getAttribute( 'aria-describedby' );
		await expect( page.locator( `#${ describedBy }` ) ).not.toBeEmpty();
		expect( await violations( page, '.dbem-single-event' ) ).toEqual( [] );
	} );

	test( 'pagina check-in dopo il PIN', async ( { page, request } ) => {
		await resetState( request, { events: [ { key: 'a' } ] } );
		await page.goto( PUBLIC_PAGES.checkin );
		expect( await violations( page ) ).toEqual( [] );

		await page.locator( '#ci-pin-input' ).fill( PIN );
		await page.locator( '#ci-pin-btn' ).click();
		await expect( page.locator( '#ci-main' ) ).toBeVisible();
		expect( await violations( page ) ).toEqual( [] );
	} );

	test( 'pagina partecipanti e finestra dell\'orario', async ( { page, request } ) => {
		const { events } = await resetState( request, { events: [ { key: 'a' } ] } );
		await request.post( '/wp-admin/admin-ajax.php', {
			headers: { Origin: process.env.WP_BASE_URL || 'http://localhost:8888' },
			form: { action: 'dbem_register', event_id: events.a.id, dbem_name: 'Anna Rossi', dbem_email: 'anna@example.com' },
		} );

		await page.goto( PUBLIC_PAGES.participants );
		await page.locator( '#pp-pin-input' ).fill( PIN );
		await page.locator( '#pp-pin-btn' ).click();
		await expect( page.getByText( 'Anna Rossi' ) ).toBeVisible();
		expect( await violations( page ) ).toEqual( [] );

		// La finestra dell'orario: dialog con nome, focus dentro, Esc riporta al pulsante (#48)
		const opener = page.getByRole( 'button', { name: /Modifica orario — Anna Rossi/ } );
		await opener.click();
		await expect( page.getByRole( 'dialog', { name: /Modifica orario/ } ) ).toBeVisible();
		await expect( page.locator( '#pp-time-modal-input' ) ).toBeFocused();
		expect( await violations( page ) ).toEqual( [] );
		await page.keyboard.press( 'Escape' );
		await expect( opener ).toBeFocused();
	} );
	test( 'archivio degli eventi', async ( { page, request } ) => {
		await resetState( request, { events: [ { key: 'a', title: 'Evento uno' }, { key: 'b', title: 'Evento due', meta: { _dbem_max_participants: 20 } } ] } );
		await page.goto( '/eventi/' );
		await expect( page.getByText( 'Evento uno' ).first() ).toBeVisible();
		expect( await violations( page, '.dbem-archive-wrap' ) ).toEqual( [] );
	} );

	test( 'pagina evento con colori personalizzati (contrasti calcolati dal plugin)', async ( { page, request } ) => {
		const { events } = await resetState( request, {
			events: [ { key: 'a', meta: { _dbem_appearance: { color_bg: '#1b1f3b', color_primary: '#ffcc00', color_text: '#ffffff' } } } ],
		} );
		await page.goto( events.a.url );
		expect( await violations( page, '.dbem-single-event' ) ).toEqual( [] );

		// Anche con gli errori del form a video
		await page.locator( '.dbem-submit' ).click();
		expect( await violations( page, '.dbem-single-event' ) ).toEqual( [] );
	} );
} );
