/**
 * Take screenshots of all key pages for the README.
 *
 * Usage: node bin/take-screenshots.js
 * Requires: wp-env running (npm run env:start)
 */

const { chromium } = require( '@playwright/test' );
const path = require( 'path' );

const BASE = process.env.WP_BASE_URL || 'http://localhost:8889';
const OUT = path.join( __dirname, '..', 'docs', 'screenshots' );

const pages = [
	{ name: '01-directory-homepage', url: '/', desc: 'Main directory — group discovery' },
	{ name: '02-melbourne-homepage', url: '/melbourne/', desc: 'Melbourne group homepage' },
	{ name: '03-melbourne-events', url: '/melbourne/events/', desc: 'Events listing' },
	{ name: '04-melbourne-members', url: '/melbourne/members/', desc: 'Members page' },
	{ name: '05-melbourne-about', url: '/melbourne/about/', desc: 'About page' },
	{ name: '06-tokyo-homepage', url: '/tokyo/', desc: 'Tokyo group homepage' },
];

( async () => {
	const browser = await chromium.launch();
	const context = await browser.newContext( { viewport: { width: 1280, height: 900 } } );

	for ( const p of pages ) {
		const page = await context.newPage();
		try {
			await page.goto( BASE + p.url, { waitUntil: 'networkidle', timeout: 15000 } );
			await page.screenshot( { path: path.join( OUT, p.name + '.png' ), fullPage: true } );
			console.log( `✓ ${ p.name } — ${ p.desc }` );
		} catch ( e ) {
			console.log( `✗ ${ p.name } — ${ e.message.slice( 0, 80 ) }` );
		}
		await page.close();
	}

	// Single event page — find first event link on Melbourne.
	const eventPage = await context.newPage();
	try {
		await eventPage.goto( BASE + '/melbourne/', { waitUntil: 'networkidle', timeout: 15000 } );
		const link = await eventPage.locator( 'a[href*="/event/"]' ).first().getAttribute( 'href' );
		if ( link ) {
			await eventPage.goto( link.startsWith( 'http' ) ? link : BASE + link, { waitUntil: 'networkidle', timeout: 15000 } );
			await eventPage.screenshot( { path: path.join( OUT, '07-single-event.png' ), fullPage: true } );
			console.log( '✓ 07-single-event — Single event with RSVP' );
		}
	} catch ( e ) {
		console.log( `✗ 07-single-event — ${ e.message.slice( 0, 80 ) }` );
	}
	await eventPage.close();

	// Mobile view.
	const mobile = await browser.newContext( { viewport: { width: 375, height: 812 } } );
	const mobilePage = await mobile.newPage();
	try {
		await mobilePage.goto( BASE + '/melbourne/', { waitUntil: 'networkidle', timeout: 15000 } );
		await mobilePage.screenshot( { path: path.join( OUT, '08-mobile-view.png' ), fullPage: true } );
		console.log( '✓ 08-mobile-view — Mobile responsive' );
	} catch ( e ) {
		console.log( `✗ 08-mobile-view — ${ e.message.slice( 0, 80 ) }` );
	}

	await browser.close();
	console.log( `\nScreenshots saved to docs/screenshots/` );
} )();
