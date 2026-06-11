<?php
// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Modernize the Login and Signup Pages
 */
function stg_custom_login_signup_styles() {
    // Only apply on login or signup pages
    global $pagenow;
    if ( $pagenow !== 'wp-login.php' && $pagenow !== 'wp-signup.php' ) return;
    ?>
    <style>
        /* Modernize the Background */
        body.login, body.wp-signup, body.wp-core-ui {
            background-color: #f3f4f6 !important; /* Light gray */
            font-family: 'Inter', sans-serif !important;
        }
        
        /* Center and style the main form boxes */
        #login, .mu_register {
            width: 100% !important;
            max-width: 450px !important;
            margin: 8vh auto !important;
            background: #ffffff !important;
            padding: 40px 30px !important;
            border-radius: 16px !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05) !important;
            border: 1px solid #e5e7eb !important;
        }

        /* Make inputs look modern */
        #login input[type="text"], #login input[type="password"], #login input[type="email"], #login input[type="tel"], #login input[type="number"],
        .mu_register input[type="text"], .mu_register input[type="email"], .mu_register input[type="password"], .mu_register input[type="tel"], .mu_register input[type="number"] {
            width: 100% !important;
            padding: 14px !important;
            border: 1px solid #d1d5db !important;
            border-radius: 8px !important;
            margin-top: 5px !important;
            margin-bottom: 20px !important;
            font-size: 16px !important;
            box-shadow: none !important;
        }

        /* Style the Submit Buttons (Blue) */
        #login .button-primary, .mu_register #submit, .mu_register input[type="submit"] {
            width: 100% !important;
            background-color: #2563eb !important;
            border: none !important;
            color: white !important;
            padding: 14px !important;
            border-radius: 8px !important;
            font-size: 16px !important;
            font-weight: 800 !important;
            cursor: pointer !important;
            transition: all 0.2s !important;
            text-shadow: none !important;
            box-shadow: none !important;
        }
        #login .button-primary:hover, .mu_register #submit:hover {
            background-color: #1d4ed8 !important;
        }

        /* Hide the default WordPress logo on the login screen */
        #login h1 a { display: none !important; }
        
        /* Add a custom text title instead of the WP logo */
        #login h1::after {
            content: "St. George Storage Auctions";
            font-size: 24px;
            font-weight: 800;
            color: #1f2937;
            display: block;
            margin-bottom: 20px;
        }

        /* Fix Multisite Signup specific quirks */
        .mu_register h2 {
            font-size: 24px !important;
            font-weight: 800 !important;
            color: #1f2937 !important;
            margin-bottom: 20px !important;
            text-align: center;
        }
        .mu_register p {
            color: #4b5563 !important;
        }
    </style>
    <?php
}
// Apply to both the standard login screen and the multisite signup screen
add_action( 'login_enqueue_scripts', 'stg_custom_login_signup_styles' );
add_action( 'wp_head', 'stg_custom_login_signup_styles' );