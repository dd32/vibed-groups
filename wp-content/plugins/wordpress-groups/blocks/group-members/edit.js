/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Edit component for the Group Members block.
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
				block="groups/group-members"
				attributes={ attributes }
				LoadingResponsePlaceholder={ () => (
					<Placeholder
						icon="groups"
						label={ __( 'Group Members', 'wordpress-groups' ) }
					>
						<Spinner />
					</Placeholder>
				) }
				ErrorResponsePlaceholder={ () => (
					<Placeholder
						icon="groups"
						label={ __( 'Group Members', 'wordpress-groups' ) }
						instructions={ __(
							'Error loading group members preview.',
							'wordpress-groups'
						) }
					/>
				) }
				EmptyResponsePlaceholder={ () => (
					<Placeholder
						icon="groups"
						label={ __( 'Group Members', 'wordpress-groups' ) }
						instructions={ __(
							'No members found for this group.',
							'wordpress-groups'
						) }
					/>
				) }
			/>
		</div>
	);
}
