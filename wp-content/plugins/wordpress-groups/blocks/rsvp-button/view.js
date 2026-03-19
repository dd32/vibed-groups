/**
 * RSVP Button — frontend interactive script.
 *
 * Hydrates the server-rendered container and manages RSVP state via the REST API.
 */

import apiFetch from '@wordpress/api-fetch';
import { createElement, render, useState, useEffect, useCallback } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * State constants.
 */
const STATE = {
	NOT_LOGGED_IN: 'not-logged-in',
	NOT_RSVPED: 'not-rsvped',
	ATTENDING: 'attending',
	WAITLISTED: 'waitlisted',
	LOADING: 'loading',
};

/**
 * RSVP Button component.
 *
 * @param {Object} props
 * @param {number} props.eventId       Event post ID.
 * @param {string} props.initialState  Initial RSVP state from server render.
 * @param {number} props.initialAttending  Initial attending count.
 * @param {number} props.initialWaitlisted Initial waitlist count.
 */
function RsvpButton( { eventId, initialState, initialAttending, initialWaitlisted } ) {
	const [ state, setState ] = useState( initialState );
	const [ attending, setAttending ] = useState( initialAttending );
	const [ waitlisted, setWaitlisted ] = useState( initialWaitlisted );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( '' );

	/**
	 * Refresh RSVP data from the server.
	 */
	const refreshState = useCallback( async () => {
		try {
			const data = await apiFetch( {
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
			// Silently fall back to server-rendered state on fetch failure.
		}
	}, [ eventId, state ] );

	// Refresh state on mount if the user is logged in.
	useEffect( () => {
		if ( state !== STATE.NOT_LOGGED_IN ) {
			refreshState();
		}
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	/**
	 * Handle RSVP action (toggle between RSVP and cancel).
	 */
	const handleClick = useCallback( async () => {
		if ( state === STATE.NOT_LOGGED_IN || isLoading ) {
			return;
		}

		setIsLoading( true );
		setError( '' );

		try {
			if ( state === STATE.ATTENDING || state === STATE.WAITLISTED ) {
				// Cancel RSVP.
				await apiFetch( {
					path: `/groups/v1/events/${ eventId }/rsvp`,
					method: 'DELETE',
				} );

				setState( STATE.NOT_RSVPED );
			} else {
				// Create RSVP.
				const response = await apiFetch( {
					path: `/groups/v1/events/${ eventId }/rsvp`,
					method: 'POST',
				} );

				setState( response.status === 'waitlisted' ? STATE.WAITLISTED : STATE.ATTENDING );
			}

			await refreshState();
		} catch ( err ) {
			setError( err.message || __( 'Something went wrong. Please try again.', 'wordpress-groups' ) );
		} finally {
			setIsLoading( false );
		}
	}, [ eventId, state, isLoading, refreshState ] );

	const currentState = isLoading ? STATE.LOADING : state;

	const buttonText = {
		[ STATE.NOT_LOGGED_IN ]: __( 'Log in to RSVP', 'wordpress-groups' ),
		[ STATE.NOT_RSVPED ]: __( 'RSVP', 'wordpress-groups' ),
		[ STATE.ATTENDING ]: __( 'Cancel RSVP', 'wordpress-groups' ),
		[ STATE.WAITLISTED ]: __( 'Leave Waitlist', 'wordpress-groups' ),
		[ STATE.LOADING ]: __( 'Updating\u2026', 'wordpress-groups' ),
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
				'aria-label': buttonText[ currentState ],
			},
			isLoading &&
				createElement( 'span', {
					className: 'wp-block-groups-rsvp-button__spinner',
					'aria-hidden': 'true',
				} ),
			createElement(
				'span',
				{ className: 'wp-block-groups-rsvp-button__label' },
				buttonText[ currentState ]
			)
		),
		createElement(
			'div',
			{
				className: 'wp-block-groups-rsvp-button__counts',
				'aria-live': 'polite',
				'aria-atomic': 'true',
			},
			createElement(
				'span',
				{ className: 'wp-block-groups-rsvp-button__attending-count' },
				sprintf(
					/* translators: %d: number of attendees */
					_n( '%d attending', '%d attending', attending, 'wordpress-groups' ),
					attending
				)
			),
			waitlisted > 0 &&
				createElement(
					'span',
					{ className: 'wp-block-groups-rsvp-button__waitlist-count' },
					sprintf(
						/* translators: %d: number of people on waitlist */
						_n( '%d waitlisted', '%d waitlisted', waitlisted, 'wordpress-groups' ),
						waitlisted
					)
				)
		),
		error &&
			createElement(
				'p',
				{
					className: 'wp-block-groups-rsvp-button__error',
					role: 'alert',
				},
				error
			)
	);
}

/**
 * Hydrate all RSVP button containers on the page.
 */
function init() {
	const containers = document.querySelectorAll( '.wp-block-groups-rsvp-button[data-event-id]' );

	containers.forEach( ( container ) => {
		const eventId = parseInt( container.dataset.eventId, 10 );
		const initialState = container.dataset.initialState || STATE.NOT_LOGGED_IN;
		const initialAttending = parseInt( container.dataset.attending, 10 ) || 0;
		const initialWaitlisted = parseInt( container.dataset.waitlisted, 10 ) || 0;

		render(
			createElement( RsvpButton, {
				eventId,
				initialState,
				initialAttending,
				initialWaitlisted,
			} ),
			container
		);
	} );
}

// Hydrate when the DOM is ready.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', init );
} else {
	init();
}
