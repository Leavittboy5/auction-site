<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add "Ban Bidder" checkbox to the User Profile screen
 */
function stg_add_banned_user_field( $user ) {
    if ( ! current_user_can( 'edit_user', $user->ID ) ) {
        return;
    }

    $is_banned = get_user_meta( $user->ID, 'stg_banned_from_bidding', true );
    ?>
    <div id="stg-auction-management-section">
        <h3>Auction Management (St. George Storage)</h3>
        <table class="form-table">
            <tr>
                <th><label for="stg_banned_from_bidding">Ban Bidder</label></th>
                <td>
                    <input type="checkbox" name="stg_banned_from_bidding" id="stg_banned_from_bidding" value="1" <?php checked( $is_banned, '1' ); ?> />
                    <span class="description" style="color: #d63638; font-weight: 600;">
                        Check this box to prevent this user from placing bids. This also permanently blacklists their email from future registration.
                    </span>
                </td>
            </tr>
        </table>
    </div>
    <?php
}
add_action( 'show_user_profile', 'stg_add_banned_user_field' );
add_action( 'edit_user_profile', 'stg_add_banned_user_field' );

/**
 * Save the "Ban Bidder" status AND update the Global Email Blacklist
 */
function stg_save_banned_user_field( $user_id ) {
    if ( ! current_user_can( 'edit_user', $user_id ) ) {
        return false;
    }

    $user_info = get_userdata( $user_id );
    $user_email = $user_info->user_email;
    
    // Get the current list of permanently banned emails
    $banned_emails = get_option( 'stg_banned_emails_list', array() );

    if ( isset( $_POST['stg_banned_from_bidding'] ) ) {
        update_user_meta( $user_id, 'stg_banned_from_bidding', '1' );

        // Add email to the permanent blacklist if it isn't there already
        if ( ! in_array( $user_email, $banned_emails ) ) {
            $banned_emails[] = $user_email;
            update_option( 'stg_banned_emails_list', $banned_emails );
        }
    } else {
        delete_user_meta( $user_id, 'stg_banned_from_bidding' );

        // If you UNBAN them, remove their email from the blacklist
        $key = array_search( $user_email, $banned_emails );
        if ( $key !== false ) {
            unset( $banned_emails[$key] );
            update_option( 'stg_banned_emails_list', array_values( $banned_emails ) ); // Re-index array
        }
    }
}
add_action( 'personal_options_update', 'stg_save_banned_user_field' );
add_action( 'edit_user_profile_update', 'stg_save_banned_user_field' );

/**
 * Block Blacklisted Emails During Registration
 */
function stg_prevent_banned_email_registration( $errors, $sanitized_user_login, $user_email ) {
    $banned_emails = get_option( 'stg_banned_emails_list', array() );

    // If their email is on the blacklist, stop the registration and show an error
    if ( in_array( $user_email, $banned_emails ) ) {
        $errors->add( 'email_banned', '<strong>Error</strong>: This email address has been restricted from participating in St. George Storage auctions due to a previous terms violation.' );
    }

    return $errors;
}
add_filter( 'registration_errors', 'stg_prevent_banned_email_registration', 10, 3 );