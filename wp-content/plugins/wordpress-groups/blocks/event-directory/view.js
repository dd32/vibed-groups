/**
 * Event Directory — frontend interactive directory.
 *
 * Hydrates the server-rendered container with a React-based event directory
 * supporting list/calendar toggle, date filtering, and event cards.
 */

import apiFetch from '@wordpress/api-fetch';
import { createElement, createRoot, useState, useEffect, useCallback, useMemo } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * View mode constants.
 */
const VIEW_LIST = 'list';
const VIEW_CALENDAR = 'calendar';

/**
 * Days of the week for the calendar header.
 */
const WEEKDAYS = [
	__( 'Sun', 'wordpress-groups' ),
	__( 'Mon', 'wordpress-groups' ),
	__( 'Tue', 'wordpress-groups' ),
	__( 'Wed', 'wordpress-groups' ),
	__( 'Thu', 'wordpress-groups' ),
	__( 'Fri', 'wordpress-groups' ),
	__( 'Sat', 'wordpress-groups' ),
];

/**
 * Format a date string for display.
 *
 * @param {string} dateStr Date string in Y-m-d H:i:s format.
 * @param {string} timezone Timezone identifier.
 * @return {string} Formatted date string.
 */
function formatEventDate( dateStr, timezone ) {
	if ( ! dateStr ) {
		return '';
	}

	try {
		// The API returns dates in Y-m-d H:i:s (UTC). Build a proper UTC date.
		const utcDate = dateStr.includes( 'T' ) ? new Date( dateStr ) : new Date( dateStr.replace( ' ', 'T' ) + 'Z' );

		return utcDate.toLocaleDateString( undefined, {
			weekday: 'short',
			year: 'numeric',
			month: 'short',
			day: 'numeric',
			hour: 'numeric',
			minute: '2-digit',
			timeZone: timezone || undefined,
		} );
	} catch {
		return dateStr;
	}
}

/**
 * Format a date string as YYYY-MM-DD for API queries.
 *
 * @param {Date} date Date object.
 * @return {string} Formatted date string.
 */
function toApiDate( date ) {
	const year = date.getFullYear();
	const month = String( date.getMonth() + 1 ).padStart( 2, '0' );
	const day = String( date.getDate() ).padStart( 2, '0' );
	return `${ year }-${ month }-${ day }`;
}

/**
 * Get the first and last days of a month.
 *
 * @param {number} year  Full year.
 * @param {number} month Month (0-indexed).
 * @return {Object} Object with `start` and `end` Date objects.
 */
function getMonthRange( year, month ) {
	return {
		start: new Date( year, month, 1 ),
		end: new Date( year, month + 1, 0 ),
	};
}

/**
 * Parse a date string into a YYYY-MM-DD key for calendar grouping.
 *
 * @param {string} dateStr Date string.
 * @return {string} Date key.
 */
function toDateKey( dateStr ) {
	if ( ! dateStr ) {
		return '';
	}
	return dateStr.substring( 0, 10 );
}

/**
 * View toggle component.
 *
 * @param {Object}   props
 * @param {string}   props.view     Current view mode.
 * @param {Function} props.onChange  View change handler.
 */
function ViewToggle( { view, onChange } ) {
	return createElement(
		'div',
		{
			className: 'wp-block-groups-event-directory__view-toggle',
			role: 'tablist',
			'aria-label': __( 'Directory view', 'wordpress-groups' ),
		},
		createElement(
			'button',
			{
				type: 'button',
				role: 'tab',
				'aria-selected': view === VIEW_LIST ? 'true' : 'false',
				className: [
					'wp-block-groups-event-directory__view-btn',
					view === VIEW_LIST ? 'is-active' : '',
				]
					.filter( Boolean )
					.join( ' ' ),
				onClick: () => onChange( VIEW_LIST ),
			},
			__( 'List', 'wordpress-groups' )
		),
		createElement(
			'button',
			{
				type: 'button',
				role: 'tab',
				'aria-selected': view === VIEW_CALENDAR ? 'true' : 'false',
				className: [
					'wp-block-groups-event-directory__view-btn',
					view === VIEW_CALENDAR ? 'is-active' : '',
				]
					.filter( Boolean )
					.join( ' ' ),
				onClick: () => onChange( VIEW_CALENDAR ),
			},
			__( 'Calendar', 'wordpress-groups' )
		)
	);
}

