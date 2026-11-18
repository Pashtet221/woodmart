# Локальный integration smoke test

Тест выполняет настоящий checkout и создаёт заказ **только в одноразовом локальном WordPress**. Не копировать fixture в рабочий магазин. `seed.php` отказывается запускаться без WP-CLI и `WP_ENVIRONMENT_TYPE=local`, браузерный тест разрешает только localhost/127.0.0.1.

Нужны WordPress, WooCommerce 11.3, родительская тема WoodMart, эта дочерняя тема, WP-CLI, Node.js, Playwright и Chromium. В контейнере PHP выделить не менее 512 MB. Пользователь PHP должен иметь право чтения файлов тем.

На чистом локальном стенде:

```sh
wp config set WP_ENVIRONMENT_TYPE local
wp theme activate woodmart-child
cp wp-content/themes/woodmart-child/tests/checkout/local-fixture.php wp-content/mu-plugins/mitkin-local-fixture.php
wp eval-file wp-content/themes/woodmart-child/tests/checkout/seed.php
CHECKOUT_TEST_URL=http://127.0.0.1:8787 node wp-content/themes/woodmart-child/tests/checkout/smoke.cjs
```

`seed.php` создаёт тестовые товары, купон 7%, доставку 0/500 и два настоящих offline gateway WooCommerce. Он заменяет методы зоны 0, поэтому стенд должен быть одноразовым. Fixture подавляет почту и внешний HTTP, добавляет проверочное обязательное поле и виджет ПВЗ на стандартном хуке. Это не код для магазина и не подмена продакшен-интеграций СДЭК/ЮKassa.

Тест проверяет серверные AJAX-ответы, изменение суммы и доступности шлюзов, сохранение полей/consent, отказ в недоступной оплате, успешный локальный заказ, виртуальную/бесплатную корзину и отсутствие overflow. Скриншоты сохраняются в `/tmp/mitkin-checkout-desktop.png` и `/tmp/mitkin-checkout-mobile.png`. Суммы берутся из настоящего WooCommerce; статический HTML-макет не используется.
