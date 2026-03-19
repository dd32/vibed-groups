/**
 * Tests for the RSVP Button view script.
 */

/* global jest, describe, it, expect, beforeEach, afterEach */

import { createElement } from '@wordpress/element';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';

// Mock @wordpress/api-fetch.
const mockApiFetch = jest.fn();
jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: ( ...args ) => mockApiFetch( ...args ),
} ) );

// Mock @wordpress/i18n.
jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
	_n: ( single, plural, count ) => ( count === 1 ? single : plural ),
	sprintf: ( format, ...args ) => {
		let i = 0;
		return format.replace( /%[ds]/g, () => args[ i++ ] );
	},
} ) );

// Import the actual component from view.js.
import { RsvpButton } from '../view.js';

describe( 'RsvpButton', () => {
	beforeEach( () => {
		mockApiFetch.mockReset();
	} );

	it( 'renders initial state with correct button text and counts', () => {
		render(
			createElement( RsvpButton, {
				eventId: 42,
				initialState: 'not-rsvped',
				initialAttending: 5,
				initialWaitlisted: 2,
			} )
		);

		expect( screen.getByText( 'RSVP' ) ).toBeInTheDocument();
		expect( screen.getByText( '5 attending' ) ).toBeInTheDocument();
		expect( screen.getByText( '2 waitlisted' ) ).toBeInTheDocument();
	} );

	it( 'renders disabled button when not logged in', () => {
		render(
			createElement( RsvpButton, {
				eventId: 42,
				initialState: 'not-logged-in',
				initialAttending: 3,
				initialWaitlisted: 0,
			} )
		);

		const button = screen.getByRole( 'button' );
		expect( button ).toBeDisabled();
		expect( screen.getByText( 'Log in to RSVP' ) ).toBeInTheDocument();
	} );

	it( 'sends POST request when RSVP button is clicked', async () => {
		mockApiFetch
			.mockResolvedValueOnce( { status: 'attending' } ) // POST rsvp
			.mockResolvedValueOnce( { attending_count: 6, waitlist_count: 0, user_status: 'attending' } ); // GET refresh

		render(
			createElement( RsvpButton, {
				eventId: 42,
				initialState: 'not-rsvped',
				initialAttending: 5,
				initialWaitlisted: 0,
			} )
		);

		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalledWith( {
				path: '/groups/v1/events/42/rsvp',
				method: 'POST',
			} );
		} );
	} );

	it( 'sends DELETE request when cancelling RSVP', async () => {
		mockApiFetch
			.mockResolvedValueOnce( {} ) // DELETE rsvp
			.mockResolvedValueOnce( { attending_count: 4, waitlist_count: 0 } ); // GET refresh

		render(
			createElement( RsvpButton, {
				eventId: 42,
				initialState: 'attending',
				initialAttending: 5,
				initialWaitlisted: 0,
			} )
		);

		expect( screen.getByText( 'Cancel RSVP' ) ).toBeInTheDocument();

		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalledWith( {
				path: '/groups/v1/events/42/rsvp',
				method: 'DELETE',
			} );
		} );
	} );

	it( 'shows error message on API failure', async () => {
		// First call is refreshState on mount (resolve it), second is the POST (reject it).
		mockApiFetch
			.mockResolvedValueOnce( { attending_count: 5, waitlist_count: 0 } )
			.mockRejectedValueOnce( new Error( 'Network error' ) );

		render(
			createElement( RsvpButton, {
				eventId: 42,
				initialState: 'not-rsvped',
				initialAttending: 5,
				initialWaitlisted: 0,
			} )
		);

		// Wait for mount refresh to complete.
		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalledTimes( 1 );
		} );

		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			const alert = screen.getByRole( 'alert' );
			expect( alert ).toBeInTheDocument();
			expect( alert.textContent ).toContain( 'Network error' );
		} );
	} );

	it( 'hides waitlist count when zero', () => {
		render(
			createElement( RsvpButton, {
				eventId: 42,
				initialState: 'not-rsvped',
				initialAttending: 3,
				initialWaitlisted: 0,
			} )
		);

		expect( screen.queryByText( /waitlisted/ ) ).toBeNull();
	} );
} );
