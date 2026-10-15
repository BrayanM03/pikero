<footer class="footer">
    <div class="container-fluid">
        <div class="row text-muted">
            <div class="col-6 text-start">
                <p class="mb-0">
                    <a class="text-muted" href="<?php echo BASE_URL; ?>panel"><strong>Pikero</strong></a> &copy; <?php echo date('Y'); ?>
                </p>
            </div>
            <div class="col-6 text-end">
                <small><?php echo htmlspecialchars($_SESSION['ferreteria'] ?? ''); ?></small>
            </div>
        </div>
    </div>
</footer>
