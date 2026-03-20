/**
 * Venue Map — Frontend view script.
 *
 * Initializes Leaflet maps for venue-map blocks. Uses IntersectionObserver
 * for lazy loading so maps are only initialized when scrolled into view.
 */

/**
 * Initialize a Leaflet map on the given container element.
 *
 * @param {HTMLElement} container The map container element with data attributes.
 */
function initMap( container ) {
	if ( container.dataset.initialized ) {
		return;
	}

	const lat = parseFloat( container.dataset.lat );
	const lon = parseFloat( container.dataset.lon );
	const name = container.dataset.name || '';

	if ( isNaN( lat ) || isNaN( lon ) ) {
		return;
	}

	container.dataset.initialized = 'true';

	// Replace the static role="img" with an interactive application role.
	container.setAttribute( 'role', 'application' );
	container.setAttribute(
		'aria-label',
		container.getAttribute( 'aria-label' ) ||
			( name ? 'Interactive map showing location of ' + name : 'Interactive venue map' )
	);

	const map = window.L.map( container, {
		scrollWheelZoom: false,
	} ).setView( [ lat, lon ], 15 );

	window.L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
		attribution:
			'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
		maxZoom: 19,
	} ).addTo( map );

	const marker = window.L.marker( [ lat, lon ] ).addTo( map );

	if ( name ) {
		marker.bindPopup( name );
	}

	// Invalidate map size after a short delay to handle layout shifts.
	setTimeout( () => {
		map.invalidateSize();
	}, 100 );
}

/**
 * Set up all venue map containers with lazy-loading via IntersectionObserver.
 */
function initAllMaps() {
	const containers = document.querySelectorAll(
		'.wp-block-groups-venue-map__container:not([data-initialized])'
	);

	if ( ! containers.length ) {
		return;
	}

	// If IntersectionObserver is not supported, initialize all maps immediately.
	if ( ! ( 'IntersectionObserver' in window ) ) {
		containers.forEach( initMap );
		return;
	}

	const observer = new IntersectionObserver(
		( entries ) => {
			entries.forEach( ( entry ) => {
				if ( entry.isIntersecting ) {
					initMap( entry.target );
					observer.unobserve( entry.target );
				}
			} );
		},
		{
			rootMargin: '200px 0px',
			threshold: 0,
		}
	);

	containers.forEach( ( container ) => {
		observer.observe( container );
	} );
}

// Wait for Leaflet to be available, then initialize maps.
if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', initAllMaps );
} else {
	initAllMaps();
}
