<?php
/**
 * Custom Commands Handler
 */

class ChatBot_Commands {

    /**
     * Process custom commands
     */
    public static function process_command( $message ) {
        $message_lower = strtolower( trim( $message ) );
        
        // Define custom commands
        $commands = array(
            '/help' => 'chatbot_cmd_help',
            '/info' => 'chatbot_cmd_info',
            '/time' => 'chatbot_cmd_time',
            '/date' => 'chatbot_cmd_date',
            '/weather' => 'chatbot_cmd_weather',
            '/hello' => 'chatbot_cmd_hello',
            '/clear' => 'chatbot_cmd_clear',
        );
        
        // Check if message starts with a command
        foreach ( $commands as $cmd => $callback ) {
            if ( strpos( $message_lower, $cmd ) === 0 ) {
                return call_user_func( $callback, $message );
            }
        }


        return false; // Not a command, use AI
    }
    
    /**
     * List available commands
     */
    public static function get_commands() {
        return array(
            '/help' => 'Show available commands',
            '/info' => 'Get information about this chatbot',
            '/time' => 'Show current time',
            '/date' => 'Show current date',
            '/weather' => 'Get weather information',
            '/hello' => 'Say hello',
            '/clear' => 'Clear chat history (client-side)',
        );
    }
}

/**
 * Command Callbacks
 */

function chatbot_cmd_help() {
    $commands = ChatBot_Commands::get_commands();
    $help_text = "📋 **Available Commands:**\n\n";
    foreach ( $commands as $cmd => $desc ) {
        $help_text .= "**{$cmd}** - {$desc}\n";
    }
    $help_text .= "\n💡 Or just type anything to chat with me using AI!";
    return $help_text;
}

function chatbot_cmd_info() {
    return "🤖 I'm Rocket Assist for We Are Team Rocket. I help visitors learn about the company, answer questions, and guide interested leads toward a meeting. Type **/help** to see available commands.";
}

function chatbot_cmd_time() {
    return "⏰ Current time: " . current_time( 'H:i:s' );
}

function chatbot_cmd_date() {
    return "📅 Current date: " . current_time( 'Y-m-d (l)' );
}

function chatbot_cmd_weather() {
    return "🌤️ Weather feature coming soon! For now, check your favorite weather service.";
}

function chatbot_cmd_hello() {
    $greetings = array(
        "Hello! 👋 How can I help you today?",
        "Hi there! 😊 What can I do for you?",
        "Hey! 👋 Great to see you. What's on your mind?",
        "Greetings! 🙌 How can I assist you?",
    );
    return $greetings[ array_rand( $greetings ) ];
}

function chatbot_cmd_clear() {
    return "CLEAR_CHAT"; // Special response to clear chat on frontend
}
