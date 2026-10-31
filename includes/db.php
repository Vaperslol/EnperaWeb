<?php
require_once __DIR__ . '/config.php';

function get_db_connection(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $dbFile = ROOT_DIR . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.sqlite';
    $dbDir = dirname($dbFile);
    if (!is_dir($dbDir)) {
        @mkdir($dbDir, 0777, true);
    }

    $pdo = new PDO('sqlite:' . $dbFile);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("PRAGMA journal_mode = WAL;");
    $pdo->exec("PRAGMA foreign_keys = ON;");

    // Táblák létrehozása ha még nem léteznek
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS news (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            date TEXT NOT NULL,
            type TEXT NOT NULL,
            text TEXT NOT NULL,
            image TEXT DEFAULT ''
        );

        CREATE TABLE IF NOT EXISTS concerts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            date TEXT NOT NULL,
            time TEXT NOT NULL,
            place TEXT NOT NULL,
            address TEXT DEFAULT '',
            status TEXT DEFAULT 'open',
            entry TEXT DEFAULT '',
            ticketOption TEXT DEFAULT 'no-ticket',
            ticketLink TEXT DEFAULT '',
            streamOption TEXT DEFAULT 'no-stream',
            streamLink TEXT DEFAULT '',
            recordingOption TEXT DEFAULT 'no-recording',
            recordingLink TEXT DEFAULT ''
        );

        CREATE TABLE IF NOT EXISTS timeline (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            date TEXT NOT NULL,
            type TEXT NOT NULL,
            text TEXT NOT NULL,
            image TEXT DEFAULT ''
        );

        CREATE TABLE IF NOT EXISTS messages (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            contact TEXT NOT NULL,
            location TEXT DEFAULT '',
            text TEXT NOT NULL,
            createdAt TEXT NOT NULL,
            opened INTEGER DEFAULT 0
        );

        CREATE TABLE IF NOT EXISTS gallery (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            date TEXT NOT NULL,
            description TEXT DEFAULT '',
            images TEXT DEFAULT '[]',
            createdAt TEXT,
            updatedAt TEXT
        );

        CREATE TABLE IF NOT EXISTS settings (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS discography (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            category TEXT NOT NULL DEFAULT 'lemez',
            release_date TEXT NOT NULL,
            cover_image TEXT DEFAULT '',
            description TEXT DEFAULT '',
            tracks TEXT DEFAULT '[]',
            spotify_url TEXT DEFAULT '',
            youtube_url TEXT DEFAULT '',
            apple_url TEXT DEFAULT '',
            other_url TEXT DEFAULT ''
        );
    ");

    // Automatikus migráció database.json-ből ha a táblák üresek
    migrate_from_json_if_needed($pdo);
    seed_discography_if_needed($pdo);

    return $pdo;
}

function migrate_from_json_if_needed(PDO $pdo): void {
    $newsCount = (int)$pdo->query("SELECT COUNT(*) FROM news")->fetchColumn();
    if ($newsCount > 0) return;

    $jsonPath = ROOT_DIR . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'database.json';
    if (!file_exists($jsonPath)) return;

    $raw = file_get_contents($jsonPath);
    $data = json_decode($raw, true);
    if (!is_array($data)) return;

    if (!empty($data['news'])) {
        $stmt = $pdo->prepare("INSERT INTO news (id, title, date, type, text, image) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($data['news'] as $item) {
            $stmt->execute([$item['id'] ?? null, $item['title'], $item['date'], $item['type'], $item['text'], $item['image'] ?? '']);
        }
    }

    if (!empty($data['concerts'])) {
        $stmt = $pdo->prepare("INSERT INTO concerts (id, title, date, time, place, address, status, entry, ticketOption, ticketLink, streamOption, streamLink, recordingOption, recordingLink) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        foreach ($data['concerts'] as $item) {
            $stmt->execute([
                $item['id'] ?? null, $item['title'], $item['date'], $item['time'], $item['place'],
                $item['address'] ?? '', $item['status'] ?? 'open', $item['entry'] ?? '',
                $item['ticketOption'] ?? 'no-ticket', $item['ticketLink'] ?? '',
                $item['streamOption'] ?? 'no-stream', $item['streamLink'] ?? '',
                $item['recordingOption'] ?? 'no-recording', $item['recordingLink'] ?? ''
            ]);
        }
    }

    if (!empty($data['timeline'])) {
        $stmt = $pdo->prepare("INSERT INTO timeline (id, title, date, type, text, image) VALUES (?, ?, ?, ?, ?, ?)");
        foreach ($data['timeline'] as $item) {
            $stmt->execute([$item['id'] ?? null, $item['title'], $item['date'], $item['type'], $item['text'], $item['image'] ?? '']);
        }
    }

    if (!empty($data['messages'])) {
        $stmt = $pdo->prepare("INSERT INTO messages (id, title, contact, location, text, createdAt, opened) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($data['messages'] as $item) {
            $stmt->execute([
                $item['id'] ?? null, $item['title'], $item['contact'], $item['location'] ?? '',
                $item['text'], $item['createdAt'] ?? date('c'), !empty($item['opened']) ? 1 : 0
            ]);
        }
    }

    if (!empty($data['gallery'])) {
        $stmt = $pdo->prepare("INSERT INTO gallery (id, title, date, description, images, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($data['gallery'] as $item) {
            $stmt->execute([
                $item['id'] ?? null, $item['title'], $item['date'], $item['description'] ?? '',
                json_encode($item['images'] ?? []), $item['createdAt'] ?? date('c'), $item['updatedAt'] ?? null
            ]);
        }
    }
}

