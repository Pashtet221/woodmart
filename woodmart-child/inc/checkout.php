<?php
/**
 * Classic WooCommerce checkout presentation. No rates, gateways or totals are invented here.
 *
 * @package WoodmartChild
 */

defined( 'ABSPATH' ) || exit;

/** Whether the current request renders the classic checkout or its AJAX fragments. */
function mitkin_is_classic_checkout() {
	if ( ! function_exists( 'WC' ) || ! function_exists( 'is_checkout' ) ) {
		return false;
	}

	$ajax_action = isset( $_GET['wc-ajax'] ) ? sanitize_key( wp_unslash( $_GET['wc-ajax'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- context only; WooCommerce verifies checkout requests.
	if ( in_array( $ajax_action, array( 'update_order_review', 'checkout' ), true ) ) {
		return true;
	}

	return is_checkout() && ! is_wc_endpoint_url() && ! has_block( 'woocommerce/checkout', get_post() );
}

/** Keep third-party review hooks, but render the standard payment function in its own panel. */
function mitkin_checkout_setup() {
	if ( mitkin_is_classic_checkout() ) {
		remove_action( 'woocommerce_checkout_order_review', 'woocommerce_checkout_payment', 20 );
	}
}
add_action( 'wp', 'mitkin_checkout_setup', 30 );

/** Checkout needs the full content column, without changing header/footer or other pages. */
function mitkin_checkout_layout_option( $value, $option ) {
	return 'main_layout' === $option && mitkin_is_classic_checkout() ? 'full-width' : $value;
}
add_filter( 'woodmart_option', 'mitkin_checkout_layout_option', 10, 2 );

/** Do not let WoodMart render a second product table or hide the native cart loop. */
function mitkin_checkout_use_own_product_table( $replace ) {
	return mitkin_is_classic_checkout() ? false : $replace;
}
add_filter( 'woodmart_replace_checkout_template_condition', 'mitkin_checkout_use_own_product_table' );

/** Assets are limited to the classic checkout; header, footer and order-pay stay untouched. */
function mitkin_checkout_assets() {
	if ( ! mitkin_is_classic_checkout() ) {
		return;
	}

	$directory = get_stylesheet_directory();
	$uri       = get_stylesheet_directory_uri();
	wp_enqueue_style( 'mitkin-checkout', $uri . '/assets/css/checkout.css', array( 'child-style' ), filemtime( $directory . '/assets/css/checkout.css' ) );
	wp_enqueue_script( 'mitkin-checkout', $uri . '/assets/js/checkout.js', array( 'jquery', 'wc-checkout' ), filemtime( $directory . '/assets/js/checkout.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'mitkin_checkout_assets', 10020 );

/** Split only address fields; plugin-provided recipient fields retain their native definition. */
function mitkin_checkout_is_address_field( $key, $field ) {
	$address_keys = array( 'billing_company', 'billing_country', 'billing_state', 'billing_city', 'billing_postcode', 'billing_address_1', 'billing_address_2' );
	$is_address  = in_array( $key, $address_keys, true ) || in_array( 'address-field', (array) ( $field['class'] ?? array() ), true );
	return (bool) apply_filters( 'mitkin_checkout_is_address_field', $is_address, $key, $field );
}

/** Native package/rate markup includes shipping_method[], rate hooks and carrier pickup widgets. */
function mitkin_checkout_shipping_methods() {
	?>
	<div class="mitkin-checkout-shipping-methods">
		<?php if ( WC()->cart && WC()->cart->needs_shipping() ) : ?>
			<table class="mitkin-shipping-table"><tbody>
				<?php do_action( 'woocommerce_review_order_before_shipping' ); ?>
				<?php wc_cart_totals_shipping_html(); ?>
				<?php do_action( 'woocommerce_review_order_after_shipping' ); ?>
			</tbody></table>
		<?php else : ?>
			<p class="mitkin-checkout-hint"><?php esc_html_e( 'Для этого заказа доставка не требуется.', 'woodmart-child' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/** The submit block remains in the order summary and is refreshed with the payment fragment. */
function mitkin_checkout_place_order() {
	wc_get_template( 'checkout/mitkin-place-order.php', array( 'order_button_text' => apply_filters( 'woocommerce_order_button_text', __( 'Place order', 'woocommerce' ) ) ) );
}

/** WooCommerce already refreshes the native review table and #payment; add the other two regions. */
function mitkin_checkout_fragments( $fragments ) {
	if ( ! mitkin_is_classic_checkout() ) {
		return $fragments;
	}

	ob_start();
	mitkin_checkout_shipping_methods();
	$fragments['.mitkin-checkout-shipping-methods'] = ob_get_clean();
	ob_start();
	mitkin_checkout_place_order();
	$fragments['.mitkin-checkout-place-order'] = ob_get_clean();
	return $fragments;
}
add_filter( 'woocommerce_update_order_review_fragments', 'mitkin_checkout_fragments', 20 );
