<?php
if ( ! defined( 'ABSPATH' ) ) exit;

function stg_add_auction_meta_boxes() {
    add_meta_box('stg_auction_details', 'Auction Details', 'stg_auction_meta_box_html', 'storage_auction', 'normal', 'high');
}
add_action( 'add_meta_boxes', 'stg_add_auction_meta_boxes' );

function stg_auction_meta_box_html( $post ) {
    $starting_bid = get_post_meta( $post->ID, '_stg_starting_bid', true );
    $end_date = get_post_meta( $post->ID, '_stg_end_date', true );
    $facility = get_post_meta( $post->ID, '_stg_facility', true );
    $item_desc = get_post_meta( $post->ID, '_stg_item_description', true );
    $video_url = get_post_meta( $post->ID, '_stg_video_url', true );
    $hide_from_list = get_post_meta( $post->ID, '_stg_hide_from_list', true );

    $poster_image = get_post_meta( $post->ID, '_stg_poster_image', true );
    $unit_id = get_post_meta( $post->ID, '_stg_unit_id', true );

    wp_nonce_field( 'stg_save_auction_meta', 'stg_auction_meta_nonce' );
    ?>
    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 20px; margin-bottom: 20px;">
        <div>
            <label><strong>Auto-Generated Unit ID:</strong></label><br>
            <input type="text" value="<?php echo esc_attr($unit_id); ?>" style="width: 100%; background: #f0f0f0;" readonly>
            <p class="description">Used for public display.</p>
        </div>
        <div>
            <label><strong>Facility:</strong></label><br>
            <select name="stg_facility" style="width: 100%;">
                <option value="AllClimate" <?php selected($facility, 'AllClimate'); ?>>AllClimate</option>
                <option value="Classic" <?php selected($facility, 'Classic'); ?>>Classic</option>
                <option value="Handy" <?php selected($facility, 'Handy'); ?>>Handy</option>
                <option value="Secure" <?php selected($facility, 'Secure'); ?>>Secure</option>
            </select>
        </div>
        <div>
            <label><strong>Starting Bid ($):</strong></label><br>
            <input type="number" name="stg_starting_bid" value="<?php echo esc_attr($starting_bid); ?>" style="width: 100%;">
        </div>
        <div>
            <label><strong>End Date:</strong></label><br>
            <input type="datetime-local" name="stg_end_date" value="<?php echo esc_attr($end_date); ?>" style="width: 100%;">
        </div>
    </div>
    <div style="margin-bottom: 20px;">
        <label><strong>YouTube Video URL:</strong></label><br>
        <input type="url" name="stg_video_url" value="<?php echo esc_url($video_url); ?>" style="width: 100%;" placeholder="https://youtube.com/watch?v=...">
    </div>
    <div style="margin-bottom: 20px;">
        <label><strong>Poster Image URL:</strong></label><br>
        <input type="url" name="stg_poster_image" value="<?php echo esc_url($poster_image); ?>" style="width: 100%;" placeholder="https://example.com/image.jpg">
        <p class="description">Optional: Provide an image URL to act as the video thumbnail.</p>
    </div>
    <div style="margin-bottom: 20px;">
        <label>
            <input type="checkbox" name="stg_hide_from_list" value="1" <?php checked($hide_from_list, '1'); ?>>
            <strong>Hide from Main List</strong>
        </label>
        <p class="description">This allows keeping a permanent record of the unit without cluttering the front end.</p>
    </div>
    <div>
        <label><strong>Item Description:</strong></label><br>
        <textarea name="stg_item_description" rows="3" style="width: 100%;"><?php echo esc_textarea($item_desc); ?></textarea>
    </div>
    
    <?php
    $deposit_held = get_post_meta($post->ID, '_stg_cleaning_deposit_held', true);
    if ($deposit_held === 'yes') :
    ?>
    <div style="margin-top: 20px; padding: 15px; border: 1px solid #ccc; background: #fff;">
        <label><strong>$100 Cleaning Deposit Hold</strong></label>
        <p>A $100 hold was placed on the winner's card.</p>
        <button type="button" class="button stg-deposit-action" data-action="release" data-id="<?php echo $post->ID; ?>" style="margin-right:10px;">Unit Clean (Release Hold)</button>
        <button type="button" class="button button-primary stg-deposit-action" data-action="capture" data-id="<?php echo $post->ID; ?>">Abandoned (Capture Hold)</button>
        <div id="stg-deposit-response" style="margin-top: 10px; font-weight: bold;"></div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.stg-deposit-action').on('click', function(e) {
                e.preventDefault();
                var actionType = $(this).data('action');
                var postId = $(this).data('id');
                var btn = $(this);
                
                if (!confirm('Are you sure you want to ' + actionType + ' the $100 hold?')) return;
                
                btn.prop('disabled', true).text('Processing...');
                
                $.post(ajaxurl, {
                    action: 'stg_process_deposit',
                    type: actionType,
                    post_id: postId,
                    security: '<?php echo wp_create_nonce("stg_deposit_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#stg-deposit-response').css('color', 'green').text(response.data);
                        $('.stg-deposit-action').hide();
                    } else {
                        $('#stg-deposit-response').css('color', 'red').text(response.data);
                        btn.prop('disabled', false).text(actionType === 'release' ? 'Unit Clean (Release Hold)' : 'Abandoned (Capture Hold)');
                    }
                });
            });
        });
        </script>
    </div>
    <?php endif; ?>
    <?php
}

