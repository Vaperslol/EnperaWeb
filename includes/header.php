<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/db.php';

$pageTitle = $pageTitle ?? 'N/A';
$pageDescription = $pageDescription ?? 'N/A hivatalos weboldala: pesterzsébeti metal zenekar.';
$activeNav = $activeNav ?? '';
$pathPrefix = $pathPrefix ?? '';
$extraCss = $extraCss ?? [];
if (is_string($extraCss)) {
    $extraCss = [$extraCss];
}
$isHome = ($activeNav === 'home');

// Dinamikus színtéma lekérése
$siteTheme = db_get_theme_settings();
$themeAccent = $siteTheme['accent'] ?? '#ffffff';
$themeBgMain = $siteTheme['bg_main'] ?? '#060607';
$themeBgSection = $siteTheme['bg_section'] ?? '#0c0c0e';
$themeBgCard = $siteTheme['bg_card'] ?? '#141417';
$themeTextMain = $siteTheme['text_main'] ?? '#f4f4f6';
$themeGrayscale = $siteTheme['image_grayscale'] ?? '100';

[$ar, $ag, $ab] = hex_to_rgb($themeAccent);
$themeAccentContrast = get_contrast_color($themeAccent);
$themeAccentHover = adjust_hex_brightness($themeAccent, -15);
$themeGlow = "rgba({$ar}, {$ag}, {$ab}, 0.22)";
$themeBorderHover = "rgba({$ar}, {$ag}, {$ab}, 0.45)";
$themeHeroGlow = "radial-gradient(circle at center, rgba({$ar}, {$ag}, {$ab}, 0.28), transparent 45%)";
$themeCardGlow = "radial-gradient(circle at center, rgba({$ar}, {$ag}, {$ab}, 0.16), transparent 45%)";
$themeCardCornerGlow = "radial-gradient(circle at top right, rgba({$ar}, {$ag}, {$ab}, 0.22), transparent 38%)";
$themeLineGlow = "rgba({$ar}, {$ag}, {$ab}, 0.55)";
?>
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title><?= e($pageTitle) ?></title>

    <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin="anonymous" />
    <link href="<?= $pathPrefix ?>kepek/logo/enpera-feher.png" rel="icon" type="image/png" />
    <link href="<?= $pathPrefix ?>kepek/logo/enpera-feher.png" rel="shortcut icon" type="image/png" />

    <meta name="description" content="<?= e($pageDescription) ?>" />
    <meta name="theme-color" content="<?= e($themeBgMain) ?>" />

    <!-- Bootstrap 5 CSS -->
    <link crossorigin="anonymous" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />

    <!-- Alap stíluslap -->
    <link href="<?= $pathPrefix ?>stiluslapok/alap.css" rel="stylesheet" />

    <!-- Oldalspecifikus stíluslapok -->
    <?php foreach ($extraCss as $css): ?>
        <link href="<?= $pathPrefix ?>stiluslapok/<?= e($css) ?>" rel="stylesheet" />
    <?php endforeach; ?>

    <!-- Dinamikus színtéma az adatbázisból -->
    <style id="dynamic-site-theme">
        :root {
            --bg-main: <?= e($themeBgMain) ?>;
            --bg-section: <?= e($themeBgSection) ?>;
            --bg-card: <?= e($themeBgCard) ?>;
            --text-main: <?= e($themeTextMain) ?>;
            --accent: <?= e($themeAccent) ?>;
            --accent-hover: <?= e($themeAccentHover) ?>;
            --accent-contrast: <?= e($themeAccentContrast) ?>;
            --glow-color: <?= $themeGlow ?>;
            --border-hover: <?= $themeBorderHover ?>;
        }

        <?php if (strtolower($themeAccent) !== '#ffffff'): ?>
        body.lightmode {
            --accent: <?= e($themeAccent) ?>;
            --accent-hover: <?= e($themeAccentHover) ?>;
            --accent-contrast: <?= e($themeAccentContrast) ?>;
            --glow-color: rgba(<?= "{$ar}, {$ag}, {$ab}" ?>, 0.18);
            --border-hover: rgba(<?= "{$ar}, {$ag}, {$ab}" ?>, 0.4);
        }
        <?php endif; ?>

        .bandatagok-hero,
        .member-profile-hero,
        .galeria-hero,
        .hirek-hero,
        .kapcsolat-hero,
        .koncertek-hero,
        .rolunk-hero,
        .timeline-hero {
            background-image: <?= $themeHeroGlow ?>, linear-gradient(rgba(0, 0, 0, 0.78), rgba(0, 0, 0, 0.95)) !important;
        }

        .profile-image-card::after,
        .member-overlay,
        .album-cover::after,
        .news-image-box::after,
        .rolunk-image-panel::after {
            background: linear-gradient(to top, var(--bg-card), transparent 65%), <?= $themeCardGlow ?> !important;
        }

        .contact-icon,
        .rolunk-quote-card {
            background: <?= $themeCardGlow ?>, var(--bg-section) !important;
        }

        .concert-card::before {
            background: <?= $themeCardCornerGlow ?> !important;
        }

        .timeline-container::before,
        #timelineContainer::before {
            background: linear-gradient(90deg, transparent, <?= $themeLineGlow ?>, transparent) !important;
        }

        .concert-card-red {
            background: linear-gradient(135deg, rgba(<?= "{$ar}, {$ag}, {$ab}" ?>, 0.16), rgba(<?= "{$ar}, {$ag}, {$ab}" ?>, 0.04)), var(--bg-card) !important;
            border-color: rgba(<?= "{$ar}, {$ag}, {$ab}" ?>, 0.35) !important;
        }

        img {
            filter: grayscale(<?= e($themeGrayscale) ?>%) !important;
        }
    </style>
