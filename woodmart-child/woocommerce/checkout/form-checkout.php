<?php
/**
 * MITKIN classic checkout. Header/footer are provided by the active WoodMart layout.
 *
 * @package WooCommerce\Templates
 * @version 9.4.0
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="mitkin-checkout">
<?php
// Coupon/login forms are siblings of form.checkout, inside the same width-constrained container.
do_action( 'woocommerce_before_checkout_form', $checkout );

if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
	echo esc_html( apply_filters( 'woocommerce_checkout_must_be_logged_in_message', __( 'You must be logged in to checkout.', 'woocommerce' ) ) );
	echo '</div>';
	return;
}
?>
	<div class="mitkin-checkout-heading">
		<h1><?php esc_html_e( 'Оформление заказа', 'woodmart-child' ); ?></h1>
		<p class="mitkin-checkout-secure"><svg viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="4" y="10" width="16" height="12" rx="2"/><path d="M8 10V6a4 4 0 0 1 8 0v4m-4 5v3"/></svg> <?php esc_html_e( 'Безопасное оформление', 'woodmart-child' ); ?></p>
	</div>
	<form name="checkout" method="post" class="checkout woocommerce-checkout mitkin-checkout-form" action="<?php echo esc_url( apply_filters( 'woocommerce_get_checkout_url', wc_get_checkout_url() ) ); ?>" enctype="multipart/form-data" aria-label="<?php esc_attr_e( 'Checkout', 'woocommerce' ); ?>">
		<div class="mitkin-checkout-layout">
			<div class="mitkin-checkout-main">
				<?php if ( $checkout->get_checkout_fields() ) : ?>
					<?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>
					<div class="customer-details" id="customer_details">
						<?php // The child billing template owns both panels and invokes the shipping action exactly once. ?>
						<?php do_action( 'woocommerce_checkout_billing' ); ?>
					</div>
					<?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>
				<?php endif; ?>
				<section class="mitkin-checkout-panel mitkin-checkout-payment-panel" aria-labelledby="mitkin-payment-heading">
					<h2 id="mitkin-payment-heading"><?php esc_html_e( '3. Оплата', 'woodmart-child' ); ?></h2>
					<?php woocommerce_checkout_payment(); ?>
				</section>
			</div>
			<aside class="checkout-order-review mitkin-checkout-summary" aria-labelledby="order_review_heading">
				<?php do_action( 'woocommerce_checkout_before_order_review_heading' ); ?>
				<h2 id="order_review_heading"><?php esc_html_e( 'Ваш заказ', 'woodmart-child' ); ?></h2>
				<?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
				<div id="order_review" class="woocommerce-checkout-review-order">
					<?php do_action( 'woocommerce_checkout_order_review' ); ?>
					<?php mitkin_checkout_place_order(); ?>
				</div>
				<?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
			</aside>
		</div>
	</form>
<?php do_action( 'woocommerce_after_checkout_form', $checkout ); ?>
</div>
