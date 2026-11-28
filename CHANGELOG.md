# Changelog

## 1.0.0-beta.22 – 2026-11-28

- **Videos über das AddOn video_editor (optional, wie image_editor):** fluid bringt kein eigenes ffmpeg und keine eigene
  Untertitel-Auslieferung mehr mit (`src/Media/Ffmpeg.php`, `src/Media/Tracks.php` und der Endpunkt `/fluid-track/*`
  sind entfallen). Mit video_editor: Web-Fassungen, Poster oder Standbild, Untertitel auch als SRT (gesäubert) und Text
  zum Video. Ohne: Originaldatei, gewähltes Vorschaubild, WebVTT-Dateien unverändert.
- `fluid_poster` verkleinert nur noch gewählte Bilder; das Standbild kommt von `VideoEditor::frameUrl()`.
- Neu für eigene Ausgaben: `fluid_track_url()` und `fluid_track_label()`. Braucht klxm/video_editor ab 1.0.0-beta.2.

## 1.0.0-beta.21 – 2026-11-28

- **Umbenannt (Bruch):** Die Attribute tragen den Namen des AddOns: `#[AsBlock]` → `#[AsFluidBlock]`, `#[AsKit]` → `#[AsFluidKit]`, `#[AsChartEngine]` → `#[AsFluidChartEngine]`, `#[AsChartSource]` → `#[AsFluidChartSource]`. Eigene Elemente, Kits und Diagramm-Quellen bitte anpassen (Attribut und `use`-Zeile).
- Braucht klxm/routes 1.0.0-beta.5 (`#[AsRoutesEndpoint]`, `#[AsRoutesSource]` …) und klxm/tiptap 1.0.0-beta.9 (`#[AsTiptapProfile]`).

## 1.0.0-beta.20 – 2026-11-28

- Lässt sich nicht zusammen mit yrewrite installieren (`conflict` in composer.json, ebenso klxm/routes 1.0.0-beta.4). fluid nutzt routes für Adressen, Domains und SEO.

## 1.0.0-beta.19 – 2026-11-27

- **Deutsch und Englisch durchgehend:** Die Oberfläche auf der Website spricht die Sprache, die die Redakteurin im Backend-Profil gewählt hat (Gäste der Demo: Sprache der Seite) – Editor-Leiste, Seitenleisten, Seiteneinstellungen, Website-Einstellungen, alle Blöcke mit Feldern, Hilfen und Varianten, Stil-Editor, Backend-Seiten. Texte für Besucher folgen wie bisher der Seitensprache. Konsole, MCP und Selbsttests auf Englisch.\n- Texte des Editor-Skripts kommen aus den Sprachdateien (`fluid_js_*`), Datumsangaben in der Sprache der Redakteurin.\n- Feld-Attribute und `#[AsBlock]` nehmen `Translatable` – auch für eigene Blöcke im Projekt.

## 1.0.0-beta.18 – 2026-11-27

- „Linkvorschau beim Teilen erlauben“ (Website → Recht & Suchmaschinen) wirkt wieder mit dem eigenen robots-Text;
  eine per robots-Text gesperrte Website gilt auch für Seitenkopf und llms.txt als gesperrt (klxm/routes 1.0.0-beta.2).

## 1.0.0-beta.17 – 2026-11-27

