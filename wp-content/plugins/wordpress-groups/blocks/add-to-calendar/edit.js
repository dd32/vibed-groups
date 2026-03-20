/**
 * Add to Calendar — editor component.
 *
 * Shows a placeholder with event selector in the block editor.
 */

import { createElement } from '@wordpress/element';
import { useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { Placeholder, TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';

/**
 * Edit component for the Add to Calendar block.
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
				icon: 'calendar-alt',
				label: __( 'Add to Calendar', 'wordpress-groups' ),
				instructions: __(
					'This block displays a dropdown with links to add the event to Google Calendar, Outlook, or download an iCal file.',
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
				{ className: 'wp-block-groups-add-to-calendar__preview' },
				createElement(
					'button',
					{
						type: 'button',
						className: 'wp-block-groups-add-to-calendar__btn',
						disabled: true,
						style: {
							border: '2px solid #ddd',
							borderRadius: '4px',
							padding: '10px 24px',
							fontSize: '16px',
							fontWeight: 600,
							cursor: 'default',
							opacity: 0.7,
						},
					},
					__( 'Add to Calendar', 'wordpress-groups' ),
					' \u25BE'
				)
			)
		)
	);
}
