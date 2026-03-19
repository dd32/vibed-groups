/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

/**
 * Edit component for the Venue Map block.
 *
 * Shows a placeholder in the editor with the venue name.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @param {Object} props.context    Block context.
 * @return {Element} Block edit element.
 */
export default function Edit( { attributes, context } ) {
	const blockProps = useBlockProps();

	const postId = context?.postId || 0;
	const postType = context?.postType || '';

	// Determine the venue ID from attributes or context.
	const venueId = attributes.venueId || 0;

	// Get venue information for display in the editor.
	const venueName = useSelect(
		( select ) => {
			// If explicit venue ID is set, use it.
			if ( venueId ) {
				const venue = select( 'core' ).getEntityRecord(
					'postType',
					'venue',
					venueId
				);
				return venue?.title?.rendered || '';
			}

			// If in a venue post context, use that post's title.
			if ( postId && postType === 'venue' ) {
				const venue = select( 'core' ).getEntityRecord(
					'postType',
					'venue',
					postId
				);
				return venue?.title?.rendered || '';
			}

			// If in an event post context, try to get the linked venue.
			if ( postId && postType === 'event' ) {
				const event = select( 'core' ).getEntityRecord(
					'postType',
					'event',
					postId
				);
				const eventVenueId = event?.meta?._event_venue_id;
				if ( eventVenueId ) {
					const venue = select( 'core' ).getEntityRecord(
						'postType',
						'venue',
						eventVenueId
					);
					return venue?.title?.rendered || '';
				}
			}

			return '';
		},
		[ venueId, postId, postType ]
	);

	const label = venueName
		? /* translators: %s: venue name */
		  __( 'Venue Map: ', 'wordpress-groups' ) + venueName
		: __( 'Venue Map', 'wordpress-groups' );

	return (
		<div { ...blockProps }>
			<Placeholder
				icon="location-alt"
				label={ label }
				instructions={
					venueName
						? __(
								'The map will display on the frontend when the venue has coordinates.',
								'wordpress-groups'
						  )
						: __(
								'This block displays a venue map. Place it in a venue or event template.',
								'wordpress-groups'
						  )
				}
			/>
		</div>
	);
}
