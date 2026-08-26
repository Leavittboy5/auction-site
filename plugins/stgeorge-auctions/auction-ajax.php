<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function stg_process_bid() {
    header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
    header("Pragma: no-cache");

    if ( ! is_user_logged_in() ) {
        wp_send_json_error( 'You must be logged in to place a bid.' );
    }

    check_ajax_referer( 'stg_bid_nonce', 'nonce' );

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
    
    $previous_high_bidder_id = $high_bidder_id;

    if ( $current_bid == 0 ) {
        $current_bid = $starting_bid;
    }

    $increment = 1.00;
    
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

    if ( empty($high_bidder_id) ) {
        $current_bid = $starting_bid; 
        update_post_meta( $auction_id, '_stg_max_bid', $new_max_bid );
        update_post_meta( $auction_id, '_stg_high_bidder', $user_id );
        update_post_meta( $auction_id, '_stg_current_bid', $current_bid );
        $new_high_bidder_id = $user_id;
    } else {
        if ( $user_id == $high_bidder_id ) {
            update_post_meta( $auction_id, '_stg_max_bid', $new_max_bid );
        } else {
            if ( $new_max_bid < $current_max_bid ) {
                $current_bid = min($new_max_bid + $increment, $current_max_bid);
                update_post_meta( $auction_id, '_stg_current_bid', $current_bid );
                $is_outbid_immediately = true;
                $outbid_message = 'You were immediately outbid by an existing proxy bid. The current bid is now $' . number_format($current_bid, 2);
            } elseif ( $new_max_bid == $current_max_bid ) {
                $current_bid = $new_max_bid;
                update_post_meta( $auction_id, '_stg_current_bid', $current_bid );
                $new_high_bidder_id = $high_bidder_id;
                $is_outbid_immediately = true;
                $outbid_message = 'You were immediately outbid by an existing proxy bid. The current bid is now $' . number_format($current_bid, 2);
            } else {
                if ( $new_max_bid > $current_max_bid ) {
                    $current_bid = $current_max_bid + $increment;
                } else {
                    $current_bid = $new_max_bid;
                }
                update_post_meta( $auction_id, '_stg_max_bid', $new_max_bid );
                update_post_meta( $auction_id, '_stg_high_bidder', $user_id );
                update_post_meta( $auction_id, '_stg_current_bid', $current_bid );
                $new_high_bidder_id = $user_id;
            }
        }
    }

    if ( ! empty($previous_high_bidder_id) && $previous_high_bidder_id != $new_high_bidder_id ) {
        $prev_user = get_userdata( $previous_high_bidder_id );
        if ( $prev_user ) {
            $unit_id = get_post_meta( $auction_id, '_stg_unit_id', true );
            $unit_title = !empty($unit_id) ? "Unit #" . $unit_id : "Upcoming Unit";
            $subject = "You have been outbid on " . $unit_title;
            $message = "You have been outbid on " . $unit_title . ". The new current bid is $" . number_format($current_bid, 2) . ". Log in to place a higher bid.";
            wp_mail( $prev_user->user_email, $subject, $message );
        }
    }

    // --- 15-SECOND ANTI-SNIPING EXTENSION RULE ---
    $end_date_meta = get_post_meta( $auction_id, '_stg_end_date', true );
    $end_timestamp = !empty($end_date_meta) ? strtotime($end_date_meta . ' ' . wp_timezone_string()) : 0;
    $current_time  = time();
    $time_left     = $end_timestamp - $current_time;
    $new_end_timestamp = $end_timestamp;

    if ( $time_left > 0 && $time_left <= 15 ) {
        $new_end_timestamp = $current_time + 15;
        update_post_meta( $auction_id, '_stg_end_date', wp_date('Y-m-d H:i:s', $new_end_timestamp) );
    }

    clean_post_cache( $auction_id );

    $push_data = array(
        'auctionId'            => $auction_id,
        'bidAmount'            => $current_bid, 
        'highBidderId'         => $new_high_bidder_id,
        'previousHighBidderId' => $previous_high_bidder_id,
        'newEndTimestamp'      => $new_end_timestamp
    );

    wp_remote_post( 'http://127.0.0.1:8080/api/new-bid', array(
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
            'state'   => array(
                'auctionId'  => $auction_id,
                'currentBid' => $current_bid,
                'fee'        => $current_bid * 0.15,
                'totalDue'   => $current_bid + ($current_bid * 0.15) + 35.00,
                'nextMinBid' => $current_bid + 1.00
            )
        ) );
    } else {
        wp_send_json_success( array(
            'message' => 'Bid placed successfully',
            'max_bid' => floatval( get_post_meta( $auction_id, '_stg_max_bid', true ) )
        ) );
    }
}
add_action( 'wp_ajax_stg_place_bid', 'stg_process_bid' );
add_action( 'wp_ajax_nopriv_stg_place_bid', 'stg_process_bid' );
