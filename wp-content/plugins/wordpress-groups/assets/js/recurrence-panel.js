/**
 * Recurrence Panel — PluginDocumentSettingPanel for event recurrence.
 *
 * Adds a sidebar panel to the event editor that allows organizers to
 * set a recurrence rule (None, Weekly, Biweekly, Monthly) and see a
 * preview of the next 3 occurrences.
 *
 * @package Groups
 */
( function () {
	const { registerPlugin } = wp.plugins;
	const { PluginDocumentSettingPanel } = wp.editPost;
	const { SelectControl, Spinner, Notice } = wp.components;
	const { useSelect, useDispatch } = wp.data;
	const { useState, useEffect } = wp.element;
	const { __ } = wp.i18n;
	const apiFetch = wp.apiFetch;

	const FREQUENCY_OPTIONS = [
		{ label: __( 'None', 'wordpress-groups' ), value: 'none' },
		{ label: __( 'Weekly', 'wordpress-groups' ), value: 'weekly' },
		{ label: __( 'Biweekly', 'wordpress-groups' ), value: 'biweekly' },
		{ label: __( 'Monthly', 'wordpress-groups' ), value: 'monthly-date' },
	];

	/**
	 * Format a Y-m-d date string into a human-readable form.
	 *
	 * @param {string} dateStr Date in Y-m-d format.
	 * @return {string} Formatted date string.
	 */
	function formatDate( dateStr ) {
		const date = new Date( dateStr + 'T00:00:00' );
		return date.toLocaleDateString( undefined, {
			weekday: 'short',
			year: 'numeric',
			month: 'short',
			day: 'numeric',
		} );
	}

	/**
	 * Parse a stored recurrence rule JSON string into a frequency value.
	 *
	 * @param {string} metaValue The raw meta value (JSON string or empty).
	 * @return {string} The frequency string, or 'none'.
	 */
	function parseFrequency( metaValue ) {
		if ( ! metaValue ) {
			return 'none';
		}

		try {
			const parsed = JSON.parse( metaValue );
			return parsed.frequency || 'none';
		} catch ( e ) {
			return 'none';
		}
	}

	/**
	 * Build a recurrence rule JSON string from a frequency and start date.
	 *
	 * @param {string} frequency The selected frequency.
	 * @param {string} startDate The event start date (Y-m-d or ISO).
	 * @return {string} JSON string for the rule, or empty string for 'none'.
	 */
	function buildRuleJSON( frequency, startDate ) {
		if ( frequency === 'none' || ! startDate ) {
			return '';
		}

		const date = new Date( startDate );
		const rule = { frequency: frequency };

		if ( [ 'weekly', 'biweekly', 'monthly-day' ].includes( frequency ) ) {
			rule.day_of_week = date.getUTCDay();
		}

		if ( frequency === 'monthly-date' ) {
			rule.day_of_month = date.getUTCDate();
		}

		return JSON.stringify( rule );
	}

	/**
	 * The Recurrence Panel component.
	 */
	function RecurrencePanel() {
		const { postType, meta, startDate } = useSelect( function ( select ) {
			const editor = select( 'core/editor' );
			const currentPostType = editor.getCurrentPostType();
			const postMeta = editor.getEditedPostAttribute( 'meta' ) || {};
			const allMeta = postMeta || {};

			// Try to get start date from meta.
			let eventStartDate = allMeta._event_start_utc || '';

			// Fall back to post date if no event start date is set.
			if ( ! eventStartDate ) {
				eventStartDate = editor.getEditedPostAttribute( 'date' ) || '';
			}

			return {
				postType: currentPostType,
				meta: allMeta,
				startDate: eventStartDate,
			};
		}, [] );

		const { editPost } = useDispatch( 'core/editor' );

		const metaKey = window.groupsRecurrence?.metaKey || '_event_recurrence_rule';
		const expectedPostType = window.groupsRecurrence?.postType || 'event';

		const currentMetaValue = meta[ metaKey ] || '';
		const [ frequency, setFrequency ] = useState( parseFrequency( currentMetaValue ) );
		const [ occurrences, setOccurrences ] = useState( [] );
		const [ loading, setLoading ] = useState( false );
		const [ error, setError ] = useState( '' );

		// Don't render for non-event post types.
		if ( postType !== expectedPostType ) {
			return null;
		}

		/**
		 * When frequency changes, update the post meta and fetch preview.
		 */
		function onFrequencyChange( newFrequency ) {
			setFrequency( newFrequency );
			setError( '' );

			const ruleJSON = buildRuleJSON( newFrequency, startDate );

			editPost( {
				meta: {
					[ metaKey ]: ruleJSON,
				},
			} );

			// Fetch occurrence preview.
			if ( newFrequency === 'none' ) {
				setOccurrences( [] );
				return;
			}

			if ( ! startDate ) {
				setError( __( 'Set an event start date first.', 'wordpress-groups' ) );
				return;
			}

			fetchPreview( newFrequency, startDate );
		}

		/**
		 * Fetch occurrence preview from the REST endpoint.
		 */
		function fetchPreview( freq, date ) {
			setLoading( true );
			setOccurrences( [] );

			// Normalize date to Y-m-d.
			const dateStr = date.substring( 0, 10 );

			apiFetch( {
				path: '/groups/v1/recurrence-preview',
				method: 'POST',
				data: {
					frequency: freq,
					start_date: dateStr,
				},
			} )
				.then( function ( response ) {
					setOccurrences( response.occurrences || [] );
					setLoading( false );
				} )
				.catch( function () {
					setError( __( 'Could not load occurrence preview.', 'wordpress-groups' ) );
					setLoading( false );
				} );
		}

		// Fetch preview on mount if there is already a rule set.
		useEffect( function () {
			if ( frequency !== 'none' && startDate ) {
				fetchPreview( frequency, startDate );
			}
		}, [] ); // eslint-disable-line react-hooks/exhaustive-deps

		return wp.element.createElement(
			PluginDocumentSettingPanel,
			{
				name: 'groups-recurrence-panel',
				title: __( 'Recurrence', 'wordpress-groups' ),
				className: 'groups-recurrence-panel',
			},
			wp.element.createElement( SelectControl, {
				label: __( 'Repeat', 'wordpress-groups' ),
				value: frequency,
				options: FREQUENCY_OPTIONS,
				onChange: onFrequencyChange,
			} ),
			error &&
				wp.element.createElement(
					Notice,
					{
						status: 'warning',
						isDismissible: false,
						className: 'groups-recurrence-notice',
					},
					error
				),
			loading && wp.element.createElement( Spinner, null ),
			! loading &&
				occurrences.length > 0 &&
				wp.element.createElement(
					'div',
					{ className: 'groups-recurrence-preview' },
					wp.element.createElement(
						'p',
						{
							style: {
								fontWeight: 600,
								marginBottom: '8px',
								fontSize: '11px',
								textTransform: 'uppercase',
								letterSpacing: '0.5px',
							},
						},
						__( 'Next occurrences', 'wordpress-groups' )
					),
					wp.element.createElement(
						'ul',
						{
							style: {
								margin: 0,
								padding: 0,
								listStyle: 'none',
							},
						},
						occurrences.map( function ( dateStr, index ) {
							return wp.element.createElement(
								'li',
								{
									key: index,
									style: {
										padding: '6px 0',
										borderBottom:
											index < occurrences.length - 1
												? '1px solid #e0e0e0'
												: 'none',
										fontSize: '13px',
									},
								},
								formatDate( dateStr )
							);
						} )
					)
				)
		);
	}

	registerPlugin( 'groups-recurrence-panel', {
		render: RecurrencePanel,
		icon: 'calendar-alt',
	} );
} )();
