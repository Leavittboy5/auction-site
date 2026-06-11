<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 1. Add the Checkbox and Phone HTML to the Registration Form
 */
function stg_add_terms_checkbox_to_registration() {
    // If they submit the form but hit an error, this keeps the box checked so they don't have to click it again
    $is_checked = isset( $_POST['stg_agree_terms'] ) ? 'checked="checked"' : '';
    $phone_val  = isset( $_POST['stg_phone'] ) ? esc_attr( $_POST['stg_phone'] ) : '';
    $first_name = isset( $_POST['first_name'] ) ? esc_attr( $_POST['first_name'] ) : '';
    $last_name  = isset( $_POST['last_name'] ) ? esc_attr( $_POST['last_name'] ) : '';
    ?>
    <!-- Mock Stripe JS and Element for SetupIntent -->
    <script src="https://js.stripe.com/v3/"></script>
    <div style="margin-bottom: 20px; padding: 15px; border: 1px solid #ccc; border-radius: 4px;">
        <label style="display:block; margin-bottom: 8px; font-weight: bold;">Credit Card (Required to Bid)</label>
        <div id="stg-card-element" style="padding: 10px; background: white; border: 1px solid #ddd; border-radius: 4px;"></div>
        <div id="stg-card-errors" role="alert" style="color: red; margin-top: 8px;"></div>
        <input type="hidden" name="stg_stripe_payment_method" id="stg_stripe_payment_method" value="" />
        <p class="description" style="font-size: 12px; color: #666; margin-top: 5px;">Your card will not be charged now. It is kept on file for the 15% Buyer Premium and $100 Cleaning Deposit.</p>
    </div>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var stripe = Stripe('pk_test_mock_key'); // Mock key
        var elements = stripe.elements();
        var card = elements.create('card');
        
        var cardElementDiv = document.getElementById('stg-card-element');
        if (cardElementDiv) {
            card.mount('#stg-card-element');
            
            card.addEventListener('change', function(event) {
                var displayError = document.getElementById('stg-card-errors');
                if (event.error) {
                    displayError.textContent = event.error.message;
                } else {
                    displayError.textContent = '';
                }
            });

            var form = document.getElementById('registerform') || document.getElementById('setupform');
            if (form) {
                form.addEventListener('submit', function(event) {
                    // For the sake of the mock environment without a real SetupIntent client secret,
                    // we will just set a mock payment method ID and allow the form to submit.
                    // In production, we'd use stripe.confirmCardSetup(...)
                    var paymentMethodInput = document.getElementById('stg_stripe_payment_method');
                    if (!paymentMethodInput.value) {
                        event.preventDefault();
                        paymentMethodInput.value = 'pm_mock_method_id_123';
                        form.submit();
                    }
                });
            }
        }
    });
    </script>
    
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
        <input type="tel" name="stg_phone" id="stg_phone" class="input" value="<?php echo $phone_val; ?>" size="25" required="required" />
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
 * 2. Validate that the Checkbox was Checked and Phone is provided
 */
function stg_validate_terms_checkbox( $errors, $sanitized_user_login, $user_email ) {
    // If the box is NOT checked, throw a hard error and stop the registration
    if ( ! isset( $_POST['stg_agree_terms'] ) || $_POST['stg_agree_terms'] !== '1' ) {
        $errors->add( 'terms_error', '<strong>Required</strong>: You must agree to the Auction Terms and Conditions to create a bidder profile.' );
    }

    // Validate phone number
    if ( empty( $_POST['stg_phone'] ) || trim( $_POST['stg_phone'] ) === '' ) {
        $errors->add( 'phone_error', '<strong>Required</strong>: Please enter a valid phone number.' );
    }

    if ( empty( $_POST['first_name'] ) || trim( $_POST['first_name'] ) === '' ) {
        $errors->add( 'first_name_error', '<strong>Required</strong>: Please enter your first name.' );
    }

    if ( empty( $_POST['last_name'] ) || trim( $_POST['last_name'] ) === '' ) {
        $errors->add( 'last_name_error', '<strong>Required</strong>: Please enter your last name.' );
    }

    if ( empty( $_POST['stg_stripe_payment_method'] ) ) {
        $errors->add( 'stripe_error', '<strong>Required</strong>: Please provide a valid credit card.' );
    }

    return $errors;
}
add_filter( 'registration_errors', 'stg_validate_terms_checkbox', 10, 3 );

