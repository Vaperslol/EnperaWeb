<?php
require_once __DIR__ . '/config.php';

/**
 * Szigorú ellenőrzés: aktív-e a PHP munkamenet és be van-e jelentkezve az adminisztrátor
 */
function is_admin_logged_in(): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Ellenőrzi, hogy a munkamenet jelenleg brute-force tiltás alatt áll-e (5 sikertelen kísérlet után 15 perc)
 */
function is_admin_locked_out(): bool {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }

    if (!empty($_SESSION['admin_lockout_until'])) {
        if (time() < (int)$_SESSION['admin_lockout_until']) {
            return true;
        }
        // Ha lejárt a 15 perces tiltás, nullázzuk a számlálót
        unset($_SESSION['admin_lockout_until']);
        $_SESSION['admin_login_attempts'] = 0;
    }

    return false;
}

/**
 * Hátralévő tiltási idő másodpercben
 */
function get_admin_lockout_remaining_seconds(): int {
    if (!is_admin_locked_out()) {
        return 0;
    }
    return max(0, (int)$_SESSION['admin_lockout_until'] - time());
}

/**
 * Hátralévő tiltási idő felfelé kerekített percekben
 */
function get_admin_lockout_remaining_minutes(): int {
    $seconds = get_admin_lockout_remaining_seconds();
    return $seconds > 0 ? (int)ceil($seconds / 60) : 0;
}

/**
 * Adminisztrátori beléptetés password_verify() ellenőrzéssel és brute-force védelemmel
 */
function admin_login(string $username, string $password): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Ha a munkamenet le van tiltva a túl sok sikertelen próbálkozás miatt
    if (is_admin_locked_out()) {
        return false;
    }

    $isUsernameValid = hash_equals(ADMIN_USERNAME, $username);
    $isPasswordValid = password_verify($password, ADMIN_PASSWORD_HASH);

    if ($isUsernameValid && $isPasswordValid) {
        // Sikeres belépéskor új munkamenet-azonosító generálása (Session Fixation védelem)
        session_regenerate_id(true);

        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;

        // Sikertelen próbálkozások számlálójának törlése
        unset($_SESSION['admin_login_attempts'], $_SESSION['admin_lockout_until']);
        return true;
    }

    // Sikertelen belépési kísérlet rögzítése a munkamenetben
    $attempts = (int)($_SESSION['admin_login_attempts'] ?? 0) + 1;
    $_SESSION['admin_login_attempts'] = $attempts;

    if ($attempts >= ADMIN_MAX_LOGIN_ATTEMPTS) {
        $_SESSION['admin_lockout_until'] = time() + ADMIN_LOCKOUT_SECONDS;
    }

    return false;
}

/**
 * Adminisztrátor kijelentkeztetése
 */
function admin_logout(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $_SESSION['admin_logged_in'] = false;
    unset($_SESSION['admin_logged_in'], $_SESSION['admin_username']);

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
}

/**
 * Jogosultság ellenőrzése minden védett adminisztrátori művelet előtt.
 * Jogosulatlan hozzáférés esetén azonnali átirányítás a főoldalra (index.php).
 */
function require_admin(string $redirectUrl = 'index.php'): void {
    if (!is_admin_logged_in()) {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
        header("Location: $redirectUrl");
        exit;
    }
}
