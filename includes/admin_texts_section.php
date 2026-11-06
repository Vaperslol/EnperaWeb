<?php
if (!function_exists('is_admin_logged_in') || !is_admin_logged_in()) {
    header('Location: index.php');
    exit;
}

$allDiscoReleases = db_get_all_discography();
$editDisco = $editDiscography ?? null;
$editTracks = ($editDisco && !empty($editDisco['tracks']))
    ? $editDisco['tracks']
    : [['title' => '', 'duration' => '', 'lyrics' => '']];
?>

<!-- DISZKOGRÁFIA (LEMEZ / KISLEMEZ / SINGLE) KEZELÉSE (OLDALSZÖVEGEK NEM SZERKESZTHETŐK) -->
<section class="admin-section-alt admin-page-panel" id="szovegek" data-admin-page="szovegek" data-admin-title="Diszkográfia Oldal (Kiadványok)">
    <div class="container">
        <div class="admin-section-header text-center mb-4">
            <h2 class="text-accent d-flex align-items-center justify-content-center gap-2">
                <svg class="line-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width: 28px; height: 28px;">
                    <circle cx="12" cy="12" r="10"/>
                    <circle cx="12" cy="12" r="3"/>
                </svg>
                <span>Diszkográfia Kezelése (Lemez / Kislemez / Single)</span>
            </h2>
            <p class="text-muted-custom mx-auto" style="max-width: 820px;">
                Itt adhatsz hozzá új <strong>Lemezeket (Album)</strong>, <strong>Kislemezeket (EP)</strong> és <strong>Single-öket</strong>, valamint kezelheted a dallistákat.
                <span class="d-block small mt-1 text-accent">🔒 A Diszkográfia oldal szövegei és címei védettek (nem szerkeszthetők).</span>
            </p>
        </div>

        <div class="row g-4 mb-2">
            <!-- BAL OSZLOP: ÚJ KIADVÁNY VAGY SZERKESZTÉS -->
            <div class="col-lg-6">
                <div class="admin-card h-100">
                    <h3 class="section-title text-accent mb-4 h4">
                        <?= $editDisco ? 'Kiadvány szerkesztése: ' . e($editDisco['title']) : 'Új kiadvány hozzáadása' ?>
                    </h3>

                    <form method="POST" action="<?= e(ADMIN_ENTRY_FILE) ?>" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="save_discography" />
                        <?php if ($editDisco): ?>
                            <input type="hidden" name="edit_id" value="<?= (int)$editDisco['id'] ?>" />
                            <input type="hidden" name="existing_cover" value="<?= e($editDisco['cover_image'] ?? '') ?>" />
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label" for="discoTitle">Kiadvány (Album / EP / Single) címe *</label>
                            <input type="text" class="form-control" id="discoTitle" name="discoTitle" required placeholder="Pl. Enpera" value="<?= e($editDisco['title'] ?? '') ?>" />
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="discoCategory">Kategória / Oldal *</label>
                                <select class="form-select" id="discoCategory" name="discoCategory" required>
                                    <?php
                                    $curCat = $editDisco['category'] ?? 'lemez';
                                    ?>
                                    <option value="lemez" <?= $curCat === 'lemez' ? 'selected' : '' ?>>Lemez (Nagylemez / Album)</option>
                                    <option value="kislemez" <?= $curCat === 'kislemez' ? 'selected' : '' ?>>Kislemez (EP)</option>
                                    <option value="single" <?= $curCat === 'single' ? 'selected' : '' ?>>Single</option>
                                </select>
                            </div>

                            <div class="col-md-6 mb-3">
                                <label class="form-label" for="discoDate">Megjelenés dátuma *</label>
                                <input type="date" class="form-control" id="discoDate" name="discoDate" required value="<?= e($editDisco['release_date'] ?? date('Y-m-d')) ?>" />
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="discoCover">Borítókép feltöltése (opcionális)</label>
                            <input type="file" class="form-control" id="discoCover" name="discoCover" accept="image/*" />
                            <?php if (!empty($editDisco['cover_image'])): ?>
                                <small class="text-muted-custom d-block mt-1">
                                    Jelenlegi borító: <code><?= e($editDisco['cover_image']) ?></code> (hagyd üresen, ha megtartod)
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label" for="discoDescription">Kiadvány leírása / háttértörténete</label>
                            <textarea class="form-control" id="discoDescription" name="discoDescription" rows="3" placeholder="Írd le röviden a lemez történetét, hangzását..."><?= e($editDisco['description'] ?? '') ?></textarea>
                        </div>

                        <!-- DALLISTA (TRACKLIST) SZERKESZTŐ -->
                        <div class="mb-3 p-3 rounded border border-secondary" style="background: var(--bg-main);">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <label class="form-label text-accent fw-bold mb-0">Dallista (Tracklist) a kiadványon</label>
                                <button type="button" class="btn btn-sm btn-outline-light" id="addDiscoTrackBtn">
                                    + Új dal hozzáadása
                                </button>
                            </div>

                            <div id="discoTracksEditorList">
                                <?php foreach ($editTracks as $tIdx => $tr): ?>
                                    <div class="disco-track-edit-row p-2 mb-2 rounded border border-secondary">
                                        <div class="row g-2 align-items-center">
                                            <div class="col-md-7">
                                                <input type="text" class="form-control form-control-sm" name="track_title[]" placeholder="Dal címe (pl. Gyújtsuk Fel)" value="<?= e($tr['title'] ?? '') ?>" />
                                            </div>
                                            <div class="col-md-3 col-8">
                                                <input type="text" class="form-control form-control-sm" name="track_duration[]" placeholder="Hossz (pl. 3:48)" value="<?= e($tr['duration'] ?? '') ?>" />
                                            </div>
                                            <div class="col-md-2 col-4 text-end">
                                                <button type="button" class="btn btn-sm btn-outline-danger w-100 remove-disco-track-btn" title="Dal eltávolítása">✕</button>
                                            </div>
                                        </div>
                                        <input type="hidden" name="track_lyrics[]" value="<?= e($tr['lyrics'] ?? '') ?>" />
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <small class="text-muted-custom d-block mt-2">
                                Megjegyzés: A diszkográfia szövegei és dalszövegei zárolva vannak, nem szerkeszthetők.
                            </small>
                        </div>

                        <!-- STREAMING LINKEK (OPCIONÁLIS) -->
                        <div class="mb-4 p-3 rounded border border-secondary" style="background: var(--bg-main);">
                            <label class="form-label text-accent fw-bold mb-2">Streaming / Hallgatási linkek (opcionális)</label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">Spotify URL</label>
                                    <input type="url" class="form-control form-control-sm" name="discoSpotify" placeholder="https://open.spotify.com/..." value="<?= e($editDisco['spotify_url'] ?? '') ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">YouTube URL</label>
                                    <input type="url" class="form-control form-control-sm" name="discoYoutube" placeholder="https://youtube.com/..." value="<?= e($editDisco['youtube_url'] ?? '') ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">Apple Music URL</label>
                                    <input type="url" class="form-control form-control-sm" name="discoApple" placeholder="https://music.apple.com/..." value="<?= e($editDisco['apple_url'] ?? '') ?>" />
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">Egyéb link (Bandcamp, SoundCloud stb.)</label>
                                    <input type="url" class="form-control form-control-sm" name="discoOther" placeholder="https://..." value="<?= e($editDisco['other_url'] ?? '') ?>" />
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-custom">
                                <?= $editDisco ? 'Kiadvány módosításainak mentése' : 'Kiadvány mentése a diszkográfiába' ?>
                            </button>
                            <?php if ($editDisco): ?>
                                <a href="<?= e(ADMIN_ENTRY_FILE) ?>#szovegek" class="btn btn-outline-secondary">Szerkesztés megszakítása</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- JOBB OSZLOP: MEGLÉVŐ KIADVÁNYOK LISTÁJA -->
            <div class="col-lg-6">
                <div class="admin-card h-100 d-flex flex-column justify-content-between">
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h3 class="section-title text-accent mb-0 h4">Feltöltött kiadványok (<?= count($allDiscoReleases) ?>)</h3>
                            <a href="bandatagok.php" class="btn btn-sm btn-outline-light" target="_blank">Diszkográfia megnyitása ↗</a>
                        </div>

                        <div class="admin-list">
                            <?php if (empty($allDiscoReleases)): ?>
                                <p class="text-muted-custom">Még nincs feltöltött kiadvány a diszkográfiában.</p>
                            <?php else: ?>
                                <?php foreach ($allDiscoReleases as $rel):
                                    $catLabel = get_discography_category_label($rel['category'] ?? 'lemez');
                                    $tCount = count($rel['tracks'] ?? []);
                                ?>
                                    <div class="admin-item p-3 mb-2 border rounded border-secondary">
                                        <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                                            <div>
                                                <span class="badge bg-secondary mb-1"><?= e($catLabel) ?></span>
                                                <h4 class="h5 text-white mb-1"><?= e($rel['title']) ?></h4>
                                            </div>
                                            <small class="text-muted-custom"><?= format_hungarian_date($rel['release_date']) ?> • <?= $tCount ?> dal</small>
                                        </div>

                                        <?php if (!empty($rel['description'])): ?>
                                            <p class="small text-muted-custom mt-1 mb-2"><?= e($rel['description']) ?></p>
                                        <?php endif; ?>

                                        <div class="d-flex gap-2 flex-wrap mt-2">
                                            <a href="bandatagok.php?kiadvany=<?= (int)$rel['id'] ?>" target="_blank" class="btn btn-sm btn-outline-light">Megtekintés</a>
                                            <a href="<?= e(ADMIN_ENTRY_FILE) ?>?edit_discography=<?= (int)$rel['id'] ?>#szovegek" class="btn btn-sm btn-custom">Szerkesztés</a>
                                            <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=delete_discography&id=<?= (int)$rel['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Biztosan törlöd ezt a kiadványt a diszkográfiából?')">Törlés</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-top border-secondary">
                        <a href="<?= e(ADMIN_ENTRY_FILE) ?>?action=reset_discography" class="btn btn-outline-secondary w-100" onclick="return confirm('Visszaállítod az alapértelmezett diszkográfia kiadványokat?')">
                            Alapértelmezett kiadványok visszaállítása
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const addTrackBtn = document.getElementById("addDiscoTrackBtn");
    const tracksList = document.getElementById("discoTracksEditorList");

    function bindRemoveButtons() {
        if (!tracksList) return;
        tracksList.querySelectorAll(".remove-disco-track-btn").forEach(function (btn) {
            btn.onclick = function () {
                const rows = tracksList.querySelectorAll(".disco-track-edit-row");
                if (rows.length > 1) {
                    this.closest(".disco-track-edit-row").remove();
                } else {
                    const row = this.closest(".disco-track-edit-row");
                    row.querySelectorAll("input").forEach(el => { el.value = ""; });
                }
            };
        });
    }

    if (addTrackBtn && tracksList) {
        addTrackBtn.addEventListener("click", function () {
            const div = document.createElement("div");
            div.className = "disco-track-edit-row p-2 mb-2 rounded border border-secondary";
            div.innerHTML =
                '<div class="row g-2 align-items-center">' +
                    '<div class="col-md-7">' +
                        '<input type="text" class="form-control form-control-sm" name="track_title[]" placeholder="Dal címe (pl. Új dal)" value="" />' +
                    '</div>' +
                    '<div class="col-md-3 col-8">' +
                        '<input type="text" class="form-control form-control-sm" name="track_duration[]" placeholder="Hossz (pl. 3:45)" value="" />' +
                    '</div>' +
                    '<div class="col-md-2 col-4 text-end">' +
                        '<button type="button" class="btn btn-sm btn-outline-danger w-100 remove-disco-track-btn" title="Dal eltávolítása">✕</button>' +
                    '</div>' +
                '</div>' +
                '<input type="hidden" name="track_lyrics[]" value="" />';
            tracksList.appendChild(div);
            bindRemoveButtons();
        });
        bindRemoveButtons();
    }
});
</script>
