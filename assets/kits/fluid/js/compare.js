// Before/after (fluid_compare): show the slider, value as --hx-pos on the frame, meaningful value for screen readers
document.querySelectorAll('[data-compare]').forEach((box) => {
  const range = box.querySelector('input[type=range]');
  if (!range) return;
  const set = () => {
    box.style.setProperty('--hx-pos', range.value + '%');
    range.setAttribute('aria-valuetext', range.value + ' %');
  };
  range.hidden = false;
  box.classList.add('is-ready');
  range.addEventListener('input', set);
  set();
});
