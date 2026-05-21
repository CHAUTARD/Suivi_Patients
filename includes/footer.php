<?php
// footer.php - Fermeture du layout + chargement des scripts JS
// Variables optionnelles :
//   $extraScripts (array) : noms de fichiers dans assets/js/
//   $cdnScripts   (array) : URLs complètes chargées avant jQuery (ex. Chart.js CDN)
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

<?php if (!empty($cdnScripts)): ?>
    <?php foreach ($cdnScripts as $cdnSrc): ?>
        <script src="<?= htmlspecialchars($cdnSrc) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
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
