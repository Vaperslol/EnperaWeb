<?php
require_once __DIR__ . '/config.php';

/**
 * Különálló SQLite adatbázis kapcsolat a Webshophoz és a teljes adatbázis-kezelőhöz
 * Fájl: database/webshop.sqlite
 */
function get_shop_db_connection(): PDO {
    static $shopPdo = null;
    if ($shopPdo !== null) {
        return $shopPdo;
    }

    $dbFile = ROOT_DIR . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'webshop.sqlite';
    $dbDir = dirname($dbFile);
    if (!is_dir($dbDir)) {
        @mkdir($dbDir, 0777, true);
    }

    $shopPdo = new PDO('sqlite:' . $dbFile);
    $shopPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $shopPdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $shopPdo->exec("PRAGMA journal_mode = WAL;");
    $shopPdo->exec("PRAGMA foreign_keys = ON;");

    $shopPdo->exec("
        CREATE TABLE IF NOT EXISTS shop_products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            category TEXT NOT NULL DEFAULT 'polok',
            price INTEGER NOT NULL DEFAULT 0,
            stock INTEGER NOT NULL DEFAULT 10,
            sizes TEXT DEFAULT '',
            description TEXT DEFAULT '',
            image TEXT DEFAULT '',
            featured INTEGER DEFAULT 0,
            created_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS shop_orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number TEXT NOT NULL,
            customer_name TEXT NOT NULL,
            customer_email TEXT NOT NULL,
            customer_phone TEXT NOT NULL,
            shipping_method TEXT NOT NULL,
            shipping_point TEXT DEFAULT '',
            shipping_zip TEXT DEFAULT '',
            shipping_city TEXT DEFAULT '',
            shipping_address TEXT DEFAULT '',
            payment_method TEXT NOT NULL,
            payment_status TEXT DEFAULT 'pending',
            order_status TEXT DEFAULT 'new',
            items_json TEXT NOT NULL DEFAULT '[]',
            subtotal INTEGER NOT NULL DEFAULT 0,
            shipping_cost INTEGER NOT NULL DEFAULT 0,
            total_price INTEGER NOT NULL DEFAULT 0,
            note TEXT DEFAULT '',
            created_at TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS shop_settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
    ");

    seed_shop_products_if_needed($shopPdo);

    return $shopPdo;
}

function seed_shop_products_if_needed(PDO $pdo): void {
    $stmt = $pdo->prepare("SELECT value FROM shop_settings WHERE key = 'shop_seeded_v4' LIMIT 1");
    $stmt->execute();
    if ($stmt->fetchColumn() !== false) {
        return;
    }

    $orderCount = (int)$pdo->query("SELECT COUNT(*) FROM shop_orders")->fetchColumn();
    if ($orderCount === 0) {
        $pdo->exec("DELETE FROM shop_products");
        insert_default_shop_products($pdo);
    }

    $ins = $pdo->prepare("INSERT INTO shop_settings (key, value) VALUES ('shop_seeded_v4', '1') ON CONFLICT(key) DO UPDATE SET value = '1'");
    $ins->execute();
}

function insert_default_shop_products(PDO $pdo): void {
    $now = date('Y-m-d H:i:s');
    $placeholder = 'kepek/helyorzo/webshop-default.svg';
    $defaults = [
        [
            'name' => 'N/A – Logós Zenekari Póló',
            'category' => 'polok',
            'price' => 6990,
            'stock' => 25,
            'sizes' => 'S,M,L,XL,2XL',
            'description' => "Hivatalos fekete N/A zenekari póló.\n\n100% vastag pamut, elöl N/A emblémával. Unisex szabás.",
            'image' => $placeholder,
            'featured' => 1
        ],
        [
            'name' => 'N/A – „Enpera” Turné Póló',
            'category' => 'polok',
            'price' => 7490,
            'stock' => 20,
            'sizes' => 'S,M,L,XL,2XL',
            'description' => "Fekete zenekari póló elöl N/A mellkasi logóval, hátul az Enpera 2026-os koncertállomásaival.\n\nUnisex szabás, tartós szitanyomással.",
            'image' => $placeholder,
            'featured' => 1
        ],
        [
            'name' => 'N/A – Kapucnis Pulóver (Hoodie)',
            'category' => 'puloverek',
            'price' => 13990,
            'stock' => 15,
            'sizes' => 'S,M,L,XL,2XL',
            'description' => "Nehézsúlyú fekete kapucnis pulóver (hoodie) kenguru zsebbel és fehér N/A logóval.\n\nUnisex szabás, belül bolyhosított puha pamut anyag.",
            'image' => $placeholder,
            'featured' => 1
        ],
        [
            'name' => 'N/A – Cipzáras Zenekari Pulóver',
            'category' => 'puloverek',
            'price' => 14490,
            'stock' => 14,
            'sizes' => 'S,M,L,XL,2XL',
            'description' => "Fekete, végig cipzáras kapucnis pulóver mellkasi N/A logóval és két oldalzsebbel.\n\nUnisex szabás.",
            'image' => $placeholder,
            'featured' => 0
        ],
        [
            'name' => 'N/A – „Enpera” Bakelit Lemez (LP)',
            'category' => 'lemez',
            'price' => 9990,
            'stock' => 15,
            'sizes' => '',
            'description' => "Az N/A első nagylemeze 180 grammos fekete bakelit (LP) kiadásban, kihajtható borítóval és dalszövegkönyvvel.",
            'image' => $placeholder,
            'featured' => 1
        ],
        [
            'name' => 'N/A – „Enpera” Stúdióalbum (CD)',
            'category' => 'lemez',
            'price' => 3990,
            'stock' => 40,
            'sizes' => '',
            'description' => "Az N/A első stúdióalbuma 8 dallal és 12 oldalas dalszövegkönyvvel (booklet) digipak tokban.",
            'image' => $placeholder,
            'featured' => 1
        ],
        [
            'name' => 'N/A – Hímzett Baseball Sapka',
            'category' => 'kiegeszito',
            'price' => 4990,
            'stock' => 25,
            'sizes' => 'Egyméretes',
            'description' => "Fekete zenekari baseball sapka elöl vastag fehér N/A hímzéssel, hátul állítható pánttal.",
            'image' => $placeholder,
            'featured' => 1
        ],
        [
            'name' => 'N/A – Koncert Póló (Minimal Edition)',
            'category' => 'polok',
            'price' => 6490,
            'stock' => 22,
            'sizes' => 'S,M,L,XL,2XL',
            'description' => "Letisztult fekete-fehér N/A koncertpóló 100% pamut anyagból.",
            'image' => $placeholder,
            'featured' => 0
        ]
    ];

    $stmt = $pdo->prepare("INSERT INTO shop_products (name, category, price, stock, sizes, description, image, featured, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    foreach ($defaults as $item) {
        $stmt->execute([
            $item['name'],
            $item['category'],
            $item['price'],
            $item['stock'],
            $item['sizes'],
            $item['description'],
            $item['image'],
            $item['featured'],
            $now
        ]);
    }
}

/* ==========================================================================
   KATEGÓRIÁK, SZÁLLÍTÁSI ÉS FIZETÉSI MÓDOK
   ========================================================================== */

function get_shop_categories(): array {
    return [
        'puloverek' => 'Pulóverek',
        'polok' => 'Pólók',
        'kiegeszito' => 'Kiegészítők',
        'lemez' => 'CD/DVD & Vinyl'
    ];
}

function get_shop_category_label(string $cat): string {
    $cats = get_shop_categories();
    if ($cat === 'ruhazat') return 'Pólók & Pulóverek';
    return $cats[$cat] ?? 'Merch';
}

function get_product_default_image(array $product): string {
    $img = trim($product['image'] ?? '');
    if ($img !== '' && file_exists(ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $img))) {
        return $img;
    }
    return 'kepek/helyorzo/webshop-default.svg';
}

