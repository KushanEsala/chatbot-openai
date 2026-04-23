<?php
/**
 * Admin Settings Page
 */

if ( ! current_user_can( 'manage_options' ) ) {
    return;
}

$openai_key = get_option( 'chatbot_openai_key' );
$model = get_option( 'chatbot_model', 'gpt-4o-mini' );
$temperature = get_option( 'chatbot_temperature', '0.7' );
$enable_home_icon = get_option( 'chatbot_enable_home_icon', '1' );
$ai_behavior_instructions = get_option( 'chatbot_ai_instructions', '' );
$bot_name = get_option( 'chatbot_bot_name', 'AI Assistant' );
$primary_color = get_option( 'chatbot_primary_color', '#2457d6' );
$enable_voice_input = get_option( 'chatbot_enable_voice_input', '1' );
$enable_voice_output = get_option( 'chatbot_enable_voice_output', '1' );
$voice_autosend = get_option( 'chatbot_voice_autosend', '1' );
$voice_rate = get_option( 'chatbot_voice_rate', '1' );
$voice_pitch = get_option( 'chatbot_voice_pitch', '1' );
$meeting_link = get_option( 'chatbot_meeting_link', '' );
$enable_chat_logging = get_option( 'chatbot_enable_chat_logging', '1' );

$default_nurture_instructions = "You're a friendly Team Rocket expert helping B2B companies with lead generation.\n\nHow to chat naturally:\n- Sound like a real person texting, not a corporate bot\n- Acknowledge what they tell you and build on it (don't repeat earlier questions)\n- Keep replies short (1-3 sentences max)\n- Ask genuine follow-up questions based on their business\n- Only suggest a meeting when they show real interest\n- Be conversational and warm, never pushy\n\nKey points:\n- Team Rocket does B2B lead generation for businesses\n- We help convert visitors into qualified meetings\n- If they seem interested in booking, offer the meeting link naturally";

$model_options = function_exists( 'chatbot_get_model_options' ) ? chatbot_get_model_options() : array(
    'gpt-4.1' => 'GPT-4.1 (Best general reasoning)',
    'gpt-4.1-mini' => 'GPT-4.1 Mini (Fast, lower cost)',
    'gpt-4o' => 'GPT-4o (Balanced flagship)',
    'gpt-4o-mini' => 'GPT-4o Mini (Fast, cost efficient)',
    'gpt-4' => 'GPT-4 (Legacy high quality)',
    'gpt-3.5-turbo' => 'GPT-3.5 Turbo (Legacy)',
);

// Handle form submission
if ( isset( $_POST['chatbot_settings_nonce'] ) && wp_verify_nonce( $_POST['chatbot_settings_nonce'], 'chatbot_settings' ) ) {
    $openai_key = sanitize_text_field( $_POST['chatbot_openai_key'] ?? '' );
    $model = sanitize_text_field( $_POST['chatbot_model'] ?? 'gpt-4o-mini' );
    $temperature = floatval( $_POST['chatbot_temperature'] ?? 0.7 );
    $enable_home_icon = isset( $_POST['chatbot_enable_home_icon'] ) ? '1' : '0';
    $ai_behavior_instructions = sanitize_textarea_field( $_POST['chatbot_ai_instructions'] ?? '' );
    $bot_name = sanitize_text_field( $_POST['chatbot_bot_name'] ?? 'AI Assistant' );
    $primary_color = sanitize_hex_color( $_POST['chatbot_primary_color'] ?? '#667eea' );
    $enable_voice_input = isset( $_POST['chatbot_enable_voice_input'] ) ? '1' : '0';
    $enable_voice_output = isset( $_POST['chatbot_enable_voice_output'] ) ? '1' : '0';
    $voice_autosend = isset( $_POST['chatbot_voice_autosend'] ) ? '1' : '0';
    $voice_rate = floatval( $_POST['chatbot_voice_rate'] ?? 1 );
    $voice_pitch = floatval( $_POST['chatbot_voice_pitch'] ?? 1 );
    $meeting_link = esc_url_raw( trim( $_POST['chatbot_meeting_link'] ?? '' ) );
    $enable_chat_logging = isset( $_POST['chatbot_enable_chat_logging'] ) ? '1' : '0';
    if ( empty( $primary_color ) ) {
        $primary_color = '#667eea';
    }
    
    update_option( 'chatbot_openai_key', $openai_key );
    update_option( 'chatbot_model', $model );
    update_option( 'chatbot_temperature', $temperature );
    update_option( 'chatbot_enable_home_icon', $enable_home_icon );
    update_option( 'chatbot_ai_instructions', $ai_behavior_instructions );
    update_option( 'chatbot_bot_name', $bot_name );
    update_option( 'chatbot_primary_color', $primary_color );
    update_option( 'chatbot_enable_voice_input', $enable_voice_input );
    update_option( 'chatbot_enable_voice_output', $enable_voice_output );
    update_option( 'chatbot_voice_autosend', $voice_autosend );
    update_option( 'chatbot_voice_rate', max( 0.5, min( 2.0, $voice_rate ) ) );
    update_option( 'chatbot_voice_pitch', max( 0.5, min( 2.0, $voice_pitch ) ) );
    update_option( 'chatbot_meeting_link', $meeting_link );
    update_option( 'chatbot_enable_chat_logging', $enable_chat_logging );
    
    echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
}