- **Adressen, Websites, SEO und GEO kommen aus dem neuen AddOn [klxm/routes](https://github.com/KLXM/routes).** Das
  bisherige Routing von fluid (`src/Routing`, Namespace `Klxm\Fluid\Routing`) ist dort neu gedacht aufgegangen –
  Namespace jetzt `Klxm\Routes`. Die Migration übernimmt eigene Adressen, Domains (Landing-Domains werden Websites),
  Weiterleitungen, 404-Protokoll und SEO-Felder unverändert.
- Seitenleiste **Websites & Domains**: mehrere Websites mit Startpunkt, Sprachen, 404-Seite, Titel-Vorlage, KI-Crawlern,
  llms.txt; Domains für alle oder ausgewählte Sprachen, Varianten, Aliase, https. Rechte `routes[sites]` und
  `routes[redirects]` statt nur Admin.
- **Domain wechseln** im Menü ⋯: dieselbe Seite auf einer anderen Domain – dort angemeldet (eine Anmeldung für alle Domains).
- **SEO & KI** im Menü ⋯: Wertung und Hinweise für diese Seite und die ganze Website, verständlich für Redakteure.
- Seiteneinstellungen → Erweitert (Recht `routes[seo]`): Canonical, Seite als Weiterleitung, nofollow, nicht in der
  Sitemap, Seitentyp für schema.org.
- Weiterleitungen: 301/302/303/307/308/410, je Website, Ablaufdatum, Parameter übernehmen, „Adresse testen“.
- Menü nur mit den Seiten der aktuellen Website; Templates nutzen `Router::site()`/`Router::home()` statt `Router::landing()`.

## 1.0.0-beta.16 – 2026-11-27

- Formate per `#[AsTiptapStyles]` (vorher `#[AsStyles]`), setzt TipTap 1.0.0-beta.7 voraus.

## 1.0.0-beta.15 – 2026-11-27

- Textformate der Kits kommen über `#[AsStyles('fluid.kit', …)]` in den Editor (`Tiptap\KitStyles`) – derselbe Weg, den
  jedes AddOn und das Projekt für eigene Formate nutzen. `RichText::html()` behält die Klassen, die das Profil
  `tiptap.fluid` dann kennt, also auch Formate anderer AddOns. Setzt TipTap 1.0.0-beta.6 voraus.
- Demo-Beitrag „TipTap meets fluid“: Code-Beispiel für eigene Formate per `#[AsStyles]`.

## 1.0.0-beta.14 – 2026-11-27

- Text direkt auf der Seite: Die Werkzeugleiste von TipTap sieht aus wie die übrige fluid-Oberfläche (weiß, abgerundet,
  Farben, Schrift, dunkler Modus), bleibt einzeilig und zeigt seltene Werkzeuge hinter „⋯“ (TipTap 1.0.0-beta.3).
- **Textformate bringt das Kit mit** (`kit.php` → `text`, optional, erbt vom Eltern-Kit): Absatz-, Überschriften-, Farb-,
  Listen- und Linkformate. Der Editor bietet genau diese an, die Ausgabe behält nur diese Klassen (`TextFormats`).
  Kit fluid neu: Zweite Markenfarbe, Überschrift „Groß“, Links als Button; Kit NativeCSS: Listen Punkte/Striche/
  Trennlinien/Schritte, Buttons von NativeCSS, Farbverlauf für Überschriften. Nummerierte Listen behalten Zählart und
  Start (`ol type/start`). `RichText::P_CLASSES` und `RichText::COLORS` entfallen.
- README und Demo-Beitrag „TipTap meets fluid“ mit neuen Bildern: einzeilige Leiste, Formate aus dem Kit (fluid und
  NativeCSS), Textblock mit allen Formaten zum Ausprobieren. Fix: Link-Leiste blieb sichtbar (TipTap 1.0.0-beta.5).
- `fluid:demo --page=… --update` erneuert auch die Beitragsdaten (Anreißer, Bild, Datum, Autor, Schlagwörter).

## 1.0.0-beta.13 – 2026-11-27

- Schraffur beim Abstand-Ziehen mit weißen Linien – auch auf dunklen Abschnitten gut zu sehen.
- **Endung der Adressen auf der Website wählen** (Website → Adressen): ohne, Schrägstrich, `.html`, gemischt (Menüpunkte `/`,
  Seiten `.html`) oder eigene – mit Beispielen echter Seiten. Bisherige Schreibweisen leiten per 301 um. Setzt das Projekt
  `Router::$config`, gilt der Code (Anzeige nur lesend); neu dafür `Router::defaults()` und `Router::configuredByProject()`.
- **Adressen gestalten** in den Seiteneinstellungen: „Eigene Adresse“ ohne übergeordnete Seiten (auch mit `/`, z. B.
  `angebot/sommer`) und für Menüpunkte „In den Adressen der Unterseiten ausblenden“ (`/leistungen/beratung` → `/beratung`).
  Adressen werden jetzt entlang des Seitenbaums berechnet und auf der ganzen Adresse eindeutig gehalten; alte leiten weiter.
- **Kurzlinks** in den Seiteneinstellungen: Wunschadresse oder kurzer Code, kopieren mit einem Klick, Klicks zählen,
  optional gültig bis zu einem Datum. Unter „Website → Weiterleitungen“ auch zu anderen Websites (Art „Kurzlink“).
  Belegte Adressen werden abgelehnt. Redakteure brauchen dafür kein Backend, nur das Recht, die Seite zu bearbeiten.
- **Extension Points** fürs Routing wie im Core: `ShortlinkPath`, `ShortlinkAdded`, `ShortlinkDeleted`, `RedirectMatched`,
  `PageAddressChanged`, `UrlFormatChanged`.
- Weiterleitungen können ablaufen (Spalte `expires`).
- Adressen aus Titeln schreiben Akzente um (`Café` → `cafe`, `Łódź` → `lodz`), bisher wurden sie zu Bindestrichen.
- Fix: „Ganzes Bild“ mit Hintergrund „Unscharfes Bild“ zeigte ein fast scharfes Bild (die kleinste Breite des Bildeditors
  ist 320 px). Jetzt mit dem neuen Medientyp `image_editor_backdrop` (48 px, weichgezeichnet; image_editor 021f2a8).
  Dazu Abstände in der Auswahl „Darstellung“ – eine Option je Zeile.
- Demo „REDAXO 6“: Seite „Adressen“ zeigt Endung, eigene Adressen, Kurzlinks und die Extension Points (deutsch und englisch).
- Fix: „Originalgröße“ (an dieser Stelle oder am Medium) ließ das Bild verschwinden – der Rahmen ohne Seitenverhältnis war
  0 px hoch. Neu `frame--natural`: das Bild steht in eigener Größe, zentriert, höchstens so breit wie die Spalte.
- `Routes::volatile()`: Routing ohne Cache-Dateien, für Änderungen in einer Transaktion, die zurückgerollt wird (fluid_demo).
  Kurzlinks lassen sich auch über ihre Adresse löschen; „Anlegen“ liefert die vergebene Adresse (`wish`) mit.
- Demo „REDAXO 6“: Wortwahl „Elemente“ statt „Bausteine“/„Baukasten“; die Seite „Bausteine“ heißt „Elemente“
  (`/fluid/elemente`, alte Adresse leitet weiter). Startseite mit „Modular und erweiterbar“: normale REDAXO-Module laufen
  mit, eigene Elemente als Klasse, eigenes Template (Kit-Styles einbinden), Erweitern wie im Core.
- `fluid:demo`: Seiten mit `'renamed_from' => ['Alter Titel']` werden mit `--update` umbenannt statt neu angelegt (neue
  Adresse, alte leitet weiter). Fix: Beim Übersetzen bekam bei Menüpunkten nur die Kategorie den Titel – `<title>` blieb
  in der Startsprache.
- **Eigenes Template mit den Elementen von fluid:** `KitHead::htmlAttributes()` und `KitHead::render()` – Titel, SEO,
  Styles und Skripte des aktiven Kits, geschrieben am Ende der Anfrage (nur Styles der genutzten Elemente, Kopf darf vor
  dem Inhalt stehen). Der Kopf gehört jetzt dem Kit (`Kit::head()`, NativeKit mit Cascade Layers); beide Seitenrahmen
  nutzen `KitHead` – Ausgabe unverändert (verglichen für beide Kits).
- Website-Texte: „Ein Element ist ein REDAXO-Modul mit kleinen Extras“; Seite „Module und Templates“ mit Beispiel für ein
  eigenes Template.
- Demo „REDAXO 6“: Meldung „TipTap meets fluid“ (Neuigkeiten, mit Screenshots, sechs Gründe, Profil als Klasse) und Hinweis
  darauf auf der Startseite.
- **Text-Editor: TipTap** (`klxm/tiptap`, jetzt Abhängigkeit) statt der eigenen Formatierungsleiste – in der Seitenleiste
  und direkt auf der Seite (Inline-Modus von TipTap, Klick setzt die Schreibmarke an die Stelle). Profil als Klasse
  `Tiptap\FluidProfile` (`tiptap.fluid`) mit denselben Formaten wie bisher, Plugin für Anker an Überschriften und die
  Video-Lightbox an Links; Seiten und Dateien wählen Redakteure auf der Website mit der Auswahl von fluid. Bestehende Texte
  bleiben unverändert (Rundlauf geprüft). Entfernt: `assets/rte.js`, `rte.css` und ihre Quellen.
- Fix: Der Core ersetzt `redaxo://ID` in der ganzen Ausgabe eines Slices – auch im `data-link` von Links; beim Bearbeiten
  auf der Seite ging so die feste Verbindung zur Seite verloren. `data-link` steht jetzt als Entität in der Ausgabe.
- **Gewöhnliche REDAXO-Module auf der Website hinzufügen:** Die Auswahl zeigt sie unter „REDAXO-Module“ (wenn ihr Formular
  vollständig in der Seitenleiste geht, Modulrecht, Template und Kategorie erlauben es). Erst das Formular, nach
  „Übernehmen“ der Slice – geprüft wie im Backend. Neu: `ModuleForm::addable()/newForm()/create()`, API `module_new`/`module_add`.
- **Glossar per Markieren:** Wer im Bearbeiten-Modus Text im Inhalt markiert, bekommt darunter den Knopf „Zum Glossar“ –
  öffnet das Glossar mit dem Begriff (wie Alt+G, das bisher der einzige Weg mit Markierung war).
- Fix: Automatische Weiterleitungen nach geänderten Adressen führten aus einer weiteren Sprache auf die Seite der Startsprache
  (`/en/alt` → `/neu` statt `/en/new`). Neue Regeln speichern die Sprache (`redaxo://ID@Sprache`), bestehende nehmen die Sprache der
  aufgerufenen Adresse – ohne Migration.
- Demo „REDAXO 6“: Die Dachzeile der Startseite zeigt mit dem AddOn fluid_demo die aktuelle Version von fluid (`{fluid-version}`).
- **Datenliste: Suchfeld für Besucher** (Häkchen am Block): sucht serverseitig in allen Textfeldern der Tabelle, auch über
  alle Seiten beim Blättern – mit dem Suchfilter von YForm (`tee*` beginnt mit, `"tee"` genau, `!tee` schließt aus).
  Neu `YFormBridge::search()`. Demo: Seite „Daten“.
- **Eigenes Formular: Datei hochladen und Prüfregeln.** Neue Art „Datei hochladen“ (YForm-Upload, erlaubte Typen und
  Größe je Feld, ausführbare Typen nie erlaubt) – Dateien gehen als Anhang an die Mail ans Team. „Mindestens/Höchstens“ je
  Feld: Zeichen bei Text, Wert bei Zahlen, Datum auch relativ (`today`, `+1 year`), der Datumswähler begrenzt mit.
  Demo: Seite „Anmeldung“.
- Fix: Gäste der Demo konnten im Text keine Seiten und Dateien verlinken – der Link-Dialog bot nur eine Adresse an. Die
  Seiten- und Medienauswahl (`Picker\FrontendPicker`) gibt es jetzt auch für Gäste (`FLUID_GUEST_USER`) mit den Rechten
  ihrer Rolle, nur lesend: kein Hochladen. Neu `FrontendPicker::user()`/`readOnly()`.

## 1.0.0-beta.12 – 2026-11-27

### Bild an dieser Stelle

- Am Bild im Bearbeiten-Modus neu **„◧ Hier anpassen“** – gilt nur für diese Verwendung im Block, nicht für das Medium:
  **Effekt** (Schwarzweiß, Sepia, Warm, Kühl, Gedämpft, Kräftig, Kontrast, „Kein Effekt“ schaltet auch den Effekt des Designs ab;
  Regler für Sättigung, Helligkeit, Kontrast), **Darstellung im Rahmen** (füllen, ganzes Bild mit Hintergrund unscharf/Akzentfarbe/
  eigene Farbe, Originalgröße) und **Ausschnitt** (Position und Zoom, durch Ziehen am Bild, Regler oder Tastatur; in Rahmen mit
  festem Format). Vorschau live auf der Seite, gespeichert mit „Speichern“ wie Texte.
- Gespeichert im Block unter `_img` (Feldpfad → Einstellung), gilt nur, solange an der Stelle dasselbe Bild steht. Ausgabe als
  Klassen (CSP), ohne neue Bildfassungen. Klassen `Klxm\Fluid\Media\ImagePlace`, `ImagePlaces`, `ImageEffect`, `ImageFit`.
- Funktioniert auch ohne das AddOn image_editor; die Knöpfe für das Medium („Bearbeiten“, „Anpassen“, „Ausschnitt“) erklären
  jetzt per Tooltip, dass sie für alle Verwendungen gelten.

### Editor

- **Blockleiste rechts** am Block (wie die Slice-Leiste im REDAXO-Backend); Bild- und Modul-Knöpfe weichen ihr aus.
- **Abstand ziehen:** Der Abstand ist beim Zeigen und Ziehen schraffiert, der Abschnitt leuchtet auf (wie KLXM Studio).

### Behoben

- Klassen für Fokuspunkt und Bildeinstellungen fehlten in Blöcken, die der Editor per API neu ausgibt (z. B. nach „Bild
  bearbeiten“): Die API liefert ihre Regeln jetzt mit (`InlineStyle::css()`).

### Neu für Entwickler

- Übersetzungen in `lang/` (`I18n::msg('fluid_…')`), zuerst für die Bildeinstellungen.

## 1.0.0-beta.11 – 2026-11-22

Nach dem Vorbild von KLXM Studio (MIT) – schlanke erste Fassungen, angepasst an REDAXO/fluid. **Nach dem Update
`php bin/console migrate`** (legt die YForm-Tabelle „Glossar“ an). Alles Neue ist aus, bis man es einschaltet: Menüs, Seiten und
Texte bestehender Websites bleiben unverändert (Pixelvergleich der Demo-Seiten).

### Einträge im Menü

- Block „Datenliste“ mit Detailansicht → **„Einträge im Menü zeigen“**: Keine (Standard) | Oberste Ebene | Zwei Ebenen (Kapitel und
  Unterkapitel bei Tabellen der Art „Wissen“), dazu „Höchstens im Menü (je Ebene)“. Die Einträge erscheinen als virtuelle Unterpunkte
  der Übersichtsseite im Kopf- und Mobilmenü beider Kits (`FluidTemplate::menu()`), auf der Detailseite ist der Eintrag „aktuell“ und
  die Übersicht „aktiv“. Brotkrumen (schema.org) mit Oberkapiteln, Sitemap über die Detailseiten wie bisher.
- `Klxm\Fluid\Data\MenuEntries`, Extension Point `FLUID_MENU_ENTRIES` (Punkte ändern).

### Glossar

- **Ein Glossar je Website** als YForm-Tabelle „Glossar“ (`rex_fluid_glossary`, `Glossary\GlossaryTable`): Begriff, Schreibweisen,
  Kurztext (Hinweisfenster, höchstens 240 Zeichen) und Text (ausführliche Erklärung aus Blöcken, auf der Detailseite direkt bearbeitbar).
- **Automatisch markieren** (Website → Glossar, Standard aus): erstes Vorkommen je Seite oder je Abschnitt, nicht in Links, Überschriften
  (h1–h3), Knöpfen, Navigation, Kopf und Fuß, Code; Schreibweisen mit üblichen Endungen, Abkürzungen nur in genauer Schreibweise;
  Hinweisfenster per Popover (Esc, Klick daneben, Fokus zurück, auf dem Telefon als Blatt unten), „Mehr im Glossar“ zur Detailseite.
  Ausnehmen mit `data-glossary="off"`. Nie im Bearbeiten-Modus, nie auf der eigenen Detailseite. Stylesheet und Skript nur auf Seiten
  mit Treffern. Markierung: `Glossary\Annotator` aus KLXM Studio (unverändert).
- **Block „Glossar“** (Daten): Übersicht von A bis Z mit Suche und Buchstaben (DefinedTermSet); seine Seite ist die Übersicht, jeder
  Begriff hat darunter eine Detailseite, z. B. `/glossar/barrierefreiheit` (Titel, Beschreibung, Canonical, Sitemap, DefinedTerm).
- **Quick-Glossar** im Editor (Menü ⋯ → Glossar, Alt+G): Begriffe suchen, markierten Text als neuen Begriff anlegen (vorbelegt),
  „Gibt es schon?“ mit „Als Schreibweise zu … hinzufügen“. Recht: Bearbeiten der YForm-Tabelle.
- Interaktives Bild: Markierungen mit Glossar-Begriff (Feld „term“) bekommen Begriff und Kurztext aus dem Glossar (`FLUID_HOTSPOT_TERM`).
- Datenquelle hinter einer kleinen Schnittstelle (`Glossary\GlossaryStore`, Standard `YFormGlossaryStore`) – ein anderes Backend setzt
  `Glossary::$store` in `Project::configure()`.

### Wissensbereich

- **Tabellenart „Wissen“:** `Klxm\Fluid\Data\KnowledgeTable` im Projekt ableiten (Beispiel `examples/yform/HandbuchTable.php`) –
  Titel, Oberkapitel (Kapitelbaum), Reihenfolge, Kapitelnummer (sonst automatisch 1, 1.1 …), Bereich (`areas()`), Anreißer, Bild,
  Inhalt aus Blöcken (Feld „Fluid-Blöcke“), Schlagworte, verwandte Kapitel, Glossar-Begriffe, „Zuletzt geprüft“, Online, Sprache.
- **Übersicht** mit dem Block „Datenliste“: neue Darstellung **„Inhaltsverzeichnis“** (Bereiche → Kapitel → Unterkapitel mit Nummern;
  bei anderen Tabellen eine nummerierte Liste) oder Karten/Liste in der Reihenfolge des Kapitelbaums.
- **Detailansicht mit Kapitelnavigation:** Seitenleiste mit Suche, Kapitelbaum (aktueller Zweig offen) und „In diesem Kapitel“ (h2 des
  Inhalts, mit Ankern), Inhalt aus Blöcken (auf der Website bearbeitbar), Schlagworte, Begriffe, verwandte Kapitel, „Zuletzt geprüft“,
  Vor/Zurück. Zwei Stile: **„Magazin“** (Einstieg mit Nummer, Titel, Anreißer und Bild über die volle Breite) und **„Sachlich“** (große
  Nummer vor dem Titel, kompakt – für Handbücher und Satzungen). Beide Kits.
- **Strukturierte Daten:** Article je Kapitel (isPartOf Sammlung und Oberkapitel, position, alternateName = Nummer, keywords,
  articleSection, about = DefinedTerm des Glossars), WebPage mit lastReviewed und relatedLink, auf der Übersicht CollectionPage mit
  hasPart in Lesereihenfolge.
- Bewusst (noch) nicht übernommen aus Studio: Layout-Modi (Kompakt, Fokus, Handbuch), Kapitelnavigation als eigener Block, Verweis-Kapitel
  auf Seiten, Umstieg Seitenzweig → Wissensbereich, Trefferauszug und Glossar-Treffer in der Suche der Datenliste, geteiltes Glossar über
  mehrere Websites, dynamische Bereiche (Markieren im Browser), Mehrfach-Filter.

### Behoben

- Datenliste: Einträge mit „Alle Sprachen“, die über die Maske von YForm gespeichert wurden (Spalte `NULL` statt `''`), fehlten in
  Liste, Detailansicht und Sitemap (`YFormBridge::inLanguage()`).
- Editor, dunkles Farbschema: gewählter Reiter der Seitenleisten (Website, Anfragen, Glossar) weiß auf hell (1,2:1) – jetzt in der
  Grundfarbe der Leiste.

### Selbsttest

- `console fluid:selftest`: neue Gruppen „Menü“, „Glossar“ und „Wissen“.

*EN:* Data list entries can appear in the menu below their overview page (none / top level / two levels, limit). New site glossary
(YForm table): automatic marking of the first occurrence with popovers (off by default), glossary block with A–Z overview and detail
pages, Quick glossary in the editor (Alt+G), store interface for other backends. Knowledge area: table kind “Wissen” (chapter tree,
numbers, areas, tags, related chapters, glossary terms, last reviewed, block content), table-of-contents display, detail view with
chapter navigation in two styles (magazine, factual), JSON-LD Article/CollectionPage/DefinedTerm.

## 1.0.0-beta.10 – 2026-11-22

Nach dem Vorbild von KLXM Studio (MIT), angepasst an REDAXO/fluid. **Nach dem Update `php bin/console migrate`** – bestehende
Rollen behalten dabei alle Rechte, bestehende Datenlisten bleiben unverändert.

### Rollen: erlaubte Blöcke, gesperrte Blöcke, Gestaltung in zwei Stufen

- **Erlaubte Blöcke = Modulrechte der Rolle** (REDAXO). fluid führt kein eigenes Recht ein: Die Blockauswahl zeigt nur erlaubte
  Blöcke, Blöcke anderer Module stehen auf der Seite mit dem Hinweis „Kein Recht“ (verschiebbar, nicht änderbar). Die API prüft das
  Modulrecht jetzt auch beim Löschen und beim Online-Schalter (vorher nur Einfügen und Speichern).
- **Gesperrte Blöcke:** Seitenleiste → Abschnitt → „Block sperren“ (Abschnitts-Option `lock` im Slice). Gesperrte Blöcke bleiben
  sichtbar und verschiebbar, lassen sich aber nur mit Recht ändern, ausblenden oder löschen – auf der Website (Leiste „Gesperrt“,
  keine Felder direkt auf der Seite, keine Greifer), in der API, im MCP-Server und im Backend des Cores (Menü des Slices,
  `SliceUpdating`/`SliceDeleting`). Kopien gesperrter Blöcke sind nur mit Recht wieder gesperrt; Ausschneiden lässt das Original stehen.
- **Gestaltung in zwei Stufen** als Optionen der Rolle (`Klxm\Fluid\Perm`): `fluid[design]` – Abschnitt (Hintergrund, Abstände auch
  per Greifer, Sprungmarke, Einblend-Effekt); `fluid[design_full]` – zusätzlich Blöcke sperren/entsperren, gesperrte Blöcke ändern und
  Listen-Vorlagen verwalten. Ohne Recht bleiben die gespeicherten Werte, die Seitenleiste zeigt den Abschnitt nicht.
- **Migration:** `migrate` gibt allen vorhandenen Rollen einmal beide Optionen (`Perm::migrateRoles()`, Merker `perm_design_roles`) –
  niemand verliert etwas. Neue Rollen haben sie erst, wenn man sie anhakt. Admins dürfen ohnehin alles.

### Listen-Vorlagen für die Datenliste

- **Gespeicherte Darstellungen** (Optionssätze) je YForm-Tabelle: Darstellung, Bild-, Text- und Zusatzfeld, Sortierung,
  Spalten-Sortierung, Filter für Besucher, Breite. Beim Block bleiben Überschrift, Tabelle, Titelfeld, Bedingung, Anzahl, Blättern,
  Detailansicht und Kalender – also alles, was Inhalt und Adressen bestimmt.
- **Auswahl im Block mit Vorschau:** Feld „Vorlage“ als Kacheln mit Vorschaubild je Darstellung, nur Vorlagen der gewählten Tabelle,
  „Eigene Einstellungen“ zuerst. Mit gewählter Vorlage blendet die Seitenleiste ihre Felder aus; „Anpassen (Vorlage lösen)“ übernimmt die
  Werte in den Block. Die Vorschau auf der Seite folgt sofort.
- **Vorschläge je Tabelle** beim ersten Öffnen (Karten, Große Karten mit Bild bzw. Liste, Liste mit Bild, Tabelle sortierbar).
- **Verwalten in der Seitenleiste** (Recht `fluid[design_full]`): „Als Vorlage speichern …“, „Als Standard der Tabelle“ (wird beim
  Wählen der Tabelle vorausgewählt), „Mit diesen Einstellungen überschreiben“, Umbenennen, Löschen.
- **Vordefiniert per Kit und Extension Point:** `kit.php` → `'list_templates'` (Schlüssel `kit:…`) und `FLUID_LIST_TEMPLATES`
  (`ext:…`), nur lesbar, optional nur für bestimmte Tabellen und als Standard.
- **Ohne gewählte Vorlage bleibt alles wie bisher** (auch alle bestehenden Blöcke); eine gelöschte Vorlage fällt auf die eigenen Werte
  des Blocks zurück. Technik: `Klxm\Fluid\Data\ListTemplates` (Tabelle `rex_fluid_list_template`), Feld `Field\ListTemplate`,
  `#[AsBlock(listTemplate: true)]` – auch für eigene Listenblöcke.

### Selbsttest

- `console fluid:selftest` hat die neuen Gruppen „Rechte“ und „Listen-Vorlagen“ (`Klxm\Fluid\FeatureSelfTest`).

*EN:* Roles: allowed blocks come from REDAXO's module permissions (block picker, read-only blocks, API checks incl. delete and
status). New locked blocks (visible and movable, editable only with permission – website, API, MCP, core backend). Two design
levels as role options `fluid[design]` (section options) and `fluid[design_full]` (lock, list templates); existing roles get both on
`migrate`. List templates for the data list: saved display option sets per YForm table with tile preview, suggestions, default per
table, save/overwrite/rename/delete in the sidebar, predefined via kit or `FLUID_LIST_TEMPLATES`; blocks without a template are
unchanged.

