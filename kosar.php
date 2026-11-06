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
$completedOrder = null;

// Kosár és megrendelés műveletek feldolgozása
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['shop_action'])) {
    $action = $_POST['shop_action'];

    if ($action === 'update_cart') {
        $cartKey = $_POST['cart_key'] ?? '';
        $qty = (int)($_POST['qty'] ?? 1);
        shop_update_cart_item($cartKey, $qty);
        $shopNotice = 'A kosár mennyisége frissítve lett.';
    } elseif ($action === 'remove_item') {
        $cartKey = $_POST['cart_key'] ?? '';
        shop_remove_from_cart($cartKey);
        $shopNotice = 'A tétel eltávolítva a kosárból.';
    } elseif ($action === 'clear_cart') {
        shop_clear_cart();
        $shopNotice = 'A kosár kiürítve.';
    } elseif ($action === 'place_order') {
        $cart = shop_get_cart();
        if (empty($cart)) {
            $shopError = 'A kosarad üres! Kérjük, először tegyél legalább egy tárgyat a kosárba.';
        } else {
            $customerName = trim($_POST['customer_name'] ?? '');
            $customerEmail = trim($_POST['customer_email'] ?? '');
            $customerPhone = trim($_POST['customer_phone'] ?? '');
            $shippingMethodKey = $_POST['shipping_method'] ?? 'foxpost_automata';
            $paymentMethodKey = $_POST['payment_method'] ?? 'bankkartya';
            $note = trim($_POST['note'] ?? '');

            $shippingMethods = get_shipping_methods();
            $paymentMethods = get_payment_methods();

            if (!isset($shippingMethods[$shippingMethodKey])) {
                $shippingMethodKey = 'foxpost_automata';
            }
            if (!isset($paymentMethods[$paymentMethodKey])) {
                $paymentMethodKey = 'bankkartya';
            }

            $selectedShipping = $shippingMethods[$shippingMethodKey];
            $selectedPayment = $paymentMethods[$paymentMethodKey];

            $shippingPoint = '';
            $shippingZip = trim($_POST['shipping_zip'] ?? '');
            $shippingCity = trim($_POST['shipping_city'] ?? '');
            $shippingAddress = trim($_POST['shipping_address'] ?? '');

            if ($selectedShipping['type'] === 'pickup') {
                $presetPoint = trim($_POST['pickup_point_select_' . $shippingMethodKey] ?? '');
                $customPoint = trim($_POST['pickup_point_custom'] ?? '');
                $shippingPoint = $customPoint !== '' ? $customPoint : $presetPoint;
            }

            if ($customerName === '' || $customerEmail === '' || $customerPhone === '') {
                $shopError = 'Kérjük, add meg a nevedet, e-mail címedet és telefonszámodat a megrendeléshez!';
            } elseif ($selectedShipping['type'] === 'pickup' && $shippingPoint === '') {
                $shopError = 'Kérjük, válaszd ki vagy írd be a kívánt csomagautomatát / átvételi pontot!';
            } elseif ($selectedShipping['type'] === 'home' && ($shippingZip === '' || $shippingCity === '' || $shippingAddress === '')) {
                $shopError = 'Házhozszállításhoz kérjük, add meg az irányítószámot, települést és a pontos címet!';
            } else {
                $subtotal = shop_get_cart_subtotal();
                $shippingCost = (int)$selectedShipping['price'] + (int)$selectedPayment['fee'];
                $totalPrice = $subtotal + $shippingCost;
                $paymentStatus = ($paymentMethodKey === 'bankkartya') ? 'paid' : 'pending';

                $created = shop_create_order([
                    'customer_name' => $customerName,
                    'customer_email' => $customerEmail,
                    'customer_phone' => $customerPhone,
                    'shipping_method' => $shippingMethodKey,
                    'shipping_point' => $shippingPoint,
                    'shipping_zip' => $shippingZip,
                    'shipping_city' => $shippingCity,
                    'shipping_address' => $shippingAddress,
                    'payment_method' => $paymentMethodKey,
                    'payment_status' => $paymentStatus,
                    'items' => $cart,
                    'subtotal' => $subtotal,
                    'shipping_cost' => $shippingCost,
                    'total_price' => $totalPrice,
                    'note' => $note
                ]);

                shop_clear_cart();
                $completedOrder = shop_get_order_by_id((int)$created['id']);
            }
        }
    }
}

