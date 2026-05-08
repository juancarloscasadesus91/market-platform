# Cron Job Setup

## Configuración del Scheduler de Laravel

Para que los comandos programados se ejecuten automáticamente, necesitas configurar un cron job en tu servidor.

### 1. Editar Crontab

```bash
crontab -e
```

### 2. Agregar esta línea

```bash
* * * * * cd /home/jhony/Documentos/TOS/market-platform && php artisan schedule:run >> /dev/null 2>&1
```

### 3. Verificar que el cron está activo

```bash
crontab -l
```

## Comandos Programados

### Sincronización de Símbolos
- **Comando**: `php artisan schwab:sync-symbols`
- **Frecuencia**: Diariamente a las 6:00 AM (hora de Nueva York)
- **Propósito**: Sincroniza todos los símbolos e índices disponibles desde la API de Schwab

## Ejecutar Manualmente

Para probar el comando sin esperar al cron:

```bash
# Sincronizar símbolos
php artisan schwab:sync-symbols

# Ver el schedule configurado
php artisan schedule:list

# Ejecutar todos los comandos programados ahora
php artisan schedule:run
```

## Logs

Los logs del scheduler se guardan en:
- `storage/logs/laravel.log`

Para ver los logs en tiempo real:
```bash
tail -f storage/logs/laravel.log
```

## Personalizar Frecuencia

Edita `routes/console.php` para cambiar la frecuencia:

```php
// Cada hora
Schedule::command('schwab:sync-symbols')->hourly();

// Cada 6 horas
Schedule::command('schwab:sync-symbols')->everySixHours();

// Semanalmente (Domingos a las 2 AM)
Schedule::command('schwab:sync-symbols')->weeklyOn(0, '02:00');

// Solo días de semana a las 6 AM
Schedule::command('schwab:sync-symbols')->weekdays()->at('06:00');
```
