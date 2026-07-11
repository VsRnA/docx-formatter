# Docx Formatter

Внутренний веб-сервис обработки инструкций: `.docx` → перевод → HTML-редактор → экспорт.

**Стек:** Laravel (Clean Architecture), React (FSD) + Ant Design, PostgreSQL, Redis, Horizon, Docker, Caddy, Grafana + Loki.

## Структура

```
backend/   — Laravel API + queue workers (Horizon)
frontend/  — React SPA (Vite)
deploy/    — Caddy, Loki, Promtail, Grafana configs
```

## Локальная разработка (Docker)

```bash
cp .env.example .env
docker compose build
docker compose up -d

docker compose exec backend composer install
docker compose exec backend php artisan key:generate
docker compose exec backend php artisan migrate
docker compose exec frontend npm install
```

- API: http://localhost:8080/api/v1
- Frontend (dev): http://localhost:5173

### Режимы интеграций Yandex

| Режим | `.env` |
|-------|--------|
| Всё локально | `MOCK_STORAGE=true`, `MOCK_TRANSLATION=true` |
| Только перевод через Yandex AI | `MOCK_STORAGE=true`, `MOCK_TRANSLATION=false` + `YANDEX_AI_*` |
| Production | `MOCK_*=false` + `S3_*` и `YANDEX_AI_*` |

После изменения `.env` пересоздайте контейнеры:

```bash
docker compose up -d --force-recreate backend queue
```

## Команды (Docker)

```bash
docker compose exec backend php artisan test
docker compose exec backend php artisan horizon:status
docker compose exec frontend npm run lint
docker compose exec frontend npm run test
docker compose exec frontend npm run build
```

## CI

GitHub Actions (`.github/workflows/ci.yml`) на push/PR в `main`:

- backend: `composer install`, `php artisan test`, Pint
- frontend: `npm ci`, lint, test, production build

Автодеплоя нет — обновление на VPS выполняется вручную (см. ниже).

## Деплой на VPS (Ubuntu, доступ по IPv4, без домена)

> **Важно:** текущая production-конфигурация рассчитана на доступ по **plain HTTP** (`http://<VPS_IP>`). Basic Auth передаёт логин и пароль в открытом виде. Для внутреннего инструмента на 5–10 пользователей это обычно приемлемо; если нужен HTTPS — потребуется домен и отдельная настройка Caddy с Let's Encrypt.

Рекомендуемые ресурсы VPS: **4 vCPU / 8 GB RAM / 50+ GB SSD**.

### 1. Подготовка сервера

```bash
apt update && apt upgrade -y
adduser deploy
usermod -aG sudo deploy

apt install -y ufw
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 3001/tcp
ufw enable
```

Установка Docker (под пользователем `deploy`):

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker $USER
newgrp docker
```

### 2. Публикация кода (локально, до клонирования на сервер)

```bash
git add -A
git commit -m "Prepare production deployment"
git push origin main
```

### 3. Клонирование и настройка `.env`

```bash
git clone https://github.com/VsRnA/docx-formatter.git
cd docx-formatter
cp .env.example .env
nano .env
```

Пример `.env` для IP `203.0.113.10`:

```env
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:...          # см. шаг 4
APP_URL=http://203.0.113.10
FRONTEND_URL=http://203.0.113.10
LOG_CHANNEL=stderr
LOG_LEVEL=info

GRAFANA_ROOT_URL=http://203.0.113.10:3001

BASIC_AUTH_USER=admin
BASIC_AUTH_HASH=...         # см. шаг 5
GRAFANA_BASIC_AUTH_USER=admin
GRAFANA_BASIC_AUTH_HASH=...

GRAFANA_ADMIN_USER=admin
GRAFANA_ADMIN_PASSWORD=...

MOCK_STORAGE=false
MOCK_TRANSLATION=false
MOCK_NORMALIZER=false
MOCK_YANDEX=false
S3_*=...
YANDEX_AI_*=...

HORIZON_MAX_PROCESSES=5
```

### 4. APP_KEY

```bash
docker compose run --rm backend php artisan key:generate --show
```

Скопировать вывод в `.env`.

### 5. Хэши для Basic Auth

```bash
docker run --rm caddy:2-alpine caddy hash-password --plaintext 'пароль-для-приложения'
docker run --rm caddy:2-alpine caddy hash-password --plaintext 'пароль-для-grafana'
```

### 6. Запуск production-стека

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml build
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
docker compose exec backend php artisan migrate --force
```

Production-стек включает:

- **Caddy** — HTTP Basic Auth, порты 80 (приложение) и 3001 (Grafana)
- **backend** — Laravel API + nginx + php-fpm
- **queue** — Laravel Horizon
- **frontend** — собранный Vite-бандл за nginx
- **postgres**, **redis**
- **loki**, **promtail**, **grafana** — логи и алерты

Доступ:

- Приложение: `http://<VPS_IP>` (Basic Auth)
- Horizon: `http://<VPS_IP>/horizon`
- Grafana: `http://<VPS_IP>:3001` (Basic Auth + логин Grafana)

### 7. Проверка

```bash
curl -u admin:пароль http://<VPS_IP>/up
docker compose ps
docker compose logs -f caddy
```

### 8. Обновление версии

```bash
git pull
docker compose -f docker-compose.yml -f docker-compose.prod.yml build
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d
docker compose exec backend php artisan migrate --force
```

### 9. Backup PostgreSQL (рекомендуется)

```bash
mkdir -p ~/backups
crontab -e
```

```cron
0 3 * * * cd /home/deploy/docx-formatter && docker compose exec -T postgres pg_dump -U docx docx_formatter | gzip > /home/deploy/backups/docx_formatter_$(date +\%F).sql.gz
```

Обновите email в `deploy/grafana/provisioning/alerting/contact-points.yml` и настройте SMTP в Grafana UI, если нужны email-алерты.

## Безопасность

- Доступ закрыт **HTTP Basic Auth** на уровне Caddy (общий логин/пароль).
- Без TLS пароль передаётся по сети в открытом виде — используйте только в доверенной сети или добавьте домен + HTTPS позже.
- Postgres в production не публикует порт наружу.

## Smoke-тест

1. Открыть `http://<VPS_IP>`, пройти Basic Auth.
2. Загрузить `.docx`, дождаться `status: ready`.
3. Открыть редактор, сохранить черновик.
4. Проверить файл в S3 (`docx-formatter/documents/{uuid}/source.docx`).
5. Открыть Grafana → дашборд «Docx Formatter Logs».
