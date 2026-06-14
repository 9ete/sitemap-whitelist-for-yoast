/**
 * End-to-end smoke of the Sitemap Whitelist for Yoast settings flow.
 * Runs against the isolated swy.lndo.site (WordPress + Yoast SEO).
 */
describe( 'Sitemap Whitelist for Yoast — settings flow', () => {
	beforeEach( () => {
		cy.task( 'createWpTestUser', {
			username: Cypress.env( 'test_user' ),
			email: Cypress.env( 'test_email' ),
			password: Cypress.env( 'test_pass' ),
		} );
		cy.task( 'setWhitelistOption', null );
		cy.wpLogin();
	} );

	it( 'registers the settings screen under Settings', () => {
		cy.visitSwySettings();
		cy.contains( 'h1', 'Sitemap Whitelist' ).should( 'be.visible' );
		cy.get( '#smwy_whitelist_urls' ).should( 'exist' );
		cy.get( '#smwy_whitelist_csv' ).should( 'exist' );
	} );

	it( 'saves a whitelist and shows a success notice with health counts', () => {
		cy.visitSwySettings();
		cy.get( '#smwy_whitelist_urls' )
			.clear()
			.type( 'https://swy.lndo.site/\nhttps://swy.lndo.site/sample-page/' );
		cy.contains( 'button', 'Save whitelist' ).click();

		cy.get( '.notice-success' ).should( 'contain', 'Whitelist saved' );
		cy.contains( 'Valid URLs' ).should( 'exist' );
	} );

	it( 'clears the whitelist and reports it', () => {
		cy.task( 'setWhitelistOption', 'https://swy.lndo.site/' );
		cy.visitSwySettings();
		cy.on( 'window:confirm', () => true );
		cy.contains( 'button', 'Clear whitelist' ).click();
		cy.get( '.notice-success' ).should( 'contain', 'cleared' );
	} );

	it( 'exposes the import + export controls', () => {
		cy.visitSwySettings();
		cy.contains( 'button', 'Import CSV' ).should( 'exist' );
		cy.contains( 'button', 'Export CSV' ).should( 'exist' );
	} );

	it( 'renders the settings page without PHP errors', () => {
		cy.visitSwySettings();
		// Confirm we are actually on the settings screen (not bounced to login).
		cy.contains( 'h1', 'Sitemap Whitelist' ).should( 'be.visible' );
		cy.get( 'body' )
			.invoke( 'text' )
			.should( ( text ) => {
				expect( text ).not.to.match( /Fatal error|Parse error|Warning:|Notice:|Deprecated:/ );
			} );
	} );
} );
