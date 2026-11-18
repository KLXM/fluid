/*
 * Linkauswahl für REDAXO (Ersatz für Studio _links.js): Artikel der Struktur, Adresse, E-Mail, Telefon, Anker.
 * Artikel werden als href (aktuelle Adresse) + data-link="redaxo://ID" gespeichert – die Ausgabe aktualisiert die Adresse,
 * wenn der Artikel umzieht. Liste der Artikel: <script type="application/json" id="blocks-links"> (Links::json()).
 */
import { t } from './_i18n.js';
import { layerBox } from './_shadow.js';
import { icon } from './_icons.js';

const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

export function normalizeTel(v) {
  const raw = String(v || '').trim().replace(/^tel:/i, '');
  let n = raw.replace(/\(0\)/g, '').replace(/[^\d+]/g, '');
  if (n.startsWith('00')) n = '+' + n.slice(2);
  else if (n.startsWith('0')) n = '+49' + n.slice(1);
  else if (n && !n.startsWith('+')) n = '+49' + n;
  if (n.replace(/\D/g, '').length < 5) return { error: t('Bitte eine gültige Telefonnummer eingeben.') };
  return { href: 'tel:' + n, label: raw };
}

let articles = null;
function list() {
  if (articles === null) {
    try { articles = JSON.parse(document.getElementById('blocks-links')?.textContent || '[]'); } catch { articles = []; }
  }
  return articles;
}

