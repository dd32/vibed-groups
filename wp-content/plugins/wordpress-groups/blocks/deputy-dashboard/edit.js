/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Edit component for the Deputy Dashboard block.
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
				block="groups/deputy-dashboard"
				attributes={ attributes }
				LoadingResponsePlaceholder={ () => (
					<Placeholder
						icon="superhero-alt"
						label={ __( 'Deputy Dashboard', 'wordpress-groups' ) }
					>
						<Spinner />
					</Placeholder>
				) }
				ErrorResponsePlaceholder={ () => (
					<Placeholder
						icon="superhero-alt"
						label={ __( 'Deputy Dashboard', 'wordpress-groups' ) }
						instructions={ __(
							'Error loading deputy dashboard preview.',
							'wordpress-groups'
						) }
					/>
				) }
			/>
		</div>
	);
}
