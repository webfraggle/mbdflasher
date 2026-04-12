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

// Find a product image — checks from most specific to least specific:
//   1. images/downloads/{project}-{family}-{variant}.{ext}
//   2. images/downloads/{project}-{family}.{ext}
//   3. images/downloads/{project}.{ext}
// Supports jpg, jpeg, png, webp
function findProductImage($projectSlug, $familySlug, $variantSlug) {
    $extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $candidates = [
        $projectSlug . '-' . $familySlug . '-' . $variantSlug,
        $projectSlug . '-' . $familySlug,
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

// Build grouped structure: project_id -> family_name -> variant -> [entries]
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
    $family = $fw['family_name'];
    $variant = $fw['variant'] ?: 'Standard';

    $grouped[$pid][$family][$variant][] = [
        'name'    => $fw['name'],
        'version' => $fw['version'],
        'dir'     => $dir,
        'zip'     => $dir . '/' . $dir . '.zip',
    ];
}

// Sort versions descending within each group
foreach ($grouped as &$families) {
    ksort($families);
    foreach ($families as &$variants) {
        ksort($variants);
        foreach ($variants as &$entries) {
            usort($entries, function ($a, $b) {
                return version_compare($b['version'], $a['version']);
            });
        }
    }
}
unset($families, $variants, $entries);
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
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
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

                <?php foreach ($grouped[$pid] as $familyName => $variants): ?>
                    <div class="family">
                        <div class="family-label"><?= htmlspecialchars($familyName) ?></div>

                        <?php foreach ($variants as $variantName => $entries):
                            $projectSlug = slugify($projectNames[$pid]);
                            $familySlug = slugify($familyName);
                            $variantSlug = slugify($variantName);
                            $image = findProductImage($projectSlug, $familySlug, $variantSlug);
                        ?>
                            <div class="variant-card<?= $image ? '' : ' no-image' ?>">
                                <div class="variant-top">
                                    <?php if ($image): ?>
                                        <div class="variant-image">
                                            <img src="<?= htmlspecialchars($image) ?>" alt="<?= htmlspecialchars($projectNames[$pid] . ' ' . $familyName . ' ' . $variantName) ?>" loading="lazy">
                                        </div>
                                    <?php endif; ?>
                                    <div class="variant-body">
                                        <div class="variant-name"><?= htmlspecialchars($variantName) ?></div>
                                        <ul class="dl-list">
                                            <?php foreach ($entries as $entry): ?>
                                                <li class="dl-row">
                                                    <div class="dl-info">
                                                        <span class="dl-name"><?= htmlspecialchars($entry['name']) ?></span>
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
