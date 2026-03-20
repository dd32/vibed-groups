/**
 * Group Directory — editor component.
 *
 * Shows a placeholder in the block editor since the directory is client-rendered.
 */

import { createElement } from '@wordpress/element';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Edit component for the Group Directory block.
 */
export default function Edit() {
	const blockProps = useBlockProps();

	return createElement(
		'div',
		blockProps,
		createElement( Placeholder, {
			icon: 'groups',
			label: __( 'Group Directory', 'wordpress-groups' ),
			instructions: __(
				'This block displays a searchable directory of WordPress community groups. The directory is rendered on the frontend only.',
				'wordpress-groups'
			),
		} )
	);
}