function get_shipping_methods(): array {
    return [
        'foxpost_automata' => [
            'label' => 'Foxpost Csomagautomata',
            'provider' => 'Foxpost',
            'type' => 'pickup',
            'price' => 1190,
            'eta' => '1–2 munkanap',
            'description' => 'Átvétel bármelyik Foxpost csomagautomatából 0–24 órában, nyitókóddal.'
        ],
        'gls_pont' => [
            'label' => 'GLS CsomagPont / GLS Automata',
            'provider' => 'GLS',
            'type' => 'pickup',
            'price' => 1390,
            'eta' => '1–2 munkanap',
            'description' => 'Kényelmes csomagátvétel országosan több mint 1500 GLS CsomagPonton és automatában.'
        ],
        'mpl_pont' => [
            'label' => 'Magyar Posta (MPL) Csomagautomata / PostaPont / Postán maradó',
            'provider' => 'Magyar Posta (MPL)',
            'type' => 'pickup',
            'price' => 1290,
            'eta' => '1–3 munkanap',
            'description' => 'Átvétel MPL csomagautomatában, MOL/Coop PostaPonton vagy bármelyik postahivatalban.'
        ],
        'foxpost_hazhoz' => [
            'label' => 'Házhozszállítás Foxposttal',
            'provider' => 'Foxpost Házhozszállítás',
            'type' => 'home',
            'price' => 1890,
            'eta' => '1–3 munkanap',
            'description' => 'Kézbesítés közvetlenül a megadott lakcímre vagy munkahelyre Foxpost futárral.'
        ],
        'mpl_hazhoz' => [
            'label' => 'Házhozszállítás Magyar Postával (MPL)',
            'provider' => 'Magyar Posta (MPL) Házhozszállítás',
            'type' => 'home',
            'price' => 1990,
            'eta' => '1–3 munkanap',
            'description' => 'Országos házhozszállítás a Magyar Posta (MPL) futárszolgálatával, SMS/e-mail értesítéssel.'
        ],
        'gls_hazhoz' => [
            'label' => 'Házhozszállítás GLS futárszolgálattal',
            'provider' => 'GLS Házhozszállítás',
            'type' => 'home',
            'price' => 2190,
            'eta' => '1 munkanap',
            'description' => 'Gyors, 1 órás időablakos házhozszállítás GLS futárral, telefonos értesítéssel.'
        ]
    ];
}