function stg_save_auction_meta( $post_id ) {
    if ( ! isset( $_POST['stg_auction_meta_nonce'] ) || ! wp_verify_nonce( $_POST['stg_auction_meta_nonce'], 'stg_save_auction_meta' ) ) return;
    
    update_post_meta( $post_id, '_stg_facility', sanitize_text_field( $_POST['stg_facility'] ) );
    update_post_meta( $post_id, '_stg_starting_bid', sanitize_text_field( $_POST['stg_starting_bid'] ) );
    update_post_meta( $post_id, '_stg_end_date', sanitize_text_field( $_POST['stg_end_date'] ) );
    update_post_meta( $post_id, '_stg_item_description', sanitize_textarea_field( $_POST['stg_item_description'] ) );
    update_post_meta( $post_id, '_stg_video_url', esc_url_raw( $_POST['stg_video_url'] ) );
    update_post_meta( $post_id, '_stg_poster_image', esc_url_raw( $_POST['stg_poster_image'] ) );
    
    $hide = isset( $_POST['stg_hide_from_list'] ) ? '1' : '0';
    update_post_meta( $post_id, '_stg_hide_from_list', $hide );
}
add_action( 'save_post_storage_auction', 'stg_save_auction_meta' );

function stg_generate_unit_id( $post_id, $post, $update ) {
    if ( wp_is_post_revision( $post_id ) ) return;

    $unit_id = get_post_meta( $post_id, '_stg_unit_id', true );
    
    if ( empty( $unit_id ) ) {
        global $wpdb;
        $max_id = $wpdb->get_var("SELECT MAX(CAST(meta_value AS UNSIGNED)) FROM $wpdb->postmeta WHERE meta_key = '_stg_unit_id'");
        $next_id = $max_id ? intval( $max_id ) + 1 : 1;
        update_post_meta( $post_id, '_stg_unit_id', $next_id );
    }
}
add_action( 'save_post_storage_auction', 'stg_generate_unit_id', 10, 3 );
// Add custom column for Quick Edit visibility
add_filter('manage_storage_auction_posts_columns', 'stg_add_auction_columns');
function stg_add_auction_columns($columns) {
    $columns['stg_visibility'] = 'Hidden';
    return $columns;
}

// Output hidden field for Quick Edit inline JS
add_action('manage_storage_auction_posts_custom_column', 'stg_custom_auction_columns', 10, 2);
function stg_custom_auction_columns($column, $post_id) {
    if ($column === 'stg_visibility') {
        $hidden = get_post_meta($post_id, '_stg_hide_from_list', true);
        echo $hidden === '1' ? 'Yes' : 'No';
        echo '<div class="hidden" id="stg_hidden_status_' . $post_id . '">' . esc_html($hidden) . '</div>';
    }
}

// Add Quick Edit field
add_action('quick_edit_custom_box', 'stg_quick_edit_custom_box', 10, 2);
function stg_quick_edit_custom_box($column_name, $post_type) {
    if ($column_name === 'stg_visibility' && $post_type === 'storage_auction') {
        wp_nonce_field('stg_quick_edit_nonce', 'stg_quick_edit_nonce_field');
        ?>
        <fieldset class="inline-edit-col-right">
            <div class="inline-edit-col">
                <label class="alignleft">
                    <input type="checkbox" name="stg_hide_from_list" value="1">
                    <span class="checkbox-title">Hide from Main List</span>
                </label>
            </div>
        </fieldset>
        <?php
    }
}

// Populate Quick Edit field with JS
add_action('admin_footer', 'stg_quick_edit_js');
function stg_quick_edit_js() {
    global $current_screen;
    if ($current_screen && $current_screen->post_type === 'storage_auction') {
        ?>
        <script>
        jQuery(document).ready(function($) {
            var $wp_inline_edit = inlineEditPost.edit;
            inlineEditPost.edit = function(id) {
                $wp_inline_edit.apply(this, arguments);
                var post_id = 0;
                if (typeof(id) == 'object') {
                    post_id = parseInt(this.getId(id));
                } else {
                    post_id = parseInt(id);
                }
                if (post_id > 0) {
                    var hidden_val = $('#stg_hidden_status_' + post_id).text();
                    var $row = $('#edit-' + post_id);
                    if (hidden_val === '1') {
                        $row.find('input[name="stg_hide_from_list"]').prop('checked', true);
                    } else {
                        $row.find('input[name="stg_hide_from_list"]').prop('checked', false);
                    }
                }
            };
        });
        </script>
        <?php
    }
}

// Save Quick Edit value
add_action('save_post_storage_auction', 'stg_save_quick_edit_data');
function stg_save_quick_edit_data($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!isset($_POST['stg_quick_edit_nonce_field']) || !wp_verify_nonce($_POST['stg_quick_edit_nonce_field'], 'stg_quick_edit_nonce')) {
        return; // It might be a regular save or something else
    }
    if (!current_user_can('edit_post', $post_id)) return;

    if (isset($_POST['stg_hide_from_list'])) {
        update_post_meta($post_id, '_stg_hide_from_list', '1');
    } else {
        update_post_meta($post_id, '_stg_hide_from_list', '0');
    }
}
