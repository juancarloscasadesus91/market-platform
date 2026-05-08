# Trading Journal - Actualización v2.0

## 🎉 Cambios Implementados

### ✅ Nueva Fórmula de Cálculo
**Fórmula Anterior:**
```
Profit Diario = Capital Inicial * (% Profit / 100)
```

**Fórmula Nueva:**
```
Profit Diario = Capital Inicial * Num Trades * (% Profit / 100)
Capital Final = Capital Inicial + Profit Diario
```

### ✅ Nueva Columna: Número de Trades
- Campo editable para especificar cuántos trades se hicieron ese día
- Por defecto: **2 trades** por día
- Se puede modificar manualmente para cada entrada

### ✅ Migración a Base de Datos
- **Antes:** Almacenamiento en archivo JSON
- **Ahora:** Base de datos MySQL/SQLite
- Tabla: `trading_journal_entries`
- Mejor rendimiento y escalabilidad

### ✅ Datos Generados Automáticamente
- **Capital Inicial:** $230.00
- **Período:** 2 de mayo 2026 - 31 de diciembre 2026
- **Días de Trading:** Solo lunes a viernes (días de mercado)
- **Total de Entradas:** 174 días de trading
- **Trades por Día:** 2 (configurable)

---

## 📊 Estructura de Datos

### Campos de la Tabla

| Campo | Tipo | Descripción | Editable |
|-------|------|-------------|----------|
| **id** | Integer | ID único | No |
| **fecha** | Date | Fecha del trading | Sí |
| **capital_inicial** | Decimal | Capital al inicio del día | Sí |
| **num_trades** | Integer | Número de trades realizados | Sí |
| **profit_percent** | Decimal | Porcentaje de ganancia por trade | Sí |
| **profit_diario** | Decimal | Ganancia total del día | Auto-calculado |
| **formula** | String | Fórmula usada | Auto-generado |
| **capital_final** | Decimal | Capital al final del día | Auto-calculado |
| **capital_real** | Decimal | Capital real (ajustable) | Sí |

---

## 🔢 Ejemplo de Cálculo

### Escenario 1: Día Ganador
```
Capital Inicial: $230.00
Num Trades: 2
% Profit: 1.5%

Cálculo:
Profit Diario = 230.00 * 2 * (1.5 / 100)
Profit Diario = 230.00 * 2 * 0.015
Profit Diario = $6.90

Capital Final = 230.00 + 6.90 = $236.90
```

### Escenario 2: Día Perdedor
```
Capital Inicial: $236.90
Num Trades: 2
% Profit: -0.8%

Cálculo:
Profit Diario = 236.90 * 2 * (-0.8 / 100)
Profit Diario = 236.90 * 2 * -0.008
Profit Diario = -$3.79

Capital Final = 236.90 - 3.79 = $233.11
```

### Escenario 3: Día con Más Trades
```
Capital Inicial: $233.11
Num Trades: 5
% Profit: 0.5%

Cálculo:
Profit Diario = 233.11 * 5 * (0.5 / 100)
Profit Diario = 233.11 * 5 * 0.005
Profit Diario = $5.83

Capital Final = 233.11 + 5.83 = $238.94
```

---

## 🎲 Generación de Datos Realistas

El seeder genera datos con la siguiente distribución:

- **15%** de probabilidad de pérdida (-2% a -0.1%)
- **35%** de probabilidad de ganancia pequeña (0.1% a 1%)
- **35%** de probabilidad de ganancia media (1% a 3%)
- **15%** de probabilidad de ganancia grande (3% a 5%)

Esto simula un trader consistentemente rentable con días ocasionales de pérdida.

---

## 💾 Base de Datos

### Migración
```bash
php artisan migrate
```

### Seeder
```bash
php artisan db:seed --class=TradingJournalSeeder
```

### Re-generar Datos
```bash
# Limpiar y re-generar
php artisan migrate:fresh --seed --class=TradingJournalSeeder
```

---

## 🔄 Cálculos Automáticos

Cuando editas cualquiera de estos campos:
- **Capital Inicial**
- **Num Trades**
- **% Profit**

El sistema automáticamente recalcula:
- ✅ Profit Diario
- ✅ Capital Final
- ✅ Formula

---

## 📱 Interfaz Actualizada

### Columnas en la Tabla
1. **Fecha** - Editable (date picker)
2. **Capital Inicial** - Editable (número)
3. **Num Trades** - ⭐ NUEVO - Editable (entero)
4. **Profit Diario** - Auto-calculado (solo lectura)
5. **% Profit** - Editable (decimal)
6. **Formula** - Auto-generado (solo lectura)
7. **Capital Final** - Auto-calculado (solo lectura)
8. **Capital Real** - Editable (para ajustes manuales)
9. **Actions** - Botón eliminar

---

## 🚀 Uso

### Editar Número de Trades
```
1. Haz clic en el campo "Num Trades"
2. Cambia el valor (ej: de 2 a 5)
3. El sistema recalcula automáticamente el profit
```

### Agregar Nueva Entrada
```
1. Click en "Add Entry"
2. Se crea con:
   - Fecha: Hoy
   - Capital Inicial: Capital final del día anterior
   - Num Trades: 2 (por defecto)
   - % Profit: 0
```

### Exportar Datos
Los exports ahora incluyen la columna **Num Trades**:
- Excel (CSV): Incluye todas las columnas
- PDF (HTML): Tabla completa con formato

---

## 📈 Estadísticas

Las estadísticas se calculan sobre **todas** las entradas:
- **Total Profit**: Suma de todos los profits diarios
- **Avg Daily Profit**: Promedio de profit por día
- **Win Rate**: Porcentaje de días ganadores
- **Total Entries**: 174 días de trading

---

## 🔧 Archivos Modificados

### Backend
- ✅ `database/migrations/2026_05_02_090014_create_trading_journal_entries_table.php`
- ✅ `app/Models/TradingJournalEntry.php`
- ✅ `database/seeders/TradingJournalSeeder.php`
- ✅ `app/Livewire/TradingJournal.php`
- ✅ `app/Http/Controllers/TradingJournalExportController.php`

### Frontend
- ✅ `resources/views/livewire/trading-journal.blade.php`

---

## 📊 Proyección de Capital

Con los datos generados (174 días de trading):

**Escenario Conservador** (promedio 0.5% por trade, 2 trades/día):
```
Día 1: $230.00
Día 30: ~$237.00
Día 90: ~$251.00
Día 174: ~$272.00
```

**Escenario Optimista** (promedio 1% por trade, 2 trades/día):
```
Día 1: $230.00
Día 30: ~$244.00
Día 90: ~$274.00
Día 174: ~$315.00
```

---

## ⚠️ Notas Importantes

1. **Capital Encadenado**: El capital inicial de cada día es el capital final del día anterior
2. **Solo Días de Semana**: No se generan entradas para sábados y domingos
3. **Fórmula Multiplicativa**: Más trades = más profit (o más pérdida)
4. **Capital Real vs Calculado**: Usa "Capital Real" para ajustar por comisiones/slippage

---

## 🎯 Próximos Pasos Sugeridos

- [ ] Agregar gráfico de crecimiento de capital
- [ ] Filtro por rango de fechas
- [ ] Análisis de mejor/peor día
- [ ] Promedio de profit por número de trades
- [ ] Exportar a Google Sheets
- [ ] Backup automático de la base de datos

---

**Versión:** 2.0  
**Fecha:** 2 de mayo de 2026  
**Estado:** ✅ Completado y Funcional
