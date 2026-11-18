<?php
/**
 * Native available gateways; the submit/terms block is rendered in the order summary.
 *
 * @package WooCommerce\Templates
 * @version 10.9.0
 */

defined( 'ABSPATH' ) || exit;
if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_before_payment' );
}
?>
<div id="payment" class="woocommerce-checkout-payment">
	<?php if ( WC()->cart && WC()->cart->needs_payment() ) : ?>
		<ul class="wc_payment_methods payment_methods methods" aria-label="<?php esc_attr_e( 'Payment methods', 'woocommerce' ); ?>" data-preselected="<?php echo esc_attr( ( $has_chosen_gateway ?? true ) ? 'yes' : 'no' ); ?>">
			<?php if ( ! empty( $available_gateways ) ) : ?>
				<?php foreach ( $available_gateways as $gateway ) : ?>
					<?php wc_get_template( 'checkout/payment-method.php', array( 'gateway' => $gateway ) ); ?>
				<?php endforeach; ?>
			<?php else : ?>
				<li><?php wc_print_notice( apply_filters( 'woocommerce_no_available_payment_methods_message', WC()->customer->get_billing_country() ? __( 'Sorry, it seems that there are no available payment methods. Please contact us if you require assistance or wish to make alternate arrangements.', 'woocommerce' ) : __( 'Please fill in your details above to see available payment methods.', 'woocommerce' ) ), 'notice' ); ?></li>
			<?php endif; ?>
		</ul>
	<?php else : ?>
		<p class="mitkin-checkout-hint"><?php esc_html_e( 'Для этого заказа оплата не требуется.', 'woodmart-child' ); ?></p>
	<?php endif; ?>
</div>
<?php
if ( ! wp_doing_ajax() ) {
	do_action( 'woocommerce_review_order_after_payment' );
}
