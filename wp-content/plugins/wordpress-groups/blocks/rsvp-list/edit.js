/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Edit component for the RSVP List block.
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
					icon="groups"
					label={ __( 'RSVP List', 'wordpress-groups' ) }
					instructions={ __(
						'This block displays the RSVP list for an event. Place it in an event template or set an event ID.',
						'wordpress-groups'
					) }
				/>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			<ServerSideRender
				block="groups/rsvp-list"
				attributes={ { eventId } }
				LoadingResponsePlaceholder={ () => (
					<Placeholder
						icon="groups"
						label={ __( 'RSVP List', 'wordpress-groups' ) }
					>
						<Spinner />
					</Placeholder>
				) }
				ErrorResponsePlaceholder={ () => (
					<Placeholder
						icon="groups"
						label={ __( 'RSVP List', 'wordpress-groups' ) }
						instructions={ __(
							'Error loading RSVP list preview.',
							'wordpress-groups'
						) }
					/>
				) }
			/>
		</div>
	);
}
