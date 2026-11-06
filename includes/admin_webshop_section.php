<?php
if (!function_exists('is_admin_logged_in') || !is_admin_logged_in()) {
    header('Location: index.php');
    exit;
}

$shopProducts = shop_get_all_products();
$shopOrders = shop_get_all_orders();
$shopCategories = get_shop_categories();
$shippingMethods = get_shipping_methods();
$paymentMethods = get_payment_methods();
$orderStatusLabels = get_order_status_labels();
$paymentStatusLabels = get_payment_status_labels();

$editProd = $editShopProduct ?? null;

$newOrdersCount = 0;
$totalRevenue = 0;
foreach ($shopOrders as $ord) {
    if (($ord['order_status'] ?? '') === 'new') {
        $newOrdersCount++;
    }
    if (($ord['order_status'] ?? '') !== 'cancelled') {
        $totalRevenue += (int)($ord['total_price'] ?? 0);
    }
}

$dbOverview = db_admin_get_overview();
$inspectDb = $_GET['inspect_db'] ?? 'shop';
if (!isset($dbOverview[$inspectDb])) {
    $inspectDb = 'shop';
}
$inspectTable = $_GET['inspect_table'] ?? ($inspectDb === 'shop' ? 'shop_products' : 'news');
if (!isset($dbOverview[$inspectDb]['tables'][$inspectTable])) {
    $inspectTable = array_key_first($dbOverview[$inspectDb]['tables']);
}
$inspectedRows = db_admin_get_table_rows($inspectDb, $inspectTable, 100);
?>

<!-- =========================================================================
     WEBSHOP TÁRGYAK FELTÖLTÉSE ÉS BERENDELÉSEK KEZELÉSE OLDAL
     ========================================================================= -->
