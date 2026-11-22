/*
 * Video-Links in der Lightbox (Klxm\Fluid\VideoLinks; aus KLXM Studio, MIT): Links mit data-video-lb="youtube|vimeo" + data-video-id
 * öffnen nach Einwilligung den Player in einem <dialog class="cms-lb cms-lb--video"> (Stile css/video-lightbox.css, vom Kit
 * ersetzbar). Lädt nur, wenn die Website-Einstellung an ist und die Seite solche Links hat.
 *  - Einwilligung beim Klick prüfen (spätere Änderungen gelten ab dem nächsten Klick): AddOn consent_kit (window.ConsentKit.has
 *    mit den Diensten „youtube“/„vimeo“), Einwilligungs-API von KLXM Studio (window.cmsConsent.embed) oder die Freigabe
 *    „{Anbieter}-Videos künftig direkt laden“ des Video-Blocks (localStorage, wie js/embed.js). Keine eigene Cookie-Auswertung.
 *  - Ohne Einwilligung: normaler Link – kein Nachladen, keine Anfrage an den Anbieter.
 *  - Player: nur youtube-nocookie.com bzw. player.vimeo.com, Adresse erst beim Öffnen; Esc/Knopf schließen, iframe wird entfernt,
 *    der Fokus kehrt zum Link zurück. Modaler Dialog: der Rest der Seite ist solange inaktiv.
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
  // Titel für Dialog und Player: sichtbarer Linktext (ohne Screenreader-Zusätze wie „öffnet in neuem Tab“)
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
    dlg.querySelector('.cms-lb__close')?.focus();   // Fokus nicht ins fremde iframe – dort käme Esc nicht an
  }

  d.addEventListener('click', (e) => {
    const a = e.target.closest?.('a[data-video-lb]');
    if (!a || e.defaultPrevented || e.button || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    const p = a.dataset.videoLb, id = a.dataset.videoId || '';
    if (!ID[p] || !ID[p].test(id) || !allowed(p)) return;   // „off“, unbekannt oder ohne Einwilligung: normaler Link
    e.preventDefault();
    open(a, p, id);
  });
})();
