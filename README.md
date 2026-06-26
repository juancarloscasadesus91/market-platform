# Market Platform

A production-grade financial market analysis platform built with Laravel 12, featuring real-time market data, options chain analysis, premium flow heatmaps, and intelligent alerts.

## 🎯 Features

### Dashboard
- **Market Overview**: Real-time quotes for major indices (SPX, SPY, QQQ)
- **Top Movers**: Track gainers, losers, and most active stocks
- **Unusual Options Activity**: Monitor high-volume option contracts
- **Mini Heatmap**: Quick view of options flow intensity

### Symbol Detail Page
- **Real-time Quote Data**: Live prices, volume, and market statistics
- **Options Sentiment**: Call/Put volume analysis and ratios
- **Volatility Metrics**: IV rank, percentile, and historical data
- **Support/Resistance Levels**: Key price zones
- **Full Option Chain**: Professional-grade options table with Greeks

### Options Heatmap
- **Multi-Symbol Analysis**: Visualize flow across multiple tickers
- **Premium Flow Tracking**: Identify where money is moving
- **Volume Clusters**: Spot unusual activity zones
- **IV Spike Detection**: Track volatility changes

### Alerts System
- **Unusual Premium Alerts**: Detect abnormal option activity
- **Volume Spike Notifications**: Track sudden volume changes
- **Delta Threshold Triggers**: Monitor position Greeks
- **Price Movement Alerts**: Get notified on key price levels
- **IV Spike Warnings**: Track volatility explosions

## 🏗️ Architecture

### Tech Stack
- **Laravel 12** - Modern PHP framework
- **PHP 8.4** - Latest PHP features
- **Livewire 3** - Reactive components
- **Alpine.js** - Lightweight JavaScript
- **Tailwind CSS 4** - Utility-first styling
- **MySQL** - Relational database
- **Redis** - Caching and queues

### Clean Architecture
```
app/
├── Actions/          # Single-purpose action classes
├── DTOs/            # Data Transfer Objects
├── Services/        # Business logic layer
│   ├── SchwabAuthService
│   ├── SchwabQuoteService
│   ├── SchwabOptionChainService
│   ├── MarketMetricsService
│   └── HeatmapBuilderService
├── Jobs/            # Queue jobs
│   ├── RefreshQuotesJob
│   ├── RefreshOptionChainJob
│   └── BuildHeatmapJob
├── Livewire/        # Reactive components
├── Models/          # Eloquent models
└── Support/
    └── Enums/       # Type-safe enumerations
```

## 🚀 Installation

### Prerequisites
- PHP 8.4+
- Composer
- MySQL 8.0+
- Redis
- Node.js & npm (for asset compilation)

### Setup Steps

1. **Clone and install dependencies**
```bash
cd market-platform
composer install
npm install
```

2. **Environment configuration**
```bash
cp .env.example .env
php artisan key:generate
```

3. **Configure database**
Edit `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=market_platform
DB_USERNAME=root
DB_PASSWORD=your_password
```

4. **Configure Redis**
```env
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
CACHE_STORE=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
```

5. **Schwab API credentials** (optional)
```env
SCHWAB_APP_KEY=your_app_key
SCHWAB_APP_SECRET=your_app_secret
SCHWAB_CALLBACK_URL=http://localhost:8000/auth/schwab/callback
```

6. **Run migrations and seed data**
```bash
php artisan migrate
php artisan db:seed
```

7. **Build assets**
```bash
npm run build
```

8. **Start the application**
```bash
php artisan serve
```

Visit `http://localhost:8000`

## 🎨 UI Design

### Dark Premium Theme
- **Charcoal backgrounds** (#0a0b0d, #13151a, #1a1d24)
- **Glassmorphism cards** with backdrop blur
- **Soft blue accents** for interactive elements
- **Emerald green** for bullish indicators
- **Muted red** for bearish indicators
- **Smooth transitions** and hover effects

### Responsive Design
- Mobile-first approach
- Adaptive layouts for all screen sizes
- Touch-friendly interface
- Optimized for desktop trading

## 📊 Data Flow

### Quote Refresh
```
RefreshQuotesJob → SchwabQuoteService → QuoteData DTO → Database
                                      ↓
                                   Cache (60s TTL)
```

### Option Chain
```
RefreshOptionChainJob → SchwabOptionChainService → OptionChainData DTO
                                                 ↓
                                    OptionContractData DTOs → Database
```

### Heatmap Generation
```
BuildHeatmapJob → HeatmapBuilderService → HeatmapCellData DTOs
                                        ↓
                                    Cache (15min TTL)
```

## 🔧 Queue Workers

Start queue workers for background processing:

```bash
php artisan queue:work redis --queue=default --tries=3
```

## 📝 Seeded Data

The application comes with fake data for:
- **SPX** - S&P 500 Index
- **SPY** - SPDR S&P 500 ETF
- **QQQ** - Invesco QQQ Trust
- **NVDA** - NVIDIA Corporation
- **AAPL** - Apple Inc.
- **TSLA** - Tesla, Inc.
- **MSFT** - Microsoft Corporation
- **AMZN** - Amazon.com
- **GOOGL** - Alphabet Inc.
- **META** - Meta Platforms

## 🔐 Security

- CSRF protection on all forms
- SQL injection prevention via Eloquent ORM
- XSS protection in Blade templates
- Rate limiting on API endpoints
- Secure session management with Redis

## 📈 Performance

- **Redis caching** for frequently accessed data
- **Eager loading** to prevent N+1 queries
- **Database indexing** on critical columns
- **Queue jobs** for heavy operations
- **Asset optimization** with Vite

## 🧪 Testing

```bash
php artisan test
```

## 📦 Production Deployment

1. Set `APP_ENV=production` and `APP_DEBUG=false`
2. Run `php artisan config:cache`
3. Run `php artisan route:cache`
4. Run `php artisan view:cache`
5. Set up supervisor for queue workers
6. Configure Redis persistence
7. Enable HTTPS
8. Set up database backups

## 🤝 Contributing

This is a production-ready starter template. Feel free to customize and extend based on your needs.

## 📄 License

MIT License
