# Quick Installation Guide

## Prerequisites Check

Before starting, ensure you have:
- ✅ PHP 8.4 or higher (`php -v`)
- ✅ Composer (`composer --version`)
- ✅ MySQL 8.0+ running
- ✅ Redis running (`redis-cli ping` should return PONG)
- ✅ Node.js & npm (`node -v` and `npm -v`)

## Step-by-Step Installation

### 1. Install PHP Dependencies
```bash
composer install
```

### 2. Install Node Dependencies
```bash
npm install
```

### 3. Environment Setup
```bash
cp .env.example .env
php artisan key:generate
```

### 4. Configure Database
Edit `.env` file and update these lines:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=market_platform
DB_USERNAME=root
DB_PASSWORD=your_password_here
```

### 5. Create Database
```bash
mysql -u root -p
```
Then in MySQL:
```sql
CREATE DATABASE market_platform;
EXIT;
```

### 6. Run Migrations & Seeders
```bash
php artisan migrate
php artisan db:seed
```

This will create:
- All database tables
- 10 symbols (SPX, SPY, QQQ, NVDA, AAPL, TSLA, MSFT, AMZN, GOOGL, META)
- Fake quotes for all symbols
- Fake option contracts for SPY, QQQ, NVDA, AAPL, TSLA

### 7. Build Frontend Assets
```bash
npm run build
```

For development with hot reload:
```bash
npm run dev
```

### 8. Start the Application
```bash
php artisan serve
```

Visit: **http://localhost:8000**

## Optional: Start Queue Worker

For background jobs (quote refresh, option chain updates):
```bash
php artisan queue:work redis --queue=default --tries=3
```

## Troubleshooting

### "could not find driver" Error
Install PHP MySQL extension:
```bash
# Ubuntu/Debian
sudo apt-get install php8.4-mysql

# macOS (Homebrew)
brew install php@8.4
```

### Redis Connection Error
Make sure Redis is running:
```bash
# Ubuntu/Debian
sudo service redis-server start

# macOS
brew services start redis
```

### Permission Errors
```bash
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
```

### Clear Cache
```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Default Features Enabled

✅ Dashboard with market overview  
✅ Symbol search functionality  
✅ Watchlist sidebar  
✅ Market movers (gainers/losers/active)  
✅ Unusual options activity feed  
✅ Symbol detail pages  
✅ Full option chain tables  
✅ Options flow heatmap  
✅ Alerts management  

## Next Steps

1. **Add more symbols**: Edit `database/seeders/SymbolSeeder.php`
2. **Configure Schwab API**: Add credentials to `.env` for real data
3. **Customize theme**: Edit `resources/css/app.css`
4. **Add charts**: Integrate TradingView or Chart.js
5. **Set up cron jobs**: For automated quote refresh

## Production Deployment

See `README.md` for full production deployment instructions.

## Support

For issues or questions, check the main README.md file.
