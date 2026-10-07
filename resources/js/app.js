import './bootstrap';
import Chart from 'chart.js/auto';

Chart.defaults.font.family = "'Inter', ui-sans-serif, system-ui, sans-serif";

const rm = (v) => 'RM' + Number(v).toLocaleString('en-MY', { maximumFractionDigits: 0 });

// --- Theme -------------------------------------------------------------------

const darkQuery = window.matchMedia('(prefers-color-scheme: dark)');

const readMode = () => {
    try { return localStorage.getItem('theme') || 'dark'; } catch { return 'dark'; }
};

const applyMode = (mode) => {
    const dark = mode === 'dark' || (mode === 'system' && darkQuery.matches);
    document.documentElement.classList.toggle('dark', dark);
    window.dispatchEvent(new CustomEvent('theme-changed'));
};

// Enable colour transitions only after the first paint, so page load doesn't animate.
requestAnimationFrame(() => document.documentElement.classList.add('theme-ready'));

darkQuery.addEventListener('change', () => {
    if (readMode() === 'system') applyMode('system');
});

// Reads the current theme's colour tokens from CSS.
const token = (name) => getComputedStyle(document.documentElement).getPropertyValue(`--${name}`).trim();

const alpha = (hex, a) => {
    const n = parseInt(hex.slice(1), 16);
    return `rgba(${(n >> 16) & 255},${(n >> 8) & 255},${n & 255},${a})`;
};

document.addEventListener('alpine:init', () => {
    window.Alpine.data('themeSwitcher', () => ({
        mode: readMode(),

        set(mode) {
            this.mode = mode;
            try { localStorage.setItem('theme', mode); } catch {}
            applyMode(mode);
        },
    }));

    window.Alpine.data('balanceChart', (initial) => {
        // Keep the Chart instance outside Alpine's reactive proxy.
        let chart = null;

        const datasets = (d) => [
            {
                key: 'balance', type: 'line', label: 'Closing balance', data: d.balance, yAxisID: 'y',
                fill: true, cubicInterpolationMode: 'monotone', pointRadius: 3, pointHoverRadius: 5, borderWidth: 2, order: 1,
            },
            {
                key: 'whatIf', type: 'line', label: 'What-if balance', data: d.whatIf, yAxisID: 'y',
                borderDash: [6, 4], fill: false, cubicInterpolationMode: 'monotone', pointRadius: 0, borderWidth: 2,
                hidden: !d.whatIf.length, order: 0,
            },
            {
                key: 'deposits', type: 'bar', label: 'Deposits', data: d.deposits, yAxisID: 'y1',
                borderRadius: 4, maxBarThickness: 18, order: 2,
            },
            {
                key: 'withdrawals', type: 'bar', label: 'Withdrawals', data: d.withdrawals, yAxisID: 'y1',
                borderRadius: 4, maxBarThickness: 18, order: 2,
            },
        ];

        // Applies the active theme's colours to the chart in place.
        const paint = () => {
            const [pos, alt, dep, neg] = ['pos', 'alt', 'dep', 'neg'].map(token);
            const subtle = token('subtle');
            const byKey = Object.fromEntries(chart.data.datasets.map((ds) => [ds.key, ds]));

            Object.assign(byKey.balance, { borderColor: pos, backgroundColor: alpha(pos, 0.1), pointBackgroundColor: pos, pointBorderColor: token('surface') });
            byKey.whatIf.borderColor = alt;
            byKey.deposits.backgroundColor = alpha(dep, 0.55);
            byKey.withdrawals.backgroundColor = alpha(neg, 0.6);

            const { scales, plugins } = chart.options;
            Object.assign(plugins.tooltip, {
                backgroundColor: token('surface'), borderColor: token('line-strong'),
                titleColor: token('fg'), bodyColor: token('fg-2'),
            });
            scales.x.ticks.color = subtle;
            scales.y.grid.color = token('line');
            for (const axis of [scales.y, scales.y1]) {
                axis.ticks.color = subtle;
                axis.title.color = subtle;
            }
        };

        // The deposits/withdrawals axis is noise when there is nothing to plot on it.
        const syncFlowAxis = () => {
            const visible = chart.data.datasets.some((ds) => ['deposits', 'withdrawals'].includes(ds.key) && !ds.hidden && ds.data.some((v) => v > 0));
            chart.options.scales.y1.display = visible;
        };

        return {
            show: { balance: true, deposits: true, withdrawals: true },

            init() {
                chart = new Chart(this.$refs.canvas, {
                    data: { labels: initial.labels, datasets: datasets(initial) },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                borderWidth: 1, padding: 12, cornerRadius: 10, boxPadding: 4,
                                callbacks: { label: (c) => ` ${c.dataset.label}: RM${Number(c.raw).toLocaleString('en-MY', { minimumFractionDigits: 2 })}` },
                            },
                        },
                        scales: {
                            x: { grid: { display: false }, border: { display: false }, ticks: {} },
                            y: {
                                position: 'left',
                                border: { display: false },
                                // Pad the range but never show negative balances.
                                afterDataLimits: (s) => {
                                    const pad = (s.max - s.min) * 0.1 || 1000;
                                    s.max += pad;
                                    s.min = Math.max(0, s.min - pad);
                                },
                                grid: {},
                                ticks: { callback: rm },
                                title: { display: true, text: 'Balance' },
                            },
                            y1: {
                                position: 'right', beginAtZero: true, grid: { display: false }, border: { display: false },
                                ticks: { callback: rm },
                                title: { display: true, text: 'Deposits / withdrawals' },
                            },
                        },
                    },
                });
                paint();
                syncFlowAxis();
                chart.update('none');
            },

            repaint() {
                if (!chart) return;
                paint();
                chart.update('none');
            },

            update(d) {
                if (!chart) return;
                chart.data.labels = d.labels;
                chart.data.datasets.forEach((ds) => {
                    ds.data = d[ds.key];
                    if (ds.key === 'whatIf') ds.hidden = !d.whatIf.length || !this.show.balance;
                    else ds.hidden = !this.show[ds.key];
                });
                syncFlowAxis();
                chart.update();
            },

            toggle(key) {
                this.show[key] = !this.show[key];
                chart.data.datasets.forEach((ds) => {
                    if (ds.key === key || (key === 'balance' && ds.key === 'whatIf' && ds.data.length)) {
                        ds.hidden = !this.show[key];
                    }
                });
                syncFlowAxis();
                chart.update();
            },

            destroy() {
                chart?.destroy();
            },
        };
    });
});
