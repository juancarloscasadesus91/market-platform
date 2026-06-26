# Docker para Market Platform

Este entorno levanta Laravel, Vite, MySQL y Redis sin instalar PHP, Composer, Node, MySQL ni Redis en Windows.

## Requisitos

- Docker Desktop para Windows.

## Primer arranque

Desde la carpeta `market-platform`:

```bash
docker compose up --build
```

Luego abre:

- Aplicacion: http://localhost:8000
- Vite: http://localhost:5173

En el primer arranque el contenedor instala Composer/NPM, crea `.env` si no existe, genera `APP_KEY` si falta y ejecuta las migraciones.

## Variables y credenciales

Si ya tienes `.env`, Docker lo conserva, pero en cada arranque sincroniza las variables de infraestructura para usar los servicios del Compose: `DB_HOST=mysql`, `REDIS_HOST=redis`, `REDIS_URL=redis://redis:6379`, colas/cache/sesiones en Redis y `APP_URL=http://localhost:8000`.

Para APIs externas, configura tus valores en `.env`, por ejemplo:

```dotenv
SCHWAB_APP_KEY=
SCHWAB_APP_SECRET=
ALPACA_API_KEY=
ALPACA_API_SECRET=
```

## Comandos utiles

```bash
docker compose down
docker compose exec app php artisan migrate
docker compose exec app php artisan test
docker compose exec app php artisan tinker
docker compose exec app npm run build
```

Para borrar la base de datos y empezar de cero:

```bash
docker compose down -v
docker compose up --build
```
