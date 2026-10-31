<?php
$pageTitle = 'Kapcsolat - N/A';
$pageDescription = 'Kapcsolatfelvétel az N/A zenekarral fellépés, megkeresés vagy kérdés esetén.';
$activeNav = 'kapcsolat';
$pathPrefix = '';
$extraCss = ['kapcsolat.css'];

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

// Űrlap feldolgozása POST kérés esetén
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $title = trim($_POST['messageTitle'] ?? '');
    $text = trim($_POST['messageText'] ?? '');
    $contact = trim($_POST['messageContact'] ?? '');
    $location = trim($_POST['messageLocation'] ?? '');

    // Űrlapadatok ideiglenes megőrzése hiba esetére
    $_SESSION['contact_old'] = [
        'title' => $title,
        'text' => $text,
        'contact' => $contact,
        'location' => $location
    ];

    $captchaCheck = verify_contact_captcha($_POST);

    if (empty($title) || empty($text) || empty($contact)) {
        set_flash('error', 'A cím, az üzenet és az elérhetőség megadása kötelező!');
    } elseif (!$captchaCheck['valid']) {
        set_flash('error', $captchaCheck['error']);
    } else {
        try {
            db_create_message([
                'title' => $title,
                'text' => $text,
                'contact' => $contact,
                'location' => $location
            ]);
            unset($_SESSION['contact_old']);
            set_flash('success', 'Köszönjük! Az üzenetedet sikeresen elküldtük a bandának.');
        } catch (Exception $e) {
            set_flash('error', 'Hiba történt az üzenet küldése során. Kérjük próbáld újra később!');
        }
    }

    // Post/Redirect/Get minta
    session_write_close();
    header('Location: kapcsolat.php#uzenetkuldes');
    exit;
}

$flash = get_flash();
$oldInput = $_SESSION['contact_old'] ?? [];
unset($_SESSION['contact_old']);
$captchaData = generate_contact_captcha();
$useGoogleRecaptcha = (RECAPTCHA_SITE_KEY !== '' && RECAPTCHA_SECRET_KEY !== '');

require_once __DIR__ . '/includes/header.php';
?>

