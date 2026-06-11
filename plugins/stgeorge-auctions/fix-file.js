const fs = require('fs');
let file = fs.readFileSync('wp-content/themes/twentytwentyfive-child/single-storage_auction.php', 'utf8');

const regex = /<h1 class="text-4xl font-black text-gray-900 mb-4"><\?php echo esc_html\( \$display_title \); \?><\/h1>\s*<\?php if \( !empty\(\$video_url\) \) : \?>/s;

let replaceText = `<h1 class="text-4xl font-black text-gray-900 mb-4"><?php echo esc_html( $display_title ); ?></h1>

                    <?php if ( !empty($video_url) ) : ?>`;

file = file.replace(regex, replaceText);

const endRegex = /<\/div>\s*<\?php endif; \?>\s*<div class="border-t border-gray-100 pt-8">/s;

let endReplaceText = `</div>
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

                    <div class="border-t border-gray-100 pt-8">`;

file = file.replace(endRegex, endReplaceText);

fs.writeFileSync('wp-content/themes/twentytwentyfive-child/single-storage_auction.php', file);