<section class="admin-section admin-page-panel" id="webshop" data-admin-page="webshop" data-admin-title="Webshop, Tárgyak & Berendelések">
    <div class="container">
        <div class="admin-section-header text-center mb-4">
            <h2 class="text-accent d-flex align-items-center justify-content-center gap-2">
                <svg class="line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 28px; height: 28px;">
                    <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <path d="M16 10a4 4 0 0 1-8 0"></path>
                </svg>
                <span>Webshop Kezelése – Tárgyak & Berendelések</span>
            </h2>
            <p class="text-muted-custom mx-auto" style="max-width: 820px;">
                Itt tudsz új tárgyakat (pólókat, pulóvereket, lemezeket, kiegészítőket) felrakni a különálló Webshop adatbázisba (<code>webshop.sqlite</code>), és itt tudod kezelni a beérkezett berendeléseket (Foxpost, GLS, Magyar Posta MPL).
            </p>
        </div>

        <!-- GYORS STATISZTIKA SÁV -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="admin-card p-3 text-center">
                    <div class="small text-muted-custom text-uppercase fw-bold">Feltöltött tárgyak</div>
                    <div class="h2 fw-bold text-accent mb-0"><?= count($shopProducts) ?> db</div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="admin-card p-3 text-center">
                    <div class="small text-muted-custom text-uppercase fw-bold">Beérkezett berendelések</div>
                    <div class="h2 fw-bold text-accent mb-0">
                        <?= count($shopOrders) ?> db
                        <?php if ($newOrdersCount > 0): ?>
                            <span class="badge bg-light text-dark fs-6 ms-1"><?= $newOrdersCount ?> új</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="admin-card p-3 text-center">
                    <div class="small text-muted-custom text-uppercase fw-bold">Összes rendelési érték</div>
                    <div class="h2 fw-bold text-accent mb-0"><?= format_huf($totalRevenue) ?></div>
                </div>
            </div>
        </div>

        <!-- TÁRGYAK FELTÖLTÉSE ÉS LISTÁJA -->
        <div class="row g-4 mb-5">
            <div class="col-lg-6">
                <div class="admin-card h-100">
                    <h3 class="section-title text-accent mb-4 h4">
                        <?= $editProd ? 'Tárgy szerkesztése: ' . e($editProd['name']) : 'Új tárgy felrakása a Webshopba' ?>
                    </h3>

                    <form method="POST" action="<?= e(ADMIN_ENTRY_FILE) ?>" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="save_shop_product" />
                        <?php if ($editProd): ?>
                            <input type="hidden" name="edit_id" value="<?= (int)$editProd['id'] ?>" />
                            <input type="hidden" name="existing_image" value="<?= e($editProd['image'] ?? '') ?>" />
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label" for="prodName">Tárgy neve *</label>
                            <input type="text" class="form-control" id="prodName" name="prodName" required placeholder="Pl. N/A – Enpera Póló" value="<?= e($editProd['name'] ?? '') ?>" />
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="prodCategory">Kategória *</label>
                                <select class="form-select" id="prodCategory" name="prodCategory">
                                    <?php $curProdCat = $editProd['category'] ?? 'ruhazat'; ?>
                                    <?php foreach ($shopCategories as $cKey => $cLabel): ?>
                                        <option value="<?= e($cKey) ?>" <?= $curProdCat === $cKey ? 'selected' : '' ?>><?= e($cLabel) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <label class="form-label" for="prodPrice">Ár (Ft) *</label>
                                <input type="number" class="form-control" id="prodPrice" name="prodPrice" required min="0" step="10" value="<?= (int)($editProd['price'] ?? 5990) ?>" />
                            </div>
                            <div class="col-md-3 col-6 mb-3">
                                <label class="form-label" for="prodStock">Készlet (db) *</label>
                                <input type="number" class="form-control" id="prodStock" name="prodStock" required min="0" value="<?= (int)($editProd['stock'] ?? 20) ?>" />
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="prodSizes">Választható méretek (vesszővel elválasztva, vagy hagyd üresen)</label>
                            <input type="text" class="form-control" id="prodSizes" name="prodSizes" placeholder="Pl. S,M,L,XL,XXL vagy Egyméretes" value="<?= e($editProd['sizes'] ?? '') ?>" />
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="prodDescription">Tárgy leírása *</label>
                            <textarea class="form-control" id="prodDescription" name="prodDescription" rows="3" required placeholder="Rövid leírás a tárgyról, anyagáról..."><?= e($editProd['description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="prodImage">Termékkép feltöltése (opcionális)</label>
                            <input type="file" class="form-control" id="prodImage" name="prodImage" accept="image/*" />
                            <?php if (!empty($editProd['image'])): ?>
                                <small class="text-muted-custom d-block mt-1">Jelenlegi kép: <code><?= e($editProd['image']) ?></code></small>
                            <?php endif; ?>
                        </div>

                        <div class="form-check mb-4">
                            <input class="form-check-input" type="checkbox" id="prodFeatured" name="prodFeatured" value="1" <?= !empty($editProd['featured']) ? 'checked' : '' ?> />
                            <label class="form-check-label text-white" for="prodFeatured">
                                Kiemelt tárgy (elöl jelenjen meg a webshopban)
                            </label>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-custom">
                                <?= $editProd ? 'Tárgy módosításainak mentése' : 'Tárgy felrakása a Webshopba' ?>
                            </button>
                            <?php if ($editProd): ?>
                                <a href="<?= e(ADMIN_ENTRY_FILE) ?>#webshop" class="btn btn-outline-secondary">Mégse</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="admin-card h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 class="section-title text-accent mb-0 h4">Webshop Tárgyak (<?= count($shopProducts) ?>)</h3>
                            <a href="webshop.php" target="_blank" class="btn btn-sm btn-outline-light">Webshop megnyitása ↗</a>
                        </div>

                        <div style="max-height: 520px; overflow-y: auto;">
                            <?php if (empty($shopProducts)): ?>
                                <p class="text-muted-custom">Még nincs tárgy a webshopban.</p>
                            <?php else: ?>
                                <?php foreach ($shopProducts as $sp): ?>
                                    <div class="p-3 mb-3 border rounded border-secondary" style="background: var(--bg-section);">
                                        <div class="d-flex justify-content-between align-items-start gap-2 mb-1">
                                            <div>
                                                <span class="badge bg-secondary me-1"><?= e(get_shop_category_label($sp['category'])) ?></span>
                                                <span class="badge bg-dark border border-secondary">
                                                    Készlet: <?= (int)$sp['stock'] ?> db
                                                </span>
                                                <h5 class="text-white mt-2 mb-1"><?= e($sp['name']) ?></h5>
                                            </div>
                                            <div class="h5 fw-bold text-accent mb-0"><?= format_huf((int)$sp['price']) ?></div>
                                        </div>
                                        <?php if (!empty($sp['sizes'])): ?>
                                            <small class="text-muted-custom d-block mb-1">Méretek: <?= e($sp['sizes']) ?></small>
                                        <?php endif; ?>
                                        <p class="small text-muted-custom mb-2"><?= e($sp['description']) ?></p>
                                        <div class="d-flex gap-2">
                                            <a href="<?= e(ADMIN_ENTRY_FILE) ?>?edit_product=<?= (int)$sp['id'] ?>#webshop" class="btn btn-sm btn-custom">Szerkesztés</a>
                                            <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=delete_shop_product&id=<?= (int)$sp['id'] ?>" class="btn btn-sm btn-outline-custom" onclick="return confirm('Biztosan törlöd ezt a tárgyat a webshopból?')">Törlés</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-3 pt-3 border-top border-secondary">
                        <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=reset_shop_products" class="btn btn-sm btn-outline-secondary w-100" onclick="return confirm('Visszaállítod az alapértelmezett webshop tárgyakat?')">
                            Alapértelmezett tárgyak visszaállítása
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- BEÉRKEZETT BERENDELÉSEK (MEGRENDELÉSEK) KEZELÉSE -->
        <div class="admin-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4 pb-3 border-bottom border-secondary">
                <div>
                    <h3 class="section-title text-accent mb-1 h4">Beérkezett Berendelések (<?= count($shopOrders) ?> db)</h3>
                    <p class="text-muted-custom small mb-0">
                        Itt látod a látogatók által leadott rendeléseket, a választott Foxpost / GLS / Magyar Posta (MPL) csomagpontot vagy házhozszállítási címet, és módosíthatod a rendelés állapotát.
                    </p>
                </div>
                <?php if (!empty($shopOrders)): ?>
                    <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=clear_shop_orders" class="btn btn-sm btn-outline-custom" onclick="return confirm('Biztosan törlöd az összes berendelést?')">
                        Összes berendelés törlése
                    </a>
                <?php endif; ?>
            </div>

            <?php if (empty($shopOrders)): ?>
                <p class="text-muted-custom text-center py-4 mb-0">Még nem érkezett berendelés a webshopból.</p>
            <?php else: ?>
                <div class="d-flex flex-column gap-3">
                    <?php foreach ($shopOrders as $ord):
                        $shipKey = $ord['shipping_method'] ?? '';
                        $shipLabel = $shippingMethods[$shipKey]['label'] ?? $shipKey;
                        $payKey = $ord['payment_method'] ?? '';
                        $payLabel = $paymentMethods[$payKey]['label'] ?? $payKey;
                        $ordStatus = $ord['order_status'] ?? 'new';
                        $payStatus = $ord['payment_status'] ?? 'pending';
                    ?>
                        <div class="p-4 rounded border <?= $ordStatus === 'new' ? 'border-light' : 'border-secondary' ?>" style="background: var(--bg-section);">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3 pb-2 border-bottom border-secondary">
                                <div>
                                    <span class="badge bg-light text-dark fw-bold me-2"><?= e($ord['order_number']) ?></span>
                                    <strong class="text-white fs-5"><?= e($ord['customer_name']) ?></strong>
                                    <span class="text-muted-custom small ms-2">(<?= e($ord['created_at']) ?>)</span>
                                </div>
                                <div class="h4 fw-bold text-accent mb-0">
                                    <?= format_huf((int)$ord['total_price']) ?>
                                </div>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-4">
                                    <div class="small text-muted-custom">
                                        <strong class="text-white d-block mb-1">Megrendelő elérhetősége:</strong>
                                        E-mail: <a href="mailto:<?= e($ord['customer_email']) ?>" class="text-accent"><?= e($ord['customer_email']) ?></a><br />
                                        Telefon: <strong class="text-white"><?= e($ord['customer_phone']) ?></strong>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="small text-muted-custom">
                                        <strong class="text-white d-block mb-1">Szállítás & Fizetés:</strong>
                                        Mód: <span class="text-accent fw-bold"><?= e($shipLabel) ?></span><br />
                                        <?php if (!empty($ord['shipping_point'])): ?>
                                            Automata / Pont: <strong class="text-white"><?= e($ord['shipping_point']) ?></strong><br />
                                        <?php endif; ?>
                                        <?php if (!empty($ord['shipping_address']) || !empty($ord['shipping_city'])): ?>
                                            Cím: <strong class="text-white"><?= e(trim($ord['shipping_zip'] . ' ' . $ord['shipping_city'] . ', ' . $ord['shipping_address'], ' ,')) ?></strong><br />
                                        <?php endif; ?>
                                        Fizetés: <?= e($payLabel) ?>
                                    </div>
                                </div>

                                <div class="col-md-4">
                                    <div class="small text-muted-custom">
                                        <strong class="text-white d-block mb-1">Megrendelt tárgyak:</strong>
                                        <?php foreach ($ord['items'] as $it): ?>
                                            <div>
                                                • <strong class="text-white"><?= (int)$it['qty'] ?>× <?= e($it['name']) ?></strong>
                                                <?= !empty($it['size']) ? '(' . e($it['size']) . ')' : '' ?>
                                                – <?= format_huf(((int)$it['qty']) * ((int)$it['price'])) ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (!empty($ord['note'])): ?>
                                            <div class="mt-1 text-accent">Megjegyzés: <?= e($ord['note']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Státusz módosító sor -->
                            <form method="POST" action="<?= e(ADMIN_ENTRY_FILE) ?>" class="d-flex flex-wrap align-items-center justify-content-between gap-2 pt-3 border-top border-secondary">
                                <input type="hidden" name="action" value="update_order_status" />
                                <input type="hidden" name="order_id" value="<?= (int)$ord['id'] ?>" />

                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <label class="small text-muted-custom mb-0">Rendelés állapota:</label>
                                    <select name="order_status" class="form-select form-select-sm" style="width: auto; min-height: 34px;">
                                        <?php foreach ($orderStatusLabels as $sKey => $sLbl): ?>
                                            <option value="<?= e($sKey) ?>" <?= $ordStatus === $sKey ? 'selected' : '' ?>><?= e($sLbl) ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <label class="small text-muted-custom mb-0 ms-md-2">Fizetés:</label>
                                    <select name="payment_status" class="form-select form-select-sm" style="width: auto; min-height: 34px;">
                                        <?php foreach ($paymentStatusLabels as $pKey => $pLbl): ?>
                                            <option value="<?= e($pKey) ?>" <?= $payStatus === $pKey ? 'selected' : '' ?>><?= e($pLbl) ?></option>
                                        <?php endforeach; ?>
                                    </select>

                                    <button type="submit" class="btn btn-sm btn-custom">Állapot mentése</button>
                                </div>

                                <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=delete_shop_order&id=<?= (int)$ord['id'] ?>" class="btn btn-sm btn-outline-custom" onclick="return confirm('Biztosan törlöd ezt a berendelést?')">
                                    Berendelés törlése
                                </a>
                            </form>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- =========================================================================
     KÖZPONTI ADATBÁZIS KEZELŐ OLDAL (DATABASE.SQLITE + WEBSHOP.SQLITE)
     ========================================================================= -->
<section class="admin-section-alt admin-page-panel" id="adatbazis" data-admin-page="adatbazis" data-admin-title="Adatbázis Kezelő (Weboldal + Webshop)">
    <div class="container">
        <div class="admin-section-header text-center mb-4">
            <h2 class="text-accent d-flex align-items-center justify-content-center gap-2">
                <svg class="line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 28px; height: 28px;">
                    <ellipse cx="12" cy="5" rx="9" ry="3"></ellipse>
                    <path d="M21 12c0 1.66-4 3-9 3s-9-1.34-9-3"></path>
                    <path d="M3 5v14c0 1.66 4 3 9 3s9-1.34 9-3V5"></path>
                </svg>
                <span>Központi Adatbázis Kezelő</span>
            </h2>
            <p class="text-muted-custom mx-auto" style="max-width: 820px;">
                Itt ugyanott kezelheted a weboldal fő adatbázisát (<code>database/database.sqlite</code>) és a különálló webshop adatbázist (<code>database/webshop.sqlite</code>): táblák megtekintése, rekordok törlése, optimalizálás és JSON mentés.
            </p>
        </div>

        <div class="row g-4 mb-4">
            <?php foreach ($dbOverview as $dbKey => $dbInfo): ?>
                <div class="col-lg-6">
                    <div class="admin-card h-100">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3 pb-3 border-bottom border-secondary">
                            <div>
                                <span class="badge bg-secondary mb-1"><?= e($dbInfo['file_name']) ?> (<?= $dbInfo['file_size_kb'] ?> KB)</span>
                                <h3 class="h4 text-accent fw-bold mb-0"><?= e($dbInfo['label']) ?></h3>
                            </div>
                            <span class="badge bg-light text-dark fw-bold"><?= $dbInfo['total_rows'] ?> rekord</span>
                        </div>

                        <div class="d-flex flex-column gap-2 mb-4">
                            <?php foreach ($dbInfo['tables'] as $tName => $tStat):
                                $isCurrentTable = ($inspectDb === $dbKey && $inspectTable === $tName);
                            ?>
                                <div class="p-2 px-3 rounded border <?= $isCurrentTable ? 'border-light' : 'border-secondary' ?> d-flex justify-content-between align-items-center flex-wrap gap-2" style="background: var(--bg-main);">
                                    <div>
                                        <strong class="text-white"><?= e($tStat['label']) ?></strong>
                                        <span class="badge bg-secondary ms-2"><?= (int)$tStat['count'] ?> sor</span>
                                    </div>
                                    <div class="d-flex gap-2">
                                        <a href="<?= e(ADMIN_ENTRY_FILE) ?>?inspect_db=<?= e($dbKey) ?>&inspect_table=<?= e($tName) ?>#adatbazis" class="btn btn-sm <?= $isCurrentTable ? 'btn-custom' : 'btn-outline-light' ?>">
                                            Megtekintés / Kezelés
                                        </a>
                                        <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=db_truncate_table&db=<?= e($dbKey) ?>&table=<?= e($tName) ?>" class="btn btn-sm btn-outline-custom" onclick="return confirm('Biztosan kiüríted a(z) <?= e($tName) ?> tábla összes sorát?')">
                                            Ürítés
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="d-flex flex-wrap gap-2 pt-2 border-top border-secondary">
                            <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=db_vacuum&db=<?= e($dbKey) ?>" class="btn btn-sm btn-outline-secondary">
                                Optimalizálás (VACUUM)
                            </a>
                            <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=db_export_json&db=<?= e($dbKey) ?>" class="btn btn-sm btn-outline-light">
                                JSON Mentés Letöltése ⬇
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- KIVÁLASZTOTT TÁBLA SORAINAK KÖZVETLEN KEZELÉSE -->
        <div class="admin-card">
            <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3 pb-3 border-bottom border-secondary">
                <div>
                    <span class="badge bg-secondary mb-1"><?= e($dbOverview[$inspectDb]['file_name']) ?></span>
                    <h3 class="h4 text-accent fw-bold mb-0">
                        Tábla adatai: <code><?= e($inspectTable) ?></code> (<?= count($inspectedRows) ?> megjelenített rekord)
                    </h3>
                </div>
            </div>

            <?php if (empty($inspectedRows)): ?>
                <p class="text-muted-custom text-center py-4 mb-0">A kiválasztott tábla (<code><?= e($inspectTable) ?></code>) jelenleg üres.</p>
            <?php else:
                $columns = array_keys($inspectedRows[0]);
                $pkColumn = in_array('id', $columns, true) ? 'id' : (in_array('key', $columns, true) ? 'key' : null);
            ?>
                <div class="table-responsive" style="max-height: 480px; overflow-y: auto;">
                    <table class="table table-dark table-hover table-bordered align-middle small mb-0">
                        <thead>
                            <tr>
                                <?php foreach ($columns as $col): ?>
                                    <th class="text-accent"><?= e($col) ?></th>
                                <?php endforeach; ?>
                                <?php if ($pkColumn): ?>
                                    <th class="text-end text-accent">Művelet</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($inspectedRows as $r): ?>
                                <tr>
                                    <?php foreach ($columns as $col):
                                        $val = (string)($r[$col] ?? '');
                                        if (mb_strlen($val) > 110) {
                                            $val = mb_substr($val, 0, 110) . '...';
                                        }
                                    ?>
                                        <td><?= e($val) ?></td>
                                    <?php endforeach; ?>
                                    <?php if ($pkColumn): ?>
                                        <td class="text-end">
                                            <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=db_delete_row&db=<?= e($inspectDb) ?>&table=<?= e($inspectTable) ?>&pk_col=<?= e($pkColumn) ?>&pk_val=<?= urlencode((string)$r[$pkColumn]) ?>"
                                               class="btn btn-sm btn-outline-custom py-0 px-2"
                                               onclick="return confirm('Biztosan törlöd ezt a rekordot az adatbázisból?')">
                                                Törlés
                                            </a>
                                        </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
