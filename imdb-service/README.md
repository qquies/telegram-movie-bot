# IMDb Service

Laravel-сервис для поиска фильмов через OMDb API с кэшированием в Redis.

## Запуск в Docker

1. При необходимости укажите `OMDB_API_KEY` в локальном `.env`.
2. Соберите и запустите сервис:

```bash
docker compose up --build
```

Сервис будет доступен на `http://localhost:8000`.

## Redis в Docker

Redis запускается как отдельный контейнер `redis` в `docker-compose.yml`.
Приложение использует его как кэш (`CACHE_STORE=redis`, `REDIS_HOST=redis`).

## Healthcheck

- В приложении используется встроенный Laravel health-роут `GET /up`.
- В `Dockerfile` добавлен `HEALTHCHECK`, который проверяет `http://127.0.0.1:8000/up`.
- Для Redis добавлен healthcheck `redis-cli ping`.

Проверить статус:

```bash
docker compose ps
```

## Интеграционные тесты в контейнерной среде

Запуск интеграционных тестов (профиль `test`):

```bash
docker compose --profile test run --rm test
```

Тестовый контейнер использует тот же образ приложения и подключается к контейнеру Redis.
