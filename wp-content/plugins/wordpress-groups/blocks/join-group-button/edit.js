/**
 * Join Group Button — editor component.
 *
 * Shows a static placeholder preview in the block editor.
 */

import { createElement } from '@wordpress/element';
import { useBlockProps } from '@wordpress/block-editor';
import { Placeholder } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Edit component for the Join Group Button block.
 */
export default function Edit() {
	const blockProps = useBlockProps();

	return createElement(
		'div',
		blockProps,
		createElement(
			Placeholder,
			{
				icon: 'groups',
				label: __( 'Join Group Button', 'wordpress-groups' ),
				instructions: __(
					'This block displays a Join/Leave Group button on the frontend. Logged-out users see a login link.',
					'wordpress-groups'
				),
			},
			createElement(
				'div',
				{ className: 'wp-block-groups-join-group-button__preview' },
				createElement(
					'button',
					{
						type: 'button',
						className: 'wp-block-groups-join-group-button__btn wp-block-groups-join-group-button__btn--not-member',
						disabled: true,
					},
					__( 'Join Group', 'wordpress-groups' )
				)
			)
		)
	);
}
