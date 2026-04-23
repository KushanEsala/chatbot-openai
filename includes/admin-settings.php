<?php
/**
 * Admin Settings Handler
 */

add_action( 'admin_init', 'chatbot_register_settings' );

function chatbot_register_settings() {
    register_setting( 'chatbot_settings', 'chatbot_openai_key' );
    register_setting( 'chatbot_settings', 'chatbot_model' );
    register_setting( 'chatbot_settings', 'chatbot_temperature' );
    register_setting( 'chatbot_settings', 'chatbot_enable_home_icon' );
    register_setting( 'chatbot_settings', 'chatbot_ai_instructions' );
    register_setting( 'chatbot_settings', 'chatbot_bot_name' );
    register_setting( 'chatbot_settings', 'chatbot_primary_color' );
    register_setting( 'chatbot_settings', 'chatbot_meeting_link' );
    register_setting( 'chatbot_settings', 'chatbot_enable_chat_logging' );
}
