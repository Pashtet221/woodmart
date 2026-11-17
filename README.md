# Codex для WoodMart

В репозитории лежат клиентские команды из WPDevStudio и дополнительный плагин для Elementor. Коммерческую тему WoodMart и WoodMart Core устанавливают в WordPress отдельно.

## Подключение к тестовому сайту

1. Установи WoodMart, WoodMart Core и Elementor. Импортируй демо WoodMart в версии для Elementor.
2. Для обычных команд установи существующий **Codex WordPress Bridge 0.8.x**. Для Elementor скопируй папку `plugins/codex-elementor-bridge` в `wp-content/plugins` и активируй **Codex Bridge for Elementor**.
3. Создай пароль приложения WordPress. Для чтения Elementor нужны права редактирования документа, для записи также нужны права администратора сайта.
4. Задай `WORDPRESS_URL`, `WORDPRESS_USERNAME` и `WORDPRESS_APP_PASSWORD` в окружении Codex либо в локальном `codex/config/.env` по примеру из репозитория.

```bash
codex/scripts/wp health
codex/scripts/wp site
codex/scripts/wp schema
codex/scripts/wp pages
codex/scripts/wp elementor-get 123 > document.json
```

## Изменение блока Elementor

Возьми версию документа и ID нужного блока из ответа. Параметры зависят от типа виджета. Например, для стандартного заголовка Elementor можно передать такой файл:

```json
{
  "version": "хеш из elementor-get",
  "element_id": "a1b2c3d",
  "settings": {"title": "Новая коллекция"}
}
```

```bash
codex/scripts/wp elementor-update 123 payload.json
codex/scripts/wp elementor-get 123
```

Ключи внутри `settings` заменяются целиком, включая массивы и вложенные объекты. Закрой редактор перед записью и проверь результат во фронтенде и Elementor. На опубликованной странице изменения вступают в силу сразу. При HTTP 409 сначала прочитай свежее состояние.

Если блок на странице ссылается на отдельный HTML Block или шаблон WoodMart, передавай ID исходного документа, если он сохранён в Elementor. Header Builder, настройки темы, импорт демо и создание элементов через этот API не предусмотрены.

Список обычных команд доступен через `codex/scripts/wp help` после задания переменных окружения.