// Validate for Multisite / wp-signup.php
function stg_wpmu_validate_terms_checkbox( $result ) {
    if ( ! isset( $_POST['stg_agree_terms'] ) || $_POST['stg_agree_terms'] !== '1' ) {
        $result['errors']->add( 'terms_error', '<strong>Required</strong>: You must agree to the Auction Terms and Conditions to create a bidder profile.' );
    }

    if ( empty( $_POST['stg_phone'] ) || trim( $_POST['stg_phone'] ) === '' ) {
        $result['errors']->add( 'phone_error', '<strong>Required</strong>: Please enter a valid phone number.' );
    }

    if ( empty( $_POST['first_name'] ) || trim( $_POST['first_name'] ) === '' ) {
        $result['errors']->add( 'first_name_error', '<strong>Required</strong>: Please enter your first name.' );
    }

    if ( empty( $_POST['last_name'] ) || trim( $_POST['last_name'] ) === '' ) {
        $result['errors']->add( 'last_name_error', '<strong>Required</strong>: Please enter your last name.' );
    }

    if ( empty( $_POST['stg_stripe_payment_method'] ) ) {
        $result['errors']->add( 'stripe_error', '<strong>Required</strong>: Please provide a valid credit card.' );
    }

    return $result;
}
add_filter( 'wpmu_validate_user_signup', 'stg_wpmu_validate_terms_checkbox' );

/**
 * 3. Save the Phone Number and Names
 */
function stg_save_registration_fields( $user_id ) {
    if ( isset( $_POST['stg_phone'] ) && trim( $_POST['stg_phone'] ) !== '' ) {
        update_user_meta( $user_id, 'stg_phone', sanitize_text_field( $_POST['stg_phone'] ) );
    }
    if ( isset( $_POST['first_name'] ) && trim( $_POST['first_name'] ) !== '' ) {
        update_user_meta( $user_id, 'first_name', sanitize_text_field( $_POST['first_name'] ) );
    }
    if ( isset( $_POST['last_name'] ) && trim( $_POST['last_name'] ) !== '' ) {
        update_user_meta( $user_id, 'last_name', sanitize_text_field( $_POST['last_name'] ) );
    }
    if ( isset( $_POST['stg_stripe_payment_method'] ) && trim( $_POST['stg_stripe_payment_method'] ) !== '' ) {
        // In a real scenario, we'd create a Stripe Customer using this PaymentMethod and save the cus_XXX ID.
        // For this task, we'll store the mock PM ID directly.
        update_user_meta( $user_id, 'stg_stripe_payment_method', sanitize_text_field( $_POST['stg_stripe_payment_method'] ) );
        update_user_meta( $user_id, 'stg_stripe_customer_id', 'cus_mock_123' );
    }
}
add_action( 'user_register', 'stg_save_registration_fields' );

// Save meta to the signup table for Multisite
function stg_wpmu_add_signup_meta( $meta ) {
    if ( isset( $_POST['stg_phone'] ) && trim( $_POST['stg_phone'] ) !== '' ) {
        $meta['stg_phone'] = sanitize_text_field( $_POST['stg_phone'] );
    }
    if ( isset( $_POST['first_name'] ) && trim( $_POST['first_name'] ) !== '' ) {
        $meta['first_name'] = sanitize_text_field( $_POST['first_name'] );
    }
    if ( isset( $_POST['last_name'] ) && trim( $_POST['last_name'] ) !== '' ) {
        $meta['last_name'] = sanitize_text_field( $_POST['last_name'] );
    }
    if ( isset( $_POST['stg_stripe_payment_method'] ) && trim( $_POST['stg_stripe_payment_method'] ) !== '' ) {
        $meta['stg_stripe_payment_method'] = sanitize_text_field( $_POST['stg_stripe_payment_method'] );
    }
    return $meta;
}
add_filter( 'add_signup_meta', 'stg_wpmu_add_signup_meta' );

// Save meta to usermeta when the user is activated in Multisite
function stg_wpmu_activate_user( $user_id, $password, $meta ) {
    if ( isset( $meta['stg_phone'] ) && trim( $meta['stg_phone'] ) !== '' ) {
        update_user_meta( $user_id, 'stg_phone', sanitize_text_field( $meta['stg_phone'] ) );
    }
    if ( isset( $meta['first_name'] ) && trim( $meta['first_name'] ) !== '' ) {
        update_user_meta( $user_id, 'first_name', sanitize_text_field( $meta['first_name'] ) );
    }
    if ( isset( $meta['last_name'] ) && trim( $meta['last_name'] ) !== '' ) {
        update_user_meta( $user_id, 'last_name', sanitize_text_field( $meta['last_name'] ) );
    }
    if ( isset( $meta['stg_stripe_payment_method'] ) && trim( $meta['stg_stripe_payment_method'] ) !== '' ) {
        update_user_meta( $user_id, 'stg_stripe_payment_method', sanitize_text_field( $meta['stg_stripe_payment_method'] ) );
        update_user_meta( $user_id, 'stg_stripe_customer_id', 'cus_mock_123' );
    }
}
add_action( 'wpmu_activate_user', 'stg_wpmu_activate_user', 10, 3 );