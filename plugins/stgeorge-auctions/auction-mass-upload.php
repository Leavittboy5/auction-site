<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add Mass Upload Page to the Admin Menu
 */
function stg_register_mass_upload_page() {
    add_submenu_page(
        'edit.php?post_type=storage_auction',
        'Mass Upload',
        'Mass Upload',
        'manage_options',
        'stg-mass-upload',
        'stg_render_mass_upload_page'
    );
}
add_action( 'admin_menu', 'stg_register_mass_upload_page' );

/**
 * Render the Mass Upload Page
 */
function stg_render_mass_upload_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // Handle form submission
    if ( isset( $_POST['stg_mass_upload_nonce'] ) && wp_verify_nonce( $_POST['stg_mass_upload_nonce'], 'stg_mass_upload' ) ) {
        $units = isset( $_POST['units'] ) ? $_POST['units'] : array();
        $batch_id = isset( $_POST['batch_assignment'] ) ? intval( $_POST['batch_assignment'] ) : 0;
        
        $count = 0;
        foreach ( $units as $unit ) {
            if ( !empty( $unit['title'] ) && !empty( $unit['facility'] ) ) {
                $post_id = wp_insert_post( array(
                    'post_title'   => sanitize_text_field( $unit['title'] ),
                    'post_type'    => 'storage_auction',
                    'post_status'  => 'publish',
                ) );

                if ( $post_id ) {
                    update_post_meta( $post_id, '_stg_facility', sanitize_text_field( $unit['facility'] ) );
                    update_post_meta( $post_id, '_stg_starting_bid', sanitize_text_field( $unit['starting_bid'] ) );
                    update_post_meta( $post_id, '_stg_end_date', sanitize_text_field( $unit['end_date'] ) );
                    update_post_meta( $post_id, '_stg_item_description', sanitize_textarea_field( $unit['item_description'] ) );
                    
                    if ( $batch_id > 0 ) {
                        wp_set_object_terms( $post_id, $batch_id, 'auction_batch' );
                    }
                    
                    $count++;
                }
            }
        }
        echo '<div class="notice notice-success is-dismissible"><p>Successfully created ' . $count . ' auctions.</p></div>';
    }

    $batches = get_terms( array( 'taxonomy' => 'auction_batch', 'hide_empty' => false ) );
    ?>
    <div class="wrap">
        <h1>Mass Upload Auctions</h1>
        <p>Use this form to rapidly create multiple auction listings and assign them to a batch.</p>
        
        <form method="post" action="">
            <?php wp_nonce_field( 'stg_mass_upload', 'stg_mass_upload_nonce' ); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="batch_assignment">Assign to Batch</label></th>
                    <td>
                        <select name="batch_assignment" id="batch_assignment">
                            <option value="">-- No Batch --</option>
                            <?php foreach ( $batches as $batch ) : ?>
                                <option value="<?php echo esc_attr( $batch->term_id ); ?>"><?php echo esc_html( $batch->name ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
            </table>

            <table class="wp-list-table widefat fixed striped" id="mass-upload-table" style="margin-top: 20px;">
                <thead>
                    <tr>
                        <th>Unit Physical Title (Private)</th>
                        <th>Facility</th>
                        <th>Starting Bid ($)</th>
                        <th>End Date</th>
                        <th>Item Description</th>
                    </tr>
                </thead>
                <tbody>
                    <?php for ( $i = 0; $i < 5; $i++ ) : ?>
                    <tr>
                        <td><input type="text" name="units[<?php echo $i; ?>][title]" style="width: 100%;" placeholder="e.g. Unit 105"></td>
                        <td>
                            <select name="units[<?php echo $i; ?>][facility]" style="width: 100%;">
                                <option value="">-- Select --</option>
                                <option value="AllClimate">AllClimate</option>
                                <option value="Classic">Classic</option>
                                <option value="Handy">Handy</option>
                                <option value="Secure">Secure</option>
                            </select>
                        </td>
                        <td><input type="number" name="units[<?php echo $i; ?>][starting_bid]" style="width: 100%;" placeholder="e.g. 50"></td>
                        <td><input type="datetime-local" name="units[<?php echo $i; ?>][end_date]" style="width: 100%;"></td>
                        <td><textarea name="units[<?php echo $i; ?>][item_description]" rows="1" style="width: 100%;"></textarea></td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
            
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Create Auctions">
            </p>
        </form>
    </div>
    <?php
}
