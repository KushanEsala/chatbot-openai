<?php
/**
 * Plugin Name: OpenAI Chatbot
 * Plugin URI: https://weareteamrocket.com/
 * Description: Custom chatbot integrated with OpenAI API with custom commands
 * Version: 1.5.0
 * Author: Kushan Esala
 * Author URI: https://kushanesala.github.io/portfolio/
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'CHATBOT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CHATBOT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load plugin files
require_once CHATBOT_PLUGIN_DIR . 'includes/chatbot-api.php';
require_once CHATBOT_PLUGIN_DIR . 'includes/chatbot-commands.php';
require_once CHATBOT_PLUGIN_DIR . 'includes/chatbot-storage.php';
require_once CHATBOT_PLUGIN_DIR . 'includes/admin-settings.php';
require_once CHATBOT_PLUGIN_DIR . 'includes/plugin-updater.php';

add_filter( 'plugin_row_meta', 'chatbot_brand_plugin_row_meta', 10, 2 );
function chatbot_brand_plugin_row_meta( $plugin_meta, $plugin_file ) {
    if ( plugin_basename( __FILE__ ) !== $plugin_file ) {
        return $plugin_meta;
    }

    $website_link = '<a href="https://weareteamrocket.com/" target="_blank" rel="noopener noreferrer">We Are Team Rocket</a>';
    $plugin_meta = array_filter(
        $plugin_meta,
        function( $meta ) {
            return false === stripos( $meta, 'Visit plugin site' );
        }
    );

    $plugin_meta[] = $website_link;

    return $plugin_meta;
}

// Enqueue scripts and styles
add_action( 'wp_enqueue_scripts', 'chatbot_enqueue_scripts' );
function chatbot_enqueue_scripts() {
    $css_ver = file_exists( CHATBOT_PLUGIN_DIR . 'assets/css/chatbot.css' ) ? filemtime( CHATBOT_PLUGIN_DIR . 'assets/css/chatbot.css' ) : '1.2.0';
    $js_ver = file_exists( CHATBOT_PLUGIN_DIR . 'assets/js/chatbot.js' ) ? filemtime( CHATBOT_PLUGIN_DIR . 'assets/js/chatbot.js' ) : '1.2.0';

    wp_enqueue_style( 'chatbot-style', CHATBOT_PLUGIN_URL . 'assets/css/chatbot.css', array(), $css_ver );
    wp_enqueue_script( 'chatbot-script', CHATBOT_PLUGIN_URL . 'assets/js/chatbot.js', array( 'jquery' ), $js_ver, true );

    $bot_name = get_option( 'chatbot_bot_name', 'AI Assistant' );
    $primary_color = sanitize_hex_color( get_option( 'chatbot_primary_color', '#2457d6' ) );
    if ( empty( $primary_color ) ) {
        $primary_color = '#2457d6';
    }
    $primary_color_dark = chatbot_adjust_hex_color( $primary_color, -24 );
    $voice_input_enabled = '1' === (string) get_option( 'chatbot_enable_voice_input', '1' );
    $voice_output_enabled = '1' === (string) get_option( 'chatbot_enable_voice_output', '1' );
    $voice_autosend = '1' === (string) get_option( 'chatbot_voice_autosend', '1' );
    $voice_rate = floatval( get_option( 'chatbot_voice_rate', '1' ) );
    $voice_pitch = floatval( get_option( 'chatbot_voice_pitch', '1' ) );
    
    wp_localize_script( 'chatbot-script', 'chatbotVars', array(
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce' => wp_create_nonce( 'chatbot_nonce' ),
        'welcomeText' => 'Hello! I\'m ' . $bot_name . '. Type /help to see available commands or just ask me anything!',
        'botName' => $bot_name,
        'maxMessageLength' => 800,
        'primaryColor' => $primary_color,
        'primaryColorDark' => $primary_color_dark,
        'voiceInputEnabled' => $voice_input_enabled,
        'voiceOutputEnabled' => $voice_output_enabled,
        'voiceAutoSend' => $voice_autosend,
        'voiceRate' => $voice_rate,
        'voicePitch' => $voice_pitch,
    ) );
}

function chatbot_get_client_identifier() {
    $ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
    return 'chatbot_rl_' . md5( $ip );
}

function chatbot_rate_limit_ok() {
    $key = chatbot_get_client_identifier();
    $data = get_transient( $key );
    $now = time();
    $window = 60;
    $max_requests = 30;

    if ( ! is_array( $data ) || ! isset( $data['start'] ) || ! isset( $data['count'] ) ) {
        set_transient( $key, array( 'start' => $now, 'count' => 1 ), $window );
        return true;
    }

    if ( ( $now - (int) $data['start'] ) >= $window ) {
        set_transient( $key, array( 'start' => $now, 'count' => 1 ), $window );
        return true;
    }

    $data['count'] = (int) $data['count'] + 1;
    set_transient( $key, $data, $window );

    return $data['count'] <= $max_requests;
}

function chatbot_adjust_hex_color( $hex, $steps ) {
    $hex = ltrim( $hex, '#' );

    if ( 3 === strlen( $hex ) ) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }

    if ( 6 !== strlen( $hex ) ) {
        return '#667eea';
    }

    $steps = max( -255, min( 255, (int) $steps ) );

    $r = hexdec( substr( $hex, 0, 2 ) );
    $g = hexdec( substr( $hex, 2, 2 ) );
    $b = hexdec( substr( $hex, 4, 2 ) );

    $r = max( 0, min( 255, $r + $steps ) );
    $g = max( 0, min( 255, $g + $steps ) );
    $b = max( 0, min( 255, $b + $steps ) );

    return sprintf( '#%02x%02x%02x', $r, $g, $b );
}

function chatbot_get_model_options() {
    $models = array(
        'gpt-4.1' => 'GPT-4.1 (Best general reasoning)',
        'gpt-4.1-mini' => 'GPT-4.1 Mini (Fast, lower cost)',
        'gpt-4o' => 'GPT-4o (Balanced flagship)',
        'gpt-4o-mini' => 'GPT-4o Mini (Fast, cost efficient)',
        'gpt-4' => 'GPT-4 (Legacy high quality)',
        'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Legacy)',
    );

    return apply_filters( 'chatbot_model_options', $models );
}

function chatbot_migrate_legacy_options() {
    $legacy_instructions = get_option( 'chatbot_custom_commands', null );
    $new_instructions = get_option( 'chatbot_ai_instructions', null );

    if ( null !== $legacy_instructions && null === $new_instructions ) {
        update_option( 'chatbot_ai_instructions', $legacy_instructions );
    }
}
add_action( 'init', 'chatbot_migrate_legacy_options' );

add_action( 'init', 'chatbot_register_blocks' );
function chatbot_register_blocks() {
    if ( ! function_exists( 'register_block_type' ) ) {
        return;
    }

    wp_register_script(
        'chatbot-block-editor',
        CHATBOT_PLUGIN_URL . 'assets/js/chatbot-block.js',
        array( 'wp-blocks', 'wp-element', 'wp-editor' ),
        '1.1.0',
        true
    );

    register_block_type(
        'chatbot/popup',
        array(
            'editor_script' => 'chatbot-block-editor',
            'render_callback' => 'chatbot_render_popup_block',
        )
    );
}

function chatbot_render_popup_block() {
    return chatbot_render_widget( 'popup' );
}

// Register AJAX endpoint
add_action( 'wp_ajax_send_message', 'chatbot_handle_message' );
add_action( 'wp_ajax_nopriv_send_message', 'chatbot_handle_message' );

function chatbot_handle_message() {
    // Validate nonce for authenticated users. For public chatbot usage,
    // allow guest requests even if nonce is missing/mismatched.
    $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
    if ( is_user_logged_in() && ! wp_verify_nonce( $nonce, 'chatbot_nonce' ) ) {
        wp_send_json_error( 'Nonce verification failed' );
    }
    
    $message = sanitize_text_field( $_POST['message'] ?? '' );
    $session_key = isset( $_POST['session_id'] ) ? sanitize_text_field( wp_unslash( $_POST['session_id'] ) ) : '';
    $page_url = isset( $_POST['page_url'] ) ? esc_url_raw( wp_unslash( $_POST['page_url'] ) ) : '';
    $referrer = isset( $_POST['referrer'] ) ? esc_url_raw( wp_unslash( $_POST['referrer'] ) ) : '';
    $visitor_id = chatbot_get_client_identifier();
    
    // Get conversation history from frontend
    $conversation_history_json = isset( $_POST['conversation_history'] ) ? wp_unslash( $_POST['conversation_history'] ) : '[]';
    $conversation_history = json_decode( $conversation_history_json, true );
    if ( ! is_array( $conversation_history ) ) {
        $conversation_history = array();
    }
    
    if ( empty( $message ) ) {
        wp_send_json_error( 'No message provided' );
    }

    if ( strlen( $message ) > 800 ) {
        wp_send_json_error( 'Message too long. Keep it under 800 characters.' );
    }

    if ( ! chatbot_rate_limit_ok() ) {
        wp_send_json_error( 'Too many requests. Please wait a moment and try again.' );
    }

    if ( empty( $session_key ) ) {
        $session_key = 'chatbot_' . wp_generate_uuid4();
    }

    chatbot_log_chat_message(
        $session_key,
        'user',
        $message,
        array(
            'visitor_id' => $visitor_id,
            'page_url'   => $page_url,
            'referrer'   => $referrer,
            'status'     => function_exists( 'chatbot_infer_chat_status' ) ? chatbot_infer_chat_status( $message ) : 'active',
        )
    );
    
    // Process commands
    $response = ChatBot_Commands::process_command( $message );
    
    // If not a command, send to OpenAI with conversation history
    if ( ! $response ) {
        $response = ChatBot_API::send_to_openai( $message, $conversation_history );
    }

    chatbot_log_chat_message(
        $session_key,
        'assistant',
        $response,
        array(
            'visitor_id' => $visitor_id,
            'page_url'   => $page_url,
            'referrer'   => $referrer,
        )
    );
    
    wp_send_json_success( array( 'reply' => $response ) );
}

function chatbot_render_widget( $mode = 'inline' ) {
    static $instance = 0;
    $instance++;

    $bot_name = get_option( 'chatbot_bot_name', 'AI Assistant' );
    $primary_color = sanitize_hex_color( get_option( 'chatbot_primary_color', '#667eea' ) );
    if ( empty( $primary_color ) ) {
        $primary_color = '#667eea';
    }
    $primary_color_dark = chatbot_adjust_hex_color( $primary_color, -24 );
    $widget_style = '--chatbot-primary:' . $primary_color . ';--chatbot-primary-dark:' . $primary_color_dark . ';';

    if ( 'popup' === $mode ) {
        $GLOBALS['chatbot_popup_rendered'] = true;
    }

    ob_start();
    if ( 'popup' === $mode ) {
        include CHATBOT_PLUGIN_DIR . 'templates/chatbot-popup.php';
    } else {
        include CHATBOT_PLUGIN_DIR . 'templates/chatbot-widget.php';
    }
    return ob_get_clean();
}

// Register shortcodes
add_shortcode( 'chatbot', 'chatbot_shortcode_inline' );
add_shortcode( 'chatbot_popup', 'chatbot_shortcode_popup' );

function chatbot_shortcode_inline( $atts ) {
    return chatbot_render_widget( 'inline' );
}

function chatbot_shortcode_popup( $atts ) {
    return chatbot_render_widget( 'popup' );
}

add_action( 'wp_footer', 'chatbot_render_homepage_floating_chat' );
function chatbot_render_homepage_floating_chat() {
    $enabled = get_option( 'chatbot_enable_home_icon', '1' );
    if ( '1' !== (string) $enabled ) {
        return;
    }

    if ( ! is_front_page() && ! is_home() ) {
        return;
    }

    if ( ! empty( $GLOBALS['chatbot_popup_rendered'] ) ) {
        return;
    }

    echo chatbot_render_widget( 'popup' );
}

// Plugin activation
register_activation_hook( __FILE__, 'chatbot_activate' );
function chatbot_activate() {
    // Set default API key option
    if ( ! get_option( 'chatbot_openai_key' ) ) {
        add_option( 'chatbot_openai_key', 'sk-dummy-key-replace-with-your-key' );
    }

    if ( false === get_option( 'chatbot_enable_home_icon', false ) ) {
        add_option( 'chatbot_enable_home_icon', '1' );
    }

    if ( false === get_option( 'chatbot_ai_instructions', false ) ) {
        add_option( 'chatbot_ai_instructions', '' );
    }

    if ( false === get_option( 'chatbot_bot_name', false ) ) {
        add_option( 'chatbot_bot_name', 'AI Assistant' );
    }

    if ( false === get_option( 'chatbot_primary_color', false ) ) {
        add_option( 'chatbot_primary_color', '#2457d6' );
    }

    if ( false === get_option( 'chatbot_model', false ) ) {
        add_option( 'chatbot_model', 'gpt-4o-mini' );
    }

    if ( false === get_option( 'chatbot_temperature', false ) ) {
        add_option( 'chatbot_temperature', '0.7' );
    }

    if ( false === get_option( 'chatbot_enable_voice_input', false ) ) {
        add_option( 'chatbot_enable_voice_input', '1' );
    }

    if ( false === get_option( 'chatbot_enable_voice_output', false ) ) {
        add_option( 'chatbot_enable_voice_output', '1' );
    }

    if ( false === get_option( 'chatbot_voice_autosend', false ) ) {
        add_option( 'chatbot_voice_autosend', '1' );
    }

    if ( false === get_option( 'chatbot_voice_rate', false ) ) {
        add_option( 'chatbot_voice_rate', '1' );
    }

    if ( false === get_option( 'chatbot_voice_pitch', false ) ) {
        add_option( 'chatbot_voice_pitch', '1' );
    }

    if ( false === get_option( 'chatbot_enable_chat_logging', false ) ) {
        add_option( 'chatbot_enable_chat_logging', '1' );
    }

    if ( false === get_option( 'chatbot_db_version', false ) ) {
        add_option( 'chatbot_db_version', '' );
    }

    chatbot_install_storage_tables();
    chatbot_migrate_legacy_options();
}

// Admin menu
add_action( 'admin_menu', 'chatbot_admin_menu' );
function chatbot_admin_menu() {
    add_menu_page(
        'Chatbot Settings',
        'Chatbot',
        'manage_options',
        'chatbot-settings',
        'chatbot_admin_page',
        'dashicons-format-chat'
    );
}

function chatbot_admin_page() {
    include CHATBOT_PLUGIN_DIR . 'admin/settings-page.php';
}
