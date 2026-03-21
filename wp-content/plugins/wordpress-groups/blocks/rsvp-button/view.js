/**
 * RSVP Button — frontend interactive script.
 *
 * Hydrates the server-rendered container and manages RSVP state via the REST API.
 */

import apiFetch from '@wordpress/api-fetch';
import { createElement, createRoot, useState, useEffect, useCallback, useRef } from '@wordpress/element';
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
 * Check whether a REST API error indicates an expired session (401/403).
 *
 * @param {Object} err Error object from apiFetch.
 * @return {boolean} True when the error is an authentication failure.
 */
function isSessionExpiredError( err ) {
	const code = err?.code || err?.data?.status || err?.status;
	return (
		code === 401 ||
		code === 403 ||
		code === 'rest_not_logged_in' ||
		code === 'rest_forbidden'
	);
}

/**
 * RSVP Button component.
 *
 * @param {Object} props
 * @param {number} props.eventId       Event post ID.
 * @param {string} props.initialState  Initial RSVP state from server render.
 * @param {number} props.initialAttending  Initial attending count.
 * @param {number} props.initialWaitlisted Initial waitlist count.
 * @param {string} props.loginUrl      Login URL for logged-out users.
 */
export function RsvpButton( { eventId, initialState, initialAttending, initialWaitlisted, loginUrl } ) {
	const [ state, setState ] = useState( initialState );
	const [ attending, setAttending ] = useState( initialAttending );
	const [ waitlisted, setWaitlisted ] = useState( initialWaitlisted );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const [ sessionExpired, setSessionExpired ] = useState( false );
	const stateRef = useRef( state );

	// Keep the ref in sync with state.
	useEffect( () => {
		stateRef.current = state;
	}, [ state ] );

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
			} else if ( stateRef.current !== STATE.NOT_LOGGED_IN ) {
				setState( STATE.NOT_RSVPED );
			}
		} catch {
			// Silently fall back to server-rendered state on fetch failure.
		}
	}, [ eventId ] );

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
		if ( stateRef.current === STATE.NOT_LOGGED_IN ) {
			if ( loginUrl ) {
				window.location.href = loginUrl;
			}
			return;
		}

		if ( isLoading ) {
			return;
		}

		setIsLoading( true );
		setError( '' );
		setSessionExpired( false );

		try {
			if ( stateRef.current === STATE.ATTENDING || stateRef.current === STATE.WAITLISTED ) {
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
			if ( isSessionExpiredError( err ) ) {
				setState( STATE.NOT_LOGGED_IN );
				setSessionExpired( true );
				setError( __( 'Session expired \u2014 please log in again.', 'wordpress-groups' ) );
			} else {
				setError( err.message || __( 'Something went wrong. Please try again.', 'wordpress-groups' ) );
			}
		} finally {
			setIsLoading( false );
		}
	}, [ eventId, isLoading, loginUrl, refreshState ] );

	const currentState = isLoading ? STATE.LOADING : state;

	const buttonText = {
		[ STATE.NOT_LOGGED_IN ]: __( 'Log in to RSVP', 'wordpress-groups' ),
		[ STATE.NOT_RSVPED ]: __( 'RSVP', 'wordpress-groups' ),
		[ STATE.ATTENDING ]: __( 'Cancel RSVP', 'wordpress-groups' ),
		[ STATE.WAITLISTED ]: __( 'Leave Waitlist', 'wordpress-groups' ),
		[ STATE.LOADING ]: __( 'Updating\u2026', 'wordpress-groups' ),
	};

	const reLoginUrl = loginUrl || '/wp-login.php';

	return createElement(
		'div',
		{ className: 'wp-block-groups-rsvp-button__inner' },
		createElement(
			'button',
			{
				type: 'button',
				className: `wp-block-groups-rsvp-button__btn wp-block-groups-rsvp-button__btn--${ currentState }`,
				onClick: handleClick,
				disabled: ( state === STATE.NOT_LOGGED_IN && ! loginUrl ) || isLoading,
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
		error && sessionExpired
			? createElement(
				'p',
				{
					className: 'wp-block-groups-rsvp-button__error wp-block-groups-rsvp-button__session-expired',
					role: 'alert',
				},
				__( 'Session expired \u2014 ', 'wordpress-groups' ),
				createElement(
					'a',
					{ href: reLoginUrl, className: 'wp-block-groups-rsvp-button__login-link' },
					__( 'please log in again', 'wordpress-groups' )
				)
			)
			: error &&
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
		const loginUrl = container.dataset.loginUrl || '';

		const root = createRoot( container );
		root.render(
			createElement( RsvpButton, {
				eventId,
				initialState,
				initialAttending,
				initialWaitlisted,
				loginUrl,
			} )
		);
	} );
}

/**
 * Handle guest RSVP forms (no React — plain DOM).
 */
function initGuestForms() {
	document.querySelectorAll( '.wp-block-groups-rsvp-button__guest-form' ).forEach( ( form ) => {
		form.addEventListener( 'submit', async ( e ) => {
			e.preventDefault();
			const btn = form.querySelector( 'button[type="submit"]' );
			const eventId = form.dataset.eventId;
			const name = form.querySelector( 'input[name="guest_name"]' ).value;
			const email = form.querySelector( 'input[name="guest_email"]' ).value;

			btn.disabled = true;
			btn.textContent = 'Submitting…';

			try {
				const resp = await window.fetch( form.dataset.apiUrl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify( { event_id: parseInt( eventId, 10 ), email, name } ),
				} );
				const data = await resp.json();

				if ( resp.ok ) {
					form.innerHTML = '<p class="wp-block-groups-rsvp-button__guest-success" role="status">✓ ' +
						( data.status === 'waitlisted' ? 'You\'re on the waitlist!' : 'You\'re confirmed!' ) +
						' Check your email.</p>';
				} else {
					btn.disabled = false;
					btn.textContent = 'RSVP as Guest';
					const note = form.querySelector( '.wp-block-groups-rsvp-button__guest-note' );
					if ( note ) {
						note.textContent = data.message || 'Something went wrong.';
						note.style.color = 'var(--wp--preset--color--error-700, #B91C1C)';
					}
				}
			} catch {
				btn.disabled = false;
				btn.textContent = 'RSVP as Guest';
			}
		} );
	} );
}

// Hydrate when the DOM is ready (skip during tests).
if ( typeof document !== 'undefined' ) {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', () => { init(); initGuestForms(); } );
	} else {
		init();
		initGuestForms();
	}
}
