# Schwab API Setup - Two Separate Authentications Required

This application requires **TWO separate OAuth authentications** with different scopes:

## Why Two Authentications?

Schwab uses different OAuth scopes for different APIs:
- **Market Data API**: Uses `readonly` scope
- **Trader API**: Uses `api` scope

You can use the **SAME Schwab App** (same App Key), but you need to authenticate **TWICE** - once for each scope. Each authentication will give you a separate token.

## Setup Instructions

### 1. Create ONE Schwab Application

1. Go to https://developer.schwab.com
2. Create a new application
3. Name it something like "Your Trading App"
4. Add **TWO Redirect URIs**:
   - `http://localhost:8000/auth/schwab/callback`
   - `http://localhost:8000/auth/schwab/trader/callback`
5. Request **BOTH scopes**: `readonly` AND `api`
6. Save your **App Key** and **App Secret**

### 2. Configure Environment Variables

Add the following to your `.env` file (use the SAME credentials for both):

```env
# Schwab Market Data API Configuration
SCHWAB_APP_KEY=your_app_key_here
SCHWAB_APP_SECRET=your_app_secret_here
SCHWAB_CALLBACK_URL=http://localhost:8000/auth/schwab/callback

# Schwab Trader API Configuration (SAME app, different callback)
SCHWAB_TRADER_APP_KEY=your_app_key_here
SCHWAB_TRADER_APP_SECRET=your_app_secret_here
SCHWAB_TRADER_CALLBACK_URL=http://localhost:8000/auth/schwab/trader/callback

# Schwab API Base URL (same for both)
SCHWAB_API_BASE_URL=https://api.schwabapi.com
```

**Note:** Use the SAME App Key and App Secret for both. The difference is only in the callback URL and the scope requested during OAuth.

### 4. Authenticate Both APIs

1. Start your application: `php artisan serve`
2. Go to the dashboard
3. In the **Schwab API Status** panel:
   - Click **"🔐 Authenticate Market Data API"** (blue button)
   - Complete the OAuth flow
   - Click **"🔐 Authenticate Trader API"** (green button)
   - Complete the OAuth flow

### 5. Verify Connection

After authenticating both APIs, you should see:
- ✅ **Market Data API**: Connected
- ✅ **Trader API (Accounts & Trading)**: Connected
- ✅ **Streaming Credentials**: Available

## Features by API

### Market Data API (`readonly` scope)
- Option chains
- Real-time quotes
- Market data
- Unusual options activity
- Premium flow

### Trader API (`api` scope)
- User preferences
- Streaming credentials
- WebSocket streaming
- Live option contract monitor
- Time & sales data
- Level 1 options data

## Troubleshooting

### "Unauthorized: Token expired or invalid"
- Tokens expire after 30 minutes
- Refresh tokens last 7 days
- Re-authenticate when needed

### "One API disconnects when authenticating the other"
- Make sure you're using **TWO DIFFERENT** App Keys
- Each app must have its own unique credentials
- Check that callback URLs match exactly

### "Missing required scope"
- Market Data app must have `readonly` scope
- Trader app must have `api` scope
- Verify in Schwab Developer Portal

## Token Storage

Tokens are stored in Redis cache with the following keys:
- `schwab_market_access_token` - Market Data API access token
- `schwab_market_refresh_token` - Market Data API refresh token
- `schwab_trader_access_token` - Trader API access token
- `schwab_trader_refresh_token` - Trader API refresh token

## Important Notes

- Both applications can use the **same Schwab account**
- You don't need two separate Schwab accounts
- The applications are completely independent
- Authenticating one will NOT invalidate the other
- Each token is managed separately
