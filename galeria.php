<?php
$pageTitle = 'Galéria - N/A';
$pageDescription = 'N/A galéria: képek próbákról, koncertekről és zenekari pillanatokról.';
$activeNav = 'galeria';
$pathPrefix = '';
$extraCss = ['galeria.css'];

require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/db.php';

$albums = db_get_all_gallery();
?>

<main class="main-bg">
    <!-- HERO -->
    <section class="galeria-hero">
        <div class="container text-center">
            <p class="text-accent fw-bold text-uppercase mb-2">Próbák, koncertek és pillanatok</p>
            <h1 class="galeria-title">
                Galéria
            </h1>
            <p class="lead text-muted-custom mt-3 mx-auto" style="max-width: 850px;">
                Itt gyűjtjük az N/A fontosabb képeit: próbákat, koncerteket, háttérpillanatokat és minden olyan emléket, ami a banda történetéhez tartozik.
            </p>
        </div>
    </section>

    <!-- ALBUMOK -->
    <section class="py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="section-title text-accent">Albumok</h2>
                <p class="text-muted-custom">
                    A képek albumokba rendezve jelennek meg, így könnyen visszanézhetők a próbák, fellépések és fontosabb zenekari pillanatok.
                </p>
            </div>

            <div class="row g-4" id="galleryAlbumContainer">
                <?php if (empty($albums)): ?>
                    <div class="col-12 text-center">
                        <p class="text-muted-custom">Még nincs feltöltött galéria-album.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($albums as $album): 
                        $images = $album['images'] ?? [];
                        $cover = !empty($images) ? normalize_image_url($images[0]) : 'kepek/helyorzo/galeria-kartya.svg';
                        $normalizedImages = array_map(function($img) {
                            return normalize_image_url($img);
                        }, $images);
                        $jsonImages = htmlspecialchars(json_encode($normalizedImages), ENT_QUOTES, 'UTF-8');
                    ?>
                        <div class="col-lg-4 col-md-6">
                            <article class="card card-custom h-100 gallery-card">
                                <div class="gallery-card-image-box">
                                    <img src="<?= e($cover) ?>" alt="<?= e($album['title']) ?>" class="card-img-top gallery-cover-image" loading="lazy" decoding="async" />
                                    <span class="gallery-badge"><?= count($images) ?> kép</span>
                                </div>
                                <div class="card-body d-flex flex-column">
                                    <small class="text-muted-custom mb-1"><?= format_hungarian_date($album['date']) ?></small>
                                    <h3 class="card-title text-accent h5"><?= e($album['title']) ?></h3>
                                    <p class="card-text text-muted-custom mt-2 flex-grow-1">
                                        <?= nl2br(e($album['description'])) ?>
                                    </p>
                                    <?php if (!empty($images)): ?>
                                        <button class="btn btn-custom w-100 mt-3 open-album-btn" 
                                                data-title="<?= e($album['title']) ?>" 
                                                data-images='<?= $jsonImages ?>'
                                                type="button">
                                            Album megtekintése
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </article>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>
</main>

<!-- KÉPNÉZEGETŐ MODAL -->
<div class="gallery-modal d-none" id="galleryModal">
    <button class="gallery-close" id="closeGalleryModal" aria-label="Bezárás" type="button">
        <svg class="line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </svg>
    </button>
    <button class="gallery-arrow gallery-arrow-left" id="prevImage" aria-label="Előző kép" type="button">
        <svg class="line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="15 18 9 12 15 6"></polyline>
        </svg>
    </button>
    <div class="gallery-modal-content">
        <p class="gallery-modal-album" id="galleryAlbumTitle"></p>
        <img alt="Galéria kép" decoding="async" id="galleryModalImage" src="" />
        <p class="gallery-counter" id="galleryImageCounter"></p>
    </div>
    <button class="gallery-arrow gallery-arrow-right" id="nextImage" aria-label="Következő kép" type="button">
        <svg class="line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <polyline points="9 18 15 12 9 6"></polyline>
        </svg>
    </button>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const modal = document.getElementById("galleryModal");
    const modalImg = document.getElementById("galleryModalImage");
    const modalTitle = document.getElementById("galleryAlbumTitle");
    const modalCounter = document.getElementById("galleryImageCounter");
    const closeBtn = document.getElementById("closeGalleryModal");
    const prevBtn = document.getElementById("prevImage");
    const nextBtn = document.getElementById("nextImage");

    let currentImages = [];
    let currentIndex = 0;

    function updateModal() {
        if (!currentImages.length) return;
        modalImg.src = currentImages[currentIndex];
        modalCounter.textContent = (currentIndex + 1) + " / " + currentImages.length;
    }

    document.querySelectorAll(".open-album-btn").forEach(btn => {
        btn.addEventListener("click", function () {
            try {
                currentImages = JSON.parse(this.getAttribute("data-images") || "[]");
            } catch (e) {
                currentImages = [];
            }
            if (!currentImages.length) return;
            currentIndex = 0;
            modalTitle.textContent = this.getAttribute("data-title") || "";
            updateModal();
            modal.classList.remove("d-none");
            document.body.style.overflow = "hidden";
        });
    });

    function closeModal() {
        modal.classList.add("d-none");
        document.body.style.overflow = "";
    }

    if (closeBtn) closeBtn.addEventListener("click", closeModal);
    if (prevBtn) {
        prevBtn.addEventListener("click", function () {
            if (!currentImages.length) return;
            currentIndex = (currentIndex - 1 + currentImages.length) % currentImages.length;
            updateModal();
        });
    }
    if (nextBtn) {
        nextBtn.addEventListener("click", function () {
            if (!currentImages.length) return;
            currentIndex = (currentIndex + 1) % currentImages.length;
            updateModal();
        });
    }

    document.addEventListener("keydown", function (e) {
        if (modal.classList.contains("d-none")) return;
        if (e.key === "Escape") closeModal();
        if (e.key === "ArrowLeft" && prevBtn) prevBtn.click();
        if (e.key === "ArrowRight" && nextBtn) nextBtn.click();
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
