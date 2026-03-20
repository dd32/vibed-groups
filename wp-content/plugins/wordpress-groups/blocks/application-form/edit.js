/**
 * Application Form — editor component.
 *
 * Shows a placeholder in the block editor since the form is client-rendered.
 */

import { createElement } from '@wordpress/element';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Edit component for the Application Form block.
 */
export default function Edit() {
	const blockProps = useBlockProps();

	return createElement(
		'div',
		blockProps,
		createElement( Placeholder, {
			icon: 'clipboard',
			label: __( 'Application Form', 'wordpress-groups' ),
			instructions: __(
				'This block displays a multi-step application form for organizing a WordPress community group. The form is rendered on the frontend only.',
				'wordpress-groups'
			),
		} )
	);
}
