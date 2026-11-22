/*
 * Scrollytelling – nur auf Seiten mit dem Block. Der Abschnitt in der Bildschirmmitte wird aktiv und blendet in der stehenden
 * Spalte das Bild ein, das für ihn gilt (data-img: eigenes oder das zuletzt gebrachte, leer = noch keins). Fokus im Abschnitt
 * (Tastatur, Links) aktiviert ihn ebenso. Ohne JavaScript bleibt das erste Bild stehen; jedes Bild steht zusätzlich bei seinem
 * Text, wenn der Platz für die stehende Spalte fehlt (CSS Container-Query). Aus KLXM Studio (MIT).
 */
document.querySelectorAll('[data-scrolly]').forEach((box) => {
  const steps = [...box.querySelectorAll('[data-step]')];
  const imgs = [...box.querySelectorAll('[data-step-img]')];
  const activate = (st) => {
    steps.forEach((s) => s.classList.toggle('is-active', s === st));
    const i = st.dataset.img ?? st.dataset.step;
    if (i === '' && !imgs.some((im) => im.classList.contains('is-active'))) return;
    imgs.forEach((im) => im.classList.toggle('is-active', im.dataset.stepImg === i));
  };
  const io = new IntersectionObserver((entries) => entries.forEach((en) => { if (en.isIntersecting) activate(en.target); }), { rootMargin: '-45% 0px -45% 0px' });
  steps.forEach((s) => { io.observe(s); s.addEventListener('focusin', () => activate(s)); });
});
