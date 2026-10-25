<?php
// Firmware Download Page for modellbahn-displays.de

$jsonPath = __DIR__ . '/api/firmware_list/all/index.json';
$json = file_get_contents($jsonPath);
$firmwares = json_decode($json, true);

// Only show these project IDs
$allowedProjects = [1, 3, 5];
$projectNames = [
    1 => 'Zugzielanzeiger',
    3 => 'Tankstellenanzeige',
    5 => 'Video-Werbeanzeige',
];
$projectOrder = [1, 3, 5];

// Slugify a string for image filenames
function slugify($text) {
    $text = mb_strtolower($text, 'UTF-8');
    $text = str_replace(['ä', 'ö', 'ü', 'ß'], ['ae', 'oe', 'ue', 'ss'], $text);
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    return trim($text, '-');
}

// Find a product image — checks from most specific to least specific.
// The two grouping slugs are controller + display for Tankstelle/Video and
// family + variant for ZZA:
//   1. images/downloads/{project}-{level1}-{level2}.{ext}
//   2. images/downloads/{project}-{level1}.{ext}
//   3. images/downloads/{project}.{ext}
// Supports jpg, jpeg, png, webp
function findProductImage($projectSlug, $level1Slug, $level2Slug) {
    $extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $candidates = [
        $projectSlug . '-' . $level1Slug . '-' . $level2Slug,
        $projectSlug . '-' . $level1Slug,
        $projectSlug,
    ];
    foreach ($candidates as $base) {
        foreach ($extensions as $ext) {
            $rel = 'images/downloads/' . $base . '.' . $ext;
            if (file_exists(__DIR__ . '/' . $rel)) {
                return $rel;
            }
        }
    }
    return null;
}

// Extract firmware directory from download_url
function getFirmwareDir($downloadUrl) {
    if (preg_match('#/firmware/([^/]+)/firmware\.bin$#', $downloadUrl, $m)) {
        return $m[1];
    }
    return null;
}

// ── Grouping helpers ──────────────────────────────────────────────────
// The firmware directory follows the naming convention
//   project-display-controller-version   (e.g. tankstellenanzeige-h0-universal-2.0.6)
// Tankstelle and Video are grouped by Controller (level 1) -> Display
// (level 2). The Display dimension only lives in the directory name, so it
// is parsed from there (with fallbacks for legacy directories). ZZA keeps
// the classic family_name -> variant grouping.

// Pretty controller label. JSON variants are already "Universal"/"S3Pro".
function formatController($raw) {
    $map = ['universal' => 'Universal', 's3pro' => 'S3Pro'];
    $key = strtolower((string) $raw);
    if (isset($map[$key])) {
        return $map[$key];
    }
    return $raw !== '' ? $raw : 'Standard';
}

// Sort order for controllers: Universal first, then S3Pro, then the rest.
function controllerSort($label) {
    $order = ['Universal' => 0, 'S3Pro' => 1];
    return $order[$label] ?? 2;
}

// Tankstelle track gauge label: h0 -> "Spur H0", n -> "Spur N".
function formatSpur($tok) {
    $tok = strtolower($tok);
    if ($tok === 'h0') return 'Spur H0';
    if ($tok === 'n')  return 'Spur N';
    return strtoupper($tok);
}

// Extract the display (track gauge) of a Tankstelle directory.
function tankstelleDisplay($dir) {
    // New: tankstellenanzeige-<display>-<controller>-<version>
    if (preg_match('/^tankstellenanzeige-([a-z0-9]+)-[a-z0-9]+-[0-9]/', $dir, $m)) {
        return formatSpur($m[1]);
    }
    // Legacy: tankstelle-pylon-h0-..., tankstelle-spur-n-...
    if (preg_match('/-(h0|n)(-|$)/', $dir, $m)) {
        return formatSpur($m[1]);
    }
    return null;
}

// Normalise a video display size token to "d.dd" ("105" -> "1.05", "96" -> "0.96").
function normalizeVideoSize($s) {
    if (strlen($s) === 2) {
        $s = '0' . $s;
    }
    return $s[0] . '.' . substr($s, 1);
}

// German inch label, e.g. "1.05" -> '1,05" Display'.
function formatInch($ddd) {
    return str_replace('.', ',', $ddd) . '" Display';
}

// Extract the display size of a Video directory (handles new + legacy names).
function videoDisplay($dir) {
    // New: video-<digits>-<controller>-<version>   e.g. video-105-universal-3.0.1
    if (preg_match('/^video-(\d{2,3})-[a-z]+-[0-9]/', $dir, $m)) {
        return formatInch(normalizeVideoSize($m[1]));
    }
    // Legacy A: video-<controller>-<d.dd>-<version>   e.g. video-universal-1.05-2.2
    if (preg_match('/^video-[a-z]+-(\d\.\d{2})-/', $dir, $m)) {
        return formatInch($m[1]);
    }
    // Legacy B: video-<d.dd>-<version>                e.g. video-0.96-1.0.0
    if (preg_match('/^video-(\d\.\d{2})-/', $dir, $m)) {
        return formatInch($m[1]);
    }
    return null;
}

