/*
 * Maps (fluid, from KLXM Studio): MapLibre GL + OpenFreeMap via the own proxy (/proxy/ofm/…).
 * MapLibre (~800 KB) is loaded only when a map comes into view.
 * Only after a click on „Karte anzeigen“: with „Datensparen“ (Save-Data) and in two-click mode (data-click, Core\Maps).
 * Kits with their own loader import this module only on click and set data-go before – these maps start immediately.
 * 3D (cfg.view3d, Core\Maps): buildings with height (fill-extrusion from render_height of the OpenMapTiles tiles), after
 * loading a smooth camera move to pitch 55° / bearing −20° – instantly with „Bewegung reduzieren“. Controls with
 * compass/pitch; keyboard: arrows pan, +/− zoom, Shift + arrows rotate/tilt (MapLibre).
 */
let lib = null;

function loadLib(cfg) {
  if (lib) return lib;
  if (!document.querySelector('link[data-maplibre-css]')) {
    const l = document.createElement('link');
    l.rel = 'stylesheet';
    l.href = cfg.css;
    l.dataset.maplibreCss = '';
    document.head.append(l);
  }
  lib = import(cfg.vendor + 'maplibre-gl.mjs');
  return lib;
}

async function init(el) {
  if (el.dataset.ready) return;
  el.dataset.ready = '1';
  const cfg = JSON.parse(el.dataset.cmsMap);
  const canvas = el.querySelector('.cms-map__canvas');
  el.classList.add('is-loading');
  try {
    const ml = await loadLib(cfg);
    const reduce = matchMedia('(prefers-reduced-motion: reduce)').matches;
    // Dark style only if the theme supports dark mode and the device asks for it
    const themeDark = /dark/.test(document.querySelector('meta[name="color-scheme"]')?.content || '');
    const mq = matchMedia('(prefers-color-scheme: dark)');
    const styleFor = () => (themeDark && mq.matches && cfg.styleDark ? cfg.styleDark : cfg.style);
    const map = new ml.Map({
      container: canvas,
      style: styleFor(),
      center: cfg.center,
      zoom: cfg.zoom,
      maxBounds: [[cfg.bounds[0], cfg.bounds[1]], [cfg.bounds[2], cfg.bounds[3]]],
      maxZoom: 18,
      cooperativeGestures: true,
      attributionControl: { compact: true },
      locale: cfg.i18n,
      fadeDuration: reduce ? 0 : 300,
      ...(cfg.view3d ? { maxPitch: 60, ...(reduce ? { pitch: 55, bearing: -20 } : {}) } : {}),
    });
    map.addControl(new ml.NavigationControl(cfg.view3d ? { showCompass: true, visualizePitch: true } : { showCompass: false }), 'top-right');
    if (cfg.view3d) map.on('style.load', () => add3d(map, reduce));
    const pin = document.createElement('div');
    pin.className = 'cms-map__marker';
    pin.setAttribute('role', 'img');
    pin.setAttribute('aria-label', cfg.label || 'Standort');
    new ml.Marker({ element: pin, anchor: 'bottom' }).setLngLat(cfg.center).addTo(map);
    map.once('load', () => el.classList.replace('is-loading', 'is-ready'));
    if (themeDark) mq.addEventListener('change', () => map.setStyle(styleFor()));
    map.on('error', e => console.warn('Karte:', e?.error?.message || e));
  } catch (e) {
    el.classList.remove('is-loading');
    el.classList.add('is-failed');
    delete el.dataset.ready;
    console.warn('Karte konnte nicht geladen werden', e);
  }
}

// Buildings as solids (height from render_height), hide the style's flat buildings; tilt the camera smoothly once
let tilted = false;
function add3d(map, reduce) {
  const style = map.getStyle();
  if (!style.sources.openmaptiles) return;
  const flat = style.layers.filter(l => l['source-layer'] === 'building' && l.type === 'fill');
  if (!style.layers.some(l => l.type === 'fill-extrusion')) {
    const color = flat[0] ? map.getPaintProperty(flat[0].id, 'fill-color') : null;
    map.addLayer({
      id: 'cms-building-3d', type: 'fill-extrusion', source: 'openmaptiles', 'source-layer': 'building', minzoom: 14,
      filter: ['!=', ['get', 'hide_3d'], true],
      paint: {
        'fill-extrusion-color': typeof color === 'string' ? color : '#d9d4ce',
        'fill-extrusion-height': ['interpolate', ['linear'], ['zoom'], 14, 0, 15.5, ['coalesce', ['get', 'render_height'], 6]],
        'fill-extrusion-base': ['coalesce', ['get', 'render_min_height'], 0],
        'fill-extrusion-opacity': 0.85,
      },
    }, style.layers.find(l => l.type === 'symbol')?.id);
    flat.forEach(l => map.setLayoutProperty(l.id, 'visibility', 'none'));
  }
  if (!tilted && !reduce) map.easeTo({ pitch: 55, bearing: -20, zoom: map.getZoom() + 0.3, duration: 2400 });
  tilted = true;
}

const saveData = navigator.connection?.saveData === true;
const start = el => { el.classList.add('is-started'); init(el); };
const io = 'IntersectionObserver' in window && new IntersectionObserver(entries => {
  for (const en of entries) if (en.isIntersecting) { io.unobserve(en.target); init(en.target); }
}, { rootMargin: '300px 0px' });
// Find maps – on load and after blocks are re-rendered in the editor (event fluid:rendered)
function scan() {
  for (const el of document.querySelectorAll('[data-cms-map]:not([data-ready]):not([data-seen])')) {
    el.dataset.seen = '1';
    if (el.dataset.go) { start(el); continue; }
    if (saveData || el.dataset.click !== undefined || !io) {
      const b = el.querySelector('[data-cms-map-load]');
      if (b && !b.dataset.bound) { b.dataset.bound = '1'; b.hidden = false; b.addEventListener('click', () => start(el), { once: true }); }
    } else io.observe(el);
  }
}
scan();
document.addEventListener('fluid:rendered', scan);
