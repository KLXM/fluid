/*
 * Two-click solution for external videos (core, Core\Embeds::script – loads only on pages with video, not in edit mode).
 * Markup: core fragment app/Views/fragments/video-embed.php (.vembed[data-embed]). The player (iframe) loads only after a click.
 * „Künftig direkt laden“ per provider: consent management (extension consent_kit, window.cmsConsent) – otherwise in the
 * browser (localStorage, no cookie), revocable at any time. Consent via the cookie notice → event cms:consent.
 * Replaces the former kit scripts js/video.js, js/embed.js and the video part of js/blocks.js; their storage keys
 * ({kit}-embed-{provider}, mycms-consent-{provider}) still apply.
 */
(() => {
  const d = document;
  const KEY = p => 'cms-embed-' + p;
  // former keys of the kits (basis-embed-youtube, glas-embed-vimeo, mycms-consent-youtube …)
  const legacy = p => { try { return Object.keys(localStorage).filter(k => k !== KEY(p) && (k.endsWith('-embed-' + p) || k === 'mycms-consent-' + p)); } catch { return []; } };
  // consent management: window.cmsConsent.embed(p) → true/false, null = not managed (then localStorage)
  const managed = p => window.cmsConsent?.embed(p) ?? null;
  const stored = p => { try { return localStorage.getItem(KEY(p)) === '1' || legacy(p).some(k => localStorage.getItem(k) === '1'); } catch { return false; } };
  const allowed = p => managed(p) ?? stored(p);
  const remember = (p, on) => {
    if (managed(p) !== null) { window.cmsConsent.allowEmbed(p, on); return; }
    try {
      if (on) localStorage.setItem(KEY(p), '1');
      else [KEY(p), ...legacy(p)].forEach(k => localStorage.removeItem(k));
    } catch { /* private mode */ }
  };

  function load(el, autoplay) {
    const p = el.dataset.embed;
    const f = d.createElement('iframe');
    f.src = autoplay ? el.dataset.src : el.dataset.src.replace('autoplay=1', 'autoplay=0');
    f.title = el.dataset.title;
    f.className = 'vembed__frame';
    f.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
    f.allowFullscreen = true;
    f.referrerPolicy = 'strict-origin-when-cross-origin';
    el.replaceChildren(f);
    el.classList.add('is-loaded');
    if (allowed(p)) {
      const note = d.createElement('p');
      note.className = 'vembed__revoke';
      const btn = d.createElement('button');
      btn.type = 'button';
      btn.textContent = el.dataset.tRevoke;
      btn.addEventListener('click', () => { remember(p, false); location.reload(); });
      note.append(el.dataset.tDirect + ' ', btn);
      el.after(note);
    }
    if (autoplay) f.focus();
  }

  d.querySelectorAll('[data-embed]').forEach(el => {
    if (allowed(el.dataset.embed)) { load(el, false); return; }
    el.querySelector('[data-embed-play]')?.addEventListener('click', () => {
      if (el.querySelector('[data-embed-remember]')?.checked) remember(el.dataset.embed, true);
      load(el, true);
    });
  });
  // consent given via the cookie notice (consent_kit) → load waiting videos (without autoplay)
  d.addEventListener('cms:consent', () => d.querySelectorAll('[data-embed]:not(.is-loaded)').forEach(el => { if (allowed(el.dataset.embed)) load(el, false); }));
})();