## 1.0.0-beta.9 – 2026-11-22

Neue Funktionen nach dem Vorbild von KLXM Studio (MIT), angepasst an REDAXO/fluid. **Nach dem Update `php bin/console migrate`**
ausführen – bestehende Blöcke behalten dabei ihr Aussehen (siehe unten).

### Bestand schützen bei geänderten Standardwerten

- `Klxm\Fluid\DefaultChanges`: Ändert sich der Standardwert eines Blockfelds, schreibt `migrate` (FluidAddon::install) einmal je
  Änderung den bisherigen Zustand in alle gespeicherten Blöcke ohne Wert – Slices (Live und Arbeitsversion), Versionen
  (`rex_article_slice_history`) und Felder „Fluid-Blöcke“ in YForm-Tabellen. Idempotent, vorhandene Werte (auch `null`) bleiben,
  erledigte Änderungen stehen in der Konfiguration `block_defaults`. Neu: `console fluid:upgrade:defaults` (`--dry-run`) und der
  Extension Point `FLUID_DEFAULT_CHANGES` für eigene Blöcke und Kits.
- Dieses Release nutzt es für Text + Bild (`valign` → bisher „Mitte“), Bildergalerie (`single_size` → bisher „wie die Galerie“)
  und Scrollytelling (`ratio` → bisher 4:5). Testinstallation: 72 Slices, 706 Versionen, 2 Datensätze festgehalten; die
  Demo-Seiten sehen danach gleich aus (Pixelvergleich).

### Blockoptionen

- **Text + Bild → „Bild ausrichten“:** Mitlaufen (Standard für neue Blöcke: bei langem Text steht das Bild oben und bleibt beim
  Scrollen neben dem Text, haftend unter dem Kopf; nur nebeneinander), Oben, Mitte (bisher). Beide Kits.
- **Bildergalerie → „Größe bei nur einem Bild“:** wie die Galerie (eine Spalte, bisher), Mittel (zentriert, höchstens 44 rem) oder
  Groß (volle Inhaltsbreite im Originalformat, höchstens 85 % der Fensterhöhe; Standard für neue Blöcke). Lightbox wie sonst.
- **Scrollytelling:** Text je Abschnitt als Rich-Text (Zwischenüberschriften, Links, Listen; Überschriften im Text liegen unter
  dem Abschnittstitel). Alter Klartext erscheint unverändert wie bisher, der Editor macht beim Bearbeiten Absätze daraus. Bild je
  Abschnitt optional – ohne eigenes Bild bleibt das vorige stehen (`data-img`). Neues Bildformat „Automatisch“ (jedes Bild ganz,
  ohne Zuschnitt; Standard für neue Blöcke). Fokus in einem Abschnitt aktiviert ihn. Beide Kits.

### Fußbereich

- **Handlungsaufruf im Fuß** (Website → Kopf, Fuß & Hinweis: Beschriftung + Ziel), getrennt vom Button im Kopf –
  `fluid_footer_cta()`. Kit fluid: „Spalten“ unter Claim/Text, „Schlicht“/„Zentriert“ in der Zeile, „Großer Schriftzug“ statt des
  Kopf-Buttons; Kit NativeCSS: unter dem Text. Leer = wie bisher.
- **Dichte des Fußbereichs** (Design → Kopf & Fuß bzw. im Kit NativeCSS unter „Form & Raum“): Normal (bisher) oder Kompakt – weniger
  Abstand, Links als Zeilen, Adresse in einer Zeile, Touch-Ziele ≥ 2,75 rem. Telefon (Demo): fluid 854 → 429 px, NativeCSS 865 → 458 px.
- **Kopf weicht dem Fuß:** Auf dem Telefon lag der haftende Kopf am Seitenende über Logo und Claim des Fußes. Er gleitet jetzt per
  View-Timeline (ohne JavaScript) mit dem Anfang des Fußes weg; kurze Füße (Desktop) erreichen ihn nie – keine Änderung.

### Block „Interaktives Bild“

- Neuer Block `fluid.hotspots` (Medien): Bild aus dem Medienpool mit Markierungen – Punkt, Kreis, Rechteck, Fläche (in %).
  Hinweisfenster per `<button popovertarget>` (Esc, Klick daneben, Fokus zurück), Flächen als SVG, Klick auf eine Fläche öffnet ihre
  Markierung. Auf dem Telefon (bzw. mit Option „Immer“) zusätzlich als Liste unter dem Bild, sonst für Screenreader. Rich-Text
  ohne Bilder, Link per Linkmap, Glossar-Begriff optional (`FLUID_HOTSPOT_TERM`), Symbol statt Nummer.
- **Marker-Editor** in der Seitenleiste (und im Backend): ins Bild klicken setzt eine Markierung, ziehen verschiebt sie, Pfeiltasten
  (Umschalt 5 %, Alt 0,1 %), Entf, Eingabe, Bild auf/ab; Ecke, Radius und Eckpunkte an der gewählten Markierung; Ansagen für
  Screenreader. Feld `Klxm\Fluid\Field\Hotspots` für eigene Blöcke.
- **Datenformat Version 1 exakt wie KLXM Studio** (`Klxm\Fluid\Hotspots`, `{v: 1, items: […]}`) – Markierungen sind austauschbar.
- **Andockstelle für externe Editoren:** Extension Point `FLUID_HOTSPOT_EDITORS`, Dialog mit `postMessage` – gleiches Protokoll wie
  Studio.
- CSP-tauglich: keine `style`-Attribute (Lage über Klassen `x0`–`x100`/`y0`–`y100`, genau per CSSOM), Skript und Stile nur auf
  Seiten mit dem Block.

### Video-Links in der Lightbox

- Website → Kopf, Fuß & Hinweis → „Video-Links in Lightbox öffnen“ (Standard aus): Links und Buttons auf YouTube- und
  Vimeo-Videos öffnen nach Einwilligung den Player (youtube-nocookie.com / player.vimeo.com) in einem modalen Dialog. Einwilligung:
  AddOn consent_kit (`window.ConsentKit.has`), sonst die Freigabe „Videos künftig direkt laden“ des Blocks Video. **Ohne Einwilligung
  bleibt es ein normaler Link** – keine Anfrage an den Anbieter.
- Ausnahme je Button: „Video ohne Lightbox öffnen“ hinter jedem Link-Feld (nur bei eingeschalteter Einstellung, Editor auf der
  Website), im HTML `data-video-lb="off"` (bleibt im Rich-Text erhalten). `Klxm\Fluid\VideoLinks`, `js/video-lightbox.js`,
  `css/video-lightbox.css`.

### Termine: „In den Kalender“

- Datenliste → „In den Kalender in der Detailansicht anbieten (.ics)“ (Standard aus): Detailansicht mit Link
  `/fluid-ics/{seite}-{eintrag}.ics` (`Klxm\Fluid\Data\Ics`) – ganztägig (Datum) oder mit Uhrzeit (Datum mit Uhrzeit, in UTC),
  Ende und Ort aus passend benannten Feldern, Titel, Beschreibung und Adresse der Detailseite; dieselben Bedingungen wie die
  Detailansicht. Serien mit einzelnen Vorkommen kennt fluid nicht (kein Serienmodell) – jeder Eintrag ist ein Termin.

### Selbsttest

- `console fluid:selftest` → „Blöcke“ prüft zusätzlich: geänderte Standardwerte, Format und Ausgabe des Interaktiven Bilds (ohne
  `style`), Video-Links (aus = unverändert), Kalenderdateien (Maskierung, 75-Byte-Zeilen) und Scrollytelling (Klartext, Ebenen).

*EN:* Existing blocks keep their look when field defaults change (`DefaultChanges`, runs on `migrate`). New block options: text +
image “image alignment” (follow/top/centre), single-image gallery size, scrollytelling with rich text, optional images and an
“automatic” format. Footer call to action, compact footer density, sticky header yields to the footer. New “Interactive image”
block (hotspots in Studio’s v1 format, sidebar editor, external editor hook). Video links in a lightbox after consent (off by
default). “Add to calendar” (.ics) for data list detail pages.

## 1.0.0-beta.8 – 2026-11-22

Fehler, die KLXM Studio ebenso hatte – die Lösungen von dort (MIT) übernommen und an REDAXO/fluid angepasst.

### Hintergrundvideo: runder Play/Pause-Schalter

- Einstieg „Vollflächig“ (Kits fluid und NativeCSS) und Video „Morphing“ (Kit NativeCSS): runder Symbol-Knopf statt der
  ausgeschriebenen Schaltfläche – mindestens 44 px, halbtransparente Fläche mit Rand, doppelter Fokusring. Läuft das Video,
  zeigt er „Pause“, angehalten „Play“; der zugängliche Name steht als Text im Knopf und wechselt („Hintergrundvideo
  anhalten“ / „… abspielen“), kein `aria-pressed` mehr. Bei „Bewegung reduzieren“, „Daten sparen“ und im Kontrastmodus
  startet das Video nicht, der Knopf bietet „abspielen“ an (vorher fehlte er dann im Kit fluid ganz).
- Neue Option im Block „Einstieg“: **Video-Schalter** „Symbol“ (Standard) oder „Symbol mit Text“.
- Ein gemeinsamer Schalter für beide Kits: `fluid_video_toggle()`, `css/video-toggle.css` (Tokens `--hx-vt-*`) und
  `js/hero-video.js`, nur auf Seiten mit Hintergrundvideo (`@videotoggle`). Das Videoskript in `js/site.js` und `js/video-bg.js`
  von NativeCSS (Textknopf) entfallen dafür; Quellen mit `data-src` laden weiter erst beim Abspielen.
- Symbole `pause` und `play` im Symbolsatz (`icons/core.svg`).

### Datenliste „Karten“: wenige Treffer behalten die Spaltenbreite

- `auto-fill` statt `auto-fit` im Kartenraster (`css/data.css`, auch Blog-Übersicht): Ein einzelner Treffer wird nicht mehr
  über die volle Breite gezogen.

### Seitenwechsel ohne Konsolenfehler

- Design „Seitenwechsel: Morphen“ (`js/vt.js`): `pageswap` und `pagereveal` fangen abgebrochene Übergänge ab (`ready`,
  `finished`, `updateCallbackDone`) – keine „Transition was aborted“-Meldungen mehr, z. B. beim schnellen Klicken.
- Editor: Der Wechsel „Ansehen ↔ Bearbeiten“ überspringt den Übergang (die Seite im Bearbeiten-Modus meldet sich nicht an) –
  vorher drei Fehler je Klick („ViewTransition opt-in disabled“).

### Inhaltsverzeichnis erkennt h2

- `fluid_toc()` (Text-Block „Artikel“): Enthält der Text `<h2>` (importiert oder eingefügt), ist h2 die oberste Ebene. Texte
  nur mit h3/h4 bleiben unverändert.

### Lange Adressen im Fließtext

- Grund-CSS (`css/site.css`, gilt auch im Kit NativeCSS): Links, Fettes, Kursives und Code in Absätzen, Listen, Tabellen,
  Zitaten und Überschriften brechen notfalls an jeder Stelle um – lange Adressen verbreitern die Seite auf dem Telefon nicht
  mehr. Texte ohne Überlauf bleiben unverändert.
- Die Nummern im Inhaltsverzeichnis („01“, „02“) brechen dabei nicht um (`css/b-article.css`).

### Prüfung: kein Code hinter Zeilenkommentaren

- `console fluid:selftest` hat die neue Gruppe „Blöcke“ (`Klxm\Fluid\BlockSelfTest`): Views ohne Code hinter `//`
  (`<?php $a = 1; // … $b = 2;` verschluckt `$b`), Inhaltsverzeichnis, Video-Schalter. Ohne REDAXO und in der CI:
  `php tools/check-views.php`.

### Kit „NativeCSS“: Text + Bild und farbige Abschnitte