// Sort order for displays: Spur H0, Spur N, then video sizes ascending.
function displaySort($label) {
    if (stripos($label, 'H0') !== false)     return 0.0;
    if (stripos($label, 'Spur N') !== false) return 1.0;
    if (preg_match('/(\d),(\d{2})/', $label, $m)) {
        return (float) ($m[1] . '.' . $m[2]);
    }
    return 99.0;
}

// Determine the two grouping levels + the full row title for one firmware.
// Returns [level1 label, level1 sort, level2 label, level2 sort, row title].
function groupingFor($fw, $dir, $projectNames) {
    $pid = $fw['project_id'];

    if ($pid === 3) {            // Tankstellenanzeige: Controller -> Display
        $l1 = formatController($fw['variant']);
        $l2 = tankstelleDisplay($dir) ?? ($fw['variant'] ?: 'Standard');
        $title = $projectNames[$pid] . ' ' . $l1 . ' ' . $l2;
        return [$l1, controllerSort($l1), $l2, displaySort($l2), $title];
    }

    if ($pid === 5) {            // Video: Controller (Universal) -> Display
        $l1 = 'Universal';
        $l2 = videoDisplay($dir) ?? 'Standard';
        $title = $projectNames[$pid] . ' ' . $l1 . ' ' . $l2;
        return [$l1, controllerSort($l1), $l2, displaySort($l2), $title];
    }

    // ZZA (pid 1) and anything else: classic family_name -> variant.
    $l1 = $fw['family_name'];
    $l2 = $fw['variant'] ?: 'Standard';
    return [$l1, $l1, $l2, $l2, $fw['name']];
}

// Build a pseudo firmware entry for a ZZA beta directory that is not listed
// in the JSON, e.g. zza-universal-1.05-quad-3.2.11-beta. Family and variant
// follow the conventions of the JSON entries (1.05 -> ESP32-S3,
// 0.96 -> ESP32-S2, quad -> Doppelgleis, dual -> Einzelgleis).
function zzaBetaFirmware($dir) {
    if (!preg_match('/^zza-(?:[a-z0-9]+-)?(\d\.\d{2})-(quad|dual)-(\d+(?:\.\d+)*-beta)$/', $dir, $m)) {
        return null;
    }
    return [
        'name'         => 'Zugzielanzeiger ' . $m[1],
        'project_id'   => 1,
        'family_name'  => $m[1] === '0.96' ? 'ESP32-S2' : 'ESP32-S3',
        'variant'      => $m[2] === 'quad' ? 'Doppelgleis' : 'Einzelgleis',
        'version'      => $m[3],
        'download_url' => 'https://www.modellbahn-displays.de/firmware/' . $dir . '/firmware.bin',
    ];
}

// Add ZZA beta directories that are not part of the JSON catalog.
$knownDirs = [];
foreach ($firmwares as $fw) {
    $knownDirs[getFirmwareDir($fw['download_url'])] = true;
}
foreach (glob(__DIR__ . '/zza-*-beta', GLOB_ONLYDIR) as $path) {
    $dir = basename($path);
    if (isset($knownDirs[$dir])) {
        continue;
    }
    $fw = zzaBetaFirmware($dir);
    if ($fw) {
        $firmwares[] = $fw;
    }
}

// Build grouped structure:
//   project_id -> level1 -> ['label','sort','level2' => level2 -> ['label','sort','entries']]
$grouped = [];

foreach ($firmwares as $fw) {
    if (!in_array($fw['project_id'], $allowedProjects)) {
        continue;
    }

    $dir = getFirmwareDir($fw['download_url']);
    if (!$dir) {
        continue;
    }

    $zipPath = __DIR__ . '/' . $dir . '/' . $dir . '.zip';
    if (!file_exists($zipPath)) {
        continue;
    }

    $pid = $fw['project_id'];
    [$l1label, $l1sort, $l2label, $l2sort, $rowTitle] = groupingFor($fw, $dir, $projectNames);

    if (!isset($grouped[$pid][$l1label])) {
        $grouped[$pid][$l1label] = ['label' => $l1label, 'sort' => $l1sort, 'level2' => []];
    }
    if (!isset($grouped[$pid][$l1label]['level2'][$l2label])) {
        $grouped[$pid][$l1label]['level2'][$l2label] = ['label' => $l2label, 'sort' => $l2sort, 'entries' => []];
    }

    $grouped[$pid][$l1label]['level2'][$l2label]['entries'][] = [
        'title'   => $rowTitle,
        'version' => $fw['version'],
        'dir'     => $dir,
        'zip'     => $dir . '/' . $dir . '.zip',
    ];
}

