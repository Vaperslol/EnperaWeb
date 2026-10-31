<?php
$pathPrefix = $pathPrefix ?? '';
$footerSubtitle = $footerSubtitle ?? 'N/A hivatalos weboldala';
?>
    <footer class="footer-custom text-center py-4">
        <div class="container">
            <p class="mb-1">
                &copy; 2026 N/A
            </p>
            <p class="mb-0">
                <?= htmlspecialchars($footerSubtitle, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
    </footer>

    <!-- Bootstrap 5 JavaScript Bundle -->
    <script crossorigin="anonymous" defer src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>

    <!-- UI témaváltó és animációs scriptek -->
    <script defer src="<?= $pathPrefix ?>javascript/mode-valtas.js"></script>
    <script defer src="<?= $pathPrefix ?>javascript/animaciok.js"></script>

    <?php if (!empty($extraScripts)): ?>
        <?php foreach ((array)$extraScripts as $script): ?>
            <script defer src="<?= $script ?>"></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>
