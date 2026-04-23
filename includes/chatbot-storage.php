<?php
/**
 * Chatbot storage, logging, and export helpers.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function chatbot_get_db_version() {
    return '1.0.0';
}

function chatbot_get_sessions_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'chatbot_sessions';
}

function chatbot_get_messages_table_name() {
    global $wpdb;
    return $wpdb->prefix . 'chatbot_messages';
}

function chatbot_install_storage_tables() {
    global $wpdb;

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();
    $sessions_table = chatbot_get_sessions_table_name();
    $messages_table = chatbot_get_messages_table_name();

    $sessions_sql = "CREATE TABLE {$sessions_table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_key VARCHAR(191) NOT NULL,
        visitor_id VARCHAR(191) NULL,
        user_id BIGINT(20) UNSIGNED NULL,
        page_url TEXT NULL,
        referrer TEXT NULL,
        status VARCHAR(32) NOT NULL DEFAULT 'open',
        message_count INT UNSIGNED NOT NULL DEFAULT 0,
        first_message LONGTEXT NULL,
        last_message LONGTEXT NULL,
        created_at DATETIME NOT NULL,
        updated_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY session_key (session_key),
        KEY visitor_id (visitor_id),
        KEY user_id (user_id),
        KEY status (status),
        KEY created_at (created_at)
    ) {$charset_collate};";

    $messages_sql = "CREATE TABLE {$messages_table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id BIGINT(20) UNSIGNED NOT NULL,
        role VARCHAR(20) NOT NULL,
        message_text LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL,
        PRIMARY KEY  (id),
        KEY session_id (session_id),
        KEY role (role),
        KEY created_at (created_at)
    ) {$charset_collate};";

    dbDelta( $sessions_sql );
    dbDelta( $messages_sql );

    update_option( 'chatbot_db_version', chatbot_get_db_version() );
}

function chatbot_maybe_upgrade_storage_tables() {
    $stored_version = (string) get_option( 'chatbot_db_version', '' );

    if ( chatbot_get_db_version() !== $stored_version ) {
        chatbot_install_storage_tables();
    }
}
add_action( 'init', 'chatbot_maybe_upgrade_storage_tables' );

function chatbot_normalize_storage_text( $text ) {
    return trim( wp_strip_all_tags( (string) $text ) );
}

function chatbot_infer_chat_status( $message ) {
    $text = strtolower( trim( (string) $message ) );

    $negative_terms = array( 'angry', 'upset', 'bad', 'worst', 'hate', 'annoyed', 'frustrated', 'not happy', 'complaint' );
    foreach ( $negative_terms as $term ) {
        if ( false !== strpos( $text, $term ) ) {
            return 'negative';
        }
    }

    $ready_terms = array( 'book', 'schedule', 'meeting', 'call', 'demo', 'calendar', 'calendar link', 'book a meeting', 'let us meet', 'let\'s meet' );
    foreach ( $ready_terms as $term ) {
        if ( false !== strpos( $text, $term ) ) {
            return 'ready_for_meeting';
        }
    }

    $interest_terms = array( 'interested', 'pricing', 'price', 'cost', 'services', 'proposal', 'quote', 'lead', 'generate', 'contact', 'details' );
    foreach ( $interest_terms as $term ) {
        if ( false !== strpos( $text, $term ) ) {
            return 'interested';
        }
    }

    return 'active';
}

function chatbot_get_or_create_session_row( $session_key, $context = array() ) {
    global $wpdb;

    $sessions_table = chatbot_get_sessions_table_name();
    $session_key = sanitize_text_field( (string) $session_key );

    if ( '' === $session_key ) {
        $session_key = 'chatbot_' . wp_generate_uuid4();
    }

    $existing = $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$sessions_table} WHERE session_key = %s", $session_key ),
        ARRAY_A
    );

    if ( $existing ) {
        return $existing;
    }

    $now = current_time( 'mysql' );
    $wpdb->insert(
        $sessions_table,
        array(
            'session_key'   => $session_key,
            'visitor_id'    => sanitize_text_field( $context['visitor_id'] ?? '' ),
            'user_id'       => isset( $context['user_id'] ) ? absint( $context['user_id'] ) : null,
            'page_url'      => esc_url_raw( $context['page_url'] ?? '' ),
            'referrer'      => esc_url_raw( $context['referrer'] ?? '' ),
            'status'        => sanitize_text_field( $context['status'] ?? 'open' ),
            'message_count' => 0,
            'first_message' => sanitize_textarea_field( $context['first_message'] ?? '' ),
            'last_message'  => sanitize_textarea_field( $context['last_message'] ?? '' ),
            'created_at'    => $now,
            'updated_at'    => $now,
        ),
        array( '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
    );

    $session_id = (int) $wpdb->insert_id;

    return $wpdb->get_row(
        $wpdb->prepare( "SELECT * FROM {$sessions_table} WHERE id = %d", $session_id ),
        ARRAY_A
    );
}

function chatbot_log_chat_message( $session_key, $role, $message_text, $context = array() ) {
    if ( '1' !== (string) get_option( 'chatbot_enable_chat_logging', '1' ) ) {
        return false;
    }

    global $wpdb;

    $sessions_table = chatbot_get_sessions_table_name();
    $messages_table = chatbot_get_messages_table_name();
    $message_text = chatbot_normalize_storage_text( $message_text );

    if ( '' === $message_text ) {
        return false;
    }

    $session_row = chatbot_get_or_create_session_row(
        $session_key,
        array(
            'visitor_id'    => $context['visitor_id'] ?? '',
            'user_id'       => $context['user_id'] ?? null,
            'page_url'      => $context['page_url'] ?? '',
            'referrer'      => $context['referrer'] ?? '',
            'status'        => $context['status'] ?? 'open',
            'first_message' => $message_text,
            'last_message'  => $message_text,
        )
    );

    if ( ! $session_row || empty( $session_row['id'] ) ) {
        return false;
    }

    $session_id = (int) $session_row['id'];
    $now = current_time( 'mysql' );

    $wpdb->insert(
        $messages_table,
        array(
            'session_id'    => $session_id,
            'role'          => sanitize_key( $role ),
            'message_text'  => $message_text,
            'created_at'    => $now,
        ),
        array( '%d', '%s', '%s', '%s' )
    );

    $message_count = (int) $session_row['message_count'] + 1;
    $update_data = array(
        'message_count' => $message_count,
        'last_message'  => $message_text,
        'updated_at'    => $now,
    );

    if ( '' === trim( (string) $session_row['first_message'] ) ) {
        $update_data['first_message'] = $message_text;
    }

    if ( ! empty( $context['status'] ) ) {
        $update_data['status'] = sanitize_text_field( $context['status'] );
    }

    $wpdb->update(
        $sessions_table,
        $update_data,
        array( 'id' => $session_id ),
        array_fill( 0, count( $update_data ), '%s' ),
        array( '%d' )
    );

    return (int) $wpdb->insert_id;
}

function chatbot_get_chat_export_rows( $filters = array() ) {
    global $wpdb;

    $sessions_table = chatbot_get_sessions_table_name();
    $messages_table = chatbot_get_messages_table_name();

    $where = array( '1=1' );
    $params = array();

    if ( ! empty( $filters['from'] ) ) {
        $where[] = 's.created_at >= %s';
        $params[] = $filters['from'] . ' 00:00:00';
    }

    if ( ! empty( $filters['to'] ) ) {
        $where[] = 's.created_at <= %s';
        $params[] = $filters['to'] . ' 23:59:59';
    }

    $sql = "SELECT
            s.id AS session_id,
            s.session_key,
            s.visitor_id,
            s.user_id,
            s.page_url,
            s.referrer,
            s.status AS session_status,
            s.message_count AS session_message_count,
            s.first_message AS session_first_message,
            s.last_message AS session_last_message,
            s.created_at AS session_created_at,
            s.updated_at AS session_updated_at,
            m.id AS message_id,
            m.role AS message_role,
            m.message_text,
            m.created_at AS message_created_at
        FROM {$sessions_table} s
        INNER JOIN {$messages_table} m ON m.session_id = s.id
        WHERE " . implode( ' AND ', $where ) . "
        ORDER BY s.created_at ASC, m.created_at ASC, m.id ASC";

    if ( ! empty( $params ) ) {
        $sql = $wpdb->prepare( $sql, $params );
    }

    return $wpdb->get_results( $sql, ARRAY_A );
}

function chatbot_handle_export_csv() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to export chat history.', 'chatbot-openai' ) );
    }

    check_admin_referer( 'chatbot_export_chat_history', 'chatbot_export_nonce' );

    $rows = chatbot_get_chat_export_rows();
    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename=chatbot-history-' . gmdate( 'Y-m-d-His' ) . '.csv' );

    $output = fopen( 'php://output', 'w' );

    fputcsv(
        $output,
        array(
            'session_id',
            'session_key',
            'visitor_id',
            'user_id',
            'page_url',
            'referrer',
            'session_status',
            'session_message_count',
            'session_first_message',
            'session_last_message',
            'session_created_at',
            'session_updated_at',
            'message_id',
            'message_role',
            'message_text',
            'message_created_at',
        )
    );

    foreach ( $rows as $row ) {
        fputcsv(
            $output,
            array(
                $row['session_id'],
                $row['session_key'],
                $row['visitor_id'],
                $row['user_id'],
                $row['page_url'],
                $row['referrer'],
                $row['session_status'],
                $row['session_message_count'],
                $row['session_first_message'],
                $row['session_last_message'],
                $row['session_created_at'],
                $row['session_updated_at'],
                $row['message_id'],
                $row['message_role'],
                $row['message_text'],
                $row['message_created_at'],
            )
        );
    }

    fclose( $output );
    exit;
}
add_action( 'admin_post_chatbot_export_chat_csv', 'chatbot_handle_export_csv' );