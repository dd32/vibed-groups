/**
 * Notification Preferences — editor component.
 *
 * Shows a placeholder preview in the block editor.
 */

import { createElement } from '@wordpress/element';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Edit component for the Notification Preferences block.
 */
export default function Edit() {
	const blockProps = useBlockProps();

	return createElement(
		'div',
		blockProps,
		createElement(
			Placeholder,
			{
				icon: 'bell',
				label: __( 'Notification Preferences', 'wordpress-groups' ),
				instructions: __(
					'This block displays notification preference toggles for logged-in users on the frontend.',
					'wordpress-groups'
				),
			},
			createElement(
				'div',
				{ className: 'wp-block-groups-notification-preferences__preview' },
				createElement(
					'div',
					{ className: 'wp-block-groups-notification-preferences__item' },
					createElement( 'span', null, __( 'Event Reminders', 'wordpress-groups' ) ),
					createElement(
						'span',
						{ className: 'wp-block-groups-notification-preferences__toggle-preview' },
						__( 'On', 'wordpress-groups' )
					)
				),
				createElement(
					'div',
					{ className: 'wp-block-groups-notification-preferences__item' },
					createElement( 'span', null, __( 'Announcements', 'wordpress-groups' ) ),
					createElement(
						'span',
						{ className: 'wp-block-groups-notification-preferences__toggle-preview' },
						__( 'On', 'wordpress-groups' )
					)
				),
				createElement(
					'div',
					{ className: 'wp-block-groups-notification-preferences__item' },
					createElement( 'span', null, __( 'RSVP Confirmations', 'wordpress-groups' ) ),
					createElement(
						'span',
						{ className: 'wp-block-groups-notification-preferences__toggle-preview' },
						__( 'On', 'wordpress-groups' )
					)
				)
			)
		)
	);
}
