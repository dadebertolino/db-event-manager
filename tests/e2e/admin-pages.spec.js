// @ts-check
const { test, expect } = require( '@playwright/test' );
const { ADMIN_STATE, resetState, getState } = require( './helpers' );

/**
 * Pagine admin: check-in, partecipanti (azioni, blocco, export), Duplica dall'elenco
 */
test.use( { storageState: ADMIN_STATE } );

test.describe( 'Pagine admin', () => {
	test( 'check-in admin: ricerca, check-in e QR di un altro evento (bug #15)', async ( { page, request } ) => {
		const { events } = await resetState( request, {
			events: [
				{ key: 'a', title: 'Serata A', registrations: [ { name: 'Carla Verdi', email: 'carla@example.com' } ] },
				{ key: 'b', title: 'Serata B', registrations: [ { name: 'Ugo Blu', email: 'ugo@example.com' } ] },
			],
		} );

		await page.goto( `/wp-admin/edit.php?post_type=dbem_event&page=dbem-checkin&event_id=${ events.a.id }` );
		await page.locator( '#dbem-search-input' ).fill( 'Verdi' );
		await page.locator( '#dbem-search-btn' ).click();
		await page.locator( '#dbem-results-list' ).getByRole( 'button', { name: /Carla Verdi/ } ).click();
		await expect( page.locator( '#dbem-checkin-feedback' ) ).toContainText( 'Check-in effettuato' );
		expect( ( await getState( request ) ).registrations.find( ( r ) => r.email === 'carla@example.com' ).status ).toBe( 'checked_in' );

		// QR dell'evento B con l'evento A selezionato: nessun check-in
		await page.evaluate( ( token ) => window.dbemCheckinProcessToken( token ), events.b.registrations[ 0 ].token );
		await expect( page.locator( '#dbem-checkin-feedback' ) ).toContainText( 'altro evento' );
		expect( ( await getState( request ) ).registrations.find( ( r ) => r.email === 'ugo@example.com' ).status ).toBe( 'confirmed' );
	} );

	test( 'partecipanti: approva, azione in blocco per stato, export senza IP', async ( { page, request } ) => {
		const { events } = await resetState( request, {
			events: [ {
				key: 'a',
				registrations: [
					{ name: 'In attesa', email: 'attesa@example.com', status: 'pending' },
					{ name: 'Confermato', email: 'confermato@example.com', status: 'confirmed' },
					{ name: 'Annullato', email: 'annullato@example.com', status: 'cancelled' },
				],
			} ],
		} );
		const status = async ( email ) => ( await getState( request ) ).registrations.find( ( r ) => r.email === email ).status;
		const url = `/wp-admin/edit.php?post_type=dbem_event&page=dbem-participants&event_id=${ events.a.id }`;

		await page.goto( url );
		await Promise.all( [ page.waitForNavigation(), page.getByRole( 'button', { name: 'Approva iscrizione' } ).click() ] );
		expect( await status( 'attesa@example.com' ) ).toBe( 'confirmed' );
		expect( ( await getState( request ) ).mails.map( ( m ) => m.to ) ).toContain( 'attesa@example.com' );

		// "Segna presente" su tutti: gli annullati restano annullati (bug #12)
		await page.locator( '#dbem-select-all' ).check();
		await page.locator( '#dbem-bulk-select' ).selectOption( 'checkin' );
		page.once( 'dialog', ( d ) => d.accept() );
		await Promise.all( [ page.waitForNavigation(), page.locator( '#dbem-bulk-apply' ).click() ] );
		expect( await status( 'confermato@example.com' ) ).toBe( 'checked_in' );
		expect( await status( 'annullato@example.com' ) ).toBe( 'cancelled' );

		const href = await page.locator( '#dbem-export-csv' ).getAttribute( 'href' );
		const csv = ( await ( await page.request.get( href || '' ) ).text() ).replace( /^﻿/, '' );
		const header = csv.split( /\r?\n/ )[ 0 ];
		expect( header.split( ';' ) ).toContain( 'Email' );
		expect( header ).not.toContain( 'IP' );
	} );

	test( 'Duplica dall\'elenco eventi', async ( { page, request } ) => {
		const { events } = await resetState( request, { events: [ { key: 'a', title: 'Laboratorio di chimica' } ] } );

		await page.goto( '/wp-admin/edit.php?post_type=dbem_event' );
		const row = page.locator( `#post-${ events.a.id }` );
		await row.hover();
		await Promise.all( [ page.waitForURL( /dbem_duplicated=1/ ), row.getByRole( 'link', { name: /Duplica/ } ).click() ] );

		const copy = ( await getState( request ) ).events.find( ( e ) => e.id !== events.a.id );
		expect( copy ).toMatchObject( { status: 'draft', title: 'Laboratorio di chimica (copia)' } );
	} );
} );
