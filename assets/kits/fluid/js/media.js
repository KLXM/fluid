/*
 * Blocks „Bildergalerie“ (lightbox) and „Slider“ of the kit fluid (from KLXM Studio, media.mjs) – only on pages with these blocks.
 * Without JavaScript: gallery images link to the large version, the slider is a swipeable scroll-snap strip.
 *  Lightbox: <dialog> (modal), focus stays in the dialog, Esc closes, ←/→ and swiping page through, caption + counter.
 *            Motion only in kits with „Animationen“ (html.has-motion) and without „Bewegung reduzieren“: zoom from the
 *            thumbnail and back, sliding with crossfade when paging, swiping follows the finger (Web Animations, no inline styles in the HTML).
 *  Slider:  WAI-ARIA „Carousel“; autoplay only with a pause button, stops on mouse/focus, never with „Bewegung reduzieren“.
 */
(() => {
const d = document;
const rm = matchMedia('(prefers-reduced-motion: reduce)');
const $$ = (s, c = d) => [...c.querySelectorAll(s)];
const swipe = (el, fn) => {
  let x = null;
  el.addEventListener('pointerdown', e => { x = e.isPrimary ? e.clientX : null; });
  el.addEventListener('pointerup', e => { const dx = x === null ? 0 : e.clientX - x; x = null; if (Math.abs(dx) > 50) fn(dx < 0 ? 1 : -1); });
};

// ------------------------------------------------------------------ Lightbox
const motion = () => d.documentElement.classList.contains('has-motion') && !rm.matches;
const EASE = 'cubic-bezier(.2,.75,.15,1)';
const thumbOf = a => a?.querySelector('img');
let dx = 0, x0 = null;
// Zoom between thumbnail and large view (translation + scaling of the two rectangles)
function zoom(a, back) {
  const t = thumbOf(a);
  if (!motion() || !t) return null;
  const from = t.getBoundingClientRect(), to = img.getBoundingClientRect();
  if (!from.width || !to.width) return null;
  const tf = `translate(${from.left + from.width / 2 - (to.left + to.width / 2)}px, ${from.top + from.height / 2 - (to.top + to.height / 2)}px) scale(${from.width / to.width})`;
  const frames = [{ transform: tf, opacity: .6 }, { transform: 'none', opacity: 1 }];
  dlg.animate([{ backgroundColor: 'transparent' }, {}], { duration: back ? 280 : 420, easing: 'ease', direction: back ? 'reverse' : 'normal', fill: 'both' });
  return img.animate(back ? frames.reverse() : frames, { duration: back ? 300 : 460, easing: EASE, fill: back ? 'forwards' : 'none' });
}
// Paging: a copy of the old image slides out, the new one in
function slideOut(dir) {
  if (!motion() || !img.currentSrc) return;
  const r = img.getBoundingClientRect(), g = img.cloneNode();
  g.className = 'cms-lb__ghost';
  Object.assign(g.style, { left: r.left + 'px', top: r.top + 'px', width: r.width + 'px', height: r.height + 'px' });
  dlg.append(g);
  g.animate([{ transform: `translateX(${dx}px)`, opacity: 1 }, { transform: `translateX(${-dir * 18}%) scale(.96)`, opacity: 0 }], { duration: 420, easing: EASE, fill: 'forwards' }).finished.finally(() => g.remove());
}
function slideIn(dir) {
  if (!motion()) return;
  img.animate([{ transform: `translateX(${dir * 14}%) scale(1.02)`, opacity: 0, filter: 'blur(6px)' }, { transform: 'none', opacity: 1, filter: 'none' }], { duration: 520, easing: EASE });
}
function close() {
  const a = items[idx], r = thumbOf(a)?.getBoundingClientRect();
  const anim = r && r.bottom > 0 && r.top < innerHeight ? zoom(a, true) : null;
  if (anim) anim.finished.then(() => { dlg.close(); anim.cancel(); }, () => dlg.close()); else dlg.close();
  if (a) opener = a;
}
// Look up the dialog when needed: after the content is replaced (editing, event cms:content-replaced) it is a new element
let dlg = null, img, txt, cnt;
let items = [], idx = 0, opener;
function lb() {
  const el = d.querySelector('dialog.cms-lb');
  if (el && el !== dlg) {
    dlg = el; img = dlg.querySelector('img'); [txt, cnt] = dlg.querySelectorAll('figcaption span');
    dlg.addEventListener('click', e => {
      const b = e.target.closest('button');
      if (b) b.dataset.d ? show(idx + +b.dataset.d, +b.dataset.d) : close();
      else if (e.target === dlg || e.target.classList.contains('cms-lb__fig')) close();
    });
    dlg.addEventListener('keydown', e => {
      if (e.key === 'Escape' && motion()) { e.preventDefault(); close(); }
      else if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') { e.preventDefault(); const s = e.key === 'ArrowLeft' ? -1 : 1; if (items.length > 1) show(idx + s, s); }
      else if (e.key === 'Tab') { // Keep focus in the dialog
        const f = $$('button:not([hidden])', dlg), first = f[0], last = f[f.length - 1];
        if (e.shiftKey && d.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && d.activeElement === last) { e.preventDefault(); first.focus(); }
      }
    });
    // Swiping: the image follows the finger (with motion), from 50 px it pages, otherwise it springs back
    dlg.addEventListener('pointerdown', e => { x0 = e.isPrimary && e.pointerType !== 'mouse' ? e.clientX : null; dx = 0; });
    dlg.addEventListener('pointermove', e => { if (x0 === null || items.length < 2) return; dx = e.clientX - x0; if (motion()) img.style.transform = `translateX(${dx}px)`; });
    dlg.addEventListener('pointerup', () => {
      if (x0 === null) return;
      x0 = null;
      img.style.transform = '';
      if (Math.abs(dx) > 50 && items.length > 1) show(idx + (dx < 0 ? 1 : -1), dx < 0 ? 1 : -1);
      else if (dx && motion()) img.animate([{ transform: `translateX(${dx}px)` }, { transform: 'none' }], { duration: 300, easing: EASE });
      dx = 0;
    });
    dlg.addEventListener('close', () => opener?.focus());
  }
  $$('[data-cms-lightbox] a').forEach(a => a.setAttribute('aria-haspopup', 'dialog'));
  return dlg;
}
function show(i, dir = 0) {
  if (dir) slideOut(dir);
  idx = (i + items.length) % items.length;
  const a = items[idx];
  img.removeAttribute('srcset');
  img.src = a.href;
  if (a.dataset.srcset) img.srcset = a.dataset.srcset;
  img.alt = a.querySelector('img')?.alt || '';
  txt.textContent = a.dataset.caption || '';
  cnt.textContent = ' ' + dlg.dataset.count.replace('{n}', idx + 1).replace('{total}', items.length);
  if (dir) slideIn(dir);
  dx = 0;
}
lb();
d.addEventListener('click', e => {
  const a = e.target.closest?.('[data-cms-lightbox] a');
  if (!a || e.button || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey || !lb()) return;
  e.preventDefault();
  items = $$('a', a.closest('[data-cms-lightbox]'));
  opener = a;
  $$('[data-d]', dlg).forEach(b => { b.hidden = items.length < 2; });
  show(items.indexOf(a));
  dlg.showModal();
  if (motion()) (img.complete ? Promise.resolve() : img.decode().catch(() => {})).then(() => zoom(a));
});

// ------------------------------------------------------------------ Slider
function slider(el) {
  const c = JSON.parse(el.dataset.cmsSlider), track = el.querySelector('.cms-slider__track'), slides = [...track.children], n = slides.length;
  const dots = $$('.cms-slider__dot', el), arrows = $$('.cms-slider__arrow', el), play = el.querySelector('.cms-slider__play');
  const fade = el.classList.contains('cms-slider--fade'), nav = el.querySelector('.cms-slider__nav');
  const auto = c.autoplay && !rm.matches && n > 1;
  let i = 0, timer, paused = false, hold = false, sync;
  const set = (k, scroll = true) => {
    i = c.loop ? (k + n) % n : Math.max(0, Math.min(n - 1, k));
    slides.forEach((s, j) => { s.classList.toggle('is-current', j === i); s.inert = j !== i; });
    dots.forEach((b, j) => j === i ? b.setAttribute('aria-current', 'true') : b.removeAttribute('aria-current'));
    if (!c.loop && arrows.length === 2) { arrows[0].setAttribute('aria-disabled', i === 0); arrows[1].setAttribute('aria-disabled', i === n - 1); }
    if (scroll && !fade) track.scrollTo({ left: i * track.clientWidth, behavior: rm.matches ? 'auto' : 'smooth' });
  };
  const tick = () => {
    clearTimeout(timer);
    const run = auto && !paused && !hold && !d.hidden;
    track.setAttribute('aria-live', run ? 'off' : 'polite');
    if (run) timer = setTimeout(() => { set((i + 1) % n); tick(); }, c.autoplay);
  };
  el.classList.add('is-js');
  if (nav) nav.hidden = false;
  arrows.forEach(b => b.addEventListener('click', () => set(i + +b.dataset.dir)));
  dots.forEach((b, j) => b.addEventListener('click', () => set(j)));
  track.addEventListener('keydown', e => {
    const k = { ArrowLeft: i - 1, ArrowRight: i + 1, Home: 0, End: n - 1 }[e.key];
    if (k !== undefined && e.target === track) { e.preventDefault(); set(k); }
  });
  if (fade) swipe(track, dir => set(i + dir));
  else track.addEventListener('scroll', () => {
    clearTimeout(sync);
    sync = setTimeout(() => { const k = Math.round(track.scrollLeft / track.clientWidth); if (k !== i) set(k, false); }, 120);
  }, { passive: true });
  if (auto) {
    play.hidden = false;
    const label = play.querySelector('[data-label]');
    play.addEventListener('click', () => {
      paused = !paused;
      el.classList.toggle('is-paused', paused);
      label.textContent = paused ? c.play : c.pause;
      tick();
    });
    el.addEventListener('mouseenter', () => { hold = true; tick(); });
    el.addEventListener('mouseleave', () => { hold = el.contains(d.activeElement) && d.activeElement !== play; tick(); });
    el.addEventListener('focusin', e => { hold = e.target !== play; tick(); });
    el.addEventListener('focusout', e => { if (!el.contains(e.relatedTarget)) { hold = el.matches(':hover'); tick(); } });
    d.addEventListener('visibilitychange', tick);
  }
  set(0, false);
  tick();
}
// Also start new sliders after the content is replaced (editing on the website) – each only once
const scan = () => $$('[data-cms-slider]').forEach(el => { if (!el.cmsSlider) { el.cmsSlider = true; slider(el); } });
scan();
d.addEventListener('cms:content-replaced', () => { lb(); scan(); });
d.addEventListener('cms:block-preview', () => { lb(); scan(); });   // Page editor: block redrawn
})();
