<?php
$pageTitle = 'Vezérlőpult - N/A';
$pageDescription = 'N/A admin felület tartalomkezeléshez.';
$activeNav = 'admin';
$pathPrefix = '';
$extraCss = ['admin.css'];

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

// Fájlfeltöltő segédfüggvény (szigorú munkamenet-ellenőrzéssel)
function handle_file_upload(string $inputName): string {
    require_admin('index.php');

    if (empty($_FILES[$inputName]['name']) || $_FILES[$inputName]['error'] !== UPLOAD_ERR_OK) {
        return '';
    }

    $tmpName = $_FILES[$inputName]['tmp_name'];
    $originalName = $_FILES[$inputName]['name'];
    $cleanName = preg_replace('/[^a-zA-Z0-9.\-_]/', '-', strtolower($originalName));
    $uniqueName = time() . '-' . mt_rand(100000, 999999) . '-' . $cleanName;
    $targetPath = UPLOADS_DIR . DIRECTORY_SEPARATOR . $uniqueName;

    if (move_uploaded_file($tmpName, $targetPath)) {
        return 'uploads/' . $uniqueName;
    }
    return '';
}

function redirect_admin(string $url): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    header("Location: $url");
    exit;
}

// -------------------------------------------------------------
// POST KÉRÉSEK KEZELÉSE
// -------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. BEJELENTKEZÉS (Brute-Force védelemmel)
    if ($action === 'login') {
        if (is_admin_locked_out()) {
            $mins = get_admin_lockout_remaining_minutes();
            set_flash('error', "Túl sok sikertelen próbálkozás! Az újabb bejelentkezés {$mins} percig le van tiltva.");
            redirect_admin(ADMIN_ENTRY_FILE);
        }

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if (admin_login($username, $password)) {
            set_flash('success', 'Sikeresen bejelentkeztél mint ' . htmlspecialchars($username));
            redirect_admin(ADMIN_ENTRY_FILE);
        } else {
            if (is_admin_locked_out()) {
                set_flash('error', '5 sikertelen jelszópróbálkozás! Az újabb próbálkozás 15 percre letiltva.');
            } else {
                $remaining = max(0, ADMIN_MAX_LOGIN_ATTEMPTS - (int)($_SESSION['admin_login_attempts'] ?? 0));
                set_flash('error', "Hibás felhasználónév vagy jelszó. (Hátralévő próbálkozások: {$remaining})");
            }
            redirect_admin(ADMIN_ENTRY_FILE);
        }
    }

    // Minden további POST művelet előtt szigorú PHP session ellenőrzés;
    // jogosulatlan hozzáférés esetén azonnali átirányítás az index.php-ra
    require_admin('index.php');

    // 0. SZÍNTÉMA MENTÉSE
    if ($action === 'save_theme') {
        require_admin('index.php');

        $accent = trim($_POST['theme_accent'] ?? '#ffffff');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $accent)) {
            $accent = '#ffffff';
        }
        $bgMain = trim($_POST['theme_bg_main'] ?? '#060607');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $bgMain)) {
            $bgMain = '#060607';
        }
        $bgCard = trim($_POST['theme_bg_card'] ?? '#141417');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $bgCard)) {
            $bgCard = '#141417';
        }
        $textMain = trim($_POST['theme_text_main'] ?? '#f4f4f6');
        if (!preg_match('/^#[0-9a-fA-F]{6}$/', $textMain)) {
            $textMain = '#f4f4f6';
        }
        $grayscale = isset($_POST['theme_grayscale']) ? (int)$_POST['theme_grayscale'] : 100;
        if ($grayscale < 0 || $grayscale > 100) {
            $grayscale = 100;
        }

        db_save_theme_settings([
            'accent' => strtolower($accent),
            'bg_main' => strtolower($bgMain),
            'bg_card' => strtolower($bgCard),
            'text_main' => strtolower($textMain),
            'image_grayscale' => (string)$grayscale
        ]);

        set_flash('success', 'Weboldal színtémája sikeresen mentve és frissítve az egész oldalon!');
        redirect_admin(ADMIN_ENTRY_FILE . '#szinek');
    }

    // 1.5. WEBSHOP TÁRGY MENTÉSE ÉS BERENDELÉS ÁLLAPOT FRISSÍTÉSE
    if ($action === 'save_shop_product') {
        require_admin('index.php');

        $editId = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;
        $uploadedImg = handle_file_upload('prodImage');
        $existingImg = trim($_POST['existing_image'] ?? '');
        $finalImg = $uploadedImg ?: $existingImg;

        $prodData = [
            'name' => trim($_POST['prodName'] ?? ''),
            'category' => $_POST['prodCategory'] ?? 'ruhazat',
            'price' => (int)($_POST['prodPrice'] ?? 0),
            'stock' => (int)($_POST['prodStock'] ?? 0),
            'sizes' => trim($_POST['prodSizes'] ?? ''),
            'description' => trim($_POST['prodDescription'] ?? ''),
            'image' => $finalImg,
            'featured' => !empty($_POST['prodFeatured']) ? 1 : 0
        ];

        if ($editId) {
            shop_update_product($editId, $prodData);
            set_flash('success', 'Webshop tárgy (' . $prodData['name'] . ') sikeresen frissítve.');
        } else {
            shop_create_product($prodData);
            set_flash('success', 'Új tárgy (' . $prodData['name'] . ') sikeresen felrakva a webshopba.');
        }
        redirect_admin(ADMIN_ENTRY_FILE . '#webshop');
    }

    if ($action === 'update_order_status') {
        require_admin('index.php');

        $orderId = (int)($_POST['order_id'] ?? 0);
        $ordStatus = $_POST['order_status'] ?? 'new';
        $payStatus = $_POST['payment_status'] ?? 'pending';

        if ($orderId > 0) {
            shop_update_order_status($orderId, $ordStatus, $payStatus);
            set_flash('success', 'A berendelés állapota sikeresen frissítve!');
        }
        redirect_admin(ADMIN_ENTRY_FILE . '#webshop');
    }

    // 1.6. DISZKOGRÁFIA KIADVÁNYOK MENTÉSE (A diszkográfia oldalszövegek nem szerkeszthetők)
    if ($action === 'save_discography') {
        require_admin('index.php');

        $editId = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;
        $uploadedCover = handle_file_upload('discoCover');
        $existingCover = trim($_POST['existing_cover'] ?? '');
        $finalCover = $uploadedCover ?: $existingCover;

        $tracks = [];
        $trackTitles = $_POST['track_title'] ?? [];
        $trackDurations = $_POST['track_duration'] ?? [];
        $trackLyrics = $_POST['track_lyrics'] ?? [];

        if (is_array($trackTitles)) {
            foreach ($trackTitles as $idx => $tTitle) {
                $tTitle = trim((string)$tTitle);
                if ($tTitle !== '') {
                    $tracks[] = [
                        'title' => $tTitle,
                        'duration' => trim((string)($trackDurations[$idx] ?? '')),
                        'lyrics' => trim((string)($trackLyrics[$idx] ?? ''))
                    ];
                }
            }
        }

        $discoData = [
            'title' => trim($_POST['discoTitle'] ?? ''),
            'category' => $_POST['discoCategory'] ?? 'lemez',
            'release_date' => trim($_POST['discoDate'] ?? date('Y-m-d')),
            'cover_image' => $finalCover,
            'description' => trim($_POST['discoDescription'] ?? ''),
            'tracks' => $tracks,
            'spotify_url' => trim($_POST['discoSpotify'] ?? ''),
            'youtube_url' => trim($_POST['discoYoutube'] ?? ''),
            'apple_url' => trim($_POST['discoApple'] ?? ''),
            'other_url' => trim($_POST['discoOther'] ?? '')
        ];

        if ($editId) {
            db_update_discography($editId, $discoData);
            set_flash('success', 'Kiadvány (' . $discoData['title'] . ') sikeresen frissítve.');
        } else {
            db_create_discography($discoData);
            set_flash('success', 'Új kiadvány (' . $discoData['title'] . ') sikeresen hozzáadva a diszkográfiához.');
        }
        redirect_admin(ADMIN_ENTRY_FILE . '#szovegek');
    }

    // 2. KONCERT MENTÉSE (Hozzáadás vagy Szerkesztés)
    if ($action === 'save_concert') {
        require_admin('index.php');

        $editId = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;
        $concertData = [
            'title' => trim($_POST['name'] ?? ''),
            'date' => trim($_POST['date'] ?? ''),
            'time' => trim($_POST['time'] ?? ''),
            'place' => trim($_POST['place'] ?? ''),
            'address' => trim($_POST['address'] ?? ''),
            'status' => $_POST['status'] ?? 'open',
            'entry' => trim($_POST['entry'] ?? ''),
            'ticketOption' => $_POST['ticketOption'] ?? 'no-ticket',
            'ticketLink' => trim($_POST['ticketLink'] ?? ''),
            'streamOption' => $_POST['streamOption'] ?? 'no-stream',
            'streamLink' => trim($_POST['streamLink'] ?? ''),
            'recordingOption' => $_POST['recordingOption'] ?? 'no-recording',
            'recordingLink' => trim($_POST['recordingLink'] ?? '')
        ];

        if ($editId) {
            db_update_concert($editId, $concertData);
            set_flash('success', 'Koncert sikeresen frissítve.');
        } else {
            db_create_concert($concertData);
            set_flash('success', 'Új koncert sikeresen hozzáadva.');
        }
        redirect_admin(ADMIN_ENTRY_FILE . '#koncertek');
    }

    // 3. HÍR MENTÉSE
    if ($action === 'save_news') {
        require_admin('index.php');

        $editId = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;
        $uploadedImage = handle_file_upload('newsImage');
        $existingImage = $_POST['existing_image'] ?? '';
        $finalImage = $uploadedImage ?: $existingImage;

        $newsData = [
            'title' => trim($_POST['newsTitle'] ?? ''),
            'date' => trim($_POST['newsDate'] ?? ''),
            'type' => $_POST['newsType'] ?? 'Bejelentés',
            'text' => trim($_POST['newsText'] ?? ''),
            'image' => $finalImage
        ];

        if ($editId) {
            db_update_news($editId, $newsData);
            set_flash('success', 'Hír sikeresen frissítve.');
        } else {
            db_create_news($newsData);
            set_flash('success', 'Új hír sikeresen létrehozva.');
        }
        redirect_admin(ADMIN_ENTRY_FILE . '#hirek');
    }

    // 4. GALÉRIA ALBUM LÉTREHOZÁSA
    if ($action === 'save_gallery') {
        require_admin('index.php');

        $title = trim($_POST['galleryTitle'] ?? '');
        $date = trim($_POST['galleryDate'] ?? '');
        $description = trim($_POST['galleryDescription'] ?? '');

        $uploadedImages = [];
        if (!empty($_FILES['galleryImages']['name'][0])) {
            $totalFiles = count($_FILES['galleryImages']['name']);
            for ($i = 0; $i < $totalFiles; $i++) {
                if ($_FILES['galleryImages']['error'][$i] === UPLOAD_ERR_OK) {
                    $tmpName = $_FILES['galleryImages']['tmp_name'][$i];
                    $cleanName = preg_replace('/[^a-zA-Z0-9.\-_]/', '-', strtolower($_FILES['galleryImages']['name'][$i]));
                    $uniqueName = time() . '-' . mt_rand(100000, 999999) . '-' . $cleanName;
                    $targetPath = UPLOADS_DIR . DIRECTORY_SEPARATOR . $uniqueName;
                    if (move_uploaded_file($tmpName, $targetPath)) {
                        $uploadedImages[] = 'uploads/' . $uniqueName;
                    }
                }
            }
        }

        db_create_gallery([
            'title' => $title,
            'date' => $date,
            'description' => $description,
            'images' => $uploadedImages
        ]);

        set_flash('success', 'Galéria album sikeresen létrehozva ' . count($uploadedImages) . ' feltöltött képpel.');
        redirect_admin(ADMIN_ENTRY_FILE . '#galeria');
    }

    // 5. TIMELINE ESEMÉNY MENTÉSE
    if ($action === 'save_timeline') {
        require_admin('index.php');

        $editId = !empty($_POST['edit_id']) ? (int)$_POST['edit_id'] : null;
        $uploadedImage = handle_file_upload('timelineImage');
        $existingImage = $_POST['existing_image'] ?? '';
        $finalImage = $uploadedImage ?: $existingImage;

        $timelineData = [
            'title' => trim($_POST['timelineTitle'] ?? ''),
            'date' => trim($_POST['timelineDate'] ?? ''),
            'type' => $_POST['timelineType'] ?? 'Alakulás',
            'text' => trim($_POST['timelineText'] ?? ''),
            'image' => $finalImage
        ];

        if ($editId) {
            db_update_timeline($editId, $timelineData);
            set_flash('success', 'Timeline esemény sikeresen frissítve.');
        } else {
            db_create_timeline($timelineData);
            set_flash('success', 'Timeline esemény sikeresen hozzáadva.');
        }
        redirect_admin(ADMIN_ENTRY_FILE . '#timeline');
    }
}

