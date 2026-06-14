// Cypress support file for Sitemap Whitelist for Yoast e2e tests.

// Yoast and other plugins can emit benign console errors that should not fail
// the suite; only fail on errors originating from our own plugin code.
Cypress.on( 'uncaught:exception', ( err ) => {
	if ( err && typeof err.message === 'string' && err.message.includes( 'sitemap-whitelist-for-yoast' ) ) {
		return true;
	}
	return false;
} );

/**
 * Log in to wp-admin via the standard login form.
 *
 * @param {string} [user] Username (defaults to the configured test admin).
 * @param {string} [pass] Password (defaults to the configured test admin).
 */
Cypress.Commands.add( 'wpLogin', ( user, pass ) => {
	const username = user || Cypress.env( 'test_user' );
	const password = pass || Cypress.env( 'test_pass' );

	// Authenticate fresh per test via HTTP (no cy.session — its cookie
	// restoration drops the /wp-admin-scoped auth cookie, which bounces admin
	// page loads to wp-login with reauth=1). Seed the test cookie with a GET,
	// then POST credentials; cypress keeps these cookies for the test's visits.
	cy.request( '/wp-login.php' );
	cy.request( {
		method: 'POST',
		url: '/wp-login.php',
		form: true,
		body: {
			log: username,
			pwd: password,
			'wp-submit': 'Log In',
			redirect_to: '/wp-admin/',
			testcookie: '1',
		},
	} );
} );

/**
 * Visit the plugin's settings screen (Settings → Sitemap Whitelist).
 */
Cypress.Commands.add( 'visitSwySettings', () => {
	cy.visit( '/wp-admin/options-general.php?page=sitemap-whitelist-for-yoast' );
} );
