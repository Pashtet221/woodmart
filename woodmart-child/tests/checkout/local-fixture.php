<?php
/** Copy into wp-content/mu-plugins ONLY in a disposable local integration test. */
if ( ! defined( 'ABSPATH' ) || 'local' !== wp_get_environment_type() ) {
	return;
}

// This local integration test never sends customer email or calls a real payment API.
add_filter( 'pre_wp_mail', '__return_true' );
add_filter( 'pre_http_request', function () { return new WP_Error( 'local_test', 'External HTTP disabled in checkout fixture.' ); } );
add_filter( 'woocommerce_checkout_fields', function ( $fields ) {
	$fields['billing']['billing_vat_test'] = array( 'type' => 'text', 'label' => 'ИНН (тест плагина)', 'required' => true, 'class' => array( 'form-row-wide' ), 'priority' => 120 );
	return $fields;
} );

add_action( 'wp_loaded', function () {
	if ( ! isset( $_GET['mitkin_test_cart'] ) || ! function_exists( 'WC' ) ) {
		return;
	}
	wc_load_cart();
	WC()->cart->empty_cart();
	$ids = get_option( 'mitkin_test_products' );
	$mode = sanitize_key( $_GET['mitkin_test_cart'] );
	foreach ( 'digital' === $mode ? array( $ids[3] ) : ( 'free' === $mode ? array( $ids[4] ) : array_slice( $ids, 0, 3 ) ) as $id ) {
		WC()->cart->add_to_cart( $id );
	}
	if ( 'physical' === $mode ) {
		WC()->cart->apply_coupon( 'mitkin7' );
	}
	WC()->cart->calculate_totals();
	wp_safe_redirect( wc_get_checkout_url() );
	exit;
}, 30 );

// A stand-in for a carrier's pickup widget, registered on the same native hook CDEK uses.
add_action( 'woocommerce_after_shipping_rate', function ( $method ) {
	if ( 'local_pickup' === $method->method_id ) {
		woocommerce_form_field( 'mitkin_test_pickup', array( 'type' => 'text', 'label' => 'Код пункта выдачи (тест)', 'required' => true ), WC()->session->get( 'mitkin_test_pickup', '' ) );
	}
} );
add_action( 'woocommerce_checkout_update_order_review', function ( $data ) {
	parse_str( $data, $fields );
	WC()->session->set( 'mitkin_test_pickup', sanitize_text_field( $fields['mitkin_test_pickup'] ?? '' ) );
} );
add_action( 'woocommerce_checkout_process', function () {
	$chosen = WC()->session->get( 'chosen_shipping_methods', array() );
	if ( isset( $chosen[0] ) && 0 === strpos( $chosen[0], 'local_pickup:' ) && empty( $_POST['mitkin_test_pickup'] ) ) {
		wc_add_notice( 'Выберите пункт выдачи (тест).', 'error' );
	}
} );
add_filter( 'woocommerce_get_item_data', function ( $data, $item ) {
	$data[] = array( 'key' => 'Размер (тест)', 'value' => '54' );
	return $data;
}, 10, 2 );
