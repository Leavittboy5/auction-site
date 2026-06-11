<?php
/**
 * Plugin Name: St. George Storage Auctions
 * Description: Custom cash-only auction engine and bidder management for St. George Storage.
 * Version: 1.0.0
 * Author: Advanced Realty / You
 * Text Domain: stgeorge-auctions
 */

// Exit if accessed directly for security
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 1. Register the Custom Post Type
require_once plugin_dir_path( __FILE__ ) . 'auction-register.php';

// 2. Add Auction Meta Boxes (Pricing and Dates)
require_once plugin_dir_path( __FILE__ ) . 'auction-meta.php';

// 3. Add User Banning System
require_once plugin_dir_path( __FILE__ ) . 'auction-banning.php';

// 4. Front-End Display & Bidding Engine
require_once plugin_dir_path( __FILE__ ) . 'auction-ajax.php';
require_once plugin_dir_path( __FILE__ ) . 'auction-shortcode.php';

// 5. Registration Form Modifications (T&C Checkbox)
require_once plugin_dir_path( __FILE__ ) . 'auction-registration.php';

// 6. Beautify Login and Registration Screens
require_once plugin_dir_path( __FILE__ ) . 'auction-styling.php';

// 7. Admin Reports
require_once plugin_dir_path( __FILE__ ) . 'auction-reports.php';

// 8. Mass Upload Page
require_once plugin_dir_path( __FILE__ ) . 'auction-mass-upload.php';

// AJAX Action for Stripe Deposit
add_action('wp_ajax_stg_process_deposit', 'stg_process_deposit_ajax');
function stg_process_deposit_ajax() {
    check_ajax_referer('stg_deposit_nonce', 'security');
    if (!current_user_can('edit_posts')) wp_send_json_error('Unauthorized');

    $post_id = intval($_POST['post_id']);
    $type = sanitize_text_field($_POST['type']);
    $intent_id = get_post_meta($post_id, '_stg_stripe_intent_id', true);

    if (!$intent_id) wp_send_json_error('No Stripe Intent found for this auction.');

    // Mock Stripe logic:
    if ($type === 'release') {
        // \Stripe\Stripe::setApiKey('sk_test_mock_key');
        // $intent = \Stripe\PaymentIntent::retrieve($intent_id);
        // $intent->cancel();
        update_post_meta($post_id, '_stg_cleaning_deposit_held', 'released');
        wp_send_json_success('Hold released successfully. The $100 will drop off the customer\'s statement.');
    } elseif ($type === 'capture') {
        // \Stripe\Stripe::setApiKey('sk_test_mock_key');
        // $intent = \Stripe\PaymentIntent::retrieve($intent_id);
        // $intent->capture(['amount_to_capture' => 10000]); // Capture $100
        update_post_meta($post_id, '_stg_cleaning_deposit_held', 'captured');
        wp_send_json_success('Hold captured. The $100 has been charged to the customer.');
    }
}

// Enqueue Socket.io
add_action('wp_enqueue_scripts', 'stg_enqueue_socket_io');
function stg_enqueue_socket_io() {
    wp_enqueue_script('socket-io', 'https://cdn.socket.io/4.7.2/socket.io.min.js', array(), null, true);
}

add_filter( 'registration_errors', function( $errors ) {
    if ( empty( $errors->get_error_codes() ) && isset( $_POST['user_login'] ) ) {
        // This is a trick to show a custom message on the login screen after signup
        add_filter( 'wp_login_errors', function( $login_errors ) {
            return new WP_Error( 'success', '<strong>Success!</strong> Your bidder account is created. Please check your email to set your password before bidding.', 'message' );
        });
    }
    return $errors;
}, 10 );