// Sort: level1 by sort key, level2 by sort key, entries by version descending.
$bySort = function ($a, $b) {
    return ($a['sort'] <=> $b['sort']) ?: strcmp((string) $a['label'], (string) $b['label']);
};
foreach ($grouped as &$level1) {
    uasort($level1, $bySort);
    foreach ($level1 as &$l1) {
        uasort($l1['level2'], $bySort);
        foreach ($l1['level2'] as &$l2) {
            usort($l2['entries'], function ($a, $b) {
                return version_compare($b['version'], $a['version']);
            });
        }
    }
}
unset($level1, $l1, $l2);
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Firmware Downloads — Modellbahn-Displays</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,800&family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&display=swap" rel="stylesheet">
    <style>
        :root {
            --orange: #FD7014;
            --orange-hover: #e56410;
            --orange-light: rgba(253, 112, 20, 0.08);
            --orange-glow: rgba(253, 112, 20, 0.15);
            --teal: #037F8C;
            --black: #1a1a1a;
            --gray-800: #2d2d2d;
            --gray-600: #555;
            --gray-400: #999;
            --gray-200: #e2e2e2;
            --gray-100: #f5f5f5;
            --white: #ffffff;
            --radius: 10px;
            --font-display: 'Bricolage Grotesque', Georgia, serif;
            --font-body: 'DM Sans', system-ui, sans-serif;
        }

        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: var(--font-body);
            font-size: 15px;
            line-height: 1.65;
            color: var(--black);
            background: var(--white);
            -webkit-font-smoothing: antialiased;
        }

        /* ── Page shell ── */
        .page {
            max-width: 880px;
            margin: 0 auto;
            padding: 0 1.5rem 4rem;
        }

        /* ── Hero header ── */
        .hero {
            padding: 3.5rem 0 2.5rem;
            border-bottom: 3px solid var(--black);
            margin-bottom: 2.5rem;
        }
        .hero-eyebrow {
            font-family: var(--font-body);
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.12em;
            text-transform: uppercase;
            color: var(--orange);
            margin-bottom: 0.5rem;
        }
        .hero h1 {
            font-family: var(--font-display);
            font-size: clamp(2rem, 5vw, 2.8rem);
            font-weight: 800;
            line-height: 1.1;
            color: var(--black);
        }
        .hero p {
            margin-top: 0.75rem;
            font-size: 1.05rem;
            color: var(--gray-600);
            max-width: 540px;
        }

        /* ── Category section ── */
        .category {
            margin-bottom: 3rem;
        }
        .category-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
        }
        .category-bar {
            width: 5px;
            height: 1.8rem;
            background: var(--orange);
            border-radius: 3px;
            flex-shrink: 0;
        }
        .category-header h2 {
            font-family: var(--font-display);
            font-size: 1.55rem;
            font-weight: 700;
            color: var(--black);
            line-height: 1.2;
        }

        /* ── Family group ── */
        .family {
            margin-bottom: 1.5rem;
        }
        .family-label {
            display: inline-block;
            font-family: var(--font-body);
            font-size: 0.72rem;
            font-weight: 600;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--gray-600);
            background: var(--gray-100);
            padding: 0.25rem 0.7rem;
            border-radius: 4px;
            margin-bottom: 0.75rem;
            margin-left: 0.25rem;
        }

        /* ── Variant card ── */
        .variant-card {
            background: var(--white);
            border: 1px solid var(--gray-200);
            border-radius: var(--radius);
            margin-bottom: 0.75rem;
            overflow: hidden;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .variant-card:hover {
            border-color: #ccc;
            box-shadow: 0 2px 12px rgba(0,0,0,0.06);
        }
        .variant-top {
            display: flex;
            gap: 1.25rem;
            align-items: stretch;
        }
        .variant-image {
            width: 160px;
            min-height: 110px;
            flex-shrink: 0;
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .variant-image img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .variant-image .placeholder-icon {
            color: var(--gray-400);
        }
        .variant-body {
            flex: 1;
            padding: 1rem 1.25rem 1rem 0;
            min-width: 0;
        }
        .variant-card.no-image .variant-body {
            padding-left: 1.25rem;
        }
        .variant-name {
            font-family: var(--font-display);
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--black);
            margin-bottom: 0.5rem;
        }

        /* ── Download rows ── */
        .dl-list {
            list-style: none;
        }
        .dl-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.35rem 0;
            border-top: 1px solid var(--gray-100);
        }
        .dl-row:first-child {
            border-top: none;
        }
        .dl-info {
            display: flex;
            align-items: baseline;
            gap: 0.5rem;
            min-width: 0;
        }
        .dl-name {
            font-size: 0.9rem;
            color: var(--gray-800);
        }
        .dl-version {
            font-size: 0.78rem;
            font-weight: 500;
            color: var(--gray-400);
            white-space: nowrap;
        }
        .dl-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            background: var(--orange);
            color: var(--white);
            font-family: var(--font-body);
            font-size: 0.78rem;
            font-weight: 600;
            text-decoration: none;
            padding: 0.3rem 0.75rem;
            border-radius: 6px;
            white-space: nowrap;
            transition: background 0.15s ease, transform 0.1s ease;
        }
        .dl-btn:hover {
            background: var(--orange-hover);
        }
        .dl-btn:active {
            transform: scale(0.97);
        }
        .dl-btn svg {
            flex-shrink: 0;
        }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 4rem 1rem;
            color: var(--gray-400);
        }
        .empty-state p {
            font-size: 1.1rem;
        }

        /* ── Footer ── */
        .page-footer {
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--gray-200);
            font-size: 0.82rem;
            color: var(--gray-400);
        }
        .page-footer a {
            color: var(--orange);
            text-decoration: none;
        }
        .page-footer a:hover {
            text-decoration: underline;
        }

        /* ── Responsive ── */
        @media (max-width: 600px) {
            .page { padding: 0 1rem 3rem; }
            .hero { padding: 2.5rem 0 1.75rem; }
            .variant-top { flex-direction: column; }
            .variant-image {
                width: 100%;
                min-height: 140px;
                max-height: 180px;
            }
            .variant-body {
                padding: 0.75rem 1rem 1rem !important;
            }
            .dl-row {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.35rem;
                padding: 0.5rem 0;
            }
        }
    </style>
