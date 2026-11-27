/*
 * Multi-step forms (YForm tabs as steps, block „Formular“): one step visible, „Weiter“ validates the step's fields
 * (browser validation), „Zurück“ without validation, submit only in the last step. Server validation errors:
 * YForm marks the step with the error as active – it continues there. Without JavaScript all steps are stacked.
 */
(() => {
  const t = (de, en) => (document.documentElement.lang || 'de').startsWith('de') ? de : en;
  const init = (form) => {
    const panes = [...form.querySelectorAll('.dff-pane')];
    const list = form.querySelector('.dff-steps');
    const actions = form.querySelector('.dff-actions');
    if (panes.length < 2 || !list || form.dataset.steps) return;
    form.dataset.steps = '1';
    const labels = [...list.querySelectorAll('a')].map((a) => a.textContent.trim());
    // Progress instead of tabs: list of steps, earlier ones clickable
    const progress = document.createElement('ol');
    progress.className = 'dff-progress';
    progress.setAttribute('aria-label', t('Schritte', 'Steps'));
    list.replaceWith(progress);
    let cur = Math.max(0, panes.findIndex((p) => p.classList.contains('is-active')));
    const nav = document.createElement('div');
    nav.className = 'dff-stepnav';
    const back = Object.assign(document.createElement('button'), { type: 'button', className: 'btn btn--ghost', textContent: '← ' + t('Zurück', 'Back') });
    const next = Object.assign(document.createElement('button'), { type: 'button', className: 'btn btn--primary', textContent: t('Weiter', 'Next') + ' →' });
    nav.append(back, next);
    (actions || form).before(nav);
    const valid = (pane) => {
      for (const el of pane.querySelectorAll('input, select, textarea')) {
        if (!el.disabled && !el.checkValidity()) { el.reportValidity(); return false; }
      }
      return true;
    };
    const show = (i, focus) => {
      cur = i;
      panes.forEach((p, k) => { p.hidden = k !== i; p.classList.toggle('is-active', k === i); });
      progress.innerHTML = labels.map((l, k) => `<li${k === i ? ' aria-current="step"' : ''} class="${k < i ? 'is-done' : ''}">${k < i ? `<button type="button" data-go="${k}">` : '<span>'}<span class="dff-progress__n">${k + 1}</span> ${l.replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' })[c])}${k < i ? '</button>' : '</span>'}</li>`).join('');
      back.hidden = i === 0;
      next.hidden = i === panes.length - 1;
      if (actions) actions.hidden = i !== panes.length - 1;
      if (focus) {
        panes[i].querySelector('input:not([type=hidden]), select, textarea')?.focus({ preventScroll: true });
        form.scrollIntoView({ block: 'start', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
      }
    };
    next.addEventListener('click', () => { if (valid(panes[cur])) show(cur + 1, true); });
    back.addEventListener('click', () => show(cur - 1, true));
    progress.addEventListener('click', (e) => { const b = e.target.closest('[data-go]'); if (b) show(+b.dataset.go, true); });
    // Enter in a field: before the last step „Weiter“ instead of submitting
    form.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && cur < panes.length - 1 && e.target.matches('input:not([type=submit]):not([type=button])')) {
        e.preventDefault();
        next.click();
      }
    });
    show(cur, false);
  };
  const scan = (root = document) => root.querySelectorAll('form.dff').forEach(init);
  scan();
  document.addEventListener('fluid:rendered', (e) => scan(e.target || document));
})();
