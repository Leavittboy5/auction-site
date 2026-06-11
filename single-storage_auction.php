<?php
/**
 * The template for displaying single auction units
 */
get_header(); ?>

<main class="bg-gray-50 min-h-screen py-12">
    <div class="max-w-4xl mx-auto px-4">
        <a href="<?php echo home_url(); ?>" class="text-blue-600 font-bold mb-6 inline-block hover:underline">
            <i class="fa-solid fa-arrow-left mr-2"></i> Back to All Auctions
        </a>

        <?php if ( have_posts() ) : while ( have_posts() ) : the_post();
            $auction_id = get_the_ID();
            $item_description = get_post_meta( $auction_id, '_stg_item_description', true );
            $video_url = get_post_meta( $auction_id, '_stg_video_url', true );

            // Get all images attached to this post to create a gallery
            $gallery_images = get_attached_media('image', $auction_id);
        ?>
            <div class="bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-100">
                <?php if ( has_post_thumbnail() ) : ?>
                    <div class="w-full h-96 overflow-hidden relative">
                        <?php the_post_thumbnail('full', ['class' => 'w-full h-full object-cover']); ?>
                        <?php if (count($gallery_images) > 1) : ?>
                            <div class="absolute bottom-4 right-4 bg-black bg-opacity-70 text-white px-3 py-1 rounded-full text-xs font-bold">
                                <i class="fa-solid fa-images mr-1"></i> +<?php echo count($gallery_images) - 1; ?> Images
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (count($gallery_images) > 1) : ?>
                    <div class="grid grid-cols-4 gap-2 p-4 bg-gray-50 border-b border-gray-100">
                        <?php foreach($gallery_images as $image) :
                            // Skip the featured image if it's in the gallery
                            if (get_post_thumbnail_id() == $image->ID) continue;
                        ?>
                            <div class="h-24 rounded-lg overflow-hidden cursor-pointer hover:opacity-80 transition-opacity">
                                <?php echo wp_get_attachment_image($image->ID, 'thumbnail', false, ['class' => 'w-full h-full object-cover']); ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="p-8 md:p-12">
                        <?php 
                        $unit_id = get_post_meta( $auction_id, '_stg_unit_id', true );
                        
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

                        $display_title = "Unit #" . esc_html( $unit_id );
                        if ( $is_upcoming || empty($unit_id) ) {
                            $display_title = "Upcoming Unit";
                        }
                        ?>
                        <h1 class="text-4xl font-black text-gray-900 mb-4"><?php echo esc_html( $display_title ); ?></h1>

                    <?php if ( !empty($video_url) ) : ?>
                        <div class="mb-8">
                            <h3 class="font-bold text-gray-800 mb-4">Video Walkthrough</h3>
                            <div class="aspect-video rounded-2xl overflow-hidden shadow-lg">
                                <?php
                                $embed_url = $video_url;
                                if (preg_match('%(?:youtube(?:-nocookie)?\.com/(?:[^/]+/.+/|(?:v|e(?:mbed)?)/|.*[?&]v=)|youtu\.be/)([^"&?/\s]{11})%i', $video_url, $match)) {
                                    $youtube_id = $match[1];
                                    $embed_url = "https://www.youtube.com/embed/" . $youtube_id;
                                }
                                ?>
                                <iframe class="w-full h-full" src="<?php echo esc_url($embed_url); ?>" frameborder="0" allowfullscreen></iframe>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="prose max-w-none text-gray-600 mb-8">
                        <?php the_content(); ?>
                    </div>

                    <?php if ( !empty($item_description) ) : ?>
                        <div class="bg-blue-50 p-6 rounded-2xl border border-blue-100 mb-8">
                            <h3 class="text-blue-900 font-bold mb-2 flex items-center">
                                <i class="fa-solid fa-box-open mr-2"></i> Visible Items
                            </h3>
                            <p class="text-blue-800"><?php echo esc_html($item_description); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="prose max-w-none text-gray-600 mb-8">
                        <?php the_content(); ?>
                    </div>

                    <?php if ( !empty($item_description) ) : ?>
                        <div class="bg-blue-50 p-6 rounded-2xl border border-blue-100 mb-8">
                            <h3 class="text-blue-900 font-bold mb-2 flex items-center">
                                <i class="fa-solid fa-box-open mr-2"></i> Visible Items
                            </h3>
                            <p class="text-blue-800"><?php echo esc_html($item_description); ?></p>
                        </div>
                    <?php endif; ?>

                    <div class="border-t border-gray-100 pt-8">
                        <?php echo do_shortcode('[stg_auctions id="' . $auction_id . '"]'); // This handles the live bidding logic ?>
                    </div>
                </div>
            </div>
        <?php endwhile; endif; ?>
    </div>
</main>

<?php get_footer(); ?>