function get_payment_methods(): array {
    return [
        'bankkartya' => [
            'label' => 'Online bankkártyás fizetés / Google Pay (Azonnali fizetés)',
            'fee' => 0,
            'description' => 'Biztonságos, azonnali fizetés a rendelés leadásakor (Visa, Mastercard, Google Pay, Apple Pay).'
        ],
        'utalas' => [
            'label' => 'Banki előre utalás',
            'fee' => 0,
            'description' => 'A rendelés visszaigazolása után az összeget a zenekari bankszámlára tudod elutalni.'
        ],
        'utanvet' => [
            'label' => 'Utánvét (Fizetés átvételkor futárnál / automatánál)',
            'fee' => 490,
            'description' => 'Fizetés a csomag átvételekor készpénzzel vagy bankkártyával (+490 Ft utánvét kezelési díj).'
        ]
    ];
}

function get_pickup_points_by_provider(): array {
    return [
        'foxpost_automata' => [
            'Budapest XX. (Pesterzsébet) – Interspar Foxpost Automata (Széchenyi u. 1.)',
            'Budapest IX. – Lurdy Ház Foxpost Automata (Könyves Kálmán krt. 12-14.)',
            'Budapest XI. – Allee Bevásárlóközpont Foxpost Automata (Október 23. u. 8-10.)',
            'Budapest VI. – WestEnd Foxpost Automata (Váci út 1-3.)',
            'Budapest XIV. – Árkád Örs vezér tere Foxpost Automata',
            'Debrecen – Fórum Foxpost Automata (Csapó u. 30.)',
            'Szeged – Árkád Foxpost Automata (Londoni krt. 3.)',
            'Pécs – Árkád Foxpost Automata (Bajcsy-Zsilinszky u. 11.)',
            'Győr – Árkád Foxpost Automata (Budai út 1.)',
            'Miskolc – Szinvapark Foxpost Automata (Bajcsy-Zsilinszky út 2-4.)'
        ],
        'gls_pont' => [
            'Budapest XX. (Pesterzsébet) – GLS Automata Kossuth Lajos utca 42.',
            'Budapest IV. (Újpest) – GLS CsomagPont és Automata (Görgey Artúr u. / István út)',
            'Budapest IX. – Corvin Plaza GLS Automata (Futó u. 37.)',
            'Budapest XI. – Etele Plaza GLS Automata (Hadak útja 1.)',
            'Budapest XIII. – Duna Plaza GLS CsomagPont (Váci út 178.)',
            'Debrecen – Tesco GLS Automata (Kishegyesi út 1-11.)',
            'Szeged – Napfény Park GLS Automata (Szabadkai út 7.)',
            'Pécs – Tesco GLS Automata (Budai Vám 1.)',
            'Győr – Interspar GLS Automata (Fehérvári út 3.)',
            'Székesfehérvár – Alba Plaza GLS CsomagPont (Palotai út 1.)'
        ],
        'mpl_pont' => [
            'Budapest XX. – Pesterzsébet 1. sz. Posta / MPL Csomagautomata (Kossuth L. u. 48.)',
            'Budapest IV. – Újpest 1. sz. Posta / MPL Pont (István út 18.)',
            'Budapest VIII. – Keleti pályaudvar MPL Csomagautomata (Baross tér)',
            'Budapest VI. – Nyugati pályaudvar MPL Csomagautomata (Teréz krt. 51.)',
            'Budapest XI. – Kelenföld vasútállomás MPL Csomagautomata',
            'Debrecen 1. sz. Posta / MPL Csomagautomata (Bethlen u. 1.)',
            'Szeged 1. sz. Posta / MPL Pont (Széchenyi tér 15.)',
            'Pécs 1. sz. Posta / MPL Csomagautomata (Jókai Mór u. 10.)',
            'Győr 1. sz. Posta / MPL Pont (Bajcsy-Zsilinszky út 46.)',
            'Miskolc 1. sz. Posta / MPL Csomagautomata (Kazinczy u. 16.)'
        ]
    ];
}

