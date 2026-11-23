<?php
/** MITKIN storefront: native mini-cart, one checkout route, and brand assets. */
defined( 'ABSPATH' ) || exit;

function mitkin_storefront_assets() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	$root = get_stylesheet_directory();
	wp_enqueue_style( 'mitkin-storefront', get_stylesheet_directory_uri() . '/assets/css/storefront.css', array( 'child-style' ), filemtime( $root . '/assets/css/storefront.css' ) );
	wp_enqueue_script( 'mitkin-storefront', get_stylesheet_directory_uri() . '/assets/js/storefront.js', array( 'jquery', 'wc-add-to-cart' ), filemtime( $root . '/assets/js/storefront.js' ), true );
}
add_action( 'wp_enqueue_scripts', 'mitkin_storefront_assets', 10030 );

/** Retain the cart/session and WooCommerce endpoints; skip only the separate cart screen. */
function mitkin_storefront_cart_url( $url ) {
	return function_exists( 'wc_get_checkout_url' ) ? wc_get_checkout_url() : $url;
}
add_filter( 'woocommerce_get_cart_url', 'mitkin_storefront_cart_url' );

function mitkin_storefront_redirect_cart() {
	if ( ! function_exists( 'WC' ) || ! WC()->cart || is_admin() || wp_doing_ajax() ) {
		return;
	}
	// Handle an empty checkout before WooCommerce redirects it back to the cart URL.
	if ( is_cart() || ( is_checkout() && ! is_wc_endpoint_url() && WC()->cart->is_empty() ) ) {
		$target = WC()->cart->is_empty() ? wc_get_page_permalink( 'shop' ) : wc_get_checkout_url();
		wp_safe_redirect( $target, 302 );
		exit;
	}
}
add_action( 'template_redirect', 'mitkin_storefront_redirect_cart', 1 );

function mitkin_storefront_cart_buttons() {
	remove_action( 'woocommerce_widget_shopping_cart_buttons', 'woocommerce_widget_shopping_cart_button_view_cart', 10 );
	remove_action( 'woocommerce_widget_shopping_cart_buttons', 'woodmart_mini_cart_view_cart_btn', 10 );
}
add_action( 'wp_loaded', 'mitkin_storefront_cart_buttons', 30 );
// Remove callbacks at render time too, including AJAX fragments and late theme setup.
add_action( 'woocommerce_widget_shopping_cart_before_buttons', 'mitkin_storefront_cart_buttons', PHP_INT_MAX );

/** Our idempotent opening replaces WoodMart's toggle after an asynchronous add. */
function mitkin_storefront_cart_behavior( $value, $option ) {
	if ( 'add_to_cart_action' === $option ) {
		return 'nothing';
	}
	return $value;
}
add_filter( 'woodmart_option', 'mitkin_storefront_cart_behavior', 20, 2 );

/** The native AJAX follow-up link now names its actual destination. */
function mitkin_storefront_script_data( $data, $handle ) {
	if ( 'wc-add-to-cart' === $handle && is_array( $data ) ) {
		$data['i18n_view_cart'] = __( 'Оформить заказ', 'woodmart-child' );
	}
	return $data;
}
add_filter( 'woocommerce_get_script_data', 'mitkin_storefront_script_data', 20, 2 );

/** Rename the label only while the mini-cart template is rendering. */
function mitkin_mini_cart_total_label( $translation, $text, $domain ) {
	return 'Subtotal' === $text ? 'Итого' : $translation;
}
function mitkin_mini_cart_start_labels() {
	add_filter( 'gettext_woocommerce', 'mitkin_mini_cart_total_label', 20, 3 );
}
function mitkin_mini_cart_end_labels() {
	remove_filter( 'gettext_woocommerce', 'mitkin_mini_cart_total_label', 20 );
}
add_action( 'woocommerce_before_mini_cart', 'mitkin_mini_cart_start_labels', 0 );
add_action( 'woocommerce_after_mini_cart', 'mitkin_mini_cart_end_labels', PHP_INT_MAX );
