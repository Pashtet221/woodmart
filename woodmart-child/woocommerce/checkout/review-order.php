<?php
/**
 * Native cart contents and totals, with shipping selectors rendered in the delivery panel.
 *
 * @package WooCommerce\Templates
 * @version 11.2.2
 */

defined( 'ABSPATH' ) || exit;
?>
<table class="shop_table woocommerce-checkout-review-order-table mitkin-review-table">
	<thead><tr><th class="product-name"><?php esc_html_e( 'Product', 'woocommerce' ); ?></th><th class="product-total"><?php esc_html_e( 'Subtotal', 'woocommerce' ); ?></th></tr></thead>
	<tbody>
		<?php do_action( 'woocommerce_review_order_before_cart_contents' ); ?>
		<?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) : ?>
			<?php
			$product = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
			if ( ! $product || ! $product->exists() || $cart_item['quantity'] <= 0 || ! apply_filters( 'woocommerce_checkout_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
				continue;
			}
			?>
			<tr class="<?php echo esc_attr( apply_filters( 'woocommerce_cart_item_class', 'cart_item', $cart_item, $cart_item_key ) ); ?>">
				<td class="product-name">
					<div class="mitkin-review-product">
						<div class="mitkin-review-image"><?php echo apply_filters( 'woocommerce_cart_item_thumbnail', $product->get_image( 'woocommerce_thumbnail' ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce image HTML/filter. ?></div>
						<div class="mitkin-review-description">
							<span class="mitkin-review-name"><?php echo wp_kses_post( apply_filters( 'woocommerce_cart_item_name', $product->get_name(), $cart_item, $cart_item_key ) ); ?></span>
							<?php echo wc_get_formatted_cart_item_data( $cart_item ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce escaped cart metadata. ?>
							<?php do_action( 'woocommerce_checkout_after_cart_item_meta', $cart_item, $cart_item_key ); ?>
						</div>
					</div>
				</td>
				<td class="product-total">
					<?php echo apply_filters( 'woocommerce_checkout_cart_item_quantity', '<span class="product-quantity">' . sprintf( esc_html__( '%s шт.', 'woodmart-child' ), esc_html( $cart_item['quantity'] ) ) . '</span>', $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- standard WooCommerce filter. ?>
					<span class="mitkin-review-line-total"><?php echo apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] ), $cart_item, $cart_item_key ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WooCommerce formatted price/filter. ?></span>
					<a class="mitkin-review-remove" href="<?php echo esc_url( add_query_arg( '_wp_http_referer', wc_get_checkout_url(), wc_get_cart_remove_url( $cart_item_key ) ) ); ?>" aria-label="<?php echo esc_attr( sprintf( __( 'Удалить товар «%s» из заказа', 'woodmart-child' ), $product->get_name() ) ); ?>" title="<?php esc_attr_e( 'Удалить товар', 'woodmart-child' ); ?>"><span aria-hidden="true">&times;</span></a>
				</td>
			</tr>
		<?php endforeach; ?>
		<?php do_action( 'woocommerce_review_order_after_cart_contents' ); ?>
	</tbody>
	<tfoot>
		<tr class="cart-subtotal"><th><?php esc_html_e( 'Стоимость', 'woodmart-child' ); ?></th><td><?php wc_cart_totals_subtotal_html(); ?></td></tr>
		<?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
			<tr class="cart-discount coupon-<?php echo esc_attr( sanitize_title( $code ) ); ?>"><th><?php wc_cart_totals_coupon_label( $coupon ); ?></th><td><?php wc_cart_totals_coupon_html( $coupon ); ?></td></tr>
		<?php endforeach; ?>
		<?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
			<tr class="mitkin-shipping-total"><th><?php esc_html_e( 'Доставка', 'woodmart-child' ); ?></th><td><?php echo wp_kses_post( WC()->cart->get_cart_shipping_total() ); ?></td></tr>
		<?php endif; ?>
		<?php foreach ( WC()->cart->get_fees() as $fee ) : ?>
			<tr class="fee"><th><?php echo esc_html( $fee->name ); ?></th><td><?php wc_cart_totals_fee_html( $fee ); ?></td></tr>
		<?php endforeach; ?>
		<?php if ( wc_tax_enabled() && ! WC()->cart->display_prices_including_tax() ) : ?>
			<?php if ( 'itemized' === get_option( 'woocommerce_tax_total_display' ) ) : ?>
				<?php foreach ( WC()->cart->get_tax_totals() as $code => $tax ) : ?>
					<tr class="tax-rate tax-rate-<?php echo esc_attr( sanitize_title( $code ) ); ?>"><th><?php echo esc_html( $tax->label ); ?></th><td><?php echo wp_kses_post( $tax->formatted_amount ); ?></td></tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr class="tax-total"><th><?php echo esc_html( WC()->countries->tax_or_vat() ); ?></th><td><?php wc_cart_totals_taxes_total_html(); ?></td></tr>
			<?php endif; ?>
		<?php endif; ?>
		<?php do_action( 'woocommerce_review_order_before_order_total' ); ?>
		<tr class="order-total"><th><?php esc_html_e( 'Итого', 'woodmart-child' ); ?></th><td><?php wc_cart_totals_order_total_html(); ?></td></tr>
		<?php do_action( 'woocommerce_review_order_after_order_total' ); ?>
	</tfoot>
</table>
