<?php
/**
 * Plugin Updater - Check for updates from GitHub releases
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class ChatBot_Plugin_Updater {
    private static $repo_owner = 'kushanesala';
    private static $repo_name = 'chatbot-openai';
    private static $api_url = 'https://api.github.com/repos/kushanesala/chatbot-openai/releases/latest';
    private static $plugin_file = 'chatbot-openai/chatbot-openai.php';

    public static function init() {
        // Check for updates when WordPress checks
        add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check_for_updates' ) );
        
        // Allow plugin update from GitHub
        add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 10, 3 );
        
        // Perform the actual update
        add_filter( 'upgrader_post_install', array( __CLASS__, 'after_update' ), 10, 3 );
    }

    /**
     * Check for plugin updates from GitHub
     */
    public static function check_for_updates( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $current_version = isset( $transient->checked[ self::$plugin_file ] ) ? $transient->checked[ self::$plugin_file ] : '0';
        $remote_version = self::get_latest_version();

        if ( $remote_version && version_compare( $remote_version, $current_version, '>' ) ) {
            $plugin_data = array(
                'slug'        => 'chatbot-openai',
                'plugin'      => self::$plugin_file,
                'new_version' => $remote_version,
                'url'         => 'https://github.com/' . self::$repo_owner . '/' . self::$repo_name,
                'package'     => self::get_download_url( $remote_version ),
            );

            $transient->response[ self::$plugin_file ] = (object) $plugin_data;
        }

        return $transient;
    }

    /**
     * Get the latest version from GitHub
     */
    private static function get_latest_version() {
        $transient = get_transient( 'chatbot_latest_version' );
        
        if ( false !== $transient ) {
            return $transient;
        }

        $response = wp_remote_get( self::$api_url, array(
            'headers' => array(
                'Accept' => 'application/vnd.github.v3+json',
            ),
            'timeout' => 10,
        ) );

        if ( is_wp_error( $response ) ) {
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        $version = isset( $body['tag_name'] ) ? trim( $body['tag_name'], 'v' ) : false;

        if ( $version ) {
            set_transient( 'chatbot_latest_version', $version, 12 * HOUR_IN_SECONDS );
            return $version;
        }

        return false;
    }

    /**
     * Get download URL for the release
     */
    private static function get_download_url( $version ) {
        return 'https://github.com/' . self::$repo_owner . '/' . self::$repo_name . '/archive/refs/tags/v' . $version . '.zip';
    }

    /**
     * Provide plugin info for update details
     */
    public static function plugin_info( $res, $action, $args ) {
        if ( $action !== 'plugin_information' || $args->slug !== 'chatbot-openai' ) {
            return $res;
        }

        $remote_version = self::get_latest_version();
        if ( ! $remote_version ) {
            return $res;
        }

        $plugin_info = array(
            'name'            => 'OpenAI Chatbot',
            'slug'            => 'chatbot-openai',
            'version'         => $remote_version,
            'author'          => 'Kushan Esala',
            'author_profile'  => 'https://kushanesala.github.io/portfolio/',
            'download_link'   => self::get_download_url( $remote_version ),
            'description'     => 'Custom chatbot integrated with OpenAI API with custom commands and voice support',
            'homepage'        => 'https://github.com/kushanesala/chatbot-openai',
            'requires'        => '5.0',
            'tested'          => get_bloginfo( 'version' ),
            'active_installs' => 0,
            'banners'         => array(),
        );

        return (object) $plugin_info;
    }

    /**
     * After plugin update, clear transients
     */
    public static function after_update( $response, $hook_extra, $result ) {
        if ( isset( $hook_extra['plugin'] ) && self::$plugin_file === $hook_extra['plugin'] ) {
            delete_transient( 'chatbot_latest_version' );
        }
        return $response;
    }
}

ChatBot_Plugin_Updater::init();
