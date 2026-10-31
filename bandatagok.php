<?php
require_once __DIR__ . '/includes/db.php';

$allReleases = db_get_all_discography();
$lemezList = [];
$kislemezList = [];
$singleList = [];

foreach ($allReleases as $r) {
    if (($r['category'] ?? '') === 'kislemez') {
        $kislemezList[] = $r;
    } elseif (($r['category'] ?? '') === 'single') {
        $singleList[] = $r;
    } else {
        $lemezList[] = $r;
    }
}

$openReleaseId = isset($_GET['kiadvany']) ? (int)$_GET['kiadvany'] : 0;
$openedRelease = $openReleaseId > 0 ? db_get_discography_by_id($openReleaseId) : null;

$activeCategoryPage = $_GET['oldal'] ?? 'osszes';
if (!in_array($activeCategoryPage, ['osszes', 'lemez', 'kislemez', 'single'], true)) {
    $activeCategoryPage = 'osszes';
}
if ($openedRelease) {
    $activeCategoryPage = $openedRelease['category'] ?? 'lemez';
}

$pageTitle = $openedRelease
    ? ($openedRelease['title'] . ' (' . get_discography_category_label($openedRelease['category']) . ') - Diszkográfia - N/A')
    : 'Diszkográfia – Lemezek, Kislemezek & Single-ök - N/A';
$pageDescription = 'N/A hivatalos diszkográfia: kiadott nagylemezek, kislemezek (EP) és single megjelenések teljes dallistával.';
$activeNav = 'bandatagok';
$pathPrefix = '';
$extraCss = ['bandatagok.css'];

require_once __DIR__ . '/includes/header.php';

function render_release_card(array $release): void {
    $tracks = $release['tracks'] ?? [];
    $trackCount = count($tracks);
    $cover = trim($release['cover_image'] ?? '');
    $hasCover = !empty($cover);
    $catLabel = get_discography_category_label($release['category'] ?? 'lemez');
    $detailUrl = 'bandatagok.php?kiadvany=' . (int)$release['id'];
    ?>
    <div class="col-xl-4 col-md-6">
        <article class="disco-release-card">
            <a href="<?= e($detailUrl) ?>" class="disco-cover-link" aria-label="<?= e($release['title']) ?> megnyitása">
                <div class="disco-cover-badges">
                    <span class="disco-type-badge"><?= e($catLabel) ?></span>
                    <span class="disco-track-badge"><?= $trackCount ?> dal</span>
                </div>

                <?php if ($hasCover): ?>
                    <img src="<?= e(normalize_image_url($cover)) ?>" alt="<?= e($release['title']) ?> borító" class="disco-cover-img" loading="lazy" decoding="async" />
                <?php else: ?>
                    <div class="disco-cover-placeholder">
                        <div class="disco-vinyl-disc"></div>
                        <span class="fw-bold text-uppercase small" style="letter-spacing: 2px; opacity: 0.85;">N/A • <?= e($catLabel) ?></span>
                    </div>
                <?php endif; ?>
            </a>

            <div class="disco-card-body">
                <div class="disco-release-date">
                    <svg class="line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 15px; height: 15px;">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    <span><?= format_hungarian_date($release['release_date']) ?></span>
                </div>

                <h3 class="disco-release-title">
                    <a href="<?= e($detailUrl) ?>"><?= e($release['title']) ?></a>
                </h3>

                <p class="disco-release-desc">
                    <?= nl2br(e($release['description'] ?: 'Kattints a kiadvány megnyitásához és a dallista megtekintéséhez.')) ?>
                </p>

                <a href="<?= e($detailUrl) ?>" class="btn btn-custom w-100 d-inline-flex align-items-center justify-content-center gap-2">
                    <span>Album megnyitása (<?= $trackCount ?> dal)</span>
                    <svg class="line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        </article>
    </div>
    <?php
}
?>