// -------------------------------------------------------------
// GET MŰVELETEK KEZELÉSE (Törlés, kijelentkezés, visszaállítás, szerkesztés)
// -------------------------------------------------------------
$action = $_GET['action'] ?? '';

if (!empty($action) || isset($_GET['edit_concert']) || isset($_GET['edit_news']) || isset($_GET['edit_timeline']) || isset($_GET['edit_discography']) || isset($_GET['edit_product']) || isset($_GET['inspect_db'])) {
    // Ha valaki jogosultság nélkül próbálja megnyitni a védett admin funkciókat,
    // irányítsuk át azonnal a főoldalra (index.php)
    require_admin('index.php');
}

if ($action === 'logout') {
    require_admin('index.php');
    admin_logout();
    redirect_admin('index.php');
}

if (!empty($action)) {
    require_admin('index.php');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : null;

    if ($action === 'delete_concert' && $id) {
        db_delete_concert($id);
        set_flash('success', 'Koncert törölve.');
        redirect_admin(ADMIN_ENTRY_FILE . '#koncertek');
    }
    if ($action === 'clear_concerts') {
        db_clear_concerts();
        set_flash('success', 'Összes koncert törölve.');
        redirect_admin(ADMIN_ENTRY_FILE . '#koncertek');
    }
    if ($action === 'reset_concerts') {
        db_reset_concert_defaults();
        set_flash('success', 'Alapértelmezett koncertek visszaállítva.');
        redirect_admin(ADMIN_ENTRY_FILE . '#koncertek');
    }
    if ($action === 'delete_news' && $id) {
        db_delete_news($id);
        set_flash('success', 'Hír törölve.');
        redirect_admin(ADMIN_ENTRY_FILE . '#hirek');
    }
    if ($action === 'reset_news') {
        db_reset_news_defaults();
        set_flash('success', 'Alapértelmezett hírek visszaállítva.');
        redirect_admin(ADMIN_ENTRY_FILE . '#hirek');
    }
    if ($action === 'delete_gallery' && $id) {
        db_delete_gallery($id);
        set_flash('success', 'Album és képei törölve.');
        redirect_admin(ADMIN_ENTRY_FILE . '#galeria');
    }
    if ($action === 'reset_gallery') {
        db_reset_gallery_defaults();
        set_flash('success', 'Galéria kiürítve.');
        redirect_admin(ADMIN_ENTRY_FILE . '#galeria');
    }
    if ($action === 'delete_timeline' && $id) {
        db_delete_timeline($id);
        set_flash('success', 'Timeline esemény törölve.');
        redirect_admin(ADMIN_ENTRY_FILE . '#timeline');
    }
    if ($action === 'reset_timeline') {
        db_reset_timeline_defaults();
        set_flash('success', 'Alap timeline visszaállítva.');
        redirect_admin(ADMIN_ENTRY_FILE . '#timeline');
    }
    if ($action === 'open_message' && $id) {
        db_mark_message_opened($id);
        set_flash('success', 'Üzenet olvasottként megjelölve.');
        redirect_admin(ADMIN_ENTRY_FILE . '#uzenetek');
    }
    if ($action === 'delete_message' && $id) {
        db_delete_message($id);
        set_flash('success', 'Üzenet törölve.');
        redirect_admin(ADMIN_ENTRY_FILE . '#uzenetek');
    }
    if ($action === 'delete_all_messages') {
        db_delete_all_messages();
        set_flash('success', 'Összes üzenet törölve.');
        redirect_admin(ADMIN_ENTRY_FILE . '#uzenetek');
    }
    if ($action === 'reset_theme') {
        db_save_theme_settings([
            'accent' => '#ffffff',
            'bg_main' => '#060607',
            'bg_section' => '#0c0c0e',
            'bg_card' => '#141417',
            'text_main' => '#f4f4f6',
            'text_muted' => '#9c9ca4',
            'image_grayscale' => '100'
        ]);
        set_flash('success', 'A weboldal színei visszaállítva az alapértelmezett fekete-fehérre.');
        redirect_admin(ADMIN_ENTRY_FILE . '#szinek');
    }
    if ($action === 'delete_discography' && $id) {
        require_admin('index.php');
        db_delete_discography($id);
        set_flash('success', 'Kiadvány törölve a diszkográfiából.');
        redirect_admin(ADMIN_ENTRY_FILE . '#szovegek');
    }
    if ($action === 'reset_discography') {
        require_admin('index.php');
        db_reset_discography_defaults();
        set_flash('success', 'Alapértelmezett diszkográfia (Lemez, Kislemez, Single) visszaállítva.');
        redirect_admin(ADMIN_ENTRY_FILE . '#szovegek');
    }
    if ($action === 'delete_shop_product' && $id) {
        require_admin('index.php');
        shop_delete_product($id);
        set_flash('success', 'Tárgy törölve a webshopból.');
        redirect_admin(ADMIN_ENTRY_FILE . '#webshop');
    }
    if ($action === 'reset_shop_products') {
        require_admin('index.php');
        shop_reset_products_defaults();
        set_flash('success', 'Alapértelmezett webshop tárgyak visszaállítva.');
        redirect_admin(ADMIN_ENTRY_FILE . '#webshop');
    }
    if ($action === 'delete_shop_order' && $id) {
        require_admin('index.php');
        shop_delete_order($id);
        set_flash('success', 'Berendelés törölve.');
        redirect_admin(ADMIN_ENTRY_FILE . '#webshop');
    }
    if ($action === 'clear_shop_orders') {
        require_admin('index.php');
        shop_clear_all_orders();
        set_flash('success', 'Összes berendelés törölve.');
        redirect_admin(ADMIN_ENTRY_FILE . '#webshop');
    }
    if ($action === 'db_delete_row') {
        require_admin('index.php');
        $dbKey = $_GET['db'] ?? 'shop';
        $tbl = $_GET['table'] ?? '';
        $pkCol = $_GET['pk_col'] ?? 'id';
        $pkVal = $_GET['pk_val'] ?? '';
        if (db_admin_delete_row($dbKey, $tbl, $pkCol, $pkVal)) {
            set_flash('success', "Rekord törölve a(z) {$tbl} táblából.");
        }
        redirect_admin(ADMIN_ENTRY_FILE . '?inspect_db=' . urlencode($dbKey) . '&inspect_table=' . urlencode($tbl) . '#adatbazis');
    }
    if ($action === 'db_truncate_table') {
        require_admin('index.php');
        $dbKey = $_GET['db'] ?? 'shop';
        $tbl = $_GET['table'] ?? '';
        if (db_admin_truncate_table($dbKey, $tbl)) {
            set_flash('success', "A(z) {$tbl} tábla sikeresen kiürítve.");
        }
        redirect_admin(ADMIN_ENTRY_FILE . '?inspect_db=' . urlencode($dbKey) . '&inspect_table=' . urlencode($tbl) . '#adatbazis');
    }
    if ($action === 'db_vacuum') {
        require_admin('index.php');
        $dbKey = $_GET['db'] ?? 'shop';
        db_admin_vacuum($dbKey);
        set_flash('success', 'Adatbázis sikeresen optimalizálva (VACUUM)!');
        redirect_admin(ADMIN_ENTRY_FILE . '?inspect_db=' . urlencode($dbKey) . '#adatbazis');
    }
    if ($action === 'db_export_json') {
        require_admin('index.php');
        $dbKey = $_GET['db'] ?? 'shop';
        $meta = get_allowed_admin_tables();
        if (isset($meta[$dbKey])) {
            $exportData = [];
            foreach (array_keys($meta[$dbKey]['tables']) as $tName) {
                $exportData[$tName] = db_admin_get_table_rows($dbKey, $tName, 500);
            }
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $dbKey . '-backup-' . date('Y-m-d') . '.json"');
            echo json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            exit;
        }
        redirect_admin(ADMIN_ENTRY_FILE . '#adatbazis');
    }
}

