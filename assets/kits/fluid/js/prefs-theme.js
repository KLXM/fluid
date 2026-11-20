/*
 * Kit „NativeCSS“: Farbschema-Wahl der Besucher (<ncss-preferences>, data-theme am <html>) auf die Klassen von fluid
 * übertragen – synchron im <head> direkt nach ncss/js/preferences-early.js, damit nichts aufblitzt.
 *   data-theme="dark"   → is-dark (wie die Vorschau im Style-Editor), nur wenn das Design ein dunkles Schema erlaubt
 *   data-theme="light"  → has-dark vorübergehend weg: die dunklen Regeln für die Geräte-Einstellung greifen nicht
 * So folgen auch Regeln von fluid, die nur html.has-dark/html.is-dark kennen (Logo, Hinweisfarben, Kacheln …).
 */
(function () {
  var html = document.documentElement;
  var allowed = html.classList.contains("has-dark") || html.hasAttribute("data-fluid-dark");
  if (!allowed) return;
  html.setAttribute("data-fluid-dark", "");
  var ours = false;   // is-dark nur zurücknehmen, wenn es von hier kommt (die Vorschau im Style-Editor setzt es selbst)
  function sync() {
    var theme = html.getAttribute("data-theme");
    if (theme === "dark") {
      ours = ours || !html.classList.contains("is-dark");
      html.classList.add("is-dark");
    } else if (ours) {
      html.classList.remove("is-dark");
      ours = false;
    }
    html.classList.toggle("has-dark", theme !== "light");
  }
  sync();
  new MutationObserver(sync).observe(html, { attributes: true, attributeFilter: ["data-theme"] });
})();
