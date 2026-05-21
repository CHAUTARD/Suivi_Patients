<?php
// footer.php - Fermeture du layout + chargement des scripts JS
// Variable optionnelle : $extraScripts (array de noms de fichiers dans assets/js/)
?>
</main><!-- /container-fluid -->

<footer class="footer mt-auto py-3 bg-light border-top">
    <div class="container-fluid text-center text-muted small">
        <?= htmlspecialchars(APP_NAME) ?> &mdash; <?= htmlspecialchars(APP_SUBTITLE) ?>
        &copy; <?= date('Y') ?>
        <span class="mx-2">&bull;</span>
        Développé par Patrick CH.
        <span class="ms-2 text-muted opacity-75">v<?= htmlspecialchars(APP_VERSION) ?></span>
    </div>
</footer>

<script src="<?= SITE_ROOT ?>/assets/js/jquery.min.js"></script>
<script src="<?= SITE_ROOT ?>/assets/js/bootstrap.bundle.min.js"></script>
<script src="<?= SITE_ROOT ?>/assets/js/flatpickr.min.js"></script>
<script src="<?= SITE_ROOT ?>/assets/js/flatpickr.fr.js"></script>
<script src="<?= SITE_ROOT ?>/assets/js/app.js"></script>
<?php if (!empty($extraScripts)): ?>
    <?php foreach ($extraScripts as $script): ?>
        <script src="<?= SITE_ROOT ?>/assets/js/<?= htmlspecialchars($script) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
