// @ts-check
/**
 * Helper condivisi per gli E2E di DB Event Manager.
 */
const path = require( 'path' );

/**
 * Sessione admin salvata da auth.setup.js. Negli spec admin:
 * test.use( { storageState: ADMIN_STATE } ).
 */
const ADMIN_STATE = path.join( __dirname, '.auth', 'admin.json' );

/** PIN di sistema impostato dal reset della fixture. */
const PIN = '123456';

const PUBLIC_PAGES = {
	checkin: '/?dbem_checkin_page=1',
	participants: '/?dbem_participants_page=1',
};

/**
 * Stato baseline e creazione degli eventi (dbem_e2e_reset_state() nella fixture).
 *
 * @param {import('@playwright/test').APIRequestContext} request
 * @param {{events?: Array<{key?: string, title?: string, status?: string, meta?: object, registrations?: Array<{name?: string, email?: string, status?: string}>}>, rate_limit?: number}} [opts]
 * @returns {Promise<{events: Object<string, {id: number, url: string, registrations: Array<{id: number, token: string}>}>, pin: string}>}
 */
async function resetState( request, opts = {} ) {
	const res = await request.post( '/?rest_route=/dbem-e2e/v1/reset', { data: opts } );
	if ( ! res.ok() ) {
		throw new Error( `Reset E2E fallito (HTTP ${ res.status() }): ${ await res.text() }` );
	}
	return res.json();
}

/**
 * Email catturate e iscrizioni salvate.
 *
 * @param {import('@playwright/test').APIRequestContext} request
 * @returns {Promise<{mails: Array<{to: string, subject: string, message: string}>, registrations: Array<object>, survey: Array<{id: number, event_id: number, registration_id: number, data: string}>}>}
 */
async function getState( request ) {
	const res = await request.get( '/?rest_route=/dbem-e2e/v1/state' );
	if ( ! res.ok() ) {
		throw new Error( `Lettura stato E2E fallita (HTTP ${ res.status() }): ${ await res.text() }` );
	}
	return res.json();
}

/**
 * Compila e invia il form integrato di un evento.
 *
 * @param {import('@playwright/test').Page} page
 * @param {{name: string, email: string, privacy?: boolean}} data
 */
async function submitRegistration( page, { name, email, privacy = false } ) {
	const form = page.locator( '.dbem-form' );
	await form.locator( 'input[name="dbem_name"]' ).fill( name );
	await form.locator( 'input[name="dbem_email"]' ).fill( email );
	if ( privacy ) {
		await form.locator( 'input[name="dbem_privacy"]' ).check();
	}
	await form.locator( '.dbem-submit' ).click();
	return form.locator( '.dbem-message' );
}

/**
 * Primo link di un'email catturata che contiene il testo indicato (es. 'dbem_action=approve'),
 * con le entità HTML di esc_url() decodificate e relativo al sito
 *
 * @param {{message: string}} mail
 * @param {string} contains
 * @returns {string}
 */
function linkFromMail( mail, contains ) {
	const hrefs = [ ...mail.message.matchAll( /href="([^"]+)"/g ) ].map( ( m ) => m[ 1 ].replace( /&#0?38;|&amp;/g, '&' ) );
	const link = hrefs.find( ( h ) => h.includes( contains ) );
	if ( ! link ) {
		throw new Error( `Nessun link con "${ contains }" nell'email «${ mail.subject }»` );
	}
	const url = new URL( link );
	return url.pathname + url.search;
}

/**
 * Iscrizione anonima diretta all'endpoint, come dalla pagina dell'evento
 *
 * @param {import('@playwright/test').APIRequestContext} request
 * @param {object} form
 */
function registerViaAjax( request, form ) {
	return request.post( '/wp-admin/admin-ajax.php', {
		headers: { Origin: process.env.WP_BASE_URL || 'http://localhost:8888' },
		form: { action: 'dbem_register', ...form },
	} );
}

module.exports = {
	ADMIN_STATE,
	PIN,
	PUBLIC_PAGES,
	resetState,
	getState,
	submitRegistration,
	linkFromMail,
	registerViaAjax,
};
