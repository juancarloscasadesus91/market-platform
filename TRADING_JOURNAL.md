# Trading Journal

## Overview
The Trading Journal is a feature that allows you to track your daily trading performance with editable tables and export capabilities.

## Features

### 📊 Editable Table
- **Fecha (Date)**: Click to edit the trading date
- **Capital Inicial**: Starting capital for the day (editable)
- **Profit Diario**: Automatically calculated daily profit
- **% Profit**: Profit percentage (editable) - automatically recalculates profit
- **Formula**: Shows the calculation formula
- **Capital Final**: Ending capital for the day (auto-calculated)
- **Capital Real**: Real capital (editable for manual adjustments)

### 📈 Statistics Dashboard
- **Total Profit**: Sum of all daily profits
- **Average Daily Profit**: Average profit across all entries
- **Win Rate**: Percentage of winning days
- **Total Entries**: Number of trading days tracked

### 🔄 Sorting
Click on any column header to sort by that field. Click again to reverse the sort order.

### 📄 Pagination
- View 10 entries per page
- Navigate between pages using Previous/Next buttons
- Shows current page and total pages

### 📥 Export Options

#### Excel Export (CSV)
- Click "Export Excel" button
- Downloads a CSV file with all entries
- Can be opened in Excel, Google Sheets, etc.
- Filename format: `trading_journal_YYYY-MM-DD.csv`

#### PDF Export (HTML)
- Click "Export PDF" button
- Downloads an HTML file that auto-prints
- Can be saved as PDF using browser's print dialog
- Filename format: `trading_journal_YYYY-MM-DD.html`

### ➕ Add Entry
- Click "Add Entry" to create a new trading day
- New entry starts with the previous day's ending capital
- All fields are editable

### 🗑️ Delete Entry
- Click the trash icon on any row
- Confirms before deletion
- Permanently removes the entry

## How It Works

### Auto-Calculations
When you edit either:
- **Capital Inicial** or
- **% Profit**

The system automatically recalculates:
- **Profit Diario** = Capital Inicial × (% Profit / 100)
- **Capital Final** = Capital Inicial + Profit Diario
- **Formula** = Shows the calculation used

### Data Storage
- All entries are stored in `storage/app/trading_journal.json`
- Data persists between sessions
- Automatically creates sample data on first visit

## Access
Navigate to the Trading Journal page via:
- URL: `/trading-journal`
- Navigation menu: Click "Trading Journal" in the top navigation bar

## Sample Data
On first visit, the system creates 30 days of sample trading data with:
- Starting capital: $10,000
- Random daily profits between -0.5% and 1.5%
- Realistic formulas and calculations

You can delete these entries and add your own real trading data.

## Technical Details

### Files Created
1. `app/Livewire/TradingJournal.php` - Main Livewire component
2. `app/Http/Controllers/TradingJournalExportController.php` - Export controller
3. `resources/views/livewire/trading-journal.blade.php` - Table view
4. `resources/views/trading-journal.blade.php` - Page layout
5. `storage/app/trading_journal.json` - Data storage

### Routes
- `GET /trading-journal` - Main page
- `GET /trading-journal/export/excel` - Excel export
- `GET /trading-journal/export/pdf` - PDF export

## Tips
- Edit the **% Profit** field to automatically update all related calculations
- Use **Capital Real** to track actual account balance vs calculated
- Export regularly to keep backups of your trading data
- Sort by **Profit Diario** to see your best and worst days
- Use the date filter to track specific time periods
