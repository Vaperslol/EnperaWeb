# N/A – Teljes PHP Alapú Weboldal

A weboldal **teljes PHP alapú weboldalként** fut natív PHP és SQLite segítségével.

---

## 🚀 Hogyan indítható el?

### 1. lehetőség: Egy kattintásos indítás (Ajánlott)
Kattints duplán a gyökérkönyvtárban lévő **`start_szerver.bat`** fájlra!
- Ez elindítja a helyi PHP szervert a gépen lévő XAMPP PHP-val a `http://localhost:8000` címen, és automatikusan megnyitja a böngészőt.
- Nincs szükség Node.js-re vagy külső függőségekre!

### 2. lehetőség: XAMPP Apache alatt
1. Másold be a projekt mappát a `C:\xampp\htdocs\` könyvtárba.
2. Indítsd el az Apache-ot a XAMPP Control Panelben.
3. Nyisd meg a böngészőben a megfelelő helyi címet.

---

## 📁 Felépítés és mappa-szerkezet

```text
├── index.php                    # Főoldal (zenekar bemutatás, legfrissebb hírek)
├── bandatagok.php               # Bandatagok & Hangszerek (mind az 5 tag gitárjai, pedáljai, dobjai egy helyen)
├── koncertek.php                # Koncertek oldal (közelgő/régi koncertek, térkép, visszaszámláló)
├── hirek.php                    # Hírek oldal (szerveroldali adatbázis renderelés)
├── galeria.php                  # Galéria (albumok, képmegjelenítő)
├── timeline.php                 # Idővonal oldal (események időrendben)
├── kapcsolat.php                # Kapcsolat (natív PHP űrlapfeldolgozás POST-tal)
├── na-zenekar-kapu.php          # Rejtett, védett PHP Admin vezérlőpult és színválasztó
├── admin.php                    # Letiltott végpont (azonnali átirányítás az index.php-ra)
│
├── includes/                    # Újrafelhasználható PHP komponensek
│   ├── config.php               # Alapbeállítások, admin jelszó hash konfig, munkamenet
│   ├── header.php               # Közös HTML fejléc, meta tagek és navigáció
│   ├── footer.php               # Közös lábléc és záró elemek
│   ├── db.php                   # SQLite PDO kapcsolat és CRUD adatbázis modellek
│   └── auth.php                 # Admin munkamenet, password_verify és brute-force védelem
│
├── database/                    # Adatbázis mappa
│   ├── database.sqlite          # SQLite adatbázis (hírek, koncertek, galéria, üzenetek, timeline, témabeállítások)
│   └── database.json            # Kezdeti biztonsági mentés / magadatok
│
├── uploads/                     # Feltöltött képek (hírekhez, galériához, timeline-hoz)
├── stiluslapok/                 # CSS stíluslapok
├── kepek/                       # Logók, ikonok, grafikák
├── javascript/                  # Kliensoldali UI scriptek (sötét/világos mód, animációk)
└── start_szerver.bat            # Egy kattintásos PHP webszerver indító
```

---

## 🔐 Rejtett Admin felület és Biztonság

- **Titkos belépési cím:** `http://localhost:8000/na-zenekar-kapu.php` *(a sima `/admin.php` letiltva, automatikusan a főoldalra irányít)*
- **Felhasználónév:** `admin` *(módosítható az `includes/config.php` fájlban)*
- **Alapértelmezett jelszó:** `admin123` *(az `includes/config.php` fájlban `password_hash()`-sel hashelve, könnyen cserélhető)*
- **Brute-Force védelem:** 5 sikertelen jelszópróbálkozás után a munkamenet 15 percre letiltja az újabb belépési kísérleteket, sikeres belépéskor pedig `session_regenerate_id(true)` fut le.

Az admin felületen keresztül elérhető funkciók:
1. **Színek és Megjelenés:** központi színválasztóval a teljes weboldal összes kiemelő színe, ragyogása, gombjai és átmenetei módosíthatók, 1-kattintásos gyors témákkal és élő valós idejű előnézettel.
2. **Koncertek:** új koncert hozzáadása, szerkesztése, törlése.
3. **Hírek:** új bejegyzések képfeltöltéssel, szerkesztés, törlés.
4. **Galéria:** új albumok létrehozása, több kép egyidejű feltöltése a szerverre.
5. **Timeline:** új események hozzáadása képfeltöltéssel.
6. **Üzenetek:** a kapcsolat oldalon beküldött látogatói megkeresések olvasása, olvasottként jelölése és törlése.
