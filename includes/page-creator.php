<?php
/**
 * Auto-create test page on plugin activation
 */

function chatbot_create_test_page() {
    // Check if test page already exists
    $args = array(
        'post_type' => 'page',
        'meta_query' => array(
            array(
                'key' => '_chatbot_test_page',
                'value' => '1'
            )
        )
    );
    
    $pages = get_posts( $args );
    
    if ( ! empty( $pages ) ) {
        return; // Page already exists
    }
    
    // Create test page
    $page_id = wp_insert_post( array(
        'post_type' => 'page',
        'post_title' => 'Chat with Our AI Assistant',
        'post_content' => '[chatbot]',
        'post_status' => 'publish',
    ) );
    
    if ( $page_id ) {
        add_post_meta( $page_id, '_chatbot_test_page', '1' );
    }
}

// Run on plugin activation
register_activation_hook( __FILE__, 'chatbot_create_test_page' );

// Also run on admin_init to create if missing
add_action( 'admin_init', 'chatbot_create_test_page' );