function get_order_status_labels(): array {
    return [
        'new' => 'Új berendelés',
        'processing' => 'Feldolgozás alatt',
        'packed' => 'Csomagolva',
        'shipped' => 'Feladva / Úton',
        'completed' => 'Teljesítve / Átvéve',
        'cancelled' => 'Lemondva / Törölve'
    ];
}

function get_payment_status_labels(): array {
    return [
        'pending' => 'Fizetésre vár / Utánvétes',
        'paid' => 'Kifizetve',
        'refunded' => 'Visszatérítve'
    ];
}

function format_huf(int $amount): string {
    return number_format($amount, 0, ',', '.') . ' Ft';
}

/* ==========================================================================
   WEBSHOP TERMÉKEK (TÁRGYAK) CRUD
   ========================================================================== */

function shop_get_all_products(?string $category = null): array {
    $pdo = get_shop_db_connection();
    if ($category !== null && $category !== '' && $category !== 'osszes') {
        $stmt = $pdo->prepare("SELECT * FROM shop_products WHERE category = ? ORDER BY featured DESC, id ASC");
        $stmt->execute([$category]);
        return $stmt->fetchAll();
    }
    return $pdo->query("SELECT * FROM shop_products ORDER BY featured DESC, id ASC")->fetchAll();
}

function shop_get_product_by_id(int $id): ?array {
    $pdo = get_shop_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM shop_products WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function shop_create_product(array $data): int {
    $pdo = get_shop_db_connection();
    $stmt = $pdo->prepare("INSERT INTO shop_products (name, category, price, stock, sizes, description, image, featured, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        trim($data['name'] ?? ''),
        $data['category'] ?? 'polok',
        max(0, (int)($data['price'] ?? 0)),
        max(0, (int)($data['stock'] ?? 0)),
        trim($data['sizes'] ?? ''),
        trim($data['description'] ?? ''),
        trim($data['image'] ?? ''),
        !empty($data['featured']) ? 1 : 0,
        date('Y-m-d H:i:s')
    ]);
    return (int)$pdo->lastInsertId();
}

