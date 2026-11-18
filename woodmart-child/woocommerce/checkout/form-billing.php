<?php
/**
 * Native billing fields grouped as recipient and delivery, retaining all field definitions.
 *
 * @package WooCommerce\Templates
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;
$fields = $checkout->get_checkout_fields( 'billing' );
?>
<div class="woocommerce-billing-fields">
	<?php do_action( 'woocommerce_before_checkout_billing_form', $checkout ); ?>
	<section class="mitkin-checkout-panel" aria-labelledby="mitkin-recipient-heading">
		<h2 id="mitkin-recipient-heading"><?php esc_html_e( '1. Получатель', 'woodmart-child' ); ?></h2>
		<div class="woocommerce-billing-fields__field-wrapper mitkin-receiver-fields">
			<?php foreach ( $fields as $key => $field ) : ?>
				<?php if ( ! mitkin_checkout_is_address_field( $key, $field ) ) : ?>
					<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
	</section>
	<section class="mitkin-checkout-panel mitkin-checkout-delivery-panel" aria-labelledby="mitkin-delivery-heading">
		<h2 id="mitkin-delivery-heading"><?php echo esc_html( WC()->cart->needs_shipping() ? __( '2. Доставка', 'woodmart-child' ) : __( '2. Платёжный адрес', 'woodmart-child' ) ); ?></h2>
		<?php mitkin_checkout_shipping_methods(); ?>
		<div class="woocommerce-billing-fields__field-wrapper mitkin-address-fields">
			<?php foreach ( $fields as $key => $field ) : ?>
				<?php if ( mitkin_checkout_is_address_field( $key, $field ) ) : ?>
					<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>
		<?php do_action( 'woocommerce_after_checkout_billing_form', $checkout ); ?>
		<?php if ( ! is_user_logged_in() && $checkout->is_registration_enabled() ) : ?>
			<div class="woocommerce-account-fields">
				<?php if ( ! $checkout->is_registration_required() ) : ?>
					<p class="form-row form-row-wide create-account"><label class="woocommerce-form__label woocommerce-form__label-for-checkbox checkbox"><input class="woocommerce-form__input woocommerce-form__input-checkbox input-checkbox" id="createaccount" <?php checked( true === $checkout->get_value( 'createaccount' ) || true === apply_filters( 'woocommerce_create_account_default_checked', false ), true ); ?> type="checkbox" name="createaccount" value="1"> <span><?php esc_html_e( 'Create an account?', 'woocommerce' ); ?></span></label></p>
				<?php endif; ?>
				<?php do_action( 'woocommerce_before_checkout_registration_form', $checkout ); ?>
				<?php if ( $checkout->get_checkout_fields( 'account' ) ) : ?>
					<div class="create-account">
						<?php foreach ( $checkout->get_checkout_fields( 'account' ) as $key => $field ) : ?>
							<?php woocommerce_form_field( $key, $field, $checkout->get_value( $key ) ); ?>
						<?php endforeach; ?>
						<div class="clear"></div>
					</div>
				<?php endif; ?>
				<?php do_action( 'woocommerce_after_checkout_registration_form', $checkout ); ?>
			</div>
		<?php endif; ?>
		<?php do_action( 'woocommerce_checkout_shipping' ); ?>
	</section>
</div>
