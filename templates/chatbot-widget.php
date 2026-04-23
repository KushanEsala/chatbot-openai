<div class="chatbot-widget chatbot-mode-inline" style="<?php echo esc_attr( $widget_style ); ?>" data-chatbot-mode="inline" data-chatbot-id="<?php echo esc_attr( $instance ); ?>">
    <div class="chatbot-container">
        <div class="chatbot-header">
            <div class="chatbot-header-brand">
                <span class="chatbot-brand-avatar">AI</span>
                <div class="chatbot-brand-copy">
                    <span class="chatbot-brand-title"><?php echo esc_html( $bot_name ); ?></span>
                    <span class="chatbot-brand-subtitle">Instant replies, voice ready</span>
                </div>
            </div>
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
