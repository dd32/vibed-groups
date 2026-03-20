/**
 * WordPress dependencies.
 */
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl, Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Edit component for the Event Directory block.
 *
 * Renders a placeholder in the editor with a settings panel for per-page count.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {Element} Block edit element.
 */
export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Settings', 'wordpress-groups' ) }>
					<RangeControl
						label={ __( 'Events per page', 'wordpress-groups' ) }
						value={ attributes.perPage }
						onChange={ ( value ) =>
							setAttributes( { perPage: value } )
						}
						min={ 1 }
						max={ 50 }
					/>
				</PanelBody>
			</InspectorControls>
			<Placeholder
				icon="calendar"
				label={ __( 'Event Directory', 'wordpress-groups' ) }
				instructions={ __(
					'Displays a filterable event directory with calendar and list views. Configure events per page in the block settings.',
					'wordpress-groups'
				) }
			/>
		</div>
	);
}