// Szerkesztéshez betöltendő elem meghatározása ha van
$editConcert = null;
if (isset($_GET['edit_concert'])) {
    require_admin('index.php');
    $editConcert = db_get_concert_by_id((int)$_GET['edit_concert']);
}

$editNews = null;
if (isset($_GET['edit_news'])) {
    require_admin('index.php');
    $editNews = db_get_news_by_id((int)$_GET['edit_news']);
}

$editTimeline = null;
if (isset($_GET['edit_timeline'])) {
    require_admin('index.php');
    $editTimeline = db_get_timeline_by_id((int)$_GET['edit_timeline']);
}

$editDiscography = null;
if (isset($_GET['edit_discography'])) {
    require_admin('index.php');
    $editDiscography = db_get_discography_by_id((int)$_GET['edit_discography']);
}

$editShopProduct = null;
if (isset($_GET['edit_product'])) {
    require_admin('index.php');
    $editShopProduct = shop_get_product_by_id((int)$_GET['edit_product']);
}

$flash = get_flash();
$isLoggedIn = is_admin_logged_in();
$isLockedOut = is_admin_locked_out();
$lockoutMinutes = get_admin_lockout_remaining_minutes();

$currentTheme = db_get_theme_settings();

if ($isLoggedIn) {
    $allConcerts = db_get_all_concerts();
    $allNews = db_get_all_news();
    $allTimeline = db_get_all_timeline();
    $allGallery = db_get_all_gallery();
    $allMessages = db_get_all_messages();
}

require_once __DIR__ . '/includes/header.php';
?>

