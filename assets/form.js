/*
 * klxm/fluid – Verhalten der Block-Formulare (FieldForm): Felder je Variante, Wiederholgruppen.
 * Gemeinsam für das Backend (Slice bearbeiten) und den Editor auf der Website (window.BlocksForm.init).
 */
(() => {
  'use strict';

  const toEl = (html) => {
    const t = document.createElement('template');
    t.innerHTML = html.trim();
    return t.content.firstElementChild;
  };

  function variantFields(form) {
    const sel = form.querySelector('[data-blocks-variant]');
    const v = sel ? sel.value : '';
    form.querySelectorAll('[data-variants]').forEach((f) => {
      f.hidden = v !== '' && !f.dataset.variants.split(' ').includes(v);
    });
  }

  // Wiederholgruppen: hinzufügen, entfernen, verschieben (Reihenfolge im Formular = Reihenfolge der Einträge)
  function initRepeater(rep, changed) {
    if (rep.dataset.blocksReady) return;
    rep.dataset.blocksReady = '1';
    const list = rep.querySelector('[data-blocks-list]');
    const tpl = rep.querySelector('[data-blocks-template]');
    const max = Number(rep.dataset.max || 0);
    const addBtn = rep.querySelector('[data-blocks-add]');
    let n = list.children.length;
    const sync = () => {
      if (addBtn) addBtn.disabled = max > 0 && list.children.length >= max;
    };
    addBtn?.addEventListener('click', () => {
      // neue Einträge: freie Zahl als Schlüssel; der Server nummeriert neu
      const item = toEl(tpl.innerHTML.replaceAll('__i__', String(1000 + n++)));
      list.append(item);
      item.open = true;
      // Backend: aufklappbare Einträge (Collapse des Cores) brauchen je Eintrag eigene IDs (auch verschachtelte)
      item.querySelectorAll('.collapse[id]').forEach((el) => {
        const id = `${el.id}-${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`;
        item.querySelectorAll(`[data-target="#${CSS.escape(el.id)}"]`).forEach((t) => { t.dataset.target = '#' + id; t.setAttribute('aria-controls', id); });
        el.id = id;
      });
      item.querySelectorAll('[data-blocks-repeater]').forEach((r) => initRepeater(r, changed));
      initPickers(item, changed);
      item.querySelector('input:not([type=hidden]),textarea,select')?.focus();
      sync();
      changed();
    });
    list.addEventListener('click', (e) => {
      const item = e.target.closest('[data-blocks-item]');
      if (!item || item.parentElement !== list) return;
      if (e.target.closest('[data-blocks-remove]')) item.remove();
      else if (e.target.closest('[data-blocks-up]')) item.previousElementSibling?.before(item);
      else if (e.target.closest('[data-blocks-down]')) item.nextElementSibling?.after(item);
      else return;
      e.preventDefault();
      sync();
      changed();
    });
    list.addEventListener('change', (e) => { if (e.target.matches('select')) e.target.dispatchEvent(new Event('input', { bubbles: true })); });
    list.addEventListener('input', (e) => {
      const item = e.target.closest('[data-blocks-item]');
      // Titel im Kopf: <summary> (Website) bzw. Kopf des Eintrags (Backend) – nicht die verschachtelter Einträge
      const title = item?.querySelector(':scope > summary [data-blocks-item-title], :scope > .fluid-rep-item__head [data-blocks-item-title]');
      // Auswahlfeld als Titel: Text der Option („Montag“ statt „1“)
      if (title && e.target.name?.endsWith(`[${title.dataset.field}]`) && e.target.value) title.textContent = e.target.selectedOptions?.[0]?.text || e.target.value;
    });
    sync();
  }

  // ------------------------------------------------------------------ Medien- und Seitenauswahl (ohne Popup-Fenster)
  // Backend: Medienpool und Linkmap des Cores als Overlay (rex.pick). Website: FrontendPicker (assets/picker, für jedes
  // AddOn nutzbar) mit derselben Antwort [{ value, label }].

  /**
   * Auswahl öffnen; Ergebnis: { value, name } oder null (ohne Auswahl geschlossen)
   * @param {'media'|'link'} kind
   * @param {{types?: string[], category?: number, language?: number, value?: string}} opts
   */
  async function pick(kind, opts = {}) {
    // Gastzugang ohne Backend (z. B. AddOn fluid_demo setzt window.fluidPickerOff): Hinweis statt Auswahl
    if (window.fluidPickerOff) {
      document.dispatchEvent(new CustomEvent('fluid:notice', { detail: String(window.fluidPickerOff) }));
      return null;
    }
    const open = typeof window.rex?.pick === 'function' ? window.rex.pick : window.FrontendPicker?.pick;
    if (!open) return null;
    const [item] = kind === 'media'
      ? await open('media', { types: opts.types?.length ? opts.types : undefined })
      : await open('article', {
        category: opts.category || undefined,
        language: opts.language || undefined,
        value: /^redaxo:\/\/\d+/.test(opts.value || '') ? opts.value : undefined,
      });
    if (!item) return null;
    return kind === 'media' ? { value: item.value, name: item.label } : { value: 'redaxo://' + item.value, name: item.label };
  }

  // ------------------------------------------------------------------ Bildeditor (Addon image_editor, optional)
  // Skript erst bei Bedarf laden. Der Editor auf der Website hängt Dialog und CSS in seine Shadow-DOM-Ebene
  // (Ereignis „blocks:image-editor“); ohne Abnehmer (Backend) kommt das CSS ins Dokument.
  let ieLoading = null;
  async function imageEditor(cfg) {
    if (!window.ImageEditor) {
      ieLoading ||= new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = String(cfg.js).replace(/&amp;/g, '&');
        s.onload = resolve;
        s.onerror = () => reject(new Error('Bildeditor konnte nicht geladen werden.'));
        document.head.append(s);
      });
      await ieLoading;
      const ev = new CustomEvent('blocks:image-editor', { detail: cfg, cancelable: true });
      if (document.dispatchEvent(ev)) {
        const l = document.createElement('link');
        l.rel = 'stylesheet';
        l.href = String(cfg.css).replace(/&amp;/g, '&');
        document.head.append(l);
      }
    }
    return window.ImageEditor;
  }

  function initPickers(scope, changed) {
    scope.querySelectorAll('[data-blocks-pick]').forEach((box) => {
      if (box.dataset.blocksReady) return;
      box.dataset.blocksReady = '1';
      const input = box.querySelector('input');
      const thumb = box.querySelector('[data-blocks-pick-thumb]');
      const label = box.querySelector('[data-blocks-pick-name]');
      // Bearbeiten/Anpassen/Entfernen nur mit gewählter Datei
      // hidden für die Website; im Backend setzt .rex-button display, dort blendet die Hilfsklasse des Cores aus
      const isVideo = () => /\.(mp4|m4v|mov|webm|mkv|ogv|avi)$/i.test(input.value);
      const tools = () => box.querySelectorAll('[data-blocks-pick-edit],[data-blocks-pick-adjust],[data-blocks-pick-clear],[data-blocks-pick-video]').forEach((b) => {
        const off = !input.value || (b.hasAttribute('data-blocks-pick-video') && !isVideo());
        b.hidden = off;
        b.classList.toggle('rex-u-hidden', off);
      });
      tools();
      input.addEventListener('input', tools);
      const set = (value, name = '') => {
        input.value = value;
        if (thumb) {
          const img = /\.(jpe?g|png|gif|webp|avif|svg)$/i.test(value);
          thumb.hidden = !value || !img;
          thumb.innerHTML = value && img ? `<img src="${box.dataset.preview.replace(/&amp;/g, '&').replace('__FILE__', encodeURIComponent(value))}" alt="">` : '';
        }
        if (label) label.textContent = /^redaxo:\/\//.test(value) ? name : '';
        input.dispatchEvent(new Event('input', { bubbles: true }));
        input.dispatchEvent(new Event('change', { bubbles: true }));
        changed();
      };
      box.querySelector('[data-blocks-pick-open]')?.addEventListener('click', async () => {
        const res = await pick(box.dataset.blocksPick === 'media' ? 'media' : 'link', {
          types: (box.dataset.types || '').split(',').filter(Boolean),
          category: Number(box.dataset.category) || 0,
          language: Number(box.dataset.language) || 0,
          value: input.value,
        });
        if (res) set(res.value, res.name);
      });
      box.querySelector('[data-blocks-pick-clear]')?.addEventListener('click', () => set(''));
      // AddOn video_editor: Video im Medienpool öffnen (neuer Tab, die Eingaben hier bleiben erhalten)
      box.querySelector('[data-blocks-pick-video]')?.addEventListener('click', () => {
        if (!isVideo() || !box.dataset.veEdit) return;
        window.open(box.dataset.veEdit.replace(/&amp;/g, '&').replace('__FILE__', encodeURIComponent(input.value)), '_blank', 'noopener');
      });
      box.querySelectorAll('[data-blocks-pick-edit],[data-blocks-pick-adjust]').forEach((btn) => btn.addEventListener('click', async () => {
        if (!input.value) return;
        try {
          const IE = await imageEditor({ api: box.dataset.ieApi, js: box.dataset.ieJs, css: box.dataset.ieCss });
          const opts = { file: input.value, api: box.dataset.ieApi };
          const item = btn.hasAttribute('data-blocks-pick-adjust') ? await IE.adjust(opts) : await IE.open({ ...opts, tool: 'crop' });
          if (item && thumb) {
            thumb.hidden = false;
            thumb.innerHTML = `<img src="${item.thumb.replace(/&amp;/g, '&')}" alt="">`;
          }
          if (item) { input.dispatchEvent(new Event('change', { bubbles: true })); changed(); }
        } catch (err) {
          input.setCustomValidity(err.message);
          input.reportValidity();
          setTimeout(() => input.setCustomValidity(''), 4000);
        }
      }));
      input.addEventListener('input', () => { if (label && !/^redaxo:\/\//.test(input.value)) label.textContent = ''; });
    });
  }

  // YForm: Feldauswahl auf die gewählte Tabelle einschränken (Optionsgruppen anderer Tabellen ausblenden)
  function yformFields(form) {
    const table = form.querySelector('[data-yform-table]')?.value || '';
    form.querySelectorAll('[data-yform-field]').forEach((sel) => {
      sel.querySelectorAll('optgroup').forEach((g) => {
        const on = g.dataset.table === table;
        g.hidden = !on;
        g.disabled = !on;
      });
      if (sel.value && !sel.value.startsWith(table + '.')) sel.value = '';
    });
  }


  // ------------------------------------------------------------------ Markierungen (Feld „Markierungen“, Block „Interaktives Bild“)
  // Marker-Editor über der Wiederholgruppe (FieldForm::hotspots, Format Klxm\Fluid\Hotspots v1 wie KLXM Studio): Bild aus dem
  // Bildfeld desselben Formulars, darüber alle Markierungen. Die Zeilen bleiben die Quelle der Werte – der Editor liest sie und
  // schreibt Lage und Form zurück (Ereignis input, damit die Vorschau folgt).
  //  - Klick ins Bild: neue Markierung an der Stelle (Umschalt + Klick: Eckpunkt an die gewählte Fläche anfügen).
  //  - Ziehen verschiebt Markierungen, Ecken und Eckpunkte; Tastatur: Pfeile 1 %, mit Umschalt 5 %, mit Alt 0,1 %, Entf entfernt
  //    (Markierung bzw. Eckpunkt), Eingabe öffnet die Zeile, Bild auf/ab wählt die vorige/nächste Markierung.
  //  - Lage per CSSOM (keine style-Attribute im Markup). Weitere Editoren (FLUID_HOTSPOT_EDITORS) im Dialog, postMessage.
  // Schnittstelle: window.BlocksHotspots.get(feld) → {v: 1, items}, .set(feld, daten), .normalize(daten).
  const HS_SHAPES = ['point', 'circle', 'rect', 'poly'];
  const r2 = (v) => Math.round(v * 100) / 100;
  const clamp = (v, a = 0, b = 100) => Math.max(a, Math.min(b, v));
  const num = (v, def = null) => { const x = parseFloat(String(v ?? '').replace(',', '.')); return Number.isFinite(x) ? x : def; };
  const escH = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const parsePoints = (raw) => {
    const list = Array.isArray(raw) ? raw : String(raw || '').trim().split(/[\s;]+/).filter(Boolean).map((p) => p.split(','));
    return list.map((p) => [num(Array.isArray(p) ? p[0] : p?.x), num(Array.isArray(p) ? p[1] : p?.y)])
      .filter(([x, y]) => x !== null && y !== null).slice(0, 64).map(([x, y]) => [r2(clamp(x)), r2(clamp(y))]);
  };
  const pointsText = (pts) => pts.map(([x, y]) => `${r2(x)},${r2(y)}`).join(' ');
  const anchor = (h) => {
    if (h.shape === 'rect') return [r2(h.x + h.w / 2), r2(h.y + h.h / 2)];
    if (h.shape === 'poly' && h.points?.length >= 3) {
      let a = 0, cx = 0, cy = 0;
      const p = h.points;
      for (let i = 0; i < p.length; i++) {
        const [x0, y0] = p[i], [x1, y1] = p[(i + 1) % p.length], f = x0 * y1 - x1 * y0;
        a += f; cx += (x0 + x1) * f; cy += (y0 + y1) * f;
      }
      if (Math.abs(a) < 1e-6) return [r2(p.reduce((s, q) => s + q[0], 0) / p.length), r2(p.reduce((s, q) => s + q[1], 0) / p.length)];
      return [r2(cx / (3 * a)), r2(cy / (3 * a))];
    }
    return [h.x, h.y];
  };
  // Format v1 bereinigen (gleiche Regeln wie Klxm\Fluid\Hotspots::normalize – der Server prüft beim Speichern erneut)
  function hsNormalize(raw) {
    let data = raw;
    if (typeof data === 'string') { try { data = JSON.parse(data); } catch { data = null; } }
    const list = Array.isArray(data) ? data : Array.isArray(data?.items) ? data.items : [];
    const items = [];
    for (const it of list) {
      if (!it || typeof it !== 'object' || items.length >= 60) continue;
      const shape = HS_SHAPES.includes(it.shape) ? it.shape : 'point';
      const h = { id: /^[A-Za-z0-9_-]{1,40}$/.test(it.id || '') ? it.id : '', shape };
      if (shape === 'poly') { h.points = parsePoints(it.points); if (h.points.length < 3) { delete h.points; h.shape = 'point'; } }
      if (h.shape !== 'poly') {
        const x = num(it.x), y = num(it.y);
        if (x === null || y === null) continue;
        h.x = r2(clamp(x)); h.y = r2(clamp(y));
      }
      if (h.shape === 'circle') h.r = r2(clamp(num(it.r, 5), 0.5));
      if (h.shape === 'rect') { h.w = r2(clamp(num(it.w, 10), 0.5, 100 - h.x)); h.h = r2(clamp(num(it.h, 10), 0.5, 100 - h.y)); }
      for (const k of ['title', 'text', 'link', 'link_label', 'term', 'icon']) h[k] = typeof it[k] === 'string' ? it[k] : (k === 'text' && typeof it.content === 'string' ? it.content : '');
      items.push(h);
    }
    return { v: 1, items };
  }
  const hsFields = new WeakMap();
  function initHotspots(scope, changed) {
    scope.querySelectorAll('[data-hotspots]').forEach((field) => { if (!hsFields.has(field)) hsFields.set(field, hsSetup(field, changed)); });
  }

  function hsSetup(field, changed) {
    let cfg = {};
    try { cfg = JSON.parse(field.dataset.hotspots || '{}'); } catch { /* leer */ }
    const rootNode = field.getRootNode();
    // Stylesheet einmal je Ebene (Dokument bzw. Shadow DOM des Editors)
    if (cfg.css && !rootNode.querySelector?.('link[data-hs-css]')) {
      const l = document.createElement('link');
      l.rel = 'stylesheet'; l.href = cfg.css; l.dataset.hsCss = '';
      (rootNode instanceof ShadowRoot ? rootNode : document.head).append(l);
    }
    const ed = field.querySelector('[data-hs-ed]'), stage = field.querySelector('[data-hs-stage]'), live = field.querySelector('[data-hs-live]');
    const bar = field.querySelector('[data-hs-bar]');
    const rep = field.querySelector('[data-blocks-repeater]'), list = rep.querySelector('[data-blocks-list]');
    const form = field.closest('form, [data-blocks-form]') || rootNode;
    ed.hidden = false;
    const rows = () => [...list.children].filter((r) => r.matches('[data-blocks-item]'));
    const inp = (row, k) => [...row.querySelectorAll('input, select, textarea')].find((el) => el.name?.endsWith(`[${k}]`) && el.closest('[data-blocks-item]') === row);
    let active = -1, writing = false;

    const read = (row) => {
      const v = (k) => inp(row, k)?.value ?? '';
      const shape = HS_SHAPES.includes(v('shape')) ? v('shape') : 'point';
      const h = { id: v('id'), shape, x: num(v('x')), y: num(v('y')), r: num(v('r')), w: num(v('w')), h: num(v('h')), title: v('title'),
        text: v('text'), link: v('link'), link_label: v('link_label'), term: v('term'), icon: v('icon') };
      if (shape === 'poly') h.points = parsePoints(v('points'));
      return h;
    };
    const geom = (h) => {
      const g = { ...h, x: h.x ?? 50, y: h.y ?? 50 };
      if (g.shape === 'circle') g.r = g.r ?? 6;
      if (g.shape === 'rect') { g.w = g.w ?? 15; g.h = g.h ?? 15; }
      if (g.shape === 'poly' && !(g.points?.length >= 3)) g.points = null;
      return g;
    };
    const write = (row, vals) => {
      writing = true;
      for (const [k, val] of Object.entries(vals)) {
        const el = inp(row, k);
        if (!el) continue;
        if (k === 'text') { const a = el.parentElement?.querySelector('.rte-area'); if (a) a.innerHTML = val; }
        el.value = typeof val === 'number' ? String(r2(val)) : val;
        el.dispatchEvent(new Event('input', { bubbles: true }));
      }
      writing = false;
      changed();
    };
    const writeGeom = (row, g) => {
      const vals = {};
      if (g.shape === 'poly') { vals.points = pointsText(g.points); [vals.x, vals.y] = anchor(g); } else { vals.x = g.x; vals.y = g.y; }
      if (g.shape === 'circle') vals.r = g.r;
      if (g.shape === 'rect') { vals.w = g.w; vals.h = g.h; }
      write(row, vals);
    };

    const imgInput = () => (cfg.image ? [...form.querySelectorAll('input')].find((el) => el.name === cfg.image) : null);
    const imgSrc = () => {
      const f = imgInput()?.value.trim();
      return f && /\.(jpe?g|png|gif|webp|avif|svg)$/i.test(f) ? String(cfg.src || '').replace('__FILE__', encodeURIComponent(f)) : '';
    };

    let frame = null;
    const at = (el, x, y) => { el.style.setProperty('left', x + '%'); el.style.setProperty('top', y + '%'); };
    const render = () => {
      const src = imgSrc();
      const all = rows().map((r) => geom(read(r)));
      if (!src) { stage.innerHTML = '<p class="hs-ed__empty">Zuerst ein Bild wählen.</p>'; frame = null; return; }
      const ae = rootNode.activeElement;
      const focusKey = ae && stage.contains(ae) ? ae.dataset?.key : null;
      if (active >= all.length) active = all.length - 1;
      let svg = '', pins = '', handles = '';
      const pos = [];
      all.forEach((g, i) => {
        const n = i + 1, on = i === active ? ' is-on' : '';
        if (g.shape === 'circle') svg += `<ellipse class="hs-ed__area${on}" data-i="${i}" cx="${g.x}" cy="${g.y}" rx="${g.r}" ry="${g.r}" data-ratio></ellipse>`;
        if (g.shape === 'rect') svg += `<rect class="hs-ed__area${on}" data-i="${i}" x="${g.x}" y="${g.y}" width="${g.w}" height="${g.h}"></rect>`;
        if (g.shape === 'poly' && g.points) svg += `<polygon class="hs-ed__area${on}" data-i="${i}" points="${pointsText(g.points)}"></polygon>`;
        const [ax, ay] = anchor(g.shape === 'poly' && !g.points ? { ...g, shape: 'point' } : g);
        const name = (g.title || g.term || '').trim();
        pins += `<button type="button" class="hs-ed__pin${on}${g.shape !== 'point' ? ' hs-ed__pin--area' : ''}" data-i="${i}" data-key="p${i}"`
          + ` aria-label="${escH(`Markierung ${n}${name ? ': ' + name : ''}, ${Math.round(ax)} % waagerecht, ${Math.round(ay)} % senkrecht`)}" aria-pressed="${i === active}">${n}</button>`;
        pos.push([`p${i}`, ax, ay]);
        if (i !== active) return;
        const hnd = (k, x, y, label) => { pos.push([`h${i}-${k}`, x, y]); return `<button type="button" class="hs-ed__h" data-i="${i}" data-h="${k}" data-key="h${i}-${k}" aria-label="${escH(label)}"></button>`; };
        if (g.shape === 'rect') handles += hnd('se', g.x + g.w, g.y + g.h, `Größe von Markierung ${n} (Ecke unten rechts)`);
        if (g.shape === 'circle') handles += hnd('r', clamp(g.x + g.r), g.y, `Radius von Markierung ${n}`);
        if (g.shape === 'poly' && g.points) g.points.forEach(([x, y], k) => { handles += hnd(String(k), x, y, `Eckpunkt ${k + 1} von Markierung ${n}`); });
      });
      stage.innerHTML = `<div class="hs-ed__frame" data-hs-frame><img class="hs-ed__img" src="${escH(src)}" alt="" draggable="false">`
        + `<svg class="hs-ed__svg" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">${svg}</svg>${pins}${handles}</div>`;
      frame = stage.querySelector('[data-hs-frame]');
      pos.forEach(([key, x, y]) => { const el = frame.querySelector(`[data-key="${key}"]`); if (el) at(el, x, y); });
      // Kreise: Radius in % der Breite → senkrecht im Verhältnis des Bildes
      const img = frame.querySelector('img');
      const ratio = () => frame.querySelectorAll('ellipse[data-ratio]').forEach((el) => {
        const k = img.naturalWidth && img.naturalHeight ? img.naturalWidth / img.naturalHeight : 1;
        el.setAttribute('ry', String(+el.getAttribute('rx') * k));
      });
      if (img.complete) ratio(); else img.addEventListener('load', ratio, { once: true });
      if (focusKey) frame.querySelector(`[data-key="${focusKey}"]`)?.focus({ preventScroll: true });
      rows().forEach((r, i) => r.classList.toggle('is-hs-active', i === active));
    };
    let queued = false;
    const later = () => { if (queued) return; queued = true; requestAnimationFrame(() => { queued = false; render(); }); };
    const say = (msg) => { live.textContent = ''; setTimeout(() => { live.textContent = msg; }, 30); };
    const select = (i) => { active = i; render(); frame?.querySelector(`[data-key="p${i}"]`)?.focus({ preventScroll: true }); };
    const openRow = (i) => {
      const row = rows()[i];
      if (!row) return;
      if (row.tagName === 'DETAILS') row.open = true;
      else { const tg = row.querySelector('[data-toggle="collapse"]'); if (tg?.getAttribute('aria-expanded') === 'false') tg.click(); }
      row.scrollIntoView({ block: 'nearest', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
      inp(row, 'title')?.focus({ preventScroll: true });
    };
    const addRow = (vals) => {
      if (cfg.max && rows().length >= cfg.max) { say(`Höchstens ${cfg.max} Markierungen.`); return null; }
      const btn = rep.querySelector(':scope > [data-blocks-add]');
      if (!btn) return null;
      const keep = rootNode.activeElement;
      btn.click();
      const row = rows().at(-1);
      if (row?.tagName === 'DETAILS') row.open = false;
      if (row && vals) write(row, vals);
      keep?.focus?.({ preventScroll: true });
      return row;
    };
    const pos = (e) => {
      const b = frame.getBoundingClientRect();
      return [r2(clamp(((e.clientX - b.left) / b.width) * 100)), r2(clamp(((e.clientY - b.top) / b.height) * 100))];
    };

    function move(g0, h, dx, dy, px, py) {
      const g = { ...g0, points: g0.points?.map((p) => [...p]) };
      if (h === null) {
        if (g.shape === 'poly' && g.points) {
          const xs = g.points.map((p) => p[0]), ys = g.points.map((p) => p[1]);
          const mx = clamp(dx, -Math.min(...xs), 100 - Math.max(...xs)), my = clamp(dy, -Math.min(...ys), 100 - Math.max(...ys));
          g.points = g.points.map(([x, y]) => [r2(x + mx), r2(y + my)]);
        } else if (g.shape === 'rect') { g.x = r2(clamp(g0.x + dx, 0, 100 - g.w)); g.y = r2(clamp(g0.y + dy, 0, 100 - g.h)); }
        else { g.x = r2(clamp(g0.x + dx)); g.y = r2(clamp(g0.y + dy)); }
      } else if (h === 'se') { g.w = r2(clamp((px ?? g0.x + g0.w + dx) - g.x, 0.5, 100 - g.x)); g.h = r2(clamp((py ?? g0.y + g0.h + dy) - g.y, 0.5, 100 - g.y)); }
      else if (h === 'r') { g.r = r2(clamp(Math.abs((px ?? g0.x + g0.r + dx) - g.x), 0.5)); }
      else if (g.points?.[+h]) { const [x, y] = g0.points[+h]; g.points[+h] = [r2(clamp(px ?? x + dx)), r2(clamp(py ?? y + dy))]; }
      return g;
    }

    let drag = null, dragEnd = 0;
    stage.addEventListener('pointerdown', (e) => {
      if (!frame || e.button !== 0) return;
      const el = e.target.closest('.hs-ed__pin,.hs-ed__h');
      if (!el) return;
      e.preventDefault();
      const i = +el.dataset.i, row = rows()[i];
      if (!row) return;
      if (active !== i) { active = i; render(); }
      (frame.querySelector(`[data-key="${el.dataset.key}"]`) || el).focus({ preventScroll: true });
      drag = { i, row, h: el.dataset.h ?? null, start: pos(e), g: geom(read(row)), moved: false, id: e.pointerId };
      stage.setPointerCapture?.(e.pointerId);
    });
    stage.addEventListener('pointermove', (e) => {
      if (!drag || e.pointerId !== drag.id) return;
      const [px, py] = pos(e), dx = px - drag.start[0], dy = py - drag.start[1];
      if (!drag.moved && Math.hypot(dx, dy) < 0.6) return;
      drag.moved = true;
      writeGeom(drag.row, move(drag.g, drag.h, dx, dy, drag.h === null ? undefined : px, drag.h === null ? undefined : py));
      render();
    });
    const end = (e) => {
      if (!drag || e.pointerId !== drag.id) return;
      const d = drag;
      drag = null;
      dragEnd = Date.now();
      stage.releasePointerCapture?.(e.pointerId);
      if (d.moved) { const [x, y] = anchor(geom(read(d.row))); say(`Markierung ${d.i + 1}: ${Math.round(x)} % waagerecht, ${Math.round(y)} % senkrecht`); }
      frame?.querySelector(`[data-key="${d.h === null ? 'p' + d.i : 'h' + d.i + '-' + d.h}"]`)?.focus({ preventScroll: true });
    };
    stage.addEventListener('pointerup', end);
    stage.addEventListener('pointercancel', end);

    stage.addEventListener('click', (e) => {
      if (!frame || e.target.closest('.hs-ed__pin,.hs-ed__h') || Date.now() - dragEnd < 400 || !e.target.closest('[data-hs-frame]')) return;
      const [x, y] = pos(e);
      const row = rows()[active], g = row ? geom(read(row)) : null;
      if (e.shiftKey && g?.shape === 'poly' && g.points) {
        writeGeom(row, { ...g, points: [...g.points, [x, y]] });
        render();
        say(`Eckpunkt ${g.points.length + 1} angefügt.`);
        return;
      }
      if (addRow({ shape: 'point', x, y })) {
        active = rows().length - 1;
        render();
        frame?.querySelector(`[data-key="p${active}"]`)?.focus({ preventScroll: true });
        say(`Markierung ${active + 1} gesetzt: ${Math.round(x)} % waagerecht, ${Math.round(y)} % senkrecht`);
      }
    });
    stage.addEventListener('focusin', (e) => {
      const el = e.target.closest?.('.hs-ed__pin');
      if (el && +el.dataset.i !== active && !drag) { active = +el.dataset.i; render(); }
    });
    stage.addEventListener('keydown', (e) => {
      const el = e.target.closest?.('.hs-ed__pin,.hs-ed__h');
      if (!el) return;
      const i = +el.dataset.i, row = rows()[i], h = el.dataset.h ?? null;
      if (!row) return;
      const step = e.shiftKey ? 5 : e.altKey ? 0.1 : 1;
      const dir = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] }[e.key];
      if (dir) {
        e.preventDefault();
        writeGeom(row, move(geom(read(row)), h, dir[0], dir[1]));
        render();
        const g2 = geom(read(row)), [x, y] = h !== null && g2.shape === 'poly' ? g2.points[+h] : anchor(g2);
        say(h === 'se' ? `${Math.round(g2.w)} % breit, ${Math.round(g2.h)} % hoch` : h === 'r' ? `Radius ${Math.round(g2.r)} %` : `${Math.round(x)} % waagerecht, ${Math.round(y)} % senkrecht`);
      } else if (e.key === 'Delete' || e.key === 'Backspace') {
        e.preventDefault();
        const g = geom(read(row));
        if (h !== null && g.shape === 'poly' && g.points) {
          if (g.points.length <= 3) { say('Eine Fläche braucht mindestens drei Eckpunkte.'); return; }
          g.points.splice(+h, 1);
          writeGeom(row, g);
          render();
          frame?.querySelector(`[data-key="p${i}"]`)?.focus();
          return;
        }
        row.querySelector('[data-blocks-remove]')?.click();
        active = Math.min(i, rows().length - 1);
        render();
        if (active >= 0) frame?.querySelector(`[data-key="p${active}"]`)?.focus();
        say(`Markierung ${i + 1} entfernt.`);
      } else if (e.key === 'Enter' && h === null) {
        e.preventDefault();
        openRow(i);
      } else if ((e.key === 'PageDown' || e.key === 'PageUp') && h === null) {
        e.preventDefault();
        const n = rows().length;
        if (n) select((i + (e.key === 'PageDown' ? 1 : n - 1)) % n);
      } else if (e.key === 'Escape' && active >= 0) {
        active = -1; render();
      }
    });

    rep.addEventListener('input', () => { if (!writing) later(); });
    rep.addEventListener('change', (e) => {
      const sel = e.target.closest('select');
      const row = sel?.closest('[data-blocks-item]');
      if (!row || !sel.name.endsWith('[shape]')) { later(); return; }
      // Form umgeschaltet: fehlende Maße aus der bisherigen Lage
      const g = read(row), x = g.x ?? 50, y = g.y ?? 50;
      if (g.shape === 'rect' && (g.w === null || g.h === null)) write(row, { x: clamp(x - 7.5, 0, 85), y: clamp(y - 7.5, 0, 85), w: 15, h: 15 });
      if (g.shape === 'circle' && g.r === null) write(row, { r: 6 });
      if (g.shape === 'poly' && (g.points?.length ?? 0) < 3) write(row, { points: pointsText([[x, clamp(y - 7)], [clamp(x + 7), clamp(y + 6)], [clamp(x - 7), clamp(y + 6)]]) });
      active = rows().indexOf(row);
      later();
    });
    rep.addEventListener('focusin', (e) => {
      const row = e.target.closest('[data-blocks-item]');
      const i = row ? rows().indexOf(row) : -1;
      if (i >= 0 && i !== active) { active = i; later(); }
    });
    new MutationObserver(later).observe(list, { childList: true });
    form.addEventListener?.('input', (e) => { if (e.target?.name === cfg.image) later(); });
    form.addEventListener?.('change', (e) => { if (e.target?.name === cfg.image) later(); });

    // Weitere Editoren (Extension Point FLUID_HOTSPOT_EDITORS) im eingebetteten Fenster – Protokoll wie KLXM Studio
    bar.innerHTML = (cfg.editors || []).map((x, k) => `<button type="button" class="blocks-btn" data-hs-editor="${k}"${x.title ? ` title="${escH(x.title)}"` : ''}>${escH(x.label)}</button>`).join('');
    bar.hidden = !(cfg.editors || []).length;
    bar.addEventListener('click', (e) => {
      const b = e.target.closest('[data-hs-editor]');
      const x = b && cfg.editors[+b.dataset.hsEditor];
      if (x) openEditor(x, b);
    });
    function openEditor(x, btn) {
      const u = new URL(x.url, location.origin);
      u.searchParams.set('format', 'hotspots');
      u.searchParams.set('embed', '1');
      const file = imgInput()?.value.trim() || '';
      if (file) u.searchParams.set('media', file);
      const dlg = document.createElement('dialog');
      dlg.className = 'hs-dlg';
      dlg.setAttribute('aria-label', x.label);
      dlg.innerHTML = `<iframe title="${escH(x.label)}" src="${escH(u.pathname + u.search)}"></iframe>`;
      field.append(dlg);
      const win = () => dlg.querySelector('iframe')?.contentWindow;
      const done = () => { removeEventListener('message', onMsg); if (dlg.open) dlg.close(); dlg.remove(); btn?.focus?.({ preventScroll: true }); };
      const onMsg = (ev) => {
        if (ev.origin !== location.origin || ev.source !== win() || ev.data?.source !== 'klxm-hotspot-editor') return;
        if (ev.data.type === 'ready') {
          const img = frame?.querySelector('img');
          win().postMessage({ source: 'klxm-hotspots', type: 'init', v: 1, data: get(),
            media: file ? { file, url: imgSrc(), width: img?.naturalWidth || 0, height: img?.naturalHeight || 0 } : null }, location.origin);
        } else if (ev.data.type === 'apply') {
          set(ev.data.data);
          say('Markierungen übernommen.');
          if (ev.data.close) done();
        } else if (ev.data.type === 'close') done();
      };
      addEventListener('message', onMsg);
      dlg.addEventListener('cancel', (e) => { e.preventDefault(); done(); });
      dlg.showModal();
      dlg.querySelector('iframe').focus();
    }

    function get() {
      return hsNormalize(rows().map((r) => { const h = read(r); return h.shape === 'poly' ? h : { ...h, points: undefined }; }));
    }
    function set(data) {
      const { items } = hsNormalize(data);
      rows().forEach((r) => r.remove());
      for (const h of items) {
        const vals = { title: h.title, shape: h.shape, text: h.text, link: h.link, link_label: h.link_label, term: h.term, icon: h.icon, id: h.id };
        if (h.shape === 'poly') { vals.points = pointsText(h.points); [vals.x, vals.y] = anchor(h); } else { vals.x = h.x; vals.y = h.y; }
        if (h.shape === 'circle') vals.r = h.r;
        if (h.shape === 'rect') { vals.w = h.w; vals.h = h.h; }
        addRow(vals);
      }
      active = -1;
      render();
      changed();
      field.dispatchEvent(new CustomEvent('fluid:hotspots', { bubbles: true, composed: true, detail: get() }));
    }

    render();
    return { get, set };
  }
  window.BlocksHotspots = {
    get: (field) => hsFields.get(field)?.get() ?? null,
    set: (field, data) => hsFields.get(field)?.set(data),
    normalize: hsNormalize,
  };

  // ------------------------------------------------------------------ Listen-Vorlagen (Feld „Vorlage“, Data\ListTemplates)
  // Kacheln der gewählten Tabelle zeigen; mit gewählter Vorlage die Felder ausblenden, die sie festlegt (data-keys).
  // „Anpassen (Vorlage lösen)“ übernimmt die Werte der Vorlage in die Felder. Verwalten (nur Website, mit Recht): Speichern,
  // Überschreiben, Umbenennen, Standard, Löschen über die API des Editors (window.FluidEditor.api).
  function ltplBox(form) { return form.querySelector('[data-ltpl]'); }
  function ltplValue(box) { return box.querySelector('input[type=radio]:checked')?.value || ''; }

  function ltplSync(form) {
    const box = ltplBox(form);
    if (!box) return;
    const table = form.querySelector('[data-yform-table]')?.value || '';
    box.querySelectorAll('.ltpl-tile[data-table]').forEach((t) => {
      const on = t.dataset.table === table;
      t.hidden = !on;
      const r = t.querySelector('input');
      if (!on && r.checked) { r.checked = false; box.querySelector('.ltpl-tile:not([data-table]) input').checked = true; }
    });
    const val = ltplValue(box);
    const keys = new Set(JSON.parse(box.dataset.keys || '[]'));
    form.querySelectorAll(':scope > .blocks-form__grid > [data-fname], :scope > [data-fname]').forEach((f) => {
      if (keys.has(f.dataset.fname)) f.toggleAttribute('data-ltpl-off', val !== '');
    });
    box.querySelector('[data-ltpl-detach]').hidden = val === '';
    const manage = box.querySelector('[data-ltpl-manage]');
    const cur = box.querySelector('input[type=radio]:checked')?.closest('.ltpl-tile');
    if (manage) manage.hidden = val === '' || !!cur?.hasAttribute('data-locked');
    if (manage && cur) manage.querySelector('[data-ltpl-op="default"]').textContent = cur.hasAttribute('data-default') ? 'Nicht mehr Standard' : 'Als Standard der Tabelle';
  }

  // Werte der Vorlage in die Felder schreiben (Anpassen) – Auswahl, Häkchen, Text
  function ltplDetach(form, box, changed) {
    const r = box.querySelector('input[type=radio]:checked');
    let opts = {};
    try { opts = JSON.parse(r?.dataset.options || '{}'); } catch { /* leer */ }
    const prefix = r?.name.replace(/\[template\]$/, '') || '';
    for (const [k, v] of Object.entries(opts)) {
      const el = form.querySelector(`[name="${CSS.escape(prefix + '[' + k + ']')}"]:not([type=hidden])`) || form.querySelector(`[name="${CSS.escape(prefix + '[' + k + ']')}"]`);
      if (!el) continue;
      if (el.type === 'checkbox') el.checked = !!v; else el.value = v ?? '';
    }
    box.querySelector('.ltpl-tile:not([data-table]) input').checked = true;
    variantFields(form);
    ltplSync(form);
    changed();
  }

  function initListTemplate(form, changed) {
    const box = ltplBox(form);
    if (!box || box.dataset.ready) return;
    box.dataset.ready = '1';
    box.addEventListener('change', () => ltplSync(form));
    box.addEventListener('click', async (e) => {
      if (e.target.closest('[data-ltpl-detach]')) { ltplDetach(form, box, changed); return; }
      const b = e.target.closest('[data-ltpl-op]');
      const api = window.FluidEditor?.api;
      if (!b || !api) return;
      const op = b.dataset.ltplOp;
      const radio = box.querySelector('input[type=radio]:checked');
      const fd = new FormData(form.closest('form') || undefined);
      if (!form.closest('form')) form.querySelectorAll('input[name],select[name],textarea[name]').forEach((el) => { if ((el.type !== 'radio' && el.type !== 'checkbox') || el.checked) fd.append(el.name, el.value); });
      fd.append('op', op);
      fd.append('block', box.dataset.block);
      fd.append('table', form.querySelector('[data-yform-table]')?.value || '');
      fd.append('key', radio?.value || '');
      fd.append('name_attr', radio?.name || '');
      if (op === 'save') {
        const n = box.querySelector('[data-ltpl-name]').value.trim();
        if (!n) { box.querySelector('[data-ltpl-name]').focus(); return; }
        fd.append('name', n);
      } else if (op === 'rename') {
        const cur = radio?.closest('.ltpl-tile')?.querySelector('.ltpl-tile__name')?.textContent || '';
        const n = await (window.FluidEditor.prompt ? window.FluidEditor.prompt('Neuer Name der Vorlage', cur) : Promise.resolve(window.prompt('Neuer Name der Vorlage', cur)));
        if (!n) return;
        fd.append('name', n);
      } else if (op === 'delete' || op === 'overwrite') {
        const ok = await window.FluidEditor.ask({ title: op === 'delete' ? 'Vorlage löschen?' : 'Vorlage überschreiben?',
          body: op === 'delete' ? 'Blöcke, die sie gewählt haben, zeigen danach ihre eigenen Einstellungen.' : 'Die Änderung gilt überall, wo die Vorlage gewählt ist.',
          ok: op === 'delete' ? 'Löschen' : 'Überschreiben', danger: op === 'delete' });
        if (!ok) return;
      }
      try {
        const res = await api('list_template', fd);
        const next = toEl(res.html);
        box.replaceWith(next);
        initListTemplate(form, changed);
        const sel = [...next.querySelectorAll('input[type=radio]')].find((x) => x.value === res.key && (!x.closest('[data-table]') || x.closest('[data-table]').dataset.table === (form.querySelector('[data-yform-table]')?.value || '')));
        (sel || next.querySelector('.ltpl-tile:not([data-table]) input')).checked = true;
        ltplSync(form);
        changed();
        document.dispatchEvent(new CustomEvent('fluid:notice', { detail: { text: res.message || 'Gespeichert.' } }));
      } catch (err) {
        document.dispatchEvent(new CustomEvent('fluid:notice', { detail: { text: err.message, error: true } }));
      }
    });
    ltplSync(form);
  }

  // Tabelle gewechselt: Standard-Vorlage der neuen Tabelle wählen (falls es eine gibt)
  function ltplTable(form) {
    const box = ltplBox(form);
    if (!box) return;
    const table = form.querySelector('[data-yform-table]')?.value || '';
    const std = box.querySelector(`.ltpl-tile[data-default][data-table="${CSS.escape(table)}"] input`);
    if (std) std.checked = true;
    ltplSync(form);
  }

  function init(scope, changed = () => {}) {
    initPickers(scope, changed);
    scope.querySelectorAll('[data-blocks-form]').forEach((form) => {
      variantFields(form);
      yformFields(form);
      if (!form.dataset.blocksReady) {
        form.dataset.blocksReady = '1';
        form.addEventListener('change', (e) => {
          if (e.target.matches('[data-blocks-variant]')) variantFields(form);
          if (e.target.matches('[data-yform-table]')) { yformFields(form); ltplTable(form); }
        });
      }
      initListTemplate(form, changed);
      form.querySelectorAll('[data-blocks-repeater]').forEach((r) => initRepeater(r, changed));
    });
    initHotspots(scope, changed);
  }

  window.BlocksForm = { init };
  window.BlocksPick = { pick, imageEditor };

  // Backend: neu eingefügtes Markup (Overlays, Wiederholgruppen …) meldet der Core mit „rex:ready“ am neuen Element
  document.addEventListener('rex:ready', (e) => init(e.target instanceof Element ? e.target : document));
  if (document.readyState !== 'loading') init(document);
  else document.addEventListener('DOMContentLoaded', () => init(document));
})();
