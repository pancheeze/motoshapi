        </main>
        <?php
        $renderGlobalFooter = $renderGlobalFooter ?? true;
        if ($renderGlobalFooter):
        ?>
        <footer class="modern-footer">
            <div class="modern-container">
                <div class="d-flex flex-column flex-md-row align-items-center justify-content-center gap-3">
                    <div style="color: var(--text-secondary); font-size: 0.875rem;">&copy; <?php echo date('Y'); ?> Motoshapi. All rights reserved.</div>
                </div>
            </div>
        </footer>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/motoshapi/assets/js/dark-mode.js"></script>
</body>
</html>