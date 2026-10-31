<?php
$pageTitle = 'Timeline - N/A';
$pageDescription = 'N/A timeline: a zenekar története időrendben, alakulástól a koncertekig.';
$activeNav = 'timeline';
$pathPrefix = '';
$extraCss = ['timeline.css'];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$timelineEvents = db_get_all_timeline();
?>

<main class="main-bg">
    <!-- HERO -->
    <section class="timeline-hero">
        <div class="container text-center">
            <p class="text-accent fw-bold text-uppercase mb-2">
                A banda története időrendben
            </p>
            <h1 class="timeline-title">
                Timeline
            </h1>
            <p class="lead text-muted-custom mt-3 mx-auto" style="max-width: 850px;">
                A timeline oldalon időrendben követheted, hogyan épül fel az N/A története a kezdetektől a próbákon át a koncertekig.
            </p>
        </div>
    </section>

    <!-- IDŐVONAL -->
    <section class="py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="section-title text-accent">
                    Idővonal
                </h2>
                <p class="text-muted-custom">
                    Az események időrendben, csúsztatható formában jelennek meg, így könnyen visszanézhető, mi mikor történt a bandával.
                </p>
            </div>

            <div class="timeline-controls">
                <button class="btn btn-custom" id="timelineLeft" type="button">
                    Balra
                </button>
                <button class="btn btn-custom" id="timelineRight" type="button">
                    Jobbra
                </button>
            </div>

            <div class="timeline-scroll-wrapper" id="timelineScrollWrapper">
                <div class="timeline-container" id="timelineContainer">
                    <?php if (empty($timelineEvents)): ?>
                        <div class="text-center w-100 py-4">
                            <p class="text-muted-custom">Még nincs feltöltött timeline esemény.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($timelineEvents as $event): ?>
                            <article class="timeline-item">
                                <div class="timeline-card card-custom">
                                    <div class="timeline-date-badge">
                                        <?= format_hungarian_date($event['date']) ?>
                                    </div>

                                    <h3 class="timeline-card-title text-accent mt-2">
                                        <?= e($event['title']) ?>
                                    </h3>

                                    <span class="badge bg-secondary mb-2">
                                        <?= e($event['type']) ?>
                                    </span>

                                    <?php if (!empty($event['image'])): ?>
                                        <div class="timeline-image-box my-2">
                                            <img src="<?= e(normalize_image_url($event['image'])) ?>" alt="<?= e($event['title']) ?>" class="img-fluid rounded" loading="lazy" />
                                        </div>
                                    <?php endif; ?>

                                    <p class="timeline-card-text text-muted-custom mt-2">
                                        <?= nl2br(e($event['text'])) ?>
                                    </p>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- TÁJÉKOZTATÓ PANEL -->
    <section class="py-5 section-bg">
        <div class="container py-4">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <h2 class="section-title text-accent">
                        Mit mutat az idővonal?
                    </h2>
                    <p class="text-muted-custom mt-3">
                        Az idővonal a banda fontosabb pillanatait gyűjti össze: alakulást, próbákat, koncerteket, képfeltöltéseket és egyéb mérföldköveket.
                    </p>
                    <p class="text-muted-custom">
                        Az idővonal folyamatosan bővül az újabb zenekari eseményekkel és mérföldkövekkel.
                    </p>
                </div>
                <div class="col-lg-6">
                    <div class="timeline-info-panel">
                        <h3>Eseménytípusok</h3>
                        <ul>
                            <li><span>Alakulás:</span> a zenekar indulásának lépései</li>
                            <li><span>Próba:</span> felkészülések, dalírás, próbatermi élet</li>
                            <li><span>Koncert:</span> bejelentések és fellépések</li>
                            <li><span>Galéria:</span> friss képek megjelenése</li>
                            <li><span>Egyéb:</span> minden más zenekari történés</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const wrapper = document.getElementById("timelineScrollWrapper");
    const leftBtn = document.getElementById("timelineLeft");
    const rightBtn = document.getElementById("timelineRight");

    if (wrapper && leftBtn && rightBtn) {
        leftBtn.addEventListener("click", () => {
            wrapper.scrollBy({ left: -340, behavior: "smooth" });
        });
        rightBtn.addEventListener("click", () => {
            wrapper.scrollBy({ left: 340, behavior: "smooth" });
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
