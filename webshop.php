<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/content.php';
require_once __DIR__ . '/includes/shop_db.php';

$shopNotice = '';
$shopError = '';

// Kosárba helyezés feldolgozása
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['shop_action'])) {
    $action = $_POST['shop_action'];

    if ($action === 'add_to_cart' || $action === 'buy_now') {
        $productId = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['qty'] ?? 1));
        $size = trim($_POST['size'] ?? '');

        if (shop_add_to_cart($productId, $qty, $size)) {
            if ($action === 'buy_now') {
                header('Location: kosar.php');
                exit;
            }
            $prod = shop_get_product_by_id($productId);
            $prodName = $prod ? $prod['name'] : 'Tárgy';
            $shopNotice = 'A kiválasztott tárgy bekerült a kosárba: ' . $prodName . ($size !== '' ? ' (' . $size . ')' : '') . ' × ' . $qty . ' db.';
        } else {
            $shopError = 'A kiválasztott tárgy jelenleg nincs készleten.';
        }
    }
}

// URL paraméterek: megnyitott tárgy (?termek=ID), kategória (?kategoria=...), rendezés (?rendezes=...)
$openedProductId = isset($_GET['termek']) ? (int)$_GET['termek'] : 0;
$openedProduct = $openedProductId > 0 ? shop_get_product_by_id($openedProductId) : null;

$activeCategory = isset($_GET['kategoria']) ? trim($_GET['kategoria']) : '';
$sortBy = $_GET['rendezes'] ?? 'kiemelt';

$allProducts = shop_get_all_products();
$categories = get_shop_categories();

// Kategória szűrés
$filteredProducts = [];
if ($activeCategory !== '' && $activeCategory !== 'osszes') {
    foreach ($allProducts as $prod) {
        if (($prod['category'] ?? '') === $activeCategory) {
            $filteredProducts[] = $prod;
        }
    }
} else {
    $filteredProducts = $allProducts;
}

// Rendezés
if ($sortBy === 'ar_novekvo') {
    usort($filteredProducts, fn($a, $b) => ((int)$a['price']) <=> ((int)$b['price']));
} elseif ($sortBy === 'ar_csokkeno') {
    usort($filteredProducts, fn($a, $b) => ((int)$b['price']) <=> ((int)$a['price']));
} elseif ($sortBy === 'nev_az') {
    usort($filteredProducts, fn($a, $b) => strcmp($a['name'], $b['name']));
}

// Kiemelt tárgyak a főnézethez
$featuredTop = array_slice($allProducts, 0, 2);

// Kategória kártyák egységes helyőrző képpel
$placeholderImg = 'kepek/helyorzo/webshop-default.svg';
$categoryCards = [
    'puloverek' => [
        'label' => 'Pulóverek',
        'image' => $placeholderImg
    ],
    'polok' => [
        'label' => 'Pólók',
        'image' => $placeholderImg
    ],
    'kiegeszito' => [
        'label' => 'Kiegészítők',
        'image' => $placeholderImg
    ],
    'lemez' => [
        'label' => 'CD / Vinyl',
        'image' => $placeholderImg
    ]
];

$pageTitle = $openedProduct
    ? $openedProduct['name'] . ' | N/A Webshop'
    : 'N/A – Webshop';
$activeNav = 'webshop';
$extraCss = ['webshop.css'];

include __DIR__ . '/includes/header.php';
?>

<!-- HERO FEJLÉC -->
<header class="webshop-hero">
    <div class="container text-center">
        <p class="text-uppercase fw-bold mb-2 text-muted-custom">N/A Zenekar • Hivatalos Merchandise</p>
        <h1 class="webshop-title">WEBSHOP</h1>
        <p class="lead hero-subtitle mx-auto mb-0">
            Zenekari pólók, pulóverek, lemezek és kiegészítők – házhozszállítással vagy csomagpontra.
        </p>
    </div>
</header>

