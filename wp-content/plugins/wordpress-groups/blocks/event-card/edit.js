/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Edit component for the Event Card block.
 *
 * Uses ServerSideRender to display the same PHP-rendered output in the editor.
 *
 * @param {Object} props               Block props.
 * @param {Object} props.attributes    Block attributes.
 * @param {Object} props.context       Block context.
 * @return {Element} Block edit element.
 */
export default function Edit( { attributes, context } ) {
	const blockProps = useBlockProps();
	const eventId = attributes.eventId || context?.postId || 0;

	if ( ! eventId ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon="calendar-alt"
					label={ __( 'Event Card', 'wordpress-groups' ) }
					instructions={ __(
						'This block displays an event card. Place it in an event template or set an event ID.',
						'wordpress-groups'
					) }
				/>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			<ServerSideRender
				block="groups/event-card"
				attributes={ { eventId } }
				LoadingResponsePlaceholder={ () => (
					<Placeholder
						icon="calendar-alt"
						label={ __( 'Event Card', 'wordpress-groups' ) }
					>
						<Spinner />
					</Placeholder>
				) }
				ErrorResponsePlaceholder={ () => (
					<Placeholder
						icon="calendar-alt"
						label={ __( 'Event Card', 'wordpress-groups' ) }
						instructions={ __(
							'Error loading event card preview.',
							'wordpress-groups'
						) }
					/>
				) }
			/>
		</div>
	);
}
