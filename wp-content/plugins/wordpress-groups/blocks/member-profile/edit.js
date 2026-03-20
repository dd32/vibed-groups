/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Edit component for the Member Profile block.
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
				block="groups/member-profile"
				attributes={ attributes }
				LoadingResponsePlaceholder={ () => (
					<Placeholder
						icon="admin-users"
						label={ __( 'Member Profile', 'wordpress-groups' ) }
					>
						<Spinner />
					</Placeholder>
				) }
				ErrorResponsePlaceholder={ () => (
					<Placeholder
						icon="admin-users"
						label={ __( 'Member Profile', 'wordpress-groups' ) }
						instructions={ __(
							'Error loading member profile preview.',
							'wordpress-groups'
						) }
					/>
				) }
				EmptyResponsePlaceholder={ () => (
					<Placeholder
						icon="admin-users"
						label={ __( 'Member Profile', 'wordpress-groups' ) }
						instructions={ __(
							'No member found. This block displays the profile of the current author archive.',
							'wordpress-groups'
						) }
					/>
				) }
			/>
		</div>
	);
}