<main class="main-bg">
    <!-- HERO -->
    <section class="kapcsolat-hero">
        <div class="container text-center">
            <p class="text-accent fw-bold text-uppercase mb-2">
                Írj nekünk, kövess minket
            </p>
            <h1 class="kapcsolat-title">
                Kapcsolat
            </h1>
            <p class="lead text-muted-custom mt-3 mx-auto" style="max-width: 850px;">
                Itt találod az N/A elérhetőségeit. Keress minket koncert, közös fellépés, kérdés vagy bármilyen zenekari megkeresés miatt.
            </p>
        </div>
    </section>

    <!-- KAPCSOLATI KÁRTYÁK -->
    <section class="py-5">
        <div class="container py-4">
            <div class="text-center mb-5">
                <h2 class="section-title text-accent">
                    Elérhetőségek
                </h2>
                <p class="text-muted-custom">
                    A közösségi oldalakon követheted a frissítéseket, emailben pedig közvetlenül is megkereshetsz minket.
                </p>
            </div>
            <div class="row g-4 justify-content-center">
                <!-- FACEBOOK -->
                <div class="col-xl-3 col-md-6">
                    <a class="contact-card" href="https://facebook.com/peldanev" target="_blank" rel="noopener noreferrer">
                        <div class="contact-icon">
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M14 8.5h2.5V5h-3c-3.1 0-4.8 1.8-4.8 4.7V12H6v3.7h2.7V22h4.1v-6.3h3.1l.6-3.7h-3.7V10c0-1 .4-1.5 1.2-1.5Z"></path>
                            </svg>
                        </div>
                        <div class="contact-content">
                            <h3>Facebook</h3>
                            <p>@naband</p>
                        </div>
                    </a>
                </div>

                <!-- INSTAGRAM -->
                <div class="col-xl-3 col-md-6">
                    <a class="contact-card" href="https://www.instagram.com/naband/" target="_blank" rel="noopener noreferrer">
                        <div class="contact-icon">
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M7.5 2h9A5.5 5.5 0 0 1 22 7.5v9a5.5 5.5 0 0 1-5.5 5.5h-9A5.5 5.5 0 0 1 2 16.5v-9A5.5 5.5 0 0 1 7.5 2Zm0 2A3.5 3.5 0 0 0 4 7.5v9A3.5 3.5 0 0 0 7.5 20h9a3.5 3.5 0 0 0 3.5-3.5v-9A3.5 3.5 0 0 0 16.5 4h-9Zm4.5 3.2A4.8 4.8 0 1 1 7.2 12 4.8 4.8 0 0 1 12 7.2Zm0 2A2.8 2.8 0 1 0 14.8 12 2.8 2.8 0 0 0 12 9.2Zm5-2.4a1.2 1.2 0 1 1-1.2 1.2A1.2 1.2 0 0 1 17 6.8Z"></path>
                            </svg>
                        </div>
                        <div class="contact-content">
                            <h3>Instagram</h3>
                            <p>@naband</p>
                        </div>
                    </a>
                </div>

                <!-- TIKTOK -->
                <div class="col-xl-3 col-md-6">
                    <a class="contact-card" href="https://tiktok.com/@naband" target="_blank" rel="noopener noreferrer">
                        <div class="contact-icon">
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M16.6 2c.3 2.4 1.7 4 4 4.2v3.6a7.5 7.5 0 0 1-4-1.2v6.9c0 4.2-2.8 6.5-6.2 6.5A6.2 6.2 0 0 1 4 15.8c0-3.7 2.8-6.2 6.5-6.2.4 0 .8 0 1.2.1v3.8a4 4 0 0 0-1.2-.2 2.3 2.3 0 1 0 2.3 2.3V2h3.8Z"></path>
                            </svg>
                        </div>
                        <div class="contact-content">
                            <h3>TikTok</h3>
                            <p>@naband</p>
                        </div>
                    </a>
                </div>

                <!-- EMAIL -->
                <div class="col-xl-3 col-md-6">
                    <a class="contact-card" href="mailto:naband2026@gmail.com">
                        <div class="contact-icon">
                            <svg aria-hidden="true" viewBox="0 0 24 24">
                                <path d="M4 5h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm8 8.2L4.4 7H4v.4l8 6.5 8-6.5V7h-.4L12 13.2Z"></path>
                            </svg>
                        </div>
                        <div class="contact-content">
                            <h3>Email</h3>
                            <p>naband2026@gmail.com</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- MEGKERESÉS RÉSZ -->
    <section class="py-5 section-bg">
        <div class="container py-4">
            <div class="row align-items-center g-5">
                <div class="col-lg-6">
                    <h2 class="section-title text-accent">
                        Megkeresés
                    </h2>
                    <p class="text-muted-custom mt-3">
                        Koncertszervezés, közös fellépés, interjú, fotózás vagy bármilyen zenekari ügy miatt nyugodtan írj nekünk.
                    </p>
                    <p class="text-muted-custom">
                        A leggyorsabb elérés jelenleg az email és az Instagram, de a többi felületet is érdemes figyelni.
                    </p>
                    <a class="btn btn-custom btn-lg mt-2" href="mailto:naband2026@gmail.com">
                        Email küldése
                    </a>
                </div>
                <div class="col-lg-6">
                    <div class="contact-panel">
                        <h3>Mit írj az üzenetbe?</h3>
                        <ul>
                            <li><span>Koncert:</span> időpont, helyszín, technikai infók</li>
                            <li><span>Megkeresés:</span> miről lenne szó pontosan</li>
                            <li><span>Kapcsolat:</span> név és visszaírási lehetőség</li>
                            <li><span>Egyéb:</span> minden, ami a bandához kapcsolódik</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ÜZENETKÜLDŐ FORM -->
    <section class="py-5" id="uzenetkuldes">
        <div class="container py-4">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="contact-form-card">
                        <div class="text-center mb-4">
                            <p class="text-accent fw-bold text-uppercase mb-2">
                                Közvetlen üzenet
                            </p>
                            <h2 class="section-title">
                                Írj nekünk
                            </h2>
                            <p class="text-muted-custom">
                                Koncert, fellépés, kérdés vagy bármilyen megkeresés jöhet ide.
                            </p>
                        </div>

                        <?php if ($flash): ?>
                            <div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> mb-4 text-center">
                                <?= e($flash['message']) ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="kapcsolat.php" id="contactMessageForm">
                            <!-- Honeypot mező spam robotok ellen (emberek számára rejtett) -->
                            <div style="position: absolute; left: -9999px; opacity: 0; pointer-events: none;" aria-hidden="true">
                                <label for="website_hp">Weboldal (hagyd üresen)</label>
                                <input type="text" id="website_hp" name="website_hp" tabindex="-1" autocomplete="off" value="" />
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="messageTitle">
                                    Üzenet címe *
                                </label>
                                <input class="form-control" id="messageTitle" name="messageTitle" placeholder="Pl. Koncertmegkeresés" required type="text" value="<?= e($oldInput['title'] ?? '') ?>" />
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="messageText">
                                    Üzenet *
                                </label>
                                <textarea class="form-control" id="messageText" name="messageText" placeholder="Írd le, miről lenne szó..." required rows="6"><?= e($oldInput['text'] ?? '') ?></textarea>
                            </div>

                            <div class="mb-3">
                                <label class="form-label" for="messageContact">
                                    Elérhetőség *
                                </label>
                                <input class="form-control" id="messageContact" name="messageContact" placeholder="Email, Instagram, telefon vagy bármi, ahol elérünk" required type="text" value="<?= e($oldInput['contact'] ?? '') ?>" />
                            </div>

                            <div class="mb-4">
                                <label class="form-label" for="messageLocation">
                                    Helyszín / város (opcionális)
                                </label>
                                <input class="form-control" id="messageLocation" name="messageLocation" placeholder="Pl. Budapest, Pesterzsébet" type="text" value="<?= e($oldInput['location'] ?? '') ?>" />
                            </div>

                            <!-- RECAPTCHA ELLENŐRZÉS -->
                            <div class="mb-4">
                                <?php if ($useGoogleRecaptcha): ?>
                                    <div class="d-flex justify-content-center">
                                        <div class="g-recaptcha" data-sitekey="<?= e(RECAPTCHA_SITE_KEY) ?>" data-theme="dark"></div>
                                    </div>
                                    <script src="https://www.google.com/recaptcha/api.js?hl=hu" async defer></script>
                                <?php else: ?>
                                    <input type="hidden" name="captcha_token" value="<?= e($captchaData['token']) ?>" />
                                    <input type="hidden" name="recaptcha_checked" id="recaptchaCheckedInput" value="0" />

                                    <div class="recaptcha-box" id="customRecaptchaBox">
                                        <div class="recaptcha-main-row">
                                            <div class="recaptcha-left" id="recaptchaTriggerArea" role="checkbox" aria-checked="false" tabindex="0">
                                                <div class="recaptcha-checkbox" id="recaptchaCheckboxVisual">
                                                    <div class="recaptcha-spinner d-none" id="recaptchaSpinner"></div>
                                                    <svg class="recaptcha-checkmark d-none" id="recaptchaCheckmark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">
                                                        <polyline points="20 6 9 17 4 12"></polyline>
                                                    </svg>
                                                </div>
                                                <span class="recaptcha-label" id="recaptchaStatusLabel">Nem vagyok robot</span>
                                            </div>

                                            <div class="recaptcha-brand">
                                                <svg class="recaptcha-logo-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                                    <path d="m9 12 2 2 4-4"/>
                                                </svg>
                                                <span class="recaptcha-brand-name">reCAPTCHA</span>
                                                <small class="recaptcha-brand-terms">Védelem • Feltételek</small>
                                            </div>
                                        </div>

                                        <!-- Kihívás panel (kattintásra nyílik meg) -->
                                        <div class="recaptcha-challenge-panel d-none" id="recaptchaChallengePanel">
                                            <label class="form-label small mb-2 d-block text-accent" for="captchaAnswerInput">
                                                Biztonsági ellenőrzés: mennyi <strong><?= e($captchaData['question']) ?></strong>?
                                            </label>
                                            <div class="d-flex gap-2 align-items-center">
                                                <input
                                                    type="number"
                                                    inputmode="numeric"
                                                    class="form-control form-control-sm recaptcha-answer-input"
                                                    id="captchaAnswerInput"
                                                    name="captcha_answer"
                                                    placeholder="Írd be az eredményt..."
                                                    required
                                                    autocomplete="off"
                                                />
                                                <button type="button" class="btn btn-sm btn-custom px-3" id="verifyCaptchaBtn">
                                                    Igazolás
                                                </button>
                                            </div>
                                            <small class="recaptcha-feedback d-none mt-2" id="recaptchaClientError"></small>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <button class="btn btn-custom w-100" type="submit">
                                Üzenet küldése
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>
</main>

