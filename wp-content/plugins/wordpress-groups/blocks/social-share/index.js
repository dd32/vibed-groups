/**
 * Social Share block — editor registration.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps } from '@wordpress/block-editor';
import metadata from './block.json';
import './style.scss';

registerBlockType( metadata.name, {
	edit() {
		const blockProps = useBlockProps();
		return (
			<div { ...blockProps }>
				<p style={ { color: '#757575', fontStyle: 'italic' } }>
					Social Share — rendered on the front end.
				</p>
			</div>
		);
	},
} );
