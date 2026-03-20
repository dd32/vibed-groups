/**
 * Group Directory — frontend interactive script.
 *
 * Hydrates the server-rendered directory container with interactive search,
 * filtering, and pagination via the REST API.
 */

import apiFetch from '@wordpress/api-fetch';
import { createElement, createRoot, useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Debounce helper.
 *
 * @param {Function} fn    Function to debounce.
 * @param {number}   delay Delay in ms.
 * @return {Function} Debounced function.
 */
function debounce( fn, delay ) {
	let timer;
	return ( ...args ) => {
		clearTimeout( timer );
		timer = setTimeout( () => fn( ...args ), delay );
	};
}

/**
 * Single group card component.
 *
 * @param {Object} props
 * @param {Object} props.group Group data from the REST API.
 */
function GroupCard( { group } ) {
	const locationParts = [ group.city, group.country ].filter( Boolean );
	const location = locationParts.join( ', ' );

	return createElement(
		'article',
		{ className: 'wp-block-groups-group-directory__card' },
		createElement(
			'h3',
			{ className: 'wp-block-groups-group-directory__card-name' },
			group.site_url
				? createElement(
					'a',
					{ href: group.site_url },
					group.name
				)
				: group.name
		),
		location &&
			createElement(
				'div',
				{ className: 'wp-block-groups-group-directory__card-location' },
				location
			),
		createElement(
			'div',
			{ className: 'wp-block-groups-group-directory__card-meta' },
			createElement(
				'span',
				{ className: 'wp-block-groups-group-directory__card-members' },
				sprintf(
					/* translators: %s: Number of members. */
					_n( '%s member', '%s members', group.member_count, 'wordpress-groups' ),
					group.member_count.toLocaleString()
				)
			),
			group.next_event &&
				createElement(
					'span',
					{ className: 'wp-block-groups-group-directory__card-next-event' },
					sprintf(
						/* translators: %s: Event title. */
						__( 'Next: %s', 'wordpress-groups' ),
						group.next_event
					)
				)
		)
	);
}

/**
 * Pagination component.
 *
 * @param {Object}   props
 * @param {number}   props.currentPage Current page number.
 * @param {number}   props.totalPages  Total number of pages.
 * @param {Function} props.onPageChange Callback when page changes.
 */
function Pagination( { currentPage, totalPages, onPageChange } ) {
	if ( totalPages <= 1 ) {
		return null;
	}

	const pages = [];
	const maxVisible = 5;
	let start = Math.max( 1, currentPage - Math.floor( maxVisible / 2 ) );
	let end = Math.min( totalPages, start + maxVisible - 1 );

	if ( end - start + 1 < maxVisible ) {
		start = Math.max( 1, end - maxVisible + 1 );
	}

	for ( let i = start; i <= end; i++ ) {
		pages.push( i );
	}

	return createElement(
		'nav',
		{
			className: 'wp-block-groups-group-directory__pagination',
			'aria-label': __( 'Group directory pagination', 'wordpress-groups' ),
		},
		createElement(
			'button',
			{
				type: 'button',
				className: 'wp-block-groups-group-directory__page-btn',
				onClick: () => onPageChange( currentPage - 1 ),
				disabled: currentPage <= 1,
				'aria-label': __( 'Previous page', 'wordpress-groups' ),
			},
			'\u2190'
		),
		pages.map( ( page ) =>
			createElement(
				'button',
				{
					key: page,
					type: 'button',
					className: `wp-block-groups-group-directory__page-btn${ page === currentPage ? ' wp-block-groups-group-directory__page-btn--active' : '' }`,
					onClick: () => onPageChange( page ),
					'aria-label': sprintf(
						/* translators: %d: Page number. */
						__( 'Page %d', 'wordpress-groups' ),
						page
					),
					'aria-current': page === currentPage ? 'page' : undefined,
				},
				String( page )
			)
		),
		createElement(
			'button',
			{
				type: 'button',
				className: 'wp-block-groups-group-directory__page-btn',
				onClick: () => onPageChange( currentPage + 1 ),
				disabled: currentPage >= totalPages,
				'aria-label': __( 'Next page', 'wordpress-groups' ),
			},
			'\u2192'
		)
	);
}

/**
 * Main Group Directory component.
 *
 * @param {Object}   props
 * @param {number}   props.perPage       Groups per page.
 * @param {Array}    props.initialGroups  Server-rendered initial groups.
 * @param {number}   props.initialTotal   Total number of groups.
 * @param {number}   props.initialPages   Total number of pages.
 */
export function GroupDirectory( { perPage, initialGroups, initialTotal, initialPages } ) {
	const [ groups, setGroups ] = useState( initialGroups );
	const [ search, setSearch ] = useState( '' );
	const [ currentPage, setCurrentPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( initialPages );
	const [ total, setTotal ] = useState( initialTotal );
	const [ isLoading, setIsLoading ] = useState( false );
	const abortRef = useRef( null );

	/**
	 * Fetch groups from the REST API.
	 */
	const fetchGroups = useCallback( async ( searchTerm, page ) => {
		// Abort any in-flight request.
		if ( abortRef.current ) {
			abortRef.current.abort();
		}

		const controller = new AbortController();
		abortRef.current = controller;

		setIsLoading( true );

		try {
			const params = new URLSearchParams( {
				per_page: String( perPage ),
				page: String( page ),
			} );

			if ( searchTerm ) {
				params.set( 'search', searchTerm );
			}

			const response = await apiFetch( {
				path: `/groups/v1/groups?${ params.toString() }`,
				signal: controller.signal,
				parse: false,
			} );

			const data = await response.json();
			const newTotal = parseInt( response.headers.get( 'X-WP-Total' ), 10 ) || 0;
			const newTotalPages = parseInt( response.headers.get( 'X-WP-TotalPages' ), 10 ) || 0;

			setGroups( data );
			setTotal( newTotal );
			setTotalPages( newTotalPages );
		} catch ( err ) {
			if ( err.name !== 'AbortError' ) {
				// On error, keep existing state.
			}
		} finally {
			setIsLoading( false );
		}
	}, [ perPage ] );

	/**
	 * Debounced search handler.
	 */
	const debouncedSearch = useCallback(
		debounce( ( term ) => {
			setCurrentPage( 1 );
			fetchGroups( term, 1 );
		}, 300 ),
		[ fetchGroups ]
	);

	/**
	 * Handle search input change.
	 */
	const handleSearchChange = useCallback( ( e ) => {
		const value = e.target.value;
		setSearch( value );
		debouncedSearch( value );
	}, [ debouncedSearch ] );

	/**
	 * Handle page change.
	 */
	const handlePageChange = useCallback( ( page ) => {
		setCurrentPage( page );
		fetchGroups( search, page );
	}, [ fetchGroups, search ] );

	return createElement(
		'div',
		{ className: 'wp-block-groups-group-directory__inner' },
		createElement(
			'div',
			{ className: 'wp-block-groups-group-directory__search' },
			createElement(
				'label',
				{
					htmlFor: 'group-directory-search',
					className: 'screen-reader-text',
				},
				__( 'Search groups', 'wordpress-groups' )
			),
			createElement( 'input', {
				type: 'search',
				id: 'group-directory-search',
				className: 'wp-block-groups-group-directory__search-input',
				placeholder: __( 'Search groups\u2026', 'wordpress-groups' ),
				value: search,
				onChange: handleSearchChange,
			} )
		),
		createElement(
			'div',
			{
				className: 'wp-block-groups-group-directory__results',
				'aria-live': 'polite',
				'aria-busy': isLoading,
			},
			isLoading &&
				createElement(
					'div',
					{
						className: 'wp-block-groups-group-directory__skeleton',
						role: 'status',
						'aria-label': __( 'Loading groups\u2026', 'wordpress-groups' ),
					},
					...Array.from( { length: Math.min( perPage, 6 ) }, ( _, i ) =>
						createElement(
							'div',
							{ key: i, className: 'wp-block-groups-group-directory__skeleton-card' },
							createElement( 'div', { className: 'wp-block-groups-group-directory__skeleton-line wp-block-groups-group-directory__skeleton-line--name' } ),
							createElement( 'div', { className: 'wp-block-groups-group-directory__skeleton-line wp-block-groups-group-directory__skeleton-line--location' } ),
							createElement( 'div', { className: 'wp-block-groups-group-directory__skeleton-line wp-block-groups-group-directory__skeleton-line--meta' } )
						)
					)
				),
			! isLoading && groups.length === 0 &&
				createElement(
					'p',
					{ className: 'wp-block-groups-group-directory__empty' },
					search
						? __( 'No groups found matching your search.', 'wordpress-groups' )
						: __( 'No groups found.', 'wordpress-groups' )
				),
			! isLoading && groups.length > 0 &&
				createElement(
					'div',
					{ className: 'wp-block-groups-group-directory__grid' },
					groups.map( ( group ) =>
						createElement( GroupCard, { key: group.id, group } )
					)
				)
		),
		! isLoading &&
			createElement( Pagination, {
				currentPage,
				totalPages,
				onPageChange: handlePageChange,
			} )
	);
}

/**
 * Hydrate all Group Directory containers on the page.
 */
function init() {
	const containers = document.querySelectorAll( '.wp-block-groups-group-directory[data-per-page]' );

	containers.forEach( ( container ) => {
		const perPage = parseInt( container.dataset.perPage, 10 ) || 12;
		const initialTotal = parseInt( container.dataset.total, 10 ) || 0;
		const initialPages = parseInt( container.dataset.pages, 10 ) || 0;

		let initialGroups = [];
		try {
			initialGroups = JSON.parse( container.dataset.groups || '[]' );
		} catch {
			initialGroups = [];
		}

		const root = createRoot( container );
		root.render(
			createElement( GroupDirectory, {
				perPage,
				initialGroups,
				initialTotal,
				initialPages,
			} )
		);
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
