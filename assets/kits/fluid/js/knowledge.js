/* Knowledge base (Klxm\Fluid\Data\Knowledge): search in the chapter navigation – hides chapters without matches (umlauts and
   accents don't matter), parent chapters of matches stay visible. Without JavaScript the search field stays hidden. */
(() => {
  // Chapter navigation: always open on wide windows (the summary is hidden there)
  const wide = matchMedia('(min-width: 64rem)');
  const drawers = () => document.querySelectorAll('[data-kn-drawer]').forEach((d) => { if (wide.matches) d.open = true; });
  drawers();
  wide.addEventListener('change', drawers);
  const norm = (s) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
  document.querySelectorAll('[data-kn-search]').forEach((box) => {
    const nav = box.closest('.kn-side');
    const q = box.querySelector('input');
    const n = box.querySelector('[data-kn-count]');
    const items = [...nav.querySelectorAll('[data-kn-item]')];
    box.hidden = false;
    let t;
    q.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => {
        const v = norm(q.value.trim());
        let hits = 0;
        items.forEach((li) => { li.hidden = !!v; });
        items.forEach((li) => {
          if (v && norm(li.dataset.knItem).includes(v)) {
            hits++;
            for (let p = li; p && p !== nav; p = p.parentElement) if (p.matches?.('[data-kn-item]')) p.hidden = false;
          }
        });
        n.textContent = !v ? '' : hits === 0 ? n.dataset.none : hits === 1 ? n.dataset.one : n.dataset.many.replace('{n}', hits);
      }, 120);
    });
  });
})();