/**
 * Date filter component with month/year navigation.
 *
 * @param {Object}   props
 * @param {number}   props.year     Current year.
 * @param {number}   props.month    Current month (0-indexed).
 * @param {Function} props.onPrev   Previous month handler.
 * @param {Function} props.onNext   Next month handler.
 * @param {Function} props.onToday  Today handler.
 */
function DateFilter( { year, month, onPrev, onNext, onToday } ) {
	const monthLabel = new Date( year, month ).toLocaleDateString( undefined, {
		year: 'numeric',
		month: 'long',
	} );

	return createElement(
		'div',
		{ className: 'wp-block-groups-event-directory__date-filter' },
		createElement(
			'button',
			{
				type: 'button',
				className: 'wp-block-groups-event-directory__nav-btn',
				onClick: onPrev,
				'aria-label': __( 'Previous month', 'wordpress-groups' ),
			},
			'\u2039'
		),
		createElement(
			'span',
			{
				className: 'wp-block-groups-event-directory__month-label',
				'aria-live': 'polite',
			},
			monthLabel
		),
		createElement(
			'button',
			{
				type: 'button',
				className: 'wp-block-groups-event-directory__nav-btn',
				onClick: onNext,
				'aria-label': __( 'Next month', 'wordpress-groups' ),
			},
			'\u203A'
		),
		createElement(
			'button',
			{
				type: 'button',
				className: 'wp-block-groups-event-directory__today-btn',
				onClick: onToday,
			},
			__( 'Today', 'wordpress-groups' )
		)
	);
}

/**
 * Single event card component.
 *
 * @param {Object} props
 * @param {Object} props.event Event data object.
 */
function EventCard( { event } ) {
	const startDate = formatEventDate( event.meta.start_date, event.meta.timezone );
	const endDate = formatEventDate( event.meta.end_date, event.meta.timezone );

	return createElement(
		'article',
		{ className: 'wp-block-groups-event-directory__event-card' },
		createElement(
			'div',
			{ className: 'wp-block-groups-event-directory__event-date-badge' },
			createElement(
				'span',
				{ className: 'wp-block-groups-event-directory__event-month' },
				event.meta.start_date
					? new Date( event.meta.start_date.replace( ' ', 'T' ) + 'Z' ).toLocaleDateString( undefined, { month: 'short' } )
					: ''
			),
			createElement(
				'span',
				{ className: 'wp-block-groups-event-directory__event-day' },
				event.meta.start_date
					? new Date( event.meta.start_date.replace( ' ', 'T' ) + 'Z' ).getUTCDate()
					: ''
			)
		),
		createElement(
			'div',
			{ className: 'wp-block-groups-event-directory__event-details' },
			createElement(
				'h3',
				{ className: 'wp-block-groups-event-directory__event-title' },
				event.link
					? createElement( 'a', { href: event.link }, event.title )
					: event.title
			),
			createElement(
				'div',
				{ className: 'wp-block-groups-event-directory__event-meta' },
				startDate && createElement(
					'span',
					{ className: 'wp-block-groups-event-directory__event-time' },
					startDate
				),
				endDate && createElement(
					'span',
					{ className: 'wp-block-groups-event-directory__event-end-time' },
					' \u2013 ',
					endDate
				)
			),
			event.excerpt && createElement(
				'p',
				{ className: 'wp-block-groups-event-directory__event-excerpt' },
				event.excerpt
			),
			createElement(
				'div',
				{ className: 'wp-block-groups-event-directory__event-status' },
				createElement(
					'span',
					{
						className: [
							'wp-block-groups-event-directory__status-badge',
							`wp-block-groups-event-directory__status-badge--${ event.status }`,
						].join( ' ' ),
					},
					event.status.replace( 'event-', '' )
				)
			)
		)
	);
}