<main class="main-bg">
    <?php if (!$isLoggedIn): ?>
        <!-- ========================================== -->
        <!-- BEJELENTKEZŐ FORM -->
        <!-- ========================================== -->
        <section class="admin-login-section" id="loginSection">
            <div class="container">
                <div class="row justify-content-center">
                    <div class="col-lg-5 col-md-7">
                        <div class="admin-card">
                            <div class="text-center mb-4">
                                <p class="text-accent fw-bold text-uppercase mb-2">
                                    Védett Zenekari Kapu
                                </p>
                                <h1 class="admin-login-title">
                                    Belépés
                                </h1>
                                <p class="text-muted-custom">
                                    Csak belépés után tudod kezelni a híreket, koncerteket, galériát, timeline-t és üzeneteket.
                                </p>
                            </div>

                            <?php if ($flash): ?>
                                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-3 text-center">
                                    <?= e($flash['message']) ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($isLockedOut): ?>
                                <div class="alert alert-danger mb-3 text-center">
                                    A bejelentkezés ideiglenesen le van tiltva túl sok sikertelen próbálkozás miatt. Kérjük, várj még <strong><?= (int)$lockoutMinutes ?> percet</strong>!
                                </div>
                            <?php endif; ?>

                            <form method="POST" action="<?= e(ADMIN_ENTRY_FILE) ?>">
                                <input type="hidden" name="action" value="login" />

                                <div class="mb-3">
                                    <label class="form-label" for="username">Felhasználónév</label>
                                    <input class="form-control" id="username" name="username" required type="text" autocomplete="username" <?= $isLockedOut ? 'disabled' : '' ?> />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="password">Jelszó</label>
                                    <input class="form-control" id="password" name="password" required type="password" autocomplete="current-password" <?= $isLockedOut ? 'disabled' : '' ?> />
                                </div>

                                <button class="btn btn-custom w-100" type="submit" <?= $isLockedOut ? 'disabled' : '' ?>>
                                    Belépés
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </section>

    <?php else: ?>
        <!-- ========================================== -->
        <!-- BEJELENTKEZETT ADMIN DASHBOARD -->
        <!-- ========================================== -->
        <section class="admin-hero">
            <div class="container text-center">
                <p class="text-accent fw-bold text-uppercase mb-2">
                    Tartalomkezelő Központ • Lapozható Weblap Szerkesztő
                </p>
                <h1 class="admin-title">
                    Admin Vezérlőpult
                </h1>
                <p class="lead text-muted-custom mt-3 mx-auto" style="max-width: 850px;">
                    Üdvözlünk, <strong><?= e($_SESSION['admin_username'] ?? 'Admin') ?></strong>! Kattints a <strong>Balra / Jobbra</strong> lapozó gombokra a weblapok szerkesztéséhez, a Webshop tárgyak és berendelések kezeléséhez, valamint az adatbázisokhoz.
                </p>

                <div class="d-flex justify-content-center gap-2 mt-4 flex-wrap" id="adminQuickTabs">
                    <a href="#szinek" data-goto-page="szinek" class="btn btn-sm btn-outline-secondary admin-tab-pill">1. Színek</a>
                    <a href="#webshop" data-goto-page="webshop" class="btn btn-sm btn-outline-secondary admin-tab-pill">2. Webshop & Berendelések</a>
                    <a href="#adatbazis" data-goto-page="adatbazis" class="btn btn-sm btn-outline-secondary admin-tab-pill">3. Adatbázis</a>
                    <a href="#szovegek" data-goto-page="szovegek" class="btn btn-sm btn-outline-secondary admin-tab-pill">4. Diszkográfia</a>
                    <a href="#koncertek" data-goto-page="koncertek" class="btn btn-sm btn-outline-secondary admin-tab-pill">5. Koncertek</a>
                    <a href="#galeria" data-goto-page="galeria" class="btn btn-sm btn-outline-secondary admin-tab-pill">6. Galéria</a>
                    <a href="#hirek" data-goto-page="hirek" class="btn btn-sm btn-outline-secondary admin-tab-pill">7. Hírek</a>
                    <a href="#timeline" data-goto-page="timeline" class="btn btn-sm btn-outline-secondary admin-tab-pill">8. Timeline</a>
                    <a href="#uzenetek" data-goto-page="uzenetek" class="btn btn-sm btn-outline-secondary admin-tab-pill">9. Üzenetek</a>
                    <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=logout" class="btn btn-sm btn-outline-light ms-lg-2">Kijelentkezés</a>
                </div>
            </div>
        </section>

        <?php if ($flash): ?>
            <div class="container mt-4">
                <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> text-center">
                    <?= e($flash['message']) ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- FELSŐ BALRA-JOBBRA LAPOZÓ SÁV -->
        <div class="container mt-4">
            <div class="admin-pager-bar">
                <button type="button" class="btn btn-custom admin-pager-btn" id="adminPrevBtnTop">
                    <svg class="line-icon me-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    <span>Balra (Előző weblap)</span>
                </button>

                <div class="admin-pager-status text-center">
                    <div class="small text-muted-custom text-uppercase fw-bold" id="adminPagerCounterTop">1 / 9. weblap</div>
                    <div class="h5 text-accent fw-bold mb-0" id="adminPagerTitleTop">Főoldal & Színvilág</div>
                </div>

                <button type="button" class="btn btn-custom admin-pager-btn" id="adminNextBtnTop">
                    <span>Jobbra (Következő weblap)</span>
                    <svg class="line-icon ms-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- 0. WEBOLDAL SZÍNVILÁG & TESTRESZABÁS -->
        <section class="admin-section admin-page-panel" id="szinek" data-admin-page="szinek" data-admin-title="Főoldal & Színvilág">
            <div class="container">
                <div class="admin-section-header text-center mb-4">
                    <h2 class="text-accent d-flex align-items-center justify-content-center gap-2">
                        <svg class="line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 28px; height: 28px;">
                            <circle cx="13.5" cy="6.5" r=".5" fill="currentColor"/>
                            <circle cx="17.5" cy="10.5" r=".5" fill="currentColor"/>
                            <circle cx="8.5" cy="7.5" r=".5" fill="currentColor"/>
                            <circle cx="6.5" cy="12.5" r=".5" fill="currentColor"/>
                            <path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C21.965 6.012 17.461 2 12 2z"/>
                        </svg>
                        <span>Weboldal Színvilág & Testreszabás</span>
                    </h2>
                    <p class="text-muted-custom mx-auto" style="max-width: 800px;">
                        Itt egyetlen színválasztóval azonnal megváltoztathatod a teljes weboldal összes kiemelő színét, gombjait, ragyogásait, átmeneteit és vonalait!
                    </p>
                </div>

                <div class="row g-4 align-items-stretch">
                    <!-- Bal oldal: Főszínválasztó és beállítások -->
                    <div class="col-lg-7">
                        <div class="admin-card h-100">
                            <form method="POST" action="<?= e(ADMIN_ENTRY_FILE) ?>" id="themeForm">
                                <input type="hidden" name="action" value="save_theme">

                                <h4 class="text-accent fw-bold mb-3">Központi Színválasztó</h4>
                                <p class="text-muted-custom small mb-3">
                                    Válaszd ki az új főszínt a színválasztóval vagy add meg a HEX kódját. A gombok, fények, menük, szegélyek, kártya kiemelések és címkék automatikusan erre a színre váltanak az egész oldalon!
                                </p>

                                <div class="d-flex align-items-center gap-3 mb-4 p-3 rounded" style="background: var(--bg-main); border: 1px solid var(--border-color);">
                                    <input type="color" class="form-control form-control-color p-1" id="themeAccent" name="theme_accent" value="<?= e($currentTheme['accent']) ?>" style="width: 64px; height: 50px; cursor: pointer; border-radius: 12px;" title="Válassz színt">
                                    <div>
                                        <label for="themeAccentHex" class="form-label text-white mb-0 fw-bold">Kiválasztott főszín (HEX):</label>
                                        <input type="text" class="form-control form-control-sm text-uppercase fw-bold mt-1" id="themeAccentHex" value="<?= e($currentTheme['accent']) ?>" maxlength="7" style="width: 130px; letter-spacing: 1px;">
                                    </div>
                                </div>

                                <!-- Gyors előre definiált színpaletták -->
                                <label class="form-label text-white fw-bold mb-2 d-flex align-items-center gap-2">
                                    <svg class="line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;">
                                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                                    </svg>
                                    <span>1-Kattintásos Gyors Témák:</span>
                                </label>
                                <div class="d-flex flex-wrap gap-2 mb-4" id="themePresets">
                                    <button type="button" class="btn btn-sm btn-outline-light preset-btn d-inline-flex align-items-center gap-2" data-color="#ffffff" data-bg="#060607" data-card="#141417" data-text="#f4f4f6" data-gray="100"><span class="theme-preset-dot" style="background-color: #ffffff;"></span> Fekete-Fehér</button>
                                    <button type="button" class="btn btn-sm preset-btn d-inline-flex align-items-center gap-2" style="border: 1px solid #e50914; color: #ff4d4d;" data-color="#e50914" data-bg="#080303" data-card="#140a0a" data-text="#f4f4f6" data-gray="0"><span class="theme-preset-dot" style="background-color: #e50914;"></span> Klasszikus Vörös</button>
                                    <button type="button" class="btn btn-sm preset-btn d-inline-flex align-items-center gap-2" style="border: 1px solid #00b4d8; color: #00b4d8;" data-color="#00b4d8" data-bg="#020810" data-card="#081424" data-text="#f4f4f6" data-gray="0"><span class="theme-preset-dot" style="background-color: #00b4d8;"></span> Óceán Kék</button>
                                    <button type="button" class="btn btn-sm preset-btn d-inline-flex align-items-center gap-2" style="border: 1px solid #10b981; color: #10b981;" data-color="#10b981" data-bg="#020a06" data-card="#0a1a10" data-text="#f4f4f6" data-gray="0"><span class="theme-preset-dot" style="background-color: #10b981;"></span> Smaragd Zöld</button>
                                    <button type="button" class="btn btn-sm preset-btn d-inline-flex align-items-center gap-2" style="border: 1px solid #f59e0b; color: #f59e0b;" data-color="#f59e0b" data-bg="#0a0702" data-card="#1c140a" data-text="#f4f4f6" data-gray="0"><span class="theme-preset-dot" style="background-color: #f59e0b;"></span> Arany Metál</button>
                                    <button type="button" class="btn btn-sm preset-btn d-inline-flex align-items-center gap-2" style="border: 1px solid #a855f7; color: #c084fc;" data-color="#a855f7" data-bg="#08030c" data-card="#160822" data-text="#f4f4f6" data-gray="0"><span class="theme-preset-dot" style="background-color: #a855f7;"></span> Neon Ibolya</button>
                                    <button type="button" class="btn btn-sm preset-btn d-inline-flex align-items-center gap-2" style="border: 1px solid #ff6b35; color: #ff8c5a;" data-color="#ff6b35" data-bg="#0c0502" data-card="#1e0f08" data-text="#f4f4f6" data-gray="0"><span class="theme-preset-dot" style="background-color: #ff6b35;"></span> Láva Narancs</button>
                                    <button type="button" class="btn btn-sm preset-btn d-inline-flex align-items-center gap-2" style="border: 1px solid #ec4899; color: #f472b6;" data-color="#ec4899" data-bg="#0c0308" data-card="#1e0915" data-text="#f4f4f6" data-gray="0"><span class="theme-preset-dot" style="background-color: #ec4899;"></span> Cyber Pink</button>
                                </div>

                                <!-- Haladó finomhangolás lenyitható doboz -->
                                <div class="accordion mb-4" id="advancedColorsAccordion">
                                    <div class="accordion-item" style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden;">
                                        <h2 class="accordion-header">
                                            <button class="accordion-button collapsed text-white d-flex align-items-center gap-2" type="button" data-bs-toggle="collapse" data-bs-target="#advancedColorsCollapse" style="background: var(--bg-main); box-shadow: none;">
                                                <svg class="line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;">
                                                    <path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/>
                                                    <circle cx="12" cy="12" r="3"/>
                                                </svg>
                                                <span>Részletes színbeállítások (Háttér, Kártyák, Képszűrő)</span>
                                            </button>
                                        </h2>
                                        <div id="advancedColorsCollapse" class="accordion-collapse collapse" data-bs-parent="#advancedColorsAccordion">
                                            <div class="accordion-body">
                                                <div class="row g-3">
                                                    <div class="col-md-4">
                                                        <label class="form-label small text-muted-custom">Háttérszín:</label>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <input type="color" class="form-control form-control-color p-1" id="themeBgMain" name="theme_bg_main" value="<?= e($currentTheme['bg_main']) ?>" style="width: 44px; height: 38px;">
                                                            <input type="text" class="form-control form-control-sm text-uppercase" id="themeBgMainHex" value="<?= e($currentTheme['bg_main']) ?>" style="width: 90px;">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small text-muted-custom">Kártyák háttere:</label>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <input type="color" class="form-control form-control-color p-1" id="themeBgCard" name="theme_bg_card" value="<?= e($currentTheme['bg_card']) ?>" style="width: 44px; height: 38px;">
                                                            <input type="text" class="form-control form-control-sm text-uppercase" id="themeBgCardHex" value="<?= e($currentTheme['bg_card']) ?>" style="width: 90px;">
                                                        </div>
                                                    </div>
                                                    <div class="col-md-4">
                                                        <label class="form-label small text-muted-custom">Szövegek alapszíne:</label>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <input type="color" class="form-control form-control-color p-1" id="themeTextMain" name="theme_text_main" value="<?= e($currentTheme['text_main']) ?>" style="width: 44px; height: 38px;">
                                                            <input type="text" class="form-control form-control-sm text-uppercase" id="themeTextMainHex" value="<?= e($currentTheme['text_main']) ?>" style="width: 90px;">
                                                        </div>
                                                    </div>
                                                    <div class="col-12 mt-3">
                                                        <label class="form-label small text-muted-custom">Fotók és képek megjelenése:</label>
                                                        <select class="form-select form-select-sm" name="theme_grayscale" id="themeGrayscale">
                                                            <option value="100" <?= ($currentTheme['image_grayscale'] ?? '100') == '100' ? 'selected' : '' ?>>Fekete-Fehér fotók (100% monokróm szűrő)</option>
                                                            <option value="50" <?= ($currentTheme['image_grayscale'] ?? '100') == '50' ? 'selected' : '' ?>>Finom sötét deszaturált fotók (50%)</option>
                                                            <option value="0" <?= ($currentTheme['image_grayscale'] ?? '100') == '0' ? 'selected' : '' ?>>Eredeti színes fotók (0% szűrő)</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Mentés és visszaállítás gombok -->
                                <div class="d-flex flex-wrap gap-2 pt-3 border-top border-secondary">
                                    <button type="submit" class="btn btn-custom px-4 d-inline-flex align-items-center gap-2">
                                        <svg class="line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;">
                                            <path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/>
                                            <polyline points="17 21 17 13 7 13 7 21"/>
                                            <polyline points="7 3 7 8 15 8"/>
                                        </svg>
                                        <span>Színek mentése az egész weboldalra</span>
                                    </button>
                                    <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=reset_theme" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" onclick="return confirm('Biztosan visszaállítod az alapértelmezett fekete-fehér témát?')">
                                        <svg class="line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 18px; height: 18px;">
                                            <path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/>
                                            <path d="M3 3v5h5"/>
                                        </svg>
                                        <span>Visszaállítás fekete-fehérre</span>
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Jobb oldal: Élő interaktív előnézet -->
                    <div class="col-lg-5">
                        <div class="admin-card h-100 d-flex flex-column justify-content-between" id="themeLivePreviewCard">
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h4 class="preview-accent-title fw-bold mb-0">Élő Előnézet</h4>
                                    <span class="preview-badge px-3 py-1 rounded-pill small fw-bold">AKTÍV MINTA</span>
                                </div>
                                <p class="text-muted-custom small mb-4">
                                    A színválasztó állításakor ez a doboz valós időben megmutatja az új stílust:
                                </p>

                                <div class="p-4 rounded-4 mb-3 preview-box" style="border: 1px solid var(--border-color); background-color: var(--bg-card); transition: all 0.25s ease;">
                                    <div class="preview-eyebrow mb-1 small text-uppercase fw-bold">Következő Fellépés</div>
                                    <h3 class="preview-title h4 fw-bold mb-2">N/A Koncert</h3>
                                    <p class="text-muted-custom small mb-3">
                                        Ez a minta doboz valós időben reagál a színválasztó mozgatására. A címsorok, szegélyek, gombok és ragyogások azonnal felveszik a kiválasztott árnyalatot!
                                    </p>
                                    <div class="d-flex gap-2 flex-wrap">
                                        <button type="button" class="btn preview-btn-primary btn-sm px-3">Fő gomb</button>
                                        <button type="button" class="btn preview-btn-outline btn-sm px-3">Keretes gomb</button>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center pt-3 border-top border-secondary">
                                <small class="text-muted-custom">
                                    Mentés után a beállított színek a teljes weboldalon és minden aloldalon megjelennek.
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <?php require __DIR__ . '/includes/admin_webshop_section.php'; ?>

        <?php require __DIR__ . '/includes/admin_texts_section.php'; ?>

        <!-- 1. KONCERTEK KEZELÉSE -->
        <section class="admin-section admin-page-panel" id="koncertek" data-admin-page="koncertek" data-admin-title="Koncertek Oldal">
            <div class="container">
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="admin-card">
                            <h2 class="section-title text-accent mb-4">
                                <?= $editConcert ? 'Koncert szerkesztése' : 'Új koncert hozzáadása' ?>
                            </h2>
                            <form method="POST" action="<?= e(ADMIN_ENTRY_FILE) ?>">
                                <input type="hidden" name="action" value="save_concert" />
                                <?php if ($editConcert): ?>
                                    <input type="hidden" name="edit_id" value="<?= $editConcert['id'] ?>" />
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="form-label" for="name">Koncert neve *</label>
                                    <input class="form-control" id="name" name="name" required type="text" value="<?= e($editConcert['title'] ?? '') ?>" />
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="date">Dátum *</label>
                                        <input class="form-control" id="date" name="date" required type="date" value="<?= e($editConcert['date'] ?? '') ?>" />
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" for="time">Időpont *</label>
                                        <input class="form-control" id="time" name="time" required type="time" value="<?= e($editConcert['time'] ?? '') ?>" />
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="place">Helyszín neve *</label>
                                    <input class="form-control" id="place" name="place" placeholder="Pl. UMSZKI" required type="text" value="<?= e($editConcert['place'] ?? '') ?>" />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="address">Pontos cím *</label>
                                    <input class="form-control" id="address" name="address" placeholder="Pl. 1041 Budapest, Görgey Artúr utca 26." required type="text" value="<?= e($editConcert['address'] ?? '') ?>" />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="status">Státusz</label>
                                    <select class="form-select" id="status" name="status">
                                        <option value="open" <?= ($editConcert['status'] ?? '') === 'open' ? 'selected' : '' ?>>Látogatható</option>
                                        <option value="private" <?= ($editConcert['status'] ?? '') === 'private' ? 'selected' : '' ?>>Privát</option>
                                        <option value="not-open" <?= ($editConcert['status'] ?? '') === 'not-open' ? 'selected' : '' ?>>Nem látogatható</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="entry">Belépés leírása</label>
                                    <textarea class="form-control" id="entry" name="entry" rows="2"><?= e($editConcert['entry'] ?? '') ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="ticketOption">Jegy opció</label>
                                    <select class="form-select" id="ticketOption" name="ticketOption">
                                        <option value="no-ticket" <?= ($editConcert['ticketOption'] ?? '') === 'no-ticket' ? 'selected' : '' ?>>Nem lehet jegyet venni</option>
                                        <option value="ticket-link" <?= ($editConcert['ticketOption'] ?? '') === 'ticket-link' ? 'selected' : '' ?>>Jegyvásárlás linkkel</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="ticketLink">Jegyvásárlási link (ha van)</label>
                                    <input class="form-control" id="ticketLink" name="ticketLink" placeholder="https://..." type="url" value="<?= e($editConcert['ticketLink'] ?? '') ?>" />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="streamOption">Élő adás</label>
                                    <select class="form-select" id="streamOption" name="streamOption">
                                        <option value="no-stream" <?= ($editConcert['streamOption'] ?? '') === 'no-stream' ? 'selected' : '' ?>>Nem készül élő adás</option>
                                        <option value="stream-link" <?= ($editConcert['streamOption'] ?? '') === 'stream-link' ? 'selected' : '' ?>>Készül élő adás</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="streamLink">Élő adás linkje</label>
                                    <input class="form-control" id="streamLink" name="streamLink" placeholder="https://..." type="url" value="<?= e($editConcert['streamLink'] ?? '') ?>" />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="recordingOption">Felvétel</label>
                                    <select class="form-select" id="recordingOption" name="recordingOption">
                                        <option value="no-recording" <?= ($editConcert['recordingOption'] ?? '') === 'no-recording' ? 'selected' : '' ?>>Nem készül felvétel</option>
                                        <option value="recording-link" <?= ($editConcert['recordingOption'] ?? '') === 'recording-link' ? 'selected' : '' ?>>Készül felvétel</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="recordingLink">Felvétel linkje</label>
                                    <input class="form-control" id="recordingLink" name="recordingLink" placeholder="https://..." type="url" value="<?= e($editConcert['recordingLink'] ?? '') ?>" />
                                </div>

                                <div class="d-flex gap-2">
                                    <button class="btn btn-custom" type="submit">
                                        <?= $editConcert ? 'Módosítás mentése' : 'Koncert hozzáadása' ?>
                                    </button>
                                    <?php if ($editConcert): ?>
                                        <a href="<?= e(ADMIN_ENTRY_FILE) ?>#koncertek" class="btn btn-outline-secondary">Mégse</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="admin-card">
                            <h2 class="section-title text-accent mb-4">Eddigi koncertek</h2>
                            <div class="admin-list" style="max-height: 600px; overflow-y: auto;">
                                <?php if (empty($allConcerts)): ?>
                                    <p class="text-muted-custom">Még nincs rögzített koncert.</p>
                                <?php else: ?>
                                    <?php foreach ($allConcerts as $c): ?>
                                        <div class="p-3 mb-3 border rounded border-secondary">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <h5 class="mb-0 text-white"><?= e($c['title']) ?></h5>
                                                <span class="badge bg-secondary"><?= e(get_status_text($c['status'])) ?></span>
                                            </div>
                                            <small class="text-muted-custom d-block mb-2">
                                                <?= format_hungarian_date($c['date']) ?> <?= e($c['time']) ?> - <?= e($c['place']) ?>
                                            </small>
                                            <div class="d-flex gap-2">
                                                <a href="<?= e(ADMIN_ENTRY_FILE) ?>?edit_concert=<?= $c['id'] ?>#koncertek" class="btn btn-sm btn-outline-warning">Szerkesztés</a>
                                                <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=delete_concert&id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary text-white-50" onclick="return confirm('Biztosan törlöd ezt a koncertet?')">Törlés</a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex flex-wrap gap-2 mt-4">
                                <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=clear_concerts" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Biztosan törlöd az összes koncertet?')">Összes törlése</a>
                                <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=reset_concerts" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Visszaállítod az alapértelmezett koncerteket?')">Alap koncertek visszaállítása</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 2. GALÉRIA KEZELÉSE -->
        <section class="admin-section-alt admin-page-panel" id="galeria" data-admin-page="galeria" data-admin-title="Galéria Oldal">
            <div class="container">
                <div class="admin-section-header text-center mb-4">
                    <h2 class="text-accent">Galéria kezelése</h2>
                    <p class="text-muted-custom">Itt tudsz új képalbumot létrehozni és képeket feltölteni hozzá (szerverre mentés).</p>
                </div>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="admin-card">
                            <h3 class="text-accent fw-bold mb-4">Új galéria-album</h3>
                            <form method="POST" action="<?= e(ADMIN_ENTRY_FILE) ?>" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="save_gallery" />

                                <div class="mb-3">
                                    <label class="form-label" for="galleryTitle">Album neve *</label>
                                    <input class="form-control" id="galleryTitle" name="galleryTitle" placeholder="Pl. UMSZKI 100 koncert" required type="text" />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="galleryDate">Dátum *</label>
                                    <input class="form-control" id="galleryDate" name="galleryDate" required type="date" value="<?= date('Y-m-d') ?>" />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="galleryDescription">Leírás *</label>
                                    <textarea class="form-control" id="galleryDescription" name="galleryDescription" required rows="3"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="galleryImages">Képek feltöltése (többet is kijelölhetsz) *</label>
                                    <input class="form-control" id="galleryImages" name="galleryImages[]" type="file" multiple accept="image/*" required />
                                    <small class="text-muted-custom">A képek a PHP uploads/ mappájába mentődnek el.</small>
                                </div>

                                <button class="btn btn-custom" type="submit">Album hozzáadása</button>
                            </form>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="admin-card">
                            <h3 class="text-accent fw-bold mb-4">Feltöltött albumok</h3>
                            <div style="max-height: 500px; overflow-y: auto;">
                                <?php if (empty($allGallery)): ?>
                                    <p class="text-muted-custom">Még nincs feltöltött album.</p>
                                <?php else: ?>
                                    <?php foreach ($allGallery as $album): ?>
                                        <div class="p-3 mb-3 border rounded border-secondary">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <h5 class="text-white mb-0"><?= e($album['title']) ?></h5>
                                                <span class="badge bg-secondary"><?= count($album['images']) ?> kép</span>
                                            </div>
                                            <small class="text-muted-custom d-block mb-2"><?= format_hungarian_date($album['date']) ?></small>
                                            <p class="text-muted-custom small mb-2"><?= e($album['description']) ?></p>

                                            <?php if (!empty($album['images'])): ?>
                                                <div class="d-flex gap-2 flex-wrap mb-3">
                                                    <?php foreach (array_slice($album['images'], 0, 4) as $thumb): ?>
                                                        <img src="<?= e(normalize_image_url($thumb)) ?>" alt="Thumb" style="width: 50px; height: 50px; object-fit: cover; border-radius: 4px;" />
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>

                                            <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=delete_gallery&id=<?= $album['id'] ?>" class="btn btn-sm btn-outline-secondary text-white-50" onclick="return confirm('Biztosan törlöd az albumot és az összes feltöltött képét?')">Album törlése</a>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="mt-4">
                                <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=reset_gallery" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Biztosan kiüríted a galériát?')">Galéria kiürítése</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 3. HÍREK KEZELÉSE -->
        <section class="admin-section admin-page-panel" id="hirek" data-admin-page="hirek" data-admin-title="Hírek Oldal">
            <div class="container">
                <div class="admin-section-header text-center mb-4">
                    <h2 class="text-accent">Hírek kezelése</h2>
                    <p class="text-muted-custom">Itt tudsz új híreket hozzáadni, szerkeszteni vagy törölni.</p>
                </div>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="admin-card">
                            <h3 class="text-accent fw-bold mb-4"><?= $editNews ? 'Hír szerkesztése' : 'Új hír hozzáadása' ?></h3>
                            <form method="POST" action="<?= e(ADMIN_ENTRY_FILE) ?>" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="save_news" />
                                <?php if ($editNews): ?>
                                    <input type="hidden" name="edit_id" value="<?= $editNews['id'] ?>" />
                                    <input type="hidden" name="existing_image" value="<?= e($editNews['image']) ?>" />
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="form-label" for="newsTitle">Hír címe *</label>
                                    <input class="form-control" id="newsTitle" name="newsTitle" required type="text" value="<?= e($editNews['title'] ?? '') ?>" />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="newsDate">Dátum *</label>
                                    <input class="form-control" id="newsDate" name="newsDate" required type="date" value="<?= e($editNews['date'] ?? date('Y-m-d')) ?>" />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="newsType">Hír típusa</label>
                                    <select class="form-select" id="newsType" name="newsType">
                                        <?php $types = ['Bejelentés', 'Koncert', 'Próba', 'Galéria', 'Egyéb']; ?>
                                        <?php foreach ($types as $t): ?>
                                            <option value="<?= $t ?>" <?= ($editNews['type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="newsText">Hír szövege *</label>
                                    <textarea class="form-control" id="newsText" name="newsText" required rows="5"><?= e($editNews['text'] ?? '') ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="newsImage">Kép feltöltése (opcionális)</label>
                                    <input class="form-control" id="newsImage" name="newsImage" type="file" accept="image/*" />
                                    <?php if (!empty($editNews['image'])): ?>
                                        <small class="text-muted-custom d-block mt-1">Jelenlegi kép: <?= e($editNews['image']) ?></small>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex gap-2">
                                    <button class="btn btn-custom" type="submit"><?= $editNews ? 'Módosítás mentése' : 'Hír közzététele' ?></button>
                                    <?php if ($editNews): ?>
                                        <a href="<?= e(ADMIN_ENTRY_FILE) ?>#hirek" class="btn btn-outline-secondary">Mégse</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="admin-card">
                            <h3 class="text-accent fw-bold mb-4">Közzétett hírek</h3>
                            <div style="max-height: 500px; overflow-y: auto;">
                                <?php if (empty($allNews)): ?>
                                    <p class="text-muted-custom">Még nincs közzétett hír.</p>
                                <?php else: ?>
                                    <?php foreach ($allNews as $news): ?>
                                        <div class="p-3 mb-3 border rounded border-secondary">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <h5 class="text-white mb-0"><?= e($news['title']) ?></h5>
                                                <span class="badge bg-secondary"><?= e($news['type']) ?></span>
                                            </div>
                                            <small class="text-muted-custom d-block mb-2"><?= format_hungarian_date($news['date']) ?></small>
                                            <p class="text-muted-custom small mb-2"><?= nl2br(e(mb_strimwidth($news['text'], 0, 140, '...'))) ?></p>
                                            <div class="d-flex gap-2">
                                                <a href="<?= e(ADMIN_ENTRY_FILE) ?>?edit_news=<?= $news['id'] ?>#hirek" class="btn btn-sm btn-outline-secondary">Szerkesztés</a>
                                                <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=delete_news&id=<?= $news['id'] ?>" class="btn btn-sm btn-outline-secondary text-white-50" onclick="return confirm('Biztosan törlöd ezt a hírt?')">Törlés</a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="mt-4">
                                <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=reset_news" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Visszaállítod az alapértelmezett híreket?')">Alap hírek visszaállítása</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 4. TIMELINE KEZELÉSE -->
        <section class="admin-section-alt admin-page-panel" id="timeline" data-admin-page="timeline" data-admin-title="Timeline Oldal">
            <div class="container">
                <div class="admin-section-header text-center mb-4">
                    <h2 class="text-accent">Timeline kezelése</h2>
                    <p class="text-muted-custom">Itt tudsz új idővonal eseményeket hozzáadni vagy módosítani.</p>
                </div>
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="admin-card">
                            <h3 class="text-accent fw-bold mb-4"><?= $editTimeline ? 'Esemény szerkesztése' : 'Új esemény' ?></h3>
                            <form method="POST" action="<?= e(ADMIN_ENTRY_FILE) ?>" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="save_timeline" />
                                <?php if ($editTimeline): ?>
                                    <input type="hidden" name="edit_id" value="<?= $editTimeline['id'] ?>" />
                                    <input type="hidden" name="existing_image" value="<?= e($editTimeline['image']) ?>" />
                                <?php endif; ?>

                                <div class="mb-3">
                                    <label class="form-label" for="timelineTitle">Esemény címe *</label>
                                    <input class="form-control" id="timelineTitle" name="timelineTitle" required type="text" value="<?= e($editTimeline['title'] ?? '') ?>" />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="timelineDate">Dátum *</label>
                                    <input class="form-control" id="timelineDate" name="timelineDate" required type="date" value="<?= e($editTimeline['date'] ?? date('Y-m-d')) ?>" />
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="timelineType">Esemény típusa</label>
                                    <select class="form-select" id="timelineType" name="timelineType">
                                        <?php $tlTypes = ['Alakulás', 'Próba', 'Koncert', 'Galéria', 'Egyéb']; ?>
                                        <?php foreach ($tlTypes as $t): ?>
                                            <option value="<?= $t ?>" <?= ($editTimeline['type'] ?? '') === $t ? 'selected' : '' ?>><?= $t ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="timelineText">Esemény leírása *</label>
                                    <textarea class="form-control" id="timelineText" name="timelineText" required rows="4"><?= e($editTimeline['text'] ?? '') ?></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label" for="timelineImage">Kép feltöltése (opcionális)</label>
                                    <input class="form-control" id="timelineImage" name="timelineImage" type="file" accept="image/*" />
                                </div>

                                <div class="d-flex gap-2">
                                    <button class="btn btn-custom" type="submit"><?= $editTimeline ? 'Módosítás mentése' : 'Esemény mentése' ?></button>
                                    <?php if ($editTimeline): ?>
                                        <a href="<?= e(ADMIN_ENTRY_FILE) ?>#timeline" class="btn btn-outline-secondary">Mégse</a>
                                    <?php endif; ?>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <div class="admin-card">
                            <h3 class="text-accent fw-bold mb-4">Timeline események</h3>
                            <div style="max-height: 500px; overflow-y: auto;">
                                <?php if (empty($allTimeline)): ?>
                                    <p class="text-muted-custom">Még nincs rögzített esemény.</p>
                                <?php else: ?>
                                    <?php foreach ($allTimeline as $event): ?>
                                        <div class="p-3 mb-3 border rounded border-secondary">
                                            <div class="d-flex justify-content-between align-items-center mb-1">
                                                <h5 class="text-white mb-0"><?= e($event['title']) ?></h5>
                                                <span class="badge bg-secondary"><?= e($event['type']) ?></span>
                                            </div>
                                            <small class="text-muted-custom d-block mb-2"><?= format_hungarian_date($event['date']) ?></small>
                                            <p class="text-muted-custom small mb-2"><?= e($event['text']) ?></p>
                                            <div class="d-flex gap-2">
                                                <a href="<?= e(ADMIN_ENTRY_FILE) ?>?edit_timeline=<?= $event['id'] ?>#timeline" class="btn btn-sm btn-outline-secondary">Szerkesztés</a>
                                                <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=delete_timeline&id=<?= $event['id'] ?>" class="btn btn-sm btn-outline-secondary text-white-50" onclick="return confirm('Biztosan törlöd?')">Törlés</a>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            <div class="mt-4">
                                <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=reset_timeline" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Visszaállítod az alapértelmezett idővonalat?')">Alap timeline visszaállítása</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- 5. ÜZENETEK KEZELÉSE -->
        <section class="admin-section admin-page-panel" id="uzenetek" data-admin-page="uzenetek" data-admin-title="Kapcsolat & Üzenetek Oldal">
            <div class="container">
                <div class="admin-section-header text-center mb-4">
                    <h2 class="text-accent">Beérkezett üzenetek</h2>
                    <p class="text-muted-custom">Itt találod a látogatók által a kapcsolat oldalon beküldött megkereséseket.</p>
                </div>
                <div class="admin-card">
                    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                        <h3 class="text-accent fw-bold mb-0">Üzenetlista (<?= count($allMessages) ?> db)</h3>
                        <?php if (!empty($allMessages)): ?>
                            <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=delete_all_messages" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Biztosan törlöd az összes üzenetet?')">Összes törlése</a>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($allMessages)): ?>
                        <p class="text-muted-custom text-center py-4">Még nem érkezett üzenet a weboldalon keresztül.</p>
                    <?php else: ?>
                        <div class="row g-3">
                            <?php foreach ($allMessages as $msg): ?>
                                <div class="col-12">
                                    <div class="p-3 border rounded <?= empty($msg['opened']) ? 'border-light bg-dark' : 'border-secondary' ?>">
                                        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                            <div>
                                                <h5 class="text-white mb-0 d-inline me-2"><?= e($msg['title']) ?></h5>
                                                <?php if (empty($msg['opened'])): ?>
                                                    <span class="badge bg-light text-dark fw-bold">ÚJ</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Olvasott</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted-custom"><?= e($msg['createdAt']) ?></small>
                                        </div>

                                        <p class="text-white mb-2" style="white-space: pre-wrap;"><?= e($msg['text']) ?></p>

                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top border-secondary">
                                            <small class="text-muted-custom">
                                                <strong>Elérhetőség:</strong> <?= e($msg['contact']) ?>
                                                <?php if (!empty($msg['location'])): ?>
                                                    | <strong>Helyszín:</strong> <?= e($msg['location']) ?>
                                                <?php endif; ?>
                                            </small>
                                            <div class="d-flex gap-2">
                                                <?php if (empty($msg['opened'])): ?>
                                                    <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=open_message&id=<?= $msg['id'] ?>" class="btn btn-sm btn-outline-light">Megjelölés olvasottként</a>
                                                <?php endif; ?>
                                                <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=delete_message&id=<?= $msg['id'] ?>" class="btn btn-sm btn-outline-secondary text-white-50" onclick="return confirm('Biztosan törlöd?')">Törlés</a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- ALSÓ BALRA-JOBBRA LAPOZÓ SÁV -->
        <div class="container mb-5">
            <div class="admin-pager-bar">
                <button type="button" class="btn btn-custom admin-pager-btn" id="adminPrevBtnBottom">
                    <svg class="line-icon me-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    <span>Balra (Előző weblap)</span>
                </button>

                <div class="admin-pager-status text-center">
                    <div class="small text-muted-custom text-uppercase fw-bold" id="adminPagerCounterBottom">1 / 9. weblap</div>
                    <div class="h5 text-accent fw-bold mb-0" id="adminPagerTitleBottom">Főoldal & Színvilág</div>
                </div>

                <button type="button" class="btn btn-custom admin-pager-btn" id="adminNextBtnBottom">
                    <span>Jobbra (Következő weblap)</span>
                    <svg class="line-icon ms-1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4">
                        <path d="M5 12h14M12 5l7 7-7 7"/>
                    </svg>
                </button>
            </div>
        </div>

    <?php endif; ?>
</main>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const accentPicker = document.getElementById('themeAccent');
    const accentHex = document.getElementById('themeAccentHex');
    const bgMainPicker = document.getElementById('themeBgMain');
    const bgMainHex = document.getElementById('themeBgMainHex');
    const bgCardPicker = document.getElementById('themeBgCard');
    const bgCardHex = document.getElementById('themeBgCardHex');
    const textMainPicker = document.getElementById('themeTextMain');
    const textMainHex = document.getElementById('themeTextMainHex');
    const grayscaleSelect = document.getElementById('themeGrayscale');
    const presetButtons = document.querySelectorAll('.preset-btn');

    function hexToRgb(hex) {
        hex = hex.replace(/^#/, '');
        if (hex.length === 3) {
            hex = hex[0] + hex[0] + hex[1] + hex[1] + hex[2] + hex[2];
        }
        if (hex.length !== 6) return { r: 255, g: 255, b: 255 };
        return {
            r: parseInt(hex.substring(0, 2), 16),
            g: parseInt(hex.substring(2, 4), 16),
            b: parseInt(hex.substring(4, 6), 16)
        };
    }

    function updateThemePreview() {
        if (!accentPicker) return;
        const color = accentPicker.value;
        const rgb = hexToRgb(color);
        const luminance = (0.299 * rgb.r + 0.587 * rgb.g + 0.114 * rgb.b) / 255;
        const contrast = luminance > 0.55 ? '#060607' : '#ffffff';

        // Élő előnézet elemeinek frissítése
        document.querySelectorAll('.preview-accent-title, .preview-title, .preview-eyebrow').forEach(el => {
            el.style.color = color;
        });

        const badge = document.querySelector('.preview-badge');
        if (badge) {
            badge.style.backgroundColor = color;
            badge.style.color = contrast;
        }

        const btnPrimary = document.querySelector('.preview-btn-primary');
        if (btnPrimary) {
            btnPrimary.style.backgroundColor = color;
            btnPrimary.style.color = contrast;
            btnPrimary.style.borderColor = color;
            btnPrimary.style.boxShadow = `0 4px 14px rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0.35)`;
        }

        const btnOutline = document.querySelector('.preview-btn-outline');
        if (btnOutline) {
            btnOutline.style.color = color;
            btnOutline.style.borderColor = color;
        }

        const previewBox = document.querySelector('.preview-box');
        if (previewBox) {
            previewBox.style.borderColor = `rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0.45)`;
            previewBox.style.boxShadow = `0 10px 30px rgba(${rgb.r}, ${rgb.g}, ${rgb.b}, 0.15)`;
            if (bgCardPicker) {
                previewBox.style.backgroundColor = bgCardPicker.value;
            }
        }
    }

    if (accentPicker && accentHex) {
        accentPicker.addEventListener('input', function() {
            accentHex.value = this.value.toUpperCase();
            updateThemePreview();
        });

        accentHex.addEventListener('input', function() {
            let val = this.value.trim();
            if (!val.startsWith('#')) val = '#' + val;
            if (/^#[0-9a-fA-F]{6}$/.test(val)) {
                accentPicker.value = val;
                updateThemePreview();
            }
        });
    }

    if (bgMainPicker && bgMainHex) {
        bgMainPicker.addEventListener('input', function() {
            bgMainHex.value = this.value.toUpperCase();
        });
        bgMainHex.addEventListener('input', function() {
            let val = this.value.trim();
            if (!val.startsWith('#')) val = '#' + val;
            if (/^#[0-9a-fA-F]{6}$/.test(val)) {
                bgMainPicker.value = val;
            }
        });
    }

    if (bgCardPicker && bgCardHex) {
        bgCardPicker.addEventListener('input', function() {
            bgCardHex.value = this.value.toUpperCase();
            updateThemePreview();
        });
        bgCardHex.addEventListener('input', function() {
            let val = this.value.trim();
            if (!val.startsWith('#')) val = '#' + val;
            if (/^#[0-9a-fA-F]{6}$/.test(val)) {
                bgCardPicker.value = val;
                updateThemePreview();
            }
        });
    }

    if (textMainPicker && textMainHex) {
        textMainPicker.addEventListener('input', function() {
            textMainHex.value = this.value.toUpperCase();
        });
        textMainHex.addEventListener('input', function() {
            let val = this.value.trim();
            if (!val.startsWith('#')) val = '#' + val;
            if (/^#[0-9a-fA-F]{6}$/.test(val)) {
                textMainPicker.value = val;
            }
        });
    }

    presetButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            const color = this.dataset.color;
            const bg = this.dataset.bg;
            const card = this.dataset.card;
            const text = this.dataset.text;
            const gray = this.dataset.gray;

            if (accentPicker) accentPicker.value = color;
            if (accentHex) accentHex.value = color.toUpperCase();
            if (bgMainPicker && bg) bgMainPicker.value = bg;
            if (bgMainHex && bg) bgMainHex.value = bg.toUpperCase();
            if (bgCardPicker && card) bgCardPicker.value = card;
            if (bgCardHex && card) bgCardHex.value = card.toUpperCase();
            if (textMainPicker && text) textMainPicker.value = text;
            if (textMainHex && text) textMainHex.value = text.toUpperCase();
            if (grayscaleSelect && gray !== undefined) grayscaleSelect.value = gray;

            updateThemePreview();
        });
    });

    updateThemePreview();

    // =========================================================================
    // BALRA-JOBBRA KATTINTHATÓ WEBLAP LAPOZÓ (ADMIN PAGINATOR)
    // =========================================================================
    const panels = Array.from(document.querySelectorAll('.admin-page-panel'));
    const quickTabs = Array.from(document.querySelectorAll('.admin-tab-pill'));
    const prevTop = document.getElementById('adminPrevBtnTop');
    const nextTop = document.getElementById('adminNextBtnTop');
    const prevBottom = document.getElementById('adminPrevBtnBottom');
    const nextBottom = document.getElementById('adminNextBtnBottom');
    const counterTop = document.getElementById('adminPagerCounterTop');
    const titleTop = document.getElementById('adminPagerTitleTop');
    const counterBottom = document.getElementById('adminPagerCounterBottom');
    const titleBottom = document.getElementById('adminPagerTitleBottom');

    let currentIndex = 0;

    function showAdminPage(index, updateHash = true) {
        if (!panels.length) return;
        if (index < 0) index = panels.length - 1;
        if (index >= panels.length) index = 0;
        currentIndex = index;

        const activePanel = panels[currentIndex];
        const pageKey = activePanel.getAttribute('data-admin-page') || activePanel.id;
        const pageTitle = activePanel.getAttribute('data-admin-title') || pageKey;

        panels.forEach((p, idx) => {
            if (idx === currentIndex) {
                p.classList.remove('d-none');
            } else {
                p.classList.add('d-none');
            }
        });

        quickTabs.forEach(tab => {
            if (tab.getAttribute('data-goto-page') === pageKey) {
                tab.classList.add('active');
            } else {
                tab.classList.remove('active');
            }
        });

        const countText = (currentIndex + 1) + ' / ' + panels.length + '. weblap';
        if (counterTop) counterTop.textContent = countText;
        if (titleTop) titleTop.textContent = pageTitle;
        if (counterBottom) counterBottom.textContent = countText;
        if (titleBottom) titleBottom.textContent = pageTitle;

        if (updateHash && window.history && window.history.replaceState) {
            window.history.replaceState(null, '', '#' + pageKey);
        }
    }

    function findPageIndexByKey(key) {
        if (!key) return -1;
        key = key.replace(/^#/, '');
        return panels.findIndex(p => (p.getAttribute('data-admin-page') === key || p.id === key));
    }

    if (panels.length) {
        let initialKey = window.location.hash ? window.location.hash.replace(/^#/, '') : '';
        const urlParams = new URLSearchParams(window.location.search);
        if (!initialKey) {
            if (urlParams.has('edit_product')) initialKey = 'webshop';
            else if (urlParams.has('inspect_db') || urlParams.has('inspect_table')) initialKey = 'adatbazis';
            else if (urlParams.has('edit_discography')) initialKey = 'szovegek';
            else if (urlParams.has('edit_concert')) initialKey = 'koncertek';
            else if (urlParams.has('edit_news')) initialKey = 'hirek';
            else if (urlParams.has('edit_timeline')) initialKey = 'timeline';
        }
        const foundIdx = findPageIndexByKey(initialKey);
        showAdminPage(foundIdx >= 0 ? foundIdx : 0, false);

        if (prevTop) prevTop.addEventListener('click', () => showAdminPage(currentIndex - 1));
        if (nextTop) nextTop.addEventListener('click', () => showAdminPage(currentIndex + 1));
        if (prevBottom) prevBottom.addEventListener('click', () => {
            showAdminPage(currentIndex - 1);
            window.scrollTo({ top: 220, behavior: 'smooth' });
        });
        if (nextBottom) nextBottom.addEventListener('click', () => {
            showAdminPage(currentIndex + 1);
            window.scrollTo({ top: 220, behavior: 'smooth' });
        });

        quickTabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                const targetKey = this.getAttribute('data-goto-page');
                const idx = findPageIndexByKey(targetKey);
                if (idx >= 0) {
                    e.preventDefault();
                    showAdminPage(idx);
                }
            });
        });

        window.addEventListener('hashchange', function() {
            const idx = findPageIndexByKey(window.location.hash);
            if (idx >= 0) {
                showAdminPage(idx, false);
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