- **Text + Bild:** Auf dem Telefon war das Bild 0 px breit (die Bildspalte schrumpfte durch `align-items: center` auf die
  Breite ihres Inhalts, der Rahmen hat ein absolutes Bild). Mittig jetzt nur nebeneinander; das Bild füllt den Rahmen im
  gewählten Format (kein leerer Streifen mehr).
- **Farbige Abschnitte** (Akzent, Zweitfarbe, Dunkel): Fragen, Karten, Pakete, Scrollytelling und der Einstieg „Vollflächig“
  mit Textkarte nehmen in ihren hellen Karten die Schrift der Seite (`--f0-*`, wie die Karten von fluid) – vorher hell auf
  hell (1,5:1). Das Datum der Zeitleiste nimmt den Nebentext des Abschnitts. axe auf einer Testseite mit allen Fällen: 8 → 0.

*EN:* Round play/pause button for background videos (icon or icon + text, no `aria-pressed`, does not autoplay with reduced
motion), data list cards keep their column width, no view-transition console errors, `fluid_toc()` uses h2 as top level when
present, long URLs wrap in body text, new self-test group “Blöcke” and `tools/check-views.php`, NativeCSS kit: text + image on
phones and readable cards in coloured sections.

## 1.0.0-beta.7 – 2026-11-21

### Fix: Installation ohne YForm

- **`migrate` brach ohne YForm ab** („Class "Yakamara\YForm\Form\Action\Action" not found“). Die Klassensuche von
  REDAXO 6 lädt jede Klasse unter den Addon-Pfaden; `Data\SafeMail` erbt von einer YForm-Klasse und hatte – anders als
  `FormEntryTable`, `FluidBlocks`, `FluidLanguage` und `BuiltForm` – keinen `class_exists`-Guard. Jetzt fehlt sie ohne
  YForm einfach, wie vorgesehen (YForm bleibt optional).
- Neu: `php tools/check-optional.php` – meldet Klassen, die von YForm erben oder ein YForm-Interface implementieren,
  aber keinen Guard haben. Läuft in der CI bei jedem Push.

## 1.0.0-beta.6 – 2026-11-21

### Kit „NativeCSS“: NativeCSS 1.4.0 – gemeinsamer Kern der Player, Tabellen sortieren

- **NativeCSS 1.4.0** (`php tools/ncss-update.php --tag=v1.4.0`). Video- und Audio-Player teilen sich jetzt
  `ncss/js/media-core.js` (Texte, Live-Region, Zeitleiste, Lautstärke, Menüs, Kapitel, Playlist, Tastatur). fluid bindet
  den Kern in `kit.php` → `assets` **vor** den Playern ein (`video:@player`, `video:audio`) – er wird genau einmal geladen,
  auch wenn Video und Audio auf derselben Seite stehen (sonst lädt ihn jeder Player selbst aus seinem Ordner nach).
- Audio-Player: Tasten wie beim Video (Pfeile ±5 s und Lautstärke, N/P nächster/voriger Titel, < und > Geschwindigkeit)
  mit Ansagen für Screenreader; „Voriger Titel“ ohne Playlist tut nichts. Video-Player: Position in der Media Session,
  `data-src` in Playlists.
- Tabellen sortieren (`table-sort`): neutrale Rückfallwerte ohne Tokens, Klasse `is-sortable` an aufbereiteten Tabellen,
  `data-sort-align="end"`, Polsterung per `--ncss-table-sort-pad-block/-inline`, Datum auch mit Uhrzeit und Zeitzone.
- **Datenliste als Tabelle:** Spalten aus Ganzzahl- und Dezimalfeldern sind rechtsbündig (`data-sort-align="end"`,
  `dl-num`, Ziffern gleich breit) und sortieren als Zahl, Datumsfelder als Datum – jeweils nach dem gespeicherten Wert
  (`data-sort-value`), nicht nach der Anzeige (`YFormBridge::sortType()`, `::sortValue()`). Die Sortier-Schaltfläche füllt
  im Kit NativeCSS die ganze Kopfzelle (`--ncss-table-sort-pad-*` = Zellenabstand der Datenliste); im Kit fluid behält sie
  Großschreibung und Laufweite des Spaltenkopfs.
- Geprüft im Kit NativeCSS: Video per Tastatur (K, J/L, Pfeile, M, C), Audio-Playlist (N/P, <, >, Ansagen), beide auf einer
  Seite mit einer Anfrage für `media-core.js`, Sortieren per Tastatur mit `aria-sort` und Ansage, Kopfleiste einzeilig mit
  und ohne JavaScript, axe (WCAG 2.2 AA) hell/dunkel 1440/390 ohne Funde.

## 1.0.0-beta.5 – 2026-11-21

### Kit „NativeCSS“: NativeCSS 1.3.0 – Kopfleiste schon ohne JavaScript richtig

- **NativeCSS 1.3.0** (`php tools/ncss-update.php --tag=v1.3.0`). Die Kopfleiste entscheidet jetzt schon vor dem ersten
  Zeichnen und ohne JavaScript per **Container Query** an der Breite der Kopfzeile, ob das Menü in die Zeile passt:
  `NativeKit::navFit()` schätzt aus Menüpunkten, Marke (Name oder Logo), Sprachen, Darstellungs-Knopf, Suche und
  Handlungsaufruf die nötige Breite und schreibt sie als `data-ncss-nav-fit` (rem, wächst mit Schriftgröße und Zoom) an die
  Navigation; über 120rem steht immer die Menü-Schaltfläche (`.ncss-nav--compact`). `ncss/js/nav.js` misst danach genau
  nach und hat Vorrang. Vorher galt ohne Skript fest 64rem Fensterbreite – ein langes Menü brach darüber in sich um.
- `native.css`: **ein Schalter statt doppelter Regeln** – Marke, Menü, Sprachen, Darstellungs-Knopf und Handlungsaufruf lesen
  `--ncss-nav-compact` (`--_d: var(--ncss-nav-compact) none; display: var(--_d, …)`); die Blöcke für den Zustand von
  `nav.js` und die Media Query 64rem entfallen. Geprüft mit und ohne JavaScript: frische Aufrufe 1600–320 px und Ziehen der
  Fensterbreite 1600 → 320 → 1600 – immer eine Zeile, je Richtung genau ein Wechsel, Menü per Tastatur.
- `fluid:kit:ncss-update`/`tools/ncss-update.php`: übernimmt auch `@import`-Zeilen ohne `layer()` – ab NativeCSS 1.3 legen
  die Dateien unter `components/` und `helpers/` ihren Layer selbst fest. Vorher fehlten damit Helfer und Komponenten in
  `css/ncss-base.css` (Kopfleiste zerfiel).
- Mit NativeCSS 1.3.0 außerdem: Vorher/Nachher-Regler ragt nicht mehr über den Rand, Scrollytelling ohne gedimmte Schritte
  (Kontrast), neutrale deutsche Texte im Darstellungs-Knopf, Texte der Player per `data-label-…` überschreibbar. fluid
  setzt keine der umbenannten Attribute (`value-text`, `data-label-motionhint` …) – nichts anzupassen. Das Bedienfeld
  „Darstellung“ im Kit fluid sieht trotz eigenem Layer der Komponente unverändert aus (Pixelvergleich 0 px).
- Handlungsaufruf „Band“ auf einem farbigen Abschnitt: Überschrift, Text und Zweit-Knopf in der Schriftfarbe des Abschnitts
  statt `--f-a-on` (im dunklen Schema auf getöntem Abschnitt sonst dunkel auf dunkel).

## 1.0.0-beta.4 – 2026-11-21

### Editor: Kontraste der Leiste und der Spaltenmarken

- Status-Chip in der Leiste („Veröffentlicht“, „Hinweis“): Text in eigener Statusfarbe (`--ok-ink`, `--warn-ink` in
  `editor.shadow.css`), hell #146c43/#8a5200, dunkel #5fd39a/#f0a64a – vorher 3,3–3,5:1 (axe `color-contrast`),
  jetzt ≥ 5:1 auf der getönten Fläche. Gleiche Farbe für „nicht im Menü“ im Seitenbaum.
- „+ Block in diese Spalte“ hat einen deckenden Grund und bleibt so auch auf dunklen Websites lesbar (vorher 3,1:1).

## 1.0.0-beta.3 – 2026-11-21

### Kit „NativeCSS“: Kopfleiste immer einzeilig

- **NativeCSS 1.2.2:** `ncss/js/nav.js` misst jetzt per ResizeObserver, ob Menü, Marke und Werkzeuge in **eine** Zeile
  passen, und setzt `data-ncss-nav-auto="inline|compact"` – kein fester Breakpoint mehr, das Menü bricht nie in sich um,
  eine Hysterese von 24 px verhindert Hin-und-her-Springen. Ohne JavaScript gilt weiter die Schwelle 64rem. Die
  Menü-Schaltfläche meldet `aria-controls`/`aria-expanded`, der Fokus wandert beim Umschalten mit. Vorher brach das Menü
  bei 1024–1440 px (langes Menü, Handlungsaufruf, offene Seitenleiste des Editors) in zwei Zeilen um.
- `native.css`: Regeln für Zeile und Menü-Schaltfläche je zweimal – über den Zustand von `nav.js` und, solange es keinen
  gesetzt hat, über die Media Query. Sprachen, Darstellungs-Knopf und Handlungsaufruf wandern **nur im Kompakt-Zustand**
  ins Menü; würden sie vorher verschwinden, passte das Menü wieder in die Zeile und die Leiste wechselte hin und her.
- **Handlungsaufruf im Menü** (`.nv-nav__cta`, wie `mnav__cta` im Kit fluid), sobald die Menü-Schaltfläche erscheint – vorher
  fehlte er schmal ganz. Der Knopf in der Leiste heißt jetzt `.nv-head-cta`; die alte Klasse `.nv-cta` kollidierte mit dem
  Block „Handlungsaufruf“ (gleicher Name, die Regel zum Ausblenden griff deshalb nie).
- Karten „Text auf dem Bild“: NativeCSS gibt `.ncss-card-media` ein Seitenverhältnis von 16:9 – das Bild füllte nur den
  oberen Teil der Karte. Jetzt füllt es die ganze Karte, der Text steht unten auf einem kräftigeren Verlauf.

## 1.0.0-beta.2 – 2026-11-20

### Kit „NativeCSS“: neue Komponenten von NativeCSS – wenige, klar benannte Funktionen

- **Video & Audio** (bisher „Video“, Modul `fluid.video` bleibt): neue Darstellung „Audio (Podcast, Musik)“ mit
  Audiodateien (Titel, Sprecher, Kapitel je Datei; ab zwei Dateien Playlist), Cover und Transkript („Text zum Audio“).
  Im Kit NativeCSS spielen eigene Videodateien im `<ncss-video-player>` (Kapitel in der Zeitleiste, Untertitel-Menü,
  Tasten, Vollbild), Audio im `<ncss-audio-player>`; im Kit fluid der Player des Browsers. YouTube/Vimeo unverändert.
  Die Felder für Video erscheinen bei „Audio“ nicht, „Text daneben“ nur bei „Video mit Text daneben“.
- **Vorher/Nachher** (`fluid.compare`, Gruppe Medien): zwei Bilder mit Regler (echtes `input range`: Maus, Touch,
  Tastatur, Screenreader), ohne JavaScript nebeneinander. Kit NativeCSS: `<ncss-compare>`; Kit fluid: `fluid_compare()`
  (bisher leer) mit `css/hx-compare.css` und neuem `js/compare.js`.
- **Einblend-Effekt** als Abschnitts-Option (nicht als Block): Keiner (Standard) · Sanft einblenden · Von unten · Kreis ·
  Vorhang – `.ncss-reveal` von NativeCSS, einmal beim Hereinscrollen. Nur für Besucher, nicht beim ersten Block, aus mit
  „Bewegung reduzieren“ und mit „Dezente Animationen“ aus im Design. Ein Kit bietet Effekte über `kit.php` → `'reveal'`
  an; ohne Angabe (Kit fluid) fehlt die Option.
- Kit NativeCSS, ohne neue Einstellungen: **Kennzahlen** zählen hoch (`.ncss-count-up`; nicht Jahreszahlen), **Logos
  „Laufband“** mit `.ncss-marquee` und Schaltfläche „Laufband anhalten“, **Scrollytelling** mit `<ncss-scrolly>`,
  **Hintergrundvideo** im Einstieg „Vollflächig“ mit `js/video-bg.js` von NativeCSS – lädt erst bei Bedarf, spielt nie
  bei „Bewegung reduzieren“ oder „Daten sparen“, Schalter „Pause/Abspielen“ immer sichtbar (vorher fehlte er im Kit
  NativeCSS, weil das Skript von fluid ihn nur in `.hero-bleed` suchte).
- Barrierefreiheit beim Bearbeiten: Hinweis bei Vorher/Nachher-Bildern ohne Alternativtext (klxm_meta, „dekorativ“
  zählt) und bei eigenen Videos ohne Untertitel; Hilfetexte zu Untertiteln, Transkript und Titeln.
- **Nur laden, was die Seite braucht:** `css/ncss-base.css` (erzeugt) ersetzt `ncss/ncss.css` im Seitenrahmen – ohne die
  großen neuen Komponenten; die kommen je Block über `kit.php` → `assets` (`css/ncss-*.css` mit `layer(…)`, Skripte aus
  `ncss/js/`). Video-Player-Dateien nur bei eigener Videodatei, nicht bei YouTube.
- **`fluid:kit:ncss-update`** (bzw. `php tools/ncss-update.php` im Repo): NativeCSS aus einer Arbeitskopie (`--from`,
  optional `--ref`) oder von GitHub (`--tag`) übernehmen, mit Übersicht (neu/geändert/entfernt), `--dry-run`,
  `VERSION.txt` mit Stand und Commit; erzeugt `css/ncss-base.css` neu. Eigene Dateien des Kits bleiben unberührt.
