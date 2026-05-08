import './bootstrap';
// Alpine.js se carga desde CDN en el layout

// Import trading chart
import { TradingChart } from './trading-chart';

// Make it available globally
window.TradingChart = TradingChart;
