<div class="chatbot-widget chatbot-mode-popup" style="<?php echo esc_attr( $widget_style ); ?>" data-chatbot-mode="popup" data-chatbot-id="<?php echo esc_attr( $instance ); ?>">
    <button class="chatbot-fab" type="button" aria-label="Open chat">
        <span class="chatbot-fab-icon">💬</span>
    </button>

    <div class="chatbot-popup-panel" role="dialog" aria-modal="false" aria-label="Chat assistant">
        <div class="chatbot-header">
            <div class="chatbot-header-brand">
                <span class="chatbot-brand-avatar">AI</span>
                <div class="chatbot-brand-copy">
                    <span class="chatbot-brand-title"><?php echo esc_html( $bot_name ); ?></span>
                    <span class="chatbot-brand-subtitle">Instant replies, voice ready</span>
                </div>
            </div>
            <button class="chatbot-close-btn" type="button" aria-label="Close chat">×</button>
        </div>
        <div class="chatbot-messages"></div>
        <div class="chatbot-input-area">
            <div class="chatbot-input-tools">
                <button class="chatbot-voice-btn" type="button" aria-label="Start voice input">🎙</button>
                <button class="chatbot-replay-btn" type="button" aria-label="Replay latest response">🔊</button>
                <span class="chatbot-voice-status" aria-live="polite"></span>
            </div>
            <input type="text" class="chatbot-input" placeholder="Type a message or command..." autocomplete="off">
            <button class="chatbot-send-btn" type="button" aria-label="Send message">➤</button>
        </div>
    </div>
</div>
