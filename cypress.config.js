const { defineConfig } = require( 'cypress' );
const { execSync } = require( 'child_process' );
const path = require( 'path' );
const fs   = require( 'fs' );

/**
 * Absolute path to the Lando project dir (the one containing .lando.yml).
 * The plugin repo is a sibling of the Lando site:
 *   wp-plugins/
 *     ├── swy.lndo.site/                  ← Lando project (webroot: wp)
 *     └── sitemap-whitelist-for-yoast/    ← this repo
 * Override via LANDO_PROJECT_DIR for any other layout.
 */
const _siblingPath = path.resolve( __dirname, '..', 'swy.lndo.site' );
const LANDO_PROJECT_DIR = process.env.LANDO_PROJECT_DIR || _siblingPath;
const LANDO_BIN = process.env.LANDO_PATH || '/usr/local/bin/lando';

if ( ! fs.existsSync( path.join( LANDO_PROJECT_DIR, '.lando.yml' ) ) ) {
	// Non-fatal at require time; tasks that need Lando will throw with context.
	// eslint-disable-next-line no-console
	console.warn( `cypress.config.js: no .lando.yml at ${ LANDO_PROJECT_DIR }` );
}

/**
 * Run a `lando wp` command (wp-cli.yml in the Lando project pins --path=wp).
 *
 * @param {string} cmd WP-CLI subcommand (everything after "lando wp").
 */
function wp( cmd ) {
	execSync( `"${ LANDO_BIN }" wp ${ cmd }`, { stdio: 'pipe', cwd: LANDO_PROJECT_DIR } );
}

module.exports = defineConfig( {
	e2e: {
		baseUrl: process.env.CYPRESS_BASE_URL || 'https://swy.lndo.site',
		specPattern: 'tests/e2e/cypress/e2e/**/*.cy.js',
		supportFile: 'tests/e2e/cypress/support/e2e.js',
		screenshotsFolder: 'tests/e2e/cypress/screenshots',
		videosFolder: 'tests/e2e/cypress/videos',
		video: false,

		setupNodeEvents( on, config ) {
			on( 'task', {
				/**
				 * Reset the plugin's whitelist option to a known state.
				 *
				 * @param {string|null} value Raw option value, or null to delete.
				 */
				setWhitelistOption( value ) {
					if ( null === value ) {
						try {
							wp( 'option delete sitemap_whitelist_for_yoast_urls' );
						} catch {
							// not set — nothing to do
						}
					} else {
						const b64 = Buffer.from( String( value ), 'utf8' ).toString( 'base64' );
						wp( `eval "update_option('sitemap_whitelist_for_yoast_urls', base64_decode('${ b64 }'), false);"` );
					}
					return null;
				},

				/**
				 * Create (or reset) a WordPress admin user and suppress the
				 * admin-email-confirmation nag so login goes straight to wp-admin.
				 *
				 * @param {{ username: string, email: string, password: string }} opts
				 */
				createWpTestUser( { username, email, password } ) {
					try {
						wp( `user create "${ username }" "${ email }" --role=administrator --user_pass="${ password }"` );
					} catch {
						wp( `user update "${ username }" --user_pass="${ password }"` );
					}
					try {
						wp( 'option update admin_email_lifespan 9999999999' );
					} catch {
						// non-fatal
					}
					return null;
				},
			} );

			return config;
		},
	},
	env: {
		test_user: 'cypress_test_admin',
		test_pass: 'CypressTest123',
		test_email: 'cypress@swy-test.local',
	},
} );
