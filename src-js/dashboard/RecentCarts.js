/**
 * Recent checkout activity.
 *
 * @package SimpleCheckoutFieldsEditor
 */

import { __ } from '@wordpress/i18n';

import { formatMoney, formatDate } from './format';

/**
 * The human label for a stored status.
 *
 * The colour is a class rather than a hex value, so the palette lives in one
 * stylesheet instead of being spelled out beside every status.
 *
 * @param {string} status Stored status.
 * @return {string} Label.
 */
function describe( status ) {
	const map = {
		open: __( 'In progress', 'checkout-lift-for-woocommerce' ),
		abandoned: __( 'Abandoned', 'checkout-lift-for-woocommerce' ),
		recovered: __( 'Recovered', 'checkout-lift-for-woocommerce' ),
		completed: __( 'Completed', 'checkout-lift-for-woocommerce' ),
	};

	return map[ status ] || status;
}

/**
 * Render the recent activity table.
 *
 * @param {Object} props       Component props.
 * @param {Array}  props.carts Recent carts.
 * @return {JSX.Element} Table.
 */
export default function RecentCarts( { carts } ) {
	return (
		<div className="scfe-card">
			<div className="scfe-card__head">
				<span className="scfe-card__title">
					{ __( 'Recent checkout activity', 'checkout-lift-for-woocommerce' ) }
				</span>
				<span className="scfe-card__count">{ carts.length }</span>
			</div>

			{ 0 === carts.length ? (
				<div className="scfe-empty">
					{ __(
						'Nothing recorded yet. Activity appears here once customers start reaching the checkout.',
						'checkout-lift-for-woocommerce'
					) }
				</div>
			) : (
				<div className="scfe-scroll">
					<table className="scfe-table">
						<thead>
							<tr>
								<th>{ __( 'Status', 'checkout-lift-for-woocommerce' ) }</th>
								<th>{ __( 'Customer', 'checkout-lift-for-woocommerce' ) }</th>
								<th>{ __( 'Value', 'checkout-lift-for-woocommerce' ) }</th>
								<th>{ __( 'Items', 'checkout-lift-for-woocommerce' ) }</th>
								<th>{ __( 'Country', 'checkout-lift-for-woocommerce' ) }</th>
								<th>{ __( 'Reached', 'checkout-lift-for-woocommerce' ) }</th>
								<th>{ __( 'Last activity', 'checkout-lift-for-woocommerce' ) }</th>
								<th>{ __( 'Order', 'checkout-lift-for-woocommerce' ) }</th>
							</tr>
						</thead>
						<tbody>
							{ carts.map( ( cart ) => (
								<tr key={ cart.id }>
									<td>
										<span className={ `scfe-status is-${ cart.status }` }>
											{ describe( cart.status ) }
										</span>
										{ ! cart.consent && (
											<div className="scfe-warn">
												{ __( 'no permission', 'checkout-lift-for-woocommerce' ) }
											</div>
										) }
									</td>
									<td>
										{ cart.name && <div>{ cart.name }</div> }
										{ cart.email && (
											<div>
												<a href={ `mailto:${ cart.email }` }>{ cart.email }</a>
											</div>
										) }
										{ cart.phone && (
											<div>
												<a href={ `tel:${ cart.phone }` }>{ cart.phone }</a>
											</div>
										) }
										{ ! cart.name && ! cart.email && ! cart.phone && '—' }
									</td>
									<td>{ formatMoney( cart.total, cart.currency ) }</td>
									<td>{ cart.items }</td>
									<td>{ cart.country || '—' }</td>
									<td>{ cart.step || '—' }</td>
									<td>{ formatDate( cart.updated_at ) }</td>
									<td>
										{ cart.order_id > 0 && cart.order_url ? (
											<a href={ cart.order_url }>
												#{ cart.order_id }
											</a>
										) : (
											'—'
										) }
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				</div>
			) }
		</div>
	);
}
