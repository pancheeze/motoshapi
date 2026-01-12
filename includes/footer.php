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

    <div id="ms-chatbot" class="ms-chatbot" aria-live="polite">
        <button id="ms-chatbot-toggle" class="ms-chatbot-toggle" type="button" aria-expanded="false" aria-controls="ms-chatbot-panel" title="Chat">
            <i class="bi bi-chat-dots" style="font-size: 1.25rem;"></i>
        </button>

        <div id="ms-chatbot-panel" class="ms-chatbot-panel" hidden>
            <div class="ms-chatbot-header">
                <div class="ms-chatbot-title">Motoshapi Assistant</div>
                <button id="ms-chatbot-close" class="ms-chatbot-close" type="button" title="Close">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div id="ms-chatbot-messages" class="ms-chatbot-messages"></div>
            <form id="ms-chatbot-form" class="ms-chatbot-form" autocomplete="off">
                <input id="ms-chatbot-input" class="ms-chatbot-input" type="text" placeholder="Type a message..." />
                <button id="ms-chatbot-send" class="ms-chatbot-send" type="submit">Send</button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/dark-mode.js"></script>
    <script src="assets/js/chatbot-widget.js"></script>
</body>
</html>