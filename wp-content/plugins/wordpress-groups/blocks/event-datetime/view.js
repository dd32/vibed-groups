/**
 * Event Date & Time — frontend view script.
 *
 * Reads UTC datetime from <time> elements and appends the viewer's local time
 * as a secondary display using Intl.DateTimeFormat.
 */

/**
 * Format a Date object in the viewer's local timezone.
 *
 * @param {Date}    date       The date to format.
 * @param {Object}  options    Intl.DateTimeFormat options override.
 * @return {string} Formatted date/time string.
 */
function formatLocal( date, options = {} ) {
	try {
		const formatter = new Intl.DateTimeFormat( undefined, {
			year: 'numeric',
			month: 'long',
			day: 'numeric',
			hour: 'numeric',
			minute: '2-digit',
			timeZoneName: 'short',
			...options,
		} );
		return formatter.format( date );
	} catch {
		return '';
	}
}

/**
 * Format just the time portion in the viewer's local timezone.
 *
 * @param {Date} date The date to format.
 * @return {string} Formatted time string.
 */
function formatLocalTime( date ) {
	try {
		const formatter = new Intl.DateTimeFormat( undefined, {
			hour: 'numeric',
			minute: '2-digit',
			timeZoneName: 'short',
		} );
		return formatter.format( date );
	} catch {
		return '';
	}
}

/**
 * Check if two dates are on different calendar days in the local timezone.
 *
 * @param {Date} a First date.
 * @param {Date} b Second date.
 * @return {boolean} True if the dates are on different days.
 */
function isDifferentDay( a, b ) {
	return (
		a.getFullYear() !== b.getFullYear() ||
		a.getMonth() !== b.getMonth() ||
		a.getDate() !== b.getDate()
	);
}

/**
 * Check if the viewer's local timezone matches the event timezone displayed
 * by comparing the formatted UTC offset. If they match, no need to show local time.
 *
 * @param {Date}   date          A date to check.
 * @param {string} serverDisplay The server-rendered time string.
 * @return {boolean} True if local time adds no value.
 */
function isLocalTimezoneRedundant( date, serverDisplay ) {
	try {
		const localAbbr = new Intl.DateTimeFormat( undefined, {
			timeZoneName: 'short',
		} )
			.formatToParts( date )
			.find( ( p ) => p.type === 'timeZoneName' );

		if ( localAbbr && serverDisplay.includes( localAbbr.value ) ) {
			return true;
		}
	} catch {
		// Fall through — show local time to be safe.
	}
	return false;
}

/**
 * Process a single event-datetime block and add local time display.
 *
 * @param {Element} block The block's root element.
 */
function processBlock( block ) {
	const timeEl = block.querySelector( 'time[data-utc-start]' );
	const localContainer = block.querySelector(
		'.wp-block-groups-event-datetime__local'
	);

	if ( ! timeEl || ! localContainer ) {
		return;
	}

	const startUtc = timeEl.getAttribute( 'data-utc-start' );
	const endUtc = timeEl.getAttribute( 'data-utc-end' );
	const serverText = timeEl.textContent || '';

	if ( ! startUtc ) {
		return;
	}

	const startDate = new Date( startUtc );

	if ( isNaN( startDate.getTime() ) ) {
		return;
	}

	// Skip if the viewer is already in the event's timezone.
	if ( isLocalTimezoneRedundant( startDate, serverText ) ) {
		return;
	}

	let localText = '';

	if ( endUtc ) {
		const endDate = new Date( endUtc );

		if ( ! isNaN( endDate.getTime() ) ) {
			if ( isDifferentDay( startDate, endDate ) ) {
				// Multi-day in local timezone.
				localText =
					formatLocal( startDate ) + ' \u2013 ' + formatLocal( endDate );
			} else {
				// Same day: show full start, then just end time.
				localText =
					formatLocal( startDate, { timeZoneName: undefined } ) +
					' \u2013 ' +
					formatLocalTime( endDate );
			}
		} else {
			localText = formatLocal( startDate );
		}
	} else {
		localText = formatLocal( startDate );
	}

	if ( localText ) {
		localContainer.textContent = localText;
		localContainer.removeAttribute( 'hidden' );
	}
}

/**
 * Initialize: find all event-datetime blocks and process them.
 */
function init() {
	const blocks = document.querySelectorAll(
		'.wp-block-groups-event-datetime'
	);
	blocks.forEach( processBlock );
}

if ( typeof document !== 'undefined' ) {
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}
