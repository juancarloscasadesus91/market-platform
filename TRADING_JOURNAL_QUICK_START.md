# Trading Journal - Quick Start Guide

## 🚀 Acceso Rápido

### URL Directa
```
http://localhost/trading-journal
```

### Desde el Menú
Haz clic en **"Trading Journal"** en la barra de navegación superior.

---

## ✨ Características Principales

### 1. **Tabla Editable**
Todas las celdas son editables directamente:
- **Fecha**: Haz clic para cambiar la fecha
- **Capital Inicial**: Edita el capital de inicio del día
- **% Profit**: Edita el porcentaje de ganancia (auto-calcula todo)
- **Capital Real**: Ajusta manualmente si difiere del calculado

### 2. **Cálculos Automáticos**
Al editar **Capital Inicial** o **% Profit**, se recalcula automáticamente:
- ✅ Profit Diario = Capital Inicial × (% Profit / 100)
- ✅ Capital Final = Capital Inicial + Profit Diario
- ✅ Formula = Muestra el cálculo usado

### 3. **Estadísticas en Tiempo Real**
- 💰 **Total Profit**: Suma de todas las ganancias
- 📊 **Avg Daily Profit**: Promedio de ganancia diaria
- 🎯 **Win Rate**: Porcentaje de días ganadores
- 📈 **Total Entries**: Número total de registros

### 4. **Exportar Datos**

#### 📗 Excel (CSV)
```
Botón: "Export Excel"
Formato: trading_journal_2026-05-02.csv
Compatible con: Excel, Google Sheets, LibreOffice
```

#### 📕 PDF (HTML)
```
Botón: "Export PDF"
Formato: trading_journal_2026-05-02.html
Se abre automáticamente el diálogo de impresión
Guardar como PDF desde el navegador
```

### 5. **Gestión de Entradas**

#### ➕ Agregar Nueva Entrada
```
1. Clic en "Add Entry"
2. Se crea con el capital final del día anterior
3. Edita los campos según necesites
```

#### 🗑️ Eliminar Entrada
```
1. Clic en el ícono de basura (🗑️)
2. Confirma la eliminación
3. Se elimina permanentemente
```

### 6. **Ordenamiento**
Haz clic en cualquier encabezado de columna para ordenar:
- Primera vez: Orden ascendente ⬆️
- Segunda vez: Orden descendente ⬇️

### 7. **Paginación**
- 10 entradas por página
- Navegación: Previous / Next
- Muestra: Página actual / Total de páginas

---

## 📝 Ejemplo de Uso

### Día 1: Registro de Trading
```
Fecha: 2026-05-02
Capital Inicial: $10,000.00
% Profit: 2.5
→ Profit Diario: $250.00 (auto-calculado)
→ Capital Final: $10,250.00 (auto-calculado)
Capital Real: $10,250.00
```

### Día 2: Siguiente Día
```
Fecha: 2026-05-03
Capital Inicial: $10,250.00 (del día anterior)
% Profit: -1.2
→ Profit Diario: -$123.00 (auto-calculado)
→ Capital Final: $10,127.00 (auto-calculado)
Capital Real: $10,100.00 (ajuste manual por comisiones)
```

---

## 🎨 Colores de la Interfaz

- 🟢 **Verde**: Ganancias positivas
- 🔴 **Rojo**: Pérdidas
- ⚪ **Blanco**: Valores neutros
- 🟡 **Amarillo**: Campos editables (al hacer hover)

---

## 💾 Almacenamiento de Datos

Los datos se guardan automáticamente en:
```
storage/app/trading_journal.json
```

**Importante**: 
- ✅ Los datos persisten entre sesiones
- ✅ Se guardan automáticamente al editar
- ✅ Exporta regularmente como backup
- ⚠️ No se pierde al cerrar el navegador

---

## 🔧 Solución de Problemas

### No aparecen los datos
```bash
# Verifica que existe el archivo
ls -la storage/app/trading_journal.json

# Verifica permisos
chmod 664 storage/app/trading_journal.json
```

### Error al exportar
```bash
# Verifica que las rutas están registradas
php artisan route:list --name=trading-journal
```

### Cambios no se guardan
```bash
# Limpia la caché
php artisan cache:clear
php artisan view:clear
```

---

## 📊 Datos de Ejemplo

Al visitar por primera vez, se crean automáticamente **30 días** de datos de ejemplo:
- Capital inicial: $10,000
- Ganancias aleatorias: -0.5% a 1.5%
- Puedes eliminarlos y agregar tus datos reales

---

## 🎯 Mejores Prácticas

1. **Actualiza diariamente** después de cerrar tus operaciones
2. **Exporta semanalmente** como backup
3. **Revisa las estadísticas** para identificar patrones
4. **Usa Capital Real** para ajustes por comisiones/slippage
5. **Ordena por Profit Diario** para ver tus mejores/peores días

---

## 📱 Responsive Design

La tabla es completamente responsive:
- ✅ Desktop: Vista completa
- ✅ Tablet: Scroll horizontal
- ✅ Mobile: Scroll horizontal con campos táctiles

---

## 🚀 Próximas Mejoras Sugeridas

- [ ] Gráficos de rendimiento
- [ ] Filtros por rango de fechas
- [ ] Notas por día de trading
- [ ] Categorías de estrategias
- [ ] Comparación mes a mes
- [ ] Exportación a Google Sheets automática

---

**¡Listo para usar! 🎉**

Navega a `/trading-journal` y comienza a trackear tu rendimiento.
