jQuery(document).ready(function($) {
    const SpeechRecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition || null;

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(text).replace(/[&<>"']/g, m => map[m]);
    }

    function decodeHtmlEntities(text) {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = String(text);
        return textarea.value;
    }

    function normalizeReplyText(text) {
        return decodeHtmlEntities(String(text))
            .replace(/\u00a0/g, ' ')
            .replace(/\/n/g, '\n')
            .trim();
    }

    function formatResponse(text) {
        let safe = escapeHtml(String(text));
        // Convert Markdown-style links [text](url)
        safe = safe.replace(/\[(.*?)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer">$1</a>');
        // Convert plain URLs to clickable links, but avoid touching existing anchor tags.
        const parts = safe.split(/(<a\b[^>]*>.*?<\/a>)/gi);
        safe = parts.map(function(part) {
            if (/^<a\b/i.test(part)) {
                return part;
            }
            return part.replace(/(https?:\/\/[^\s<>"']+)/g, '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>');
        }).join('');
        // Bold text
        safe = safe.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        // Line breaks
        safe = safe.replace(/\n/g, '<br>');
        return safe;
    }

    function initWidget($widget) {
        const widgetId = $widget.data('chatbot-id') || 'default';
        const mode = $widget.data('chatbot-mode') || 'inline';
        const storageKey = 'chatbot_messages_session_' + widgetId;
        const browserSessionKey = 'chatbot_session_id';
        const attentionKey = 'chatbot_attention_prompt_shown_' + widgetId;
        const attentionSessionKey = 'chatbot_attention_prompt_session_once';
        const botName = chatbotVars.botName || 'AI Assistant';
        const voiceInputEnabled = !!chatbotVars.voiceInputEnabled;
        const voiceOutputEnabled = !!chatbotVars.voiceOutputEnabled;
        const voiceAutoSend = !!chatbotVars.voiceAutoSend;
        const voiceRate = parseFloat(chatbotVars.voiceRate || 1);
        const voicePitch = parseFloat(chatbotVars.voicePitch || 1);

        const $messages = $widget.find('.chatbot-messages');
        const $input = $widget.find('.chatbot-input');
        const $send = $widget.find('.chatbot-send-btn');
        const $fab = $widget.find('.chatbot-fab');
        const $panel = $widget.find('.chatbot-popup-panel');
        const $close = $widget.find('.chatbot-close-btn');
        const $voiceBtn = $widget.find('.chatbot-voice-btn');
        const $replayBtn = $widget.find('.chatbot-replay-btn');
        const $voiceStatus = $widget.find('.chatbot-voice-status');
        let $attentionToast = $();

        let recognition = null;
        let isListening = false;
        let lastBotReply = '';
        let isRequestPending = false;
        let pendingVoiceSubmission = false;
        let hasUserInteracted = false;

        if (!voiceOutputEnabled) {
            $replayBtn.hide();
        }

        function updateVoiceStatus(text, className) {
            if (!$voiceStatus.length) return;
            $voiceStatus
                .removeClass('is-listening is-error')
                .addClass(className || '')
                .text(text || '');
        }

        function stopSpeechOutput() {
            if ('speechSynthesis' in window) {
                window.speechSynthesis.cancel();
            }
        }

        function speakText(text) {
            if (!voiceOutputEnabled || !('speechSynthesis' in window) || !window.SpeechSynthesisUtterance) {
                return;
            }

            stopSpeechOutput();

            const utterance = new SpeechSynthesisUtterance(String(text).replace(/<[^>]*>/g, ''));
            utterance.rate = isNaN(voiceRate) ? 1 : voiceRate;
            utterance.pitch = isNaN(voicePitch) ? 1 : voicePitch;
            utterance.lang = 'en-US';
            window.speechSynthesis.speak(utterance);
        }

        function initRecognition() {
            if (!voiceInputEnabled || !SpeechRecognitionCtor) {
                $voiceBtn.hide();
                return;
            }

            if (!voiceOutputEnabled) {
                $replayBtn.hide();
            }

            recognition = new SpeechRecognitionCtor();
            recognition.continuous = false;
            recognition.interimResults = false;
            recognition.lang = 'en-US';

            recognition.onstart = function() {
                isListening = true;
                $widget.addClass('chatbot-listening');
                $voiceBtn.addClass('is-listening');
                updateVoiceStatus('Listening...', 'is-listening');
            };

            recognition.onresult = function(event) {
                const transcript = event.results && event.results[0] && event.results[0][0]
                    ? event.results[0][0].transcript
                    : '';

                if (!transcript) {
                    return;
                }

                $input.val(transcript.trim()).trigger('input');
                pendingVoiceSubmission = true;

                if (voiceAutoSend) {
                    sendMessage({ fromVoice: true });
                } else {
                    updateVoiceStatus('Transcript ready', '');
                }
            };

            recognition.onerror = function(event) {
                updateVoiceStatus('Voice input unavailable', 'is-error');
                console.log('Speech recognition error:', event.error);
            };

            recognition.onend = function() {
                isListening = false;
                $widget.removeClass('chatbot-listening');
                $voiceBtn.removeClass('is-listening');
                if (!voiceAutoSend) {
                    setTimeout(function() {
                        updateVoiceStatus('', '');
                    }, 1800);
                }
            };
        }

        function scrollToBottom() {
            if ($messages.length && $messages[0]) {
                $messages.scrollTop($messages[0].scrollHeight);
            }
        }

        function calculateTypingDelay(text) {
            // Simulate human typing: ~60-80 words per minute = ~500-700ms per word
            // Or ~50ms per character with a minimum of 400ms and max of 3000ms
            const charDelay = Math.max(400, Math.min(3000, text.length * 50));
            return charDelay;
        }

        function splitReplyIntoChunks(reply) {
            const clean = normalizeReplyText(reply);
            if (!clean) return [];

            // Keep short replies as a single message bubble.
            if (clean.length <= 120 && !clean.includes('\n')) {
                return [clean];
            }

            // Prefer paragraph-level chunks so we don't split every sentence.
            const paragraphs = clean
                .split(/\n{2,}/)
                .map(part => part.trim())
                .filter(Boolean);

            if (paragraphs.length > 1) {
                return paragraphs;
            }

            const lineBlocks = clean
                .split(/\n+/)
                .map(part => part.trim())
                .filter(Boolean);

            if (!lineBlocks.length) {
                return [clean];
            }

            // For multi-line replies, group lines naturally (usually 2-3 bubbles max).
            if (lineBlocks.length > 1) {
                const grouped = [];
                let i = 0;
                while (i < lineBlocks.length) {
                    const remaining = lineBlocks.length - i;
                    let take = 1;

                    // Occasionally group two lines together.
                    if (remaining > 1 && Math.random() < 0.45) {
                        take = 2;
                    }

                    const part = lineBlocks.slice(i, i + take).join('\n').trim();
                    grouped.push(part);
                    i += take;
                }

                // Avoid too many tiny bubbles.
                if (grouped.length > 3) {
                    return [grouped.slice(0, 2).join('\n'), grouped.slice(2).join('\n')].filter(Boolean);
                }

                return grouped;
            }

            // Single long paragraph: always split when clearly long,
            // occasionally split when medium length.
            const sentences = clean
                .split(/(?<=[.!?])\s+/)
                .map(part => part.trim())
                .filter(Boolean);

            if (sentences.length <= 2 && clean.length <= 220) {
                return [clean];
            }

            const isClearlyLong = clean.length >= 200 || sentences.length >= 4;
            const shouldSplit = isClearlyLong ? true : (Math.random() < 0.6);
            if (!shouldSplit) {
                return [clean];
            }

            // If punctuation is sparse, split by natural connectors as fallback.
            let workingParts = sentences;
            if (workingParts.length <= 1) {
                workingParts = clean
                    .split(/,\s+|\sfor example\s+|\sand\s+/i)
                    .map(part => part.trim())
                    .filter(Boolean);
            }

            if (workingParts.length <= 1) {
                return [clean];
            }

            const merged = [];
            let cursor = 0;
            while (cursor < workingParts.length) {
                const remaining = workingParts.length - cursor;
                const take = isClearlyLong
                    ? (remaining >= 4 ? 2 : 1)
                    : (remaining >= 3 && Math.random() < 0.4 ? 2 : 1);
                merged.push(workingParts.slice(cursor, cursor + take).join(' '));
                cursor += take;
            }

            return merged;
        }

        function renderBotReplyInChunks(reply, fromVoice) {
            const chunks = splitReplyIntoChunks(reply);
            let index = 0;
            const responsePace = 0.85 + (Math.random() * 0.5); // Different pace per response.

            function pushNext() {
                if (index >= chunks.length) {
                    removeTypingIndicator();
                    if (fromVoice && voiceOutputEnabled) {
                        requestAnimationFrame(function() {
                            speakText(reply);
                        });
                    }
                    isRequestPending = false;
                    $send.prop('disabled', false);
                    return;
                }

                const chunk = chunks[index];
                showTypingIndicator();

                const typingDelay = Math.max(
                    320,
                    Math.min(1700, Math.floor(((chunk.length * 24) + (Math.random() * 280)) * responsePace))
                );
                setTimeout(function() {
                    removeTypingIndicator();
                    addMessage(formatResponse(chunk), false, true, chunk);
                    lastBotReply = chunk;
                    index += 1;

                    const nextDelay = Math.max(
                        140,
                        Math.min(900, Math.floor(((chunk.length * 9) + (Math.random() * 220)) * responsePace))
                    );
                    setTimeout(pushNext, nextDelay);
                }, typingDelay);
            }

            pushNext();
        }

        function saveMessageToStorage(text, isUser) {
            try {
                const messages = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
                messages.push({ text: text, isUser: isUser, timestamp: Date.now() });
                sessionStorage.setItem(storageKey, JSON.stringify(messages));
            } catch (e) {
                console.log('Failed to save to session storage');
            }
        }

        function getBrowserSessionId() {
            try {
                let sessionId = sessionStorage.getItem(browserSessionKey);
                if (!sessionId) {
                    sessionId = (window.crypto && window.crypto.randomUUID)
                        ? 'chatbot_' + window.crypto.randomUUID()
                        : 'chatbot_' + Date.now() + '_' + Math.random().toString(36).slice(2, 12);
                    sessionStorage.setItem(browserSessionKey, sessionId);
                }
                return sessionId;
            } catch (e) {
                return 'chatbot_' + Date.now() + '_' + Math.random().toString(36).slice(2, 12);
            }
        }

        function isPopupOpen() {
            return mode === 'popup' && $widget.hasClass('chatbot-popup-open');
        }

        function setUnreadState(hasUnread) {
            if (mode !== 'popup') return;
            $fab.toggleClass('has-unread', !!hasUnread);
        }

        function showIconToast(messageText) {
            if (mode !== 'popup' || !$attentionToast.length) {
                return;
            }

            $attentionToast.text(messageText).addClass('is-visible');
            setTimeout(function() {
                $attentionToast.removeClass('is-visible');
            }, 6500);
        }

        function clearSessionStorage() {
            try {
                sessionStorage.removeItem(storageKey);
            } catch (e) {
                console.log('Failed to clear session storage');
            }
        }

        function addMessageToDOM(text, isUser, persist, allowHtml, storageText) {
            const messageClass = isUser ? 'user' : 'bot';
            const content = allowHtml ? String(text) : escapeHtml(String(text));
            const html = `
                <div class="chatbot-message ${messageClass}">
                    <div class="chatbot-message-content">${content}</div>
                </div>
            `;
            $messages.append(html);
            scrollToBottom();
            if (persist) {
                saveMessageToStorage(String(storageText !== undefined ? storageText : text), isUser);
            }
        }

        function addMessage(text, isUser, allowHtml, storageText) {
            addMessageToDOM(text, isUser, true, !!allowHtml, storageText);
        }

        function showTypingIndicator() {
            $messages.append(`
                <div class="chatbot-message bot chatbot-typing-row">
                    <div class="chatbot-typing">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            `);
            scrollToBottom();
        }

        function removeTypingIndicator() {
            $messages.find('.chatbot-typing-row').remove();
        }

        function loadMessagesFromStorage() {
            try {
                const list = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
                list.forEach(item => addMessageToDOM(normalizeReplyText(item.text), item.isUser, false, false));
            } catch (e) {
                console.log('Failed to load messages');
            }
        }

        function sendMessage(options) {
            const message = $.trim($input.val());
            const fromVoice = !!(options && options.fromVoice) || pendingVoiceSubmission;
            pendingVoiceSubmission = false;
            hasUserInteracted = true;

            if (!message || isRequestPending) return;

            if (message.length > (chatbotVars.maxMessageLength || 800)) {
                addMessage('Message too long. Keep it under ' + (chatbotVars.maxMessageLength || 800) + ' characters.', false, false);
                return;
            }

            addMessage(message, true, false);
            $input.val('').focus();
            showTypingIndicator();
            isRequestPending = true;
            $send.prop('disabled', true);

            // Get conversation history from storage
            let conversationHistory = [];
            try {
                conversationHistory = JSON.parse(sessionStorage.getItem(storageKey) || '[]');
            } catch (e) {
                conversationHistory = [];
            }

            $.ajax({
                type: 'POST',
                url: chatbotVars.ajaxUrl,
                timeout: 15000,
                data: {
                    action: 'send_message',
                    message: message,
                    conversation_history: JSON.stringify(conversationHistory),
                    nonce: chatbotVars.nonce,
                    session_id: getBrowserSessionId(),
                    page_url: window.location.href,
                    referrer: document.referrer || ''
                },
                success: function(response) {
                    if (typeof response !== 'object') {
                        removeTypingIndicator();
                        addMessage('Unexpected server response.', false, false);
                        isRequestPending = false;
                        $send.prop('disabled', false);
                        return;
                    }
                    if (response.success) {
                        const reply = normalizeReplyText(response.data.reply || '');
                        if (reply === 'CLEAR_CHAT') {
                            removeTypingIndicator();
                            $messages.html('');
                            clearSessionStorage();
                            addMessage('Chat cleared.', false, false);
                            isRequestPending = false;
                            $send.prop('disabled', false);
                        } else {
                            // Simulate typing delay before first chunk, then send chunk-by-chunk.
                            const delay = calculateTypingDelay(reply);
                            setTimeout(function() {
                                removeTypingIndicator();
                                if (mode === 'popup' && !isPopupOpen()) {
                                    setUnreadState(true);
                                    showIconToast('New message from Rocket Assist');
                                }
                                renderBotReplyInChunks(reply, fromVoice);
                            }, delay);
                        }
                    } else {
                        removeTypingIndicator();
                        addMessage('Error: ' + (response.data || 'Unknown error'), false, false);
                        isRequestPending = false;
                        $send.prop('disabled', false);
                    }
                },
                error: function() {
                    removeTypingIndicator();
                    addMessage('Sorry, something went wrong. Please try again.', false, false);
                    isRequestPending = false;
                    $send.prop('disabled', false);
                },
                complete: function() {
                    // Don't reset flags here since success/error handlers do it
                }
            });
        }

        $send.on('click', sendMessage);
        $input.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                sendMessage();
            }
        });

        $input.on('keydown', function() {
            pendingVoiceSubmission = false;
            hasUserInteracted = true;
        });

        $voiceBtn.on('click', function() {
            hasUserInteracted = true;
            if (!recognition) {
                initRecognition();
            }

            if (!recognition) {
                updateVoiceStatus('Voice not supported in this browser', 'is-error');
                return;
            }

            if (isListening) {
                recognition.stop();
                return;
            }

            try {
                recognition.start();
            } catch (error) {
                console.log('Voice start error:', error);
            }
        });

        $replayBtn.on('click', function() {
            hasUserInteracted = true;
            if (lastBotReply) {
                speakText(lastBotReply);
            }
        });

        function markAttentionPromptShown() {
            try {
                sessionStorage.setItem(attentionKey, '1');
                sessionStorage.setItem(attentionSessionKey, '1');
            } catch (e) {
                // Ignore storage failures and continue runtime behavior.
            }
        }

        function wasAttentionPromptShown() {
            try {
                return sessionStorage.getItem(attentionKey) === '1';
            } catch (e) {
                return false;
            }
        }

        function markAttentionSessionHandled() {
            try {
                sessionStorage.setItem(attentionSessionKey, '1');
            } catch (e) {
                // Ignore storage failures and continue runtime behavior.
            }
        }

        function wasAttentionSessionHandled() {
            try {
                return sessionStorage.getItem(attentionSessionKey) === '1';
            } catch (e) {
                return false;
            }
        }

        function showAttentionPrompt() {
            if (mode !== 'popup' || hasUserInteracted || wasAttentionPromptShown()) {
                return;
            }

            const promptText = "Hi, I'm Rocket Assist. How can I help you today?";
            addMessageToDOM(promptText, false, false, false);
            setUnreadState(true);
            showIconToast(promptText);
            markAttentionPromptShown();
        }

        function showInitialPopupGreeting() {
            if (mode !== 'popup' || $messages.find('.chatbot-message').length > 0 || wasAttentionPromptShown()) {
                return;
            }

            addMessageToDOM("Hi, I'm Rocket Assist. How can I help you today?", false, false, false);
            markAttentionPromptShown();
        }

        if (mode === 'popup') {
            $attentionToast = $('<div class="chatbot-icon-toast" aria-live="polite"></div>');
            $widget.append($attentionToast);

            $fab.on('click', function() {
                hasUserInteracted = true;
                markAttentionSessionHandled();
                $widget.toggleClass('chatbot-popup-open');
                if ($widget.hasClass('chatbot-popup-open')) {
                    setUnreadState(false);
                    $attentionToast.removeClass('is-visible');
                    $input.focus();
                    updateVoiceStatus('', '');
                    showInitialPopupGreeting();
                }
            });

            $close.on('click', function() {
                hasUserInteracted = true;
                markAttentionSessionHandled();
                $widget.removeClass('chatbot-popup-open');
                if (recognition && isListening) {
                    recognition.stop();
                }
                stopSpeechOutput();
            });

            if (!wasAttentionSessionHandled()) {
                markAttentionSessionHandled();
                const attentionDelay = 15000 + Math.floor(Math.random() * 5001);
                setTimeout(showAttentionPrompt, attentionDelay);
            }
        }

        initRecognition();
        loadMessagesFromStorage();

        if (mode !== 'popup' && $messages.find('.chatbot-message').length === 0) {
            addMessage(chatbotVars.welcomeText || ('Hello! I am ' + botName + '. Type /help to get started.'), false, false);
        }
    }

    $('.chatbot-widget').each(function() {
        initWidget($(this));
    });
});