function shop_update_product(int $id, array $data): bool {
    $pdo = get_shop_db_connection();
    $stmt = $pdo->prepare("UPDATE shop_products SET name = ?, category = ?, price = ?, stock = ?, sizes = ?, description = ?, image = ?, featured = ? WHERE id = ?");
    return $stmt->execute([
        trim($data['name'] ?? ''),
        $data['category'] ?? 'polok',
        max(0, (int)($data['price'] ?? 0)),
        max(0, (int)($data['stock'] ?? 0)),
        trim($data['sizes'] ?? ''),
        trim($data['description'] ?? ''),
        trim($data['image'] ?? ''),
        !empty($data['featured']) ? 1 : 0,
        $id
    ]);
}

function shop_delete_product(int $id): bool {
    $pdo = get_shop_db_connection();
    $stmt = $pdo->prepare("DELETE FROM shop_products WHERE id = ?");
    return $stmt->execute([$id]);
}

function shop_reset_products_defaults(): void {
    $pdo = get_shop_db_connection();
    $pdo->exec("DELETE FROM shop_products");
    insert_default_shop_products($pdo);
}

/* ==========================================================================
   KOSÁR KEZELÉS (PHP SESSION)
   ========================================================================== */

function shop_get_cart(): array {
    if (!isset($_SESSION['shop_cart']) || !is_array($_SESSION['shop_cart'])) {
        $_SESSION['shop_cart'] = [];
    }
    return $_SESSION['shop_cart'];
}

function shop_add_to_cart(int $productId, int $qty = 1, string $size = ''): bool {
    $product = shop_get_product_by_id($productId);
    if (!$product || (int)$product['stock'] <= 0) {
        return false;
    }

    $qty = max(1, min(20, $qty));
    $size = trim($size);
    $cartKey = $productId . '__' . ($size !== '' ? $size : 'nosize');

    $cart = shop_get_cart();
    if (isset($cart[$cartKey])) {
        $newQty = min((int)$product['stock'], $cart[$cartKey]['qty'] + $qty);
        $cart[$cartKey]['qty'] = $newQty;
    } else {
        $cart[$cartKey] = [
            'key' => $cartKey,
            'product_id' => (int)$product['id'],
            'name' => $product['name'],
            'category' => $product['category'],
            'price' => (int)$product['price'],
            'size' => $size,
            'qty' => min((int)$product['stock'], $qty),
            'image' => get_product_default_image($product)
        ];
    }

    $_SESSION['shop_cart'] = $cart;
    return true;
}

function shop_update_cart_item(string $cartKey, int $qty): void {
    $cart = shop_get_cart();
    if (!isset($cart[$cartKey])) {
        return;
    }
    if ($qty <= 0) {
        unset($cart[$cartKey]);
    } else {
        $prod = shop_get_product_by_id((int)$cart[$cartKey]['product_id']);
        $maxStock = $prod ? max(1, (int)$prod['stock']) : 20;
        $cart[$cartKey]['qty'] = min($maxStock, $qty);
    }
    $_SESSION['shop_cart'] = $cart;
}

function shop_remove_from_cart(string $cartKey): void {
    $cart = shop_get_cart();
    unset($cart[$cartKey]);
    $_SESSION['shop_cart'] = $cart;
}

function shop_clear_cart(): void {
    $_SESSION['shop_cart'] = [];
}

function shop_get_cart_count(): int {
    $cart = shop_get_cart();
    $count = 0;
    foreach ($cart as $item) {
        $count += (int)($item['qty'] ?? 0);
    }
    return $count;
}

function shop_get_cart_subtotal(): int {
    $cart = shop_get_cart();
    $sum = 0;
    foreach ($cart as $item) {
        $sum += ((int)$item['price']) * ((int)$item['qty']);
    }
    return $sum;
}

