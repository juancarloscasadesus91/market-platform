import { createChart } from 'lightweight-charts';
import { SMA, EMA, RSI, MACD, BollingerBands } from 'technicalindicators';

export class TradingChart {
    constructor(containerId, data) {
        this.container = document.getElementById(containerId);
        this.data = data;
        this.indicators = {
            sma: null,
            ema: null,
            rsi: null,
            macd: null,
            bb: null
        };

        this.init();
    }

    init() {
        const etFmt = ts => new Date(ts * 1000).toLocaleTimeString('en-US', {
            timeZone: 'America/New_York', hour: '2-digit', minute: '2-digit', hour12: false,
        });
        const etTickFmt = (ts, tickType) => {
            const d = new Date(ts * 1000);
            const opts = { timeZone: 'America/New_York' };
            // TickMarkType: 0=Year, 1=Month, 2=DayOfMonth, 3=Time, 4=TimeWithSeconds
            if (tickType <= 1) return d.toLocaleDateString('en-US', { ...opts, year: 'numeric', month: 'short' });
            if (tickType === 2) return d.toLocaleDateString('en-US', { ...opts, month: 'short', day: 'numeric' });
            return d.toLocaleTimeString('en-US', { ...opts, hour: '2-digit', minute: '2-digit', hour12: false });
        };

        this.chart = createChart(this.container, {
            autoSize: true,
            localization: {
                timeFormatter: etFmt,
            },
            layout: {
                background: { color: '#0a0b0d' },
                textColor: '#94a3b8',
            },
            grid: {
                vertLines: { color: '#1e293b' },
                horzLines: { visible: false },
            },
            crosshair: {
                mode: 1,
                vertLine: {
                    color: '#475569',
                    width: 1,
                    style: 2,
                },
                horzLine: {
                    color: '#475569',
                    width: 1,
                    style: 2,
                },
            },
            rightPriceScale: {
                borderColor: '#334155',
            },
            timeScale: {
                borderColor: '#334155',
                timeVisible: true,
                secondsVisible: false,
                barSpacing: 3,
                minBarSpacing: 0.5,
                tickMarkFormatter: etTickFmt,
            },
        });

        // Add candlestick series
        this.candleSeries = this.chart.addCandlestickSeries({
            upColor: '#10b981',
            downColor: '#ef4444',
            borderUpColor: '#10b981',
            borderDownColor: '#ef4444',
            wickUpColor: '#10b981',
            wickDownColor: '#ef4444',
            priceLineVisible: false,
            lastValueVisible: false,
        });

        this.candleSeries.setData(this.data);

        // Add volume series
        this.volumeSeries = this.chart.addHistogramSeries({
            color: '#475569',
            priceFormat: {
                type: 'volume',
            },
            priceScaleId: '',
            scaleMargins: {
                top: 0.8,
                bottom: 0,
            },
        });

        const volumeData = this.data.map(d => ({
            time: d.time,
            value: d.volume || Math.random() * 1000000,
            color: d.close > d.open ? '#10b98150' : '#ef444450'
        }));

        this.volumeSeries.setData(volumeData);

        // Fit content
        this.chart.timeScale().fitContent();
    }

    destroy() {
        try { this.chart.remove(); } catch (e) { /* already disposed */ }
    }

    addSMA(period = 20) {
        const closes = this.data.map(d => d.close);
        const smaValues = SMA.calculate({ period, values: closes });

        const smaData = smaValues.map((value, index) => ({
            time: this.data[index + period - 1].time,
            value: value
        }));

        if (this.indicators.sma) {
            this.chart.removeSeries(this.indicators.sma);
        }

        this.indicators.sma = this.chart.addLineSeries({
            color: '#3b82f6',
            lineWidth: 2,
            title: `SMA(${period})`,
        });

        this.indicators.sma.setData(smaData);
    }