add_filter( 'wp_new_user_notification_email', function( $wp_new_user_notification_email, $user, $blogname ) {
    $wp_new_user_notification_email['subject'] = 'Welcome to St. George Storage Auctions!';
    $wp_new_user_notification_email['message'] = "Hello " . $user->user_login . ",\n\n" .
        "Thank you for registering. You are now authorized to bid on our storage units.\n\n" .
        "IMPORTANT: All winning bids must be paid in CASH at our main office.\n\n" .
        "Click the link below to set your password and start bidding:\n" .
        '<' . network_site_url("wp-login.php?action=rp&key=" . get_password_reset_key( $user ) . "&login=" . rawurlencode($user->user_login), 'login') . ">\n\n" .
        "Good luck!";
    return $wp_new_user_notification_email;
}, 10, 3 );
// Login Redirect Filter
add_filter( 'login_redirect', function( $redirect_to, $request, $user ) {
    if ( isset( $user->roles ) && is_array( $user->roles ) ) {
        if ( in_array( 'administrator', $user->roles ) ) {
            return admin_url();
        } else {
            return 'https://auction.stgeorgestorage.com';
        }
    }
    return $redirect_to;
}, 10, 3 );

// Custom Cron Schedules
add_filter('cron_schedules', 'stg_add_cron_schedules');
function stg_add_cron_schedules($schedules) {
    if (!isset($schedules['15_minutes'])) {
        $schedules['15_minutes'] = array(
            'interval' => 15 * 60,
            'display' => __('Once Every 15 Minutes')
        );
    }
    return $schedules;
}

// Winner Email Cron
if ( ! wp_next_scheduled( 'stg_hourly_auction_cron' ) ) {
    wp_schedule_event( time(), 'hourly', 'stg_hourly_auction_cron' );
}

if ( ! wp_next_scheduled( 'stg_lien_sync_cron' ) ) {
    wp_schedule_event( time(), 'hourly', 'stg_lien_sync_cron' );
}

if ( ! wp_next_scheduled( 'stg_tenant_paid_kill_switch_cron' ) ) {
    wp_schedule_event( time(), '15_minutes', 'stg_tenant_paid_kill_switch_cron' );
}

add_action( 'stg_hourly_auction_cron', 'stg_process_ended_auctions_emails' );
function stg_process_ended_auctions_emails() {
    $args = array(
        'post_type'      => 'storage_auction',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'meta_query'     => array(
            array(
                'key'     => '_stg_winner_emailed',
                'compare' => 'NOT EXISTS',
            ),
        ),
    );

    $auctions = new WP_Query( $args );

    if ( $auctions->have_posts() ) {
        $current_time = current_time( 'timestamp' );

        while ( $auctions->have_posts() ) {
            $auctions->the_post();
            $auction_id = get_the_ID();
            
            $end_date_string = get_post_meta( $auction_id, '_stg_end_date', true );
            if ( empty( $end_date_string ) ) {
                continue;
            }

            $end_timestamp = strtotime( $end_date_string );

            if ( $current_time >= $end_timestamp ) {
                $winner_id = get_post_meta( $auction_id, '_stg_high_bidder', true );
                
                if ( $winner_id ) {
                    $winner_info = get_userdata( $winner_id );
                    
                    if ( $winner_info ) {
                        $winning_bid = floatval( get_post_meta( $auction_id, '_stg_current_bid', true ) );
                        $fee         = $winning_bid * 0.15;
                        $deposit     = 35.00;
                        $cleaning_deposit = 100.00;
                        $total       = $winning_bid + $fee + $deposit + $cleaning_deposit;
                        
                        // Stripe Mock Implementation:
                        // 1. Authorize total amount (capture_method: manual)
                        // 2. Capture the 15% immediately
                        $customer_id = get_user_meta($winner_id, 'stg_stripe_customer_id', true);
                        if ($customer_id) {
                            // In a real integration:
                            // \Stripe\Stripe::setApiKey('sk_test_mock_key');
                            // $intent = \Stripe\PaymentIntent::create([
                            //     'amount' => $total * 100,
                            //     'currency' => 'usd',
                            //     'customer' => $customer_id,
                            //     'payment_method' => get_user_meta($winner_id, 'stg_stripe_payment_method', true),
                            //     'capture_method' => 'manual',
                            //     'confirm' => true,
                            //     'off_session' => true
                            // ]);
                            // $intent->capture(['amount_to_capture' => $fee * 100]);
                            
                            update_post_meta( $auction_id, '_stg_stripe_intent_id', 'pi_mock_intent_123' );
                            update_post_meta( $auction_id, '_stg_cleaning_deposit_held', 'yes' );
                        }

                        $subject = "You won! Storage Unit Auction";
                        $message = "You won! A 15% buyer premium ($" . number_format($fee, 2) . ") has been charged to your card. A $100 cleaning deposit hold has also been placed. Remaining balance: $" . number_format($winning_bid + $deposit, 2) . " due in CASH at our main office (1156 E 700 S Ste. 1). You have 3 days to empty the unit.";
                        
                        wp_mail( $winner_info->user_email, $subject, $message );
                    }
                }
                
                update_post_meta( $auction_id, '_stg_winner_emailed', '1' );
            }
        }
        wp_reset_postdata();
    }
}