/**
 * List view component.
 *
 * @param {Object}  props
 * @param {Array}   props.events  Array of event objects.
 * @param {boolean} props.loading Whether events are loading.
 */
function ListView( { events, loading } ) {
	if ( loading ) {
		return createElement(
			'div',
			{ className: 'wp-block-groups-event-directory__loading', 'aria-live': 'polite' },
			createElement( 'p', null, __( 'Loading events\u2026', 'wordpress-groups' ) )
		);
	}

	if ( events.length === 0 ) {
		return createElement(
			'div',
			{ className: 'wp-block-groups-event-directory__empty' },
			createElement( 'p', null, __( 'No events found for this period.', 'wordpress-groups' ) )
		);
	}

	return createElement(
		'div',
		{ className: 'wp-block-groups-event-directory__list' },
		events.map( ( event ) =>
			createElement( EventCard, { key: event.id, event } )
		)
	);
}

/**
 * Calendar view component.
 *
 * @param {Object}  props
 * @param {Array}   props.events  Array of event objects.
 * @param {number}  props.year    Current year.
 * @param {number}  props.month   Current month (0-indexed).
 * @param {boolean} props.loading Whether events are loading.
 */
function CalendarView( { events, year, month, loading } ) {
	// Group events by date key.
	const eventsByDate = useMemo( () => {
		const grouped = {};
		events.forEach( ( event ) => {
			const key = toDateKey( event.meta.start_date );
			if ( ! key ) {
				return;
			}
			if ( ! grouped[ key ] ) {
				grouped[ key ] = [];
			}
			grouped[ key ].push( event );
		} );
		return grouped;
	}, [ events ] );

	// Build the calendar grid.
	const firstDay = new Date( year, month, 1 ).getDay();
	const daysInMonth = new Date( year, month + 1, 0 ).getDate();
	const today = toApiDate( new Date() );

	const cells = [];

	// Empty cells before the first day.
	for ( let i = 0; i < firstDay; i++ ) {
		cells.push(
			createElement( 'div', {
				key: `empty-${ i }`,
				className: 'wp-block-groups-event-directory__cal-cell wp-block-groups-event-directory__cal-cell--empty',
			} )
		);
	}

	// Day cells.
	for ( let day = 1; day <= daysInMonth; day++ ) {
		const dateKey = `${ year }-${ String( month + 1 ).padStart( 2, '0' ) }-${ String( day ).padStart( 2, '0' ) }`;
		const dayEvents = eventsByDate[ dateKey ] || [];
		const isToday = dateKey === today;

		cells.push(
			createElement(
				'div',
				{
					key: dateKey,
					className: [
						'wp-block-groups-event-directory__cal-cell',
						dayEvents.length > 0 ? 'has-events' : '',
						isToday ? 'is-today' : '',
					]
						.filter( Boolean )
						.join( ' ' ),
				},
				createElement(
					'span',
					{ className: 'wp-block-groups-event-directory__cal-day-number' },
					day
				),
				dayEvents.length > 0 &&
					createElement(
						'div',
						{ className: 'wp-block-groups-event-directory__cal-events' },
						dayEvents.map( ( event ) =>
							createElement(
								'a',
								{
									key: event.id,
									href: event.link,
									className: 'wp-block-groups-event-directory__cal-event-dot',
									title: event.title,
								},
								createElement(
									'span',
									{ className: 'wp-block-groups-event-directory__cal-event-label' },
									event.title
								)
							)
						)
					)
			)
		);
	}

	if ( loading ) {
		return createElement(
			'div',
			{ className: 'wp-block-groups-event-directory__loading', 'aria-live': 'polite' },
			createElement( 'p', null, __( 'Loading events\u2026', 'wordpress-groups' ) )
		);
	}

	return createElement(
		'div',
		{
			className: 'wp-block-groups-event-directory__calendar',
			role: 'grid',
			'aria-label': __( 'Event calendar', 'wordpress-groups' ),
		},
		createElement(
			'div',
			{
				className: 'wp-block-groups-event-directory__cal-header',
				role: 'row',
			},
			WEEKDAYS.map( ( dayName ) =>
				createElement(
					'div',
					{
						key: dayName,
						className: 'wp-block-groups-event-directory__cal-weekday',
						role: 'columnheader',
					},
					dayName
				)
			)
		),
		createElement(
			'div',
			{ className: 'wp-block-groups-event-directory__cal-grid' },
			cells
		)
	);
}

