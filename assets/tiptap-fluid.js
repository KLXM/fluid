/*
 * fluid – extensions for the TipTap editor (klxm/tiptap): attributes fluid writes that TipTap does not know, so editing
 * keeps them – anchors of headings (id) and the video lightbox of links (data-video-lb).
 */
(() => {
  const keep = (name) => ({
    default: null,
    parseHTML: (el) => el.getAttribute(name),
    renderHTML: (attrs) => (attrs[name] ? { [name]: attrs[name] } : {}),
  });
  window.RexTiptap = window.RexTiptap || {};
  const ready = (fn) => (window.RexTiptap.ready ? window.RexTiptap.ready(fn) : document.addEventListener('rex-tiptap:loaded', (e) => fn(e.detail), { once: true }));
  ready(({ tiptap }) => {
    const FluidAttributes = tiptap.Extension.create({
      name: 'fluidAttributes',
      addGlobalAttributes: () => [
        { types: ['heading'], attributes: { id: keep('id') } },
        { types: ['link'], attributes: { 'data-video-lb': keep('data-video-lb') } },
      ],
    });
    window.RexTiptap.register({ id: 'fluid', extensions: () => [FluidAttributes] });
  });
})();
