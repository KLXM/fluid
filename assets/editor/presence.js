/**
 * Presence of the editorial team – standalone module from KLXM Studio (resources/js/presence.mjs, MIT); in fluid loaded
 * by Toolbar (PresenceApi). No dependency on the editor (editor.js).
 * Coupled only via data attributes and events:
 *
 *   [data-presence-here]   Bar: who is editing this object right now, who saved last, conflict warning.
 *       data-presence-endpoint  POST address (schema see developer manual → presence)
 *       data-presence-type / -id / -label / -url / -csrf   object and CSRF token
 *       data-presence-live      address of the live stream (Live, /fluid-live) – fast update on arrival/leaving
 *       data-presence-texts     JSON with the texts (see T below)
 *     Registers only in edit mode (html.cms-editing or data-presence-editing="1"), otherwise only looks (peek).
 *   [data-presence-list]   Overview (page tree, entry list): elements [data-id] get initials in [data-presence-slot].
 *       data-presence-endpoint (GET), data-presence-type, data-presence-prefix (e.g. „news:“ for entries)
 *   [data-presence-board]  List „Gerade aktiv“ (overview/dashboard): people with a link to the place.
 *
 * Conflict: if someone else has saved since opening, the module asks on the event „cms:before-save“
 * (detail.waitUntil) – no lock, „Trotzdem speichern“ is allowed. Event „cms:presence“ on document: {here, last, conflict, lock}.
 * Edit lock (optional, lock.on, EditLock): other owner → notice line „nur Ansehen“ (+ „Übernehmen“ for
 * administrators); free again → „Jetzt bearbeiten“; lock lost while editing → say so clearly once.
 * Confirmation via window.CMSAdmin.bar.ask or window.FluidEditor.ask ({title, body, ok, cancel, danger} → Promise<bool>), else confirm().
 * Roots: document and open shadow roots (e.g. the toolbar). Overviews/bars inserted later:
 * dispatch the event „cms:presence-scan“ on document.
 */
const d = document;
const BEAT = 15000, LIST = 30000;

const roots = () => [d, ...[...d.querySelectorAll('*')].filter((x) => x.shadowRoot).map((x) => x.shadowRoot)];
const all = (sel) => roots().flatMap((r) => [...r.querySelectorAll(sel)]);
const esc = (s) => String(s ?? '').replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
const fill = (s, v) => String(s || '').replace(/\{(\w+)\}/g, (_, k) => v[k] ?? '');
const T0 = {
  editing1: '{names} bearbeitet gerade', editingN: '{names} bearbeiten gerade', last: 'Zuletzt gespeichert: {name}, {ago}',
  now: 'gerade eben', min: 'vor {n} Min.', hour: 'vor {n} Std.', day: 'am {date}',
  conflict: '{name} hat inzwischen gespeichert', conflictShort: 'Inzwischen gespeichert', conflictTitle: 'Inzwischen von {name} gespeichert',
  conflictBody: 'Seit Sie diese Seite geöffnet haben, hat {name} gespeichert ({ago}). Wenn Sie jetzt speichern, kann das diese Änderungen überschreiben – der vorige Stand bleibt als Version erhalten.',
  conflictOk: 'Trotzdem speichern', cancel: 'Abbrechen', board: '{name} · {label}',
};
const ago = (t, T) => {
  const s = Math.max(0, Date.now() / 1000 - t);
  if (s < 60) return T.now;
  if (s < 3600) return fill(T.min, { n: Math.round(s / 60) });
  if (s < 86400) return fill(T.hour, { n: Math.round(s / 3600) });
  return fill(T.day, { date: new Date(t * 1000).toLocaleDateString() });
};
const badge = (p) => p.avatar
  ? `<img class="cms-presence__ava" src="${esc(p.avatar)}" alt="" width="24" height="24">`
  : `<span class="cms-presence__ava cms-presence__ava--ini" aria-hidden="true">${esc(p.initials || '?')}</span>`;
const names = (ps) => ps.map((p) => p.name).join(', ');

// ------------------------------------------------------------------ Live (Core\Live): ask right away on a new version
function live(url, token, onChange) {
  if (!url || !token || !('EventSource' in window)) return;
  let es = null, v = 0;
  const open = () => {
    es = new EventSource(url + '?t=' + encodeURIComponent(token));
    es.addEventListener('v', (e) => { let j = {}; try { j = JSON.parse(e.data); } catch { return; } const n = +j[token] || 0; if (n > v) { if (v) onChange(); v = n; } });
  };
  d.addEventListener('visibilitychange', () => { if (d.hidden) { es?.close(); es = null; } else if (!es) open(); });
  open();
}

