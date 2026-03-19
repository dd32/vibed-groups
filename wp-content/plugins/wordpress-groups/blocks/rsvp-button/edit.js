/**
 * RSVP Button — editor component.
 *
 * Shows a placeholder with event selector in the block editor.
 */

import { createElement } from '@wordpress/element';
import { useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { Placeholder, Spinner, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';

/**
 * Edit component for the RSVP Button block.
 *
 * @param {Object}   props
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 */
export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const { eventId } = attributes;

	// Default to the current post if it is an event.
	const currentPostId = useSelect( ( select ) => {
		const { getCurrentPostId, getCurrentPostType } = select( 'core/editor' );
		const postType = getCurrentPostType();
		return postType === 'event' ? getCurrentPostId() : 0;
	}, [] );

	useEffect( () => {
		if ( ! eventId && currentPostId ) {
			setAttributes( { eventId: currentPostId } );
		}
	}, [ currentPostId, eventId, setAttributes ] );

	return createElement(
		'div',
		blockProps,
		createElement(
			Placeholder,
			{
				icon: 'yes-alt',
				label: __( 'RSVP Button', 'wordpress-groups' ),
				instructions: __(
					'This block displays an interactive RSVP button on the frontend. Set the event ID or place it on an event page to auto-detect.',
					'wordpress-groups'
				),
			},
			createElement( TextControl, {
				label: __( 'Event ID', 'wordpress-groups' ),
				value: eventId || '',
				onChange: ( value ) =>
					setAttributes( { eventId: value ? parseInt( value, 10 ) : 0 } ),
				type: 'number',
				min: 1,
				help: eventId
					? sprintf(
						/* translators: %d: event post ID */
						__( 'Currently set to event #%d.', 'wordpress-groups' ),
						eventId
					  )
					: __( 'Leave empty to use the current event page.', 'wordpress-groups' ),
			} ),
			createElement(
				'div',
				{ className: 'wp-block-groups-rsvp-button__preview' },
				createElement(
					'button',
					{
						type: 'button',
						className: 'wp-block-groups-rsvp-button__btn wp-block-groups-rsvp-button__btn--not-rsvped',
						disabled: true,
					},
					__( 'RSVP', 'wordpress-groups' )
				)
			)
		)
	);
}

// Required for @wordpress/scripts to pick up sprintf.
import { sprintf } from '@wordpress/i18n';
