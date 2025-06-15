<?php
/**
 * Plugin Name: DT Star Rating System
 * Description: Adds a star rating to posts with IP and cookie-based voting protection.
 * Version: 1.28
 * Author: D.T. Company
 */

defined('ABSPATH') || exit;

require 'plugin-update-checker/plugin-update-checker.php';
$myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
    'https://github.com/Digital-Trends-Gz/dtstarrate',
    __FILE__,
    'dtstarrate'
);

//Set the branch that contains the stable release.
$myUpdateChecker->setBranch('main');

//Optional: If you're using a private repository, specify the access token like this:
// $myUpdateChecker->setAuthentication('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');


// Create custom table on plugin activation
register_activation_hook(__FILE__, function () {
    global $wpdb;
    $table_name = $wpdb->prefix . 'post_ratings';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        post_id BIGINT(20) NOT NULL,
        rating INT(1) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        post_parent_id BIGINT(20) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_vote (post_id, ip_address)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
});

// Enqueue scripts and styles
add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style('star-rating-style', plugin_dir_url(__FILE__) . 'style.css', array(), '1.0.5');
    wp_enqueue_script('star-rating-script', plugin_dir_url(__FILE__) . 'rating.js', ['jquery'], '1.5.7', true);
    wp_localize_script('star-rating-script', 'starRatingAjax', [
        'ajaxurl' => admin_url('admin-ajax.php'),
    ]);
});
function my_enqueue_bootstrap_admin_only($hook) {
    // DataTables CSS
  // Enqueue Bootstrap CSS
    wp_enqueue_style(
        'bootstrap-admin-css',
        plugin_dir_url(__FILE__) . 'assets/css/bootstrap.css',
        array(),
        '5.3.3'
    );
        wp_enqueue_style(
        'dashboard_style_rate',
        plugin_dir_url(__FILE__) . 'assets/css/dashboard_style.css',
        array(),
        '5.3.5'
    );
            wp_enqueue_style(
        'dashboard_style_swet_alert',
        plugin_dir_url(__FILE__) . 'assets/css/sweetalerts.min.css',
        array(),
        '5.3.4'
    );
    wp_enqueue_script( 'bootstrap-js',     plugin_dir_url(__FILE__) . 'assets/js/bootstrap.min.js', array( 'jquery' ), null, true );

        if ( $hook == 'dt-star-rating_page_dt-star-posts' ||  $hook == 'dt-star-rating_page_dt-star-settings' ) {
   wp_enqueue_style( 'datatables-css', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css' );

    // jQuery + DataTables JS
    wp_enqueue_script( 'datatables-js', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', array( 'jquery' ), null, true );
    
    // Your custom script to initialize the table
    wp_enqueue_script( 'dt-star-datatables-init', plugins_url( 'assets/js/datatable.js', __FILE__ ), array( 'datatables-js' ), '5.4.3', true );

    }
         wp_enqueue_script( 'sweet_alerts_js',  plugin_dir_url(__FILE__) . 'assets/js/sweetalert.min.js', array( 'jquery' ), null, true );
        // script
        wp_enqueue_script( 'script-js', plugin_dir_url(__FILE__) . 'assets/js/script.js', array( 'jquery' ), null, true );

}
add_action('admin_enqueue_scripts', 'my_enqueue_bootstrap_admin_only');

// Shortcode to show star rating

// Shortcode to show star rating
add_shortcode('star_rating', function () {
    global $post, $wpdb;
    $post_id = $post->ID;
    $ip = $_SERVER['REMOTE_ADDR'];
    $table = $wpdb->prefix . 'post_ratings';

    // Default: use only this post_id
    $target_ids = [$post_id];
    $current_language = '';

    // Check for WPML and parent grouping logic
    if (function_exists('icl_object_id')) {
        $post_parent_id = $wpdb->get_var($wpdb->prepare(
            "SELECT post_parent_id FROM $table WHERE post_id = %d LIMIT 1",
            $post_id
        ));

        if (!empty($post_parent_id) && $post_parent_id != 0) {
            $related_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM $table WHERE post_parent_id = %d",
                $post_parent_id
            ));
            if (!empty($related_ids)) {
                $target_ids = $related_ids;
            }
        }

        // Get current language via WPML
        $current_language = apply_filters('wpml_current_language', null);
    }

    // Query safe placeholders
    $placeholders = implode(',', array_fill(0, count($target_ids), '%d'));

    // Check if already rated
    $already_rated = isset($_COOKIE["rated_post_$post_id"]) || $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE post_id IN ($placeholders) AND ip_address = %s",
        [...$target_ids, $ip]
    ));

    // Google snippet options
    $google_snippet = get_option('dt_star_rating_setting');
    $google_enabled = '';
    $types = [];

    if ($google_snippet) {
        $google_enabled = $google_snippet['dt_google'] ?? '';
        $types = $google_snippet['types'] ?? [];
    }

    // Get all ratings
    $ratings = $wpdb->get_results($wpdb->prepare(
        "SELECT rating, COUNT(*) as count FROM $table WHERE post_id IN ($placeholders) GROUP BY rating",
        $target_ids
    ), OBJECT_K);

    $total_votes = array_sum(wp_list_pluck($ratings, 'count'));
    $avg = $total_votes ? round(array_sum(array_map(fn($r) => $r->rating * $r->count, $ratings)) / $total_votes, 1) : 0;

    $plugin_url = plugin_dir_url(__FILE__);
    $star_single = $plugin_url . 'assets/images/star_single.svg';
    $app_name = get_bloginfo('name');
    ob_start();
    ?>
    <div class="rated">
        <div class="rated__wrapper">
            <div class="rated__items">
                <div class="rated-reviews">
                    <span class="rated-reviews__counter test" id="js-ratingValue"><?php echo $avg; ?></span>
                    <?php
                    $avg = number_format((float)$avg, 1);
                    $rounded_avg = number_format((floor($avg * 2) / 2), 1);
                    $rating_img = $plugin_url . 'assets/images/stars_' . str_replace('.', '_', $rounded_avg) . '.svg';
                    ?>
                    <img class="rated-reviews__star" src="<?php echo esc_url($rating_img); ?>" width="136" height="25" alt="<?php echo esc_attr($avg); ?> stars">
                    <p class="rated-reviews__count">Average rating based on <?php echo $total_votes; ?> reviews</p>
                </div>

                <div class="rated-reviews">
                    <div class="rated-reviews__ratings">
                        <?php for ($i = 5; $i >= 1; $i--):
                            $count = $ratings[$i]->count ?? 0;
                            $percent = $total_votes ? round(($count / $total_votes) * 100) : 2;
                            if ($percent == 0) $percent = 2;
                            $colors = ['green', 'lime', 'yellow', 'orange', 'red'];
                            $color = $colors[5 - $i];
                            ?>
                            <div class="rated-reviews__rating">
                                <div class="rated-reviews__number">
                                    <span><?php echo $i; ?></span>
                                    <img src="<?php echo $star_single; ?>" width="15" height="15" alt="star">
                                </div>
                                <div class="rated-reviews__progressbar animate">
                                    <div class="bar bar--color-<?php echo $color; ?>" style="width: <?php echo $percent; ?>% !important"></div>
                                </div>
                                <div class="rated-reviews__amount"><?php echo $count; ?></div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>

                <div class="rated-reviews">
                    <span class="rated-reviews__counter2 " id="js-ratingValue2"><?php echo $already_rated ? 'Thank you!' : 'Rate Us'; ?></span>
                    <div id="star-rating " class="<?php echo $already_rated ?'star_rated' : "" ?>" data-postid="<?php echo $post_id; ?>" data-rated="<?php echo $already_rated ? '1' : '0'; ?>">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <span class="star" data-value="<?php echo $i; ?>">&#9733;</span>
                        <?php endfor; ?>
                    </div>
                    <div id="rating-response"></div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($google_enabled && !empty($types)): ?>
        <?php
            $custom_logo_id = get_theme_mod('custom_logo');
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'full');
            ?>
        <?php foreach ($types as $type):
            if (intval($total_votes) == 0 && floatval($avg) == 0.0) {
                $avg = 4.5;
                $total_votes = 10;
            }
            ?>
            <script type="application/ld+json">
            {
              "@context": "https://schema.org",
              "@type": "<?php echo esc_js($type); ?>",
              "name": "<?php echo esc_js($app_name); ?>",
              "alternateName": [
                "Instagram downloader",
                "<?php echo esc_js($app_name); ?>",
                "<?php echo esc_js($app_name); ?> APP",
                "<?php echo esc_js($app_name); ?>.com"
              ],
              "url": "<?php echo esc_url(get_permalink()); ?>",
              <?php if (!empty($current_language)): ?>
              "inLanguage": "<?php echo esc_js($current_language); ?>",
              <?php endif; ?>
              "image": "<?php echo esc_url($logo_url); ?>",
              "operatingSystem": "Windows, Linux, iOS, Android, OSX, macOS",
              "applicationCategory": "UtilitiesApplication",
              "featureList": [
                "HD Profile downloader",
                "Photo downloader",
                "Video Downloader",
                "Reel Downloader",
                "IGTV Downloader",
                "Gallery Downloader",
                "Story Downloader",
                "Highlights Downloader"
              ],
              "contentRating": "Everyone",
              "aggregateRating": {
                "@type": "AggregateRating",
                "ratingValue": "<?php echo esc_js($avg); ?>",
                "reviewCount": "<?php echo esc_js($total_votes); ?>"
              },
              "offers": {
                "@type": "Offer",
                "priceCurrency": "USD",
                "price": "0"
              }
            }
            </script>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php
    return ob_get_clean();
});