if ( isset( $_GET['chatbot_csv_saved'] ) ) {
    $saved_file_url = isset( $_GET['chatbot_csv_file_url'] ) ? esc_url_raw( wp_unslash( $_GET['chatbot_csv_file_url'] ) ) : '';
    echo '<div class="notice notice-success is-dismissible"><p>Chat history CSV saved successfully.';
    if ( ! empty( $saved_file_url ) ) {
        echo ' <a href="' . esc_url( $saved_file_url ) . '" target="_blank" rel="noopener noreferrer">Open saved file</a>';
    }
    echo '</p></div>';
}

if ( isset( $_GET['chatbot_csv_error'] ) ) {
    echo '<div class="notice notice-error is-dismissible"><p>Unable to save the CSV file. Please check the uploads folder permissions.</p></div>';
}
?>

<div class="wrap">
    <h1>🤖 Chatbot Settings</h1>
    
    <div style="max-width: 600px; margin-top: 20px;">
        <form method="post" style="background: #fff; padding: 20px; border: 1px solid #ccc; border-radius: 5px;">
            <?php wp_nonce_field( 'chatbot_settings', 'chatbot_settings_nonce' ); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="chatbot_openai_key">OpenAI API Key</label>
                    </th>
                    <td>
                        <input 
                            type="password" 
                            id="chatbot_openai_key" 
                            name="chatbot_openai_key" 
                            value="<?php echo esc_attr( $openai_key ); ?>" 
                            class="regular-text"
                            placeholder="sk-..."
                        >
                        <p class="description">
                            Get your API key from <a href="https://platform.openai.com/api/keys" target="_blank">OpenAI API Keys</a>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="chatbot_model">Model</label>
                    </th>
                    <td>
                        <select id="chatbot_model" name="chatbot_model">
                            <?php foreach ( $model_options as $model_key => $model_label ) : ?>
                                <option value="<?php echo esc_attr( $model_key ); ?>" <?php selected( $model, $model_key ); ?>><?php echo esc_html( $model_label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Select a current ChatGPT model from the dropdown. Legacy models remain available for compatibility.</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="chatbot_temperature">Temperature (0-2)</label>
                    </th>
                    <td>
                        <input 
                            type="number" 
                            id="chatbot_temperature" 
                            name="chatbot_temperature" 
                            value="<?php echo esc_attr( $temperature ); ?>" 
                            step="0.1" 
                            min="0" 
                            max="2"
                            style="width: 100px;"
                        >
                        <p class="description">Lower values = more focused, higher values = more creative</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="chatbot_bot_name">Chatbot Name</label>
                    </th>
                    <td>
                        <input
                            type="text"
                            id="chatbot_bot_name"
                            name="chatbot_bot_name"
                            value="<?php echo esc_attr( $bot_name ); ?>"
                            class="regular-text"
                            placeholder="AI Assistant"
                        >
                        <p class="description">Shown in the chat header and welcome message.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="chatbot_primary_color">Chat Primary Color</label>
                    </th>
                    <td>
                        <input
                            type="color"
                            id="chatbot_primary_color"
                            name="chatbot_primary_color"
                            value="<?php echo esc_attr( $primary_color ); ?>"
                        >
                        <p class="description">Controls chat icon, header, and primary accents.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Voice Chat Input</th>
                    <td>
                        <label>
                            <input type="checkbox" name="chatbot_enable_voice_input" value="1" <?php checked( $enable_voice_input, '1' ); ?>>
                            Enable microphone input for speech-to-text
                        </label>
                        <p class="description">Uses the browser SpeechRecognition API when available.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Voice Chat Output</th>
                    <td>
                        <label>
                            <input type="checkbox" name="chatbot_enable_voice_output" value="1" <?php checked( $enable_voice_output, '1' ); ?>>
                            Speak AI responses aloud using browser text-to-speech
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Auto Send Voice Transcript</th>
                    <td>
                        <label>
                            <input type="checkbox" name="chatbot_voice_autosend" value="1" <?php checked( $voice_autosend, '1' ); ?>>
                            Automatically send speech transcript after you finish speaking
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="chatbot_voice_rate">Voice Rate</label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="chatbot_voice_rate"
                            name="chatbot_voice_rate"
                            value="<?php echo esc_attr( $voice_rate ); ?>"
                            step="0.1"
                            min="0.5"
                            max="2"
                            style="width: 100px;"
                        >
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="chatbot_voice_pitch">Voice Pitch</label>
                    </th>
                    <td>
                        <input
                            type="number"
                            id="chatbot_voice_pitch"
                            name="chatbot_voice_pitch"
                            value="<?php echo esc_attr( $voice_pitch ); ?>"
                            step="0.1"
                            min="0.5"
                            max="2"
                            style="width: 100px;"
                        >
                    </td>
                </tr>

                <tr>
                    <th scope="row">Homepage Floating Chat Icon</th>
                    <td>
                        <label>
                            <input type="checkbox" name="chatbot_enable_home_icon" value="1" <?php checked( $enable_home_icon, '1' ); ?>>
                            Enable round chat icon popup on homepage automatically
                        </label>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="chatbot_ai_instructions">AI Reply Instructions</label>
                    </th>
                    <td>
                        <textarea id="chatbot_ai_instructions" name="chatbot_ai_instructions" rows="12" class="large-text" placeholder="<?php echo esc_attr( $default_nurture_instructions ); ?>"><?php echo esc_textarea( '' !== trim( $ai_behavior_instructions ) ? $ai_behavior_instructions : $default_nurture_instructions ); ?></textarea>
                        <p class="description">These are global AI behavior instructions, not slash commands.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="chatbot_meeting_link">Meeting Booking Link</label>
                    </th>
                    <td>
                        <input
                            type="url"
                            id="chatbot_meeting_link"
                            name="chatbot_meeting_link"
                            value="<?php echo esc_attr( $meeting_link ); ?>"
                            class="regular-text"
                            placeholder="https://calendar.google.com/..."
                        >
                        <p class="description">Paste your meeting booking link here. When a visitor shows positive interest, the chatbot will suggest booking a meeting.</p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">Chat History Logging</th>
                    <td>
                        <label>
                            <input type="checkbox" name="chatbot_enable_chat_logging" value="1" <?php checked( $enable_chat_logging, '1' ); ?>>
                            Save chat sessions and messages to the WordPress database
                        </label>
                        <p class="description">Keeps a session-based record so you can download the full chat history as CSV whenever needed.</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>

        <!-- CSV Export Form - Separate from Main Settings Form -->
        <div style="margin-top: 20px; padding: 15px; background: #fff; border: 1px solid #ccc; border-radius: 5px;">
            <h3>Download Chat History</h3>
            <p>Export all chat sessions and messages to a CSV file for analysis or backup.</p>
            <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
                <?php wp_nonce_field( 'chatbot_export_chat_history', 'chatbot_export_nonce' ); ?>
                <input type="hidden" name="action" value="chatbot_export_chat_csv">
                <button type="submit" class="button button-primary">Download CSV</button>
                <span class="description">Click to download all sessions and messages</span>
            </form>
        </div>
    </div>
    
    <div style="background: #f0f7ff; padding: 15px; border-left: 4px solid #667eea; margin-top: 30px; border-radius: 5px;">
        <h3>📚 How to Use the Chatbot:</h3>
        <ol>
            <li>Use shortcode <code>[chatbot_popup]</code> for popup chat with round icon</li>
            <li>Use shortcode <code>[chatbot]</code> for embedded chat panel</li>
            <li>Use Gutenberg block: <code>Chatbot Popup</code> on any page/post</li>
            <li>Type <strong>/help</strong> to see built-in commands</li>
            <li>Use <strong>AI Reply Instructions</strong> to control how AI responds to normal messages</li>
            <li>Paste your meeting booking link to turn positive leads into booked calls</li>
            <li>Enable voice input/output above to allow speaking and listening</li>
        </ol>
        <p><strong>Available Commands:</strong></p>
        <ul>
            <li><code>/help</code> - Show available commands</li>
            <li><code>/info</code> - Get chatbot information</li>
            <li><code>/time</code> - Show current time</li>
            <li><code>/date</code> - Show current date</li>
            <li><code>/hello</code> - Say hello</li>
            <li><code>/clear</code> - Clear chat history</li>
            <li>No extra slash commands are added from settings</li>
        </ul>
    </div>

</div>

<style>
    .wrap input[type="password"],
    .wrap input[type="text"],
    .wrap select {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    .wrap input[type="password"]:focus,
    .wrap input[type="text"]:focus,
    .wrap select:focus {
        border-color: #667eea;
        outline: none;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }
    
    .wrap code {
        background: #f5f5f5;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: 'Courier New', monospace;
    }
</style>
