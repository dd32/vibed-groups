/**
 * Playwright E2E test configuration.
 *
 * Tests run against the wp-env local development environment.
 * Start with: npm run env:start
 * Run with: npx playwright test
 */

const { defineConfig } = require( '@playwright/test' );

module.exports = defineConfig( {
	testDir: './tests/e2e',
	timeout: 30000,
	retries: 1,
	use: {
		baseURL: process.env.WP_BASE_URL || 'http://localhost:8889',
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
	},
	projects: [
		{
			name: 'chromium',
			use: { browserName: 'chromium' },
		},
	],
} );
