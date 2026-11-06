<?php
require_once __DIR__ . '/db.php';

/**
 * A Diszkográfia oldal (bandatagok.php / diszkografia.php) fix, nem szerkeszthető szövegei
 */
function get_default_site_texts(): array {
    return [
        'discography_hero_eyebrow' => 'Nagylemezek, kislemezek és single megjelenések',
        'discography_hero_title' => 'Diszkográfia',
        'discography_hero_subtitle' => 'Itt találod az N/A összes eddigi kiadványát. Válassz a Lemezek, Kislemezek (EP) és Single-ök oldalai között, és nyisd meg bármelyik albumot a teljes dallistához, dalszövegekhez és részletekhez.',

        'discography_lemez_title' => 'Lemezek (Nagylemezek)',
        'discography_lemez_subtitle' => 'Teljes hosszúságú stúdióalbumok és nagylemezek.',

        'discography_kislemez_title' => 'Kislemezek (EP)',
        'discography_kislemez_subtitle' => 'Többdalos kislemezek, próbatermi és stúdió EP kiadványok.',

        'discography_single_title' => 'Single megjelenések',
        'discography_single_subtitle' => 'Önálló dalok, előfutárok és kiemelt single megjelenések.',

        'discography_outro_title' => 'Saját dalok, saját hangzás',
        'discography_outro_p1' => 'Minden kiadott lemezünk, EP-nk és single-ünk egy-egy újabb állomás az N/A történetében – a pesterzsébeti próbatermi riffektől a színpadi megszólalásig.',
        'discography_outro_p2' => 'Kattints bármelyik borítóra vagy a „Kiadvány megnyitása” gombra a dalok, játékidők és dalszövegek megtekintéséhez.',
        'discography_outro_btn1' => 'Következő koncertek',
        'discography_outro_btn2' => 'Kapcsolat & Megkeresés',
    ];
}

/**
 * Diszkográfia oldalszövegek lekérése (fix, nem szerkeszthető)
 */
function db_get_site_texts(bool $forceRefresh = false): array {
    return get_default_site_texts();
}

/**
 * Egyetlen szövegmező lekérése kulcs alapján
 */
function site_text(string $key, ?string $fallback = null): string {
    $texts = get_default_site_texts();
    if (array_key_exists($key, $texts)) {
        return (string)$texts[$key];
    }
    return $fallback ?? '';
}
