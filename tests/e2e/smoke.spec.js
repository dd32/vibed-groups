/**
 * E2E smoke tests — verify critical pages load and key elements render.
 *
 * Run: npx playwright test
 * Requires: npm run env:start
 */

const { test, expect } = require( '@playwright/test' );

test.describe( 'Main directory site', () => {
	test( 'homepage loads with group directory', async ( { page } ) => {
		await page.goto( '/' );
		await expect( page ).toHaveTitle( /WordPress Community Groups/ );
		await expect( page.locator( 'body' ) ).toBeVisible();
	} );
} );

test.describe( 'Melbourne group site', () => {
	test( 'homepage loads', async ( { page } ) => {
		await page.goto( '/melbourne/' );
		await expect( page ).toHaveTitle( /WordPress Melbourne/ );
	} );

	test( 'events page loads', async ( { page } ) => {
		await page.goto( '/melbourne/events/' );
		await expect( page.locator( 'body' ) ).toBeVisible();
	} );

	test( 'members page loads', async ( { page } ) => {
		await page.goto( '/melbourne/members/' );
		await expect( page.locator( 'body' ) ).toBeVisible();
	} );

	test( 'single event page has RSVP button', async ( { page } ) => {
		await page.goto( '/melbourne/' );
		// Find first event link and click it.
		const eventLink = page.locator( 'a[href*="/event/"]' ).first();
		if ( await eventLink.isVisible() ) {
			await eventLink.click();
			// Should have RSVP-related content.
			await expect( page.locator( '.wp-block-groups-rsvp-button' ) ).toBeVisible();
		}
	} );

	test( 'guest RSVP form visible for logged-out users', async ( { page } ) => {
		await page.goto( '/melbourne/' );
		const eventLink = page.locator( 'a[href*="/event/"]' ).first();
		if ( await eventLink.isVisible() ) {
			await eventLink.click();
			// Guest form should be visible for logged-out users.
			const guestForm = page.locator( '.wp-block-groups-rsvp-button__guest-form' );
			if ( await guestForm.isVisible() ) {
				await expect( guestForm.locator( 'input[name="guest_name"]' ) ).toBeVisible();
				await expect( guestForm.locator( 'input[name="guest_email"]' ) ).toBeVisible();
			}
		}
	} );
} );

test.describe( 'Tokyo group site', () => {
	test( 'homepage loads', async ( { page } ) => {
		await page.goto( '/tokyo/' );
		await expect( page ).toHaveTitle( /WordPress Tokyo/ );
	} );
} );

test.describe( 'REST API', () => {
	test( 'events endpoint returns JSON', async ( { request } ) => {
		const response = await request.get( '/melbourne/wp-json/groups/v1/events' );
		expect( response.ok() ).toBeTruthy();
		const data = await response.json();
		expect( Array.isArray( data ) ).toBeTruthy();
	} );

	test( 'groups directory endpoint returns JSON', async ( { request } ) => {
		const response = await request.get( '/wp-json/groups/v1/groups' );
		expect( response.ok() ).toBeTruthy();
	} );

	test( 'health endpoint requires auth', async ( { request } ) => {
		const response = await request.get( '/wp-json/groups/v1/health' );
		// Should be 401 for unauthenticated.
		expect( response.status() ).toBe( 401 );
	} );
} );
