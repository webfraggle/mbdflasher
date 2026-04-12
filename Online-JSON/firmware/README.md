# Firmware Download-Seite

Die `index.php` generiert eine öffentliche Download-Seite für Firmware-ZIPs.

## Datenquelle

Liest `api/firmware_list/all/index.json` direkt vom Dateisystem. Angezeigt werden nur:

- **Project ID 1** — Zugzielanzeiger
- **Project ID 3** — Tankstellenanzeige
- **Project ID 5** — Video-Werbeanzeige

Alle Versionen werden angezeigt (neueste zuerst), sortiert nach Display-Typ > Gerätefamilie > Controller-Typ.

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

Bilder werden in `images/downloads/` abgelegt (jpg, jpeg, png oder webp). Die Seite sucht automatisch vom Spezifischsten zum Allgemeinsten:

| Priorität | Dateiname-Schema | Beispiel |
|---|---|---|
| 1 (exakt) | `{projekt}-{familie}-{variante}.jpg` | `tankstellenanzeige-esp32-s3-universal.jpg` |
| 2 (Familie) | `{projekt}-{familie}.jpg` | `tankstellenanzeige-esp32-s3.jpg` |
| 3 (Projekt) | `{projekt}.jpg` | `tankstellenanzeige.jpg` |

Die Namen werden slugifiziert: Kleinbuchstaben, Umlaute aufgelöst (ä→ae), Sonderzeichen durch Bindestriche ersetzt.

### Alle gültigen Projekt-Slugs

- `zugzielanzeiger`
- `tankstellenanzeige`
- `video-werbeanzeige`

### Alle gültigen Familien-Slugs

- `esp32-s2`
- `esp32-s3`

### Alle gültigen Varianten-Slugs

- `doppelgleis`
- `einzelgleis`
- `universal`
- `s3pro`
- `standard` (Fallback wenn keine Variante gesetzt ist)

### Beispiel

Du möchtest ein Bild für alle Tankstellenanzeigen auf ESP32-S3 zeigen, aber ein spezielles für die S3Pro-Variante:

```
images/downloads/
  tankstellenanzeige-esp32-s3.jpg       <-- wird bei "Universal" und "Standard" gezeigt
  tankstellenanzeige-esp32-s3-s3pro.jpg <-- wird nur bei "S3Pro" gezeigt
```

Kein Bild vorhanden = kein Bild auf der Seite. Die Downloads funktionieren auch ohne Bilder.
