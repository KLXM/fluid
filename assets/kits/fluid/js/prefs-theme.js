/*
 * Applies the visitors' display preferences (<ncss-preferences> of NativeCSS, „Website → Darstellungs-Knopf für Besucher
 * anzeigen“) to the fluid classes – both kits. Synchronous in the <head> right after preferences-early.js.
 *   data-theme="dark"     → is-dark (like the preview in the style editor), only if the design allows a dark scheme
 *   data-theme="light"    → has-dark temporarily removed: the dark rules for the device setting don't apply
 *   data-motion="reduce"  → has-motion removed: no reveal animations and no fluid marquee
 */
(function () {
  var html = document.documentElement;
  var dark = html.classList.contains("has-dark");
  var motion = html.classList.contains("has-motion");
  var ours = false;   // only revert is-dark if it was set here (the preview in the style editor sets it itself)
  function sync() {
    var theme = html.getAttribute("data-theme");
    if (dark) {
      if (theme === "dark") {
        ours = ours || !html.classList.contains("is-dark");
        html.classList.add("is-dark");
      } else if (ours) {
        html.classList.remove("is-dark");
        ours = false;
      }
      html.classList.toggle("has-dark", theme !== "light");
    }
    if (motion) {
      html.classList.toggle("has-motion", html.getAttribute("data-motion") !== "reduce");
    }
  }
  sync();
  new MutationObserver(sync).observe(html, { attributes: true, attributeFilter: ["data-theme", "data-motion"] });
})();
