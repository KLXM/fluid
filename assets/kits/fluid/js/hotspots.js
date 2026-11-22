/**
 * Interaktives Bild (Klxm\Fluid\Hotspots, aus KLXM Studio, MIT) auf der Website – nur auf Seiten mit dem Block.
 *  - Lage der Knöpfe genau (data-x/data-y in %, per CSSOM – die Klassen x0–x100/y0–y100 runden auf ganze Prozent).
 *  - Hinweisfenster: <button popovertarget> öffnet <div popover> (Esc, Klick daneben, Fokus zurück übernimmt der Browser);
 *    hier aria-expanded, Lage am Knopf (schmale Bildschirme: Blatt unten, nur CSS), Fläche hervorheben, Klick auf eine Fläche
 *    öffnet ihre Markierung. Rückfall ohne Popover-API: Klasse is-open, Esc, Klick daneben, Fokus zurück.
 */
(() => {
  const d = document;
  const native = Object.prototype.hasOwnProperty.call(HTMLElement.prototype, 'popover');
  const pin = (p) => d.querySelector('.cms-hs__pin[aria-controls="' + p.id + '"]');
  let open = null;

  d.querySelectorAll('[data-cms-hs] .cms-hs__pin').forEach((b) => {
    b.style.setProperty('left', b.dataset.x + '%');
    b.style.setProperty('top', b.dataset.y + '%');
  });

  const place = (p) => {
    const b = pin(p), st = p.style;
    if (!b) return;
    if (matchMedia('(max-width:36em)').matches) { st.left = st.top = st.right = st.bottom = st.margin = ''; return; }
    const r = b.getBoundingClientRect(), m = 12, gap = 10;
    const w = p.offsetWidth, h = p.offsetHeight, vw = d.documentElement.clientWidth;
    // Rechts neben dem Knopf, sonst links, sonst darunter; senkrecht am Knopf ausgerichtet
    let x = r.right + gap, y = r.top + r.height / 2 - h / 2;
    if (x + w > vw - m) x = r.left - gap - w;
    if (x < m) { x = Math.max(m, Math.min(r.left + r.width / 2 - w / 2, vw - w - m)); y = r.bottom + gap; if (y + h > innerHeight - m) y = r.top - gap - h; }
    st.right = st.bottom = 'auto';
    st.margin = '0';
    st.left = Math.round(Math.max(m, Math.min(x, vw - w - m))) + 'px';
    st.top = Math.round(Math.max(m, Math.min(y, innerHeight - h - m))) + 'px';
  };
  const area = (p, on) => {
    const b = pin(p), fig = b?.closest('[data-cms-hs]');
    fig?.querySelector('.cms-hs__area[data-n="' + b.dataset.n + '"]')?.classList.toggle('is-active', on);
  };
  const state = (p, on) => {
    pin(p)?.setAttribute('aria-expanded', on ? 'true' : 'false');
    area(p, on);
    if (on) { open = p; place(p); } else if (open === p) open = null;
  };
  const isPop = (el) => el?.classList?.contains('cms-hs__pop');

  if (native) {
    d.addEventListener('beforetoggle', (e) => { if (isPop(e.target) && e.newState === 'open') place(e.target); }, true);
    d.addEventListener('toggle', (e) => { if (isPop(e.target)) state(e.target, e.newState === 'open'); }, true);
  } else {
    const close = (p, focus) => { p.classList.remove('is-open'); state(p, false); if (focus) pin(p)?.focus(); };
    d.addEventListener('click', (e) => {
      const b = e.target.closest?.('.cms-hs__pin,.cms-hs__x');
      const p = b && d.getElementById(b.getAttribute('popovertarget'));
      if (p) {
        e.preventDefault();
        const on = b.classList.contains('cms-hs__pin') && !p.classList.contains('is-open');
        if (open && open !== p) close(open);
        if (on) { p.classList.add('is-open'); state(p, true); } else close(p, true);
      } else if (open && !e.target.closest?.('.cms-hs__pop')) close(open);
    });
    d.addEventListener('keydown', (e) => { if (e.key === 'Escape' && open) close(open, true); });
  }
  // Klick auf eine Fläche = Klick auf ihren Knopf (der Knopf bleibt der Weg für Tastatur und Screenreader)
  d.addEventListener('click', (e) => {
    const a = e.target.closest?.('.cms-hs__area');
    if (!a) return;
    a.closest('[data-cms-hs]')?.querySelector('.cms-hs__pin[data-n="' + a.dataset.n + '"]')?.click();
  });
  const again = () => open && place(open);
  addEventListener('resize', again);
  addEventListener('scroll', again, { passive: true });
})();
