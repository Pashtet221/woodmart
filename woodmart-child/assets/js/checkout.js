/* WooCommerce owns submission, payment selection, shipping calculation and AJAX replacement. */
(function ($) {
    'use strict';

    function decorateMethods() {
        var $form = $('.mitkin-checkout-form');
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
    });
})(jQuery);
