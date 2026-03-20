/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder, Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import ServerSideRender from '@wordpress/server-side-render';

/**
 * Edit component for the Group Stats block.
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
				block="groups/group-stats"
				attributes={ attributes }
				LoadingResponsePlaceholder={ () => (
					<Placeholder
						icon="chart-bar"
						label={ __( 'Group Stats', 'wordpress-groups' ) }
					>
						<Spinner />
					</Placeholder>
				) }
				ErrorResponsePlaceholder={ () => (
					<Placeholder
						icon="chart-bar"
						label={ __( 'Group Stats', 'wordpress-groups' ) }
						instructions={ __(
							'Error loading group stats preview.',
							'wordpress-groups'
						) }
					/>
				) }
			/>
		</div>
	);
}
