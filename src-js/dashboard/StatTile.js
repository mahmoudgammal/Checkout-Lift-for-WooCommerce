/**
 * A single headline figure.
 *
 * @package SimpleCheckoutFieldsEditor
 */

/**
 * Render one statistic.
 *
 * @param {Object}  props          Component props.
 * @param {string}  props.label    What the figure counts.
 * @param {*}       props.value    The figure itself.
 * @param {string}  props.help     Supporting detail beneath it.
 * @param {boolean} props.emphasis Whether this is the headline figure.
 * @return {JSX.Element} Tile.
 */
export default function StatTile( { label, value, help, emphasis = false } ) {
	return (
		<div className={ emphasis ? 'scfe-stat is-lead' : 'scfe-stat' }>
			<div className="scfe-stat__label">{ label }</div>
			<div className="scfe-stat__value">{ value }</div>
			{ help && <div className="scfe-stat__help">{ help }</div> }
		</div>
	);
}