- Demo „REDAXO 6“: Seite Video mit eigenem Video (Kapitel, Untertitel de/en, Transkript) und Audio (Playlist, Kapitel,
  Transkript), Seite Bausteine mit Vorher/Nachher und Einblend-Effekten (Bento, Vorher/Nachher), englische Texte.
  Medien: Kurzfilm, Untertitel, Kapitel, Musik aus der Demo von NativeCSS (MIT), Foto von Curtis Adams (Pexels-Lizenz) –
  Nachweis in den Bildunterschriften, Titeln im Medienpool und im README.
- **NativeCSS 1.1.0** (0c4f60d): u. a. Darstellungs-Einstellungen `<ncss-preferences>`, Lese-Schriften mit OFL-Lizenzen
  (`ncss/fonts/`, kopiert der Update-Befehl jetzt mit), Kontrast-Tokens, `data-motion` in den Skripten, „Klick daneben
  schließt“ für Dialoge (`js/dialog-light-dismiss.js`, im Kit für das Menü geladen). Neue Komponenten ohne Block in fluid
  (Tabs, Tooltip, Pagination, Alert …) stehen nicht im Manifest `css/ncss-base.css`. Der Update-Befehl liest die
  Version aus `package.json`.
- **Darstellungs-Knopf für Besucher** (Kit NativeCSS, Website → Kit, Standard aus; Lese-Schriften abschaltbar):
  `<ncss-preferences>` im Kopf (breit in der Leiste, schmal im Menü), `preferences-early.js` vor den Stylesheets,
  CSS/JS nur bei eingeschalteter Option. Die Wahl wirkt auch auf die Blöcke von fluid: Farbschema über die dunklen
  Design-Werte (`Design` mit `'dark' => ['attr' => 'data-theme']`) und die Klassen `has-dark`/`is-dark`
  (`js/prefs-theme.js`), Kontrast und Lese-Schrift über `--f-…`.
- Kit NativeCSS: Kopfleiste bleibt auch bei 360 px einzeilig (Marke schrumpft); Kennzahlen wie „6.x“ zählen nicht hoch.
- NativeCSS 1.1.0 auf 8701f90: Morphing (`components/morph.css`, bei Bedarf), Karten-Ausbau und A11y-Korrekturen
  (Audio-Player, Laufband, Scrollytelling-Kontrast, „Mehr“-Menü); `<ncss-map>` nicht übernommen – fluid hat einen
  eigenen Karten-Block mit Proxy.
- **Text + Bild „Morphing beim Scrollen“** (nur Kit NativeCSS, über `kit.php` → `blocks`): `.ncss-morph`, beim Bearbeiten
  und bei „Bewegung reduzieren“ als Bild neben Text. Im Kit fluid fehlt die Darstellung in der Auswahl; gespeicherte
  Blöcke erscheinen dort wie „Bild rechts“.
- **Darstellungs-Knopf für Besucher auch im Kit fluid:** die Schalter stehen jetzt unter Website → Kopf, Fuß & Hinweis
  (beide Kits, Standard aus). Im Kit fluid: `preferences.js`/`-early.js` und das Bedienfeld aus dem Kit NativeCSS,
  Tokens und Wirkung (Kontrast, Lese-Schrift, Bewegung, Schriftgröße, Textabstände, Links, Fokus) in `css/prefs.css`,
  Bedienfeld in den Farben der Seite auch im farbigen Kopf; `js/prefs-theme.js` (jetzt im Kit fluid, für beide) gleicht
  `is-dark`/`has-dark` und bei „Bewegung: Reduziert“ `has-motion` ab; dunkle Design-Werte per `Design::head(true)` auch
  beim Standard-Design. Knopf in der Kopfleiste, sobald die Navigation dort steht, sonst im Menü.
- **Karten „Hervorheben“** (beide Kits): je Karte Rahmen in der Akzentfarbe und ein Hinweis an der Ecke (Text
  bearbeitbar, Standard „Empfohlen“); NativeCSS `.ncss-card--featured` + `.ncss-card-badge`, im Kit fluid eigenes CSS.
- **„Video & Audio“ mit Morphing beim Scrollen** (nur Kit NativeCSS): eigene Videodatei stumm in Schleife in
  `.ncss-morph`, Pause-Schalter und Laden wie beim Hintergrundvideo (`js/video-bg.js`), statisch beim Bearbeiten und bei
  reduzierter Bewegung; im Kit fluid ausgeblendet.
- Demo „REDAXO 6“: Morphing-Beispiele (Text + Bild auf „Bausteine“, Video auf „Video“). Doku: nach dem Update-Befehl
  `console migrate`.
- **NativeCSS 1.1.0 auf 8bb3e3f (Runde 2)** – neue Komponenten als Optionen bestehender Blöcke, keine neuen Blöcke:
  - **Tabellen** (beide Kits): Datenliste „Tabelle“ und Paketvergleich scrollen waagerecht mit Schatten an der Kante;
    Datenliste „Spalten zum Sortieren anklickbar“ (`js/table-sort.js` von NativeCSS, auch im Kit fluid). Fließtext kennt
    keine Tabellen – dort keine Option. Paketvergleich ohne Sortieren (Zeilen sind Merkmale, keine Daten).
  - **Filter nach Kategorie** (beide Kits): Karten („Kategorie“ je Karte, Option am Block) und Datenliste („Filter für
    Besucher nach …“, ein Feld der Tabelle) – Optionsfelder als Pillen, Filtern per CSS, Trefferzahl per `js/filter.js`.
    Beiträge haben das schon (Schlagworte).
  - **Pakete „Preisliste (Speisekarte)“** (beide Kits): Name, Führungspunkte, Preis, Beschreibung – NativeCSS
    `.ncss-leaders`, im Kit fluid eigenes CSS.
  - Kit NativeCSS: Pakete „Karten“ als Preistabelle (`.ncss-pricing`, Subgrid, „Nicht enthalten“ mit „–“ am Zeilenanfang;
    kein Monatlich/Jährlich-Umschalter – der Block kennt einen Preis), Ablauf „Zeitleiste“ (`.ncss-timeline`), Zitat
    „Groß“/„Raster“ als Kundenstimmen (`.ncss-testimonial`), Sprachwahl ab drei Sprachen als Aufklapp-Liste (Dropdown).
  - Bewusst nicht übernommen: Slideshow-Pfeile/-Punkte (der Slider von fluid hat beides), Stepper (Fortschritt, keine
    Anleitung), sortierbare Liste, Kommentare, Toolbar, Pillen-Gruppe.
  - Demo „Bausteine“: Karten mit Filter und „Hervorheben“, Preisliste, Zeitleiste, Kundenstimmen, sortierbare Tabelle.

## 1.0.0-beta.1 – 2026-11-18

Erste Fassung für REDAXO 6 (`redaxo/core` 6.x, YForm 6).

### Demo: Abschnitte & Spalten

- Neue Seite im Kapitel fluid mit Beispielen: Abschnitte mit Hintergründen, Spalten ½ + ½ (mittig), ⅓ × 3 (gestreckt,
  Karten durch Hintergrund), ⅔ + ⅓ (gestreckt, mobil umgekehrt), ¼ × 4 (schon auf Tablets untereinander), Bento als
  Raster, Kacheln als flexibles Raster, „Im Backend“ (Galerie mit Raster, Baum, Menü ⋯ und Overlay, Demo-Bilder
  fluid-backend-*.jpg) und „So baut man es“ (freie Grid-/Flex-Container sind geplant).

### Keine Inline-Styles (Content-Security-Policy)

- `InlineStyle`: Werte, die sich nicht über feste Klassen abbilden lassen (Fokuspunkt eines Bildes, Rasterbreite …),
  bekommen eine Klasse; die Regeln stehen gesammelt in einem `<style nonce>` im Kopf der Seite (OUTPUT_FILTER, Website
  und Backend). `img()` des Kits, Kit native (Raster, Termine), Beispielblöcke `Hinweis`/`Teaser` nutzen sie.
- Beispielmodule `Hinweis`/`HinweisEigenesFormular` (ohne fluid): Klassen und einmal je Seite ein `<style nonce>` mit
  `Response::getNonce()` – im Backend wurden ihre Vorschauen bisher ohne Gestaltung gezeigt (CSP blockte die
  style-Attribute). Nonce auch für die `<style>`-Blöcke im Kit native und auf der Willkommensseite.
- Editor auf der Website: Farbfelder und Einrückung im Seitenbaum über CSSOM statt style-Attributen im Markup.
- E-Mails behalten Inline-Styles (Mailprogramme laden keine Stylesheets, dort gilt keine CSP).

### Kopieren, Ausschneiden, Einfügen, Verdoppeln – auch im Backend

- Jeder Block – fluid-Blöcke und gewöhnliche Module des Cores – hat in der Slice-Liste ein Menü „⋯“: Verdoppeln (auch ohne Skript), Kopieren, Ausschneiden und
  „Einfügen darunter“. Dieselbe Zwischenablage wie auf der Website (Browser, seitenübergreifend): auf der Website
  kopieren, im Backend einfügen und umgekehrt. `Backend\BlockOpsApi` (API-Funktion `fluid_block_ops`, CSRF, Rechte auf
  Seite, Modul und Template).
- Einfügen hinter einem Spalten-Block landet in derselben Spalte (`BlockOps::insertAfter()`), Verdoppeln ebenso.
- Ausschneiden (Backend und Website): Das Original wird erst nach dem Einfügen entfernt (`BlockOps::finishCut()`) – es
  geht nichts verloren; danach bleibt der Inhalt als Kopie in der Zwischenablage.
- Spalten-Blöcke: schwebende Leiste mit Titel-Reiter, Bearbeiten, ↑ ↓ und „⋯“ (Status, Löschen, Zwischenablage).

### Backend: Layout als Raster wie auf der Website

- In der Slice-Liste stehen die Blöcke eines Layouts jetzt im Layout-Panel nebeneinander in ihren Spalten (Breiten wie
  auf der Website), jeder mit Kopf und Knöpfen des Cores – weiter echte Slices. Je Spalte „+ Block in Spalte n“;
  „Block hinzufügen“ in einer Spalte gibt die Spalte mit (`fluid_col`, im Formular vorgewählt). Wird ein Spalten-Block
  bearbeitet oder über „+ Block in Spalte n“ angelegt, öffnet das Formular des Cores in einem Dialog über dem Raster
  (Esc/✕ wie „Abbrechen“; auf dem Telefon bildschirmfüllend), ohne Skript stehen die Spalten untereinander. Schmaler
  Platz: Container Query auf das Layout-Panel – je Spalte mind. ~190 px (2 ab 420, 3 ab 620, 4 ab 820 px), sonst
  untereinander; schmale Spalten zeigen die Vorschau verkleinert. Hell und dunkel
  über `light-dark()`.
- Vorschauen in der Slice-Liste in der Höhe begrenzt (360 px, in Spalten 300 px) und in sich scrollbar – unten
  ausgeblendet, „Ganze Vorschau“ klappt auf; überlaufende Vorschauen sind per Tastatur erreichbar (für alle Blöcke und
  Module, nicht bei geöffnetem Formular).
- Layout offline: Seine Spalten-Blöcke sind auf der Website ebenfalls nicht sichtbar (nur das Layout gibt sie aus) –
  das zeigt jetzt ein Hinweis am Layout und an jedem Spalten-Block; die Blöcke sind leicht gedämpft, bei Zeiger oder
  Fokus voll da. Im Raster bleiben sie lesbar und
  bedienbar: Schleier und Klicksperre des Cores für Offline-Slices trafen bisher auch die Spalten.
- Umschalter „Spalten | Baum“ je Layout (gemerkt im Cookie `fluid_view`): „Baum“ zeigt die Rasterskizze und die
  Spalten-Blöcke eingerückt am Ast darunter – für viele Spalten oder schmale Bildschirme.
- Von außen, ohne den Core zu ändern: `Backend\SliceGrid` sammelt die Panels über `SliceOutput` ein, das Fragment
  `core/structure/content/slice_list.php` (in `fragments/` des AddOns) setzt sie ins Raster, `ContentPageModuleSelect`
  ergänzt die Spalte. Ohne Raster (z. B. anderes Fragment) bleibt die Baumansicht.

### Übersicht bei verschachtelten Blöcken

- Backend: Spalten-Blöcke fehlten in der Slice-Liste ganz – fluid hat im Bearbeiten-Modus des Backends den Extension
  Point `SliceOutput` (dort das ganze Panel des Cores) wie auf der Website behandelt. Behoben; jetzt listet ein Layout
  seine Spalten mit den Blöcken darin („Spalte 1: Diagramm „…““), und die Spalten-Blöcke stehen eingerückt mit Linie und
  „Spalte 1 von 2 · im Layout „½ + ½“ darüber“.
- Website (Bearbeiten-Modus): Spaltenmarke oben an jeder Layout-Spalte, die Leiste eines Spalten-Blocks zeigt
  „Layout › Spalte 1/2 · Block“ – „Layout ›“ wählt das Layout aus.

### Seitenwechsel: Überblenden und Morphen (optional)

- Neue Design-Einstellung „Seitenwechsel“ im Kit fluid (Bewegung & Farbschema, Standard „Wie gewohnt“): „Überblenden“
  (Kopf bleibt stehen, Inhalt blendet über) oder „Morphen“ (angeklicktes Bild wird zum großen Bild der Zielseite, ein
  kurzer Linktext zur Überschrift; zurück umgekehrt). Cross-Document View Transitions (`@view-transition`), Chrome, Edge,
  Safari ab 18.2 – andere Browser wechseln wie gewohnt. Nur mit Animationen, nie bei „Bewegung reduzieren“, nicht im
  Bearbeiten-Modus (aus KLXM Studio, Kits foto/galerie).

### Bildergalerie und Slider