// AJAX handler
add_action('wp_ajax_submit_rating', 'submit_star_rating');
add_action('wp_ajax_nopriv_submit_rating', 'submit_star_rating');

function submit_star_rating() {
    global $wpdb;

    $post_id = intval($_POST['post_id']);
    $rating = intval($_POST['rating']);
    $ip = $_SERVER['REMOTE_ADDR'];
    $table = $wpdb->prefix . 'post_ratings';

    // Check if user has already rated
    $already = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table WHERE post_id = %d AND ip_address = %s",
        $post_id, $ip
    ));

    if ($already || isset($_COOKIE["rated_post_$post_id"])) {
        echo 'You have already rated this post.';
    } else {
        // Set default data
        $data = [
            'post_id'    => $post_id,
            'rating'     => $rating,
            'ip_address' => $ip,
        ];

        // If WPML is active, get and add post_parent_id
      if (function_exists('icl_object_id')) {
        $post = get_post($post_id);
        if ($post) {
            $element_type = 'post_' . $post->post_type;
            $trid = apply_filters('wpml_element_trid', null, $post_id, $element_type);


            if ($trid) {
                $translations = apply_filters('wpml_get_element_translations', null, $trid, $element_type);

                foreach ($translations as $lang => $translation) {
                    if (!empty($translation->original)) {
                        $data['post_parent_id'] = $translation->element_id;
                        break;
                    }
                }
            }
        }
    }


        // Insert rating with optional post_parent_id
        $wpdb->insert($table, $data);

        // Set a cookie for 1 year
        setcookie("rated_post_$post_id", '1', time() + 365 * DAY_IN_SECONDS, "/");

        echo 'Thanks for your rating!';
    }

    wp_die();
}