/* ==========================================================================
   HÍREK MODEL
   ========================================================================== */

function db_get_all_news(): array {
    $pdo = get_db_connection();
    return $pdo->query("SELECT * FROM news ORDER BY date DESC, id DESC")->fetchAll();
}

function db_get_latest_news(int $limit = 3): array {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM news ORDER BY date DESC, id DESC LIMIT ?");
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function db_get_news_by_id(int $id): ?array {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM news WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch();
    return $res ?: null;
}

function db_create_news(array $data): int {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("INSERT INTO news (title, date, type, text, image) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$data['title'], $data['date'], $data['type'], $data['text'], $data['image'] ?? '']);
    return (int)$pdo->lastInsertId();
}

function db_update_news(int $id, array $data): bool {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("UPDATE news SET title = ?, date = ?, type = ?, text = ?, image = ? WHERE id = ?");
    return $stmt->execute([$data['title'], $data['date'], $data['type'], $data['text'], $data['image'] ?? '', $id]);
}

function db_delete_news(int $id): bool {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("DELETE FROM news WHERE id = ?");
    return $stmt->execute([$id]);
}

function db_reset_news_defaults(): void {
    $pdo = get_db_connection();
    $pdo->exec("DELETE FROM news");
    $stmt = $pdo->prepare("INSERT INTO news (id, title, date, type, text, image) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([1, "Megszület a banda", "2026-04-26", "Bejelentés", "A zenekar hivatalosan megkezdi működését.", ""]);
    $stmt->execute([2, "Próbatermi bemelegítés", "2026-05-10", "Próba", "Elkészültek az első riffek és dobtémák.", ""]);
}

/* ==========================================================================
   KONCERTEK MODEL
   ========================================================================== */

function db_get_all_concerts(): array {
    $pdo = get_db_connection();
    return $pdo->query("SELECT * FROM concerts ORDER BY date ASC, time ASC, id ASC")->fetchAll();
}

function db_get_upcoming_concerts(): array {
    $all = db_get_all_concerts();
    $now = date('Y-m-d');
    return array_values(array_filter($all, function($c) use ($now) {
        return ($c['date'] >= $now);
    }));
}

function db_get_past_concerts(): array {
    $all = db_get_all_concerts();
    $now = date('Y-m-d');
    $past = array_values(array_filter($all, function($c) use ($now) {
        return ($c['date'] < $now);
    }));
    return array_reverse($past);
}

function db_get_concert_by_id(int $id): ?array {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM concerts WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch();
    return $res ?: null;
}

function db_create_concert(array $data): int {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("INSERT INTO concerts (
        title, date, time, place, address, status, entry,
        ticketOption, ticketLink, streamOption, streamLink,
        recordingOption, recordingLink
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['title'],
        $data['date'],
        $data['time'],
        $data['place'],
        $data['address'] ?? '',
        $data['status'] ?? 'open',
        $data['entry'] ?? '',
        $data['ticketOption'] ?? 'no-ticket',
        $data['ticketLink'] ?? '',
        $data['streamOption'] ?? 'no-stream',
        $data['streamLink'] ?? '',
        $data['recordingOption'] ?? 'no-recording',
        $data['recordingLink'] ?? ''
    ]);
    return (int)$pdo->lastInsertId();
}

function db_update_concert(int $id, array $data): bool {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("UPDATE concerts SET
        title = ?, date = ?, time = ?, place = ?, address = ?,
        status = ?, entry = ?, ticketOption = ?, ticketLink = ?,
        streamOption = ?, streamLink = ?, recordingOption = ?, recordingLink = ?
        WHERE id = ?");
    return $stmt->execute([
        $data['title'],
        $data['date'],
        $data['time'],
        $data['place'],
        $data['address'] ?? '',
        $data['status'] ?? 'open',
        $data['entry'] ?? '',
        $data['ticketOption'] ?? 'no-ticket',
        $data['ticketLink'] ?? '',
        $data['streamOption'] ?? 'no-stream',
        $data['streamLink'] ?? '',
        $data['recordingOption'] ?? 'no-recording',
        $data['recordingLink'] ?? '',
        $id
    ]);
}

function db_delete_concert(int $id): bool {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("DELETE FROM concerts WHERE id = ?");
    return $stmt->execute([$id]);
}

function db_clear_concerts(): bool {
    $pdo = get_db_connection();
    return $pdo->exec("DELETE FROM concerts") !== false;
}

function db_reset_concert_defaults(): void {
    $pdo = get_db_connection();
    $pdo->exec("DELETE FROM concerts");
    db_create_concert([
        'title' => 'UMSZKI évnyitó koncert',
        'date' => '2026-09-01',
        'time' => '10:30',
        'place' => 'UMSZKI',
        'address' => '1041 Budapest IV. ker., Görgey Artúr utca 26.',
        'status' => 'private',
        'entry' => 'Diák exkluzív',
        'ticketOption' => 'no-ticket',
        'ticketLink' => '',
        'streamOption' => 'no-stream',
        'streamLink' => '',
        'recordingOption' => 'no-recording',
        'recordingLink' => ''
    ]);
    db_create_concert([
        'title' => 'UMSZKI 100 koncert',
        'date' => '2026-09-05',
        'time' => '11:00',
        'place' => 'Budapest, UMSZKI',
        'address' => '1041 Budapest IV. ker., Görgey Artúr utca 26.',
        'status' => 'private',
        'entry' => 'UMSZKI-s diákoknak, volt diákoknak, tanároknak, volt tanároknak és hozzátartozóknak',
        'ticketOption' => 'no-ticket',
        'ticketLink' => '',
        'streamOption' => 'no-stream',
        'streamLink' => '',
        'recordingOption' => 'no-recording',
        'recordingLink' => ''
    ]);
}

/* ==========================================================================
   TIMELINE MODEL
   ========================================================================== */

function db_get_all_timeline(): array {
    $pdo = get_db_connection();
    return $pdo->query("SELECT * FROM timeline ORDER BY date ASC, id ASC")->fetchAll();
}

function db_get_timeline_by_id(int $id): ?array {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM timeline WHERE id = ?");
    $stmt->execute([$id]);
    $res = $stmt->fetch();
    return $res ?: null;
}

function db_create_timeline(array $data): int {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("INSERT INTO timeline (title, date, type, text, image) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$data['title'], $data['date'], $data['type'], $data['text'], $data['image'] ?? '']);
    return (int)$pdo->lastInsertId();
}

function db_update_timeline(int $id, array $data): bool {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("UPDATE timeline SET title = ?, date = ?, type = ?, text = ?, image = ? WHERE id = ?");
    return $stmt->execute([$data['title'], $data['date'], $data['type'], $data['text'], $data['image'] ?? '', $id]);
}

function db_delete_timeline(int $id): bool {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("DELETE FROM timeline WHERE id = ?");
    return $stmt->execute([$id]);
}

function db_reset_timeline_defaults(): void {
    $pdo = get_db_connection();
    $pdo->exec("DELETE FROM timeline");
    db_create_timeline([
        'title' => 'Az N/A elindul',
        'date' => '2026-05-01',
        'type' => 'Alakulás',
        'text' => 'A zenekar elkezdi összerakni a saját hangzását, az első ötletek és próbák alapján.',
        'image' => ''
    ]);
    db_create_timeline([
        'title' => 'Első komolyabb próbák',
        'date' => '2026-06-01',
        'type' => 'Próba',
        'text' => 'A banda elkezd teljes dalokon dolgozni, kialakulnak az első riffek, témák és koncertötletek.',
        'image' => ''
    ]);
}

/* ==========================================================================
   ÜZENETEK MODEL
   ========================================================================== */

function db_get_all_messages(): array {
    $pdo = get_db_connection();
    return $pdo->query("SELECT * FROM messages ORDER BY id DESC")->fetchAll();
}

function db_create_message(array $data): int {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("INSERT INTO messages (title, contact, location, text, createdAt, opened) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['title'],
        $data['contact'],
        $data['location'] ?? '',
        $data['text'],
        date('Y-m-d H:i:s'),
        0
    ]);
    return (int)$pdo->lastInsertId();
}

function db_mark_message_opened(int $id): bool {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("UPDATE messages SET opened = 1 WHERE id = ?");
    return $stmt->execute([$id]);
}

function db_delete_message(int $id): bool {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
    return $stmt->execute([$id]);
}

function db_delete_all_messages(): bool {
    $pdo = get_db_connection();
    return $pdo->exec("DELETE FROM messages") !== false;
}

/* ==========================================================================
   GALÉRIA MODEL
   ========================================================================== */

function db_get_all_gallery(): array {
    $pdo = get_db_connection();
    $rows = $pdo->query("SELECT * FROM gallery ORDER BY date DESC, id DESC")->fetchAll();
    foreach ($rows as &$row) {
        $row['images'] = json_decode($row['images'] ?? '[]', true) ?: [];
    }
    return $rows;
}

function db_get_gallery_by_id(int $id): ?array {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM gallery WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $row['images'] = json_decode($row['images'] ?? '[]', true) ?: [];
        return $row;
    }
    return null;
}

function db_create_gallery(array $data): int {
    $pdo = get_db_connection();
    $imagesJson = json_encode($data['images'] ?? []);
    $stmt = $pdo->prepare("INSERT INTO gallery (title, date, description, images, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['title'],
        $data['date'],
        $data['description'] ?? '',
        $imagesJson,
        date('Y-m-d H:i:s'),
        null
    ]);
    return (int)$pdo->lastInsertId();
}

function db_delete_gallery(int $id): bool {
    $album = db_get_gallery_by_id($id);
    if ($album && !empty($album['images'])) {
        foreach ($album['images'] as $imgUrl) {
            $filename = basename(parse_url($imgUrl, PHP_URL_PATH));
            $filePath = UPLOADS_DIR . DIRECTORY_SEPARATOR . $filename;
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("DELETE FROM gallery WHERE id = ?");
    return $stmt->execute([$id]);
}

function db_reset_gallery_defaults(): void {
    $pdo = get_db_connection();
    $pdo->exec("DELETE FROM gallery");
}

// -------------------------------------------------------------
// BEÁLLÍTÁSOK / SZÍNTÉMA MŰVELETEK
// -------------------------------------------------------------

function db_get_setting(string $key, string $default = ''): string {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = :key LIMIT 1");
    $stmt->execute([':key' => $key]);
    $val = $stmt->fetchColumn();
    return $val !== false ? (string)$val : $default;
}

function db_set_setting(string $key, string $value): void {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("INSERT INTO settings (key, value) VALUES (:key, :value) ON CONFLICT(key) DO UPDATE SET value = :value");
    $stmt->execute([':key' => $key, ':value' => $value]);
}

function db_get_theme_settings(): array {
    $default = [
        'accent' => '#ffffff',
        'bg_main' => '#060607',
        'bg_section' => '#0c0c0e',
        'bg_card' => '#141417',
        'text_main' => '#f4f4f6',
        'text_muted' => '#9c9ca4',
        'image_grayscale' => '100',
    ];

    $raw = db_get_setting('site_theme', '');
    if (!empty($raw)) {
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_merge($default, $decoded);
        }
    }
    return $default;
}

function db_save_theme_settings(array $settings): void {
    db_set_setting('site_theme', json_encode($settings, JSON_UNESCAPED_UNICODE));
}

/* ==========================================================================
   DISZKOGRÁFIA MODEL (LEMEZ / KISLEMEZ / SINGLE)
   ========================================================================== */

function seed_discography_if_needed(PDO $pdo): void {
    $stmt = $pdo->prepare("SELECT value FROM settings WHERE key = 'discography_seeded' LIMIT 1");
    $stmt->execute();
    if ($stmt->fetchColumn() !== false) {
        return;
    }

    $count = (int)$pdo->query("SELECT COUNT(*) FROM discography")->fetchColumn();
    if ($count === 0) {
        insert_default_discography_rows($pdo);
    }

    $ins = $pdo->prepare("INSERT INTO settings (key, value) VALUES ('discography_seeded', '1') ON CONFLICT(key) DO UPDATE SET value = '1'");
    $ins->execute();
}

function insert_default_discography_rows(PDO $pdo): void {
    $defaults = [
        [
            'title' => 'Maw of the Void',
            'category' => 'lemez',
            'release_date' => '2026-10-25',
            'cover_image' => '',
            'description' => 'Az N/A első teljes nagylemeze: 8 súlyos metal tétel a pesterzsébeti próbaterem nyers energiájából, drop hangolású riffekkel és sötét atmoszférával.',
            'tracks' => [
                ['title' => 'Intro – A Mélység Torka', 'duration' => '1:35', 'lyrics' => ' Instrumentális bevezető a sötétből építkező gitárgerinccel.'],
                ['title' => 'Gyújtsuk Fel', 'duration' => '3:48', 'lyrics' => "Szikra pattan a csend falán,\nNincs már fék az éjszakán.\nGyújtsuk fel a szürke eget,\nHallja meg mindenki a neved!"],
                ['title' => 'Szürke Beton', 'duration' => '4:05', 'lyrics' => "Panelárnyék, hideg falak,\nDe a mélyben a dobok szólnak."],
                ['title' => 'Árnyékok Közt', 'duration' => '4:30', 'lyrics' => ''],
                ['title' => 'Maw of the Void', 'duration' => '5:12', 'lyrics' => 'A lemez címadó dala: lassú, mázsás breakdown és feszes duplázó témák.'],
                ['title' => 'Töréspont', 'duration' => '3:39', 'lyrics' => ''],
                ['title' => 'Visszhang', 'duration' => '4:18', 'lyrics' => ''],
                ['title' => 'Utolsó Akkord', 'duration' => '4:50', 'lyrics' => '']
            ],
            'spotify_url' => '',
            'youtube_url' => '',
            'apple_url' => '',
            'other_url' => ''
        ],
        [
            'title' => 'Próbatermi Zaj EP',
            'category' => 'kislemez',
            'release_date' => '2026-07-15',
            'cover_image' => '',
            'description' => 'Négy dalos bemutatkozó kislemez (EP), amely a zenekar első közös hónapjainak legfeszesebb témáit foglalja össze.',
            'tracks' => [
                ['title' => 'Beállás a sötétben', 'duration' => '1:42', 'lyrics' => ''],
                ['title' => 'Gyújtsuk Fel (EP Verzió)', 'duration' => '3:48', 'lyrics' => ''],
                ['title' => 'Feszültség', 'duration' => '4:12', 'lyrics' => ''],
                ['title' => 'Nincs Visszaút', 'duration' => '3:55', 'lyrics' => '']
            ],
            'spotify_url' => '',
            'youtube_url' => '',
            'apple_url' => '',
            'other_url' => ''
        ],
        [
            'title' => 'Gyújtsuk Fel',
            'category' => 'single',
            'release_date' => '2026-05-20',
            'cover_image' => '',
            'description' => 'Az N/A legelső saját dala és single megjelenése, amely 2026 májusában született meg és kijelölte a zenekar saját irányát.',
            'tracks' => [
                ['title' => 'Gyújtsuk Fel', 'duration' => '3:48', 'lyrics' => "Szikra pattan a csend falán,\nNincs már fék az éjszakán.\nGyújtsuk fel a szürke eget,\nHallja meg mindenki a neved!"]
            ],
            'spotify_url' => '',
            'youtube_url' => '',
            'apple_url' => '',
            'other_url' => ''
        ]
    ];

    $stmt = $pdo->prepare("INSERT INTO discography (
        title, category, release_date, cover_image, description,
        tracks, spotify_url, youtube_url, apple_url, other_url
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

    foreach ($defaults as $item) {
        $stmt->execute([
            $item['title'],
            $item['category'],
            $item['release_date'],
            $item['cover_image'],
            $item['description'],
            json_encode($item['tracks'], JSON_UNESCAPED_UNICODE),
            $item['spotify_url'],
            $item['youtube_url'],
            $item['apple_url'],
            $item['other_url']
        ]);
    }
}

function get_discography_category_label(string $category): string {
    switch ($category) {
        case 'lemez':
            return 'Lemez (Album)';
        case 'kislemez':
            return 'Kislemez (EP)';
        case 'single':
            return 'Single';
        default:
            return 'Lemez';
    }
}

function db_get_all_discography(?string $category = null): array {
    $pdo = get_db_connection();
    if ($category !== null && in_array($category, ['lemez', 'kislemez', 'single'], true)) {
        $stmt = $pdo->prepare("SELECT * FROM discography WHERE category = ? ORDER BY release_date DESC, id DESC");
        $stmt->execute([$category]);
        $rows = $stmt->fetchAll();
    } else {
        $rows = $pdo->query("SELECT * FROM discography ORDER BY release_date DESC, id DESC")->fetchAll();
    }

    foreach ($rows as &$row) {
        $row['tracks'] = json_decode($row['tracks'] ?? '[]', true) ?: [];
    }
    return $rows;
}

function db_get_discography_by_id(int $id): ?array {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("SELECT * FROM discography WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if ($row) {
        $row['tracks'] = json_decode($row['tracks'] ?? '[]', true) ?: [];
        return $row;
    }
    return null;
}

function db_create_discography(array $data): int {
    $pdo = get_db_connection();
    $category = in_array($data['category'] ?? '', ['lemez', 'kislemez', 'single'], true) ? $data['category'] : 'lemez';
    $tracksJson = json_encode(array_values($data['tracks'] ?? []), JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare("INSERT INTO discography (
        title, category, release_date, cover_image, description,
        tracks, spotify_url, youtube_url, apple_url, other_url
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([
        $data['title'],
        $category,
        $data['release_date'],
        $data['cover_image'] ?? '',
        $data['description'] ?? '',
        $tracksJson,
        $data['spotify_url'] ?? '',
        $data['youtube_url'] ?? '',
        $data['apple_url'] ?? '',
        $data['other_url'] ?? ''
    ]);
    return (int)$pdo->lastInsertId();
}

function db_update_discography(int $id, array $data): bool {
    $pdo = get_db_connection();
    $category = in_array($data['category'] ?? '', ['lemez', 'kislemez', 'single'], true) ? $data['category'] : 'lemez';
    $tracksJson = json_encode(array_values($data['tracks'] ?? []), JSON_UNESCAPED_UNICODE);

    $stmt = $pdo->prepare("UPDATE discography SET
        title = ?, category = ?, release_date = ?, cover_image = ?, description = ?,
        tracks = ?, spotify_url = ?, youtube_url = ?, apple_url = ?, other_url = ?
        WHERE id = ?");
    return $stmt->execute([
        $data['title'],
        $category,
        $data['release_date'],
        $data['cover_image'] ?? '',
        $data['description'] ?? '',
        $tracksJson,
        $data['spotify_url'] ?? '',
        $data['youtube_url'] ?? '',
        $data['apple_url'] ?? '',
        $data['other_url'] ?? '',
        $id
    ]);
}

function db_delete_discography(int $id): bool {
    $pdo = get_db_connection();
    $stmt = $pdo->prepare("DELETE FROM discography WHERE id = ?");
    return $stmt->execute([$id]);
}

function db_reset_discography_defaults(): void {
    $pdo = get_db_connection();
    $pdo->exec("DELETE FROM discography");
    insert_default_discography_rows($pdo);
}

function calculate_release_duration(array $tracks): string {
    $totalSeconds = 0;
    foreach ($tracks as $track) {
        $dur = trim($track['duration'] ?? '');
        if (preg_match('/^(\d+):(\d{1,2})$/', $dur, $m)) {
            $totalSeconds += ((int)$m[1] * 60) + (int)$m[2];
        }
    }
    if ($totalSeconds <= 0) {
        return '';
    }
    $mins = (int)floor($totalSeconds / 60);
    $secs = $totalSeconds % 60;
    return sprintf('%d perc %02d mp', $mins, $secs);
}

require_once __DIR__ . '/content.php';