/* ==========================================================================
   BERENDELÉSEK (MEGRENDELÉSEK) CRUD
   ========================================================================== */

function shop_create_order(array $orderData): array {
    $pdo = get_shop_db_connection();
    $orderNum = 'NA-' . date('Y') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));

    $items = $orderData['items'] ?? [];
    $stmt = $pdo->prepare("INSERT INTO shop_orders (
        order_number, customer_name, customer_email, customer_phone,
        shipping_method, shipping_point, shipping_zip, shipping_city, shipping_address,
        payment_method, payment_status, order_status,
        items_json, subtotal, shipping_cost, total_price, note, created_at
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $now = date('Y-m-d H:i:s');
    $stmt->execute([
        $orderNum,
        trim($orderData['customer_name'] ?? ''),
        trim($orderData['customer_email'] ?? ''),
        trim($orderData['customer_phone'] ?? ''),
        $orderData['shipping_method'] ?? 'foxpost_automata',
        trim($orderData['shipping_point'] ?? ''),
        trim($orderData['shipping_zip'] ?? ''),
        trim($orderData['shipping_city'] ?? ''),
        trim($orderData['shipping_address'] ?? ''),
        $orderData['payment_method'] ?? 'bankkartya',
        $orderData['payment_status'] ?? 'pending',
        'new',
        json_encode(array_values($items), JSON_UNESCAPED_UNICODE),
        (int)($orderData['subtotal'] ?? 0),
        (int)($orderData['shipping_cost'] ?? 0),
        (int)($orderData['total_price'] ?? 0),
        trim($orderData['note'] ?? ''),
        $now
    ]);

    $orderId = (int)$pdo->lastInsertId();

    $stockStmt = $pdo->prepare("UPDATE shop_products SET stock = MAX(0, stock - ?) WHERE id = ?");
    foreach ($items as $item) {
        if (!empty($item['product_id']) && !empty($item['qty'])) {
            $stockStmt->execute([(int)$item['qty'], (int)$item['product_id']]);
        }
    }

    return [
        'id' => $orderId,
        'order_number' => $orderNum
    ];
}

function shop_get_all_orders(): array {
    $pdo = get_shop_db_connection();
    $rows = $pdo->query("SELECT * FROM shop_orders ORDER BY id DESC")->fetchAll();
    foreach ($rows as &$row) {
        $row['items'] = json_decode($row['items_json'] ?? '[]', true) ?: [];
    }
    return $rows;
}

function shop_get_order_by_id(int $id): ?array {
    $pdo = get_shop_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM shop_orders WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $row['items'] = json_decode($row['items_json'] ?? '[]', true) ?: [];
        return $row;
    }
    return null;
}

function shop_update_order_status(int $id, string $orderStatus, string $paymentStatus): bool {
    $pdo = get_shop_db_connection();
    $validOrderStatuses = array_keys(get_order_status_labels());
    $validPayStatuses = array_keys(get_payment_status_labels());

    if (!in_array($orderStatus, $validOrderStatuses, true)) {
        $orderStatus = 'new';
    }
    if (!in_array($paymentStatus, $validPayStatuses, true)) {
        $paymentStatus = 'pending';
    }

    $stmt = $pdo->prepare("UPDATE shop_orders SET order_status = ?, payment_status = ? WHERE id = ?");
    return $stmt->execute([$orderStatus, $paymentStatus, $id]);
}

function shop_delete_order(int $id): bool {
    $pdo = get_shop_db_connection();
    $stmt = $pdo->prepare("DELETE FROM shop_orders WHERE id = ?");
    return $stmt->execute([$id]);
}

function shop_clear_all_orders(): bool {
    $pdo = get_shop_db_connection();
    return $pdo->exec("DELETE FROM shop_orders") !== false;
}

/* ==========================================================================
   KÖZPONTI ADATBÁZIS-KEZELŐ SEGÉDFÜGGVÉNYEK (WEBOLDAL DB + WEBSHOP DB)
   ========================================================================== */