- Block „Bildergalerie“ (`fluid.gallery`): Raster, Mosaik oder bündige Zeilen, Bilder als `Collection` mit
  Bildunterschrift, Lightbox als `<dialog>` (Fokus bleibt im Dialog und kehrt zurück, ←/→, Wischen, Esc, Zähler); ohne
  JavaScript führen die Bilder zur großen Fassung.
- Block „Slider“ (`fluid.slider`, Darstellungen Schieben/Überblenden): Muster „Carousel“ von WAI-ARIA – Region mit
  `aria-roledescription`, Folien als Gruppe „n von m“, nicht sichtbare Folien `inert`, Pfeile, Punkte, Tastatur
  (←/→, Pos1/Ende), Wischen. Automatisch weiter nur mit Pause-Knopf, hält bei Maus und Fokus, nie bei „Bewegung
  reduzieren“; ohne JavaScript ein wischbarer Scroll-Snap-Streifen (aus KLXM Studio, `media.mjs`).

### Rechtstexte zentral, Datenschutz im Dialog (optional)

- `Legal\Legal`: Impressum, Datenschutz und Barrierefreiheit aus Website → Einstellungen → „Recht“ an einer Stelle
  (`Legal::url()`, `Legal::links()`, `Legal::article()`), in der Sprache der Seite; die Helfer des Kits
  (`privacy_url()`, `fluid_legal_links()`) nutzen sie.
- `Legal\LegalDialog` (optional, Standard aus): Der Link „Datenschutzhinweise“ unter Formularen öffnet die
  Datenschutzerklärung im Dialog über dem Formular – Eingaben bleiben erhalten. Einschalten in `Project::configure()`:
  `Legal::$config = new LegalConfig(dialog: true)`. Endpunkt `/_legal/privacy` liefert den bereinigten Text der Seite;
  ohne JavaScript bleibt es ein Link (aus KLXM Studio).

### Ziel bearbeiten an Karten und Kacheln

- `$b->targetEdit($link, $titel)` (bisher ohne Funktion) zeigt im Bearbeiten-Modus an Karten und Kacheln, die auf eine
  Seite oder einen Datensatz zeigen, „Seite“ bzw. „Datensatz“: die Seite öffnet im Bearbeiten-Modus (ungespeicherte
  Änderungen werden vorher geklärt), der Datensatz in der YForm-Maske im Overlay. Nur mit Recht auf die Kategorie bzw.
  die Tabelle; Besucher bekommen kein Markup (aus KLXM Studio, `TargetEdit`).

### Diagramme

- Neuer Block „Diagramm“ (`fluid.chart`): Balken, waagerechte Balken, Linie, Fläche, Torte, Ring – Werte von Hand
  (Reihen als `Collection`) oder aus einer Datenquelle (`#[AsChartSource]`), Tabelle der Werte für Screenreader und
  ohne JavaScript, Darstellungen „standard“ und „split“.
- `Chart::render()` für Templates, Module und AddOns; Engine austauschbar (`#[AsChartEngine]`, `ChartConfig`),
  Standard Apache ECharts, selbst gehostet, lädt erst in Sichtweite (aus KLXM Studio – `Core\Charts`).
- Demo: Seite „Diagramme“ im Bereich fluid, Quelle „Blöcke je Art“ des Kits zählt live aus der Datenbank.

### Versionen: Live-Schaltung = Stand, Entwurf = Zwischenstände

- Jede Live-Schaltung legt den Stand an, der live geht („Veröffentlicht“, Archiv). Vorher ein Stand der alten
  Live-Version nur, wenn sie noch nicht im Archiv liegt; unverändert erneut veröffentlicht → kein neuer Stand.
- Zwischenstände der Arbeitsversion (Speichern, vor Verwerfen/Wiederherstellen) werden bei der Live-Schaltung geleert –
  sie waren nie online. Seitenleiste und Zeitleiste zeigen Entwurf und Archiv getrennt.
- Stände in derselben Sekunde liefen unter einem Zeitpunkt zusammen (Wiederherstellen hätte Blöcke doppelt eingefügt) –
  fluid weicht jetzt auf die nächste freie Sekunde aus.

### Bearbeitungssperre

- Optional (Website → Einstellungen → Redaktion, Standard aus): Eine Seite bearbeitet immer nur eine Person – wer sie
  zuerst im Bearbeiten-Modus hat. Alle anderen sehen sie nur an (Hinweis unter der Leiste, „Jetzt bearbeiten“, sobald
  sie frei ist); Speichern über Editor, Seitenleiste und MCP lehnt fluid mit 423 ab. Administratoren übernehmen, die
  bisherige Person erfährt es sofort. Abgeleitet aus der Präsenz, ohne Tabelle und ohne hängende Sperren (aus KLXM
  Studio, `EditLock`). Selbsttest in `fluid:selftest`.
- Präsenz: Die Live-Aktualisierung von Seitenbaum und „Gerade aktiv“ lief ins Leere (Funktion `lists()` durch eine
  Konstante verdeckt) – behoben. Der Bearbeiten-Modus wurde am Parameter `?edit` statt `?fluid_edit` erkannt.

### Blöcke wie Module des Cores

- **Ein Block ist ein Modul des Cores mit anderer Basisklasse:** Felder als Attribute des Cores, Ausgabe in `output()`,
  der Core befüllt die Eigenschaften (typisiert: `?Media`, `DateTimeImmutable`, Objekte einer `Collection`). Vom Modul
  zum Block: `FluidBlock` statt `Module`, Schlüssel `fluid.…`, `#[AsBlock]` – Felder und `output()` bleiben.
  `$this->edit('feld')` für das Bearbeiten auf der Seite. Ohne eigenes `output()` die View des Kits (`$b`, `$d`, `$m`).
- **Felder:** die des Cores, oder die gleichnamigen von fluid (`Klxm\Fluid\Field\…`) als Unterklassen mit
  zusätzlichen, optionalen Parametern: `width`, `variants`, `placeholder`, `search`, bei `Collection` `itemLabel` und
  `titleField`. Felder nur von fluid: `RichText`, `Link`, `Icon`, `Geo`, `MediaCategory`, `Blog`, `YFormTable`,
  `YFormForm`, `YFormField` – auch in gewöhnlichen Modulen nutzbar. Neu in der Seitenleiste: `Email`,
  `DateTimePicker`, `ArticlePicker`, Zahl mit `min`/`max`/`step`, Auswahl mit Platzhalter und Gruppen.
- **`Repeater` → `Collection` des Cores** (Eintrag = Klasse mit eigenen Feldern); `Media`/`File` → `MediaPicker`
  (`preview: true` = Bildfeld). Die 17 Wiederholgruppen der Blöcke haben Eintragsklassen in `Kit\Fluid\Module\Item`.
- **Speicherung wie beim Core:** je Feld unter seinem Schlüssel in `data`, dazu `variant` und `tunes` (vorher
  `{values, tunes}`). `console fluid:upgrade:slices` stellt vorhandene Slices samt Historie um; gelesen wird das alte
  Format weiterhin. Verweise (Medien, Seiten) kommen jetzt von den Feldern des Cores bzw. `Link`/`RichText`.
- Formular im Backend benennt die Eingaben wie der Core nach Eigenschaft (`slice[moreLink]`) – vorher hätte ein
  Speichern im Backend Felder mit zusammengesetzten Namen verloren.
- **Gewöhnliche Module** zeigt die Seitenleiste mit derselben Abbildung – jetzt auch mit `Collection` und den Feldern
  von fluid; gespeichert und geprüft vom Formular des Cores.
- Entfernt: `view(Block $b, array $d)` und `{Klasse}.view.php` (→ `output()`), `Field\Field`, `Field\Repeater`,
  `Field\Media`, `Field\File`, `Form\BlockValues` (→ internes `Form\Stored`), Felder anhängen über das Kit
  (Felder gehören als Eigenschaften in die Klasse).

### Adressen (Rewriter)

- **Für alle AddOns:** Einträge eigener Quellen (News, Termine, Produkte …) bekommen eine Adresse unter ihrer Seite –
  aus einer Tabelle nur mit Attributen (`#[AsRouteSource]`, `#[FromTable]`, `#[Slug]`, `#[Title]`, `#[Description]`,
  `#[Image]`, `#[Modified]`, Klasse `TableSource`) oder als eigene `RouteSource`. Gespeichert, eindeutig gegen Seiten,
  301 nach einer Änderung, in der Sitemap mit hreflang und Bildern, mit Canonical, hreflang und Open Graph im Kopf.
  `Router::urlFor()`, `Routes::rebuildSource()`, Konsole `routing:rebuild`.
- **Adressformat** im Projekt: ohne Endung, „/“, „.html“, gemischt (Menüpunkte „/“, Seiten „.html“) oder eigene Endung
  (`Router::$config = new RoutingConfig(format: UrlFormat::mixed())`). Jede andere Schreibweise leitet per 301 um – eine
  kanonische Adresse je Seite, an einer Stelle geprüft (Groß-/Kleinschreibung, Schrägstrich, Endung, Sprachpräfix).
- **Seitenkopf** für Seiten und Einträge gleich (`Routing\Seo\Head`): neu `og:url`, `og:type`, `og:locale` (+ alternate),
  `x-default`, `article:*_time`, `twitter:card` (nur dieses – den Rest liest X aus Open Graph), Bildmaße aus dem
  Medienpool. Eigene Tags über `HeadConfig::$tags`, `Head::add()` oder `PageMetaResolved` (gleicher Schlüssel ersetzt).
- Felder eines Eintrags (Titel, Beschreibung, Bild, Adresse) in den **Seiteneinstellungen der Detailseite** bearbeitbar;
  YForm-Tabellen über die Record-API.
- Detailseiten der Datenlisten: Titel, Beschreibung, Bild, Canonical und hreflang kommen jetzt vom Resolver (unabhängig
  von der Reihenfolge beim Rendern), Brotkrumen und JSON-LD mit der Adresse des Eintrags. Mit Endung `.html` entstand
  vorher `/daten.html/titel-5`.
- Cache: Adressen der Seiten und jeder Quelle als PHP-Datei (OPcache) statt einer Abfrage aller Adressen je Anfrage.
- REDAXO-6-Stil: Auflösen bei `PACKAGES_INCLUDED` (Quellen und Endpunkte von AddOns, die nach fluid starten, gelten
  jetzt auch), Erweiterungen per `#[AsExtension]`, eigene Adressen per `#[AsEndpoint]`, Extension Points als Klassen
  (`ArticleMeta`, `PageMetaResolved`, `SitemapEntries`, `RobotsTxt`, `RoutesRebuilt` statt `FLUID_SEO_HEAD`,
  `FLUID_SITEMAP`, `FLUID_ROBOTS`, `FLUID_ROBOTS_RULES`, `FLUID_ROUTES_REBUILT`). `Router::endpoint()` ohne `afterPackages`,
  Resolver geben `RouteMatch` statt einer Seiten-ID zurück.
- `RoutingConfig(enabled: false)` schaltet den Rewriter ab (z. B. für yrewrite). `src/Routing/README.md`: Herauslösen als
  eigenes AddOn und Abbildung auf yrewrite + URL-AddOn.
- Demo: Seite „Adressen“ im Kapitel fluid.

### Für Redakteure

- Datensätze aus YForm auf der Website bearbeiten:
  - „Eintrag bearbeiten“ und „+ Neuer Eintrag“ öffnen die Maske von YForm im Overlay statt in einem Popup-Fenster.
    „Speichern“ schließt es und gibt den Block neu aus, „Übernehmen“ bleibt in der Maske.
  - In der Detailansicht lassen sich Titel und Felder direkt auf der Seite ändern: Text, Internetadresse, E-Mail, Datum,
    Uhrzeit, Zahl, Auswahl, Ja/Nein, Bild (Medienpool) und Seitenlink (Linkmap). Gespeichert wird sofort, mit den Prüfungen
    von YForm; Esc nimmt eine Änderung zurück. Ein geänderter Titel ändert die Adresse der Detailseite.
  - Auf einer Detailseite bleiben „Bearbeiten“, „Ansehen“ und „Arbeitsversion“ in der Leiste beim Eintrag (vorher Sprung
    zur Liste); Blöcke werden mit dem Eintrag neu ausgegeben.
  - Der Knopf „Eintrag bearbeiten“ sitzt an Karten unten – die Bildwerkzeuge verdeckten ihn.
  - Weiterleitungen wegen eines geänderten Titels speichert der Browser nicht mehr (ein zurückbenannter Eintrag führte
    sonst in eine Schleife).
- Seitenbaum: „Nicht im Menü zeigen“ blendet einen Menüpunkt nur aus dem Menü aus – die Seite bleibt online, an ihrer
  Stelle, mit ihren Unterseiten und in der Sitemap (wie in KLXM Studio). Gespeichert im Metafeld „Nicht in der
  Navigation zeigen“ von navigation_array (auch im Backend unter Kategorie bearbeiten). Vorher wurde der Menüpunkt zur
  Seite ohne Menüeintrag umgewandelt und sprang dabei in der Reihenfolge; mit Unterseiten ging es gar nicht.
  Im Baum: ☰ schräg durchgestrichen und „nicht im Menü“ in Warnfarbe.
- Seitenbaum: Status farbig – online grün ●, offline grau ○, gesperrt rot ● (weiterer Status eines AddOns, mit dessen
  Bezeichnung). Nur online steht in Menü und Sitemap.
- Menüs der Seite (Kopf, Menü-Blatt, Fuß) passen sich sofort an, wenn im Seitenbaum etwas online/offline geschaltet,
  aus dem Menü genommen, verschoben oder gelöscht wird – ohne Neuladen.