<main class="main-bg">
    <!-- HERO SZEKCIÓ -->
    <section class="bandatagok-hero text-center">
        <div class="container">
            <p class="text-accent fw-bold text-uppercase mb-2 tracking-wide">
                <?= e(site_text('discography_hero_eyebrow')) ?>
            </p>
            <h1 class="bandatagok-title">
                <?= e(site_text('discography_hero_title')) ?>
            </h1>
            <p class="lead bandatagok-leiras mt-3 mx-auto">
                <?= nl2br(e(site_text('discography_hero_subtitle'))) ?>
            </p>

            <!-- KATEGÓRIA OLDALAK VÁLASZTÓJA: LEMEZ / KISLEMEZ / SINGLE / ÖSSZES -->
            <div class="disco-page-nav" role="navigation" aria-label="Diszkográfia oldalak">
                <a href="bandatagok.php?oldal=osszes"
                   data-disco-page="osszes"
                   class="disco-page-tab <?= (!$openedRelease && $activeCategoryPage === 'osszes') ? 'active' : '' ?>">
                    <span>Összes kiadvány</span>
                    <span class="disco-tab-count"><?= count($allReleases) ?></span>
                </a>

                <a href="bandatagok.php?oldal=lemez"
                   data-disco-page="lemez"
                   class="disco-page-tab <?= ($activeCategoryPage === 'lemez') ? 'active' : '' ?>">
                    <svg class="line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 17px; height: 17px;">
                        <circle cx="12" cy="12" r="10"></circle>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                    <span>Lemezek (Album)</span>
                    <span class="disco-tab-count"><?= count($lemezList) ?></span>
                </a>

                <a href="bandatagok.php?oldal=kislemez"
                   data-disco-page="kislemez"
                   class="disco-page-tab <?= ($activeCategoryPage === 'kislemez') ? 'active' : '' ?>">
                    <svg class="line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 17px; height: 17px;">
                        <path d="M9 18V5l12-2v13"></path>
                        <circle cx="6" cy="18" r="3"></circle>
                        <circle cx="18" cy="16" r="3"></circle>
                    </svg>
                    <span>Kislemezek (EP)</span>
                    <span class="disco-tab-count"><?= count($kislemezList) ?></span>
                </a>

                <a href="bandatagok.php?oldal=single"
                   data-disco-page="single"
                   class="disco-page-tab <?= ($activeCategoryPage === 'single') ? 'active' : '' ?>">
                    <svg class="line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 17px; height: 17px;">
                        <polygon points="5 3 19 12 5 21 5 3"></polygon>
                    </svg>
                    <span>Single-ök</span>
                    <span class="disco-tab-count"><?= count($singleList) ?></span>
                </a>
            </div>
        </div>
    </section>

    <?php if ($openedRelease): 
        $tracks = $openedRelease['tracks'] ?? [];
        $totalDuration = calculate_release_duration($tracks);
        $cover = trim($openedRelease['cover_image'] ?? '');
        $catLabel = get_discography_category_label($openedRelease['category'] ?? 'lemez');
    ?>
        <!-- =================================================================
             MEGNYITOTT KIADVÁNY / ALBUM OLDAL (?kiadvany=ID)
             ================================================================= -->
        <section class="py-5" id="megnyitott-album">
            <div class="container py-3">
                <!-- Vissza navigáció -->
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
                    <div class="d-flex flex-wrap gap-2">
                        <a href="bandatagok.php?oldal=<?= e($openedRelease['category']) ?>" class="btn btn-outline-secondary btn-sm d-inline-flex align-items-center gap-2">
                            <svg class="line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                                <path d="M19 12H5M12 19l-7-7 7-7"/>
                            </svg>
                            <span>Vissza ide: <?= e($catLabel) ?> oldal</span>
                        </a>
                        <a href="bandatagok.php?oldal=osszes" class="btn btn-outline-secondary btn-sm">
                            Összes kiadvány
                        </a>
                    </div>
                </div>

                <!-- Album fejléc kártya -->
                <div class="disco-detail-card mb-5">
                    <div class="row g-4 g-lg-5 align-items-center">
                        <div class="col-lg-5 col-md-6">
                            <div class="disco-detail-cover-wrap">
                                <?php if (!empty($cover)): ?>
                                    <img src="<?= e(normalize_image_url($cover)) ?>" alt="<?= e($openedRelease['title']) ?> borító" />
                                <?php else: ?>
                                    <div class="disco-cover-placeholder">
                                        <div class="disco-vinyl-disc" style="width: 140px; height: 140px;"></div>
                                        <span class="fw-bold text-uppercase" style="letter-spacing: 2px;"><?= e($openedRelease['title']) ?></span>
                                        <small class="text-muted-custom mt-1"><?= e($catLabel) ?></small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-lg-7 col-md-6">
                            <div class="disco-meta-pills">
                                <span class="disco-meta-pill accent"><?= e($catLabel) ?></span>
                                <span class="disco-meta-pill">Megjelenés: <?= format_hungarian_date($openedRelease['release_date']) ?></span>
                                <span class="disco-meta-pill"><?= count($tracks) ?> dal</span>
                                <?php if (!empty($totalDuration)): ?>
                                    <span class="disco-meta-pill">Hossz: <?= e($totalDuration) ?></span>
                                <?php endif; ?>
                            </div>

                            <h2 class="section-title text-accent mb-3" style="font-size: clamp(2rem, 4.5vw, 3.2rem);">
                                <?= e($openedRelease['title']) ?>
                            </h2>

                            <p class="text-muted-custom lead mb-4" style="line-height: 1.7;">
                                <?= nl2br(e($openedRelease['description'] ?: 'Az N/A hivatalos kiadványa.')) ?>
                            </p>

                            <!-- Streaming / Hallgatási gombok ha meg vannak adva -->
                            <?php
                            $hasStreamLinks = !empty($openedRelease['spotify_url'])
                                || !empty($openedRelease['youtube_url'])
                                || !empty($openedRelease['apple_url'])
                                || !empty($openedRelease['other_url']);
                            ?>
                            <?php if ($hasStreamLinks): ?>
                                <div class="pt-3 border-top border-secondary">
                                    <p class="small text-uppercase fw-bold text-accent mb-2" style="letter-spacing: 1px;">
                                        Hallgasd meg itt:
                                    </p>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php if (!empty($openedRelease['spotify_url'])): ?>
                                            <a href="<?= e($openedRelease['spotify_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-custom btn-sm px-3">
                                                Spotify
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($openedRelease['youtube_url'])): ?>
                                            <a href="<?= e($openedRelease['youtube_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-custom btn-sm px-3">
                                                YouTube
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($openedRelease['apple_url'])): ?>
                                            <a href="<?= e($openedRelease['apple_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-custom btn-sm px-3">
                                                Apple Music
                                            </a>
                                        <?php endif; ?>
                                        <?php if (!empty($openedRelease['other_url'])): ?>
                                            <a href="<?= e($openedRelease['other_url']) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-light btn-sm px-3">
                                                További link
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- DALLISTA (TRACKLIST) -->
                <div class="tracklist-container mb-5">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 pb-3 border-bottom border-secondary">
                        <div>
                            <h3 class="text-accent fw-bold mb-1">Dallista – <?= e($openedRelease['title']) ?></h3>
                            <p class="text-muted-custom small mb-0">
                                Összesen <?= count($tracks) ?> dal a kiadványon<?= $totalDuration ? ' • Teljes játékidő: ' . e($totalDuration) : '' ?>
                            </p>
                        </div>
                    </div>

                    <?php if (empty($tracks)): ?>
                        <p class="text-muted-custom mb-0">Ehhez a kiadványhoz még nincs feltöltve dallista.</p>
                    <?php else: ?>
                        <?php foreach ($tracks as $idx => $track):
                            $trackNum = str_pad((string)($idx + 1), 2, '0', STR_PAD_LEFT);
                            $trackTitle = trim($track['title'] ?? 'Névtelen dal');
                            $trackDur = trim($track['duration'] ?? '');
                            $trackLyrics = trim($track['lyrics'] ?? '');
                            $lyricsCollapseId = 'trackLyrics_' . $idx;
                        ?>
                            <div class="tracklist-item">
                                <div class="tracklist-row">
                                    <div class="tracklist-left">
                                        <span class="track-number"><?= e($trackNum) ?></span>
                                        <h4 class="track-title"><?= e($trackTitle) ?></h4>
                                    </div>

                                    <div class="tracklist-right">
                                        <?php if (!empty($trackLyrics)): ?>
                                            <button class="btn btn-sm btn-outline-secondary"
                                                    type="button"
                                                    data-bs-toggle="collapse"
                                                    data-bs-target="#<?= e($lyricsCollapseId) ?>"
                                                    aria-expanded="false">
                                                Dalszöveg / infó
                                            </button>
                                        <?php endif; ?>

                                        <?php if (!empty($trackDur)): ?>
                                            <span class="track-duration"><?= e($trackDur) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if (!empty($trackLyrics)): ?>
                                    <div class="collapse" id="<?= e($lyricsCollapseId) ?>">
                                        <div class="track-lyrics-box">
                                            <?= nl2br(e($trackLyrics)) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- TOVÁBBI KIADVÁNYOK A DISZKOGRÁFIÁBÓL -->
                <?php
                $otherReleases = array_values(array_filter($allReleases, function($r) use ($openedRelease) {
                    return (int)$r['id'] !== (int)$openedRelease['id'];
                }));
                ?>
                <?php if (!empty($otherReleases)): ?>
                    <div class="pt-3">
                        <h3 class="section-title text-accent text-center mb-4">További kiadványaink</h3>
                        <div class="row g-4 justify-content-center">
                            <?php foreach ($otherReleases as $rel): ?>
                                <?php render_release_card($rel); ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

    <?php else: ?>
        <!-- =================================================================
             OLDALANKÉNTI NÉZET: LEMEZ / KISLEMEZ / SINGLE
             ================================================================= -->
        <section class="py-5">
            <div class="container py-3">

                <!-- 1. LEMEZEK (NAGYLEMEZEK) OLDAL -->
                <div class="disco-category-section mb-5 <?= ($activeCategoryPage !== 'osszes' && $activeCategoryPage !== 'lemez') ? 'd-none' : '' ?>"
                     data-category-section="lemez"
                     id="oldal-lemez">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4 pb-3 border-bottom" style="border-color: var(--border-color) !important;">
                        <div>
                            <span class="badge bg-secondary text-uppercase mb-2">1. Oldal • LP / Album</span>
                            <h2 class="section-title text-accent mb-1">
                                <?= e(site_text('discography_lemez_title')) ?>
                            </h2>
                            <p class="text-muted-custom mb-0">
                                <?= e(site_text('discography_lemez_subtitle')) ?>
                            </p>
                        </div>
                        <div class="mt-2 mt-md-0">
                            <span class="disco-meta-pill"><?= count($lemezList) ?> lemez</span>
                        </div>
                    </div>

                    <div class="row g-4">
                        <?php if (empty($lemezList)): ?>
                            <div class="col-12">
                                <div class="card card-custom p-4 text-center">
                                    <p class="text-muted-custom mb-0">Jelenleg még nincs feltöltött nagylemez ebben a kategóriában.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($lemezList as $rel): ?>
                                <?php render_release_card($rel); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. KISLEMEZEK (EP) OLDAL -->
                <div class="disco-category-section mb-5 <?= ($activeCategoryPage !== 'osszes' && $activeCategoryPage !== 'kislemez') ? 'd-none' : '' ?>"
                     data-category-section="kislemez"
                     id="oldal-kislemez">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4 pb-3 border-bottom" style="border-color: var(--border-color) !important;">
                        <div>
                            <span class="badge bg-secondary text-uppercase mb-2">2. Oldal • EP / Kislemez</span>
                            <h2 class="section-title text-accent mb-1">
                                <?= e(site_text('discography_kislemez_title')) ?>
                            </h2>
                            <p class="text-muted-custom mb-0">
                                <?= e(site_text('discography_kislemez_subtitle')) ?>
                            </p>
                        </div>
                        <div class="mt-2 mt-md-0">
                            <span class="disco-meta-pill"><?= count($kislemezList) ?> kislemez</span>
                        </div>
                    </div>

                    <div class="row g-4">
                        <?php if (empty($kislemezList)): ?>
                            <div class="col-12">
                                <div class="card card-custom p-4 text-center">
                                    <p class="text-muted-custom mb-0">Jelenleg még nincs feltöltött kislemez (EP) ebben a kategóriában.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($kislemezList as $rel): ?>
                                <?php render_release_card($rel); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 3. SINGLE-ÖK OLDAL -->
                <div class="disco-category-section mb-4 <?= ($activeCategoryPage !== 'osszes' && $activeCategoryPage !== 'single') ? 'd-none' : '' ?>"
                     data-category-section="single"
                     id="oldal-single">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-4 pb-3 border-bottom" style="border-color: var(--border-color) !important;">
                        <div>
                            <span class="badge bg-secondary text-uppercase mb-2">3. Oldal • Single</span>
                            <h2 class="section-title text-accent mb-1">
                                <?= e(site_text('discography_single_title')) ?>
                            </h2>
                            <p class="text-muted-custom mb-0">
                                <?= e(site_text('discography_single_subtitle')) ?>
                            </p>
                        </div>
                        <div class="mt-2 mt-md-0">
                            <span class="disco-meta-pill"><?= count($singleList) ?> single</span>
                        </div>
                    </div>

                    <div class="row g-4">
                        <?php if (empty($singleList)): ?>
                            <div class="col-12">
                                <div class="card card-custom p-4 text-center">
                                    <p class="text-muted-custom mb-0">Jelenleg még nincs feltöltött single ebben a kategóriában.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($singleList as $rel): ?>
                                <?php render_release_card($rel); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </section>
    <?php endif; ?>

    <!-- ALSÓ ÖSSZEFOGLALÓ SZEKCIÓ -->
    <section class="py-5 section-bg">
        <div class="container py-4 text-center">
            <h2 class="section-title text-accent">
                <?= e(site_text('discography_outro_title')) ?>
            </h2>
            <p class="text-muted-custom mt-3 mx-auto max-w-700">
                <?= nl2br(e(site_text('discography_outro_p1'))) ?>
            </p>
            <p class="text-muted-custom mx-auto max-w-700">
                <?= nl2br(e(site_text('discography_outro_p2'))) ?>
            </p>
            <div class="mt-4 d-flex flex-wrap justify-content-center gap-3">
                <a class="btn btn-custom btn-lg" href="koncertek.php">
                    <?= e(site_text('discography_outro_btn1')) ?>
                </a>
                <a class="btn btn-custom btn-lg" href="kapcsolat.php">
                    <?= e(site_text('discography_outro_btn2')) ?>
                </a>
            </div>
        </div>
    </section>
</main>

<?php if (!$openedRelease): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const tabs = document.querySelectorAll("[data-disco-page]");
    const sections = document.querySelectorAll("[data-category-section]");

    tabs.forEach(function (tab) {
        tab.addEventListener("click", function (e) {
            const page = this.getAttribute("data-disco-page");
            if (!page || !sections.length) return;
            e.preventDefault();

            tabs.forEach(t => t.classList.remove("active"));
            this.classList.add("active");

            sections.forEach(function (sec) {
                const cat = sec.getAttribute("data-category-section");
                if (page === "osszes" || cat === page) {
                    sec.classList.remove("d-none");
                } else {
                    sec.classList.add("d-none");
                }
            });

            if (window.history && window.history.replaceState) {
                const newUrl = "bandatagok.php?oldal=" + encodeURIComponent(page);
                window.history.replaceState({ page: page }, "", newUrl);
            }
        });
    });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
