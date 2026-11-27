/* WooCommerce owns submission, payment selection, shipping calculation and AJAX replacement. */
(function ($) {
    'use strict';

    function decorateMethods() {
        var $form = $('.mitkin-checkout-form');
        // Known gateway IDs only; WooCommerce still owns availability and submission.
        var labels = {cod: 'Оформить заказ', yookassa_epl: 'Перейти к оплате'};
        var method = $form.find('input[name="payment_method"]:checked').val();
        if (labels[method]) {
            $form.find('#place_order').text(labels[method]).val(labels[method]).attr('data-value', labels[method]);
        }

        $form.find('#shipping_method li').each(function () {
            var $row = $(this);
            var $input = $row.find('input.shipping_method').first();
            $row.toggleClass('mitkin-method-selected', $input.is(':checked') || $input.attr('type') === 'hidden');
        });
        $form.find('.wc_payment_methods > li').each(function () {
            $(this).toggleClass('mitkin-method-selected', $(this).find('input[name="payment_method"]').is(':checked'));
        });
    }

    $(function () {
        decorateMethods();
        $(document.body).on('updated_checkout payment_method_selected', decorateMethods);
        $(document).on('change', '.mitkin-checkout-form input.shipping_method, .mitkin-checkout-form input[name="payment_method"]', decorateMethods);
        $(document.body).on('checkout_error', function () {
            $('.mitkin-checkout .woocommerce-invalid').closest('details').prop('open', true);
            $('.woocommerce-error li[data-id]').each(function () {
                var key = $(this).attr('data-id');
                var field = document.getElementById(key) || document.getElementById(key + '_field');
                if (field) $(field).closest('details').prop('open', true);
            });
        });
    });
})(jQuery);
