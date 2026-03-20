/**
 * Group Directory — frontend interactive script.
 *
 * Hydrates the server-rendered directory container with interactive search,
 * filtering, pagination, and an optional Leaflet map view.
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
 * Map View component using Leaflet.
 *
 * Renders an OpenStreetMap with markers for each group that has coordinates.
 *
 * @param {Object}   props
 * @param {Array}    props.mapGroups   Groups with lat/lon data for the map.
 * @param {Function} props.onLoadAll   Callback to fetch all groups for the map.
 */
function MapView( { mapGroups, onLoadAll } ) {
	const mapRef = useRef( null );
	const mapInstanceRef = useRef( null );
	const markersRef = useRef( [] );
	const [ geolocating, setGeolocating ] = useState( false );

	/**
	 * Initialize the Leaflet map on first render.
	 */
	useEffect( () => {
		if ( ! mapRef.current || ! window.L ) {
			return;
		}

		if ( mapInstanceRef.current ) {
			return;
		}

		const map = window.L.map( mapRef.current, {
			scrollWheelZoom: false,
		} ).setView( [ 20, 0 ], 2 );

		window.L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
			attribution:
				'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
			maxZoom: 19,
		} ).addTo( map );

		mapInstanceRef.current = map;

		// Trigger a load of all groups for the map.
		if ( onLoadAll ) {
			onLoadAll();
		}

		// Invalidate size after layout settles.
		setTimeout( () => map.invalidateSize(), 100 );

		return () => {
			map.remove();
			mapInstanceRef.current = null;
		};
	}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

	/**
	 * Update markers when mapGroups changes.
	 */
	useEffect( () => {
		const map = mapInstanceRef.current;
		if ( ! map || ! window.L ) {
			return;
		}

		// Clear existing markers.
		markersRef.current.forEach( ( m ) => m.remove() );
		markersRef.current = [];

		const bounds = [];

		mapGroups.forEach( ( group ) => {
			if ( ! group.latitude || ! group.longitude ) {
				return;
			}

			const latlng = [ group.latitude, group.longitude ];
			bounds.push( latlng );

			const locationParts = [ group.city, group.country ].filter( Boolean );
			const location = locationParts.join( ', ' );

			let popupContent = `<strong>${ group.name }</strong>`;
			if ( location ) {
				popupContent += `<br>${ location }`;
			}
			if ( group.site_url ) {
				popupContent += `<br><a href="${ group.site_url }">${ __( 'Visit group', 'wordpress-groups' ) }</a>`;
			}

			const marker = window.L.marker( latlng )
				.bindPopup( popupContent )
				.addTo( map );

			markersRef.current.push( marker );
		} );

		// Fit the map to show all markers.
		if ( bounds.length > 0 ) {
			map.fitBounds( bounds, { padding: [ 40, 40 ], maxZoom: 12 } );
		}
	}, [ mapGroups ] );

	/**
	 * Handle geolocation button click.
	 */
	const handleGeolocate = useCallback( () => {
		if ( ! navigator.geolocation || ! mapInstanceRef.current ) {
			return;
		}

		setGeolocating( true );

		navigator.geolocation.getCurrentPosition(
			( position ) => {
				const { latitude, longitude } = position.coords;
				mapInstanceRef.current.setView( [ latitude, longitude ], 10 );
				setGeolocating( false );
			},
			() => {
				setGeolocating( false );
			},
			{ timeout: 10000 }
		);
	}, [] );

	const supportsGeolocation = typeof navigator !== 'undefined' && 'geolocation' in navigator;

	return createElement(
		'div',
		{ className: 'wp-block-groups-group-directory__map-wrapper' },
		supportsGeolocation &&
			createElement(
				'button',
				{
					type: 'button',
					className: 'wp-block-groups-group-directory__geolocate-btn',
					onClick: handleGeolocate,
					disabled: geolocating,
					'aria-label': __( 'Center map on my location', 'wordpress-groups' ),
				},
				geolocating
					? __( 'Locating\u2026', 'wordpress-groups' )
					: __( 'My location', 'wordpress-groups' )
			),
		createElement( 'div', {
			ref: mapRef,
			className: 'wp-block-groups-group-directory__map-container',
			role: 'application',
			'aria-label': __( 'Interactive map of community groups', 'wordpress-groups' ),
		} )
	);
}

