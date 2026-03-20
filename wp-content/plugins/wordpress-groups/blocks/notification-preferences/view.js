/**
 * Notification Preferences — frontend interactive script.
 *
 * Hydrates the server-rendered toggle switches and saves preferences
 * via the REST API.
 */

import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Debounce helper — delays function execution until after a pause.
 *
 * @param {Function} fn    Function to debounce.
 * @param {number}   delay Delay in milliseconds.
 * @return {Function} Debounced function.
 */
function debounce( fn, delay ) {
	let timer;
	return ( ...args ) => {
		clearTimeout( timer );
		timer = setTimeout( () => fn( ...args ), delay );
	};
}

/**
 * Initialise a single notification preferences block.
 *
 * @param {HTMLElement} container The block root element.
 */
function initBlock( container ) {
	const prefsData = container.dataset.preferences;
	const preferences = prefsData ? JSON.parse( prefsData ) : {};
	const statusEl = container.querySelector(
		'.wp-block-groups-notification-preferences__status'
	);
	const toggles = container.querySelectorAll(
		'.wp-block-groups-notification-preferences__toggle'
	);

	/**
	 * Show a temporary status message.
	 *
	 * @param {string} message  The message to display.
	 * @param {string} type     'success' or 'error'.
	 */
	function showStatus( message, type ) {
		if ( ! statusEl ) {
			return;
		}
		statusEl.textContent = message;
		statusEl.className =
			'wp-block-groups-notification-preferences__status wp-block-groups-notification-preferences__status--' +
			type;

		if ( type === 'success' ) {
			setTimeout( () => {
				statusEl.textContent = '';
				statusEl.className =
					'wp-block-groups-notification-preferences__status';
			}, 3000 );
		}
	}

	/**
	 * Save a single preference to the server.
	 *
	 * @param {string}  type    Notification type key.
	 * @param {boolean} enabled Whether the notification is enabled.
	 */
	const savePreference = debounce( async ( type, enabled ) => {
		try {
			await apiFetch( {
				path: '/groups/v1/preferences',
				method: 'PUT',
				data: { [ type ]: enabled },
			} );
			showStatus(
				__( 'Preferences saved.', 'wordpress-groups' ),
				'success'
			);
		} catch ( err ) {
			showStatus(
				err.message ||
					__(
						'Failed to save preferences. Please try again.',
						'wordpress-groups'
					),
				'error'
			);
		}
	}, 300 );

	toggles.forEach( ( toggle ) => {
		toggle.addEventListener( 'click', () => {
			const item = toggle.closest(
				'.wp-block-groups-notification-preferences__item'
			);
			const type = item?.dataset.type;
			if ( ! type ) {
				return;
			}

			const isCurrentlyChecked = toggle.getAttribute( 'aria-checked' ) === 'true';
			const newValue = ! isCurrentlyChecked;

			// Update the UI immediately.
			toggle.setAttribute( 'aria-checked', String( newValue ) );
			toggle.classList.toggle( 'is-checked', newValue );

			// Update local state.
			preferences[ type ] = newValue;

			// Save to server.
			savePreference( type, newValue );
		} );
	} );
}

/**
 * Initialise all notification preference blocks on the page.
 */
function init() {
	const containers = document.querySelectorAll(
		'.wp-block-groups-notification-preferences[data-preferences]'
	);
	containers.forEach( initBlock );
}

// Hydrate when the DOM is ready (skip during tests).
if ( typeof document !== 'undefined' ) {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}
