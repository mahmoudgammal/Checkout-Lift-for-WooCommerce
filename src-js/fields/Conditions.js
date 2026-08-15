/**
 * Condition builders for a checkout field.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { __ } from '@wordpress/i18n';
import { annotateFieldOperators, ConditionScopeNote } from './CheckoutNotice';
import { Button, CheckboxControl, SelectControl, TextControl } from '@wordpress/components';

const ACTIONS = [
	{ value: 'show', label: __( 'Show', 'checkout-lift-for-woocommerce' ) },
	{ value: 'hide', label: __( 'Hide', 'checkout-lift-for-woocommerce' ) },
];

const LOGIC = [
	{ value: 'all', label: __( 'All conditions match', 'checkout-lift-for-woocommerce' ) },
	{ value: 'any', label: __( 'Any condition matches', 'checkout-lift-for-woocommerce' ) },
];

const FIELD_OPERATORS = [
	'is',
	'is_not',
	'contains',
	'not_contains',
	'starts_with',
	'ends_with',
	'is_empty',
	'is_not_empty',
	'greater_than',
	'less_than',
	'checked',
	'unchecked',
	'in',
	'not_in',
].map( ( value ) => ( { value, label: value.replace( /_/g, ' ' ) } ) );

const EFFECTS = [
	{ value: 'fade', label: __( 'Fade', 'checkout-lift-for-woocommerce' ) },
	{ value: 'slide', label: __( 'Slide', 'checkout-lift-for-woocommerce' ) },
];

/**
 * A group of rules with an action and a logic mode.
 *
 * @param {Object}   props            Component props.
 * @param {string}   props.title      Heading.
 * @param {Object?}  props.group      Stored group.
 * @param {Function} props.onChange   Called with the new group, or null.
 * @param {Array}    props.subjects   Options for the first column.
 * @param {Array}    props.operators  Options for the operator column.
 * @param {string}   props.subjectKey Property the first column writes.
 * @param {boolean}  props.withEffect Whether to offer a transition.
 * @return {JSX.Element} Builder.
 */
export function RuleGroup( { title, group, onChange, subjects, operators, subjectKey, withEffect = false } ) {
	const enabled = !! group;
	const rules = group?.rules || [];

	const emit = ( changes ) => onChange( { action: 'show', logic: 'all', ...group, ...changes } );

	const setRule = ( index, changes ) =>
		emit( { rules: rules.map( ( r, i ) => ( i === index ? { ...r, ...changes } : r ) ) } );

	return (
		<div className={ enabled ? 'scfe-group' : 'scfe-group is-off' }>
			<CheckboxControl
				__nextHasNoMarginBottom
				label={ title }
				checked={ enabled }
				// Unticking discards the rules rather than hiding them, so what
				// the screen shows is always what will be saved.
				onChange={ ( on ) =>
					onChange( on ? { action: 'show', logic: 'all', rules: [ {} ] } : null )
				}
			/>

			{ enabled && (
				<div className="scfe-group__body">
					<div className="scfe-group__head">
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							value={ group.action || 'show' }
							options={ ACTIONS }
							onChange={ ( action ) => emit( { action } ) }
						/>
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							value={ group.logic || 'all' }
							options={ LOGIC }
							onChange={ ( logic ) => emit( { logic } ) }
						/>
						{ withEffect && (
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								value={ group.effect || 'fade' }
								options={ EFFECTS }
								onChange={ ( effect ) => emit( { effect } ) }
							/>
						) }
					</div>

					{ rules.map( ( rule, index ) => (
						// eslint-disable-next-line react/no-array-index-key
						<div key={ index }>
							{ /* The joining word, so a reader does not have to infer
							     it from the mode chosen further up. */ }
							{ index > 0 && (
								<div className="scfe-rule__join">
									{ 'any' === ( group.logic || 'all' )
										? __( 'or', 'checkout-lift-for-woocommerce' )
										: __( 'and', 'checkout-lift-for-woocommerce' ) }
								</div>
							) }
							<div className="scfe-rule">
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								value={ rule[ subjectKey ] || '' }
								options={ [ { value: '', label: '—' }, ...subjects ] }
								onChange={ ( value ) => setRule( index, { [ subjectKey ]: value } ) }
							/>
							<SelectControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								value={ rule.operator || 'is' }
								options={ operators }
								onChange={ ( operator ) => setRule( index, { operator } ) }
							/>
							<TextControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								value={ rule.value || '' }
								onChange={ ( value ) => setRule( index, { value } ) }
							/>
							<Button
								size="small"
								icon="no-alt"
								isDestructive
								label={ __( 'Remove rule', 'checkout-lift-for-woocommerce' ) }
								onClick={ () => emit( { rules: rules.filter( ( r, i ) => i !== index ) } ) }
							/>
							</div>
						</div>
					) ) }

					<Button
						size="small"
						variant="secondary"
						style={ { marginTop: 8 } }
						onClick={ () => emit( { rules: [ ...rules, {} ] } ) }
					>
						{ __( 'Add rule', 'checkout-lift-for-woocommerce' ) }
					</Button>
				</div>
			) }
		</div>
	);
}