$shippingMethods = get_shipping_methods();
$paymentMethods = get_payment_methods();
$pickupPoints = get_pickup_points_by_provider();
$cart = shop_get_cart();
$cartCount = shop_get_cart_count();
$cartSubtotal = shop_get_cart_subtotal();

$pageTitle = 'Kosár & Megrendelés | N/A Webshop';
$activeNav = 'webshop';
$extraCss = ['webshop.css'];

include __DIR__ . '/includes/header.php';
?>

<!-- HERO FEJLÉC -->
<header class="webshop-hero">
    <div class="container text-center">
        <p class="text-uppercase fw-bold mb-2 text-muted-custom">N/A Zenekar • Webshop</p>
        <h1 class="webshop-title">KOSÁR &amp; MEGRENDELÉS</h1>
        <p class="lead hero-subtitle mx-auto mb-0">
            Ellenőrizd a kiválasztott tárgyakat, majd válaszd ki a szállítási és fizetési módot.
        </p>
    </div>
</header>

<main class="main-bg py-5">
    <div class="container">

        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-4">
            <a href="webshop.php" class="btn btn-outline-custom btn-sm">← Vissza a Webshopba</a>
            <?php if (!empty($cart)): ?>
                <form method="post" action="kosar.php">
                    <input type="hidden" name="shop_action" value="clear_cart">
                    <button type="submit" class="btn btn-outline-custom btn-sm">Kosár teljes ürítése</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($shopNotice !== ''): ?>
            <div class="shop-notice-box mb-4">
                <strong>FRISSÍTVE:</strong> <?= htmlspecialchars($shopNotice) ?>
            </div>
        <?php endif; ?>

        <?php if ($shopError !== ''): ?>
            <div class="shop-notice-box mb-4">
                <strong>FIGYELEM:</strong> <?= htmlspecialchars($shopError) ?>
            </div>
        <?php endif; ?>

        <?php if ($completedOrder): ?>
            <?php
                $ordShip = $shippingMethods[$completedOrder['shipping_method']] ?? ['label' => $completedOrder['shipping_method']];
                $ordPay = $paymentMethods[$completedOrder['payment_method']] ?? ['label' => $completedOrder['payment_method']];
            ?>
            <div class="checkout-panel mb-5">
                <span class="badge bg-light text-dark mb-2 px-3 py-2">SIKERES MEGRENDELÉS</span>
                <h2 class="section-title h3 mb-2">KÖSZÖNJÜK A RENDELÉSEDET!</h2>
                <p class="text-muted-custom mb-3">
                    Rendelésszám: <strong class="text-accent"><?= htmlspecialchars($completedOrder['order_number']) ?></strong>
                </p>
                <div class="row g-3 small mb-4">
                    <div class="col-md-4">
                        <div class="p-3 rounded border border-secondary">
                            <div class="text-muted-custom text-uppercase mb-1">Megrendelő</div>
                            <div class="fw-bold"><?= htmlspecialchars($completedOrder['customer_name']) ?></div>
                            <div><?= htmlspecialchars($completedOrder['customer_email']) ?></div>
                            <div><?= htmlspecialchars($completedOrder['customer_phone']) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded border border-secondary">
                            <div class="text-muted-custom text-uppercase mb-1">Szállítási mód</div>
                            <div class="fw-bold"><?= htmlspecialchars($ordShip['label']) ?></div>
                            <?php if (!empty($completedOrder['shipping_point'])): ?>
                                <div class="text-muted-custom">Csomagpont: <?= htmlspecialchars($completedOrder['shipping_point']) ?></div>
                            <?php else: ?>
                                <div class="text-muted-custom">
                                    <?= htmlspecialchars($completedOrder['shipping_zip'] . ' ' . $completedOrder['shipping_city'] . ', ' . $completedOrder['shipping_address']) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 rounded border border-secondary">
                            <div class="text-muted-custom text-uppercase mb-1">Fizetés &amp; Végösszeg</div>
                            <div class="fw-bold"><?= htmlspecialchars($ordPay['label']) ?></div>
                            <div class="fs-5 fw-bold mt-1"><?= format_huf((int)$completedOrder['total_price']) ?></div>
                        </div>
                    </div>
                </div>
                <a href="webshop.php" class="btn btn-custom">Vissza a Webshopba</a>
            </div>
        <?php endif; ?>

        <div class="checkout-panel">
            <div class="row g-4">
                <!-- BAL OSZLOP: KOSÁR TÉTELEK -->
                <div class="col-lg-5">
                    <h2 class="h5 fw-bold text-uppercase mb-3">1. Kosár tartalma (<?= $cartCount ?> db)</h2>

                    <?php if (empty($cart)): ?>
                        <div class="p-4 rounded border border-secondary text-center text-muted-custom">
                            <p class="mb-3">A kosarad jelenleg üres.</p>
                            <a href="webshop.php" class="btn btn-custom btn-sm">Tárgyak böngészése a Webshopban →</a>
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-column gap-3 mb-3">
                            <?php foreach ($cart as $cKey => $item): ?>
                                <div class="p-3 rounded border border-secondary d-flex align-items-center justify-content-between gap-2">
                                    <div>
                                        <div class="fw-bold">
                                            <?= htmlspecialchars($item['name']) ?>
                                            <?php if (!empty($item['size'])): ?>
                                                <span class="badge bg-light text-dark ms-1"><?= htmlspecialchars($item['size']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="small text-muted-custom">
                                            <?= format_huf((int)$item['price']) ?> × <?= (int)$item['qty'] ?> db =
                                            <strong class="text-accent"><?= format_huf(((int)$item['price']) * ((int)$item['qty'])) ?></strong>
                                        </div>
                                    </div>

                                    <div class="d-flex align-items-center gap-1">
                                        <form method="post" action="kosar.php" class="d-inline">
                                            <input type="hidden" name="shop_action" value="update_cart">
                                            <input type="hidden" name="cart_key" value="<?= htmlspecialchars($cKey) ?>">
                                            <input type="hidden" name="qty" value="<?= ((int)$item['qty']) - 1 ?>">
                                            <button type="submit" class="btn btn-outline-custom btn-sm px-2 py-1">−</button>
                                        </form>
                                        <span class="px-2 fw-bold"><?= (int)$item['qty'] ?></span>
                                        <form method="post" action="kosar.php" class="d-inline">
                                            <input type="hidden" name="shop_action" value="update_cart">
                                            <input type="hidden" name="cart_key" value="<?= htmlspecialchars($cKey) ?>">
                                            <input type="hidden" name="qty" value="<?= ((int)$item['qty']) + 1 ?>">
                                            <button type="submit" class="btn btn-outline-custom btn-sm px-2 py-1">+</button>
                                        </form>
                                        <form method="post" action="kosar.php" class="d-inline ms-1">
                                            <input type="hidden" name="shop_action" value="remove_item">
                                            <input type="hidden" name="cart_key" value="<?= htmlspecialchars($cKey) ?>">
                                            <button type="submit" class="btn btn-outline-custom btn-sm px-2 py-1" title="Törlés">×</button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="p-3 rounded border border-secondary">
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted-custom">Tárgyak részösszege:</span>
                                <strong id="summarySubtotal" data-subtotal="<?= $cartSubtotal ?>"><?= format_huf($cartSubtotal) ?></strong>
                            </div>
                            <div class="d-flex justify-content-between small mb-1">
                                <span class="text-muted-custom">Szállítási díj:</span>
                                <strong id="summaryShipping">1.190 Ft</strong>
                            </div>
                            <div class="d-flex justify-content-between small mb-2">
                                <span class="text-muted-custom">Fizetési díj:</span>
                                <strong id="summaryPaymentFee">0 Ft</strong>
                            </div>
                            <div class="d-flex justify-content-between border-top border-secondary pt-2 fs-5 fw-bold">
                                <span>Fizetendő összesen:</span>
                                <span id="summaryTotal"><?= format_huf($cartSubtotal + 1190) ?></span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- JOBB OSZLOP: SZÁLLÍTÁS ÉS FIZETÉS ŰRLAP -->
                <div class="col-lg-7">
                    <form method="post" action="kosar.php" id="checkoutForm">
                        <input type="hidden" name="shop_action" value="place_order">

                        <h2 class="h5 fw-bold text-uppercase mb-3">2. Megrendelő adatai</h2>
                        <div class="row g-3 mb-4">
                            <div class="col-md-12">
                                <label class="form-label small text-uppercase fw-bold">Teljes név *</label>
                                <input type="text" name="customer_name" class="form-control shop-input" placeholder="Pl. Kovács Bence" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-uppercase fw-bold">E-mail cím *</label>
                                <input type="email" name="customer_email" class="form-control shop-input" placeholder="pelda@email.hu" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small text-uppercase fw-bold">Telefonszám *</label>
                                <input type="text" name="customer_phone" class="form-control shop-input" placeholder="+36 30 123 4567" required>
                            </div>
                        </div>

                        <h2 class="h5 fw-bold text-uppercase mb-3">3. Átvételi / Házhozszállítási mód</h2>
                        <div class="mb-3">
                            <div class="small text-muted-custom text-uppercase fw-bold mb-2">Csomagpontra / Automatába kérhető:</div>
                            <?php foreach ($shippingMethods as $sKey => $sInfo): ?>
                                <?php if ($sInfo['type'] !== 'pickup') continue; ?>
                                <label class="shipping-option-card <?= $sKey === 'foxpost_automata' ? 'active' : '' ?>">
                                    <div class="d-flex align-items-center justify-content-between gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <input
                                                type="radio"
                                                name="shipping_method"
                                                value="<?= htmlspecialchars($sKey) ?>"
                                                data-type="pickup"
                                                data-price="<?= (int)$sInfo['price'] ?>"
                                                <?= $sKey === 'foxpost_automata' ? 'checked' : '' ?>
                                                onchange="updateCheckoutShipping()"
                                            >
                                            <strong><?= htmlspecialchars($sInfo['label']) ?></strong>
                                        </div>
                                        <span class="fw-bold"><?= format_huf((int)$sInfo['price']) ?></span>
                                    </div>
                                    <div class="small text-muted-custom mt-1 ms-4"><?= htmlspecialchars($sInfo['description']) ?></div>
                                </label>
                            <?php endforeach; ?>

                            <div class="small text-muted-custom text-uppercase fw-bold mt-3 mb-2">Házhozszállítás futárszolgálattal (MPL / GLS / Foxpost):</div>
                            <?php foreach ($shippingMethods as $sKey => $sInfo): ?>
                                <?php if ($sInfo['type'] !== 'home') continue; ?>
                                <label class="shipping-option-card">
                                    <div class="d-flex align-items-center justify-content-between gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <input
                                                type="radio"
                                                name="shipping_method"
                                                value="<?= htmlspecialchars($sKey) ?>"
                                                data-type="home"
                                                data-price="<?= (int)$sInfo['price'] ?>"
                                                onchange="updateCheckoutShipping()"
                                            >
                                            <strong><?= htmlspecialchars($sInfo['label']) ?></strong>
                                        </div>
                                        <span class="fw-bold"><?= format_huf((int)$sInfo['price']) ?></span>
                                    </div>
                                    <div class="small text-muted-custom mt-1 ms-4"><?= htmlspecialchars($sInfo['description']) ?></div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <!-- CSOMAGPONT VÁLASZTÓ BLOKK -->
                        <div id="pickupPointSection" class="p-3 rounded border border-secondary mb-4">
                            <label class="form-label small text-uppercase fw-bold">Kiválasztott csomagautomata / CsomagPont *</label>
                            <?php foreach ($pickupPoints as $provKey => $points): ?>
                                <select
                                    name="pickup_point_select_<?= htmlspecialchars($provKey) ?>"
                                    id="pickupSelect_<?= htmlspecialchars($provKey) ?>"
                                    class="form-select shop-input mb-2 pickup-provider-select <?= $provKey === 'foxpost_automata' ? '' : 'd-none' ?>"
                                >
                                    <?php foreach ($points as $pt): ?>
                                        <option value="<?= htmlspecialchars($pt) ?>"><?= htmlspecialchars($pt) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endforeach; ?>
                            <input
                                type="text"
                                name="pickup_point_custom"
                                class="form-control shop-input mt-2"
                                placeholder="Vagy írd be ide bármelyik másik Foxpost / GLS / MPL automata címét..."
                            >
                        </div>

                        <!-- HÁZHOZSZÁLLÍTÁSI CÍM BLOKK -->
                        <div id="homeAddressSection" class="p-3 rounded border border-secondary mb-4 d-none">
                            <label class="form-label small text-uppercase fw-bold mb-2">Házhozszállítási cím (MPL / GLS / Foxpost futárnak) *</label>
                            <div class="row g-2">
                                <div class="col-sm-4">
                                    <input type="text" name="shipping_zip" class="form-control shop-input" placeholder="Irányítószám (pl. 1201)">
                                </div>
                                <div class="col-sm-8">
                                    <input type="text" name="shipping_city" class="form-control shop-input" placeholder="Város / Település">
                                </div>
                                <div class="col-12">
                                    <input type="text" name="shipping_address" class="form-control shop-input" placeholder="Utca, házszám, emelet / ajtó">
                                </div>
                            </div>
                        </div>

                        <h2 class="h5 fw-bold text-uppercase mb-3">4. Fizetési mód</h2>
                        <div class="mb-4">
                            <?php foreach ($paymentMethods as $pKey => $pInfo): ?>
                                <label class="payment-option-card <?= $pKey === 'bankkartya' ? 'active' : '' ?>">
                                    <div class="d-flex align-items-center justify-content-between gap-2">
                                        <div class="d-flex align-items-center gap-2">
                                            <input
                                                type="radio"
                                                name="payment_method"
                                                value="<?= htmlspecialchars($pKey) ?>"
                                                data-fee="<?= (int)$pInfo['fee'] ?>"
                                                <?= $pKey === 'bankkartya' ? 'checked' : '' ?>
                                                onchange="updateCheckoutShipping()"
                                            >
                                            <strong><?= htmlspecialchars($pInfo['label']) ?></strong>
                                        </div>
                                        <span class="fw-bold"><?= (int)$pInfo['fee'] > 0 ? '+' . format_huf((int)$pInfo['fee']) : 'Ingyenes' ?></span>
                                    </div>
                                    <div class="small text-muted-custom mt-1 ms-4"><?= htmlspecialchars($pInfo['description']) ?></div>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small text-uppercase fw-bold">Megjegyzés a rendeléshez (opcionális)</label>
                            <textarea name="note" rows="2" class="form-control shop-input" placeholder="Pl. kapucsengő, futárnak szóló üzenet..."></textarea>
                        </div>

                        <button
                            type="submit"
                            id="fizetesiGomb"
                            class="btn btn-custom btn-lg w-100 py-3 text-uppercase fw-bold"
                            <?= empty($cart) ? 'disabled' : '' ?>
                        >
                            Megrendelés és Fizetés (<?= format_huf($cartSubtotal > 0 ? $cartSubtotal + 1190 : 0) ?>)
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</main>

<script>
function formatHufJs(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".") + " Ft";
}

