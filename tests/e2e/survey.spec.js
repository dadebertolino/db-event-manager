// @ts-check
const { test, expect } = require( '@playwright/test' );
const { ADMIN_STATE, resetState, getState } = require( './helpers' );

const BASE = process.env.WP_BASE_URL || 'http://localhost:8888';

/**
 * Survey post-evento: pagina pubblica con il link personale, riepilogo e tabella
 * in admin, export CSV. Le verifiche guardano ciò che si vede, non il formato dei
 * dati salvati: valgono anche dopo il passaggio agli id dei campi (#37).
 */
const SURVEY_FIELDS = [
	{ id: 's_voto', type: 'radio', label: 'Voto', required: true, options: [ 'Ottimo', 'Buono', 'Scarso' ] },
	{ id: 's_temi', type: 'checkbox', label: 'Temi preferiti', required: false, options: [ 'Robotica', 'Chimica' ] },
	{ id: 's_note', type: 'textarea', label: 'Commenti', required: false, options: [] },
];

function surveyEvent( registrations ) {
	return {
		key: 'a',
		title: 'Evento con survey',
		meta: { _dbem_survey_enabled: '1', _dbem_survey_fields: SURVEY_FIELDS },
		registrations,
	};
}

/** Risposta inviata come dalla pagina del survey (form con i nomi dbem_survey_N) */
function submitSurvey( request, token, answers ) {
	return request.post( '/wp-admin/admin-ajax.php', {
		headers: { Origin: BASE },
		form: { action: 'dbem_submit_survey', token, ...answers },
	} );
}

test.describe( 'Survey', () => {
	test( 'chi era presente risponde una volta sola dal link personale', async ( { page, request } ) => {
		const { events } = await resetState( request, { events: [ surveyEvent( [ { name: 'Anna', email: 'anna@example.com', status: 'checked_in' } ] ) ] } );
		const token = events.a.registrations[ 0 ].token;

		await page.goto( `/?dbem_survey=${ token }` );
		await page.getByLabel( 'Buono' ).check();
		await page.getByLabel( 'Robotica' ).check();
		await page.getByLabel( 'Commenti' ).fill( 'Bello, l\'aula però era piccola' );
		await page.locator( '.dbem-submit' ).click();
		await expect( page.locator( '.dbem-message' ) ).toContainText( 'Grazie' );

		expect( ( await getState( request ) ).survey ).toHaveLength( 1 );

		await page.goto( `/?dbem_survey=${ token }` );
		await expect( page.getByText( 'hai già risposto' ) ).toBeVisible();
	} );

	test( 'il voto obbligatorio va scelto', async ( { request } ) => {
		const { events } = await resetState( request, { events: [ surveyEvent( [ { status: 'checked_in' } ] ) ] } );

		const res = await submitSurvey( request, events.a.registrations[ 0 ].token, { dbem_survey_2: 'Senza voto' } );

		expect( ( await res.json() ).success ).toBe( false );
		expect( ( await getState( request ) ).survey ).toHaveLength( 0 );
	} );

	test( 'un iscritto in attesa non risponde (bug #21)', async ( { page, request } ) => {
		const { events } = await resetState( request, { events: [ surveyEvent( [ { status: 'pending' } ] ) ] } );
		const token = events.a.registrations[ 0 ].token;

		await page.goto( `/?dbem_survey=${ token }` );
		await expect( page.getByText( 'non è attivo' ) ).toBeVisible();
		const res = await submitSurvey( request, token, { dbem_survey_0: 'Ottimo' } );
		expect( ( await res.json() ).success ).toBe( false );
	} );

	test.describe( 'admin', () => {
		test.use( { storageState: ADMIN_STATE } );

		test( 'riepilogo, tabella ed export CSV mostrano le risposte sotto le domande', async ( { page, request } ) => {
			const { events } = await resetState( request, {
				events: [ surveyEvent( [
					{ name: 'Anna', email: 'anna@example.com', status: 'checked_in' },
					{ name: 'Bruno', email: 'bruno@example.com', status: 'checked_in' },
				] ) ],
			} );
			const [ anna, bruno ] = events.a.registrations;
			await submitSurvey( request, anna.token, { dbem_survey_0: 'Ottimo', 'dbem_survey_1[]': 'Robotica', dbem_survey_2: 'Tutto bene' } );
			await submitSurvey( request, bruno.token, { dbem_survey_0: 'Ottimo' } );

			await page.goto( `/wp-admin/edit.php?post_type=dbem_event&page=dbem-survey&event_id=${ events.a.id }` );
			const voto = page.locator( '.dbem-summary-field' ).filter( { has: page.getByRole( 'heading', { name: 'Voto' } ) } );
			await expect( voto.getByRole( 'row', { name: /Ottimo\s+2/ } ) ).toBeVisible();

			const table = page.locator( 'table.widefat.striped' );
			await expect( table.getByRole( 'columnheader', { name: 'Commenti' } ) ).toBeVisible();
			await expect( table.getByRole( 'row', { name: /Anna.*Ottimo.*Robotica.*Tutto bene/ } ) ).toBeVisible();

			const href = await page.getByRole( 'link', { name: /Esporta CSV/ } ).getAttribute( 'href' );
			const csv = ( await ( await page.request.get( href || '' ) ).text() ).replace( /^﻿/, '' );
			const [ header, ...rows ] = csv.trim().split( /\r?\n/ );
			expect( header ).toContain( 'Voto' );
			expect( header ).toContain( 'Commenti' );
			expect( rows.find( ( r ) => r.includes( 'anna@example.com' ) ) ).toContain( 'Tutto bene' );
		} );
	} );
} );