/**
 * Main Event Directory component.
 */
function EventDirectory() {
	const now = new Date();
	const [ view, setView ] = useState( VIEW_LIST );
	const [ year, setYear ] = useState( now.getFullYear() );
	const [ month, setMonth ] = useState( now.getMonth() );
	const [ events, setEvents ] = useState( [] );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( '' );

	/**
	 * Fetch events for the current month.
	 */
	const fetchEvents = useCallback( async () => {
		setLoading( true );
		setError( '' );

		const { start, end } = getMonthRange( year, month );

		try {
			const result = await apiFetch( {
				path: `/groups/v1/events?after=${ toApiDate( start ) }&before=${ toApiDate( end ) }&per_page=100`,
			} );

			setEvents( result );
		} catch ( err ) {
			setError(
				err.message ||
					__( 'Failed to load events. Please try again.', 'wordpress-groups' )
			);
			setEvents( [] );
		} finally {
			setLoading( false );
		}
	}, [ year, month ] );

	useEffect( () => {
		fetchEvents();
	}, [ fetchEvents ] );

	/**
	 * Navigate to the previous month.
	 */
	const handlePrev = useCallback( () => {
		if ( month === 0 ) {
			setMonth( 11 );
			setYear( ( prev ) => prev - 1 );
		} else {
			setMonth( ( prev ) => prev - 1 );
		}
	}, [ month ] );

	/**
	 * Navigate to the next month.
	 */
	const handleNext = useCallback( () => {
		if ( month === 11 ) {
			setMonth( 0 );
			setYear( ( prev ) => prev + 1 );
		} else {
			setMonth( ( prev ) => prev + 1 );
		}
	}, [ month ] );

	/**
	 * Navigate to today.
	 */
	const handleToday = useCallback( () => {
		const today = new Date();
		setYear( today.getFullYear() );
		setMonth( today.getMonth() );
	}, [] );

	return createElement(
		'div',
		{ className: 'wp-block-groups-event-directory__inner' },
		createElement(
			'div',
			{ className: 'wp-block-groups-event-directory__toolbar' },
			createElement( ViewToggle, { view, onChange: setView } ),
			createElement( DateFilter, {
				year,
				month,
				onPrev: handlePrev,
				onNext: handleNext,
				onToday: handleToday,
			} )
		),
		error &&
			createElement(
				'div',
				{
					className: 'wp-block-groups-event-directory__error',
					role: 'alert',
				},
				error
			),
		view === VIEW_LIST
			? createElement( ListView, { events, loading } )
			: createElement( CalendarView, { events, year, month, loading } )
	);
}

/**
 * Hydrate all event directory containers on the page.
 */
function init() {
	const containers = document.querySelectorAll( '.wp-block-groups-event-directory' );

	containers.forEach( ( container ) => {
		const root = createRoot( container );
		root.render( createElement( EventDirectory ) );
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
export {
	EventDirectory,
	ViewToggle,
	DateFilter,
	EventCard,
	ListView,
	CalendarView,
	formatEventDate,
	toApiDate,
	toDateKey,
};
