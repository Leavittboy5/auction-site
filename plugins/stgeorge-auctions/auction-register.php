<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Register 'Storage Auctions' Custom Post Type
 */
function stg_create_auction_cpt() {
    $labels = array(
        'name'                  => 'Auctions',
        'singular_name'         => 'Auction',
        'menu_name'             => 'Auctions',
        'name_admin_bar'        => 'Auction',
        'add_new'               => 'Add New',
        'add_new_item'          => 'Add New Auction',
        'new_item'              => 'New Auction',
        'edit_item'             => 'Edit Auction',
        'view_item'             => 'View Auction',
        'all_items'             => 'All Auctions',
        'search_items'          => 'Search Auctions',
        'not_found'             => 'No auctions found.',
        'not_found_in_trash'    => 'No auctions found in Trash.',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'auction' ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 5,
        'menu_icon'          => 'dashicons-hammer', 
        'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
        'show_in_rest'       => true, 
    );

    register_post_type( 'storage_auction', $args );

    // Register Batch Taxonomy
    $tax_labels = array(
        'name'              => 'Auction Batches',
        'singular_name'     => 'Auction Batch',
        'search_items'      => 'Search Batches',
        'all_items'         => 'All Batches',
        'parent_item'       => 'Parent Batch',
        'parent_item_colon' => 'Parent Batch:',
        'edit_item'         => 'Edit Batch',
        'update_item'       => 'Update Batch',
        'add_new_item'      => 'Add New Batch',
        'new_item_name'     => 'New Batch Name',
        'menu_name'         => 'Batches',
    );

    $tax_args = array(
        'hierarchical'      => true,
        'labels'            => $tax_labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'batch' ),
        'show_in_rest'      => true,
    );

    register_taxonomy( 'auction_batch', array( 'storage_auction' ), $tax_args );
}
add_action( 'init', 'stg_create_auction_cpt' );

// Add "Hide from Website" field to Batch Taxonomy
function stg_add_batch_hide_field() {
    ?>
    <div class="form-field term-group">
        <label for="stg_hide_batch"><input type="checkbox" id="stg_hide_batch" name="stg_hide_batch" value="yes"> Hide entire batch from website</label>
        <p>If checked, all auctions in this batch will be excluded from the live auctions list.</p>
    </div>
    <?php
}
add_action( 'auction_batch_add_form_fields', 'stg_add_batch_hide_field', 10, 2 );

function stg_edit_batch_hide_field( $term ) {
    $is_hidden = get_term_meta( $term->term_id, 'stg_hide_batch', true );
    ?>
    <tr class="form-field term-group-wrap">
        <th scope="row"><label for="stg_hide_batch">Hide from Website</label></th>
        <td>
            <input type="checkbox" id="stg_hide_batch" name="stg_hide_batch" value="yes" <?php checked( $is_hidden, 'yes' ); ?>>
            <p class="description">If checked, all auctions in this batch will be excluded from the live auctions list.</p>
        </td>
    </tr>
    <?php
}
add_action( 'auction_batch_edit_form_fields', 'stg_edit_batch_hide_field', 10, 2 );

function stg_save_batch_hide_meta( $term_id ) {
    if ( isset( $_POST['stg_hide_batch'] ) && 'yes' === $_POST['stg_hide_batch'] ) {
        update_term_meta( $term_id, 'stg_hide_batch', 'yes' );
    } else {
        delete_term_meta( $term_id, 'stg_hide_batch' );
    }
}
add_action( 'created_auction_batch', 'stg_save_batch_hide_meta', 10, 2 );
add_action( 'edited_auction_batch', 'stg_save_batch_hide_meta', 10, 2 );