</head>
<body class="<?= $isHome ? 'home-page' : '' ?>">
    <!-- Korai téma betöltés villanás megelőzésére -->
    <script data-early-theme="true">
        try {
            if (localStorage.getItem("lightmode") === "bekapcsolt") {
                document.body.classList.add("lightmode");
            }
        } catch (e) {}
    </script>

    <!-- Fény/Sötét mód kapcsoló gomb -->
    <button id="kapcsolo" aria-label="Világos és sötét mód váltása" type="button">
        <img alt="Sötét mód" decoding="async" height="28" src="<?= $pathPrefix ?>kepek/ikonok/holdacska.svg" width="28" />
        <img alt="Világos mód" decoding="async" height="28" src="<?= $pathPrefix ?>kepek/ikonok/napocska.svg" width="28" />
    </button>

    <!-- Felső navigáció -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container-fluid position-relative">
            <!-- Középre helyezett körvonalas logó gomb (Főoldal), amely lejjebb lóg a navbarból -->
            <a aria-label="Főoldal" title="Főoldal" class="navbar-brand-logo <?= $isHome ? 'active' : '' ?>" href="<?= $pathPrefix ?>index.php">
                <img alt="Empera logó" class="logo-empera-dark" decoding="async" src="<?= $pathPrefix ?>kepek/logo/enpera-feher.png" />
                <img alt="Empera logó" class="logo-empera-light" decoding="async" src="<?= $pathPrefix ?>kepek/logo/enpera-fekete.png" />
            </a>

            <!-- Mobilon a jobb felső hamburger ikon -->
            <button aria-controls="navbarNavDropdown" aria-expanded="false" aria-label="Menü megnyitása" class="navbar-toggler ms-auto d-lg-none" data-bs-target="#navbarNavDropdown" data-bs-toggle="collapse" type="button">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNavDropdown">
                <ul class="navbar-nav nav-left">
                    <li class="nav-item">
                        <a class="nav-link <?= ($activeNav === 'bandatagok' || $activeNav === 'diszkografia') ? 'active' : '' ?>" href="<?= $pathPrefix ?>bandatagok.php">
                            Diszkográfia
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= $activeNav === 'koncertek' ? 'active' : '' ?>" href="<?= $pathPrefix ?>koncertek.php">
                            Koncertek
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= $activeNav === 'galeria' ? 'active' : '' ?>" href="<?= $pathPrefix ?>galeria.php">
                            Galéria
                        </a>
                    </li>
                </ul>

                <ul class="navbar-nav nav-right">
                    <li class="nav-item">
                        <a class="nav-link <?= $activeNav === 'hirek' ? 'active' : '' ?>" href="<?= $pathPrefix ?>hirek.php">
                            Hírek
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= $activeNav === 'timeline' ? 'active' : '' ?>" href="<?= $pathPrefix ?>timeline.php">
                            Timeline
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link <?= $activeNav === 'kapcsolat' ? 'active' : '' ?>" href="<?= $pathPrefix ?>kapcsolat.php">
                            Kapcsolat
                        </a>
                    </li>

                    <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $activeNav === 'admin' ? 'active' : '' ?>" href="<?= $pathPrefix . ADMIN_ENTRY_FILE ?>" style="color: var(--accent); font-weight: bold;">
                                Vezérlőpult
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="<?= $pathPrefix . ADMIN_ENTRY_FILE ?>?action=logout">
                                Kijelentkezés
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>