- Gewöhnliche REDAXO-Module (ohne fluid-Felder) lassen sich auf der Website pflegen: Im Bearbeiten-Modus zeigt ihre
  Ausgabe „… bearbeiten“, die Seitenleiste zeigt ihre Felder im Formular von fluid (Text, Textbereich, E-Mail, URL,
  Zahl, Ja/Nein, Auswahl, Datum, Uhrzeit, Bild, Seitenlink – mit Medienpool und Linkmap der Website), „Übernehmen“
  prüft wie der Core (Pflichtfelder, Länge, gültige Werte, `validate()` des Moduls), speichert und erneuert die Ausgabe
  auf der Seite. Module mit eigenem `input()` oder anderen Feldtypen zeigen das Formular des Cores in der Seitenleiste. Das Template „fluid“ erlaubt dafür neben den Blöcken des Kits auch gewöhnliche
  Module (vorher lehnte der Core das Speichern solcher Slices ab – auch im Backend).
- YForm-Einträge mit Sprache (Feld `FluidLanguage`): Datenliste, Detailansicht und Sitemap zeigen nur Einträge der
  Seitensprache und solche ohne Sprache.
- Formulare aus YForm übernehmen die Breiten des Layouts (Designer oder `layout()`) auch auf der Website.
- Formular-Baukasten: Felder Uhrzeit, Datum und Uhrzeit, Kommazahl.
- Block „Code“ für Codebeispiele (eine oder mehrere Dateien, mit Kopier-Knopf); `<code>` im Rich Text.
- Menü ⋯ → „Abmelden“: meldet aus REDAXO ab, die Seite bleibt als Besucher offen.
- Menü-Blatt (Off-Canvas) mit Unterseiten zum Aufklappen, wie in KLXM Studio (vorher nur die erste Ebene).
- Kopf: Der aktive Eintrag im Untermenü ist auf farbigem Kopf lesbar; die Kopfzeile misst nach dem Laden der Webschriften
  neu (Menü und Menü-Knopf wechselten sonst zufällig).
- Die Knöpfe der Hinweis-Leiste wirken auch in der Ansicht (z. B. „Zurücksetzen“ und „Demo beenden“ von `fluid_demo`).
- Block-Formulare im Backend im Stil von REDAXO 6: Felder, Auswahllisten, Checkboxen und Knöpfe des Cores,
  Wiederholgruppen wie die Sammelfelder des Cores (Kopfzeile mit Titel und Knopfgruppe, aufklappbar), Farben passend
  zum hellen und dunklen Theme.
- Medien und Seiten auswählen ohne Popup-Fenster: im Backend Medienpool und Linkmap des Cores als Overlay, auf der
  Website eine eigene Auswahl im gleichen Aufbau – Medien mit Kategorien, Suche und Hochladen, Seiten als Linkmap
  (Kategorien links, Artikel rechts). Es gelten die Rechte von Medienpool und Struktur.
- Teilen in sozialen Netzwerken: Das Vorschaubild hat jetzt das Format der Netzwerke (1200 × 630, um den Fokuspunkt,
  JPEG – LinkedIn zeigt mit WebP nichts), mit Alt-Text. Neue Option (Standard aus): Linkvorschau beim Teilen erlauben,
  auch wenn der robots.txt-Text Suchmaschinen aussperrt – etwa vor dem Start einer Live-Website.
- Sitemap: Bilder je Seite, Änderungszeit mit Uhrzeit, im Browser als Tabelle lesbar (`/sitemap.xml`).
- Schnellerer Seitenaufbau: Das erste Bild im ersten Block lädt sofort und mit Vorrang.
- Blockauswahl wie in KLXM Studio: oben „Häufig verwendet“ (die meistgenutzten Blöcke der Website), darunter alle
  nach Bereich in fester Reihenfolge; beim Suchen verschwinden leere Bereiche.
- Seitenleiste: Schalter „Auf der Website sichtbar“ ganz oben, mit deutlichem Hinweis, wenn der Block ausgeblendet ist –
  schaltet sofort, ungespeicherte Eingaben im Formular bleiben.
- Redaktionsnotizen `[# … #]`: Klick im Bearbeiten-Modus → „Als Text übernehmen“ oder „Notiz löschen“.
- Umschalt+Enter im Rich-Text: Zeilenumbruch statt neuem Absatz (auch in Safari).
- Bearbeiten auf der Website: Texte direkt auf der Seite, Seitenleiste je Block, Blöcke einfügen, verschieben (Ziehen
  oder Pfeile), verdoppeln, kopieren und einfügen, online/offline; Greifer für Spalten, Abstände, Textbreite und Aufteilung.
- Arbeitsversion: Entwurf speichern, veröffentlichen, verwerfen; Versionen wiederherstellen; Entwürfe-Übersicht und Aktivität.
  Beim Verlassen mit unveröffentlichtem Entwurf fragt fluid nach.
- Bilder in festen Formaten (16:9, 4:3 …) mit dem AddOn image_editor:
  - Ausschnitt je Format, wie in KLXM Studio: Knopf „Ausschnitt“ am Bild.
  - Ausgabe genau im Format (eigener Ausschnitt oder um den Fokuspunkt).
- Bildknöpfe erscheinen auch bei Karten, deren Link über dem Bild liegt.
- MCP-Server für KI-Werkzeuge (`/fluid-mcp`, Backend → fluid → MCP, standardmäßig aus):
  - Zugangsschlüssel je Benutzer mit „nur lesen“ oder „lesen und ändern“, optional veröffentlichen.
  - Datentabellen werden einzeln freigegeben.
  - Optional öffentlicher Lesezugang; welche Inhalte, legt der Admin fest.
  - Änderungen landen im Entwurf, mit Versionen und Aktivität.
- Versionen wie in KLXM Studio:
  - Zeitleiste nach Tagen mit Uhr und Kalenderblatt; je Stand, was sich an den Blöcken geändert hat.
  - Vorschau des Stands als echte Seite (Desktop/Mobil), „Änderungen hervorheben“.
  - „Gegenüberstellen“ mit der Live-Fassung, gemeinsames Scrollen.
  - „Wiederherstellen“ in den Entwurf; Tastatur ↑/↓/Pos1/Ende/Esc.
- Wer arbeitet gerade wo (aus KLXM Studio):
  - Die Leiste zeigt, wer die Seite gerade bearbeitet, sonst wer zuletzt gespeichert hat.
  - Hat jemand anderes seit dem Öffnen gespeichert: ⚠ in der Leiste und Rückfrage vor dem Speichern. Es gibt keine
    Sperre, der vorige Stand bleibt als Version.
  - Kürzel im Seitenbaum, „Gerade aktiv“ unter Verlauf → Aktivität.
- Datenliste, Beiträge und Suche: Blättern, Schlagworte und Treffer beim Tippen (nach einer kurzen Pause, ab 3 Zeichen) ohne Seitenwechsel. Adresse,
  Zurück-Taste und Screenreader-Ansage gehen mit, ohne JavaScript bleibt alles wie bisher.
- Beiträge „Aufmacher + Karten“: Der Link des Aufmachers verdeckte die Schlagwort-Filter darüber.
- Kopf: Die Suchseite erscheint nicht mehr zusätzlich als Menüpunkt, wenn die Lupe im Kopf zu ihr führt.
- Schrift Exo (selbst gehostet, variabel, OFL) für Überschriften; die Design-Vorlage „REDAXO · Blau“ nutzt sie wie redaxo.org.
- Design: Symbole „Dünner Rahmen, zweifarbig“.
- Sprachwahl im Kopf: ab drei Sprachen ein Menü (aktuelle Sprache, Liste mit Namen; Esc und Klick daneben schließen).
  Im Handy-Menü und im Fuß bricht die Liste bei vielen Sprachen um, statt über den Rand zu laufen.
- Suche zeigt nur Öffentliches:
  - Empfänger, Betreff und Bestätigungstexte von Formularen kommen nicht mehr in den Index, ebenso Platzhalter und
    Filterwerte.
  - Blöcke in einem Offline-Layout fehlen.
  - Der Index wird auch nach Änderungen im Backend und ohne Arbeitsversion neu aufgebaut.
  - Jeder Treffer wird vor der Ausgabe geprüft (Seite online, Sprache online, nicht „nicht indexieren“).
- Seiten: Seitenbaum (Ziehen, ↑ ↓ ← →, auch am Handy), neue Seiten leer, als Kopie oder aus einer Vorlage, Seiteneinstellungen
  mit Adresse und SEO-Angaben.
- Website: Stammdaten, Kontakt und Öffnungszeiten, Kopf, Fuß und Hinweisleiste, Rechtliches, Sprachen, Domains,
  Weiterleitungen; Website-Texte je Sprache.
- Mehrsprachigkeit: Sprachwahl in der Leiste, „Inhalt aus … übernehmen“, Sprachwahl für Besucher, Kit-Texte übersetzt (EN).
- Videos mit automatischem Vorschaubild (ffmpeg), Untertiteln und Kapiteln (.vtt/.srt) und Transkript; YouTube/Vimeo
  mit Zwei-Klick-Lösung.
- Blog / News: Menüseite als Blog, Beiträge mit Datum, Autor, Schlagworten; Block „Beiträge“ (Aufmacher, Karten, Liste,
  Blättern, Schlagworte), Beitragskopf und -navigation, RSS-Feed, „+ Neuer Beitrag“, Beiträge im Seitenbaum eingeklappt.
- Blöcke von YForm-Einträgen direkt auf der Website bearbeiten (Detailansicht der Datenliste, sofort gespeichert); Demo „Projekte“
  (Fallstudien aus Blöcken, `ProjectTable`).
- YForm-Feld „Fluid-Blöcke“ (`FluidBlocks`): Inhalt aus Blöcken in jeder YForm-Tabelle, Bearbeiten in der Maske, Ausgabe in der
  Detailansicht der Datenliste bzw. mit `BlockData::render()`; Vorlage `NewsTable` mit Blöcken.
- Formular-Baukasten auf der Website: Felder, Schritte (mehrstufig), speichern und/oder per E-Mail, Bestätigung an den Absender;
  YForm-FormDefinition aus dem Block, Eingänge in der YForm-Tabelle „Formular-Eingänge“ und im Postfach.
- Datenliste: Blättern, schöne Adressen für Einträge (/seite/titel-ID) mit eigenem Titel und Canonical, Sitemap; Vorlage `NewsTable`.
- Formulare, Datenlisten und Anfragen-Postfach mit YForm 6; Karten ohne Einwilligung (eigener Proxy); Suche mit Loupe.
- Erste Schritte: leere Installation → Startseite oder Beispielseiten mit einem Klick.

### Installation

- Demo „REDAXO 6“ (`fluid:demo --set=redaxo6`): Bereich „REDAXO 6“ (Was sich ändert, Technik, Backend, Projekt anlegen,
  Module und Templates, Native Module, YForm, Navigation) und Bereich „fluid“ (Bearbeiten, Bausteine, fluid-Projekt, Eigenes Kit), mit
  Codebeispielen und Screenshots; englische Fassung aller Seiten und Datensätze mit `--lang=en`
  (Texte in `demo-redaxo6.en.php`). Auf der Startseite der Trailer, wenn die Datei im Medienpool liegt.
- `fluid:demo`:
  - `--page=…` hängt einzelne Seiten an und verschiebt vorhandene an ihre Stelle, `--update` befüllt sie neu.
  - `--media-dir=…` übernimmt weitere Dateien in den Medienpool (z. B. einen Trailer, der nicht zum Kit gehört);
    `--media` und `--media-dir` allein scheitern auf einer Website mit Seiten nicht mehr.
  - Gibt es eine Menüseite der Demo nur noch als Seite ohne Menüeintrag, macht `--page=…` sie wieder zum Menüpunkt
    (Inhalte und Adresse bleiben), statt eine zweite anzulegen.
  - Mit `--page=start` wird die vorhandene Startseite neu befüllt; vorher entstand ein zweiter Startartikel, der
    Start- und 404-Seite wurde.
  - Blöcke gewöhnlicher Module (`'native' => 'modul', 'values' => […]`), nur wenn das Modul im Projekt liegt –
    Beispiele in `examples/module` (Seite „Native Module“).
  - Website-Einstellungen, die auf gelöschte Seiten zeigen (z. B. die Suchseite), werden beim Import neu gesetzt.
- Abhängigkeit `friendsofredaxo/navigation_array:6.x-dev` (per VCS, in der Anleitung und in `install-dev.sh`).
- `install-dev.sh`: REDAXO 6, YForm 6, fluid (und image_editor) mit einem Befehl – mit kompletter Demo oder leer;
  prüft PHP, Erweiterungen und Datenbank, lädt Composer 2.9+ bei Bedarf, richtet Admin, `.env.local` und Webserver ein.
- `fluid:demo --media --design=redaxo`; README für Einsteiger, Entwickler-Referenz in `docs/entwickler.md`.
- Angepasst an REDAXO-Core `cf598c1` (Oktober 2026):
  - Statt `uninstall()` meldet fluid seine Tabellen über `getOwnedSchema()`. Nach `composer remove` entfernt
    `console migrate:cleanup` sie samt Einstellungen und Cache.
  - `install-dev.sh` nutzt `console setup` ohne die entfallenen Optionen `--agree-license` und `--db-setup`.

### Für Entwickler

