<?php
/**
 * Template Name: Auction Portal
 */

get_header(); ?>

<main class="bg-gray-50 min-h-screen pb-20">
    <section class="hero-section py-20 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-col lg:flex-row items-center justify-between gap-8">
                <div class="lg:w-1/2 text-center lg:text-left">
                    <h1 class="text-5xl font-extrabold mb-4">Storage Auctions</h1>
                    <p class="text-xl text-gray-300">Bid on abandoned units at St. George Storage locations.</p>
                </div>
                
                <div class="lg:w-1/3 w-full">
                    <div class="bg-white/10 backdrop-blur-md rounded-2xl shadow-xl p-6 border border-white/20">
                        <?php if ( !is_user_logged_in() ) : ?>
                            <h3 class="text-2xl font-bold text-white mb-4">Bidder Login</h3>
                            <form name="loginform" action="<?php echo esc_url( site_url( 'wp-login.php', 'login_post' ) ); ?>" method="post" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-white">Username</label>
                                    <input type="text" name="log" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-gray-900 focus:ring-amber-500 focus:border-amber-500">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-white">Password</label>
                                    <input type="password" name="pwd" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-gray-900 focus:ring-amber-500 focus:border-amber-500">
                                </div>
                                <button type="submit" class="w-full bg-amber-500 text-white font-bold py-3 rounded-lg hover:bg-amber-600 transition duration-150">
                                    Sign In to Bid
                                </button>
                            </form>
                            <p class="mt-4 text-sm text-center text-gray-200">
                                New bidder? <a href="<?php echo wp_registration_url(); ?>" class="text-amber-400 hover:text-amber-300 font-bold">Create an Account</a>
                            </p>
                        <?php else : ?>
                            <div class="text-center">
                                <h3 class="text-xl font-bold text-white">Welcome Back!</h3>
                                <p class="text-gray-200 mb-4"><?php $current_user = wp_get_current_user(); echo esc_html($current_user->display_name); ?></p>
                                <a href="<?php echo wp_logout_url( get_permalink() ); ?>" class="inline-block bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-lg font-bold transition duration-150">Logout</a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-8">
        <div class="grid grid-cols-1 gap-8">
            
            <div class="col-span-1 w-full">
                <div class="bg-white rounded-2xl shadow-xl p-8 border border-gray-100">
                    
                    <?php echo do_shortcode('[stg_auctions]'); ?>
                    
                </div>
            </div>

        </div>
    </div>
</main>

<?php get_footer(); ?>