// SiteLink / storEDGE Sync (Phase 4)
add_action( 'stg_lien_sync_cron', 'stg_process_lien_sync' );
function stg_process_lien_sync() {
    // MOCK API Call
    $response = wp_remote_get( 'https://mock-api.sitelink.com/Units/AuctionStatus' );
    
    // Simulating API Response Data:
    $mock_data = array(
        array('UnitID' => 'A101', 'Facility' => 'Classic', 'Status' => 'Auction Scheduled', 'StartDate' => date('Y-m-d', strtotime('+1 week'))),
        array('UnitID' => 'B205', 'Facility' => 'Secure', 'Status' => 'Auction Scheduled', 'StartDate' => date('Y-m-d', strtotime('+1 week'))),
    );
    
    foreach ($mock_data as $unit) {
        if ($unit['Status'] === 'Auction Scheduled') {
            // Check if already exists
            $existing = new WP_Query(array(
                'post_type' => 'storage_auction',
                'meta_key' => '_stg_unit_id',
                'meta_value' => $unit['UnitID'],
                'post_status' => 'any'
            ));
            
            if (!$existing->have_posts()) {
                // Auto-create draft
                $post_id = wp_insert_post(array(
                    'post_title' => 'Unit #' . $unit['UnitID'],
                    'post_type' => 'storage_auction',
                    'post_status' => 'draft',
                ));
                
                update_post_meta($post_id, '_stg_unit_id', $unit['UnitID']);
                update_post_meta($post_id, '_stg_facility', $unit['Facility']);
                update_post_meta($post_id, '_stg_starting_bid', '10.00'); // default
                update_post_meta($post_id, '_stg_end_date', date('Y-m-d\TH:i', strtotime($unit['StartDate'] . ' + 3 days')));
            }
        }
    }
}

add_action( 'stg_tenant_paid_kill_switch_cron', 'stg_process_tenant_paid_kill_switch' );
function stg_process_tenant_paid_kill_switch() {
    // Get all active (published) auctions
    $args = array(
        'post_type' => 'storage_auction',
        'post_status' => 'publish',
        'posts_per_page' => -1,
    );
    
    $auctions = new WP_Query($args);
    if ($auctions->have_posts()) {
        while ($auctions->have_posts()) {
            $auctions->the_post();
            $auction_id = get_the_ID();
            $unit_id = get_post_meta($auction_id, '_stg_unit_id', true);
            
            // MOCK API Call to check delinquency
            // $response = wp_remote_get( "https://mock-api.sitelink.com/Units/$unit_id/Status" );
            
            // Simulating tenant paid (for unit B205 randomly as an example, but we'll mock it generally false except if we trigger it)
            // Let's pretend unit A999 paid:
            if ($unit_id === 'A999') {
                $is_paid = true;
                
                if ($is_paid) {
                    // 1. Set post to private
                    wp_update_post(array(
                        'ID' => $auction_id,
                        'post_status' => 'private'
                    ));
                    
                    // 2. Notify all bidders
                    $history = get_post_meta( $auction_id, '_stg_bid_history', true );
                    if (is_array($history)) {
                        $notified_users = array();
                        foreach ($history as $bid) {
                            $uid = $bid['user_id'];
                            if (!in_array($uid, $notified_users)) {
                                $user = get_userdata($uid);
                                if ($user) {
                                    wp_mail($user->user_email, "Auction Cancelled: Unit #$unit_id", "The tenant has paid their balance. The auction for Unit #$unit_id has been cancelled.");
                                }
                                $notified_users[] = $uid;
                            }
                        }
                    }
                }
            }
        }
        wp_reset_postdata();
    }
}
