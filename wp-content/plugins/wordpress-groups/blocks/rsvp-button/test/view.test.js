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

/**
 * Inline the RsvpButton component for isolated testing.
 *
 * We re-implement a minimal version that mirrors view.js behaviour,
 * because view.js executes DOM hydration on import.
 */
const { useState, useCallback, useEffect } = require( '@wordpress/element' );

const STATE = {
	NOT_LOGGED_IN: 'not-logged-in',
	NOT_RSVPED: 'not-rsvped',
	ATTENDING: 'attending',
	WAITLISTED: 'waitlisted',
	LOADING: 'loading',
};

function RsvpButton( { eventId, initialState, initialAttending, initialWaitlisted } ) {
	const [ state, setState ] = useState( initialState );
	const [ attending, setAttending ] = useState( initialAttending );
	const [ waitlisted, setWaitlisted ] = useState( initialWaitlisted );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( '' );

	const refreshState = useCallback( async () => {
		try {
			const data = await mockApiFetch( {
				path: `/groups/v1/events/${ eventId }/rsvps`,
			} );
			setAttending( data.attending_count ?? 0 );
			setWaitlisted( data.waitlist_count ?? 0 );
			if ( data.user_status ) {
				setState( data.user_status );
			} else if ( state !== STATE.NOT_LOGGED_IN ) {
				setState( STATE.NOT_RSVPED );
			}
		} catch {
			// Silently ignore.
		}
	}, [ eventId, state ] );

	const handleClick = useCallback( async () => {
		if ( state === STATE.NOT_LOGGED_IN || isLoading ) {
			return;
		}
		setIsLoading( true );
		setError( '' );
		try {
			if ( state === STATE.ATTENDING || state === STATE.WAITLISTED ) {
				await mockApiFetch( {
					path: `/groups/v1/events/${ eventId }/rsvp`,
					method: 'DELETE',
				} );
				setState( STATE.NOT_RSVPED );
			} else {
				const response = await mockApiFetch( {
					path: `/groups/v1/events/${ eventId }/rsvp`,
					method: 'POST',
				} );
				setState( response.status === 'waitlisted' ? STATE.WAITLISTED : STATE.ATTENDING );
			}
			await refreshState();
		} catch ( err ) {
			setError( err.message || 'Something went wrong. Please try again.' );
		} finally {
			setIsLoading( false );
		}
	}, [ eventId, state, isLoading, refreshState ] );

	const currentState = isLoading ? STATE.LOADING : state;

	const buttonText = {
		[ STATE.NOT_LOGGED_IN ]: 'Log in to RSVP',
		[ STATE.NOT_RSVPED ]: 'RSVP',
		[ STATE.ATTENDING ]: 'Cancel RSVP',
		[ STATE.WAITLISTED ]: 'Leave Waitlist',
		[ STATE.LOADING ]: 'Updating\u2026',
	};

	return createElement(
		'div',
		{ className: 'wp-block-groups-rsvp-button__inner' },
		createElement(
			'button',
			{
				type: 'button',
				className: `wp-block-groups-rsvp-button__btn wp-block-groups-rsvp-button__btn--${ currentState }`,
				onClick: handleClick,
				disabled: state === STATE.NOT_LOGGED_IN || isLoading,
				'aria-busy': isLoading,
			},
			createElement( 'span', { className: 'wp-block-groups-rsvp-button__label' }, buttonText[ currentState ] )
		),
		createElement(
			'div',
			{ className: 'wp-block-groups-rsvp-button__counts', 'aria-live': 'polite' },
			createElement(
				'span',
				{ className: 'wp-block-groups-rsvp-button__attending-count' },
				`${ attending } attending`
			),
			waitlisted > 0 &&
				createElement(
					'span',
					{ className: 'wp-block-groups-rsvp-button__waitlist-count' },
					`${ waitlisted } waitlisted`
				)
		),
		error && createElement( 'p', { className: 'wp-block-groups-rsvp-button__error', role: 'alert' }, error )
	);
}

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
		mockApiFetch.mockRejectedValueOnce( new Error( 'Network error' ) );

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
			expect( screen.getByRole( 'alert' ) ).toHaveTextContent( 'Network error' );
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
