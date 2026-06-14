/**
 * Captures the WordPress.org screenshots. PNGs land under
 * tests/e2e/cypress/screenshots/wp-repo-screenshots.cy.js/ and are promoted to
 * screenshot-N.png at the plugin root by bin/sync-wp-repo-screenshots.sh.
 *
 * The numeric prefixes drive the order in readme.txt's "== Screenshots ==".
 */
describe( 'wp.org screenshots', () => {
	beforeEach( () => {
		cy.task( 'createWpTestUser', {
			username: Cypress.env( 'test_user' ),
			email: Cypress.env( 'test_email' ),
			password: Cypress.env( 'test_pass' ),
		} );
		cy.task(
			'setWhitelistOption',
			[
				'https://swy.lndo.site/',
				'https://swy.lndo.site/sample-page/',
				'https://swy.lndo.site/about/',
				'https://swy.lndo.site/contact/',
			].join( '\n' )
		);
		cy.wpLogin();
	} );

	it( 'captures the settings screen', () => {
		cy.visitSwySettings();
		cy.contains( 'h1', 'Sitemap Whitelist' ).should( 'be.visible' );
		cy.screenshot( '01-settings-screen', { capture: 'fullPage' } );
	} );

	it( 'captures the whitelist health panel', () => {
		cy.visitSwySettings();
		cy.contains( 'h2', 'Current whitelist health' ).scrollIntoView();
		cy.screenshot( '02-whitelist-health', { capture: 'viewport' } );
	} );
} );