// ------------------------------------------------------------------ Bar: this object
function here(el) {
  if (el._presence) return;
  el._presence = true;
  const T = { ...T0, ...JSON.parse(el.dataset.presenceTexts || '{}') };
  const body = (extra = {}) => JSON.stringify({ type: el.dataset.presenceType, id: el.dataset.presenceId, label: el.dataset.presenceLabel || '', url: el.dataset.presenceUrl || '', ...extra });
  // Locked (data-presence-locked): ?fluid_edit in the address does not count – the server only shows the view
  const editing = () => el.dataset.presenceEditing === '1' || d.documentElement.classList.contains('cms-editing')
    || (!el.dataset.presenceLocked && new URLSearchParams(location.search).has('fluid_edit'));
  let base = null, last = null, conflict = false, liveOn = false, timer = 0, ack = 0, lock = null, wasMine = false, toldTaken = false;
  const ask = (o) => { const f = window.CMSAdmin?.bar?.ask || window.FluidEditor?.ask; return f ? f(o) : Promise.resolve(window.confirm(o.title + '\n\n' + (o.body || ''))); };
  const editUrl = () => { const u = new URL(location.href); u.searchParams.set('fluid_edit', '1'); return u.toString(); };

  const render = (hereList) => {
    const others = hereList.filter((p) => !p.self);
    let h = '';
    const lockedHere = lock?.on && lock.by && !editing();
    if (lockedHere) {
      // Edit lock: initials + lock in the bar, text and „Übernehmen“ in the notice line below
      h = `<span class="cms-presence__avas">${badge(lock.by)}</span><span class="cms-presence__lock" aria-hidden="true">🔒</span><span class="cms-presence__sr">${esc(fill(T.locked, { name: lock.by.name }))}</span>`;
    } else if (others.length) {
      h += `<span class="cms-presence__avas">${others.slice(0, 4).map(badge).join('')}</span>`
        + `<span class="cms-presence__t">${esc(fill(others.length === 1 ? T.editing1 : T.editingN, { names: names(others) }))}</span>`;
    } else if (last && !last.self && last.name) {
      h += `<span class="cms-presence__t cms-presence__t--muted">${esc(fill(T.last, { name: last.name, ago: ago(last.at, T) }))}</span>`;
    }
    if (conflict) h = `<span class="cms-presence__warn" title="${esc(fill(T.conflict, { name: last.name || '?' }))}"><span aria-hidden="true">⚠</span><span class="cms-presence__sr">${esc(fill(T.conflict, { name: last.name || '?' }))}</span></span>` + h;
    el.innerHTML = h;
    el.hidden = !h;
    el.classList.toggle('is-conflict', conflict);
    el.classList.toggle('is-locked', !!lockedHere);
    el.title = [lockedHere ? fill(T.locked, { name: lock.by.name }) : '', conflict ? fill(T.conflict, { name: last.name || '?' }) : '', ...others.map((p) => p.name + ' – ' + ago(p.since, T))].filter(Boolean).join('\n');
    // Notice line below the bar: locked (with „Übernehmen“ for administrators) or free again
    if (lockedHere) {
      note(`<span aria-hidden="true">🔒</span><span>${esc(fill(T.lockedNotePage, { name: lock.by.name }))}</span>`
        + (lock.canTake ? `<button type="button" class="cms-lock-note__btn" data-presence-take>${esc(T.take)}</button>` : ''));
    } else if (lock?.on && !lock.by && el.dataset.presenceLocked && !editing()) {
      note(`<span>${esc(T.freeNote)}</span><a class="cms-lock-note__btn" href="${esc(editUrl())}">${esc(T.editNow)}</a>`, true);
    } else {
      note(null);
    }
  };

  // Notice line (in the same root as the bar, fixed below it)
  let noteEl = null;
  const note = (html, free = false) => {
    if (!html) { if (noteEl) noteEl.hidden = true; return; }
    if (!noteEl) {
      noteEl = d.createElement('div');
      noteEl.className = 'cms-lock-note';
      noteEl.setAttribute('role', 'status');
      noteEl.addEventListener('click', takeClick);
      const root = el.getRootNode();
      (root instanceof ShadowRoot ? root : d.body).append(noteEl);
    }
    noteEl.classList.toggle('cms-lock-note--free', free);
    noteEl.innerHTML = html;
    noteEl.hidden = false;
  };
  // Take over (administrators only – the server checks the permission): ask, take over the lock, switch to edit mode
  const takeClick = async (e) => {
    if (!e.target.closest('[data-presence-take]') || !lock?.by) return;
    const by = lock.by.name;
    if (!(await ask({ title: fill(T.takeAsk, { name: by }), body: fill(T.takeBody, { name: by }), ok: T.take, cancel: T.cancel, danger: true }))) return;
    try {
      await fetch(el.dataset.presenceEndpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': el.dataset.presenceCsrf || '' }, body: body({ take: 1 }) });
    } catch { /* reloading shows the state */ }
    location.href = editUrl();
  };

  const tick = async (peek = !editing()) => {
    clearTimeout(timer);
    try {
      const r = await fetch(el.dataset.presenceEndpoint, { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': el.dataset.presenceCsrf || '' }, body: body(peek ? { peek: 1 } : {}) });
      const j = r.ok ? await r.json() : null;
      if (!j || j.on === false) { el.hidden = true; return; }   // switched off – no further reports
      last = j.last || null;
      if (last) {
        if (base === null) base = last.at;                       // state when opening
        if (last.self) base = Math.max(base, last.at);           // own save is not a conflict
        conflict = !last.self && last.at > Math.max(base, ack);
      }
      lock = j.lock || null;
      // Lock lost (an administrator took over) while still editing here: say so clearly once
      if (lock?.on && editing()) {
        if (lock.mine) wasMine = true;
        else if (lock.by && wasMine && !toldTaken) {
          toldTaken = true;
          ask({ title: fill(T.taken, { name: lock.by.name }), body: T.takenBody, ok: T.ok, cancel: false });
        }
      }
      render(j.here || []);
      d.dispatchEvent(new CustomEvent('cms:presence', { detail: { here: j.here || [], last, conflict, lock } }));
      if (!liveOn && j.live) { liveOn = true; live(el.dataset.presenceLive, j.live, () => tick()); }
    } catch { /* next attempt */ }
    timer = setTimeout(() => tick(), BEAT);
  };

  // Conflict: ask before saving (no lock)
  d.addEventListener('cms:before-save', (e) => {
    if (!conflict || !last) return;
    const ask = window.CMSAdmin?.bar?.ask || window.FluidEditor?.ask;
    const o = { title: fill(T.conflictTitle, { name: last.name }), body: fill(T.conflictBody, { name: last.name, ago: ago(last.at, T) }), ok: T.conflictOk, cancel: T.cancel, danger: true };
    e.detail?.waitUntil?.((ask ? ask(o) : Promise.resolve(window.confirm(o.title + '\n\n' + o.body))).then((ok) => { if (ok) { ack = last.at; conflict = false; } return ok; }));
  });
  // Sign off when leaving (sendBeacon: form data with _csrf)
  addEventListener('pagehide', () => {
    if (!editing() || !navigator.sendBeacon) return;
    const f = new FormData();
    f.append('type', el.dataset.presenceType); f.append('id', el.dataset.presenceId); f.append('leave', '1'); f.append('_csrf', el.dataset.presenceCsrf || '');
    navigator.sendBeacon(el.dataset.presenceEndpoint, f);
  });
  // Mode change (view ↔ edit without reloading): report immediately
  d.addEventListener('cms:mode-change', () => tick());
  tick();
  setTimeout(() => tick(), 2000);   // Bar/mode may not be set yet at start
}

// ------------------------------------------------------------------ Overviews: initials on the entries, „Gerade aktiv“
let listTimer = 0, listLive = false;
async function lists() {
  clearTimeout(listTimer);
  const boxes = all('[data-presence-list]'), boards = all('[data-presence-board]');
  if (!boxes.length && !boards.length) return;
  const first = boxes[0] || boards[0];
  let j = null;
  try { const r = await fetch(first.dataset.presenceEndpoint, { credentials: 'same-origin', headers: { Accept: 'application/json' } }); j = r.ok ? await r.json() : null; } catch { /* later */ }
  if (j && j.on !== false) {
    const items = (j.items || []).filter((p) => !p.self);
    boxes.forEach((box) => {
      const T = { ...T0, ...JSON.parse(box.dataset.presenceTexts || '{}') };
      const type = box.dataset.presenceType, pre = box.dataset.presencePrefix || '';
      box.querySelectorAll('.cms-presence-mark').forEach((x) => x.remove());
      const by = new Map();
      items.filter((p) => p.type === type && p.id.startsWith(pre)).forEach((p) => { const k = p.id.slice(pre.length); by.set(k, [...(by.get(k) || []), p]); });
      by.forEach((ps, id) => {
        const it = box.querySelector(`[data-id="${CSS.escape(id)}"]`);
        if (!it) return;
        const slot = it.querySelector('[data-presence-slot]') || it;
        const m = d.createElement('span');
        m.className = 'cms-presence-mark';
        m.title = fill(ps.length === 1 ? T.editing1 : T.editingN, { names: names(ps) });
        m.innerHTML = ps.slice(0, 3).map(badge).join('') + `<span class="cms-presence-sr">${esc(m.title)}</span>`;
        slot.append(m);
      });
    });
    boards.forEach((b) => {
      const T = { ...T0, ...JSON.parse(b.dataset.presenceTexts || '{}') };
      b.innerHTML = items.map((p) => `<li class="cms-presence-board__i">${badge(p)}<span><b>${esc(p.name)}</b> · ${p.url ? `<a href="${esc(p.url)}">${esc(p.label || p.id)}</a>` : esc(p.label || p.id)} <small>${esc(ago(p.since, T))}</small></span></li>`).join('');
      b.closest('[data-presence-board-box]')?.toggleAttribute('hidden', !items.length);
    });
    if (!listLive && j.live) { listLive = true; live(first.dataset.presenceLive, j.live, lists); }
  }
  listTimer = setTimeout(lists, LIST);
}

const start = () => { all('[data-presence-here]').forEach(here); lists(); };
d.addEventListener('cms:presence-scan', start);
if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', start); else start();
