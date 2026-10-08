// @ts-check
const { test, expect } = require( '@playwright/test' );
const { ADMIN_STATE, resetState, registerViaAjax } = require( './helpers' );

/**
 * Ecosistema con DB Privacy Hub (attivo in wp-env): registro dei trattamenti, registro
 * dei consensi, export e cancellazione dei dati personali
 */
test.describe( 'Privacy Hub', () => {
	test( 'export dei dati personali con l\'Hub attivo: un solo exporter, dati dell\'iscritto', async ( { request } ) => {
		const { events } = await resetState( request, { events: [ { key: 'a', title: 'Evento privacy' } ] } );
		await registerViaAjax( request, { event_id: events.a.id, dbem_name: 'Gina Rosa', dbem_email: 'gina@example.com' } );

		const res = await request.get( '/?rest_route=/dbem-e2e/v1/privacy&email=gina@example.com' );
		const privacy = await res.json();

		expect( privacy.exporters ).toHaveLength( 1 );
		expect( privacy.erasers ).toHaveLength( 1 );
		const exported = JSON.stringify( Object.values( privacy.export )[ 0 ].data );
		expect( exported ).toContain( 'Gina Rosa' );
		expect( exported ).toContain( 'Evento privacy' );
	} );

	test.describe( 'admin', () => {
		test.use( { storageState: ADMIN_STATE } );

		test( 'il registro dei trattamenti e dei consensi elenca DB Event Manager', async ( { page, request, playwright } ) => {
			const { events } = await resetState( request, { events: [ { key: 'a', meta: { _dbem_gdpr_enabled: '1' } } ] } );
			const anon = await playwright.request.newContext( { baseURL: process.env.WP_BASE_URL || 'http://localhost:8888', storageState: { cookies: [], origins: [] } } );
			await registerViaAjax( anon, { event_id: events.a.id, dbem_name: 'Ivo Ocra', dbem_email: 'ivo@example.com', dbem_privacy: '1' } );
			await anon.dispose();

			await page.goto( '/wp-admin/admin.php?page=dbph' );
			await expect( page.getByText( 'Iscrizioni eventi (DB Event Manager)' ).first() ).toBeVisible();
			await expect( page.getByText( 'Email transazionali eventi (DB Event Manager)' ).first() ).toBeVisible();

			await page.goto( '/wp-admin/admin.php?page=dbph-consents' );
			await expect( page.getByText( /Event Manager — Consensi iscrizioni/ ).first() ).toBeVisible();
		} );
	} );
} );
