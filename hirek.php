<?php
$pageTitle = 'Hírek - N/A';
$pageDescription = 'N/A hírek, bejelentések és friss zenekari aktualitások.';
$activeNav = 'hirek';
$pathPrefix = '';
$extraCss = ['hirek.css'];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$newsList = db_get_all_news();
?>

<main class="main-bg">
    <section class="hirek-hero">
        <div class="container text-center">
            <p class="text-accent fw-bold text-uppercase mb-2">
                Friss hírek a zaj mélyéről
            </p>
            <h1 class="hirek-title">
                Hírek
            </h1>
            <p class="lead text-muted-custom mt-3 mx-auto" style="max-width: 850px;">
                Itt találod az N/A friss bejelentéseit, koncertinfóit, próbatermi híreit és minden olyan újdonságot, ami a bandával történik.
            </p>
        </div>
    </section>

    <section class="py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="section-title text-accent">
                    Legfrissebb bejegyzések
                </h2>
                <p class="text-muted-custom">
                    A legújabb bejegyzések időrendben jelennek meg, így mindig látható, min dolgozik éppen a banda.
                </p>
            </div>

            <div class="row g-4" id="newsContainer">
                <?php if (empty($newsList)): ?>
                    <div class="col-12 text-center">
                        <p class="text-muted-custom">Jelenleg még nincs feltöltött hír.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($newsList as $item): ?>
                        <div class="col-lg-4 col-md-6">
                            <article class="card card-custom h-100 news-card">
                                <?php 
                                    $img = trim($item['image'] ?? '');
                                    $hasRealImage = (!empty($img) && stripos($img, 'news-default.svg') === false);
                                ?>
                                <?php if ($hasRealImage): ?>
                                    <div class="card-image-box">
                                        <img src="<?= e(normalize_image_url($img)) ?>" alt="<?= e($item['title']) ?>" class="card-img-top news-card-image" loading="lazy" decoding="async" />
                                    </div>
                                <?php endif; ?>
                                <div class="card-body d-flex flex-column">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="badge bg-secondary"><?= e($item['type']) ?></span>
                                        <small class="news-date"><?= format_hungarian_date($item['date']) ?></small>
                                    </div>
                                    <h3 class="card-title text-accent h5"><?= e($item['title']) ?></h3>
                                    <p class="card-text text-muted-custom mt-2 flex-grow-1">
                                        <?= nl2br(e($item['text'])) ?>
                                    </p>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
