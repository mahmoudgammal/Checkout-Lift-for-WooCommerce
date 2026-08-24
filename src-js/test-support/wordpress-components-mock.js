/**
 * Lightweight stand-ins for @wordpress/components.
 *
 * The real package pulls a deep chain of TypeScript and ESM dependencies that
 * Jest will not parse without transforming most of node_modules, which costs
 * more than it returns here. These stubs keep the component tests focused on
 * what this plugin actually owns: fetching, rendering its own data, and
 * handling failure.
 *
 * The trade-off is explicit: these tests cannot catch a wrong prop passed to a
 * real WordPress component. Those surface in the browser, which is where the
 * screens are also checked.
 *
 * @package SimpleCheckoutFieldsEditor
 */

// React directly rather than through @wordpress/element: the test renderer
// uses react-dom, and a second React instance makes every hook call invalid.
const { createElement, useState } = require( 'react' );

const passthrough = ( tag ) => ( { children, ...props } ) =>
	createElement( tag, { 'data-testid': props[ 'data-testid' ] }, children );

const Card = passthrough( 'div' );
const CardBody = passthrough( 'div' );
const CardHeader = passthrough( 'div' );
const Flex = passthrough( 'div' );
const FlexItem = passthrough( 'div' );
const Modal = ( { title, children } ) => createElement( 'div', null, title, children );
const Notice = ( { children } ) => createElement( 'div', null, children );
const Spinner = () => createElement( 'div', null, 'Loading' );

const Button = ( { children, onClick, disabled, label } ) =>
	createElement( 'button', { onClick, disabled, 'aria-label': label }, children );

const CheckboxControl = ( { label, checked, onChange } ) =>
	createElement(
		'label',
		null,
		createElement( 'input', {
			type: 'checkbox',
			checked: !! checked,
			onChange: ( e ) => onChange && onChange( e.target.checked ),
		} ),
		label
	);

const TextControl = ( { label, value, onChange, type } ) =>
	createElement(
		'label',
		null,
		label,
		createElement( 'input', {
			type: type || 'text',
			value: value ?? '',
			onChange: ( e ) => onChange && onChange( e.target.value ),
		} )
	);

const TextareaControl = ( { label, value, onChange } ) =>
	createElement(
		'label',
		null,
		label,
		createElement( 'textarea', {
			value: value ?? '',
			onChange: ( e ) => onChange && onChange( e.target.value ),
		} )
	);

const SelectControl = ( { label, value, options = [], onChange } ) =>
	createElement(
		'label',
		null,
		label,
		createElement(
			'select',
			{ value: value ?? '', onChange: ( e ) => onChange && onChange( e.target.value ) },
			options.map( ( o ) => createElement( 'option', { key: o.value, value: o.value }, o.label ) )
		)
	);

/**
 * Renders every tab title and the active panel, which is what the tests read.
 *
 * @param {Object}   props        Component props.
 * @param {Array}    props.tabs   Tab definitions.
 * @param {Function} props.children Panel renderer.
 * @return {Object} Element.
 */
const TabPanel = ( { tabs = [], children } ) => {
	const [ active, setActive ] = useState( tabs[ 0 ]?.name );

	return createElement(
		'div',
		null,
		tabs.map( ( tab ) =>
			createElement(
				'button',
				{ key: tab.name, role: 'tab', onClick: () => setActive( tab.name ) },
				tab.title
			)
		),
		children( tabs.find( ( t ) => t.name === active ) || tabs[ 0 ] )
	);
};

module.exports = {
	Button,
	Card,
	CardBody,
	CardHeader,
	CheckboxControl,
	Flex,
	FlexItem,
	Modal,
	Notice,
	SelectControl,
	Spinner,
	TabPanel,
	TextControl,
	TextareaControl,
};