<?php if (!$useGoogleRecaptcha): ?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const expectedSum = <?= (int)$captchaData['answer'] ?>;
    const triggerArea = document.getElementById("recaptchaTriggerArea");
    const checkboxVisual = document.getElementById("recaptchaCheckboxVisual");
    const spinner = document.getElementById("recaptchaSpinner");
    const checkmark = document.getElementById("recaptchaCheckmark");
    const statusLabel = document.getElementById("recaptchaStatusLabel");
    const challengePanel = document.getElementById("recaptchaChallengePanel");
    const answerInput = document.getElementById("captchaAnswerInput");
    const verifyBtn = document.getElementById("verifyCaptchaBtn");
    const checkedInput = document.getElementById("recaptchaCheckedInput");
    const recaptchaBox = document.getElementById("customRecaptchaBox");
    const clientError = document.getElementById("recaptchaClientError");
    const form = document.getElementById("contactMessageForm");

    let isVerified = false;
    let isOpening = false;

    function openChallenge() {
        if (isVerified || isOpening) return;
        isOpening = true;
        checkboxVisual.classList.add("loading");
        spinner.classList.remove("d-none");

        setTimeout(function () {
            spinner.classList.add("d-none");
            checkboxVisual.classList.remove("loading");
            challengePanel.classList.remove("d-none");
            if (answerInput) {
                answerInput.focus();
            }
            isOpening = false;
        }, 400);
    }

    function checkChallengeAnswer(showErrorOnFail) {
        if (!answerInput) return false;
        const val = parseInt(answerInput.value.trim(), 10);
        if (val === expectedSum) {
            isVerified = true;
            checkedInput.value = "1";
            triggerArea.setAttribute("aria-checked", "true");
            checkboxVisual.classList.add("verified");
            checkmark.classList.remove("d-none");
            statusLabel.textContent = "Nem vagyok robot (Igazolva)";
            challengePanel.classList.add("d-none");
            recaptchaBox.classList.remove("recaptcha-error");
            recaptchaBox.classList.add("recaptcha-verified");
            if (clientError) clientError.classList.add("d-none");
            return true;
        } else if (showErrorOnFail) {
            if (clientError) {
                clientError.textContent = "Hibás eredmény, kérjük számold ki újra!";
                clientError.classList.remove("d-none");
            }
            recaptchaBox.classList.add("recaptcha-error");
            answerInput.focus();
        }
        return false;
    }

    if (triggerArea) {
        triggerArea.addEventListener("click", openChallenge);
        triggerArea.addEventListener("keydown", function (e) {
            if (e.key === "Enter" || e.key === " ") {
                e.preventDefault();
                openChallenge();
            }
        });
    }

    if (verifyBtn) {
        verifyBtn.addEventListener("click", function () {
            checkChallengeAnswer(true);
        });
    }

    if (answerInput) {
        answerInput.addEventListener("input", function () {
            if (clientError) clientError.classList.add("d-none");
            checkChallengeAnswer(false);
        });
        answerInput.addEventListener("keydown", function (e) {
            if (e.key === "Enter") {
                e.preventDefault();
                checkChallengeAnswer(true);
            }
        });
    }

    if (form) {
        form.addEventListener("submit", function (e) {
            if (!isVerified && !checkChallengeAnswer(true)) {
                e.preventDefault();
                recaptchaBox.classList.add("recaptcha-error");
                if (challengePanel.classList.contains("d-none")) {
                    openChallenge();
                } else if (answerInput) {
                    answerInput.focus();
                }
            }
        });
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
