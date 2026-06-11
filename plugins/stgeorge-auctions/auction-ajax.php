<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function stg_process_bid() {
    // 1. MUST CHECK IF LOGGED IN FIRST (To avoid 403 for guests)
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'You must be logged in to place a bid.' );
    }

    // 2. Security Check (Ensure this matches the 'stg_auction_nonce' used in your main plugin file)
    check_ajax_referer( 'stg_auction_nonce', 'security' );

    $auction_id = isset( $_POST['auction_id'] ) ? intval( $_POST['auction_id'] ) : 0;
    $bid_amount = isset( $_POST['bid_amount'] ) ? floatval( $_POST['bid_amount'] ) : 0;

    if ( ! $auction_id || $bid_amount <= 0 ) {
        wp_send_json_error( 'Invalid bid data.' );
    }

    // Get current bid from DB
    $current_bid = floatval( get_post_meta( $auction_id, '_stg_current_bid', true ) );
    
    if ( $bid_amount <= $current_bid ) {
        wp_send_json_error( 'Your bid must be higher than the current bid of $' . number_format($current_bid, 2) );
    }

    // Update the bid in WordPress
    update_post_meta( $auction_id, '_stg_current_bid', $bid_amount );

    // Soft-Close / Anti-Sniping Logic
    $end_timestamp = intval( get_post_meta( $auction_id, '_stg_end_date', true ) );
    $current_time  = current_time( 'timestamp' );
    $time_left     = $end_timestamp - $current_time;

    $new_end_timestamp = null;
    if ( $time_left > 0 && $time_left < 120 ) {
        $new_end_timestamp = $end_timestamp + 120;
        update_post_meta( $auction_id, '_stg_end_date', $new_end_timestamp );
    }

    // --- LITESPEED CACHE FIXES ---
    // Clear standard WP cache
    clean_post_cache( $auction_id );

    // FORCE LiteSpeed to purge this specific auction page immediately
    if ( class_exists( 'LiteSpeed_Cache_API' ) ) {
        LiteSpeed_Cache_API::purge_post( $auction_id );
    }
    // ----------------------------

    // --- REAL-TIME BRIDGE TO NODE.JS ---
    $push_data = array(
        'auctionId'       => $auction_id,
        'bidAmount'       => $bid_amount,
        'newEndTimestamp' => $new_end_timestamp
    );

    // Using the internal bridge to trigger the live update
    wp_remote_post( 'http://127.0.0.1:3000/api/new-bid', array(
        'method'      => 'POST',
        'timeout'     => 1,
        'redirection' => 1,
        'httpversion' => '1.0',
        'blocking'    => false, 
        'headers'     => array( 'Content-Type' => 'application/json' ),
        'body'        => json_encode( $push_data )
    ));

    // Send success back to the user's browser
    $response_data = array(
        'next_min_bid' => $bid_amount + 1 
    );

    if ( $new_end_timestamp ) {
        $response_data['new_end_timestamp'] = $new_end_timestamp;
    }

    wp_send_json_success( $response_data );
}
add_action( 'wp_ajax_stg_place_bid', 'stg_process_bid' );
add_action( 'wp_ajax_nopriv_stg_place_bid', 'stg_process_bid' );