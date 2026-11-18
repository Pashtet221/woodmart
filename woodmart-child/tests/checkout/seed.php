<?php
/** Run with wp eval-file on a disposable local WordPress; never on a real store. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI || 'local' !== wp_get_environment_type() ) {
	throw new RuntimeException( 'Checkout fixtures require WP-CLI and WP_ENVIRONMENT_TYPE=local.' );
}

WC_Install::create_pages();
foreach ( array( 'cart' => '[woocommerce_cart]', 'checkout' => '[woocommerce_checkout]' ) as $page => $content ) {
	wp_update_post( array( 'ID' => wc_get_page_id( $page ), 'post_content' => $content ) );
}
update_option( 'woocommerce_currency', 'RUB' );
update_option( 'woocommerce_default_country', 'RU' );
update_option( 'woocommerce_default_customer_address', 'base' );
update_option( 'woocommerce_enable_guest_checkout', 'yes' );
update_option( 'woocommerce_enable_coupons', 'yes' );
update_option( 'woocommerce_calc_taxes', 'no' );
update_option( 'woocommerce_ship_to_destination', 'billing' );
$terms = wp_insert_post( array( 'post_type' => 'page', 'post_status' => 'publish', 'post_title' => 'Условия локального теста', 'post_content' => 'Только тестовый стенд. Реальные заказы не создаются.' ) );
update_option( 'woocommerce_terms_page_id', $terms );
// Reproduce a store with a published WoodMart Checkout Builder layout already assigned.
$layout = wp_insert_post( array( 'post_type' => 'woodmart_layout', 'post_status' => 'publish', 'post_title' => 'Conflicting checkout layout (local test)', 'post_content' => '<div id="mitkin-conflicting-layout">Old checkout builder layout</div>' ) );
$conditions = get_option( 'wd_layouts_conditions', array() );
$conditions['checkout_form'] = array( $layout => array( array( 'condition_type' => 'checkout_form', 'condition_comparison' => 'include' ) ) );
update_option( 'wd_layouts_conditions', $conditions );
update_option( 'woocommerce_cod_settings', array( 'enabled' => 'yes', 'title' => 'При получении', 'enable_for_methods' => array( 'local_pickup' ) ) );
update_option( 'woocommerce_bacs_settings', array( 'enabled' => 'yes', 'title' => 'Банковский перевод', 'instructions' => 'Локальный тест: деньги не переводятся.' ) );

$zone = new WC_Shipping_Zone( 0 );
foreach ( $zone->get_shipping_methods() as $method ) {
	$zone->delete_shipping_method( $method->instance_id );
}
$pickup = $zone->add_shipping_method( 'local_pickup' );
$courier = $zone->add_shipping_method( 'flat_rate' );
update_option( 'woocommerce_local_pickup_' . $pickup . '_settings', array( 'title' => 'Пункт выдачи', 'cost' => '0', 'tax_status' => 'none' ) );
update_option( 'woocommerce_flat_rate_' . $courier . '_settings', array( 'title' => 'Курьер', 'cost' => '500', 'tax_status' => 'none' ) );
WC_Cache_Helper::get_transient_version( 'shipping', true );

$products = array();
foreach ( array( array( 'Куртка Harrington', '12990' ), array( 'Рубашка Oxford', '7990' ), array( 'Чиносы Classic', '7990' ), array( 'Цифровой товар', '99' ), array( 'Бесплатный цифровой товар', '0' ) ) as $index => $data ) {
	$product = new WC_Product_Simple();
	$product->set_name( $data[0] );
	$product->set_status( 'publish' );
	$product->set_regular_price( $data[1] );
	$product->set_virtual( $index >= 3 );
	$product->save();
	$products[] = $product->get_id();
}
update_option( 'mitkin_test_products', $products );

$coupon = new WC_Coupon( 'mitkin7' );
$coupon->set_code( 'mitkin7' );
$coupon->set_discount_type( 'percent' );
$coupon->set_amount( 7 );
$coupon->save();

WP_CLI::line( wp_json_encode( array( 'products' => $products, 'pickup' => 'local_pickup:' . $pickup, 'courier' => 'flat_rate:' . $courier, 'checkout' => wc_get_checkout_url() ) ) );
