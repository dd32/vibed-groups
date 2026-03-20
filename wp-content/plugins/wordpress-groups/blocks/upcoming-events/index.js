/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl, Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import metadata from './block.json';

registerBlockType( metadata.name, {
	edit( { attributes, setAttributes } ) {
		const blockProps = useBlockProps();

		return (
			<div { ...blockProps }>
				<InspectorControls>
					<PanelBody title={ __( 'Settings', 'wordpress-groups' ) }>
						<RangeControl
							label={ __( 'Number of events', 'wordpress-groups' ) }
							value={ attributes.count }
							onChange={ ( count ) => setAttributes( { count } ) }
							min={ 1 }
							max={ 20 }
						/>
					</PanelBody>
				</InspectorControls>
				<Placeholder
					icon="calendar"
					label={ __( 'Upcoming Events', 'wordpress-groups' ) }
					instructions={ __( 'Displays upcoming scheduled events.', 'wordpress-groups' ) }
				/>
			</div>
		);
	},
} );
