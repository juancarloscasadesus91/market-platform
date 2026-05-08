# Schwab API Token Auto-Refresh Setup

## ✅ Implementación Completada

### 1. Sistema de Refresh Automático
- **Access Token**: Se refresca automáticamente cada 20 minutos (expira en 30 min)
- **Refresh Token**: Se almacena y usa para obtener nuevos access tokens (válido por 7 días)
- **Buffer de seguridad**: 5 minutos antes de expiración

### 2. Componentes Creados

#### Servicio Actualizado
- `app/Services/SchwabAuthService.php`
  - Almacena access token y refresh token en cache
  - Refresca automáticamente cuando el token expira
  - Métodos: `getAccessToken()`, `hasRefreshToken()`, `clearToken()`

#### Comando Artisan
- `app/Console/Commands/RefreshSchwabToken.php`
  - Comando: `php artisan schwab:refresh-token`
  - Refresca el token manualmente

#### Scheduled Job
- `routes/console.php`
  - Ejecuta `schwab:refresh-token` cada 20 minutos automáticamente

#### Widget de Estado
- `app/Livewire/SchwabTokenStatus.php`
- `resources/views/livewire/schwab-token-status.php`
  - Muestra estado del token en el dashboard
  - Permite refrescar manualmente
  - Botón para re-autenticar si es necesario

#### Middleware (Opcional)
- `app/Http/Middleware/EnsureSchwabTokenIsValid.php`
  - Verifica token antes de requests a la API

## 🚀 Configuración Inicial

### Paso 1: Autenticarse por Primera Vez
```bash
# Visita esta URL en tu navegador:
https://api.schwabapi.com/v1/oauth/authorize?client_id=BJjNqh4WuzGVXryUZE3qGldSODdZmPrISIwWZPncqGhc7PRD&redirect_uri=https%3A%2F%2Fmyschwab.synergize.co%2Fauth%2Fschwab%2Fcallback

# O desde el dashboard, haz clic en "Authenticate with Schwab"
```

### Paso 2: Iniciar el Scheduler de Laravel

**Opción A: Usando systemd (Recomendado para producción)**

1. Crear archivo de servicio:
```bash
sudo nano /etc/systemd/system/laravel-scheduler.service
```

2. Agregar contenido:
```ini
[Unit]
Description=Laravel Scheduler
After=network.target

[Service]
Type=simple
User=jhony
WorkingDirectory=/home/jhony/Documentos/TOS/market-platform
ExecStart=/usr/bin/php /home/jhony/Documentos/TOS/market-platform/artisan schedule:work
Restart=always
RestartSec=3

[Install]
WantedBy=multi-user.target
```

3. Habilitar y arrancar:
```bash
sudo systemctl daemon-reload
sudo systemctl enable laravel-scheduler
sudo systemctl start laravel-scheduler
sudo systemctl status laravel-scheduler
```

**Opción B: Usando Cron (Alternativa)**

```bash
# Editar crontab
crontab -e

# Agregar esta línea:
* * * * * cd /home/jhony/Documentos/TOS/market-platform && php artisan schedule:run >> /dev/null 2>&1
```

**Opción C: Para Desarrollo (Temporal)**

```bash
# En una terminal separada, ejecutar:
php artisan schedule:work

# Esto ejecutará el scheduler en foreground
```

### Paso 3: Verificar que Funciona

```bash
# Ver logs del scheduler
tail -f storage/logs/laravel.log

# Refrescar token manualmente
php artisan schwab:refresh-token

# Ver estado en el dashboard
# Visita: http://localhost/dashboard
```

## 📊 Monitoreo

### Dashboard Widget
- **Verde**: Token activo y válido
- **Rojo**: Token expirado o faltante
- **Botón "Refresh Token Now"**: Refresca manualmente
- **Botón "Authenticate with Schwab"**: Re-autenticar si refresh token expiró

### Comandos Útiles

```bash
# Ver estado del token
php artisan tinker
>>> app(App\Services\SchwabAuthService::class)->hasRefreshToken()
>>> app(App\Services\SchwabAuthService::class)->getAccessToken()

# Limpiar tokens (forzar re-autenticación)
php artisan tinker
>>> app(App\Services\SchwabAuthService::class)->clearToken()

# Ver próximas tareas programadas
php artisan schedule:list
```

## 🔄 Flujo de Refresh Automático

1. **Cada 20 minutos**: Laravel Scheduler ejecuta `schwab:refresh-token`
2. **El comando verifica**: ¿Existe refresh token?
3. **Si existe**: Llama a Schwab API con `grant_type=refresh_token`
4. **Schwab responde**: Nuevo access token (30 min) + nuevo refresh token (7 días)
5. **Se almacena**: En cache de Laravel
6. **Todos los requests**: Usan el token actualizado automáticamente

## ⚠️ Importante

- **Refresh Token expira en 7 días**: Debes autenticarte al menos una vez por semana
- **Access Token expira en 30 min**: Se refresca automáticamente cada 20 min
- **Si el scheduler no corre**: Los tokens expirarán y necesitarás re-autenticar
- **En producción**: Usa systemd para mantener el scheduler corriendo 24/7

## 🐛 Troubleshooting

### Token no se refresca
```bash
# Verificar que el scheduler está corriendo
ps aux | grep "schedule:work"

# O si usas systemd:
sudo systemctl status laravel-scheduler

# Ver logs
tail -f storage/logs/laravel.log
```

### Refresh Token expiró
- Visita el dashboard
- Haz clic en "Authenticate with Schwab"
- Completa el flujo OAuth
- El nuevo refresh token se guardará automáticamente

### Cache no funciona
```bash
# Limpiar cache
php artisan cache:clear

# Verificar driver de cache en .env
# Debe ser 'file' o 'redis', no 'array'
CACHE_DRIVER=file
```
