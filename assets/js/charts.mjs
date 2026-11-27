/**
 * Charts (Klxm\Fluid\Chart\Chart, from KLXM Studio) – loader, independent of the engine.
 * Finds <figure data-chart>, loads the engine (data-chart-engine, ES module) only when in view and calls
 *   render(el, spec, theme) → { update(theme, {animation, print}?), resize(), dispose() }
 * (optionally image() → image URL for print). theme = colors from CSS variables of the figure (--chart-1 … --chart-8, --chart-ink, --chart-muted, --chart-grid, --chart-bg)
 * and font – so charts follow kit, accent and light/dark. Content loaded later (e.g. cards of the overview) is detected
 * by a MutationObserver; switching light/dark redraws. Without JavaScript the table remains.
 */
const engines = new Map();
const live = new Set();
const drawn = new WeakMap();   // figure → promise of the engine instance

// Colors: set variables (kit, administration) win; otherwise derived from the surroundings – text color and background
// where the chart sits. So it fits every kit and every section, light or dark, without knowing its dark mode.
const rgb = (c) => (c.match(/[\d.]+/g) || []).map(Number);
const lum = ([r, g, b]) => { const f = (x) => { x /= 255; return x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4; }; return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b); };
const alpha = (c, a) => { const [r, g, b] = rgb(c); return `rgba(${r},${g},${b},${a})`; };
const background = (el) => {
  for (let n = el; n && n.nodeType === 1; n = n.parentElement) { const bg = getComputedStyle(n).backgroundColor; const p = rgb(bg); if (p.length >= 3 && (p.length < 4 || p[3] > 0.5)) return bg; }
  return 'rgb(255,255,255)';
};
const theme = (el) => {
  const cs = getComputedStyle(el);
  const v = (n) => cs.getPropertyValue(n).trim();
  const text = cs.color;
  const forced = v('--chart-dark');
  const dark = forced !== '' ? forced === '1' : lum(rgb(text)) > 0.4;   // light text = dark background
  const colors = [];
  for (let i = 1; i <= 8; i++) { const c = v((dark ? '--chart-d' : '--chart-') + i) || v('--chart-' + i); if (c) colors.push(c); }
  return { colors, dark, font: cs.fontFamily,
    ink: v('--chart-ink') || text, muted: v('--chart-muted') || alpha(text, 0.72), grid: v('--chart-grid') || alpha(text, 0.16), bg: v('--chart-bg') || background(el) };
};

function draw(fig) {
  if (!drawn.has(fig)) drawn.set(fig, drawNow(fig));
  return drawn.get(fig);
}

async function drawNow(fig) {
  fig.dataset.chartState = 'loading';
  try {
    const spec = JSON.parse(fig.querySelector('.cms-chart__data')?.textContent || '{}');
    const url = fig.dataset.chartEngine;
    if (!engines.has(url)) engines.set(url, import(url));
    const engine = await engines.get(url);
    const box = fig.querySelector('.cms-chart__canvas');
    const inst = engine.render(box, spec, theme(fig));
    live.add({ fig, inst });
    fig.dataset.chartState = 'ready';
    if ('ResizeObserver' in window) new ResizeObserver(() => { if (box.offsetWidth > 0) inst.resize?.(); }).observe(box);
    return inst;
  } catch (e) {
    fig.dataset.chartState = 'error';
    fig.querySelector('.cms-chart__table')?.setAttribute('open', '');   // engine missing: values as a table
    console.warn('[charts]', e);
    return null;
  }
}

const io = 'IntersectionObserver' in window
  ? new IntersectionObserver((list) => { for (const e of list) if (e.isIntersecting) { io.unobserve(e.target); draw(e.target); } }, { rootMargin: '200px' })
  : null;
const scan = (root = document) => root.querySelectorAll?.('[data-chart]:not([data-chart-state])').forEach((f) => {
  f.dataset.chartState = 'waiting';   // reserve space (without JavaScript only the table remains)
  io ? io.observe(f) : draw(f);
});

scan();
new MutationObserver((ms) => { for (const m of ms) for (const n of m.addedNodes) if (n.nodeType === 1) { if (n.matches('[data-chart]')) scan(n.parentNode); else scan(n); } })
  .observe(document.body, { childList: true, subtree: true });

// Light/dark or accent changed: pass the colors again
const refresh = () => { for (const { fig, inst } of live) if (fig.isConnected) inst.update?.(theme(fig)); };
matchMedia('(prefers-color-scheme: dark)').addEventListener?.('change', refresh);
new MutationObserver(refresh).observe(document.documentElement, { attributes: true, attributeFilter: ['data-theme', 'data-accent', 'class'] });
new MutationObserver(refresh).observe(document.body, { attributes: true, attributeFilter: ['class'] });   // REDAXO backend: theme as a class on body (rex-theme-dark)

// Printing: canvas charts don't reliably adapt to the paper width (the browser applies the print format only afterwards).
// So before printing, insert a sharp image per chart (engine: image(), optional) that scales with the width in print.
const toPrint = () => {
  for (const { fig, inst } of live) {
    if (!fig.isConnected || !inst.image) continue;
    let img = fig.querySelector(':scope > .cms-chart__print');
    const box = fig.querySelector('.cms-chart__canvas');
    if (img && (!box || box.offsetWidth < 40 || matchMedia('print').matches)) continue;   // already created, area hidden in print
    if (!img) { img = document.createElement('img'); img.className = 'cms-chart__print'; img.alt = ''; fig.querySelector('.cms-chart__canvas')?.after(img); }
    try {
      const t = theme(fig);
      inst.update?.(t, { animation: false, print: true });   // paper version: full legend
      img.src = inst.image();
      inst.update?.(t, { animation: false });
    } catch (e) { img.remove(); }
  }
};
const resizeAll = () => { for (const { fig, inst } of live) if (fig.isConnected) inst.resize?.(); };
addEventListener('beforeprint', toPrint);
addEventListener('afterprint', resizeAll);

// Image of a chart (e.g. for a PDF from the server): draws immediately if needed, light = light version even in dark mode
// (paper). → Promise<string|null> (data:image/png)
const snapshot = async (fig, { light = true } = {}) => {
  if (io) io.unobserve(fig);
  const inst = await draw(fig);
  if (!inst?.image) return null;
  const t = theme(fig);
  let paper = t;
  if (light && t.dark) {
    const cs = getComputedStyle(fig);
    const colors = [];
    for (let i = 1; i <= 8; i++) { const c = cs.getPropertyValue('--chart-' + i).trim(); if (c) colors.push(c); }
    paper = { ...t, dark: false, colors, ink: '#1d2125', muted: 'rgba(29,33,37,.72)', grid: 'rgba(29,33,37,.16)', bg: '#ffffff' };
  }
  inst.update?.(paper, { animation: false, print: true });   // fully drawn (not mid-animation), full legend
  try { return inst.image(); } finally { inst.update?.(t, { animation: false }); }
};

window.cmsCharts = { scan, resize: resizeAll, print: toPrint, snapshot };