/**
 * View toggle buttons for switching between list and map.
 *
 * @param {Object}   props
 * @param {string}   props.activeView  Current view ('list' or 'map').
 * @param {Function} props.onToggle    Callback when a view is selected.
 */
function ViewToggle( { activeView, onToggle } ) {
	return createElement(
		'div',
		{
			className: 'wp-block-groups-group-directory__view-toggle',
			role: 'tablist',
			'aria-label': __( 'Directory view', 'wordpress-groups' ),
		},
		createElement(
			'button',
			{
				type: 'button',
				role: 'tab',
				className: `wp-block-groups-group-directory__view-btn${ activeView === 'list' ? ' wp-block-groups-group-directory__view-btn--active' : '' }`,
				onClick: () => onToggle( 'list' ),
				'aria-selected': activeView === 'list',
				'aria-controls': 'group-directory-list-view',
			},
			__( 'List View', 'wordpress-groups' )
		),
		createElement(
			'button',
			{
				type: 'button',
				role: 'tab',
				className: `wp-block-groups-group-directory__view-btn${ activeView === 'map' ? ' wp-block-groups-group-directory__view-btn--active' : '' }`,
				onClick: () => onToggle( 'map' ),
				'aria-selected': activeView === 'map',
				'aria-controls': 'group-directory-map-view',
			},
			__( 'Map View', 'wordpress-groups' )
		)
	);
}

/**
 * Main Group Directory component.
 *
 * @param {Object}   props
 * @param {number}   props.perPage         Groups per page.
 * @param {Array}    props.initialGroups    Server-rendered initial groups.
 * @param {number}   props.initialTotal     Total number of groups.
 * @param {number}   props.initialPages     Total number of pages.
 * @param {Array}    props.initialMapGroups Initial groups with coordinates for the map.
 */
export function GroupDirectory( { perPage, initialGroups, initialTotal, initialPages, initialMapGroups } ) {
	const [ groups, setGroups ] = useState( initialGroups );
	const [ search, setSearch ] = useState( '' );
	const [ currentPage, setCurrentPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( initialPages );
	const [ total, setTotal ] = useState( initialTotal );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ activeView, setActiveView ] = useState( 'list' );
	const [ mapGroups, setMapGroups ] = useState( initialMapGroups );
	const [ allMapGroupsLoaded, setAllMapGroupsLoaded ] = useState( false );
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
	 * Fetch all groups with coordinates for the map view.
	 * Uses a large per_page to get everything in one request.
	 */
	const fetchAllMapGroups = useCallback( async () => {
		if ( allMapGroupsLoaded ) {
			return;
		}

		try {
			const response = await apiFetch( {
				path: '/groups/v1/groups?per_page=200',
				parse: false,
			} );

			const data = await response.json();
			const geoGroups = data.filter(
				( g ) => g.latitude && g.longitude
			);

			setMapGroups( geoGroups );
			setAllMapGroupsLoaded( true );
		} catch {
			// On error, keep the initial map groups from server render.
		}
	}, [ allMapGroupsLoaded ] );

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
			{ className: 'wp-block-groups-group-directory__toolbar' },
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
			createElement( ViewToggle, {
				activeView,
				onToggle: setActiveView,
			} )
		),
		activeView === 'list' &&
			createElement(
				'div',
				{
					id: 'group-directory-list-view',
					role: 'tabpanel',
				},
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
							{ className: 'wp-block-groups-group-directory__loading' },
							createElement( 'span', {
								className: 'wp-block-groups-group-directory__spinner',
								'aria-hidden': 'true',
							} ),
							__( 'Loading groups\u2026', 'wordpress-groups' )
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
			),
		activeView === 'map' &&
			createElement(
				'div',
				{
					id: 'group-directory-map-view',
					role: 'tabpanel',
				},
				createElement( MapView, {
					mapGroups,
					onLoadAll: fetchAllMapGroups,
				} )
			)
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

		let initialMapGroups = [];
		try {
			initialMapGroups = JSON.parse( container.dataset.mapGroups || '[]' );
		} catch {
			initialMapGroups = [];
		}

		const root = createRoot( container );
		root.render(
			createElement( GroupDirectory, {
				perPage,
				initialGroups,
				initialTotal,
				initialPages,
				initialMapGroups,
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
