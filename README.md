# Docx Formatter

Внутренний веб-сервис обработки инструкций: `.docx` → перевод → HTML-редактор → публикация.

**Стек:** Laravel (MVC + Clean Architecture), React (FSD) + Ant Design, PostgreSQL, Docker.

## Важно

Код разрабатывается в репозитории **без обязательного запуска** на машине разработчика. Не нужно поднимать `docker compose`, `composer` или `npm` локально, пока вы сами не решите проверить сборку. Запуск и тесты — только через Docker, когда будете готовы.

## Структура

```
backend/   — Laravel API
frontend/  — React (FSD)
```

## Режимы интеграций Yandex

Флаги можно комбинировать (`MOCK_YANDEX` — legacy-значение по умолчанию для обоих, если `MOCK_STORAGE` / `MOCK_TRANSLATION` не заданы):

| Режим | `.env` |
|-------|--------|
| Всё локально (парсинг без облака и AI) | `MOCK_STORAGE=true`, `MOCK_TRANSLATION=true` |
| Только перевод через Yandex AI | `MOCK_STORAGE=true`, `MOCK_TRANSLATION=false` + `YANDEX_AI_*` |
| Production | `MOCK_STORAGE=false`, `MOCK_TRANSLATION=false` + все ключи |

### Только перевод (файлы локально)

```env
MOCK_STORAGE=true
MOCK_TRANSLATION=false
YANDEX_AI_API_KEY=...
YANDEX_AI_FOLDER_ID=...   # ID каталога в Yandex Cloud
```

`YC_STORAGE_*` не нужны. Файлы — в `backend/storage/app/mock-cloud/`.

После изменения `.env` пересоздайте контейнеры (обычный `restart` **не** подхватывает `env_file`):

```bash
docker compose up -d --force-recreate backend queue
```

### Без Yandex (проверка парсинга DOCX)

```env
MOCK_STORAGE=true
MOCK_TRANSLATION=true
MOCK_TRANSLATE_ENABLED=false   # оставить английский текст как в документе
QUEUE_CONNECTION=sync          # обработка без отдельного queue-воркера
```

Парсинг из CLI (файл должен быть внутри контейнера):

```bash
docker compose exec backend php artisan docx:parse /var/www/html/storage/app/tmp/sample.docx
```

Для production: `MOCK_YANDEX=false` и заполнить ключи Yandex.

## Первый запуск (когда понадобится)

```bash
cp .env.example .env
# для первого прогона достаточно MOCK_YANDEX=true (см. выше)
# для production: MOCK_YANDEX=false и заполнить YC_STORAGE_* и YANDEX_AI_*

docker compose build
docker compose up -d

docker compose exec backend composer install
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate

docker compose exec frontend npm install
```

- API: http://localhost:8080/api/v1
- Frontend: http://localhost:5173
- Публичная страница: http://localhost:8080/p/{slug}

## Команды (только Docker)

```bash
docker compose exec backend php artisan test
docker compose exec backend php artisan queue:work
docker compose exec frontend npm run lint
docker compose exec frontend npm run build
```

## Auth

На MVP авторизация отключена (`REQUIRE_AUTH=false`). Перед продакшеном — Laravel Sanctum.

## Smoke-тест

1. Загрузить `.docx` на главной странице.
2. Дождаться статуса `ready`.
3. Открыть блочный редактор, внести правки и сохранить черновик.
4. При необходимости экспортировать HTML или опубликовать документ.