<!-- WEBSHOP KATEGÓRIA SÁV -->
<nav class="shop-subnav">
    <div class="container">
        <div class="shop-subnav-inner">
            <div class="shop-cat-pills">
                <a href="webshop.php" class="shop-cat-pill <?= ($activeCategory === '' && !$openedProduct) ? 'active' : '' ?>">
                    Főoldal
                </a>
                <a href="webshop.php?kategoria=osszes" class="shop-cat-pill <?= $activeCategory === 'osszes' ? 'active' : '' ?>">
                    Összes tárgy
                </a>
                <?php foreach ($categories as $catKey => $catLabel): ?>
                    <a href="webshop.php?kategoria=<?= htmlspecialchars($catKey) ?>" class="shop-cat-pill <?= $activeCategory === $catKey ? 'active' : '' ?>">
                        <?= htmlspecialchars($catLabel) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</nav>

<main class="main-bg py-5">
    <div class="container">

        <!-- ÉRTESÍTÉSEK -->
        <?php if ($shopNotice !== ''): ?>
            <div class="shop-notice-box mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
                <div>
                    <strong>KOSÁR FRISSÍTVE:</strong> <?= htmlspecialchars($shopNotice) ?>
                </div>
                <a href="kosar.php" class="btn btn-custom btn-sm">Kosár megnyitása →</a>
            </div>
        <?php endif; ?>

        <?php if ($shopError !== ''): ?>
            <div class="shop-notice-box mb-4">
                <strong>FIGYELEM:</strong> <?= htmlspecialchars($shopError) ?>
            </div>
        <?php endif; ?>

        <?php if ($openedProduct): ?>
            <!-- ==========================================================
                 1. NÉZET: MEGNYITOTT TÁRGY RÉSZLETES OLDALA (?termek=ID)
                 ========================================================== -->
            <?php
                $prodImg = get_product_default_image($openedProduct);
                $sizesRaw = trim($openedProduct['sizes'] ?? '');
                $sizeList = $sizesRaw !== '' ? array_values(array_filter(array_map('trim', explode(',', $sizesRaw)))) : [];
                $hasSizeChart = in_array($openedProduct['category'] ?? '', ['polok', 'puloverek', 'ruhazat'], true);
                $stockCount = (int)($openedProduct['stock'] ?? 0);
            ?>

            <div class="mb-4">
                <a href="webshop.php" class="btn btn-outline-custom btn-sm">← Vissza a webshopba</a>
            </div>

            <div class="product-detail-card mb-5">
                <div class="row g-4 g-lg-5 align-items-start">
                    <!-- BAL OLDAL: NAGY KÉP + ELŐNÉZETI KÉPVÁLTÓ (TERMÉKKÉP / MÉRETTÁBLÁZAT) -->
                    <div class="col-lg-6">
                        <div class="product-detail-main-img">
                            <img id="detailMainImg" src="<?= htmlspecialchars($prodImg) ?>" alt="<?= htmlspecialchars($openedProduct['name']) ?>">
                        </div>

                        <?php if ($hasSizeChart): ?>
                            <div class="product-detail-thumbs">
                                <button type="button" class="product-thumb-btn active" onclick="switchProductImage(this, '<?= htmlspecialchars($prodImg, ENT_QUOTES) ?>')" title="Termékkép">
                                    <img src="<?= htmlspecialchars($prodImg) ?>" alt="Termékkép">
                                </button>
                                <button type="button" class="product-thumb-btn" onclick="switchProductImage(this, 'kepek/webshop/merettablazat.svg')" title="Mérettáblázat">
                                    <img src="kepek/webshop/merettablazat.svg" alt="Mérettáblázat">
                                </button>
                            </div>
                            <div class="small text-muted-custom mt-2">
                                Kattints a második képre a pontos mérettáblázat megtekintéséhez.
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- JOBB OLDAL: ADATOK, MÉRETVÁLASZTÓ, MENNYISÉG ÉS MEGRENDELÉS GOMBOK -->
                    <div class="col-lg-6">
                        <span class="badge bg-light text-dark text-uppercase mb-2 px-3 py-1">
                            <?= htmlspecialchars(get_shop_category_label($openedProduct['category'] ?? 'polok')) ?>
                        </span>

                        <h2 class="product-detail-title"><?= htmlspecialchars($openedProduct['name']) ?></h2>

                        <div class="product-detail-price"><?= format_huf((int)$openedProduct['price']) ?></div>
                        <p class="small text-muted-custom mb-4">
                            Tartalmazza az ÁFÁ-t. •
                            <?php if ($stockCount > 0): ?>
                                Raktáron: <strong><?= $stockCount ?> db</strong>
                            <?php else: ?>
                                <strong>Jelenleg nincs készleten</strong>
                            <?php endif; ?>
                        </p>

                        <div class="text-muted-custom mb-4" style="white-space: pre-line; line-height: 1.7;">
                            <?= htmlspecialchars($openedProduct['description'] ?: 'Hivatalos N/A zenekari merchandise tárgy.') ?>
                        </div>

                        <form method="post" action="webshop.php?termek=<?= (int)$openedProduct['id'] ?>">
                            <input type="hidden" name="product_id" value="<?= (int)$openedProduct['id'] ?>">

                            <?php if (!empty($sizeList)): ?>
                                <div class="mb-4">
                                    <label class="form-label fw-bold text-uppercase small d-block mb-2">Méret kiválasztása</label>
                                    <input type="hidden" name="size" id="selectedSizeInput" value="<?= htmlspecialchars($sizeList[0]) ?>">
                                    <div class="size-pill-group">
                                        <?php foreach ($sizeList as $idx => $sz): ?>
                                            <button
                                                type="button"
                                                class="size-pill-btn <?= $idx === 0 ? 'active' : '' ?>"
                                                onclick="selectProductSize(this, '<?= htmlspecialchars($sz, ENT_QUOTES) ?>')"
                                            >
                                                <?= htmlspecialchars($sz) ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mb-4">
                                <label class="form-label fw-bold text-uppercase small d-block mb-2">Mennyiség</label>
                                <div class="qty-stepper">
                                    <button type="button" class="qty-stepper-btn" onclick="stepProductQty(-1)">−</button>
                                    <input
                                        type="number"
                                        name="qty"
                                        id="detailQtyInput"
                                        class="qty-stepper-input"
                                        value="1"
                                        min="1"
                                        max="<?= max(1, $stockCount) ?>"
                                        readonly
                                    >
                                    <button type="button" class="qty-stepper-btn" onclick="stepProductQty(1)">+</button>
                                </div>
                            </div>

                            <div class="d-grid gap-3">
                                <button
                                    type="submit"
                                    name="shop_action"
                                    value="add_to_cart"
                                    class="btn btn-outline-custom btn-lg py-3 text-uppercase fw-bold"
                                    <?= $stockCount <= 0 ? 'disabled' : '' ?>
                                >
                                    Hozzáadás a kosárhoz
                                </button>

                                <button
                                    type="submit"
                                    name="shop_action"
                                    value="buy_now"
                                    class="btn btn-custom btn-lg py-3 text-uppercase fw-bold"
                                    <?= $stockCount <= 0 ? 'disabled' : '' ?>
                                >
                                    Azonnali megrendelés &amp; Fizetés →
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- KAPCSOLÓDÓ TÁRGYAK -->
            <section>
                <h3 class="section-title h4 mb-4">TOVÁBBI TÁRGYAK A WEBSHOPBÓL</h3>
                <div class="row g-4">
                    <?php
                    $relCount = 0;
                    foreach ($allProducts as $rel):
                        if ((int)$rel['id'] === (int)$openedProduct['id']) continue;
                        $relCount++;
                        if ($relCount > 4) break;
                        $relImg = get_product_default_image($rel);
                    ?>
                        <div class="col-sm-6 col-lg-3">
                            <div class="shop-product-card">
                                <a href="webshop.php?termek=<?= (int)$rel['id'] ?>" class="shop-product-thumb">
                                    <img src="<?= htmlspecialchars($relImg) ?>" alt="<?= htmlspecialchars($rel['name']) ?>">
                                    <span class="shop-product-badge"><?= htmlspecialchars(get_shop_category_label($rel['category'] ?? '')) ?></span>
                                </a>
                                <div class="shop-product-body">
                                    <h4 class="shop-product-title">
                                        <a href="webshop.php?termek=<?= (int)$rel['id'] ?>"><?= htmlspecialchars($rel['name']) ?></a>
                                    </h4>
                                    <div class="shop-product-price"><?= format_huf((int)$rel['price']) ?></div>
                                    <div class="mt-auto">
                                        <a href="webshop.php?termek=<?= (int)$rel['id'] ?>" class="btn btn-outline-custom btn-sm w-100">
                                            Megnyitás →
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>

        <?php elseif ($activeCategory !== ''): ?>
            <!-- ==========================================================
                 2. NÉZET: KATEGÓRIA / SZŰRÉS ÉS RENDEZÉS (?kategoria=...)
                 ========================================================== -->
            <?php
                $catHeading = ($activeCategory === 'osszes')
                    ? 'ÖSSZES TÁRGY'
                    : strtoupper(get_shop_category_label($activeCategory));
            ?>
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3">
                <h2 class="section-title mb-0"><?= htmlspecialchars($catHeading) ?></h2>
                <a href="webshop.php" class="btn btn-outline-custom btn-sm">← Vissza a webshop áttekintéshez</a>
            </div>

            <div class="shop-toolbar">
                <div class="small text-muted-custom">
                    Szűrés: <strong class="text-accent"><?= htmlspecialchars($catHeading) ?></strong>
                    • <strong><?= count($filteredProducts) ?> tárgy</strong>
                </div>

                <form method="get" action="webshop.php" class="d-flex align-items-center gap-2">
                    <input type="hidden" name="kategoria" value="<?= htmlspecialchars($activeCategory) ?>">
                    <label for="sortSelect" class="small text-muted-custom mb-0">Rendezési szempont:</label>
                    <select name="rendezes" id="sortSelect" class="shop-sort-select" onchange="this.form.submit()">
                        <option value="kiemelt" <?= $sortBy === 'kiemelt' ? 'selected' : '' ?>>Kiemelt tárgyak</option>
                        <option value="ar_novekvo" <?= $sortBy === 'ar_novekvo' ? 'selected' : '' ?>>Ár szerint növekvő</option>
                        <option value="ar_csokkeno" <?= $sortBy === 'ar_csokkeno' ? 'selected' : '' ?>>Ár szerint csökkenő</option>
                        <option value="nev_az" <?= $sortBy === 'nev_az' ? 'selected' : '' ?>>Név szerint (A–Z)</option>
                    </select>
                </form>
            </div>

            <div class="row g-4">
                <?php foreach ($filteredProducts as $prod): ?>
                    <?php $pImg = get_product_default_image($prod); ?>
                    <div class="col-sm-6 col-lg-3">
                        <div class="shop-product-card">
                            <a href="webshop.php?termek=<?= (int)$prod['id'] ?>" class="shop-product-thumb">
                                <img src="<?= htmlspecialchars($pImg) ?>" alt="<?= htmlspecialchars($prod['name']) ?>">
                                <span class="shop-product-badge"><?= htmlspecialchars(get_shop_category_label($prod['category'] ?? '')) ?></span>
                            </a>
                            <div class="shop-product-body">
                                <h3 class="shop-product-title">
                                    <a href="webshop.php?termek=<?= (int)$prod['id'] ?>"><?= htmlspecialchars($prod['name']) ?></a>
                                </h3>
                                <div class="shop-product-price"><?= format_huf((int)$prod['price']) ?></div>
                                <div class="mt-auto d-grid gap-2">
                                    <a href="webshop.php?termek=<?= (int)$prod['id'] ?>" class="btn btn-outline-custom btn-sm">
                                        Tárgy megnyitása
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php else: ?>
            <!-- ==========================================================
                 3. NÉZET: WEBSHOP FŐOLDAL (KIEMELT TÁRGYAK + KATEGÓRIÁK + LISTA)
                 ========================================================== -->

            <!-- KIEMELT TÁRGYAK (2 OSZLOPOS NAGY KÁRTYÁK) -->
            <?php if (!empty($featuredTop)): ?>
                <section class="mb-5">
                    <h2 class="section-title mb-4">LEGÚJABB TÁRGYAK</h2>
                    <div class="row g-4">
                        <?php foreach ($featuredTop as $feat): ?>
                            <?php $fImg = get_product_default_image($feat); ?>
                            <div class="col-md-6">
                                <div class="shop-product-card">
                                    <a href="webshop.php?termek=<?= (int)$feat['id'] ?>" class="shop-product-thumb">
                                        <img src="<?= htmlspecialchars($fImg) ?>" alt="<?= htmlspecialchars($feat['name']) ?>">
                                        <span class="shop-product-badge">Kiemelt</span>
                                    </a>
                                    <div class="shop-product-body">
                                        <h3 class="shop-product-title fs-5">
                                            <a href="webshop.php?termek=<?= (int)$feat['id'] ?>"><?= htmlspecialchars($feat['name']) ?></a>
                                        </h3>
                                        <div class="shop-product-price"><?= format_huf((int)$feat['price']) ?></div>
                                        <div class="mt-auto">
                                            <a href="webshop.php?termek=<?= (int)$feat['id'] ?>" class="btn btn-outline-custom btn-sm">
                                                Megnyitás &amp; Méretválasztás →
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <!-- KATEGÓRIA VÁLASZTÓ SÁV (4 KÁRTYA + ÖSSZES MEGTEKINTÉSE GOMB) -->
            <section class="mb-5 py-4 px-3 px-md-4 rounded-4 section-bg border border-secondary">
                <h2 class="section-title h4 mb-4">KATEGÓRIÁK</h2>
                <div class="row g-4">
                    <?php foreach ($categoryCards as $catKey => $cCard): ?>
                        <div class="col-6 col-lg-3">
                            <a href="webshop.php?kategoria=<?= htmlspecialchars($catKey) ?>" class="shop-category-card">
                                <div class="shop-category-card-img">
                                    <img src="<?= htmlspecialchars($cCard['image']) ?>" alt="<?= htmlspecialchars($cCard['label']) ?>">
                                </div>
                                <div class="shop-category-card-body">
                                    <span><?= htmlspecialchars($cCard['label']) ?></span>
                                    <span>→</span>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-4">
                    <a href="webshop.php?kategoria=osszes" class="btn btn-custom px-4 py-2">
                        Az összes megtekintése
                    </a>
                </div>
            </section>

            <!-- ÖSSZES ELÉRHETŐ TÁRGY -->
            <section>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
                    <h2 class="section-title mb-0">ÖSSZES TÁRGY</h2>
                    <span class="text-muted-custom small">Kattints bármelyik tárgyra a megnyitáshoz</span>
                </div>
                <div class="row g-4">
                    <?php foreach ($allProducts as $prod): ?>
                        <?php $pImg = get_product_default_image($prod); ?>
                        <div class="col-sm-6 col-lg-4">
                            <div class="shop-product-card">
                                <a href="webshop.php?termek=<?= (int)$prod['id'] ?>" class="shop-product-thumb">
                                    <img src="<?= htmlspecialchars($pImg) ?>" alt="<?= htmlspecialchars($prod['name']) ?>">
                                    <span class="shop-product-badge"><?= htmlspecialchars(get_shop_category_label($prod['category'] ?? '')) ?></span>
                                </a>
                                <div class="shop-product-body">
                                    <h3 class="shop-product-title">
                                        <a href="webshop.php?termek=<?= (int)$prod['id'] ?>"><?= htmlspecialchars($prod['name']) ?></a>
                                    </h3>
                                    <div class="shop-product-price"><?= format_huf((int)$prod['price']) ?></div>
                                    <div class="mt-auto">
                                        <a href="webshop.php?termek=<?= (int)$prod['id'] ?>" class="btn btn-outline-custom btn-sm w-100">
                                            Tárgy megnyitása →
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

    </div>
</main>

<script>
function switchProductImage(btn, imgSrc) {
    var mainImg = document.getElementById('detailMainImg');
    if (mainImg) {
        mainImg.src = imgSrc;
    }
    var buttons = document.querySelectorAll('.product-thumb-btn');
    buttons.forEach(function(b) { b.classList.remove('active'); });
    if (btn) {
        btn.classList.add('active');
    }
}

function selectProductSize(btn, sizeVal) {
    var input = document.getElementById('selectedSizeInput');
    if (input) {
        input.value = sizeVal;
    }
    var buttons = document.querySelectorAll('.size-pill-btn');
    buttons.forEach(function(b) { b.classList.remove('active'); });
    if (btn) {
        btn.classList.add('active');
    }
}

function stepProductQty(delta) {
    var input = document.getElementById('detailQtyInput');
    if (!input) return;
    var current = parseInt(input.value, 10) || 1;
    var maxVal = parseInt(input.getAttribute('max'), 10) || 20;
    var next = Math.max(1, Math.min(maxVal, current + delta));
    input.value = next;
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