add_action('admin_menu', 'dt_star_create_menu');

function dt_star_create_menu() {
    // Main menu page
    add_menu_page(
        'Dt Star Rating',       // Page title
        'Dt Star Rating',                 // Menu title
        'manage_options',            // Capability
        'dt-star-dashboard',       // Menu slug
        'dt_stars_dashboard_page',  // Function to display the page content
        'dashicons-star-filled',   // Icon
        6                            // Position
    );
    // Submenu page 1
    add_submenu_page(
        'dt-star-dashboard',       // Parent slug
        'Posts',                  // Page title
        'Posts',                  // Menu title
        'manage_options',            // Capability
        'dt-star-posts',        // Menu slug
        'dt_star_post'    // Function to display the content
    );
    add_submenu_page(
        'dt-star-dashboard',       // Parent slug
        'Google Snippet',                  // Page title
        'Google Snippet',                  // Menu title
        'manage_options',            // Capability
        'dt-star-google-snipet',        // Menu slug
        'dt_star_google_snipet'    // Function to display the content
    );
    // Submenu page 1
    add_submenu_page(
        'dt-star-dashboard',       // Parent slug
        'Settings',                  // Page title
        'Settings',                  // Menu title
        'manage_options',            // Capability
        'dt-star-settings',        // Menu slug
        'my_plugin_settings_page'    // Function to display the content
    );
    

    // You can add more subpages similarly
}
function dt_stars_dashboard_page() {
     global $wpdb;
    $table = $wpdb->prefix . 'post_ratings';
    $user_count =  $wpdb->get_var("SELECT COUNT(DISTINCT ip_address) FROM $table WHERE ip_address IS NOT NULL");
    $post_count = $wpdb->get_var( "SELECT COUNT(DISTINCT post_id) FROM $table WHERE post_id IS NOT NULL" );
    $top_post = $wpdb->get_row( "
        SELECT p.ID, p.post_title, AVG(r.rating) AS avg_rating
        FROM $table r
        INNER JOIN {$wpdb->posts} p ON r.post_id = p.ID
        WHERE r.post_id IS NOT NULL
        GROUP BY r.post_id
        ORDER BY avg_rating DESC
        LIMIT 1
    " );
  $recent_post_ids = $wpdb->get_col( "
    SELECT DISTINCT post_id
    FROM $table
    WHERE post_id IS NOT NULL
    ORDER BY created_at DESC
    LIMIT 10
" );



    $link = "#";
    $post_name = "Not Rtaed";
    if ( $top_post ) {
    $link = get_permalink( $top_post->ID );
    $post_name =esc_html( $top_post->post_title );}

        ?>
    <div class="container">
    <div class="cards"> 
        <div class="row">
            <div class="col">
            <div class="card user_rated">
                    <h3>User Rated Count</h3>
                    <h5><?php echo $user_count; ?></h5>
                </div>
            </div>
            <div class="col">
                <div class="card post_rared">
            <h3>Post Rated Count</h3>
            <h5><?php echo $post_count; ?></h5>
                 </div>
            </div>
            <div class="col">
           <div class="card post_text">
            <h3>The best Rated Post</h3>
            <h5><a class="post_link" href="<?php echo esc_url( $link ) ?>"><?php echo  $post_name; ?></a></h5>
                 </div>
            </div>
        </div>
        <?php 
        if ( ! empty( $recent_post_ids ) ) {
     $ids_in = implode(',', array_map('intval', $recent_post_ids));
         $posts = $wpdb->get_results( "
        SELECT 
            r.post_id, 
            p.post_title, 
            COUNT(r.id) as total_ratings, 
            AVG(r.rating) as avg_rating
        FROM $table r
        INNER JOIN {$wpdb->posts} p ON r.post_id = p.ID
        WHERE r.post_id IN ($ids_in)
        GROUP BY r.post_id
        ORDER BY MAX(r.created_at) DESC
    " );
?>
        <div class="row">
            <div class="col-lg-12">
                <h3 class="title_of_table">
                    The Last Post Rated
                </h3>
            </div>
            <div class="col-lg-12">
                
                <div class="card">
                    <div class="card-body">
                <table class="table">
                <thead>
                    <tr>
                    <th scope="col">#</th>
                    <th scope="col">Post Title</th>
                      <th scope="col">Total Of Rating</th>
                    <th scope="col">Avarge Of Rating</th>
                    </tr>
                </thead>
                <tbody>
                    <?php  foreach ( $posts as $key=>$post ) {
                  
                         $link = get_permalink( $post->post_id );
                         $number = $key +1;

                        ?>
                    <tr>
                    <th scope="row"><?php echo $key+1 ; ?></th>
                    <td><?php echo esc_html( $post->post_title ) ; ?> <a href="<?php echo  $link  ?>" target="_blanck">
                        <svg width="20px" height="20px" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                        <defs><style>.a{fill:none;stroke:#000000;stroke-linecap:round;stroke-linejoin:round;}</style>
                    </defs>
                    <path class="a" d="M23.0551,14.2115l6.942-6.9421c2.6788-2.6788,7.5386-2.1623,10.2172.5164s3.1951,7.5384.5163,10.2172L30.4481,28.2856c-2.6788,2.6788-7.5386,2.1623-10.2172-.5163"/>
                    <path class="a" d="M24.9449,33.7885l-6.942,6.9421c-2.6788,2.6788-7.5386,2.1623-10.2172-.5164S4.5906,32.6758,7.2694,29.997L17.5519,19.7144c2.6788-2.6788,7.5386-2.1623,10.2172.5163"/></svg></a></td>
                    <td><?php echo intval( $post->total_ratings ) ; ?></td>
                    <td><?php echo round( $post->avg_rating, 2 ); ; ?></td>
                    </tr>
                    <?php } ?>
                </tbody>
                </table>
                </div>
            </div>
             </div>
        </div>
 <?php } ?>
    </div>
    </div>
    <?php
}
function dt_star_post(){
    ?>
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
    <div class="card posts_card">
        <div class="card-header">
                        <h4>
                            DT Rating Posts / Posts
                        </h4>
                    </div>
        <div class="card-body">
                <table id="dt-rate-post-table" class="display ui celled table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Post Title</th>
                            <th>Average Rating</th>
                            <th>Total Ratings</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- jQuery will insert rows here -->
                    </tbody>
                </table>
        </div>
    </div>
            </div>
        </div>
    </div>



    
    <?php

}

function my_plugin_settings_page() {
    ?>
      <div class="container">
        <div class="row">
            <div class="col-lg-12">
    <div class="card posts_card setting_card">
        <h3>Settings Of Dt Star Plugin</h3>
        <div class="row">
            <div class="col-lg-12">
            <h6 class="header_setting">Apply plugin for all post :</h6>
            <button class="add_post_setting" id="bulk_add_all_post">Add For Every Post</button>
            <button class="add_post_setting delete_button" id="bulk_delete_all_post">Delete From Every Post</button>
              <button class="add_post_setting specific_button" type="button"  data-bs-toggle="modal" data-bs-target="#addpostModal">Add for specific post</button>

            </div>
        </div>
           

    </div>
    <div id="addpostModal"  aria-labelledby="addpostModalLabel" aria-hidden="true" class="modal fade" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Add Plugin for specific post</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
         <div class="container">
        <div class="row">
            <div class="col-lg-12">
    <div class="card posts_card">
        <div class="card-body">
                <table id="dt-rate-post-table_in_modal" class="display ui celled table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Post Title</th>
                            <th>Action</th>
                          
                        </tr>
                    </thead>
                    <tbody>
                        <!-- jQuery will insert rows here -->
                    </tbody>
                </table>
        </div>
    </div>
            </div>
        </div>
    </div>

      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

    <?php
}
// // Automatically add shortcode to post content
// function auto_add_ratings_to_posts($content) {
//     // Only for single posts (not pages, archives, etc.)
//     if (is_single() && 'post' === get_post_type()) {
//         // Append the shortcode (you could also prepend)
//         $content .= '[post_ratings]';
//     }
//     return $content;
// }
// add_filter('the_content', 'auto_add_ratings_to_posts');
// Run this once to add to all existing posts
function bulk_add_ratings_shortcode() {
    $success = false;
    $count   = 0;
    $added   = 0;
    $position = isset($_POST['position']) ? intval($_POST['position']) : 0;

    if (function_exists('icl_object_id')) {
        // WPML is active: only loop over parent posts
        global $wpdb;

        $parents = $wpdb->get_results("
            SELECT p.ID, p.post_type
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->prefix}icl_translations t
                ON p.ID = t.element_id
            WHERE p.post_status = 'publish'
              AND p.post_type IN ('post', 'page')
              AND t.source_language_code IS NULL
        ");

        foreach ($parents as $parent) {
            $trid = apply_filters('wpml_element_trid', null, $parent->ID, 'post_' . $parent->post_type);
            $translations = apply_filters('wpml_get_element_translations', null, $trid, 'post_' . $parent->post_type);

            foreach ($translations as $translation) {
                $translated_post = get_post($translation->element_id);
                if (!$translated_post) continue;

                $count++;

                if (strpos($translated_post->post_content, '[star_rating]') === false) {
                    $updated_content = place_of_add_short_code($position, $translated_post->post_content, "\n[star_rating]");

                    wp_update_post(array(
                        'ID' => $translated_post->ID,
                        'post_content' => $updated_content
                    ));

                    $added++;
                    $success = true;
                }
            }
        }
    } else {
        // WPML not active: fetch all published posts/pages
        $posts = get_posts(array(
            'post_type'   => array('post', 'page'),
            'numberposts' => -1,
            'post_status' => 'publish'
        ));

        foreach ($posts as $post) {
            $count++;

            if (strpos($post->post_content, '[star_rating]') === false) {
                $updated_content = place_of_add_short_code($position, $post->post_content, "\n[star_rating]");

                wp_update_post(array(
                    'ID' => $post->ID,
                    'post_content' => $updated_content
                ));

                $added++;
                $success = true;
            }
        }
    }

    $msg = ($added === 0)
        ? "All $count post(s) already have the star rating."
        : "Added star rating to $added post(s) out of $count.";

    wp_send_json(array(
        "status" => 200,
        "success" => $success,
        "msg" => $msg
    ));
}


add_action('wp_ajax_myplugin_get_rating_data_bulk_add', 'bulk_add_ratings_shortcode');

function bulk_delete_ratings_shortcode() {
    $success = false;
    $count = 0;

    if (function_exists('icl_object_id')) {
        // WPML is active: get only parent posts
        global $wpdb;

        $parents = $wpdb->get_results("
            SELECT p.ID, p.post_type
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->prefix}icl_translations t
                ON p.ID = t.element_id
            WHERE p.post_status = 'publish'
              AND p.post_type IN ('post', 'page')
              AND t.source_language_code IS NULL
        ");

        foreach ($parents as $parent) {
            // Get all translations of this parent post
            $trid = apply_filters('wpml_element_trid', null, $parent->ID, 'post_' . $parent->post_type);
            $translations = apply_filters('wpml_get_element_translations', null, $trid, 'post_' . $parent->post_type);

            foreach ($translations as $translation) {
                $translated_post = get_post($translation->element_id);
                if (!$translated_post) continue;

                if (false !== strpos($translated_post->post_content, '[star_rating]')) {
                    $updated_content = str_replace('[star_rating]', '', $translated_post->post_content);

                    if ($updated_content !== $translated_post->post_content) {
                        wp_update_post(array(
                            'ID' => $translated_post->ID,
                            'post_content' => $updated_content
                        ));
                        $count++;
                        $success = true;
                    }
                }
            }
        }
    } else {
        // WPML not active: just loop through all posts
        $posts = get_posts(array(
            'post_type'   => array('post', 'page'),
            'numberposts' => -1,
            'post_status' => 'publish'
        ));

        foreach ($posts as $post) {
            if (false !== strpos($post->post_content, '[star_rating]')) {
                $updated_content = str_replace('[star_rating]', '', $post->post_content);

                if ($updated_content !== $post->post_content) {
                    wp_update_post(array(
                        'ID' => $post->ID,
                        'post_content' => $updated_content
                    ));
                    $count++;
                    $success = true;
                }
            }
        }
    }

    $msg = $success
        ? "Removed [star_rating] shortcode from $count post(s)"
        : "No posts contained the [star_rating] shortcode";

    $json_data = array(
        "status"  => 200,
        "success" => $success,
        "msg"     => $msg,
        "count"   => $count
    );

    wp_send_json($json_data);
}

add_action('wp_ajax_myplugin_get_rating_data_bulk_delete', 'bulk_delete_ratings_shortcode');

add_action('admin_init', 'my_plugin_settings_init');

function my_plugin_settings_init() {
    register_setting('my_plugin_options_group', 'my_plugin_option_name');

    add_settings_section(
        'my_plugin_section_id',
        'Main Settings Section',
        null,
        'my-plugin-settings'
    );

    add_settings_field(
        'my_plugin_field_id',
        'Example Setting',
        'my_plugin_field_callback',
        'my-plugin-settings',
        'my_plugin_section_id'
    );
}
function delete_ratings_shortcode() {
    $post_id = intval($_POST['post_id']);
    $shortcode = sanitize_text_field($_POST['shortcode']);

    // Get the post
    $post = get_post($post_id);
    if (!$post) {
        wp_send_json_error('Post not found');
    }

    $success = false;
    $count = 0;

    if (function_exists('icl_object_id')) {
        // WPML is active: get all translations (including parent)
        $trid = apply_filters('wpml_element_trid', null, $post_id, 'post_' . $post->post_type);
        $translations = apply_filters('wpml_get_element_translations', null, $trid, 'post_' . $post->post_type);

        foreach ($translations as $translation) {
            $translated_post = get_post($translation->element_id);
            if (!$translated_post) continue;

            if (false !== strpos($translated_post->post_content, $shortcode)) {
                $updated_content = str_replace($shortcode, '', $translated_post->post_content);

                if ($updated_content !== $translated_post->post_content) {
                    wp_update_post(array(
                        'ID' => $translated_post->ID,
                        'post_content' => $updated_content
                    ));
                    $success = true;
                    $count++;
                }
            }
        }
    } else {
        // WPML not active: remove from only this post
        if (false !== strpos($post->post_content, $shortcode)) {
            $updated_content = str_replace($shortcode, '', $post->post_content);

            if ($updated_content !== $post->post_content) {
                wp_update_post(array(
                    'ID' => $post->ID,
                    'post_content' => $updated_content
                ));
                $success = true;
                $count = 1;
            }
        }
    }

    $msg = $success 
        ? "Removed [star_rating] shortcode from $count post(s)." 
        : "No post contained the [star_rating] shortcode.";

    $json_data = array(
        "status" => 200,
        "success" => $success,
        "msg" => $msg,
    );

    wp_send_json($json_data);
}

add_action('wp_ajax_myplugin_get_rating_data_delete', 'delete_ratings_shortcode');
function my_plugin_field_callback() {
    $option = get_option('my_plugin_option_name');
    echo '<input type="text" name="my_plugin_option_name" value="' . esc_attr($option) . '">';
}
add_action('wp_ajax_myplugin_get_rating_data', 'myplugin_get_rating_data_callback');

function myplugin_get_rating_data_callback() {
    global $wpdb;
    header("Content-Type: application/json");
    
    $request = $_GET;
    $table = $wpdb->prefix . 'post_ratings';
    
    // Define columns for sorting
    $columns = array(
        array('db' => 'id', 'dt' => 0),
        array('db' => 'title', 'dt' => 1),
        array('db' => 'avg', 'dt' => 2),
        array('db' => 'total', 'dt' => 3)
    );
    
    // Pagination
    $limit = intval($request['length']) ;
    $offset = intval($request['start']) ;
 
    
    // Search
    $searchValue = $request['search']['value'];
    $searchQuery = "";
    
    if (!empty($searchValue)) {
        $searchQuery = " HAVING title LIKE '%" . esc_sql($searchValue) . "%' OR 
                        avg LIKE '%" . esc_sql($searchValue) . "%' OR 
                        total LIKE '%" . esc_sql($searchValue) . "%'";
    }
    $limit_query = "";
    if ( isset($request['start']) && isset($request['length']) ) {
        $offset = intval($request['start']);
        $limit_query = "LIMIT $offset, " . intval($request['length']);
    }
    // Total records count
    $totalData = $wpdb->get_var("SELECT COUNT(DISTINCT post_id) FROM $table");
    
    // Ordering
    $order_by = "";
    if (isset($request['order']) && !empty($request['order'])) {
        
        $order_column = $request['order'][0]['column'];
        $order_direction = $request['order'][0]['dir'];
        $order_by = " ORDER BY " . $columns[$order_column]['db'] . " " . $order_direction;
    }
    
    // Main query with pagination
    $sql = "SELECT 
                post_id, 
                AVG(rating) as avg, 
                COUNT(*) as total,
            (SELECT post_title FROM {$wpdb->posts} WHERE ID = post_id) as title
            FROM $table
            GROUP BY post_id";
            if (!empty($searchQuery)) {
            $sql .= $searchQuery;
        }
            if (!empty($order_by)) {
            $sql .= " " . $order_by;
        }
        //     $sql .= " LIMIT $offset, $limit";
   $results = $wpdb->get_results($sql);
    // Prepare data
    $data = array();
    $i = $offset + 1;
    foreach ($results as $row) {
        $nestedData = array();
        $nestedData['index'] = $i++;
        $nestedData['title'] =get_the_title( $row->post_id ) ? get_the_title( $row->post_id ) : '(No title)';
        $nestedData['avg'] = round($row->avg, 2);
        $nestedData['total'] = intval($row->total);
        $data[] = $nestedData;
    }
    
    // Filtered count (same as total in this case unless searching)
    $filterDataCount = !empty($searchValue) ? $wpdb->get_var("SELECT COUNT(*) FROM ($sql) as filtered") : $totalData;
    
    // JSON response
    $json_data = array(
        "draw" => intval($request['draw']),
        "iTotalRecords" => intval($totalData),
        "iTotalDisplayRecords" => intval($filterDataCount),
        "aaData" => $data
    );
    
    wp_send_json($json_data);
}

function add_short_code_for_post() {
    // Get the post ID and other data from AJAX request
    $post_id = intval($_POST['post_id']);
    $shortcode = sanitize_text_field($_POST['shortcode']);
    $position = isset($_POST['position']) ? intval($_POST['position']) : 0;

    // Get the post
    $post = get_post($post_id);
    if (!$post) {
        wp_send_json_error('Post not found');
    }

    // Check if WPML is active
    if (function_exists('icl_object_id')) {
        // WPML is active: update all translations
        $trid = apply_filters('wpml_element_trid', null, $post_id, 'post_' . $post->post_type);
        $translations = apply_filters('wpml_get_element_translations', null, $trid, 'post_' . $post->post_type);

        $updated_count = 0;

        foreach ($translations as $lang => $translation) {
            $translated_post = get_post($translation->element_id);
            if (!$translated_post) continue;

            $updated_content = place_of_add_short_code($position, $translated_post->post_content, $shortcode);

            $updated_post = array(
                'ID'           => $translated_post->ID,
                'post_content' => $updated_content,
            );

            $result = wp_update_post($updated_post);
            if (!is_wp_error($result)) {
                $updated_count++;
            }
        }

        if ($updated_count > 0) {
            wp_send_json_success("Shortcode added to $updated_count translations successfully");
        } else {
            wp_send_json_error("No translations updated");
        }
    } else {
       
        // WPML not active: only update current post
        $updated_content = place_of_add_short_code($position, $post->post_content, $shortcode);
              $updated_post = array(
            'ID'           => $post_id,
            'post_content' => $updated_content,
        );

        $result = wp_update_post($updated_post);

        if ($result) {
            wp_send_json_success('Shortcode added successfully');
        } else {
            wp_send_json_error('Failed to update post');
        }
    }
}


add_action('wp_ajax_dt_rate_specific_post_add', 'add_short_code_for_post');

add_action('wp_ajax_dt_rate_specific_post', 'dt_rate_specific_post_table');

function dt_rate_specific_post_table() {
    try {
        global $wpdb;
        header("Content-Type: application/json");

        $request = $_GET;
        $table = $wpdb->prefix . 'post_ratings';
        $post_table = $wpdb->posts;

        // Define columns for sorting
        $columns = array(
            array('db' => 'ID', 'dt' => 0),
            array('db' => 'post_title', 'dt' => 1),
        );

        // Pagination
        $limit = isset($request['length']) ? intval($request['length']) : 10;
        $offset = isset($request['start']) ? intval($request['start']) : 0;

        // Search
        $searchValue = isset($request['search']['value']) ? sanitize_text_field($request['search']['value']) : '';
        $searchQuery = "";
        $searchBindings = array();

        if (!empty($searchValue)) {
            $searchQuery = " AND (p.post_title LIKE %s OR p.ID LIKE %s)";
            $searchBindings[] = '%' . $searchValue . '%';
            $searchBindings[] = '%' . $searchValue . '%';
        }

        // Ordering
        $order_by = "";
        if (isset($request['order'][0]['column']) && isset($request['order'][0]['dir'])) {
            $order_column = intval($request['order'][0]['column']);
            $order_direction = in_array(strtoupper($request['order'][0]['dir']), ['ASC', 'DESC']) ? strtoupper($request['order'][0]['dir']) : 'ASC';
            if (isset($columns[$order_column])) {
                $order_by = " ORDER BY p." . esc_sql($columns[$order_column]['db']) . " " . $order_direction;
            }
        }

        // Base query
        $sql = "SELECT p.ID, p.post_title, p.post_content
                FROM $post_table p";

        if (function_exists('icl_object_id')) {
            // WPML active
            $sql .= " 
                JOIN {$wpdb->prefix}icl_translations t 
                ON p.ID = t.element_id 
                WHERE p.post_status = 'publish'
                AND p.post_type IN ('post', 'page')
                AND t.element_type IN ('post_post', 'post_page')
                AND t.source_language_code IS NULL";
        } else {
            // WPML not active
            $sql .= "
                WHERE p.post_status = 'publish'
                AND p.post_type IN ('post', 'page')";
        }

        // Add search clause
        if (!empty($searchQuery)) {
            $sql .= $wpdb->prepare($searchQuery, ...$searchBindings);
        }

        // Total count before pagination
        $count_sql = "SELECT COUNT(*) FROM ($sql) AS temp_table";
        $totalFiltered = $wpdb->get_var($count_sql);

        // Add ordering and limit
        $sql .= $order_by;
        $sql .= " LIMIT %d OFFSET %d";
        $prepared_sql = $wpdb->prepare($sql, $limit, $offset);

        // Get results
        $results = $wpdb->get_results($prepared_sql);

        // Total records count
        $totalData = $wpdb->get_var("SELECT COUNT(*) FROM $post_table WHERE post_status = 'publish' AND post_type IN ('post', 'page')");

        // Prepare data for JSON
        $data = array();
        foreach ($results as $row) {
            $nestedData = array();
            $nestedData['ID'] = $row->ID;
            $nestedData['title'] = $row->post_title ? esc_html($row->post_title) : '(No title)';

            $has_shortcode = has_shortcode($row->post_content, 'star_rating');
            if ($has_shortcode) {
                $nestedData['action'] = '<button class="add_post_setting delete_button delete_short_code" data-id="' . esc_attr($row->ID) . '">Delete Short Code</button>';
            } else {
                $nestedData['action'] = '<button class="add_post_setting add_short_code" data-id="' . esc_attr($row->ID) . '">Add Short Code</button>';
            }

            $link = esc_url(get_permalink($row->ID));
            $nestedData['title'] .= ' <a href="' . $link . '" target="_blank">
                <svg width="20px" height="20px" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                    <defs><style>.a{fill:none;stroke:#000000;stroke-linecap:round;stroke-linejoin:round;}</style></defs>
                    <path class="a" d="M23.0551,14.2115l6.942-6.9421c2.6788-2.6788,7.5386-2.1623,10.2172.5164s3.1951,7.5384.5163,10.2172L30.4481,28.2856c-2.6788,2.6788-7.5386,2.1623-10.2172-.5163"/>
                    <path class="a" d="M24.9449,33.7885l-6.942,6.9421c-2.6788,2.6788-7.5386,2.1623-10.2172-.5164S4.5906,32.6758,7.2694,29.997L17.5519,19.7144c2.6788-2.6788,7.5386-2.1623,10.2172.5163"/>
                </svg>
            </a>';

            $data[] = $nestedData;
        }

        // JSON response
        $json_data = array(
            "draw" => isset($request['draw']) ? intval($request['draw']) : 0,
            "iTotalRecords" => intval($totalData),
            "iTotalDisplayRecords" => intval($totalFiltered),
            "aaData" => $data
        );

        wp_send_json($json_data);

    } catch (Exception $ex) {
        wp_send_json_error(array("error" => $ex->getMessage()));
    }
}


// Add this to your theme's functions.php or a custom plugin

// function to add the short code inside the content  or in first or in end

function place_of_add_short_code($place, $content, $short_code) {
    $place = (int)$place;
    if ($place === 0) {
        $content = $short_code . $content;

    } elseif ($place === 1) {
        $paragraphs = explode('</p>', $content);

        if (count($paragraphs) > 1) {
            
            $middle_pos = floor(count($paragraphs) / 2);
            $paragraphs[$middle_pos] .= '</p>' . $short_code;
            $content = implode('</p>', $paragraphs);
        }

    } elseif ($place === 2) {
        $content .= $short_code;
    }

    return $content;
}

function dt_star_google_snipet(){
    $google_snippet = get_option('dt_star_rating_setting');
    $valid_types = $google_snippet["types"];
    ?>
    <div class="container">
        <div class="row">
            <div class="col-lg-12">
    <div class="card posts_card setting_card">
        <div class="row">
            <div class="col">
        <h3>Google Snippet</h3>
            </div>
            <div class="col ">
                <div class="col_buttoon_activate">
             <?php if(!$google_snippet) {?>
            <button class="add_post_setting" id="activate_google_snippet">Activate Google Snippet</button>
            <?php }?>
                    <?php if($google_snippet && $google_snippet["dt_google"] == false) {?>
            <button class="add_post_setting" id="activate_google_snippet">Activate Google Snippet</button>
            <?php } elseif ($google_snippet && $google_snippet["dt_google"] == true){?>
            <button class="add_post_setting" id="disactivate_google_snippet">Disactivate Google Snippet</button>
            <?php } ?>
                </div>
            </div>
        </div>
 
        <div class="row mt-5 type_checkbox <?php if ($google_snippet && $google_snippet["dt_google"] == true) { echo "ds_block" ;}?>">
            <div class="col-lg-12">
                <h5>Choose the type of google snippet you want</h5>
            </div>
            <div class="col-lg-12">
            <div class="form-check">
            <input class="form-check-input" type="checkbox" value="SoftwareApplication" name="google_snippet_type[]" id="rating-types" <?php if($google_snippet && in_array("SoftwareApplication", $valid_types)){ echo "checked" ;}?>>
            <label class="form-check-label" for="rating-types">
               SoftwareApplication
            </label>
            </div>
           <div class="form-check">
            <input class="form-check-input" type="checkbox" value="WebApplication"  name="google_snippet_type[]" id="rating-types"  <?php if($google_snippet && in_array("WebApplication", $valid_types)){ echo "checked" ;}?> >
            <label class="form-check-label" for="rating-types">
              WebApplication
            </label>
            </div>
                     <div class="form-check">
            <input class="form-check-input" type="checkbox" value="MobileApplication"  name="google_snippet_type[]" id="rating-types"  <?php if($google_snippet && in_array("MobileApplication", $valid_types)){ echo "checked" ;}?>>
            <label class="form-check-label" for="rating-types">
               MobileApplication
            </label>
            </div>
            </div>

                   <div class="col-lg-12">
            <button class="add_post_setting" id="save_type_google_snippet">Save Types of google snippet</button>

           </div>
        </div>
    

    </div>
    <div id="addpostModal"  aria-labelledby="addpostModalLabel" aria-hidden="true" class="modal fade" tabindex="-1">

</div>
    <?php
}
function dt_star_rating_create_default_settings() {

    $dt_google = true;
    
    if(isset($_POST['dt_google']) && $_POST['dt_google'] == 'true'){
        $dt_google = true;
    }else{
         $dt_google = false;
    }
     $valid_types = ['SoftwareApplication', 'WebApplication', 'MobileApplication'];
    $types = $_POST['types'];
    $types = array_filter($types, fn($t) => in_array($t, $valid_types));
     $data = '';
    if (false === get_option('dt_star_rating_setting')) {
        $default_settings = [
                'dt_google' => $dt_google,
                'types' => $types
        ];
        add_option('dt_star_rating_setting', $default_settings);
        $data = [
            "msg" => "Activate Sucssefully",
            "status" => 200
        ];
    }else{
        
        update_option('dt_star_rating_setting', [
                'dt_google' => $dt_google,
                'types' => $types
            ]);
           $data = [
            "msg" => "Activate Sucssefully",
            "status" => 200
        ];
            
    }
             $data = [
            "msg" => "We have Error",
            "status" => 500
        ];
    wp_send_json_success($date);

}
add_action('wp_ajax_dt_star_rating_create_default_settings','dt_star_rating_create_default_settings');

function update_post_ratings_table_structure() {
 global $wpdb;
    $table = $wpdb->prefix . 'post_ratings';

    // Check first to avoid “duplicate key” errors
    $has_index = $wpdb->get_var( "
        SHOW INDEX FROM {$table}
        WHERE Key_name = 'post_parent_id'
    " );

    if ( ! $has_index ) {
        $wpdb->query( "
            ALTER TABLE {$table}
            ADD INDEX post_parent_id (post_parent_id)
        " );
    }
}
function get_current_url() {
    $protocol = is_ssl() ? 'https://' : 'http://';
    $host     = $_SERVER['HTTP_HOST'];
    $request  = $_SERVER['REQUEST_URI'];

    return $protocol . $host . $request;
}