    addEMAs(configs = []) {
        if (!this.indicators.emaLines) this.indicators.emaLines = [];
        this.indicators.emaLines.forEach(s => { try { this.chart.removeSeries(s); } catch(e){} });
        this.indicators.emaLines = [];

        configs.forEach(({ period, color, title }) => {
            if (this.data.length < 2) return;
            const k = 2 / (period + 1);
            let ema = this.data[0].close;
            const lineData = this.data.map(d => {
                ema = k * d.close + (1 - k) * ema;
                return { time: d.time, value: ema };
            });
            const series = this.chart.addLineSeries({
                color,
                lineWidth: 2,
                title: title || `EMA${period}`,
                priceLineVisible: false,
                lastValueVisible: true,
                crosshairMarkerVisible: false,
            });
            series.setData(lineData);
            this.indicators.emaLines.push(series);
        });
    }

    toggleVolume(visible) {
        this.volumeSeries.applyOptions({ visible });
    }

    toggleBB(visible) {
        if (this.indicators.bb) {
            this.indicators.bb.forEach(s => s.applyOptions({ visible }));
        }
    }

    toggleEMAs(visible) {
        if (this.indicators.emaLines) {
            this.indicators.emaLines.forEach(s => s.applyOptions({ visible }));
        }
    }

    addEMA(period = 20) {
        const closes = this.data.map(d => d.close);
        const emaValues = EMA.calculate({ period, values: closes });

        const emaData = emaValues.map((value, index) => ({
            time: this.data[index + period - 1].time,
            value: value
        }));

        if (this.indicators.ema) {
            this.chart.removeSeries(this.indicators.ema);
        }

        this.indicators.ema = this.chart.addLineSeries({
            color: '#f59e0b',
            lineWidth: 2,
            title: `EMA(${period})`,
        });

        this.indicators.ema.setData(emaData);
    }

    addBollingerBands(period = 20, stdDev = 2) {
        const closes = this.data.map(d => d.close);
        const bbValues = BollingerBands.calculate({
            period,
            values: closes,
            stdDev
        });

        const upperData = bbValues.map((value, index) => ({
            time: this.data[index + period - 1].time,
            value: value.upper
        }));

        const middleData = bbValues.map((value, index) => ({
            time: this.data[index + period - 1].time,
            value: value.middle
        }));

        const lowerData = bbValues.map((value, index) => ({
            time: this.data[index + period - 1].time,
            value: value.lower
        }));

        if (this.indicators.bb) {
            this.indicators.bb.forEach(series => this.chart.removeSeries(series));
        }

        const bbOpts = { lastValueVisible: false, priceLineVisible: false, crosshairMarkerVisible: false };

        const upperSeries = this.chart.addLineSeries({
            color: '#8b5cf6', lineWidth: 1, lineStyle: 2, ...bbOpts,
        });
        upperSeries.setData(upperData);

        const middleSeries = this.chart.addLineSeries({
            color: '#8b5cf6', lineWidth: 1, ...bbOpts,
        });
        middleSeries.setData(middleData);

        const lowerSeries = this.chart.addLineSeries({
            color: '#8b5cf6', lineWidth: 1, lineStyle: 2, ...bbOpts,
        });
        lowerSeries.setData(lowerData);

        this.indicators.bb = [upperSeries, middleSeries, lowerSeries];
    }

    removeSMA() {
        if (this.indicators.sma) {
            this.chart.removeSeries(this.indicators.sma);
            this.indicators.sma = null;
        }
    }

    removeEMA() {
        if (this.indicators.ema) {
            this.chart.removeSeries(this.indicators.ema);
            this.indicators.ema = null;
        }
    }

    removeBB() {
        if (this.indicators.bb) {
            this.indicators.bb.forEach(series => this.chart.removeSeries(series));
            this.indicators.bb = null;
        }
    }

    setTimeframe(days) {
        const newData = this.generateData(days);
        this.data = newData;
        this.candleSeries.setData(newData);

        const volumeData = newData.map(d => ({
            time: d.time,
            value: d.volume || Math.random() * 1000000,
            color: d.close > d.open ? '#10b98150' : '#ef444450'
        }));
        this.volumeSeries.setData(volumeData);

        this.chart.timeScale().fitContent();
    }

    generateData(days) {
        // This will be replaced with real data from API
        return this.data.slice(-days);
    }
}
