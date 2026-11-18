/* Requires the guarded local fixture and seed.php on a disposable WordPress/WooCommerce. */
const assert = require('node:assert/strict');
const { chromium } = require('playwright');

const base = process.env.CHECKOUT_TEST_URL || 'http://127.0.0.1:8787';
assert(['127.0.0.1', 'localhost', '[::1]'].includes(new URL(base).hostname), 'Refusing to submit orders to a non-local site.');

(async () => {
    const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/usr/bin/chromium', args: ['--no-sandbox'] });
    const context = await browser.newContext({ viewport: { width: 1440, height: 1080 } });
    const page = await context.newPage();
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));

    async function update(action) {
        const response = page.waitForResponse(r => r.url().includes('wc-ajax=update_order_review') && r.request().method() === 'POST');
        await action();
        const json = await (await response).json();
        assert.equal(json.result, 'success');
        assert(json.fragments['.mitkin-checkout-shipping-methods']);
        assert(json.fragments['.mitkin-checkout-place-order']);
        await page.waitForFunction(() => !document.querySelector('.woocommerce-checkout .blockUI'));
        return json;
    }
    await page.goto(base + '/?mitkin_test_cart=physical', { waitUntil: 'load' });
    await page.locator('.mitkin-checkout-form').waitFor();
    await update(() => page.evaluate(() => jQuery(document.body).trigger('update_checkout')));
    assert.equal(await page.locator('.mitkin-checkout-form').count(), 1, 'WoodMart layout replaced or duplicated the child form.');
    assert.equal(await page.locator('form[name=checkout]').count(), 1, 'Nested or duplicated checkout form.');
    assert.equal(await page.locator('#mitkin-conflicting-layout').count(), 0);
    assert.equal(await page.locator('#billing_country').inputValue(), 'RU');
    assert.equal(await page.locator('#billing_country_field').isVisible(), false);
    assert.equal(await page.locator('#billing_company,#shipping_company,#billing_address_2,#shipping_address_2').count(), 0);
    assert.match(await page.locator('#billing_address_1').inputValue(), /квартира 5/, 'Saved second address line was lost.');
    assert.equal(await page.locator('#order_comments').count(), 1, 'Duplicated order details.');
    assert.equal(await page.locator('.mitkin-checkout-notes').count(), 1);
    assert.equal(await page.locator('.mitkin-checkout-notes').getAttribute('open'), null);
    assert.equal(await page.locator('#billing_first_name').evaluate(e => getComputedStyle(e).borderTopLeftRadius), '12px', 'Checkout stylesheet did not apply.');
    assert((await page.locator('.mitkin-review-image img').first().evaluate(e => e.getBoundingClientRect().width)) <= 80, 'Unbounded product image.');
    const positions = await page.locator('.mitkin-address-fields').first().evaluate(e => Object.fromEntries(['billing_state_field', 'billing_city_field', 'billing_address_1_field', 'billing_postcode_field'].map(id => [id, e.querySelector('#' + id).getBoundingClientRect().y])));
    assert(Math.abs(positions.billing_state_field - positions.billing_city_field) < 2, 'Region and city should share a row.');
    assert(Math.abs(positions.billing_address_1_field - positions.billing_postcode_field) < 2, 'Street and postcode should share a row.');
    assert(positions.billing_state_field < positions.billing_address_1_field, 'Country localization restored the old address order.');
    assert.equal(await page.locator('.mitkin-checkout-panel').count(), 3);
    assert.equal(await page.locator('.mitkin-receiver-fields #billing_vat_test').count(), 1, 'Plugin recipient field was lost.');
    assert.equal(await page.locator('.mitkin-address-fields #billing_country').count(), 1);
    assert.equal(await page.locator('.mitkin-review-table tbody .cart_item').count(), 3);
    assert.equal(await page.locator('.mitkin-review-table .cart-discount').count(), 1, 'Actual WooCommerce coupon is missing.');
    assert((await page.locator('.mitkin-review-table').innerText()).includes('54'), 'Filtered cart metadata is missing.');

    for (const [id, value] of Object.entries({ billing_first_name: 'Иван', billing_last_name: 'Тестовый', billing_phone: '+79990000000', billing_email: 'checkout@example.test', billing_city: 'Москва', billing_address_1: 'Тестовая улица, 1', billing_postcode: '101000', billing_vat_test: '1234567890' })) {
        await page.locator('#' + id).fill(value);
    }
    const state = page.locator('#billing_state');
    if (await state.count()) {
        const tag = await state.evaluate(e => e.tagName);
        if (tag === 'SELECT') {
            const option = await state.locator('option').evaluateAll(es => es.find(e => /Moscow|Москва/.test(e.textContent))?.value || es.find(e => e.value)?.value);
            await state.selectOption(option);
        } else {
            await state.fill('Москва');
        }
    }
    await update(() => page.evaluate(() => jQuery(document.body).trigger('update_checkout')));
    const pickup = page.locator('input.shipping_method[value^="local_pickup:"]');
    if (!(await pickup.isChecked())) await update(() => pickup.check());
    await page.locator('#mitkin_test_pickup').fill('PVZ-TEST-1');
    await update(() => page.evaluate(() => jQuery(document.body).trigger('update_checkout')));
    assert.equal(await page.locator('#mitkin_test_pickup').inputValue(), 'PVZ-TEST-1', 'Carrier field lost its session value after refresh.');
    assert.equal(await page.locator('#payment_method_cod').count(), 1);
    const pickupTotal = await page.locator('.mitkin-review-table .order-total td').innerText();

    await update(() => page.locator('input.shipping_method[value^="flat_rate:"]').check());
    assert.equal(await page.locator('#payment_method_cod').count(), 0, 'Unavailable COD remained after courier selection.');
    assert.notEqual(await page.locator('.mitkin-review-table .order-total td').innerText(), pickupTotal, 'Shipping selection did not update the total.');
    for (const selector of ['#payment', '#place_order', '#woocommerce-process-checkout-nonce', '.mitkin-checkout-shipping-methods']) {
        assert.equal(await page.locator(selector).count(), 1, 'Duplicate or missing fragment: ' + selector);
    }
    await page.locator('#terms').check();
    await update(() => page.evaluate(() => jQuery(document.body).trigger('update_checkout')));
    assert(await page.locator('#terms').isChecked(), 'Terms consent was lost during AJAX refresh.');

    // A forged, now-unavailable gateway must be rejected by the actual WooCommerce backend.
    const invalidData = await page.locator('form.checkout').evaluate(form => Object.fromEntries(new FormData(form)));
    invalidData.payment_method = 'cod';
    const invalid = await context.request.post(base + '/?wc-ajax=checkout', { form: invalidData });
    const invalidJson = await invalid.json();
    assert.equal(invalidJson.result, 'failure');
    assert.match(invalidJson.messages, /Invalid payment method|Недопустимый способ оплаты|Неверный способ оплаты/i);

    await page.screenshot({ path: '/tmp/mitkin-checkout-desktop.png', fullPage: true });
    for (const width of [390, 768]) {
        await page.setViewportSize({ width, height: 844 });
        const dimensions = await page.locator('.mitkin-checkout').evaluate(e => ({ width: e.clientWidth, scroll: e.scrollWidth }));
        assert(dimensions.scroll <= dimensions.width + 1, 'Checkout overflow at ' + width);
        if (width === 390) await page.screenshot({ path: '/tmp/mitkin-checkout-mobile.png', fullPage: true });
    }
    await page.setViewportSize({ width: 1440, height: 1080 });
    await page.locator('#payment_method_bacs').check();
    // Read the genuine server response before WooCommerce navigates to the thank-you page.
    let resolveOrder;
    const order = new Promise(resolve => { resolveOrder = resolve; });
    await page.route(/wc-ajax=checkout/, async route => {
        const response = await route.fetch();
        resolveOrder(await response.json());
        await route.fulfill({ response });
    });
    await page.locator('#place_order').click();
    const orderJson = await order;
    assert.equal(orderJson.result, 'success', JSON.stringify(orderJson));
    assert.match(orderJson.redirect, /order-received/);
    await page.waitForURL(/order-received/);
    console.log('PASS: physical cart, coupon, cart metadata, plugin fields, pickup refresh, courier totals, gateway availability, consent, validation and local order.');

    for (const mode of ['digital', 'free']) {
        await page.goto(base + '/?mitkin_test_cart=' + mode, { waitUntil: 'load' });
        await update(() => page.evaluate(() => jQuery(document.body).trigger('update_checkout')));
        assert.equal(await page.locator('input.shipping_method').count(), 0);
        assert.equal(await page.locator('#place_order').count(), 1);
        if (mode === 'free') assert.equal(await page.locator('input[name=payment_method]').count(), 0);
        console.log('PASS: ' + mode + ' cart renders without fabricated shipping/payment choices.');
    }
    assert.deepEqual(errors, [], 'Browser JavaScript errors.');
    await browser.close();
})().catch(error => { console.error(error); process.exit(1); });
