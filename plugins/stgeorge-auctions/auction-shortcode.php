<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Shortcode to display active auctions with Filter, Media, and Terms
 */
function stg_display_auctions_shortcode($atts) {
    $a = shortcode_atts( array(
        'batch' => '',
        'id'    => '',
        'view'  => '',
    ), $atts );

    ob_start();

    // Enqueue the JS and pass the security nonce and AJAX URL
    wp_enqueue_script( 'stg-auction-js', plugin_dir_url(__FILE__) . 'auction-script.js', array('jquery'), '1.2', true );
    wp_localize_script( 'stg-auction-js', 'stgAuctionData', array(
        'ajaxurl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'stg_bid_nonce' )
    ));

    // Get all 'auction_batch' terms that are marked to be hidden
    $hidden_batch_terms = get_terms( array(
        'taxonomy'   => 'auction_batch',
        'hide_empty' => false,
        'meta_query' => array(
            array(
                'key'   => 'stg_hide_batch',
                'value' => 'yes',
            ),
        ),
        'fields'     => 'ids',
    ) );

    $args = array(
        'post_type'      => 'storage_auction',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'meta_query'     => array(
            'relation' => 'OR',
            array(
                'key'     => '_stg_hide_from_list',
                'value'   => '1',
                'compare' => '!=',
            ),
            array(
                'key'     => '_stg_hide_from_list',
                'compare' => 'NOT EXISTS',
            ),
        ),
    );

    if ( $a['view'] === 'all' ) {
        unset( $args['meta_query'] );
    }

    if ( ! empty( $a['id'] ) ) {
        $args['p'] = intval( $a['id'] );
        // If displaying a single auction directly via shortcode ID, we can ignore the hide_from_list meta check so they can view it.
        unset( $args['meta_query'] ); 
    }

    $args['tax_query'] = array();

    if ( ! empty( $a['batch'] ) ) {
        $args['tax_query'][] = array(
            'taxonomy' => 'auction_batch',
            'field'    => 'slug',
            'terms'    => $a['batch'],
        );
    }

    if ( $a['view'] !== 'all' && ! empty( $hidden_batch_terms ) && ! is_wp_error( $hidden_batch_terms ) ) {
        $args['tax_query'][] = array(
            'taxonomy' => 'auction_batch',
            'field'    => 'term_id',
            'terms'    => $hidden_batch_terms,
            'operator' => 'NOT IN',
        );
    }

    if ( count( $args['tax_query'] ) > 1 ) {
        $args['tax_query']['relation'] = 'AND';
    } elseif ( empty( $args['tax_query'] ) ) {
        unset( $args['tax_query'] );
    }

    $auctions = new WP_Query( $args );

    if ( $auctions->have_posts() ) {
        ?>
        <div class="mb-10 flex flex-col md:flex-row justify-between items-center bg-white p-4 rounded-xl shadow-sm border">
            <h3 class="text-xl font-bold text-gray-800 mb-2 md:mb-0">Live Auctions</h3>
            <select id="stg-facility-filter" class="border-gray-300 rounded-lg text-sm font-bold shadow-sm focus:ring-amber-500 focus:border-amber-500">
                <option value="all">View All Locations</option>
                <option value="AllClimate">AllClimate Storage</option>
                <option value="Classic">Classic Commercial Storage</option>
                <option value="CoralCanyon">Coral Canyon Market Storage</option>
                <option value="Handy">Handy Storage</option>
                <option value="Secure">Secure Storage</option>
            </select>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="stg-auction-list">
        <?php

        while ( $auctions->have_posts() ) {
            $auctions->the_post();
            $auction_id       = get_the_ID();
            $facility         = get_post_meta( $auction_id, '_stg_facility', true );
            $end_date         = get_post_meta( $auction_id, '_stg_end_date', true );
            $starting_bid     = floatval( get_post_meta( $auction_id, '_stg_starting_bid', true ) );
            $current_bid      = floatval( get_post_meta( $auction_id, '_stg_current_bid', true ) );
            $item_description = get_post_meta( $auction_id, '_stg_item_description', true );
            $video_url        = get_post_meta( $auction_id, '_stg_video_url', true );
            $unit_id          = get_post_meta( $auction_id, '_stg_unit_id', true );
            
            // Check if part of upcoming batch
            $is_upcoming = false;
            $terms = wp_get_post_terms( $auction_id, 'auction_batch' );
            if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
                foreach ( $terms as $term ) {
                    if ( stripos( $term->name, 'upcoming' ) !== false ) {
                        $is_upcoming = true;
                        break;
                    }
                }
            }

            // Title masking logic
            $display_title = "Unit #" . esc_html( $unit_id );
            if ( $is_upcoming || empty($unit_id) ) {
                $display_title = "Upcoming Unit";
            }

            // Calculate display price and the next minimum required bid
            $display_price = $current_bid > 0 ? $current_bid : $starting_bid;
            $next_min_bid  = $current_bid > 0 ? ($current_bid + 1.00) : $starting_bid;

            $end_timestamp = !empty($end_date) ? strtotime($end_date) : 0;
            ?>
            <div class="facility-card p-6 border rounded-xl flex flex-col gap-6 bg-white shadow-sm transition-all duration-300" data-facility="<?php echo esc_attr($facility); ?>" data-end-timestamp="<?php echo esc_attr($end_timestamp); ?>">
                <div class="w-full bg-gray-200 h-48 rounded-lg overflow-hidden flex items-center justify-center relative">
                    <?php if ( has_post_thumbnail() ) : ?>
                        <?php the_post_thumbnail('medium', ['class' => 'w-full h-full object-cover']); ?>
                    <?php elseif ( !empty($video_url) && preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $video_url, $match) ) : ?>
                        <?php $youtube_id = $match[1]; ?>
                        <img src="https://img.youtube.com/vi/<?php echo esc_attr($youtube_id); ?>/hqdefault.jpg" class="w-full h-full object-cover" alt="Video Thumbnail">
                        <div class="absolute top-2 right-2 bg-red-600 text-white px-2 py-1 rounded text-xs font-bold shadow flex items-center gap-1">
                            <i class="fa-solid fa-video"></i> Video Available
                        </div>
                    <?php elseif ( !empty($video_url) ) : ?>
                        <div class="text-center">
                            <i class="fa-solid fa-video text-4xl text-blue-500 mb-2"></i>
                            <div class="text-xs font-bold text-gray-500 uppercase tracking-wider">Video Available</div>
                        </div>
                    <?php else : ?>
                        <i class="fa-solid fa-camera text-4xl text-gray-400"></i>
                    <?php endif; ?>

                    <a href="<?php the_permalink(); ?>" class="absolute inset-0 bg-black bg-opacity-0 hover:bg-opacity-20 flex items-center justify-center transition-all duration-200 group">
                        <span class="bg-white text-gray-900 px-3 py-1 rounded-full text-xs font-bold opacity-0 group-hover:opacity-100 shadow-lg">View Details</span>
                    </a>
                </div>

                <div class="flex-1 flex flex-col">
                    <div class="flex justify-between items-start">
                        <div>
                            <span class="bg-amber-100 text-amber-800 text-xs font-bold px-2 py-1 rounded uppercase"><?php echo esc_html($facility); ?></span>
                            <h4 class="text-xl font-bold mt-2 hover:text-amber-600 transition-colors">
                                <a href="<?php the_permalink(); ?>"><?php echo esc_html( $display_title ); ?></a>
                            </h4>
                            <p class="text-sm text-gray-500 mt-1">Ends: <strong><?php echo !empty($end_date) ? date('F j, Y @ g:i A', strtotime($end_date)) : 'TBD'; ?></strong></p>
                            <?php if ( !empty($end_date) ) : ?>
                                <p class="text-xs font-bold mt-1 text-red-600 uppercase tracking-wide stg-countdown-display" data-end-time="<?php echo esc_attr($end_timestamp); ?>">Time Remaining: <span class="stg-timer-text">Loading...</span></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if ( !empty($item_description) ) : ?>
                        <div class="mt-3 bg-blue-50 text-blue-800 text-sm p-3 rounded border border-blue-100">
                            <strong><i class="fa-solid fa-box-open mr-1"></i> Visible Items:</strong> <?php echo esc_html($item_description); ?>
                        </div>
                    <?php endif; ?>

                    <?php 
                    if ( !empty($video_url) ) : 
                        $embed_url = $video_url;
                        if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $video_url, $match)) {
                            $youtube_id = $match[1];
                            $embed_url = "https://www.youtube.com/embed/" . $youtube_id;
                        }
                    ?>
                        <a href="<?php echo esc_url($embed_url); ?>" target="_blank" class="mt-2 inline-block text-blue-600 font-bold text-xs hover:underline">
                            <i class="fa-solid fa-circle-play mr-1"></i> Watch Video Walkthrough
                        </a>
                    <?php endif; ?>

                    <details class="group bg-gray-50 border border-gray-200 rounded-lg p-3 mt-3 transition-all duration-300">
                        <summary class="flex justify-between items-center text-sm font-bold text-gray-700 cursor-pointer list-none hover:text-amber-600">
                            <span><i class="fa-solid fa-scroll mr-2"></i> View Terms & Conditions</span>
                            <span class="transition-transform duration-300 group-open:rotate-180 text-gray-400"><i class="fa-solid fa-chevron-down text-xs"></i></span>
                        </summary>
                        <div class="mt-3 text-[11px] text-gray-600 space-y-2 border-t border-gray-200 pt-3 leading-relaxed">
                            <p><strong>Facility Terms:</strong> $100 cash cleaning deposit required (refundable if cleared in 3 days). Additional $35 deposit for lock combination. All payments must be <strong>CASH</strong> at our main office: 1156 E 700 S Ste. 1 St. George, UT.</p>
                            <p><strong>Auction Terms:</strong> 3-day removal timeline. Must show photo ID. Office Hours: 9am-5pm Mon-Fri.</p>
                        </div>
                    </details>

                    <div class="mt-auto pt-4 flex justify-between items-end border-t">
                        <div>
                            <span class="text-xs text-gray-500 uppercase font-bold tracking-wider">Current Bid</span>
                            <span class="text-3xl font-black text-gray-800 stg-current-bid-display block">$<?php echo number_format($display_price, 2); ?></span>

                            <?php
                            $management_fee = $display_price * 0.15;
                            $lock_deposit = 35.00;
                            $total_due = $display_price + $management_fee + $lock_deposit;
                            ?>
                            <div class="mt-2 text-xs text-gray-600 stg-financial-breakdown">
                                <p>+ 15% Fee: <span class="stg-fee-display">$<?php echo number_format($management_fee, 2); ?></span></p>
                                <p>+ Lock Deposit: <span class="stg-deposit-display">$<?php echo number_format($lock_deposit, 2); ?></span></p>
                                <p class="font-bold text-gray-800 mt-1">Total Due at Office: <span class="stg-total-display">$<?php echo number_format($total_due, 2); ?></span></p>
                            </div>
                        </div>

                        <?php
                        $is_ended = (!empty($end_date) && current_time('timestamp') >= strtotime($end_date)) ? true : false;

                        if ( $is_ended ) : ?>
                            <div class="text-right">
                                <span class="text-red-600 font-bold uppercase text-sm">Auction Ended</span>
                            </div>
                        <?php else : ?>
                            <?php if ( is_user_logged_in() ) : ?>
                                <div class="flex flex-col items-end gap-2">
                                    <div class="flex gap-2">
                                        <input type="number" class="stg-bid-input border-gray-300 rounded-lg w-28 text-center font-bold"
                                               min="<?php echo $next_min_bid; ?>"
                                               step="1"
                                               placeholder="$<?php echo number_format($next_min_bid, 0); ?>+">
                                        <button class="stg-place-bid-btn bg-amber-500 hover:bg-amber-600 text-white px-6 py-2 rounded-lg font-bold transition duration-150 shadow-md" data-auction-id="<?php echo $auction_id; ?>">
                                            Bid Now
                                        </button>
                                    </div>
                                    <div class="stg-bid-message text-sm hidden font-bold"></div>
                                </div>
                            <?php else : ?>
                                <a href="<?php echo wp_login_url( 'https://auction.stgeorgestorage.com' ); ?>" class="bg-gray-800 text-white px-6 py-2 rounded-lg font-bold shadow-md">Log in to Bid</a>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php
        }
        echo '</div>';
        wp_reset_postdata();
    } else {
        echo '<p class="text-gray-500 italic p-8 bg-white border rounded-xl text-center">No active auctions at this time.</p>';
    }

    return ob_get_clean();
}
add_shortcode( 'stg_auctions', 'stg_display_auctions_shortcode' );