/* WooCommerce owns requests, quantities, validation and fragment replacement. */
(function ($) {
    'use strict';
    var pending = 0;

    function showStatus(message) {
        var $cart = $('.cart-widget-side');
        var $status = $cart.find('.mitkin-cart-status');
        if (!$status.length) {
            $status = $('<p class="mitkin-cart-status" role="status" aria-live="polite"></p>');
            $cart.find('.wd-heading').after($status);
        }
        $status.text(message);
        $cart.toggleClass('mitkin-cart-pending', pending > 0).attr('aria-busy', pending > 0 ? 'true' : 'false');
        $cart.find('.checkout').attr('aria-disabled', pending > 0 ? 'true' : 'false');
    }

    function openCart() {
        // The checkout already shows the live order summary.
        if (document.body.classList.contains('woocommerce-checkout')) return;
        var $cart = $('.cart-widget-side');
        if (!$cart.length) return;
        $cart.addClass('wd-opened');
        $('.wd-close-side').addClass('wd-close-side-opened');
    }

    $(function () {
        $(document.body).on('adding_to_cart.mitkin', function () {
            pending += 1;
            openCart();
            showStatus('Добавляем товар…');
        }).on('added_to_cart.mitkin', function () {
            pending = Math.max(0, pending - 1);
            showStatus(pending ? 'Добавляем товар…' : 'Товар добавлен');
        });
        $(document).on('click.mitkin', '.cart-widget-side .checkout', function (event) {
            if (pending) event.preventDefault();
        });
        $(document).ajaxComplete(function (event, xhr, settings) {
            if (!pending || !/wc-ajax=add_to_cart|action=woodmart_ajax_add_to_cart/.test(settings.url + '&' + settings.data)) return;
            if (xhr.status >= 400 || !xhr.responseJSON || xhr.responseJSON.error) {
                pending = Math.max(0, pending - 1);
                showStatus('Не удалось добавить товар. Попробуйте ещё раз.');
            }
        });
    });
})(jQuery);
