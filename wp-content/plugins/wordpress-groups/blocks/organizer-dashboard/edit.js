/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Edit component for the Organizer Dashboard block.
 *
 * Uses ServerSideRender to display the same PHP-rendered output in the editor.
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @return {Element} Block edit element.
 */
export default function Edit( { attributes } ) {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<ServerSideRender
				block="groups/organizer-dashboard"
				attributes={ attributes }
				LoadingResponsePlaceholder={ () => (
					<Placeholder
						icon="dashboard"
						label={ __( 'Organizer Dashboard', 'wordpress-groups' ) }
					>
						<Spinner />
					</Placeholder>
				) }
				ErrorResponsePlaceholder={ () => (
					<Placeholder
						icon="dashboard"
						label={ __( 'Organizer Dashboard', 'wordpress-groups' ) }
						instructions={ __(
							'Error loading organizer dashboard preview.',
							'wordpress-groups'
						) }
					/>
				) }
			/>
		</div>
	);
}
