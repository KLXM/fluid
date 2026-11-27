/*
 * Scrollytelling – only on pages with the block. The section in the middle of the screen becomes active and fades in, in the
 * sticky column, the image that applies to it (data-img: its own or the last one shown, empty = none yet). Focus in the
 * section (keyboard, links) activates it as well. Without JavaScript the first image stays; each image also appears next to its
 * text when there is no room for the sticky column (CSS container query). From KLXM Studio (MIT).
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
