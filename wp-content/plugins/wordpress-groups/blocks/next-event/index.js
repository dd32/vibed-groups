/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import metadata from './block.json';
import './style.scss';

registerBlockType( metadata.name, {
	edit() {
		const blockProps = useBlockProps();

		return (
			<div { ...blockProps }>
				<Placeholder
					icon="calendar-alt"
					label={ __( 'Next Event', 'wordpress-groups' ) }
					instructions={ __(
						'Displays a callout banner for the next upcoming event.',
						'wordpress-groups'
					) }
				/>
			</div>
		);
	},
} );
