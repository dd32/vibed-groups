/**
 * Event Directory — frontend interactive view.
 *
 * Hydrates the server-rendered container with a React-based event directory
 * featuring list/calendar toggle, date filters, category filters, and pagination.
 */

import apiFetch from '@wordpress/api-fetch';
import { createElement, createRoot, useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Date filter presets.
 */
const DATE_FILTERS = {
	upcoming: __( 'Upcoming', 'wordpress-groups' ),
	this_week: __( 'This Week', 'wordpress-groups' ),
	this_month: __( 'This Month', 'wordpress-groups' ),
	custom: __( 'Custom Range', 'wordpress-groups' ),
};

/**
 * View modes.
 */
const VIEW_LIST = 'list';
const VIEW_CALENDAR = 'calendar';

/**
 * Get the start and end of the current week (Monday to Sunday).
 *
 * @return {Object} Object with `after` and `before` date strings.
 */
function getThisWeekRange() {
	const now = new Date();
	const day = now.getDay();
	const diffToMonday = day === 0 ? -6 : 1 - day;
	const monday = new Date( now );
	monday.setDate( now.getDate() + diffToMonday );
	monday.setHours( 0, 0, 0, 0 );

	const sunday = new Date( monday );
	sunday.setDate( monday.getDate() + 6 );
	sunday.setHours( 23, 59, 59, 999 );

	return {
		after: formatDateParam( monday ),
		before: formatDateParam( sunday ),
	};
}

/**
 * Get the start and end of the current month.
 *
 * @return {Object} Object with `after` and `before` date strings.
 */
function getThisMonthRange() {
	const now = new Date();
	const first = new Date( now.getFullYear(), now.getMonth(), 1 );
	const last = new Date( now.getFullYear(), now.getMonth() + 1, 0 );

	return {
		after: formatDateParam( first ),
		before: formatDateParam( last ),
	};
}

/**
 * Format a Date object as YYYY-MM-DD for API params.
 *
 * @param {Date} date Date to format.
 * @return {string} Formatted date string.
 */
function formatDateParam( date ) {
	const year = date.getFullYear();
	const month = String( date.getMonth() + 1 ).padStart( 2, '0' );
	const day = String( date.getDate() ).padStart( 2, '0' );
	return `${ year }-${ month }-${ day }`;
}

/**
 * Format a date string for display.
 *
 * @param {string} dateStr Date string from API.
 * @param {string} tz      Timezone identifier.
 * @return {string} Formatted date string.
 */
function formatEventDate( dateStr, tz ) {
	if ( ! dateStr ) {
		return '';
	}

	try {
		const options = {
			year: 'numeric',
			month: 'short',
			day: 'numeric',
			hour: 'numeric',
			minute: '2-digit',
		};

		if ( tz ) {
			options.timeZone = tz;
		}

		const date = new Date( dateStr.replace( ' ', 'T' ) + 'Z' );
		return date.toLocaleDateString( undefined, options );
	} catch {
		return dateStr;
	}
}

/**
 * Get days in a given month.
 *
 * @param {number} year  Full year.
 * @param {number} month Month (0-indexed).
 * @return {number} Number of days.
 */
function getDaysInMonth( year, month ) {
	return new Date( year, month + 1, 0 ).getDate();
}

/**
 * Get the day of the week for the first day of a month (0 = Monday in our grid).
 *
 * @param {number} year  Full year.
 * @param {number} month Month (0-indexed).
 * @return {number} Day offset (0 = Monday).
 */
function getFirstDayOffset( year, month ) {
	const day = new Date( year, month, 1 ).getDay();
	// Convert from Sunday=0 to Monday=0
	return day === 0 ? 6 : day - 1;
}

/**
 * Month names for the calendar header.
 */
const MONTH_NAMES = [
	__( 'January', 'wordpress-groups' ),
	__( 'February', 'wordpress-groups' ),
	__( 'March', 'wordpress-groups' ),
	__( 'April', 'wordpress-groups' ),
	__( 'May', 'wordpress-groups' ),
	__( 'June', 'wordpress-groups' ),
	__( 'July', 'wordpress-groups' ),
	__( 'August', 'wordpress-groups' ),
	__( 'September', 'wordpress-groups' ),
	__( 'October', 'wordpress-groups' ),
	__( 'November', 'wordpress-groups' ),
	__( 'December', 'wordpress-groups' ),
];

/**
 * Day-of-week headers for the calendar grid.
 */
const DAY_HEADERS = [
	__( 'Mon', 'wordpress-groups' ),
	__( 'Tue', 'wordpress-groups' ),
	__( 'Wed', 'wordpress-groups' ),
	__( 'Thu', 'wordpress-groups' ),
	__( 'Fri', 'wordpress-groups' ),
	__( 'Sat', 'wordpress-groups' ),
	__( 'Sun', 'wordpress-groups' ),
];

/**
 * Event Card component for the list view.
 *
 * @param {Object} props
 * @param {Object} props.event Event data from the REST API.
 */
function EventCard( { event } ) {
	const meta = event.meta || {};
	const venueName = meta.venue_id
		? __( 'Venue', 'wordpress-groups' )
		: meta.online_link
			? __( 'Online', 'wordpress-groups' )
			: '';

	return createElement(
		'article',
		{ className: 'wp-block-groups-event-directory__card' },
		createElement(
			'div',
			{ className: 'wp-block-groups-event-directory__card-content' },
			createElement(
				'h3',
				{ className: 'wp-block-groups-event-directory__card-title' },
				createElement(
					'a',
					{ href: event.link },
					event.title
				)
			),
			meta.start_date &&
				createElement(
					'div',
					{ className: 'wp-block-groups-event-directory__card-date' },
					createElement(
						'time',
						{ dateTime: meta.start_date },
						formatEventDate( meta.start_date, meta.timezone )
					)
				),
			venueName &&
				createElement(
					'span',
					{ className: 'wp-block-groups-event-directory__card-venue' },
					venueName
				),
			event.excerpt &&
				createElement(
					'p',
					{ className: 'wp-block-groups-event-directory__card-excerpt' },
					event.excerpt
				)
		)
	);
}

/**
 * Calendar View component — simple month grid with event dots.
 *
 * @param {Object}   props
 * @param {Array}    props.events      Array of event objects.
 * @param {number}   props.year        Current year.
 * @param {number}   props.month       Current month (0-indexed).
 * @param {Function} props.onPrevMonth Handler for previous month navigation.
 * @param {Function} props.onNextMonth Handler for next month navigation.
 */
function CalendarView( { events, year, month, onPrevMonth, onNextMonth } ) {
	const daysInMonth = getDaysInMonth( year, month );
	const firstDayOffset = getFirstDayOffset( year, month );

	// Build a map of day number -> events for the current month.
	const eventsByDay = useMemo( () => {
		const map = {};
		events.forEach( ( event ) => {
			const startDate = event.meta?.start_date;
			if ( ! startDate ) {
				return;
			}
			const date = new Date( startDate.replace( ' ', 'T' ) + 'Z' );
			if ( date.getFullYear() === year && date.getMonth() === month ) {
				const day = date.getDate();
				if ( ! map[ day ] ) {
					map[ day ] = [];
				}
				map[ day ].push( event );
			}
		} );
		return map;
	}, [ events, year, month ] );

	const today = new Date();
	const isCurrentMonth = today.getFullYear() === year && today.getMonth() === month;
	const todayDate = today.getDate();

	// Build calendar cells: empty cells for offset + day cells.
	const cells = [];
	for ( let i = 0; i < firstDayOffset; i++ ) {
		cells.push(
			createElement( 'div', {
				key: `empty-${ i }`,
				className: 'wp-block-groups-event-directory__calendar-cell wp-block-groups-event-directory__calendar-cell--empty',
				'aria-hidden': 'true',
			} )
		);
	}

	for ( let day = 1; day <= daysInMonth; day++ ) {
		const dayEvents = eventsByDay[ day ] || [];
		const isToday = isCurrentMonth && day === todayDate;
		const classNames = [
			'wp-block-groups-event-directory__calendar-cell',
			isToday ? 'wp-block-groups-event-directory__calendar-cell--today' : '',
			dayEvents.length > 0 ? 'wp-block-groups-event-directory__calendar-cell--has-events' : '',
		]
			.filter( Boolean )
			.join( ' ' );

		cells.push(
			createElement(
				'div',
				{
					key: `day-${ day }`,
					className: classNames,
					title: dayEvents.length > 0
						? dayEvents.map( ( e ) => e.title ).join( ', ' )
						: undefined,
				},
				createElement(
					'span',
					{ className: 'wp-block-groups-event-directory__calendar-day' },
					day
				),
				dayEvents.length > 0 &&
					createElement(
						'div',
						{ className: 'wp-block-groups-event-directory__calendar-dots' },
						dayEvents.slice( 0, 3 ).map( ( event, idx ) =>
							createElement( 'span', {
								key: idx,
								className: 'wp-block-groups-event-directory__calendar-dot',
								'aria-label': event.title,
							} )
						),
						dayEvents.length > 3 &&
							createElement(
								'span',
								{ className: 'wp-block-groups-event-directory__calendar-more' },
								`+${ dayEvents.length - 3 }`
							)
					)
			)
		);
	}

	return createElement(
		'div',
		{ className: 'wp-block-groups-event-directory__calendar' },
		createElement(
			'div',
			{ className: 'wp-block-groups-event-directory__calendar-header' },
			createElement(
				'button',
				{
					type: 'button',
					className: 'wp-block-groups-event-directory__calendar-nav',
					onClick: onPrevMonth,
					'aria-label': __( 'Previous month', 'wordpress-groups' ),
				},
				'\u2039'
			),
			createElement(
				'h3',
				{ className: 'wp-block-groups-event-directory__calendar-title' },
				`${ MONTH_NAMES[ month ] } ${ year }`
			),
			createElement(
				'button',
				{
					type: 'button',
					className: 'wp-block-groups-event-directory__calendar-nav',
					onClick: onNextMonth,
					'aria-label': __( 'Next month', 'wordpress-groups' ),
				},
				'\u203A'
			)
		),
		createElement(
			'div',
			{
				className: 'wp-block-groups-event-directory__calendar-grid',
				role: 'grid',
				'aria-label': __( 'Event calendar', 'wordpress-groups' ),
			},
			DAY_HEADERS.map( ( header ) =>
				createElement(
					'div',
					{
						key: header,
						className: 'wp-block-groups-event-directory__calendar-weekday',
						role: 'columnheader',
					},
					header
				)
			),
			...cells
		),
		// Show events for the month below the grid.
		events.length > 0 &&
			createElement(
				'ul',
				{ className: 'wp-block-groups-event-directory__calendar-events' },
				events.map( ( event ) =>
					createElement(
						'li',
						{ key: event.id, className: 'wp-block-groups-event-directory__calendar-event-item' },
						createElement(
							'a',
							{ href: event.link },
							createElement(
								'span',
								{ className: 'wp-block-groups-event-directory__calendar-event-date' },
								formatEventDate( event.meta?.start_date, event.meta?.timezone )
							),
							createElement(
								'span',
								{ className: 'wp-block-groups-event-directory__calendar-event-title' },
								event.title
							)
						)
					)
				)
			)
	);
}

/**
 * Main Event Directory component.
 *
 * @param {Object} props
 * @param {number} props.perPage    Events per page.
 * @param {Array}  props.categories Available event categories.
 */
function EventDirectory( { perPage, categories } ) {
	const [ viewMode, setViewMode ] = useState( VIEW_LIST );
	const [ events, setEvents ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );
	const [ page, setPage ] = useState( 1 );
	const [ totalPages, setTotalPages ] = useState( 1 );
	const [ totalEvents, setTotalEvents ] = useState( 0 );

	// Filters.
	const [ dateFilter, setDateFilter ] = useState( 'upcoming' );
	const [ customAfter, setCustomAfter ] = useState( '' );
	const [ customBefore, setCustomBefore ] = useState( '' );
	const [ categoryFilter, setCategoryFilter ] = useState( '' );

	// Calendar state.
	const now = new Date();
	const [ calYear, setCalYear ] = useState( now.getFullYear() );
	const [ calMonth, setCalMonth ] = useState( now.getMonth() );

	/**
	 * Build the API query path from current filter state.
	 */
	const buildApiPath = useCallback( () => {
		const params = new URLSearchParams();
		params.set( 'per_page', String( perPage ) );
		params.set( 'page', String( page ) );

		if ( categoryFilter ) {
			params.set( 'category', categoryFilter );
		}

		if ( viewMode === VIEW_CALENDAR ) {
			// For calendar view, fetch events for the displayed month.
			const first = new Date( calYear, calMonth, 1 );
			const last = new Date( calYear, calMonth + 1, 0 );
			params.set( 'after', formatDateParam( first ) );
			params.set( 'before', formatDateParam( last ) );
			params.set( 'per_page', '100' ); // Get all events for the month.
			params.delete( 'page' );
		} else {
			// List view date filtering.
			switch ( dateFilter ) {
				case 'this_week': {
					const range = getThisWeekRange();
					params.set( 'after', range.after );
					params.set( 'before', range.before );
					break;
				}
				case 'this_month': {
					const range = getThisMonthRange();
					params.set( 'after', range.after );
					params.set( 'before', range.before );
					break;
				}
				case 'custom':
					if ( customAfter ) {
						params.set( 'after', customAfter );
					}
					if ( customBefore ) {
						params.set( 'before', customBefore );
					}
					break;
				case 'upcoming':
				default:
					params.set( 'after', formatDateParam( new Date() ) );
					break;
			}
		}

		return `/groups/v1/events?${ params.toString() }`;
	}, [ perPage, page, categoryFilter, dateFilter, customAfter, customBefore, viewMode, calYear, calMonth ] );

	/**
	 * Fetch events from the REST API.
	 */
	const fetchEvents = useCallback( async () => {
		setLoading( true );
		setError( '' );

		try {
			const response = await apiFetch( {
				path: buildApiPath(),
				parse: false,
			} );

			const total = parseInt( response.headers.get( 'X-WP-Total' ), 10 ) || 0;
			const pages = parseInt( response.headers.get( 'X-WP-TotalPages' ), 10 ) || 1;
			const data = await response.json();

			setEvents( data );
			setTotalEvents( total );
			setTotalPages( pages );
		} catch ( err ) {
			setError(
				err.message ||
					__( 'Failed to load events. Please try again.', 'wordpress-groups' )
			);
			setEvents( [] );
		} finally {
			setLoading( false );
		}
	}, [ buildApiPath ] );

	// Fetch events when filters or page change.
	useEffect( () => {
		fetchEvents();
	}, [ fetchEvents ] );

	// Reset page when filters change.
	useEffect( () => {
		setPage( 1 );
	}, [ dateFilter, categoryFilter, customAfter, customBefore ] );

	/**
	 * Navigate calendar to previous month.
	 */
	const handlePrevMonth = useCallback( () => {
		if ( calMonth === 0 ) {
			setCalMonth( 11 );
			setCalYear( ( y ) => y - 1 );
		} else {
			setCalMonth( ( m ) => m - 1 );
		}
	}, [ calMonth ] );

	/**
	 * Navigate calendar to next month.
	 */
	const handleNextMonth = useCallback( () => {
		if ( calMonth === 11 ) {
			setCalMonth( 0 );
			setCalYear( ( y ) => y + 1 );
		} else {
			setCalMonth( ( m ) => m + 1 );
		}
	}, [ calMonth ] );

	// Render the toolbar with view toggle and filters.
	const toolbar = createElement(
		'div',
		{ className: 'wp-block-groups-event-directory__toolbar' },
		createElement(
			'div',
			{ className: 'wp-block-groups-event-directory__view-toggle', role: 'group', 'aria-label': __( 'View mode', 'wordpress-groups' ) },
			createElement(
				'button',
				{
					type: 'button',
					className: [
						'wp-block-groups-event-directory__view-btn',
						viewMode === VIEW_LIST ? 'is-active' : '',
					]
						.filter( Boolean )
						.join( ' ' ),
					onClick: () => setViewMode( VIEW_LIST ),
					'aria-pressed': viewMode === VIEW_LIST ? 'true' : 'false',
				},
				__( 'List', 'wordpress-groups' )
			),
			createElement(
				'button',
				{
					type: 'button',
					className: [
						'wp-block-groups-event-directory__view-btn',
						viewMode === VIEW_CALENDAR ? 'is-active' : '',
					]
						.filter( Boolean )
						.join( ' ' ),
					onClick: () => setViewMode( VIEW_CALENDAR ),
					'aria-pressed': viewMode === VIEW_CALENDAR ? 'true' : 'false',
				},
				__( 'Calendar', 'wordpress-groups' )
			)
		),
		createElement(
			'div',
			{ className: 'wp-block-groups-event-directory__filters' },
			viewMode === VIEW_LIST &&
				createElement(
					'div',
					{ className: 'wp-block-groups-event-directory__filter-group' },
					createElement(
						'label',
						{
							htmlFor: 'event-directory-date-filter',
							className: 'wp-block-groups-event-directory__filter-label',
						},
						__( 'Date', 'wordpress-groups' )
					),
					createElement(
						'select',
						{
							id: 'event-directory-date-filter',
							className: 'wp-block-groups-event-directory__filter-select',
							value: dateFilter,
							onChange: ( e ) => setDateFilter( e.target.value ),
						},
						Object.entries( DATE_FILTERS ).map( ( [ key, label ] ) =>
							createElement( 'option', { key, value: key }, label )
						)
					)
				),
			viewMode === VIEW_LIST && dateFilter === 'custom' &&
				createElement(
					'div',
					{ className: 'wp-block-groups-event-directory__filter-group wp-block-groups-event-directory__filter-group--custom-dates' },
					createElement(
						'label',
						{
							htmlFor: 'event-directory-after',
							className: 'wp-block-groups-event-directory__filter-label',
						},
						__( 'From', 'wordpress-groups' )
					),
					createElement( 'input', {
						type: 'date',
						id: 'event-directory-after',
						className: 'wp-block-groups-event-directory__filter-input',
						value: customAfter,
						onChange: ( e ) => setCustomAfter( e.target.value ),
					} ),
					createElement(
						'label',
						{
							htmlFor: 'event-directory-before',
							className: 'wp-block-groups-event-directory__filter-label',
						},
						__( 'To', 'wordpress-groups' )
					),
					createElement( 'input', {
						type: 'date',
						id: 'event-directory-before',
						className: 'wp-block-groups-event-directory__filter-input',
						value: customBefore,
						onChange: ( e ) => setCustomBefore( e.target.value ),
					} )
				),
			categories.length > 0 &&
				createElement(
					'div',
					{ className: 'wp-block-groups-event-directory__filter-group' },
					createElement(
						'label',
						{
							htmlFor: 'event-directory-category-filter',
							className: 'wp-block-groups-event-directory__filter-label',
						},
						__( 'Category', 'wordpress-groups' )
					),
					createElement(
						'select',
						{
							id: 'event-directory-category-filter',
							className: 'wp-block-groups-event-directory__filter-select',
							value: categoryFilter,
							onChange: ( e ) => setCategoryFilter( e.target.value ),
						},
						createElement(
							'option',
							{ value: '' },
							__( 'All Categories', 'wordpress-groups' )
						),
						categories.map( ( cat ) =>
							createElement(
								'option',
								{ key: cat.slug, value: cat.slug },
								cat.name
							)
						)
					)
				)
		)
	);

	// Loading state.
	if ( loading ) {
		return createElement(
			'div',
			{ className: 'wp-block-groups-event-directory__inner' },
			toolbar,
			createElement(
				'div',
				{
					className: 'wp-block-groups-event-directory__loading',
					role: 'status',
					'aria-live': 'polite',
				},
				createElement( 'span', { className: 'wp-block-groups-event-directory__spinner' } ),
				__( 'Loading events\u2026', 'wordpress-groups' )
			)
		);
	}

	// Error state.
	if ( error ) {
		return createElement(
			'div',
			{ className: 'wp-block-groups-event-directory__inner' },
			toolbar,
			createElement(
				'div',
				{
					className: 'wp-block-groups-event-directory__error',
					role: 'alert',
				},
				error
			)
		);
	}

	// Content based on view mode.
	let content;

	if ( viewMode === VIEW_CALENDAR ) {
		content = createElement( CalendarView, {
			events,
			year: calYear,
			month: calMonth,
			onPrevMonth: handlePrevMonth,
			onNextMonth: handleNextMonth,
		} );
	} else if ( events.length === 0 ) {
		content = createElement(
			'p',
			{ className: 'wp-block-groups-event-directory__empty' },
			__( 'No events found matching your filters.', 'wordpress-groups' )
		);
	} else {
		content = createElement(
			'div',
			null,
			createElement(
				'div',
				{
					className: 'wp-block-groups-event-directory__results-meta',
					'aria-live': 'polite',
				},
				createElement(
					'span',
					null,
					/* translators: %d: number of events */
					totalEvents === 1
						? __( '1 event found', 'wordpress-groups' )
						: String( totalEvents ) + ' ' + __( 'events found', 'wordpress-groups' )
				)
			),
			createElement(
				'div',
				{ className: 'wp-block-groups-event-directory__list' },
				events.map( ( event ) =>
					createElement( EventCard, { key: event.id, event } )
				)
			),
			totalPages > 1 &&
				createElement(
					'nav',
					{
						className: 'wp-block-groups-event-directory__pagination',
						'aria-label': __( 'Event pagination', 'wordpress-groups' ),
					},
					createElement(
						'button',
						{
							type: 'button',
							className: 'wp-block-groups-event-directory__page-btn',
							disabled: page <= 1,
							onClick: () => setPage( ( p ) => Math.max( 1, p - 1 ) ),
						},
						__( 'Previous', 'wordpress-groups' )
					),
					createElement(
						'span',
						{ className: 'wp-block-groups-event-directory__page-info' },
						/* translators: 1: current page, 2: total pages */
						`${ page } / ${ totalPages }`
					),
					createElement(
						'button',
						{
							type: 'button',
							className: 'wp-block-groups-event-directory__page-btn',
							disabled: page >= totalPages,
							onClick: () => setPage( ( p ) => Math.min( totalPages, p + 1 ) ),
						},
						__( 'Next', 'wordpress-groups' )
					)
				)
		);
	}

	return createElement(
		'div',
		{ className: 'wp-block-groups-event-directory__inner' },
		toolbar,
		content
	);
}

/**
 * Hydrate all event directory containers on the page.
 */
function init() {
	const containers = document.querySelectorAll( '.wp-block-groups-event-directory' );

	containers.forEach( ( container ) => {
		const perPage = parseInt( container.dataset.perPage, 10 ) || 10;
		let categories = [];

		try {
			categories = JSON.parse( container.dataset.categories || '[]' );
		} catch {
			categories = [];
		}

		const root = createRoot( container );
		root.render(
			createElement( EventDirectory, { perPage, categories } )
		);
	} );
}

// Hydrate when the DOM is ready.
if ( typeof document !== 'undefined' ) {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}

// Export for testing.
export {
	EventDirectory,
	EventCard,
	CalendarView,
	formatDateParam,
	formatEventDate,
	getThisWeekRange,
	getThisMonthRange,
};
