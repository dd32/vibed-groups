/**
 * WordPress dependencies.
 */
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * Internal dependencies.
 */
import metadata from '../block.json';

// Mock all WordPress packages that edit.js imports.
jest.mock( '@wordpress/block-editor', () => ( {
	useBlockProps: () => ( { className: 'wp-block-groups-event-card' } ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	Placeholder: ( { icon, label, instructions, children } ) => (
		<div data-testid="placeholder">
			<span>{ label }</span>
			{ instructions && <span>{ instructions }</span> }
			{ children }
		</div>
	),
	Spinner: () => <div data-testid="spinner" />,
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
} ) );

jest.mock( '@wordpress/server-side-render', () => {
	return function MockServerSideRender( props ) {
		return <div data-testid="server-side-render" data-block={ props.block } />;
	};
} );

// Import Edit after mocks are set up.
let Edit;
beforeAll( async () => {
	Edit = ( await import( '../edit' ) ).default;
} );

describe( 'Event Card block', () => {
	it( 'should render a placeholder when no event ID is provided', () => {
		render(
			<Edit
				attributes={ { eventId: 0 } }
				context={ {} }
			/>
		);

		expect(
			screen.getByText( 'Event Card' )
		).toBeInTheDocument();

		expect(
			screen.getByText(
				'This block displays an event card. Place it in an event template or set an event ID.'
			)
		).toBeInTheDocument();
	} );

	it( 'should render ServerSideRender when an event ID is provided', () => {
		render(
			<Edit
				attributes={ { eventId: 42 } }
				context={ {} }
			/>
		);

		const ssr = screen.getByTestId( 'server-side-render' );
		expect( ssr ).toBeInTheDocument();
		expect( ssr ).toHaveAttribute( 'data-block', 'groups/event-card' );
	} );

	it( 'should use postId from context when eventId attribute is not set', () => {
		render(
			<Edit
				attributes={ { eventId: 0 } }
				context={ { postId: 99 } }
			/>
		);

		const ssr = screen.getByTestId( 'server-side-render' );
		expect( ssr ).toBeInTheDocument();
	} );

	it( 'block.json should have the correct namespace', () => {
		expect( metadata.name ).toBe( 'groups/event-card' );
	} );

	it( 'block.json should have the correct category', () => {
		expect( metadata.category ).toBe( 'widgets' );
	} );

	it( 'block.json should have server-side render file', () => {
		expect( metadata.render ).toBe( 'file:./render.php' );
	} );

	it( 'block.json should have the correct text domain', () => {
		expect( metadata.textdomain ).toBe( 'wordpress-groups' );
	} );
} );
