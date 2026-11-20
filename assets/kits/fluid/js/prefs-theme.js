/*
 * Darstellungs-Einstellungen der Besucher (<ncss-preferences> von NativeCSS, Website → „Darstellungs-Knopf für Besucher
 * anzeigen“) auf die Klassen von fluid übertragen – beide Kits. Synchron im <head> direkt nach preferences-early.js.
 *   data-theme="dark"     → is-dark (wie die Vorschau im Style-Editor), nur wenn das Design ein dunkles Schema erlaubt
 *   data-theme="light"    → has-dark vorübergehend weg: die dunklen Regeln für die Geräte-Einstellung greifen nicht
 *   data-motion="reduce"  → has-motion weg: keine Einblend-Animationen und kein Laufband von fluid
 */
(function () {
  var html = document.documentElement;
  var dark = html.classList.contains("has-dark");
  var motion = html.classList.contains("has-motion");
  var ours = false;   // is-dark nur zurücknehmen, wenn es von hier kommt (die Vorschau im Style-Editor setzt es selbst)
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
