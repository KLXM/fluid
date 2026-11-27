/*
 * Background video (hero „Vollflächig“ of the kits fluid and NativeCSS, video „Morphing“ in kit NativeCSS) with play/pause toggle
 * (fluid_video_toggle() in functions.php, css/video-toggle.css). Taken from KLXM Studio (resources/js/hero.mjs, MIT).
 *
 *   <div data-hero-video-box> <video data-hero-video muted loop playsinline preload="none"><source data-src="…" type="…"></video>
 *     <button data-hero-video-toggle hidden class="hx-vt is-paused"><svg …pause/><svg …play/><span data-l-pause="…" data-l-play="…">…</span></button>
 *
 * Plays by itself only when visible and without „Bewegung reduzieren“ (system or <html data-motion>), „Daten sparen“ and
 * contrast mode – then the toggle shows „abspielen“. The visitor's deliberate choice (click) applies until she clicks again.
 * While playing: pause icon + „anhalten“, paused: play icon + „abspielen“ (text and title; no aria-pressed).
 * Sources with data-src load only on first play. Without JavaScript: only the still image, the toggle stays hidden.
 */
(() => {
  const d = document, calm = matchMedia('(prefers-reduced-motion: reduce)'), lean = matchMedia('(prefers-reduced-data: reduce)'), forced = matchMedia('(forced-colors: active)');
  const still = () => { const m = d.documentElement.getAttribute('data-motion'); return m === 'reduce' || (m !== 'full' && calm.matches); };
  const toggle = (btn, playing) => {
    if (!btn) return;
    const l = btn.querySelector('[data-l-pause]');
    if (l) { l.textContent = playing ? l.dataset.lPause : l.dataset.lPlay; btn.title = l.textContent; }
    btn.classList.toggle('is-paused', !playing);
  };
  const load = (v) => {
    let swapped = false;
    v.querySelectorAll('source[data-src]').forEach((s) => { s.src = s.dataset.src; s.removeAttribute('data-src'); swapped = true; });
    v.preload = 'auto';
    if (swapped) v.load();
  };
  d.querySelectorAll('[data-hero-video]').forEach((v) => {
    const box = v.closest('[data-hero-video-box]') || v.parentElement;
    const btn = box.querySelector('[data-hero-video-toggle]');
    let choice = null, seen = false;
    const auto = () => !still() && !lean.matches && !forced.matches && !navigator.connection?.saveData;
    const want = () => choice ?? auto();
    const sync = () => {
      if (want() && seen && !d.hidden) {
        load(v);
        v.play().then(() => { box.classList.toggle('is-playing', !v.paused); toggle(btn, !v.paused); }).catch(() => toggle(btn, false));
      } else {
        if (!v.paused) v.pause();
        box.classList.remove('is-playing');
        toggle(btn, want());   // outside the viewport the intent stays (resumes as soon as visible)
      }
    };
    if (btn) {
      toggle(btn, false);
      btn.hidden = false;
      btn.addEventListener('click', () => { choice = !want(); seen = true; sync(); });
    }
    new IntersectionObserver(([e]) => { seen = e.isIntersecting; sync(); }, { rootMargin: '25% 0px' }).observe(box);
    d.addEventListener('visibilitychange', sync);
    calm.addEventListener('change', () => choice === null && sync());
    new MutationObserver(() => choice === null && sync()).observe(d.documentElement, { attributes: true, attributeFilter: ['data-motion'] });
  });
})();