function updateCheckoutShipping() {
    var shipRadio = document.querySelector('input[name="shipping_method"]:checked');
    var payRadio = document.querySelector('input[name="payment_method"]:checked');

    document.querySelectorAll('.shipping-option-card').forEach(function(card) {
        var r = card.querySelector('input[type="radio"]');
        card.classList.toggle('active', r && r.checked);
    });
    document.querySelectorAll('.payment-option-card').forEach(function(card) {
        var r = card.querySelector('input[type="radio"]');
        card.classList.toggle('active', r && r.checked);
    });

    if (!shipRadio) return;

    var shipType = shipRadio.getAttribute('data-type');
    var shipPrice = parseInt(shipRadio.getAttribute('data-price'), 10) || 0;
    var payFee = payRadio ? (parseInt(payRadio.getAttribute('data-fee'), 10) || 0) : 0;

    var pickupSec = document.getElementById('pickupPointSection');
    var homeSec = document.getElementById('homeAddressSection');

    if (shipType === 'pickup') {
        if (pickupSec) pickupSec.classList.remove('d-none');
        if (homeSec) homeSec.classList.add('d-none');
        document.querySelectorAll('.pickup-provider-select').forEach(function(sel) {
            sel.classList.add('d-none');
        });
        var activeSel = document.getElementById('pickupSelect_' + shipRadio.value);
        if (activeSel) activeSel.classList.remove('d-none');
    } else {
        if (pickupSec) pickupSec.classList.add('d-none');
        if (homeSec) homeSec.classList.remove('d-none');
    }

    var subEl = document.getElementById('summarySubtotal');
    var subtotal = subEl ? (parseInt(subEl.getAttribute('data-subtotal'), 10) || 0) : 0;
    var total = subtotal > 0 ? (subtotal + shipPrice + payFee) : 0;

    var shipEl = document.getElementById('summaryShipping');
    var feeEl = document.getElementById('summaryPaymentFee');
    var totEl = document.getElementById('summaryTotal');
    var payBtn = document.getElementById('fizetesiGomb');

    if (shipEl) shipEl.textContent = formatHufJs(shipPrice);
    if (feeEl) feeEl.textContent = formatHufJs(payFee);
    if (totEl) totEl.textContent = formatHufJs(total);
    if (payBtn) payBtn.textContent = 'Megrendelés és Fizetés (' + formatHufJs(total) + ')';
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
