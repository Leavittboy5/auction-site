<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

function stg_process_bid() {
    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'You must be logged in to place a bid.' );
    }

    check_ajax_referer( 'stg_bid_nonce', 'security' );

    $auction_id  = isset( $_POST['auction_id'] ) ? intval( $_POST['auction_id'] ) : 0;
    $new_max_bid = isset( $_POST['bid_amount'] ) ? floatval( $_POST['bid_amount'] ) : 0;
    $user_id     = get_current_user_id();

    if ( ! $auction_id || $new_max_bid <= 0 ) {
        wp_send_json_error( 'Invalid bid data.' );
    }

    $starting_bid    = floatval( get_post_meta( $auction_id, '_stg_starting_bid', true ) );
    $current_bid     = floatval( get_post_meta( $auction_id, '_stg_current_bid', true ) );
    $current_max_bid = floatval( get_post_meta( $auction_id, '_stg_max_bid', true ) );
    $high_bidder_id  = get_post_meta( $auction_id, '_stg_high_bidder', true );
    
    if ( $current_bid == 0 ) {
        $current_bid = $starting_bid;
    }

    $increment = 1.00;
    
    // If the user is the high bidder, they must beat their own max bid. 
    // Otherwise, they must beat the public bid.
    if ( $user_id == $high_bidder_id ) {
        $min_required_bid = $current_max_bid + $increment;
    } else {
        $min_required_bid = ($current_max_bid > 0) ? ($current_bid + $increment) : $starting_bid;
    }

    if ( $new_max_bid < $min_required_bid ) {
        wp_send_json_error( 'Your max bid must be at least $' . number_format($min_required_bid, 2) );
    }

    $is_outbid_immediately = false;
    $outbid_message = '';
    $new_high_bidder_id = $high_bidder_id; 

    // PROXY BIDDING ENGINE
    if ( empty($high_bidder_id) ) {
        // First bid
        $current_bid = $starting_bid; 
        update_post_meta( $auction_id, '_stg_max_bid', $new_max_bid );
        update_post_meta( $auction_id, '_stg_high_bidder', $user_id );
        update_post_meta( $auction_id, '_stg_current_bid', $current_bid );
        $new_high_bidder_id = $user_id;
        
    } else {
        if ( $user_id == $high_bidder_id ) {
            // User increasing their own proxy bid
            update_post_meta( $auction_id, '_stg_max_bid', $new_max_bid );
        } else {
            // New bidder vs existing proxy bid
            if ( $new_max_bid <= $current_max_bid ) {
                // Outbid immediately
                $current_bid = min($new_max_bid + $increment, $current_max_bid);
                update_post_meta( $auction_id, '_stg_current_bid', $current_bid );
                $is_outbid_immediately = true;
                $outbid_message = 'You were immediately outbid by an existing proxy bid. The current bid is now $' . number_format($current_bid, 2);
            } else {
                // New winner
                $current_bid = $current_max_bid + $increment;
                update_post_meta( $auction_id, '_stg_max_bid', $new_max_bid );
                update_post_meta( $auction_id, '_stg_high_bidder', $user_id );
                update_post_meta( $auction_id, '_stg_current_bid', $current_bid );
                $new_high_bidder_id = $user_id;
            }
        }
    }

    $history = get_post_meta( $auction_id, '_stg_bid_history', true );
    if ( !is_array($history) ) $history = array();
    $history[] = array(
        'user_id' => $user_id,
        'amount'  => $new_max_bid, 
        'time'    => current_time('mysql')
    );
    update_post_meta( $auction_id, '_stg_bid_history', $history );

    $end_timestamp = intval( get_post_meta( $auction_id, '_stg_end_date', true ) );
    $current_time  = current_time( 'timestamp' );
    $time_left     = $end_timestamp - $current_time;
    $new_end_timestamp = null;

    if ( $time_left > 0 && $time_left < 120 ) {
        $new_end_timestamp = $end_timestamp + 120;
        update_post_meta( $auction_id, '_stg_end_date', $new_end_timestamp );
    }

    clean_post_cache( $auction_id );
    if ( class_exists( 'LiteSpeed_Cache_API' ) ) {
        LiteSpeed_Cache_API::purge_post( $auction_id );
    }

    // Broadcast to WebSockets with the Winner's ID
    $push_data = array(
        'auctionId'       => $auction_id,
        'bidAmount'       => $current_bid, 
        'highBidderId'    => $new_high_bidder_id,
        'newEndTimestamp' => $new_end_timestamp
    );

    wp_remote_post( 'http://127.0.0.1:3000/api/new-bid', array(
        'method'      => 'POST',
        'timeout'     => 1,
        'redirection' => 1,
        'httpversion' => '1.0',
        'blocking'    => false, 
        'headers'     => array( 'Content-Type' => 'application/json' ),
        'body'        => json_encode( $push_data )
    ));

    if ( $is_outbid_immediately ) {
        wp_send_json_error( array( 
            'message' => $outbid_message,
            'next_min_bid' => $current_bid + $increment
        ) );
    } else {
        $response_data = array(
            'next_min_bid' => ($user_id == $new_high_bidder_id) ? $new_max_bid + $increment : $current_bid + $increment,
            'is_winning'   => true,
            'max_bid'      => $new_max_bid
        );
        if ( $new_end_timestamp ) {
            $response_data['new_end_timestamp'] = $new_end_timestamp;
        }
        wp_send_json_success( $response_data );
    }
}
add_action( 'wp_ajax_stg_place_bid', 'stg_process_bid' );
add_action( 'wp_ajax_nopriv_stg_place_bid', 'stg_process_bid' );