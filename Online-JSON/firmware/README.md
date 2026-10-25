# Firmware Download-Seite

Die `index.php` generiert eine öffentliche Download-Seite für Firmware-ZIPs.

## Datenquelle

Liest `api/firmware_list/all/index.json` direkt vom Dateisystem. Angezeigt werden nur:

- **Project ID 1** — Zugzielanzeiger
- **Project ID 3** — Tankstellenanzeige
- **Project ID 5** — Video-Werbeanzeige

Alle Versionen werden angezeigt (neueste zuerst). Die Gruppierung ist projektabhängig:

- **Tankstelle & Video** — Projekt → **Controller** (`Universal`, `S3Pro`) → **Display** (`Spur H0`/`Spur N` bzw. `0,96"`/`1,05"`/`1,14"`). Controller und Display werden aus dem Verzeichnisnamen nach dem Schema `project-display-controller-version` ermittelt (mit Fallback für Altverzeichnisse). Jede Download-Zeile trägt die komplette Kombination Titel · Controller · Display · Version.
- **Zugzielanzeiger** — unverändert: Projekt → **Gerätefamilie** (ESP32-S2/S3) → **Variante** (Doppelgleis/Einzelgleis).

### Beta-Versionen (ZZA)

Zusätzlich zur JSON werden ZZA-Verzeichnisse nach dem Schema `zza-universal-1.05-quad-3.2.11-beta` (bzw. `zza-0.96-dual-…-beta`) automatisch erkannt, auch wenn sie **nicht** in der `index.json` stehen. Familie und Variante werden aus dem Namen abgeleitet: `1.05` → ESP32-S3, `0.96` → ESP32-S2, `quad` → Doppelgleis, `dual` → Einzelgleis. Auch hier gilt: ohne ZIP keine Anzeige.

## ZIP-Dateien

Ein Firmware-Eintrag wird **nur angezeigt, wenn eine ZIP-Datei existiert**. Die ZIP muss im jeweiligen Firmware-Unterverzeichnis liegen und den gleichen Namen wie das Verzeichnis tragen:

```
firmware/
  tankstellenanzeige-h0-universal-2.0.6/
    firmware.bin
    partitions.bin
    tankstellenanzeige-h0-universal-2.0.6.zip   <-- diese Datei wird verlinkt
```

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
