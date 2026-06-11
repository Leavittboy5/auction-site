<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add Report Pages to the Admin Menu
 */
function stg_register_report_pages() {
    add_submenu_page(
        'edit.php?post_type=storage_auction',
        'Winner Report',
        'Winner Report',
        'manage_options',
        'stg-winner-report',
        'stg_render_winner_report_page'
    );
    add_submenu_page(
        'edit.php?post_type=storage_auction',
        'Bidding History',
        'Bidding History',
        'manage_options',
        'stg-bidding-history',
        'stg_render_bidding_history_page'
    );
    add_submenu_page(
        'edit.php?post_type=storage_auction',
        'User Directory',
        'User Directory',
        'manage_options',
        'stg-user-directory',
        'stg_render_user_directory_page'
    );
}
add_action( 'admin_menu', 'stg_register_report_pages' );

/**
 * Enqueue DataTables
 */
function stg_enqueue_report_assets() {
    if ( ! isset($_GET['page']) || ! in_array( $_GET['page'], array('stg-winner-report', 'stg-bidding-history', 'stg-user-directory') ) ) {
        return;
    }
    wp_enqueue_script( 'jquery' );
    wp_enqueue_style( 'datatables-css', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css' );
    wp_enqueue_style( 'datatables-buttons-css', 'https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css' );
    wp_enqueue_script( 'datatables-js', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', array('jquery'), null, true );
    wp_enqueue_script( 'datatables-buttons-js', 'https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js', array('datatables-js'), null, true );
    wp_enqueue_script( 'jszip', 'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js', array(), null, true );
    wp_enqueue_script( 'pdfmake', 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js', array(), null, true );
    wp_enqueue_script( 'pdfmake-vfs', 'https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js', array(), null, true );
    wp_enqueue_script( 'datatables-buttons-html5', 'https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js', array('datatables-buttons-js'), null, true );
    wp_enqueue_script( 'datatables-buttons-print', 'https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js', array('datatables-buttons-js'), null, true );
    wp_enqueue_script( 'datatables-rowgroup', 'https://cdn.datatables.net/rowgroup/1.4.0/js/dataTables.rowGroup.min.js', array('datatables-js'), null, true );
    wp_enqueue_style( 'datatables-rowgroup-css', 'https://cdn.datatables.net/rowgroup/1.4.0/css/rowGroup.dataTables.min.css' );
}
add_action( 'admin_enqueue_scripts', 'stg_enqueue_report_assets' );

/**
 * Render the Winner Report Page
 */
function stg_render_winner_report_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    echo '<div class="wrap">';
    echo '<h1 style="display:inline-block; margin-right: 20px;">Auction Winner Report</h1>';
    echo '<button class="button button-primary" onclick="window.print()" style="margin-bottom: 20px;">Print Report</button>';

    $args = array(
        'post_type'      => 'storage_auction',
        'posts_per_page' => -1,
        'post_status'    => 'any'
    );
    $auctions = new WP_Query( $args );

    if ( $auctions->have_posts() ) {
        echo '<table id="stg-winner-report-table" class="wp-list-table widefat fixed striped table-view-list display">';
        echo '<thead><tr>';
        echo '<th>Auction Title</th>';
        echo '<th>Winner Name</th>';
        echo '<th>Phone Number</th>';
        echo '<th>Winning Bid</th>';
        echo '<th>15% Fee</th>';
        echo '<th>Total Due</th>';
        echo '</tr></thead><tbody>';

        while ( $auctions->have_posts() ) {
            $auctions->the_post();
            $auction_id  = get_the_ID();
            $winner_id   = get_post_meta( $auction_id, '_stg_high_bidder', true );

            if ( ! empty( $winner_id ) ) {
                $winner_info = get_userdata( $winner_id );
                $winner_name = $winner_info ? $winner_info->display_name : 'Unknown';
                $winner_phone = get_user_meta( $winner_id, 'stg_phone', true );

                $winning_bid = floatval( get_post_meta( $auction_id, '_stg_current_bid', true ) );
                $fee = $winning_bid * 0.15;
                $total_due = $winning_bid + $fee + 35.00;

                echo '<tr>';
                echo '<td><a href="' . get_edit_post_link( $auction_id ) . '">' . get_the_title() . '</a></td>';
                echo '<td>' . esc_html( $winner_name ) . '</td>';
                echo '<td>' . esc_html( $winner_phone ) . '</td>';
                echo '<td>$' . number_format( $winning_bid, 2 ) . '</td>';
                echo '<td>$' . number_format( $fee, 2 ) . '</td>';
                echo '<td><strong>$' . number_format( $total_due, 2 ) . '</strong></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        wp_reset_postdata();

        // Initialize DataTables
        ?>
        <script>
            jQuery(document).ready(function($) {
                $('#stg-winner-report-table').DataTable({
                    dom: 'Bfrtip',
                    buttons: [
                        'copy', 'csv', 'excel', 'pdf', 'print'
                    ],
                    "pageLength": 50
                });
            });
        </script>
        <?php
    } else {
        echo '<p>No auctions found.</p>';
    }

    echo '</div>';
}

/**
 * Render the Bidding History Page
 */
function stg_render_bidding_history_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    echo '<div class="wrap">';
    echo '<h1 style="display:inline-block; margin-right: 20px;">Bidding History Report</h1>';
    echo '<button class="button button-primary" onclick="window.print()" style="margin-bottom: 20px;">Print Report</button>';

    $args = array(
        'post_type'      => 'storage_auction',
        'posts_per_page' => -1,
        'post_status'    => 'any'
    );
    $auctions = new WP_Query( $args );

    if ( $auctions->have_posts() ) {
        echo '<table id="stg-bidding-history-table" class="wp-list-table widefat fixed striped table-view-list display">';
        echo '<thead><tr>';
        echo '<th>Auction Title</th>';
        echo '<th>Bidder Name</th>';
        echo '<th>Bid Amount</th>';
        echo '<th>Timestamp</th>';
        echo '</tr></thead><tbody>';

        while ( $auctions->have_posts() ) {
            $auctions->the_post();
            $auction_id  = get_the_ID();
            $unit_id     = get_post_meta( $auction_id, '_stg_unit_id', true );
            $display_title = "Unit #" . $unit_id . " - " . get_the_title();
            $history = get_post_meta( $auction_id, '_stg_bid_history', true );

            if ( is_array( $history ) && ! empty( $history ) ) {
                foreach ( $history as $bid ) {
                    $bidder_info = get_userdata( $bid['user_id'] );
                    $bidder_name = $bidder_info ? $bidder_info->display_name : 'Unknown';
                    
                    echo '<tr>';
                    echo '<td><a href="' . get_edit_post_link( $auction_id ) . '">' . esc_html( $display_title ) . '</a></td>';
                    echo '<td>' . esc_html( $bidder_name ) . '</td>';
                    echo '<td>$' . number_format( floatval( $bid['amount'] ), 2 ) . '</td>';
                    echo '<td>' . esc_html( $bid['time'] ) . '</td>';
                    echo '</tr>';
                }
            }
        }
        echo '</tbody></table>';
        wp_reset_postdata();

        ?>
        <script>
            jQuery(document).ready(function($) {
                $('#stg-bidding-history-table').DataTable({
                    dom: 'Bfrtip',
                    buttons: [
                        'copy', 'csv', 'excel', 'pdf', 'print'
                    ],
                    "pageLength": 50,
                    "order": [[ 0, "asc" ], [ 3, "desc" ]],
                    "rowGroup": {
                        dataSrc: 0
                    }
                });
            });
        </script>
        <?php
    } else {
        echo '<p>No auctions found.</p>';
    }

    echo '</div>';
}

/**
 * Render the User Directory Page
 */
function stg_render_user_directory_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    if ( isset( $_GET['delete_user'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_user_' . $_GET['delete_user'] ) ) {
        $user_id_to_delete = intval( $_GET['delete_user'] );
        if ( current_user_can( 'delete_users' ) && $user_id_to_delete !== get_current_user_id() ) {
            require_once( ABSPATH . 'wp-admin/includes/user.php' );
            wp_delete_user( $user_id_to_delete );
            echo '<div class="updated notice is-dismissible"><p>User deleted successfully.</p></div>';
        } else {
            echo '<div class="error notice is-dismissible"><p>You cannot delete this user.</p></div>';
        }
    }

    if ( isset( $_GET['delete_signup'] ) && isset( $_GET['_wpnonce'] ) && wp_verify_nonce( $_GET['_wpnonce'], 'delete_signup_' . $_GET['delete_signup'] ) ) {
        if ( current_user_can( 'delete_users' ) ) {
            global $wpdb;
            $signup_id_to_delete = intval( $_GET['delete_signup'] );
            $wpdb->delete( $wpdb->signups, array( 'signup_id' => $signup_id_to_delete ) );
            echo '<div class="updated notice is-dismissible"><p>Pending user deleted successfully.</p></div>';
        } else {
            echo '<div class="error notice is-dismissible"><p>You cannot delete this user.</p></div>';
        }
    }

    echo '<div class="wrap">';
    echo '<h1 style="display:inline-block; margin-right: 20px;">User Directory</h1>';
    echo '<button class="button button-primary" onclick="window.print()" style="margin-bottom: 20px;">Print Report</button>';

    $args = array(
        'role__in' => array( 'subscriber', 'bidder' ),
        'blog_id'  => 0
    );
    $user_query = new WP_User_Query( $args );
    $users = $user_query->get_results();

    if ( ! empty( $users ) ) {
        echo '<table id="stg-user-directory-table" class="wp-list-table widefat fixed striped table-view-list display">';
        echo '<thead><tr>';
        echo '<th>Username</th>';
        echo '<th>First Name</th>';
        echo '<th>Last Name</th>';
        echo '<th>Email Address</th>';
        echo '<th>Phone Number</th>';
        echo '<th>Actions</th>';
        echo '</tr></thead><tbody>';

        foreach ( $users as $user ) {
            $first_name = get_user_meta( $user->ID, 'first_name', true );
            $last_name = get_user_meta( $user->ID, 'last_name', true );
            $phone = get_user_meta( $user->ID, 'stg_phone', true );

            echo '<tr>';
            echo '<td>' . esc_html( $user->user_login ) . '</td>';
            echo '<td>' . esc_html( $first_name ) . '</td>';
            echo '<td>' . esc_html( $last_name ) . '</td>';
            echo '<td>' . esc_html( $user->user_email ) . '</td>';
            echo '<td>' . esc_html( $phone ) . '</td>';
            $delete_url = wp_nonce_url( add_query_arg( array( 'page' => 'stg-user-directory', 'delete_user' => $user->ID ), admin_url( 'edit.php?post_type=storage_auction' ) ), 'delete_user_' . $user->ID );
            echo '<td><a href="' . esc_url( $delete_url ) . '" class="button" onclick="return confirm(\'Are you sure you want to delete this user?\');">Delete</a></td>';
            echo '</tr>';
        }

        global $wpdb;
        $pending_users = $wpdb->get_results( "SELECT * FROM {$wpdb->signups} WHERE active = 0" );
        
        if ( ! empty( $pending_users ) ) {
            foreach ( $pending_users as $pending ) {
                $meta = maybe_unserialize( $pending->meta );
                $first_name = isset( $meta['first_name'] ) ? $meta['first_name'] : '';
                $last_name = isset( $meta['last_name'] ) ? $meta['last_name'] : '';
                $phone = isset( $meta['stg_phone'] ) ? $meta['stg_phone'] : '';

                echo '<tr>';
                echo '<td>' . esc_html( $pending->user_login ) . ' <strong>(Pending)</strong></td>';
                echo '<td>' . esc_html( $first_name ) . '</td>';
                echo '<td>' . esc_html( $last_name ) . '</td>';
                echo '<td>' . esc_html( $pending->user_email ) . '</td>';
                echo '<td>' . esc_html( $phone ) . '</td>';
                $delete_signup_url = wp_nonce_url( add_query_arg( array( 'page' => 'stg-user-directory', 'delete_signup' => $pending->signup_id ), admin_url( 'edit.php?post_type=storage_auction' ) ), 'delete_signup_' . $pending->signup_id );
                echo '<td><a href="' . esc_url( $delete_signup_url ) . '" class="button" onclick="return confirm(\'Are you sure you want to delete this pending user?\');">Delete</a></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';

        ?>
        <script>
            jQuery(document).ready(function($) {
                $('#stg-user-directory-table').DataTable({
                    dom: 'Bfrtip',
                    buttons: [
                        'copy', 'csv', 'excel', 'pdf', 'print'
                    ],
                    "pageLength": 50
                });
            });
        </script>
        <?php
    } else {
        echo '<p>No users found.</p>';
    }

    echo '</div>';
}