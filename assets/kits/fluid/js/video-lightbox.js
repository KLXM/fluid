/*
 * Video links in the lightbox (Klxm\Fluid\VideoLinks; from KLXM Studio, MIT): links with data-video-lb="youtube|vimeo" + data-video-id
 * open the player after consent in a <dialog class="cms-lb cms-lb--video"> (styles css/video-lightbox.css, replaceable by the
 * kit). Loads only if the website setting is on and the page has such links.
 *  - Consent is checked on click (later changes apply from the next click): addon consent_kit (window.ConsentKit.has
 *    with the services "youtube"/"vimeo"), KLXM Studio's consent API (window.cmsConsent.embed) or the permission
 *    „{Anbieter}-Videos künftig direkt laden“ of the video block (localStorage, like js/embed.js). No own cookie evaluation.
 *  - Without consent: normal link – no loading, no request to the provider.
 *  - Player: only youtube-nocookie.com or player.vimeo.com, URL set only on opening; Esc/button closes, the iframe is removed,
 *    focus returns to the link. Modal dialog: the rest of the page is inert meanwhile.
 */
(() => {
  const d = document, cfg = d.currentScript?.dataset || {};
  const ID = { youtube: /^[\w-]{11}$/, vimeo: /^\d{6,12}$/ };
  const NAME = { youtube: 'YouTube', vimeo: 'Vimeo' };
  const player = (p, id) => p === 'youtube'
    ? 'https://www.youtube-nocookie.com/embed/' + id + '?autoplay=1&rel=0&modestbranding=1&playsinline=1'
    : 'https://player.vimeo.com/video/' + id + '?autoplay=1&dnt=1';
  const KEY = (p) => 'cms-embed-' + p;
  const stored = (p) => { try { return localStorage.getItem(KEY(p)) === '1' || Object.keys(localStorage).some((k) => (k.endsWith('-embed-' + p) || k === 'mycms-consent-' + p) && localStorage.getItem(k) === '1'); } catch { return false; } };
  const allowed = (p) => {
    const studio = window.cmsConsent?.embed?.(p);
    if (studio === true || studio === false) return studio;
    if (window.ConsentKit?.has?.(p)) return true;
    return stored(p);
  };
  const el = (tag, cls) => { const n = d.createElement(tag); if (cls) n.className = cls; return n; };

  if (cfg.css && !d.querySelector('link[data-fluid-video-lb-css]')) {
    const l = el('link'); l.rel = 'stylesheet'; l.href = cfg.css; l.dataset.fluidVideoLbCss = ''; d.head.append(l);
  }

  let dlg = null, stage = null, opener = null;
  function dialog() {
    if (dlg) return dlg;
    dlg = el('dialog', 'cms-lb cms-lb--video');
    const close = el('button', 'cms-lb__btn cms-lb__close');
    close.type = 'button';
    close.setAttribute('aria-label', cfg.close || 'Schließen');
    close.innerHTML = '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 6l12 12M18 6 6 18"/></svg>';
    close.addEventListener('click', () => dlg.close());
    stage = el('div', 'cms-lb__stage');
    dlg.append(close, stage);
    dlg.addEventListener('click', (e) => { if (e.target === dlg || e.target === stage) dlg.close(); });
    dlg.addEventListener('close', () => { stage.replaceChildren(); dlg.removeAttribute('aria-label'); opener?.focus(); opener = null; });
    d.body.append(dlg);
    return dlg;
  }
  // title for dialog and player: visible link text (without screen reader additions like „öffnet in neuem Tab“)
  const title = (a) => { const c = a.cloneNode(true); c.querySelectorAll('.sr-only,.visually-hidden,svg').forEach((n) => n.remove()); return (c.textContent || '').trim().replace(/\s+/g, ' ') || cfg.video || 'Video'; };

  function open(a, p, id) {
    dialog();
    opener = a;
    const label = title(a) + ' (' + NAME[p] + ')';
    dlg.setAttribute('aria-label', label);
    const f = el('iframe');
    f.src = player(p, id);
    f.title = label;
    f.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media';
    f.allowFullscreen = true;
    f.referrerPolicy = 'strict-origin-when-cross-origin';
    const box = el('div', 'cms-lb__player');
    box.append(f);
    stage.replaceChildren(box);
    dlg.showModal();
    dlg.querySelector('.cms-lb__close')?.focus();   // don't focus into the foreign iframe – Esc wouldn't arrive there
  }

  d.addEventListener('click', (e) => {
    const a = e.target.closest?.('a[data-video-lb]');
    if (!a || e.defaultPrevented || e.button || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    const p = a.dataset.videoLb, id = a.dataset.videoId || '';
    if (!ID[p] || !ID[p].test(id) || !allowed(p)) return;   // "off", unknown or without consent: normal link
    e.preventDefault();
    open(a, p, id);
  });
})();
