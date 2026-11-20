/**
 * ncss - Code-Block: ruft das self-hosted Prism.js (vendor/prismjs/) einmal
 * kontrolliert auf (statt Prisms eigenem, unkontrolliertem DOMContentLoaded-Autostart -
 * siehe code-block.css für die dafür nötige "window.Prism = { manual: true }"-Zeile VOR
 * dem Laden von prism-core) und ergänzt jeden gefundenen Code-Block danach um einen
 * Copy-to-Clipboard-Button. Läuft auch ohne Prism (z.B. falls nur der Copy-Button
 * gebraucht wird, ohne die Prism-Scripts einzubinden) - Highlighting wird dann einfach
 * übersprungen, der Rohtext bleibt unhighlighted, aber kopierbar.
 *
 * deepQueryAll() statt document.querySelectorAll(): ein Code-Block INNERHALB eines
 * <ncss-container> (siehe ncss-container.js) liegt in dessen Shadow Root -
 * querySelectorAll() durchquert Shadow-Grenzen nicht. Aus demselben Grund wird
 * Prism.highlightElement() PRO Block aufgerufen statt des document-globalen
 * Prism.highlightAll() - highlightElement() arbeitet direkt auf dem übergebenen Element,
 * unabhängig davon, wo es im DOM (oder Shadow-DOM-Baum) sitzt.
 */
(function () {
  "use strict";

  function deepQueryAll(selector, root) {
    root = root || document;
    var found = Array.prototype.slice.call(root.querySelectorAll(selector));
    var all = root.querySelectorAll("*");
    for (var i = 0; i < all.length; i++) {
      if (all[i].shadowRoot) {
        found = found.concat(deepQueryAll(selector, all[i].shadowRoot));
      }
    }
    return found;
  }

  // Prism erst beim jeweiligen Aufruf prüfen (nicht einmalig beim Laden) - ein später
  // eingefügter Block soll auch dann hervorgehoben werden, wenn Prism nach diesem Script
  // fertig geladen wurde.
  function highlight(codeEl) {
    if (window.Prism && typeof window.Prism.highlightElement === "function") {
      window.Prism.highlightElement(codeEl);
    }
  }

  // Dynamische Inhalte (htmx, fetch+innerHTML, REDAXO-Frontend-Editing): scan() ist
  // idempotent - bereits verarbeitete Elemente merkt sich ein WeakSet, bewusst KEIN
  // data-Attribut (per innerHTML kopiertes Markup trüge sonst eine veraltete Markierung
  // mit). Läuft beim Laden und bei jedem "ncss-content-change"-Event (detail.root = neu
  // eingefügtes Element, ohne Angabe das ganze Dokument), siehe js/content-observer.js.
  var SELECTOR = "pre > code[class*=\"language-\"]";
  var seen = new WeakSet();
  function scan(root) {
    if (!root || !root.querySelectorAll) return;
    var list = deepQueryAll(SELECTOR, root);
    if (root.matches && root.matches(SELECTOR)) list.unshift(root);
    list.forEach(function (el) {
      if (seen.has(el)) return;
      seen.add(el);
      highlight(el);
      addCopyButton(el);
    });
  }
  scan(document);
  document.addEventListener("ncss-content-change", function (event) {
    scan((event.detail && event.detail.root) || document);
  });

  function addCopyButton(codeEl) {
    var pre = codeEl.parentElement;
    if (!pre) {
      return;
    }
    // Ein vorhandener Button stammt aus per innerHTML kopiertem Markup (ohne Listener) -
    // ersetzen statt übernehmen. addCopyButton() läuft pro <code> nur einmal (siehe scan()).
    var stale = pre.querySelector(".ncss-code-copy");
    if (stale) {
      stale.remove();
    }

    var button = document.createElement("button");
    button.type = "button";
    button.className = "ncss-code-copy";
    button.setAttribute("aria-label", "In die Zwischenablage kopieren");
    button.innerHTML = '<span class="ncss-icon ncss-icon-copy" aria-hidden="true"></span>';
    pre.appendChild(button);

    button.addEventListener("click", function () {
      copyText(codeEl.textContent, button);
    });
  }

  function copyText(text, button) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(text).then(function () {
        showCopied(button);
      });
      return;
    }

    var textarea = document.createElement("textarea");
    textarea.value = text;
    textarea.style.position = "fixed";
    textarea.style.opacity = "0";
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand("copy");
      showCopied(button);
    } finally {
      document.body.removeChild(textarea);
    }
  }

  function showCopied(button) {
    button.setAttribute("data-copied", "true");
    button.innerHTML = '<span class="ncss-icon ncss-icon-check" aria-hidden="true"></span>';
    window.setTimeout(function () {
      button.removeAttribute("data-copied");
      button.innerHTML = '<span class="ncss-icon ncss-icon-copy" aria-hidden="true"></span>';
    }, 1500);
  }
})();
