/**
 * WordPress dependencies.
 */
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl, Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Edit component for the Group Directory block.
 *
 * Displays a placeholder in the editor since the directory requires
 * network-wide data that is only available on the frontend.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {Element} Block edit element.
 */
export default function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const { perPage } = attributes;

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Directory Settings', 'wordpress-groups' ) }>
					<RangeControl
						label={ __( 'Groups per page', 'wordpress-groups' ) }
						value={ perPage }
						onChange={ ( value ) => setAttributes( { perPage: value } ) }
						min={ 4 }
						max={ 48 }
						step={ 4 }
					/>
				</PanelBody>
			</InspectorControls>

			<Placeholder
				icon="groups"
				label={ __( 'Group Directory', 'wordpress-groups' ) }
				instructions={ __(
					'Displays a searchable, paginated directory of all active WordPress community groups. Configure the number of groups per page in the block settings.',
					'wordpress-groups'
				) }
			>
				<div className="wp-block-groups-group-directory__editor-preview">
					<div className="wp-block-groups-group-directory__editor-search-preview" />
					<div className="wp-block-groups-group-directory__editor-grid-preview">
						{ Array.from( { length: Math.min( perPage, 6 ) } ).map( ( _, i ) => (
							<div key={ i } className="wp-block-groups-group-directory__editor-card-preview" />
						) ) }
					</div>
				</div>
			</Placeholder>
		</div>
	);
}