/** Wert aus dem Eingabefeld → Link (Adresse, E-Mail, Telefon, Anker, redaxo://ID) */
function resolve(v) {
  v = v.trim();
  if (!v) return null;
  const art = /^(?:redaxo:\/\/)?(\d+)(#[\w-]+)?$/.exec(v);
  if (art) {
    const a = list().find((x) => String(x.id) === art[1]);
    return { href: (a ? a.url : '#') + (art[2] || ''), ref: 'redaxo://' + art[1] + (art[2] || ''), label: a ? a.name : v };
  }
  if (/^[\w.+-]+@[\w-]+(\.[\w-]+)+$/.test(v)) return { href: 'mailto:' + v, label: v };
  if (/^(mailto:|tel:|https?:\/\/|#|\/)/i.test(v)) return { href: v, label: v.replace(/^(mailto:|tel:)/i, '') };
  if (/^[+0(][\d\s/().-]{5,}$/.test(v)) return normalizeTel(v).href ? normalizeTel(v) : null;
  if (/^[\w-]+(\.[\w-]+)+([/?#].*)?$/.test(v)) return { href: 'https://' + v, label: v };
  return null;
}

let dlg = null;
function build() {
  if (dlg) return dlg;
  const pick = typeof window.rex?.pick === 'function' || !!window.FrontendPicker;
  dlg = document.createElement('dialog');
  dlg.className = 'adm-dialog lp';
  dlg.innerHTML = `
    <form method="dialog" class="lp__form">
      <div class="adm-dialog__head"><h2 data-lp-title>${esc(t('Link einfügen'))}</h2></div>
      <div class="f"><label for="blocks-lp-q" data-lp-qlabel>${esc(t('Adresse eingeben oder Seite suchen'))}</label>
        <input type="text" id="blocks-lp-q" autocomplete="off" placeholder="${esc(t('Seitenname, https://…, E-Mail, Telefon, #anker'))}"></div>
      <div class="lp__kinds" role="group" aria-label="${esc(t('Linkziel wählen'))}">
        ${pick ? `<button type="button" class="lp__kind" data-lp-kind="page">${icon('page', 'rte-icon')}<span>${esc(t('Seite'))}</span></button>` : ''}
        ${pick ? `<button type="button" class="lp__kind" data-lp-kind="media">${icon('file', 'rte-icon')}<span>${esc(t('Datei'))}</span></button>` : ''}
        <button type="button" class="lp__kind" data-lp-kind="record" aria-pressed="false">${icon('record', 'rte-icon')}<span>${esc(t('Datensatz'))}</span></button>
        <button type="button" class="lp__kind" data-lp-kind="anchor" aria-pressed="false">${icon('anchor', 'rte-icon')}<span>${esc(t('Anker'))}</span></button>
      </div>
      <div class="lp__list" role="listbox" data-lp-list hidden></div>
      <label class="f-check"><input type="checkbox" data-lp-tab> ${esc(t('In neuem Tab öffnen'))}</label>
      <p class="f-help" data-lp-error hidden></p>
      <div class="adm-row">
        <button type="button" class="adm-btn adm-btn--ghost" data-lp-remove hidden>${esc(t('Link entfernen'))}</button>
        <button type="button" class="adm-btn adm-btn--ghost" data-lp-cancel>${esc(t('Abbrechen'))}</button>
        <button type="submit" class="adm-btn adm-btn--primary">${esc(t('Übernehmen'))}</button>
      </div>
    </form>`;
  return dlg;
}

/** Angaben aus <script id="blocks-links">: Sprache, aktuelle Seite, API, Adresse des Medienordners */
const meta = () => document.getElementById('blocks-links')?.dataset ?? {};

async function api(params) {
  const base = meta().api;
  if (!base) return [];
  try {
    const res = await fetch(base + '&' + new URLSearchParams({ language: meta().language || '', ...params }), { credentials: 'same-origin' });
    return res.ok ? (await res.json()).items ?? [] : [];
  } catch {
    return [];
  }
}

const row = (attrs, ico, label, metaText, badge = '') => `<button type="button" role="option" class="lp__item" ${attrs}>${icon(ico, 'rte-icon lp__ico')}`
  + `<span class="lp__txt"><span class="lp__label">${esc(label)}</span>${metaText ? `<span class="lp__meta">${esc(metaText)}</span>` : ''}</span>${badge}</button>`;

export function openLinkPicker(opts = {}) {
  const d = build();
  layerBox().append(d);
  const q = d.querySelector('#blocks-lp-q');
  const box = d.querySelector('[data-lp-list]');
  const err = d.querySelector('[data-lp-error]');
  const qLabel = d.querySelector('[data-lp-qlabel]');
  const cur = opts.current || null;
  d.querySelector('[data-lp-title]').textContent = cur ? t('Link bearbeiten') : t('Link einfügen');
  d.querySelector('[data-lp-remove]').hidden = !cur;
  d.querySelector('[data-lp-tab]').checked = !!cur?.newTab;
  q.value = cur ? (cur.ref && /^redaxo:/.test(cur.ref) ? cur.ref : (cur.href || '')) : '';
  err.hidden = true;
  // mode: '' (Adresse/Seitenvorschläge beim Tippen), 'record', 'anchor'
  let mode = '';
  let timer = 0;
  let records = [];

  const setMode = (m) => {
    mode = m;
    d.querySelectorAll('[data-lp-kind="record"], [data-lp-kind="anchor"]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.lpKind === m)));
    qLabel.textContent = m === 'record' ? t('Datensatz suchen') : m === 'anchor' ? t('Sprungmarke auf dieser Seite') : t('Adresse eingeben oder Seite suchen');
    q.placeholder = m === 'record' ? t('Titel, z. B. einer News oder eines Termins …') : m === 'anchor' ? t('Filtern …') : t('Seitenname, https://…, E-Mail, Telefon, #anker');
    if (m) q.value = '';
    render();
    q.focus();
  };

  const render = async () => {
    const s = q.value.trim();
    const low = s.toLowerCase();
    let html = '';
    if (mode === 'record') {
      records = await api({ kind: 'records', q: s });
      html = records.map((r, i) => row(`data-lp-record="${i}"`, 'record', r.label, r.meta)).join('') || `<p class="lp__empty">${esc(t('Keine Datensätze gefunden.'))}</p>`;
    } else if (mode === 'anchor') {
      const items = (await api({ kind: 'anchors', article: meta().article || '' })).filter((a) => !low || a.anchor.includes(low) || a.label.toLowerCase().includes(low));
      html = items.map((a) => row(`data-lp-anchor="${esc(a.anchor)}"`, 'anchor', a.label, '#' + a.anchor)).join('') || `<p class="lp__empty">${esc(t('Auf dieser Seite gibt es keine Sprungmarken. Im Block unter „Abschnitt“ eine Sprungmarke setzen.'))}</p>`;
    } else if (s && !resolve(s)) {
      // Seitenvorschläge nur beim Tippen eines Namens (keine Adresse)
      html = list().filter((a) => a.name.toLowerCase().includes(low) || a.path.toLowerCase().includes(low)).slice(0, 8)
        .map((a) => row(`data-id="${a.id}"`, a.category ? 'folder' : 'page', a.name, a.path, a.online ? '' : `<span class="lp__badge">${esc(t('offline'))}</span>`)).join('');
    }
    box.innerHTML = html;
    box.hidden = !html;
  };

  return new Promise((done0) => {
    const tab = () => d.querySelector('[data-lp-tab]').checked;
    const done = (v) => {
      d.close();
      d.remove();
      q.removeEventListener('input', onInput);
      d.removeEventListener('click', onClick);
      d.removeEventListener('submit', onSubmit);
      d.removeEventListener('cancel', onCancel);
      done0(v);
    };
    const onInput = () => {
      clearTimeout(timer);
      timer = setTimeout(render, mode === 'record' ? 200 : 0);
    };
    const onClick = async (e) => {
      const it = e.target.closest('.lp__item');
      const kind = e.target.closest('[data-lp-kind]')?.dataset.lpKind;
      if (it?.dataset.id) {
        const a = list().find((x) => String(x.id) === it.dataset.id);
        done({ href: a.url, ref: 'redaxo://' + a.id, label: a.name, newTab: tab() });
      } else if (it?.dataset.lpRecord) {
        const r = records[Number(it.dataset.lpRecord)];
        done({ href: r.url || '#', ref: r.ref, label: r.label, newTab: tab() });
      } else if (it?.dataset.lpAnchor) {
        done({ href: '#' + it.dataset.lpAnchor, label: it.querySelector('.lp__label')?.textContent || it.dataset.lpAnchor, newTab: false });
      } else if (kind === 'record' || kind === 'anchor') {
        setMode(mode === kind ? '' : kind);
      } else if (kind === 'page' && window.BlocksPick) {
        // Linkmap: im Backend die des Cores (Overlay), auf der Website der FrontendPicker – Ergebnis redaxo://ID
        const res = await window.BlocksPick.pick('link', { language: Number(meta().language) || 0 });
        if (!res) return;
        const id = String(res.value).replace('redaxo://', '');
        const a = list().find((x) => String(x.id) === id);
        done({ href: a ? a.url : '#', ref: 'redaxo://' + id, label: a ? a.name : res.name, newTab: tab() });
      } else if (kind === 'media' && window.BlocksPick) {
        const res = await window.BlocksPick.pick('media', {});
        if (!res) return;
        const file = String(res.value);
        done({ href: (meta().media || '/media/') + encodeURIComponent(file), ref: 'media://' + file, label: res.name || file, newTab: tab() });
      } else if (e.target.closest('[data-lp-cancel]')) done(null);
      else if (e.target.closest('[data-lp-remove]')) done({ remove: true });
    };
    const onSubmit = (e) => {
      e.preventDefault();
      const first = box.querySelector('.lp__item');
      if (mode && first) { first.click(); return; }
      const r = resolve(q.value);
      if (!r || r.error) {
        // ein Seitenname mit genau einem Vorschlag: den nehmen
        if (first && box.querySelectorAll('.lp__item').length === 1) { first.click(); return; }
        err.textContent = r?.error || t('Bitte eine Adresse eingeben oder ein Ziel wählen.');
        err.hidden = false;
        return;
      }
      done({ ...r, newTab: tab() });
    };
    const onCancel = (e) => { e.preventDefault(); done(null); };
    q.addEventListener('input', onInput);
    d.addEventListener('click', onClick);
    d.addEventListener('submit', onSubmit);
    d.addEventListener('cancel', onCancel);
    d.showModal();
    setMode('');
    if (cur) q.value = cur.ref && /^redaxo:/.test(cur.ref) ? cur.ref : (cur.href || '');
    q.focus();
    q.select();
  });
}
