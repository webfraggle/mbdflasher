# Firmware Download-Seite

Die `index.php` generiert eine öffentliche Download-Seite für Firmware-ZIPs.

## Datenquelle

Die Seite ist **unabhängig von der `index.json`**. Sie durchsucht alle Unterverzeichnisse von `firmware/`. Ein Verzeichnis wird angezeigt, wenn es **beides** enthält:

- eine ZIP mit dem Namen des Verzeichnisses (`<verzeichnis>/<verzeichnis>.zip`)
- eine `device.json` (gleiches Format wie ein Eintrag der `index.json`)

```
firmware/
  zza-105quad-universal-3.2.12-beta/
    firmware.bin
    partitions.bin
    littlefs.bin
    device.json                              <-- Metadaten (Projekt, Familie, Variante, Version …)
    zza-105quad-universal-3.2.12-beta.zip    <-- diese Datei wird verlinkt
```

Fehlt eine der beiden Dateien oder ist die `device.json` fehlerhaft, erscheint der Eintrag nicht. **Ausblenden einer Version = ZIP löschen.**

Angezeigt werden nur:

- **Project ID 1** — Zugzielanzeiger
- **Project ID 3** — Tankstellenanzeige
- **Project ID 5** — Video-Werbeanzeige

## Gruppierung

Alle Versionen werden angezeigt (neueste zuerst; eine `-beta` gilt als älter als dieselbe Version ohne `-beta`: `3.2.12` > `3.2.12-beta` > `3.2.11`). Die Gruppierung ist projektabhängig:

- **Tankstelle & Video** — Projekt → **Controller** (`variant` aus der `device.json`: `Universal`, `S3Pro`) → **Display** (`Spur H0`/`Spur N` bzw. `0,96"`/`1,05"`/`1,14"`). Das Display wird aus dem Verzeichnisnamen nach dem Schema `project-display-controller-version` ermittelt (mit Fallback für Altverzeichnisse). Jede Download-Zeile trägt die komplette Kombination Titel · Controller · Display · Version.
- **Zugzielanzeiger** — Projekt → **Gerätefamilie** (`family_name`, z. B. ESP32-S2/S3) → **Variante** (`variant`, Doppelgleis/Einzelgleis). Der Verzeichnisname spielt hier keine Rolle, beide Namensschemas (`zza-universal-1.05-quad-…` und `zza-105quad-universal-…`) funktionieren.

## Produktbilder

Bilder werden in `images/downloads/` abgelegt (jpg, jpeg, png oder webp). Die Seite sucht automatisch vom Spezifischsten zum Allgemeinsten. Die beiden Slugs entsprechen den zwei Gruppierungsebenen (bei Tankstelle/Video **Controller** + **Display**, bei ZZA **Familie** + **Variante**):

| Priorität | Dateiname-Schema | Beispiel |
|---|---|---|
| 1 (exakt) | `{projekt}-{ebene1}-{ebene2}.jpg` | `tankstellenanzeige-universal-spur-h0.jpg` |
| 2 (Ebene 1) | `{projekt}-{ebene1}.jpg` | `tankstellenanzeige-universal.jpg` |
| 3 (Projekt) | `{projekt}.jpg` | `tankstellenanzeige.jpg` |

Die Namen werden slugifiziert: Kleinbuchstaben, Umlaute aufgelöst (ä→ae), Sonderzeichen durch Bindestriche ersetzt (z. B. `1,05"` → `1-05`).

### Alle gültigen Projekt-Slugs

- `zugzielanzeiger`
- `tankstellenanzeige`
- `video-werbeanzeige`

### Ebene-1-Slugs (Controller bzw. Familie)

- `universal`, `s3pro` (Tankstelle/Video – Controller)
- `esp32-s2`, `esp32-s3` (ZZA – Gerätefamilie)

### Ebene-2-Slugs (Display bzw. Variante)

- `spur-h0`, `spur-n` (Tankstelle – Display)
- `0-96-display`, `1-05-display`, `1-14-display` (Video – Display)
- `doppelgleis`, `einzelgleis` (ZZA – Variante)
- `standard` (Fallback wenn nichts ermittelt werden konnte)

### Beispiel

Du möchtest ein Bild für alle Tankstellenanzeigen mit Universal-Controller zeigen, aber ein spezielles für Spur H0:

```
images/downloads/
  tankstellenanzeige-universal.jpg          <-- wird bei "Spur N" (und sonst) gezeigt
  tankstellenanzeige-universal-spur-h0.jpg  <-- wird nur bei "Spur H0" gezeigt
```

Kein Bild vorhanden = kein Bild auf der Seite. Die Downloads funktionieren auch ohne Bilder.