function get_allowed_admin_tables(): array {
    return [
        'main' => [
            'label' => 'Fő Weboldal Adatbázis (database.sqlite)',
            'file' => ROOT_DIR . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite',
            'tables' => [
                'news' => 'Hírek (news)',
                'concerts' => 'Koncertek (concerts)',
                'discography' => 'Diszkográfia (discography)',
                'gallery' => 'Galéria (gallery)',
                'timeline' => 'Timeline (timeline)',
                'messages' => 'Kapcsolati üzenetek (messages)',
                'settings' => 'Beállítások & Színtéma (settings)'
            ]
        ],
        'shop' => [
            'label' => 'Webshop Külön Adatbázis (webshop.sqlite)',
            'file' => ROOT_DIR . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'webshop.sqlite',
            'tables' => [
                'shop_products' => 'Webshop Tárgyak / Termékek (shop_products)',
                'shop_orders' => 'Webshop Berendelések (shop_orders)',
                'shop_settings' => 'Webshop Beállítások (shop_settings)'
            ]
        ]
    ];
}

function db_admin_get_overview(): array {
    $meta = get_allowed_admin_tables();
    $result = [];

    foreach ($meta as $dbKey => $info) {
        $pdo = ($dbKey === 'shop') ? get_shop_db_connection() : get_db_connection();
        $filePath = $info['file'];
        $fileSize = file_exists($filePath) ? filesize($filePath) : 0;

        $tableStats = [];
        $totalRows = 0;
        foreach ($info['tables'] as $tableName => $tableLabel) {
            try {
                $cnt = (int)$pdo->query("SELECT COUNT(*) FROM \"{$tableName}\"")->fetchColumn();
            } catch (Exception $e) {
                $cnt = 0;
            }
            $tableStats[$tableName] = [
                'name' => $tableName,
                'label' => $tableLabel,
                'count' => $cnt
            ];
            $totalRows += $cnt;
        }

        $result[$dbKey] = [
            'key' => $dbKey,
            'label' => $info['label'],
            'file_name' => basename($filePath),
            'file_size_kb' => round($fileSize / 1024, 1),
            'total_rows' => $totalRows,
            'tables' => $tableStats
        ];
    }

    return $result;
}

function db_admin_get_table_rows(string $dbKey, string $tableName, int $limit = 100): array {
    $meta = get_allowed_admin_tables();
    if (!isset($meta[$dbKey]['tables'][$tableName])) {
        return [];
    }
    $pdo = ($dbKey === 'shop') ? get_shop_db_connection() : get_db_connection();
    $stmt = $pdo->query("SELECT * FROM \"{$tableName}\" ORDER BY ROWID DESC LIMIT " . max(1, min(500, $limit)));
    return $stmt ? $stmt->fetchAll() : [];
}

function db_admin_delete_row(string $dbKey, string $tableName, string $pkCol, string $pkVal): bool {
    $meta = get_allowed_admin_tables();
    if (!isset($meta[$dbKey]['tables'][$tableName])) {
        return false;
    }
    if (!in_array($pkCol, ['id', 'key'], true)) {
        return false;
    }
    $pdo = ($dbKey === 'shop') ? get_shop_db_connection() : get_db_connection();
    $stmt = $pdo->prepare("DELETE FROM \"{$tableName}\" WHERE \"{$pkCol}\" = ?");
    return $stmt->execute([$pkVal]);
}

function db_admin_truncate_table(string $dbKey, string $tableName): bool {
    $meta = get_allowed_admin_tables();
    if (!isset($meta[$dbKey]['tables'][$tableName])) {
        return false;
    }
    $pdo = ($dbKey === 'shop') ? get_shop_db_connection() : get_db_connection();
    return $pdo->exec("DELETE FROM \"{$tableName}\"") !== false;
}

function db_admin_vacuum(string $dbKey): bool {
    $meta = get_allowed_admin_tables();
    if (!isset($meta[$dbKey])) {
        return false;
    }
    $pdo = ($dbKey === 'shop') ? get_shop_db_connection() : get_db_connection();
    return $pdo->exec("VACUUM;") !== false;
}
