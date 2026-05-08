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
        // Get container width, ensure it's not too large
        const containerWidth = Math.min(this.container.clientWidth, window.innerWidth - 100);
        
        // Create chart with responsive width
        this.chart = createChart(this.container, {
            width: containerWidth,
            height: 400,
            layout: {
                background: { color: '#0a0b0d' },
                textColor: '#94a3b8',
            },
            grid: {
                vertLines: { color: '#1e293b' },
                horzLines: { color: '#1e293b' },
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

        // Handle resize - constrain width
        const resizeObserver = new ResizeObserver(entries => {
            if (entries.length === 0) return;
            const newWidth = Math.min(entries[0].contentRect.width, window.innerWidth - 100);
            this.chart.applyOptions({ width: newWidth });
        });
        resizeObserver.observe(this.container);

        // Fit content
        this.chart.timeScale().fitContent();
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

        const upperSeries = this.chart.addLineSeries({
            color: '#8b5cf6',
            lineWidth: 1,
            lineStyle: 2,
        });
        upperSeries.setData(upperData);

        const middleSeries = this.chart.addLineSeries({
            color: '#8b5cf6',
            lineWidth: 1,
        });
        middleSeries.setData(middleData);

        const lowerSeries = this.chart.addLineSeries({
            color: '#8b5cf6',
            lineWidth: 1,
            lineStyle: 2,
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
