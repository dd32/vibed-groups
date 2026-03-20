/**
 * Group Directory — frontend interactive directory.
 *
 * Hydrates the server-rendered container with a React-based searchable
 * directory of WordPress community groups.
 */

import apiFetch from '@wordpress/api-fetch';
import { createElement, createRoot, useState, useEffect, useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Number of groups to display per page.
 */
const PER_PAGE = 12;

/**
 * Debounce delay in milliseconds for the search input.
 */
const DEBOUNCE_MS = 300;

/**
 * A single group card component.
 *
 * @param {Object} props
 * @param {Object} props.group Group data from the REST API.
 */
function GroupCard( { group } ) {
	const cardContent = [
		createElement(
			'h3',
			{
				key: 'name',
				className: 'wp-block-groups-group-directory__card-name',
			},
			group.site_url
				? createElement(
					'a',
					{ href: group.site_url, rel: 'noopener noreferrer' },
					group.name
				)
				: group.name
		),
		createElement(
			'p',
			{
				key: 'city',
				className: 'wp-block-groups-group-directory__card-city',
			},
			[ group.city, group.country ].filter( Boolean ).join( ', ' )
		),
		createElement(
			'p',
			{
				key: 'members',
				className: 'wp-block-groups-group-directory__card-members',
			},
			group.member_count === 1
				? __( '1 member', 'wordpress-groups' )
				: String( group.member_count ) + ' ' + __( 'members', 'wordpress-groups' )
		),
	];

	return createElement(
		'div',
		{ className: 'wp-block-groups-group-directory__card' },
		cardContent
	);
}

/**
 * Pagination component.
 *
 * @param {Object}   props
 * @param {number}   props.currentPage Current page number (1-indexed).
 * @param {number}   props.totalPages  Total number of pages.
 * @param {Function} props.onPageChange Callback when page changes.
 */
function Pagination( { currentPage, totalPages, onPageChange } ) {
	if ( totalPages <= 1 ) {
		return null;
	}

	const buttons = [];

	buttons.push(
		createElement(
			'button',
			{
				key: 'prev',
				className: 'wp-block-groups-group-directory__page-btn',
				onClick: () => onPageChange( currentPage - 1 ),
				disabled: currentPage <= 1,
				'aria-label': __( 'Previous page', 'wordpress-groups' ),
			},
			'\u2190'
		)
	);

	for ( let i = 1; i <= totalPages; i++ ) {
		buttons.push(
			createElement(
				'button',
				{
					key: i,
					className: [
						'wp-block-groups-group-directory__page-btn',
						i === currentPage ? 'is-active' : '',
					]
						.filter( Boolean )
						.join( ' ' ),
					onClick: () => onPageChange( i ),
					'aria-current': i === currentPage ? 'page' : undefined,
					'aria-label': i === currentPage
						? __( 'Current page', 'wordpress-groups' ) + ', ' + i
						: __( 'Page', 'wordpress-groups' ) + ' ' + i,
				},
				String( i )
			)
		);
	}

	buttons.push(
		createElement(
			'button',
			{
				key: 'next',
				className: 'wp-block-groups-group-directory__page-btn',
				onClick: () => onPageChange( currentPage + 1 ),
				disabled: currentPage >= totalPages,
				'aria-label': __( 'Next page', 'wordpress-groups' ),
			},
			'\u2192'
		)
	);

	return createElement(
		'nav',
		{
			className: 'wp-block-groups-group-directory__pagination',
			'aria-label': __( 'Directory pagination', 'wordpress-groups' ),
		},
		buttons
	);
}

/**
 * Main Group Directory component.
 */
function GroupDirectory() {
	const [ groups, setGroups ] = useState( [] );
	const [ search, setSearch ] = useState( '' );
	const [ debouncedSearch, setDebouncedSearch ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const [ totalGroups, setTotalGroups ] = useState( 0 );
	const [ isLoading, setIsLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	// Debounce the search input.
	useEffect( () => {
		const timer = setTimeout( () => {
			setDebouncedSearch( search );
			setPage( 1 );
		}, DEBOUNCE_MS );

		return () => clearTimeout( timer );
	}, [ search ] );

	// Fetch groups from the REST API.
	useEffect( () => {
		setIsLoading( true );
		setError( '' );

		const queryParams = new URLSearchParams( {
			per_page: String( PER_PAGE ),
			page: String( page ),
		} );

		if ( debouncedSearch.trim() ) {
			queryParams.set( 'search', debouncedSearch.trim() );
		}

		apiFetch( {
			path: '/groups/v1/groups?' + queryParams.toString(),
			parse: false,
		} )
			.then( ( response ) => {
				setTotalPages( parseInt( response.headers.get( 'X-WP-TotalPages' ) || '1', 10 ) );
				setTotalGroups( parseInt( response.headers.get( 'X-WP-Total' ) || '0', 10 ) );
				return response.json();
			} )
			.then( ( data ) => {
				setGroups( data );
				setIsLoading( false );
			} )
			.catch( ( err ) => {
				setError(
					err.message ||
						__( 'Failed to load groups. Please try again.', 'wordpress-groups' )
				);
				setIsLoading( false );
			} );
	}, [ debouncedSearch, page ] );

	/**
	 * Handle search input changes.
	 */
	const handleSearchChange = useCallback( ( e ) => {
		setSearch( e.target.value );
	}, [] );

	/**
	 * Handle page changes from the Pagination component.
	 */
	const handlePageChange = useCallback( ( newPage ) => {
		setPage( newPage );
		// Scroll back to the top of the directory.
		const el = document.querySelector( '.wp-block-groups-group-directory' );
		if ( el ) {
			el.scrollIntoView( { behavior: 'smooth', block: 'start' } );
		}
	}, [] );

	return createElement(
		'div',
		{ className: 'wp-block-groups-group-directory__inner' },
		createElement(
			'div',
			{ className: 'wp-block-groups-group-directory__header' },
			createElement(
				'h2',
				{ className: 'wp-block-groups-group-directory__title' },
				__( 'WordPress Community Groups', 'wordpress-groups' )
			),
			createElement(
				'div',
				{ className: 'wp-block-groups-group-directory__search' },
				createElement( 'input', {
					type: 'search',
					className: 'wp-block-groups-group-directory__search-input',
					placeholder: __( 'Search groups by name or city\u2026', 'wordpress-groups' ),
					value: search,
					onChange: handleSearchChange,
					'aria-label': __( 'Search groups', 'wordpress-groups' ),
				} )
			),
			! isLoading && createElement(
				'p',
				{ className: 'wp-block-groups-group-directory__count' },
				totalGroups === 1
					? __( '1 group found', 'wordpress-groups' )
					: String( totalGroups ) + ' ' + __( 'groups found', 'wordpress-groups' )
			)
		),
		error &&
			createElement(
				'div',
				{
					className: 'wp-block-groups-group-directory__error',
					role: 'alert',
				},
				error
			),
		isLoading &&
			createElement(
				'div',
				{
					className: 'wp-block-groups-group-directory__loading',
					'aria-live': 'polite',
				},
				createElement( 'p', null, __( 'Loading groups\u2026', 'wordpress-groups' ) )
			),
		! isLoading &&
			! error &&
			groups.length === 0 &&
			createElement(
				'div',
				{ className: 'wp-block-groups-group-directory__empty' },
				createElement(
					'p',
					null,
					debouncedSearch
						? __( 'No groups match your search. Try a different term.', 'wordpress-groups' )
						: __( 'No groups are available at this time.', 'wordpress-groups' )
				)
			),
		! isLoading &&
			! error &&
			groups.length > 0 &&
			createElement(
				'div',
				{
					className: 'wp-block-groups-group-directory__grid',
					role: 'list',
				},
				groups.map( ( group, index ) =>
					createElement(
						'div',
						{ key: group.site_id || index, role: 'listitem' },
						createElement( GroupCard, { group } )
					)
				)
			),
		! isLoading &&
			! error &&
			createElement( Pagination, {
				currentPage: page,
				totalPages,
				onPageChange: handlePageChange,
			} )
	);
}

/**
 * Hydrate all group directory containers on the page.
 */
function init() {
	const containers = document.querySelectorAll( '.wp-block-groups-group-directory' );

	containers.forEach( ( container ) => {
		const root = createRoot( container );
		root.render( createElement( GroupDirectory ) );
	} );
}

// Hydrate when the DOM is ready (skip during tests).
if ( typeof document !== 'undefined' ) {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}

// Export for testing.
export { GroupDirectory, GroupCard, Pagination };
