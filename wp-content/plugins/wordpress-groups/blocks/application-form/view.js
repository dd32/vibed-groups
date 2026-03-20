/**
 * Application Form — frontend multi-step form.
 *
 * Hydrates the server-rendered container with a React-based multi-step
 * application form for starting a WordPress community group.
 */

import apiFetch from '@wordpress/api-fetch';
import { createElement, createRoot, useState, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Total number of steps in the application form.
 */
const TOTAL_STEPS = 4;

/**
 * Step labels for the progress indicator.
 */
const STEP_LABELS = [
	__( 'Group Details', 'wordpress-groups' ),
	__( 'Your Background', 'wordpress-groups' ),
	__( 'Profile & Agreement', 'wordpress-groups' ),
	__( 'Review & Submit', 'wordpress-groups' ),
];

/**
 * Validate a single field value.
 *
 * @param {string} name  Field name.
 * @param {*}      value Field value.
 * @return {string} Error message, or empty string if valid.
 */
function validateField( name, value ) {
	switch ( name ) {
		case 'city':
			return value.trim()
				? ''
				: __( 'City is required.', 'wordpress-groups' );
		case 'groupName':
			return value.trim()
				? ''
				: __( 'Proposed group name is required.', 'wordpress-groups' );
		case 'wpExperience':
			return value.trim()
				? ''
				: __( 'Please describe your WordPress experience.', 'wordpress-groups' );
		case 'whyOrganize':
			return value.trim()
				? ''
				: __( 'Please explain why you want to organize a group.', 'wordpress-groups' );
		case 'profileUrl':
			if ( ! value.trim() ) {
				return __( 'WordPress.org profile link is required.', 'wordpress-groups' );
			}
			if ( ! /^https?:\/\/(profiles\.)?wordpress\.org\/.+/i.test( value.trim() ) ) {
				return __( 'Please enter a valid WordPress.org profile URL.', 'wordpress-groups' );
			}
			return '';
		case 'cocAccepted':
			return value
				? ''
				: __( 'You must accept the Code of Conduct to proceed.', 'wordpress-groups' );
		default:
			return '';
	}
}

/**
 * Fields required per step (0-indexed).
 */
const STEP_FIELDS = [
	[ 'city', 'groupName' ],
	[ 'wpExperience', 'whyOrganize' ],
	[ 'profileUrl', 'cocAccepted' ],
	[], // Review step has no new fields.
];

/**
 * Validate all fields for a given step.
 *
 * @param {number} step     Current step (0-indexed).
 * @param {Object} formData Form data object.
 * @return {Object} Errors keyed by field name.
 */
function validateStep( step, formData ) {
	const errors = {};
	const fields = STEP_FIELDS[ step ] || [];

	fields.forEach( ( field ) => {
		const error = validateField( field, formData[ field ] );
		if ( error ) {
			errors[ field ] = error;
		}
	} );

	return errors;
}

/**
 * Progress Indicator component.
 *
 * @param {Object} props
 * @param {number} props.currentStep Current step (0-indexed).
 */
function ProgressIndicator( { currentStep } ) {
	return createElement(
		'nav',
		{
			className: 'wp-block-groups-application-form__progress',
			'aria-label': __( 'Application progress', 'wordpress-groups' ),
		},
		createElement(
			'ol',
			{ className: 'wp-block-groups-application-form__steps' },
			STEP_LABELS.map( ( label, index ) =>
				createElement(
					'li',
					{
						key: index,
						className: [
							'wp-block-groups-application-form__step-item',
							index === currentStep ? 'is-current' : '',
							index < currentStep ? 'is-complete' : '',
						]
							.filter( Boolean )
							.join( ' ' ),
						'aria-current': index === currentStep ? 'step' : undefined,
					},
					createElement(
						'span',
						{ className: 'wp-block-groups-application-form__step-number', 'aria-hidden': 'true' },
						index < currentStep ? '\u2713' : String( index + 1 )
					),
					createElement(
						'span',
						{ className: 'wp-block-groups-application-form__step-label' },
						label
					)
				)
			)
		)
	);
}

/**
 * Form field with label and error display.
 *
 * @param {Object}  props
 * @param {string}  props.id          Field ID.
 * @param {string}  props.label       Field label.
 * @param {string}  props.type        Input type (text, url, textarea).
 * @param {string}  props.value       Current value.
 * @param {string}  props.error       Error message.
 * @param {string}  props.help        Help text.
 * @param {boolean} props.required    Whether the field is required.
 * @param {Function} props.onChange   Change handler.
 */
function FormField( { id, label, type = 'text', value, error, help, required = true, onChange } ) {
	const errorId = `${ id }-error`;
	const helpId = help ? `${ id }-help` : undefined;
	const describedBy = [ error ? errorId : '', helpId || '' ].filter( Boolean ).join( ' ' ) || undefined;

	const inputProps = {
		id,
		name: id,
		value,
		onChange: ( e ) => onChange( e.target.value ),
		'aria-invalid': error ? 'true' : undefined,
		'aria-describedby': describedBy,
		'aria-required': required ? 'true' : undefined,
		className: [
			'wp-block-groups-application-form__input',
			error ? 'has-error' : '',
		]
			.filter( Boolean )
			.join( ' ' ),
	};

	const input =
		type === 'textarea'
			? createElement( 'textarea', { ...inputProps, rows: 4 } )
			: createElement( 'input', { ...inputProps, type } );

	return createElement(
		'div',
		{ className: 'wp-block-groups-application-form__field' },
		createElement(
			'label',
			{
				htmlFor: id,
				className: 'wp-block-groups-application-form__label',
			},
			label,
			required && createElement( 'span', { className: 'wp-block-groups-application-form__required', 'aria-hidden': 'true' }, ' *' )
		),
		input,
		help &&
			createElement(
				'p',
				{ id: helpId, className: 'wp-block-groups-application-form__help' },
				help
			),
		error &&
			createElement(
				'p',
				{
					id: errorId,
					className: 'wp-block-groups-application-form__error',
					role: 'alert',
				},
				error
			)
	);
}

/**
 * Step 1: Group Details.
 *
 * @param {Object}   props
 * @param {Object}   props.formData Form data.
 * @param {Object}   props.errors   Validation errors.
 * @param {Function} props.onChange  Field change handler.
 */
function StepGroupDetails( { formData, errors, onChange } ) {
	return createElement(
		'fieldset',
		{ className: 'wp-block-groups-application-form__fieldset' },
		createElement( 'legend', { className: 'wp-block-groups-application-form__legend' }, __( 'Group Details', 'wordpress-groups' ) ),
		createElement( FormField, {
			id: 'city',
			label: __( 'City', 'wordpress-groups' ),
			value: formData.city,
			error: errors.city,
			help: __( 'The city where your group will primarily meet.', 'wordpress-groups' ),
			onChange: ( val ) => onChange( 'city', val ),
		} ),
		createElement( FormField, {
			id: 'groupName',
			label: __( 'Proposed Group Name', 'wordpress-groups' ),
			value: formData.groupName,
			error: errors.groupName,
			help: __( 'For example: "Melbourne WordPress User Group" or "Paris WordPress Meetup".', 'wordpress-groups' ),
			onChange: ( val ) => onChange( 'groupName', val ),
		} )
	);
}

/**
 * Step 2: Organizer Background.
 *
 * @param {Object}   props
 * @param {Object}   props.formData Form data.
 * @param {Object}   props.errors   Validation errors.
 * @param {Function} props.onChange  Field change handler.
 */
function StepBackground( { formData, errors, onChange } ) {
	return createElement(
		'fieldset',
		{ className: 'wp-block-groups-application-form__fieldset' },
		createElement( 'legend', { className: 'wp-block-groups-application-form__legend' }, __( 'Your Background', 'wordpress-groups' ) ),
		createElement( FormField, {
			id: 'wpExperience',
			label: __( 'WordPress Experience', 'wordpress-groups' ),
			type: 'textarea',
			value: formData.wpExperience,
			error: errors.wpExperience,
			help: __( 'Describe your experience with WordPress (using, developing, contributing, etc.).', 'wordpress-groups' ),
			onChange: ( val ) => onChange( 'wpExperience', val ),
		} ),
		createElement( FormField, {
			id: 'whyOrganize',
			label: __( 'Why do you want to organize a group?', 'wordpress-groups' ),
			type: 'textarea',
			value: formData.whyOrganize,
			error: errors.whyOrganize,
			help: __( 'Tell us about your motivation and what you hope to achieve.', 'wordpress-groups' ),
			onChange: ( val ) => onChange( 'whyOrganize', val ),
		} )
	);
}

/**
 * Step 3: Profile & Code of Conduct.
 *
 * @param {Object}   props
 * @param {Object}   props.formData Form data.
 * @param {Object}   props.errors   Validation errors.
 * @param {Function} props.onChange  Field change handler.
 */
function StepProfile( { formData, errors, onChange } ) {
	return createElement(
		'fieldset',
		{ className: 'wp-block-groups-application-form__fieldset' },
		createElement( 'legend', { className: 'wp-block-groups-application-form__legend' }, __( 'Profile & Agreement', 'wordpress-groups' ) ),
		createElement( FormField, {
			id: 'profileUrl',
			label: __( 'WordPress.org Profile URL', 'wordpress-groups' ),
			type: 'url',
			value: formData.profileUrl,
			error: errors.profileUrl,
			help: __( 'Your profile at profiles.wordpress.org (e.g., https://profiles.wordpress.org/yourname).', 'wordpress-groups' ),
			onChange: ( val ) => onChange( 'profileUrl', val ),
		} ),
		createElement(
			'div',
			{
				className: [
					'wp-block-groups-application-form__field',
					'wp-block-groups-application-form__field--checkbox',
				].join( ' ' ),
			},
			createElement(
				'label',
				{
					htmlFor: 'cocAccepted',
					className: 'wp-block-groups-application-form__checkbox-label',
				},
				createElement( 'input', {
					type: 'checkbox',
					id: 'cocAccepted',
					name: 'cocAccepted',
					checked: formData.cocAccepted,
					onChange: ( e ) => onChange( 'cocAccepted', e.target.checked ),
					'aria-invalid': errors.cocAccepted ? 'true' : undefined,
					'aria-describedby': errors.cocAccepted ? 'cocAccepted-error' : undefined,
					'aria-required': 'true',
					className: 'wp-block-groups-application-form__checkbox',
				} ),
				createElement(
					'span',
					null,
					__( 'I agree to the ', 'wordpress-groups' ),
					createElement(
						'a',
						{
							href: 'https://make.wordpress.org/community/handbook/meetup-organizer/responding-to-code-of-conduct-violations/',
							target: '_blank',
							rel: 'noopener noreferrer',
						},
						__( 'WordPress Community Code of Conduct', 'wordpress-groups' )
					)
				)
			),
			errors.cocAccepted &&
				createElement(
					'p',
					{
						id: 'cocAccepted-error',
						className: 'wp-block-groups-application-form__error',
						role: 'alert',
					},
					errors.cocAccepted
				)
		)
	);
}

/**
 * Step 4: Review & Submit.
 *
 * @param {Object} props
 * @param {Object} props.formData Form data.
 */
function StepReview( { formData } ) {
	return createElement(
		'fieldset',
		{ className: 'wp-block-groups-application-form__fieldset' },
		createElement( 'legend', { className: 'wp-block-groups-application-form__legend' }, __( 'Review Your Application', 'wordpress-groups' ) ),
		createElement(
			'div',
			{ className: 'wp-block-groups-application-form__review' },
			createElement(
				'dl',
				{ className: 'wp-block-groups-application-form__review-list' },
				createElement( 'dt', null, __( 'City', 'wordpress-groups' ) ),
				createElement( 'dd', null, formData.city ),
				createElement( 'dt', null, __( 'Proposed Group Name', 'wordpress-groups' ) ),
				createElement( 'dd', null, formData.groupName ),
				createElement( 'dt', null, __( 'WordPress Experience', 'wordpress-groups' ) ),
				createElement( 'dd', null, formData.wpExperience ),
				createElement( 'dt', null, __( 'Why do you want to organize?', 'wordpress-groups' ) ),
				createElement( 'dd', null, formData.whyOrganize ),
				createElement( 'dt', null, __( 'WordPress.org Profile', 'wordpress-groups' ) ),
				createElement(
					'dd',
					null,
					createElement(
						'a',
						{ href: formData.profileUrl, target: '_blank', rel: 'noopener noreferrer' },
						formData.profileUrl
					)
				),
				createElement( 'dt', null, __( 'Code of Conduct', 'wordpress-groups' ) ),
				createElement( 'dd', null, __( 'Accepted', 'wordpress-groups' ) )
			)
		)
	);
}

/**
 * Main Application Form component.
 */
function ApplicationForm() {
	const [ step, setStep ] = useState( 0 );
	const [ formData, setFormData ] = useState( {
		city: '',
		groupName: '',
		wpExperience: '',
		whyOrganize: '',
		profileUrl: '',
		cocAccepted: false,
	} );
	const [ errors, setErrors ] = useState( {} );
	const [ isSubmitting, setIsSubmitting ] = useState( false );
	const [ submitError, setSubmitError ] = useState( '' );
	const [ isSubmitted, setIsSubmitted ] = useState( false );
	const formRef = useRef( null );

	/**
	 * Update a single form field value and clear its error.
	 */
	const handleFieldChange = useCallback( ( name, value ) => {
		setFormData( ( prev ) => ( { ...prev, [ name ]: value } ) );
		setErrors( ( prev ) => {
			const next = { ...prev };
			delete next[ name ];
			return next;
		} );
	}, [] );

	/**
	 * Advance to the next step after validation.
	 */
	const handleNext = useCallback( () => {
		const stepErrors = validateStep( step, formData );

		if ( Object.keys( stepErrors ).length > 0 ) {
			setErrors( stepErrors );
			// Focus the first field with an error.
			if ( formRef.current ) {
				const firstErrorField = formRef.current.querySelector( '[aria-invalid="true"]' );
				if ( firstErrorField ) {
					firstErrorField.focus();
				}
			}
			return;
		}

		setErrors( {} );
		setStep( ( prev ) => Math.min( prev + 1, TOTAL_STEPS - 1 ) );
	}, [ step, formData ] );

	/**
	 * Go back to the previous step.
	 */
	const handlePrev = useCallback( () => {
		setErrors( {} );
		setStep( ( prev ) => Math.max( prev - 1, 0 ) );
	}, [] );

	/**
	 * Submit the application via the REST API.
	 */
	const handleSubmit = useCallback( async () => {
		setIsSubmitting( true );
		setSubmitError( '' );

		try {
			await apiFetch( {
				path: '/groups/v1/applications',
				method: 'POST',
				data: {
					city: formData.city,
					group_name: formData.groupName,
					wp_experience: formData.wpExperience,
					why_organize: formData.whyOrganize,
					profile_url: formData.profileUrl,
					coc_accepted: formData.cocAccepted,
				},
			} );

			setIsSubmitted( true );
		} catch ( err ) {
			setSubmitError(
				err.message ||
					__( 'Something went wrong submitting your application. Please try again.', 'wordpress-groups' )
			);
		} finally {
			setIsSubmitting( false );
		}
	}, [ formData ] );

	// Success state.
	if ( isSubmitted ) {
		return createElement(
			'div',
			{ className: 'wp-block-groups-application-form__success', role: 'status' },
			createElement( 'h2', null, __( 'Application Submitted!', 'wordpress-groups' ) ),
			createElement(
				'p',
				null,
				__( 'Thank you for your interest in organizing a WordPress community group. Your application has been received and will be reviewed by a community deputy.', 'wordpress-groups' )
			),
			createElement(
				'p',
				null,
				__( 'You will receive an email notification when the status of your application changes.', 'wordpress-groups' )
			)
		);
	}

	// Render the current step component.
	const stepComponents = [
		createElement( StepGroupDetails, { formData, errors, onChange: handleFieldChange } ),
		createElement( StepBackground, { formData, errors, onChange: handleFieldChange } ),
		createElement( StepProfile, { formData, errors, onChange: handleFieldChange } ),
		createElement( StepReview, { formData } ),
	];

	return createElement(
		'div',
		{ className: 'wp-block-groups-application-form__inner', ref: formRef },
		createElement( 'h2', { className: 'wp-block-groups-application-form__title' }, __( 'Apply to Organize a WordPress Group', 'wordpress-groups' ) ),
		createElement( ProgressIndicator, { currentStep: step } ),
		createElement(
			'form',
			{
				className: 'wp-block-groups-application-form__form',
				onSubmit: ( e ) => {
					e.preventDefault();
					if ( step === TOTAL_STEPS - 1 ) {
						handleSubmit();
					} else {
						handleNext();
					}
				},
				noValidate: true,
			},
			stepComponents[ step ],
			submitError &&
				createElement(
					'div',
					{
						className: 'wp-block-groups-application-form__submit-error',
						role: 'alert',
					},
					submitError
				),
			createElement(
				'div',
				{ className: 'wp-block-groups-application-form__actions' },
				step > 0 &&
					createElement(
						'button',
						{
							type: 'button',
							className: 'wp-block-groups-application-form__btn wp-block-groups-application-form__btn--secondary',
							onClick: handlePrev,
							disabled: isSubmitting,
						},
						__( 'Previous', 'wordpress-groups' )
					),
				step < TOTAL_STEPS - 1 &&
					createElement(
						'button',
						{
							type: 'submit',
							className: 'wp-block-groups-application-form__btn wp-block-groups-application-form__btn--primary',
						},
						__( 'Next', 'wordpress-groups' )
					),
				step === TOTAL_STEPS - 1 &&
					createElement(
						'button',
						{
							type: 'submit',
							className: 'wp-block-groups-application-form__btn wp-block-groups-application-form__btn--primary',
							disabled: isSubmitting,
							'aria-busy': isSubmitting ? 'true' : undefined,
						},
						isSubmitting
							? __( 'Submitting\u2026', 'wordpress-groups' )
							: __( 'Submit Application', 'wordpress-groups' )
					)
			)
		)
	);
}

/**
 * Hydrate all application form containers on the page.
 */
function init() {
	const containers = document.querySelectorAll( '.wp-block-groups-application-form[data-logged-in="1"]' );

	containers.forEach( ( container ) => {
		const root = createRoot( container );
		root.render( createElement( ApplicationForm ) );
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
export { ApplicationForm, ProgressIndicator, FormField, validateField, validateStep };