- **Felder der Blöcke als Attribute** – wie bei den Modulen des Cores. An der Klasse `#[AsBlock(icon:, group:, variants:,
  nestable: …)]`, an den Eigenschaften die Felder aus `Klxm\Fluid\Field`: `Text`, `Textarea`, `Choice`,
  `Checkbox`, `Number`, `DatePicker`, `TimePicker`, `Url` (Namen und Parameter wie im Core: `label`, `note`, `required`,
  `key`, `maxLength` …) sowie `RichText`, `Link`, `Media`, `File`, `Repeater`, `Icon`, `Geo`, `MediaCategory`, `Blog`,
  `YFormTable`, `YFormForm`, `YFormField`; dazu `width: 'half'` und `variants:`. Schlüssel = Eigenschaft in snake_case,
  Standardwert = Vorgabewert der Eigenschaft. Gespeichert wird unverändert unter `values` – vorhandene Inhalte bleiben.
  - Die 24 Blöcke des Kits fluid stehen so in `kits/fluid/src/Module/` (vorher als Arrays in `kits/fluid/kit.php`);
    die Definitionen sind unverändert (maschinell umgestellt und verglichen).
  - **Ausgabe in der Klasse:** `protected static function view(Block $b, array $d): string` – wie `output()` bei
    gewöhnlichen Modulen. Sonst `{Klasse}.view.php` daneben, dann die View des Kits.
  - **Entfällt:** `define()` und die Felder in `kit.php`. `'blocks'` in `kit.php` ergänzt jetzt die Definition der
    Blockklasse (Varianten und `variant_help` kommen hinzu, `fields` werden angehängt, anderes ersetzt) – statt sie
    komplett zu ersetzen. `Kit::hasBlock()` und `Maps::blockFields()` entfallen.
  - Beispiel `examples/block/Hinweis.php`: das Beispiel-Modul „Hinweis“ als Block in einer Datei; die Seite „Native
    Module“ der Demo zeigt die Umstellung Schritt für Schritt.
  - Demo: Auf der Seite „Native Module“ stand unter „Derselbe Hinweis als fluid-Block“ der Code des Teasers (doppelter
    Schlüssel im Demo-Inhalt).

- Menü über [navigation_array](https://github.com/KLXM/navigation_array) (Branch 6.x) statt einer eigenen
  Klasse; fluid meldet über den Extension Point `NavigationStart` den Seitenbaum einer Landing-Domain als Start.
- Blöcke speichern über `ContentHandler::editSlice()` des Cores (#6800) – im Editor und im MCP-Server.
- `fluid:upgrade:slices` macht `ctype_id` optional, wenn `migrate` `content_section_id` schon angelegt hat (sonst
  scheiterte jeder neue Block, bis `--drop-old` lief).
- `Data\RecordInline` und die Editor-Aktion `record_field`: ein Feld eines Datensatzes über `fromInput()` und die
  Record-API von YForm speichern. Die Maske im Overlay ist eine Seite des Cores im Popup-Modus (Parameter `fluid_frame`
  bzw. `Sec-Fetch-Dest: iframe`, `assets/frame.css`).
- Formular-Theme des Kits: Spaltenklassen `dff-c dff-c--{1–12}` für die Breiten des YForm-Layouts.
- `FluidBlocks` nutzt `filterable` (die dynamische Eigenschaft `searchable` gibt es in YForm 6 nicht, in PHP 8.5
  deprecated); Beispieltabellen mit filterbaren Feldern, damit YForm eine Suche zeigt.
- Seite „MCP“ im Backend mit Komponenten des Cores (ohne Bootstrap; Widerrufen fragt wieder nach – `data-confirm`
  statt `onsubmit`, das die CSP blockierte).
- Abmelden über die Editor-Aktion `logout` (`BackendLogin`): das CSRF-Token für `rex_logout` aus dem Frontend gilt im
  Backend nicht.
- Entwickler-Referenz: Ein Kit als Composer-Paket muss ein AddOn sein (`redaxo-addon` mit AddOn-Klasse).
- Beispiele `examples/yform`: `TerminTable` und `AnfrageForm` mit `layout()` im Code.
- FrontendPicker (`Picker\FrontendPicker`, `assets/picker`): Medien- und Seitenauswahl für die Website, für jedes AddOn
  nutzbar – `FrontendPicker::head()` einbinden, im Browser `FrontendPicker.pick('media' | 'article', {…})` mit derselben
  Antwort wie `rex.pick()` im Backend. Eigenständig angelegt, damit daraus später ein eigenes AddOn werden kann.
- Blöcke als Modulklassen (`FluidBlock`) mit Feldschema und View; Kit fluid mit Template, Blöcken und Design-Editor.
- Eigene Kits (Projekt oder Composer-Paket) mit `#[AsKit]`:
  - Jedes Kit erbt vom Kit fluid (oder einem anderen) und ersetzt nur, was es mitbringt: `kit.php`-Einträge, Views,
    `template.view.php`, Assets, `functions.php`. Alle Blöcke sind damit immer vorhanden.
  - Auswahl unter Website → Kit (ab zwei Kits), per EP `FLUID_KIT` auch je Domain. Die Block-Module gehören zu keinem
    Kit, Inhalte bleiben beim Wechsel.
  - Design-Werte je Kit; ein abgeleitetes Kit ohne eigene übernimmt die des Eltern-Kits.
  - Assets nach `assets/fluid-kits/{kit}/` (bei `migrate` und bei Bedarf je Datei).
  - `bin/console fluid:kit:create name --view=hero,cards --template` legt ein Kit im Projekt an.
- E-Mail (Baustein `src/Mail/`):
  - Statusseite → „E-Mail“: Versand, Absender, Umleitung, Protokoll, Archiv, Testmail, letzte Fehler, nicht gesendete
    Mails mit „Erneut senden“ (Bcc-Kopfzeile wird entfernt).
  - Formular-Mails als HTML im Stil der Website (Logo, Akzentfarbe, Anschrift) mit sauberem Textteil.
  - Cronjob `fluid_mail_archive_purge` und Einstellung „Gesendete Mails aufbewahren (Tage)“ (Standard 90).
- Formulare: Ein Mailfehler verwirft die Eingabe nicht mehr. Ist der Eintrag gespeichert, wird der Fehler protokolliert
  (Systemprotokoll, Aktivität „Formular-Mail nicht gesendet“), die Bestätigung wird trotzdem versucht, und Besucher sehen
  die Erfolgsmeldung – mit Hinweis, falls die Bestätigung nicht rausging. Vorher: „Fehler – bitte noch einmal“ trotz
  gespeichertem Eintrag (doppelte Einträge beim erneuten Absenden).
- Angepasst an REDAXO-Core bis `af70a14` (Module je Slice, Felder als JSON in `data`):
  - Blöcke speichern ihre Felder unter `values`, die Abschnitts-Optionen unter `tunes` in der Spalte `data` des Slices
    (statt `value1`/`value2`); im Backend über das Formular des Cores (Feldtyp `Form\BlockValues`).
  - Was den Blocktyp betrifft, ist statisch: `define()`, `kit()`, `fields()`, `definition()`, neu `label()`;
    `BlockModule::of($slice)` liefert das Modul eines Slices, `BlockModule::classFor($key)` und `all()` die Klassen.
    **Eigene Blöcke im Projekt:** `protected static function define()` bzw. `public static function kit()`.
  - Bestehende Datenbanken: `php bin/console fluid:upgrade:slices` (vor oder nach `migrate`) übernimmt die Blöcke und
    benennt `ctype_id` in `content_section_id` um; `--drop-old` entfernt danach die alten Spalten.
  - Löschschutz über die Verweistabelle des Cores (`rex_article_slice_reference`): Der Feldtyp der Blöcke meldet Seiten
    (`redaxo://ID`, auch im Rich-Text) und Medien aus dem Medienpool; „Verwendet in“ im Medienpool und in der Struktur.
    `fluid:upgrade:slices` baut die Verweise mit auf (sonst `bin/console content:rebuild-references`).
  - Extension Points als Klassen: `ArticleContentRendering`, `ArticleSlicesQuery`, `SliceOutput`, `SliceDeleting`,
    `ArticleUrl`, `ContentPageBeforeSlices`; Handler mit einheitlichem `$data` (Kategorien, Sprachen, Medien als `File`).
  - Neue Blöcke stehen sicher an ihrer Stelle, auch wenn mehrere in derselben Sekunde entstehen (vorher konnte ein
    Spalten-Block hinter einem fremden Block landen). Die Zwischenablage nutzt `values`/`tunes`; ältere Einträge
    lassen sich weiter einfügen.
- Angepasst an REDAXO-Core bis `fb11e8a`: Handler-Methoden umbenannt (`ArticleHandler::add()`, `changeStatus()`,
  `convertToCategory()`, `CategoryHandler::convertToArticle()` …) und deren Rückgabewerte genutzt (neue IDs ohne
  `MAX(id)`), Struktur-Extension-Points als Klassen (`ArticleAdded`, `CategoryMoved` …); Demo legt Seiten mit dem
  Template „fluid“ an (nicht mit dem Schlüssel des Kits).
- Angepasst an REDAXO-Core bis `c9b0bf1`: `Redaxo\Core\Console\AbstractCommand`, `Redaxo\Core\MediaManager\AsMediaType`,
  `Redaxo\Core\Util\Markdown\Markdown`; Handler des Cores werfen statt `false` zu liefern (Seitenbaum: Menü und Verschieben),
  ihre `UserMessageException` erscheint im Editor und per MCP als Meldung statt als interner Fehler.
- Bausteine statt fester Verdrahtung (Vorbereitung für eigene AddOns, wie beim Bildeditor):
  - Routing kennt keinen anderen Teil von fluid mehr. Bausteine melden sich an: `Router::endpoint()`, `resolver()`,
    `onPage()`, `gate()`, `keep()`, `Sitemap::exclude()/children()`; Antworten über `Router::respond()`/`redirectTo()`.
  - MCP, Live, Präsenz, Kartenproxy, Untertitel, „Erste Schritte“, Detailseiten der Datenlisten und Blog-Feed melden
    sich selbst an (`boot()` je Baustein).
  - SEO im Seitenkopf aus dem Routing gelöst: `Seo\Head`, `Seo\StructuredData`; Blog (RSS, BlogPosting) und Karten (Geo)
    ergänzen über `FLUID_SEO_HEAD` bzw. `FLUID_STRUCTURED_DATA`.
  - Neue Extension Points: `FLUID_SITEMAP`, `FLUID_ROBOTS_RULES`, `FLUID_ROUTES_REBUILT`, `FLUID_SEO_HEAD`.
  - Entwickler-Referenz: Abschnitt „Bausteine“ mit Regeln, Stand je Baustein und Ablauf zum Herauslösen.
- sitemap.xml: `lastmod` mit dem echten Änderungsdatum (vorher stand bei jeder Seite das heutige Datum), hreflang
  `x-default` (Startsprache), Sitemap-Index ab 45 000 Adressen (`/sitemap-1.xml` …).
- Kit „NativeCSS“ (`kits/native/`) auf Basis von [NativeCSS](https://github.com/skerbis/nativecss) (MIT):
  - Seitenrahmen mit Topbar, Navigation als Off-Canvas (`dialog`, Untermenüs mit `details`) und Footer im Raster.
  - Eigene Views für Einstieg (split, centered, compact, type, fullbleed), Karten, Text + Bild, Handlungsaufruf (band, box)
    und FAQ; alle übrigen Blöcke und Varianten von fluid.
  - Cascade Layers: NativeCSS-Basis, darüber das Grund-CSS von fluid, darüber Komponenten und Brücke – Design-Leiste und
    Vorlagen gelten für beides. Eigene Vorlagen „NativeCSS“ und „NativeCSS · Graphit“ (WCAG AA hell und dunkel).
- Design: abgeleitete Kits geben ihre Design-Werte immer aus (auch Standardwerte), weil das Grund-CSS die von fluid enthält.
- `fluid:demo --design=… --kit=…`; mit vorhandenen Seiten setzt `--design` nur die Vorlage.
- Eigenes Routing ohne yrewrite: gespeicherte Adressen, automatische 301-Weiterleitungen, Multidomain mit Landing-Domains,
  sitemap.xml, robots.txt, Canonical, hreflang, schema.org-@graph.
- Rechte des Cores (Struktur, Module, Optionen der Rolle) auf der Website.
- Blog-API (`Blog::posts()`, `Post`), Feldtyp `blog`, EP `FLUID_BLOG_FEED`; Demo mit Blog.
- Extension Points: `FLUID_BLOCK_DEFINITION`, `FLUID_BLOCK_VIEW`, `FLUID_SETTINGS_SCHEMA`, `FLUID_SEARCH_DOCUMENT`,
  `FLUID_STRUCTURED_DATA`, `FLUID_ROBOTS`, `FLUID_EDITOR_CONFIG`, `FLUID_SITE_TRANSLATIONS`.
- Media-Manager-Typ `fluid_poster`; Feldoption `types` für Dateifelder; Konsole `fluid:demo`, `fluid:search-index`.
- Backend: AddOns → fluid (Status, Wartung, Hilfe).
- Gastzugang für AddOns (z. B. `fluid_demo`):
  - `FLUID_GUEST_USER` meldet Besucher ohne Anmeldung mit den Rechten eines Benutzers an, nie als Admin.
  - `FLUID_API_ACTION` übernimmt Aktionen des Editors. Gäste bedient fluid nie selbst, ohne AddOn gibt es 403.
  - `Editing::isGuest()`, `Editing::guestRevision()`, `Api::dispatch()`.
  - Editor: `cfg.scripts`, `cfg.notice`, Ereignisse `fluid:api-request` und `fluid:api-done`, `window.fluidEditorWait`,
    `window.fluidPickerOff`.
  - Für Gäste aus: Präsenz, Versionen-Vorschau, Bildeditor, Medienpool, Postfach, Design, Website-Einstellungen,
    Seitenbaum, Verlauf, Entwürfe in der Beitragsliste.
- `Live` (aus KLXM Studio `Core\Live`): Kanäle mit Versionen, signierte Abos, SSE-Stream `/fluid-live` mit Rückfall auf
  Nachfragen. „Neu laden auf Aktion“ mit `'reload' => true`, `Live::region($b)`, `data-live-nav`/`data-live-input`.
- Präsenz: `Presence` (Speicher, unverändert aus Studio), `PresenceApi` (`/fluid-presence`), `assets/editor/presence.js`.
  Ereignisse `cms:before-save` (vor schreibenden Aktionen des Editors) und `cms:presence-scan`. Konsole `fluid:selftest`.
