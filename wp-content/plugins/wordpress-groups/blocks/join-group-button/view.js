/**
 * Join Group Button — frontend interactive script.
 *
 * Hydrates the server-rendered container and manages join/leave state via the REST API.
 */

import apiFetch from '@wordpress/api-fetch';
import { createElement, createRoot, useState, useCallback, useRef, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * State constants.
 */
const STATE = {
	NOT_LOGGED_IN: 'not-logged-in',
	NOT_MEMBER: 'not-member',
	MEMBER: 'member',
	LOADING: 'loading',
};

/**
 * Join Group Button component.
 *
 * @param {Object} props
 * @param {string} props.initialState Initial membership state from server render.
 * @param {string} props.loginUrl     Login URL for logged-out users.
 */
export function JoinGroupButton( { initialState, loginUrl } ) {
	const [ state, setState ] = useState( initialState );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ error, setError ] = useState( '' );
	const stateRef = useRef( state );

	useEffect( () => {
		stateRef.current = state;
	}, [ state ] );

	/**
	 * Handle join/leave action.
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

		try {
			if ( stateRef.current === STATE.MEMBER ) {
				await apiFetch( {
					path: '/groups/v1/members/leave',
					method: 'DELETE',
				} );
				setState( STATE.NOT_MEMBER );
			} else {
				await apiFetch( {
					path: '/groups/v1/members/join',
					method: 'POST',
				} );
				setState( STATE.MEMBER );
			}
		} catch ( err ) {
			setError( err.message || __( 'Something went wrong. Please try again.', 'wordpress-groups' ) );
		} finally {
			setIsLoading( false );
		}
	}, [ isLoading, loginUrl ] );

	const currentState = isLoading ? STATE.LOADING : state;

	const buttonText = {
		[ STATE.NOT_LOGGED_IN ]: __( 'Log in to join', 'wordpress-groups' ),
		[ STATE.NOT_MEMBER ]: __( 'Join Group', 'wordpress-groups' ),
		[ STATE.MEMBER ]: __( 'Leave Group', 'wordpress-groups' ),
		[ STATE.LOADING ]: __( 'Updating\u2026', 'wordpress-groups' ),
	};

	return createElement(
		'div',
		{ className: 'wp-block-groups-join-group-button__inner', 'aria-live': 'polite' },
		createElement(
			'button',
			{
				type: 'button',
				className: `wp-block-groups-join-group-button__btn wp-block-groups-join-group-button__btn--${ currentState }`,
				onClick: handleClick,
				disabled: ( state === STATE.NOT_LOGGED_IN && ! loginUrl ) || isLoading,
				'aria-busy': isLoading,
				'aria-label': buttonText[ currentState ],
			},
			isLoading &&
				createElement( 'span', {
					className: 'wp-block-groups-join-group-button__spinner',
					'aria-hidden': 'true',
				} ),
			createElement(
				'span',
				{ className: 'wp-block-groups-join-group-button__label' },
				buttonText[ currentState ]
			)
		),
		error &&
			createElement(
				'p',
				{
					className: 'wp-block-groups-join-group-button__error',
					role: 'alert',
				},
				error
			)
	);
}

/**
 * Hydrate all Join Group button containers on the page.
 */
function init() {
	const containers = document.querySelectorAll( '.wp-block-groups-join-group-button[data-initial-state]' );

	containers.forEach( ( container ) => {
		const initialState = container.dataset.initialState || STATE.NOT_LOGGED_IN;
		const loginUrl = container.dataset.loginUrl || '';

		const root = createRoot( container );
		root.render(
			createElement( JoinGroupButton, {
				initialState,
				loginUrl,
			} )
		);
	} );
}

// Hydrate when the DOM is ready (skip during tests).
if ( typeof document !== 'undefined' ) {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}
