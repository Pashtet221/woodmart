<?php
/**
 * Classic WooCommerce checkout presentation. No rates, gateways or totals are invented here.
 *
 * @package WoodmartChild
 */

defined( 'ABSPATH' ) || exit;

/** The child PHP checkout replaces only checkout layouts, never the site's header/footer. */
function mitkin_checkout_layout_id( $layout_id ) {
	return mitkin_is_classic_checkout() ? 0 : $layout_id;
}
add_filter( 'woodmart_layout_id', 'mitkin_checkout_layout_id', PHP_INT_MAX );

/** Resolve these child overrides after layout/plugin template filters. */
function mitkin_checkout_template( $template, $template_name ) {
	$templates = array( 'form-checkout.php', 'form-billing.php', 'form-shipping.php', 'review-order.php', 'payment.php', 'mitkin-place-order.php' );
	if ( mitkin_is_classic_checkout() && in_array( $template_name, array_map( function ( $name ) { return 'checkout/' . $name; }, $templates ), true ) ) {
		$child_template = get_stylesheet_directory() . '/woocommerce/' . $template_name;
		if ( is_readable( $child_template ) ) {
			return $child_template;
		}
	}
	return $template;
}
add_filter( 'woocommerce_locate_template', 'mitkin_checkout_template', PHP_INT_MAX, 2 );

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
add_filter( 'woodmart_replace_checkout_template_condition', 'mitkin_checkout_use_own_product_table', PHP_INT_MAX );

/** A Russia-only, compact address; native region/city/postcode IDs remain available to carriers. */
function mitkin_checkout_fields( $fields ) {
	if ( ! mitkin_is_classic_checkout() ) {
		return $fields;
	}
	foreach ( array( 'billing', 'shipping' ) as $group ) {
		unset( $fields[ $group ][ $group . '_company' ], $fields[ $group ][ $group . '_address_2' ] );
		$definitions = array(
			'country'   => array( 'default' => 'RU', 'class' => array( 'mitkin-country-field', 'address-field', 'update_totals_on_change' ) ),
			'state'     => array( 'label' => __( 'Область / край', 'woodmart-child' ), 'placeholder' => __( 'Например, Московская область', 'woodmart-child' ), 'priority' => 50, 'class' => array( 'form-row-first', 'address-field', 'update_totals_on_change' ) ),
			'city'      => array( 'label' => __( 'Населённый пункт', 'woodmart-child' ), 'placeholder' => __( 'Город или посёлок', 'woodmart-child' ), 'priority' => 60, 'class' => array( 'form-row-last', 'address-field', 'update_totals_on_change' ) ),
			'address_1' => array( 'label' => __( 'Адрес', 'woodmart-child' ), 'placeholder' => __( 'Улица, дом, квартира', 'woodmart-child' ), 'priority' => 70, 'class' => array( 'form-row-wide', 'address-field', 'mitkin-address-street' ) ),
			'postcode'  => array( 'label' => __( 'Индекс', 'woodmart-child' ), 'placeholder' => '101000', 'priority' => 80, 'class' => array( 'form-row-wide', 'address-field', 'update_totals_on_change', 'mitkin-address-postcode' ) ),
		);
		foreach ( $definitions as $suffix => $definition ) {
			$key = $group . '_' . $suffix;
			if ( isset( $fields[ $group ][ $key ] ) ) {
				// Keep plugin classes, validation, required flags and native field types.
				$classes = (array) ( $fields[ $group ][ $key ]['class'] ?? array() );
				$classes = array_diff( $classes, array( 'form-row-first', 'form-row-last', 'form-row-wide' ) );
				$fields[ $group ][ $key ] = array_merge( $fields[ $group ][ $key ], $definition );
				$fields[ $group ][ $key ]['class'] = array_values( array_unique( array_merge( $classes, $definition['class'] ) ) );
			}
		}
	}
	$placeholders = array( 'first_name' => __( 'Имя', 'woodmart-child' ), 'last_name' => __( 'Фамилия', 'woodmart-child' ), 'phone' => '+7 (___) ___-__-__', 'email' => 'name@example.ru' );
	foreach ( $placeholders as $suffix => $placeholder ) {
		if ( isset( $fields['billing'][ 'billing_' . $suffix ] ) ) {
			$fields['billing'][ 'billing_' . $suffix ]['placeholder'] = $placeholder;
		}
	}
	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'mitkin_checkout_fields', 1000 );

/** WooCommerce's country script also sorts fields; keep its RU layout in sync with PHP. */
function mitkin_checkout_country_locale( $locale ) {
	if ( mitkin_is_classic_checkout() ) {
		$compact = array(
			'state'     => array( 'label' => __( 'Область / край', 'woodmart-child' ), 'priority' => 50 ),
			'city'      => array( 'label' => __( 'Населённый пункт', 'woodmart-child' ), 'priority' => 60 ),
			'address_1' => array( 'label' => __( 'Адрес', 'woodmart-child' ), 'placeholder' => __( 'Улица, дом, квартира', 'woodmart-child' ), 'priority' => 70 ),
			'postcode'  => array( 'label' => __( 'Индекс', 'woodmart-child' ), 'priority' => 80 ),
		);
		foreach ( $compact as $key => $settings ) {
			$locale['RU'][ $key ] = array_merge( $locale['RU'][ $key ] ?? array(), $settings );
		}
	}
	return $locale;
}
add_filter( 'woocommerce_get_country_locale', 'mitkin_checkout_country_locale', 100 );

/** Preserve saved apartment information when presenting the previous two lines as one input. */
function mitkin_checkout_value( $value, $input ) {
	if ( ! mitkin_is_classic_checkout() ) {
		return $value;
	}
	if ( in_array( $input, array( 'billing_country', 'shipping_country' ), true ) ) {
		return 'RU';
	}
	if ( null === $value && in_array( $input, array( 'billing_address_1', 'shipping_address_1' ), true ) && ! isset( $_POST[ $input ] ) && WC()->customer ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- display only; WooCommerce validates submissions.
		$prefix = 0 === strpos( $input, 'billing_' ) ? 'billing' : 'shipping';
		$line_1 = WC()->customer->{ 'get_' . $prefix . '_address_1' }();
		$line_2 = WC()->customer->{ 'get_' . $prefix . '_address_2' }();
		if ( $line_2 ) {
			return $line_1 && false !== strpos( $line_1, $line_2 ) ? $line_1 : trim( $line_1 . ', ' . $line_2, ', ' );
		}
	}
	return $value;
}
add_filter( 'woocommerce_checkout_get_value', 'mitkin_checkout_value', 20, 2 );

/** The same country and address representation must also be used during server validation. */
function mitkin_checkout_posted_data( $data ) {
	if ( mitkin_is_classic_checkout() ) {
		$data['billing_country']    = 'RU';
		$data['shipping_country']   = 'RU';
		$data['billing_address_2']  = '';
		$data['shipping_address_2'] = '';
	}
	return $data;
}
add_filter( 'woocommerce_checkout_posted_data', 'mitkin_checkout_posted_data', 20 );

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
