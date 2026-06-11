<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Add Custom Fields to the Registration Form
 */
function stg_add_terms_checkbox_to_registration() {
    // If they submit the form but hit an error, this keeps the box checked/filled so they don't have to retype it
    $is_checked = isset( $_POST['stg_agree_terms'] ) ? 'checked="checked"' : '';
    $phone_val  = isset( $_POST['stg_phone'] ) ? esc_attr( $_POST['stg_phone'] ) : '';
    $first_name = isset( $_POST['first_name'] ) ? esc_attr( $_POST['first_name'] ) : '';
    $last_name  = isset( $_POST['last_name'] ) ? esc_attr( $_POST['last_name'] ) : '';
    ?>
    <p>
        <label for="first_name">First Name</label>
        <input type="text" name="first_name" id="first_name" class="input" value="<?php echo $first_name; ?>" size="25" required="required" />
    </p>
    <p>
        <label for="last_name">Last Name</label>
        <input type="text" name="last_name" id="last_name" class="input" value="<?php echo $last_name; ?>" size="25" required="required" />
    </p>
    <p>
        <label for="stg_phone">Phone Number</label>
        <input type="tel" name="stg_phone" id="stg_phone" class="input" value="<?php echo $phone_val; ?>" size="25" autocomplete="tel" required="required" />
    </p>
    <p style="margin-bottom: 20px; padding: 10px; background-color: #f9fafb; border: 1px solid #e5e7eb; border-radius: 5px;">
        <label for="stg_agree_terms" style="font-size: 14px; color: #374151; font-weight: normal; display: flex; align-items: flex-start; gap: 8px;">
            <input type="checkbox" name="stg_agree_terms" id="stg_agree_terms" value="1" <?php echo $is_checked; ?> style="margin-top: 4px;" required />
            <span>
                I have read and agree to the <strong>Cash Only</strong> policy and the <a href="https://advancedrealty.com/terms-and-conditions/" target="_blank" style="color: #2563eb; text-decoration: underline;">Auction Terms and Conditions</a>. I understand that failure to pay in cash or empty the unit will result in a permanent ban.
            </span>
        </label>
    </p>
    <?php
}
add_action( 'register_form', 'stg_add_terms_checkbox_to_registration' );
add_action( 'signup_extra_fields', 'stg_add_terms_checkbox_to_registration' );

/**
 * 2. Validate Custom Fields
 */
function stg_validate_terms_checkbox( $errors, $sanitized_user_login, $user_email ) {
    // If the box is NOT checked, throw a hard error and stop the registration
    if ( ! isset( $_POST['stg_agree_terms'] ) || $_POST['stg_agree_terms'] !== '1' ) {
        $errors->add( 'terms_error', '<strong>Required</strong>: You must agree to the Auction Terms and Conditions to create a bidder profile.' );
    }

    if ( empty( $_POST['first_name'] ) || trim( $_POST['first_name'] ) === '' ) {
        $errors->add( 'first_name_error', '<strong>Required</strong>: Please enter your first name.' );
    }

    if ( empty( $_POST['last_name'] ) || trim( $_POST['last_name'] ) === '' ) {
        $errors->add( 'last_name_error', '<strong>Required</strong>: Please enter your last name.' );
    }

    if ( empty( $_POST['stg_phone'] ) || trim( $_POST['stg_phone'] ) === '' ) {
        $errors->add( 'phone_error', '<strong>Required</strong>: Please enter a valid phone number.' );
    }

    return $errors;
}
add_filter( 'registration_errors', 'stg_validate_terms_checkbox', 10, 3 );

// Validate for Multisite / wp-signup.php
function stg_wpmu_validate_terms_checkbox( $result ) {
    if ( ! isset( $_POST['stg_agree_terms'] ) || $_POST['stg_agree_terms'] !== '1' ) {
        $result['errors']->add( 'terms_error', '<strong>Required</strong>: You must agree to the Auction Terms and Conditions to create a bidder profile.' );
    }

    if ( empty( $_POST['first_name'] ) || trim( $_POST['first_name'] ) === '' ) {
        $result['errors']->add( 'first_name_error', '<strong>Required</strong>: Please enter your first name.' );
    }

    if ( empty( $_POST['last_name'] ) || trim( $_POST['last_name'] ) === '' ) {
        $result['errors']->add( 'last_name_error', '<strong>Required</strong>: Please enter your last name.' );
    }

    if ( empty( $_POST['stg_phone'] ) || trim( $_POST['stg_phone'] ) === '' ) {
        $result['errors']->add( 'phone_error', '<strong>Required</strong>: Please enter a valid phone number.' );
    }

    return $result;
}
add_filter( 'wpmu_validate_user_signup', 'stg_wpmu_validate_terms_checkbox' );

/**
 * 3. Save the Custom Fields
 */
function stg_save_registration_fields( $user_id ) {
    if ( isset( $_POST['first_name'] ) && trim( $_POST['first_name'] ) !== '' ) {
        update_user_meta( $user_id, 'first_name', sanitize_text_field( $_POST['first_name'] ) );
    }
    if ( isset( $_POST['last_name'] ) && trim( $_POST['last_name'] ) !== '' ) {
        update_user_meta( $user_id, 'last_name', sanitize_text_field( $_POST['last_name'] ) );
    }
    if ( isset( $_POST['stg_phone'] ) && trim( $_POST['stg_phone'] ) !== '' ) {
        update_user_meta( $user_id, 'stg_phone', sanitize_text_field( $_POST['stg_phone'] ) );
    }
}
add_action( 'user_register', 'stg_save_registration_fields' );

// Save meta to the signup table for Multisite
function stg_wpmu_add_signup_meta( $meta ) {
    if ( isset( $_POST['first_name'] ) && trim( $_POST['first_name'] ) !== '' ) {
        $meta['first_name'] = sanitize_text_field( $_POST['first_name'] );
    }
    if ( isset( $_POST['last_name'] ) && trim( $_POST['last_name'] ) !== '' ) {
        $meta['last_name'] = sanitize_text_field( $_POST['last_name'] );
    }
    if ( isset( $_POST['stg_phone'] ) && trim( $_POST['stg_phone'] ) !== '' ) {
        $meta['stg_phone'] = sanitize_text_field( $_POST['stg_phone'] );
    }
    return $meta;
}
add_filter( 'add_signup_meta', 'stg_wpmu_add_signup_meta' );

// Save meta to usermeta when the user is activated in Multisite
function stg_wpmu_activate_user( $user_id, $password, $meta ) {
    if ( isset( $meta['first_name'] ) && trim( $meta['first_name'] ) !== '' ) {
        update_user_meta( $user_id, 'first_name', sanitize_text_field( $meta['first_name'] ) );
    }
    if ( isset( $meta['last_name'] ) && trim( $meta['last_name'] ) !== '' ) {
        update_user_meta( $user_id, 'last_name', sanitize_text_field( $meta['last_name'] ) );
    }
    if ( isset( $meta['stg_phone'] ) && trim( $meta['stg_phone'] ) !== '' ) {
        update_user_meta( $user_id, 'stg_phone', sanitize_text_field( $meta['stg_phone'] ) );
    }
}
add_action( 'wpmu_activate_user', 'stg_wpmu_activate_user', 10, 3 );