/**
 * A group that simply selects methods.
 *
 * @param {Object}   props          Component props.
 * @param {string}   props.title    Heading.
 * @param {Object?}  props.group    Stored group.
 * @param {Function} props.onChange Called with the new group, or null.
 * @param {Array}    props.methods  Available methods.
 * @return {JSX.Element} Builder.
 */
export function MethodGroup( { title, group, onChange, methods } ) {
	const enabled = !! group;
	const selected = group?.methods || [];

	return (
		<div className={ enabled ? 'scfe-group' : 'scfe-group is-off' }>
			<CheckboxControl
				__nextHasNoMarginBottom
				label={ title }
				checked={ enabled }
				onChange={ ( on ) => onChange( on ? { action: 'show', methods: [] } : null ) }
			/>

			{ enabled && (
				<div className="scfe-group__body">
					<div className="scfe-group__head">
						<SelectControl
							__nextHasNoMarginBottom
							__next40pxDefaultSize
							value={ group.action || 'show' }
							options={ ACTIONS }
							onChange={ ( action ) => onChange( { ...group, action } ) }
						/>
					</div>

					{ 0 === methods.length && (
						<p className="scfe-panel__note">
							{ __( 'No methods are available on this store.', 'checkout-lift-for-woocommerce' ) }
						</p>
					) }

					<div className="scfe-choices">
					{ methods.map( ( method ) => (
						<CheckboxControl
							key={ method.value }
							__nextHasNoMarginBottom
							label={ method.label }
							checked={ selected.includes( method.value ) }
							onChange={ ( on ) =>
								onChange( {
									...group,
									methods: on
										? [ ...selected, method.value ]
										: selected.filter( ( m ) => m !== method.value ),
								} )
							}
						/>
					) ) }
					</div>
				</div>
			) }
		</div>
	);
}

/**
 * Every condition builder for one field.
 *
 * @param {Object}   props           Component props.
 * @param {Object}   props.value     Current condition groups.
 * @param {Function} props.onChange  Called with the new groups.
 * @param {Array}    props.fields    Fields a rule may reference.
 * @param {Array}    props.payment   Available payment methods.
 * @param {Array}    props.shipping  Available shipping methods.
 * @return {JSX.Element} Builders.
 */
export default function Conditions( { value, onChange, fields, payment, shipping } ) {
	const groups = value || {};
	const ProConditions = window.scfe_admin_pro?.Conditions;
	const set = ( key ) => ( group ) => {
		const next = { ...groups };

		if ( group ) {
			next[ key ] = group;
		} else {
			delete next[ key ];
		}

		onChange( next );
	};

	return (
		<div style={ { marginTop: 8 } }>
			<RuleGroup
				title={ __( 'Show or hide based on another field', 'checkout-lift-for-woocommerce' ) }
				group={ groups.field_dependencies }
				onChange={ set( 'field_dependencies' ) }
				subjects={ fields }
				operators={ annotateFieldOperators( FIELD_OPERATORS ) }
				subjectKey="field"
				withEffect
			/>

			<RuleGroup
				title={ __( 'Show or hide based on entered values', 'checkout-lift-for-woocommerce' ) }
				group={ groups.conditions }
				onChange={ set( 'conditions' ) }
				subjects={ fields }
				operators={ annotateFieldOperators( FIELD_OPERATORS ) }
				subjectKey="field"
			/>

			{ ProConditions && (
				<ProConditions groups={ groups } set={ set } payment={ payment } shipping={ shipping } />
			) }

			<ConditionScopeNote />
		</div>
	);
}
