<?php
/**
 * OpenAI API Handler
 */

class ChatBot_API {
    /**
     * Normalize booking links and placeholder tokens in replies.
     */
    private static function inject_meeting_link( $reply ) {
        $meeting_link = esc_url_raw( trim( (string) get_option( 'chatbot_meeting_link', '' ) ) );

        if ( empty( $meeting_link ) ) {
            return $reply;
        }

        $placeholders = array(
            '[meeting link]',
            '[Meeting Booking Link]',
            '{meeting_link}',
            '{{meeting_link}}',
            '[schedule link]',
            '{schedule_link}',
            '[booking link]',
        );

        $replaced = str_ireplace( $placeholders, '[Schedule a meeting](' . $meeting_link . ')', $reply, $count );

        // Normalize booking URLs so replies always use the link configured in admin.
        $replaced = preg_replace(
            '#https?://(?:www\.)?(?:calendly\.com|calendar\.app\.google|calendar\.google\.com)/[^\s)"\']+#i',
            $meeting_link,
            $replaced
        );

        if ( $count > 0 || false !== strpos( $replaced, $meeting_link ) ) {
            return $replaced;
        }

        return $reply;
    }

    /**
     * Detect whether the user message shows positive buying or meeting intent.
     */
    private static function is_positive_lead_message( $message ) {
        $text = strtolower( trim( $message ) );
        $positive_terms = array(
            'yes',
            'yeah',
            'yep',
            'sounds good',
            'looks good',
            'interested',
            'i am interested',
            'lets talk',
            "let's talk",
            'book',
            'schedule',
            'meeting',
            'call',
            'demo',
            'proposal',
            'quote',
            'pricing',
            'send details',
            'contact me',
            'i want to proceed',
            'we are ready',
            'ready to start',
            'that sounds great',
            'sounds perfect',
            'great to hear',
            'yes please',
            'absolutely',
            'let us do it',
            'let\'s do it',
            'next step',
            'let me know more',
            'send me the link',
            'share the link',
        );

        foreach ( $positive_terms as $term ) {
            if ( false !== strpos( $text, $term ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Build a booking CTA when the user is ready to talk.
     */
    public static function maybe_add_meeting_cta( $reply, $message ) {
        $meeting_link = esc_url_raw( trim( (string) get_option( 'chatbot_meeting_link', '' ) ) );

        if ( empty( $meeting_link ) || ! self::is_positive_lead_message( $message ) ) {
            return self::inject_meeting_link( $reply );
        }

        if ( false !== stripos( $reply, $meeting_link ) ) {
            return self::inject_meeting_link( $reply );
        }

        // Do not append hardcoded CTA copy here. Let admin-defined instructions
        // control phrasing to avoid overwriting the desired conversation style.
        return self::inject_meeting_link( $reply );
    }

    /**
     * Return a simple local fallback response when API is unavailable.
     */
    private static function get_local_fallback_reply( $message ) {
        $text = strtolower( trim( $message ) );

        if ( strpos( $text, 'hello' ) !== false || strpos( $text, 'hi' ) !== false ) {
            return 'Hi! I am running in fallback mode right now because the AI quota is exhausted. You can still use /help, /time, and /date commands.';
        }

        if ( strpos( $text, 'price' ) !== false || strpos( $text, 'cost' ) !== false ) {
            return 'Pricing details are not connected yet in fallback mode. Please add your pricing info in the site content or restore API credits for AI answers.';
        }

        if ( strpos( $text, 'contact' ) !== false || strpos( $text, 'email' ) !== false ) {
            return 'Please check the Contact section of this site. If you want, I can also be wired to return your exact phone/email automatically.';
        }

        return 'AI responses are temporarily unavailable because the OpenAI quota is exceeded. Please add billing/credits, then retry. You can still use built-in commands like /help, /time, /date, /hello.';
    }

    
    /**
     * Send message to OpenAI API
     */
    public static function send_to_openai( $message, $conversation_history = array() ) {
        $api_key = get_option( 'chatbot_openai_key' );
        $custom_instruction = trim( (string) get_option( 'chatbot_ai_instructions', '' ) );
        $model = sanitize_text_field( get_option( 'chatbot_model', 'gpt-4o-mini' ) );
        $temperature = floatval( get_option( 'chatbot_temperature', 0.7 ) );
        $temperature = max( 0.0, min( 2.0, $temperature ) );
        
        if ( strpos( $api_key, 'sk-' ) !== 0 || strpos( $api_key, 'dummy' ) !== false ) {
            return "⚠️ API key not configured. Please set your OpenAI API key in Chatbot settings.";
        }
        
        $url = 'https://api.openai.com/v1/chat/completions';
        
        if ( '' !== $custom_instruction ) {
            // Use admin instructions as the primary behavior source.
            $system_prompt = $custom_instruction;
        } else {
            $system_prompt = 'You are a friendly sales expert from Team Rocket helping with B2B lead generation. Keep it natural and conversational, like texting with a real person. Never sound robotic or overly corporate. Be genuinely interested in their business, don\'t repeat questions you already asked, and naturally guide them toward a meeting only when they show interest. Keep responses short (1-3 sentences max).';
            $system_prompt .= "\n\nNurture approach:\n- Acknowledge what they\'ve told you and build on it\n- Don\'t repeat earlier greetings or questions\n- Ask meaningful follow-ups based on their business\n- When they show interest (say yes, want to talk, ready to proceed), suggest booking a call";
        }

        // Build messages array from conversation history
        $messages = array(
            array(
                'role' => 'system',
                'content' => $system_prompt
            )
        );

        // Add conversation history (previous messages)
        if ( is_array( $conversation_history ) && count( $conversation_history ) > 0 ) {
            foreach ( $conversation_history as $item ) {
                if ( isset( $item['text'] ) && ! empty( $item['text'] ) ) {
                    // Determine role: if isUser is true, it's a user message; otherwise it's an assistant message
                    $role = isset( $item['isUser'] ) && $item['isUser'] ? 'user' : 'assistant';
                    $messages[] = array(
                        'role' => $role,
                        'content' => trim( (string) $item['text'] )
                    );
                }
            }
        }

        // Add current message
        $messages[] = array(
            'role' => 'user',
            'content' => $message
        );

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode( array(
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => 500
            ) ),
            'timeout' => 30,
        );
        
        $response = wp_remote_post( $url, $args );
        
        if ( is_wp_error( $response ) ) {
            return "Sorry, I encountered an error: " . $response->get_error_message();
        }
        
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        
        if ( isset( $body['choices'][0]['message']['content'] ) ) {
            $reply = $body['choices'][0]['message']['content'];
            return self::maybe_add_meeting_cta( $reply, $message );
        } elseif ( isset( $body['error']['message'] ) ) {
            $error_message = isset( $body['error']['message'] ) ? $body['error']['message'] : '';
            $error_code = isset( $body['error']['code'] ) ? $body['error']['code'] : '';
            $quota_hit = ( 'insufficient_quota' === $error_code ) || ( false !== stripos( $error_message, 'exceeded your current quota' ) );

            if ( $quota_hit ) {
                return self::maybe_add_meeting_cta( self::get_local_fallback_reply( $message ), $message );
            }

            return "API Error: " . $error_message;
        }
        
        return self::maybe_add_meeting_cta( "Sorry, I couldn't process your request.", $message );
    }
}