</head>
<body>
    <div class="page">

        <!-- Hero -->
        <header class="hero">
            <div class="hero-eyebrow">modellbahn-displays.de</div>
            <h1>Firmware Downloads</h1>
            <p>Aktuelle Firmware-Pakete für deine Modellbahn-Displays. Lade das passende ZIP für dein Display und deinen Controller herunter.</p>
        </header>

        <?php
        $hasAny = false;
        foreach ($projectOrder as $pid):
            if (empty($grouped[$pid])) continue;
            $hasAny = true;
        ?>
            <section class="category">
                <div class="category-header">
                    <div class="category-bar"></div>
                    <h2><?= htmlspecialchars($projectNames[$pid]) ?></h2>
                </div>

                <?php foreach ($grouped[$pid] as $level1): ?>
                    <div class="family">
                        <div class="family-label"><?= htmlspecialchars($level1['label']) ?></div>

                        <?php foreach ($level1['level2'] as $level2):
                            $projectSlug = slugify($projectNames[$pid]);
                            $level1Slug = slugify($level1['label']);
                            $level2Slug = slugify($level2['label']);
                            $image = findProductImage($projectSlug, $level1Slug, $level2Slug);
                        ?>
                            <div class="variant-card<?= $image ? '' : ' no-image' ?>">
                                <div class="variant-top">
                                    <?php if ($image): ?>
                                        <div class="variant-image">
                                            <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($projectNames[$pid] . ' ' . $level1['label'] . ' ' . $level2['label']) ?>" loading="lazy">
                                        </div>
                                    <?php endif; ?>
                                    <div class="variant-body">
                                        <div class="variant-name"><?= htmlspecialchars($level2['label']) ?></div>
                                        <ul class="dl-list">
                                            <?php foreach ($level2['entries'] as $entry): ?>
                                                <li class="dl-row">
                                                    <div class="dl-info">
                                                        <span class="dl-name"><?= htmlspecialchars($entry['title']) ?></span>
                                                        <span class="dl-version">v<?= htmlspecialchars($entry['version']) ?></span>
                                                    </div>
                                                    <a class="dl-btn" href="<?= htmlspecialchars($entry['zip']) ?>" download>
                                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none"><path d="M7 1v8.5M7 9.5L3.5 6M7 9.5L10.5 6M2 12h10" stroke="#fff" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                        ZIP
                                                    </a>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>

        <?php if (!$hasAny): ?>
            <div class="empty-state">
                <p>Aktuell sind keine Firmware-Downloads verfügbar.</p>
            </div>
        <?php endif; ?>

        <footer class="page-footer">
            &copy; <?= date('Y') ?> <a href="https://www.modellbahn-displays.de">modellbahn-displays.de</a>
        </footer>

    </div>
</body>
</html>
