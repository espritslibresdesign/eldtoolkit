<?php
// Exit if accessed directly
if (!defined('ABSPATH')) { exit; }

/**
 * Outputs Social metatags
 * 
 * Writes metatags for Facebook opengraph and Twitter card, as well as the site icons for iOS and Microsoft
 * 
 * @global object $post
 */
function socialMetas() {
    global $post;
    
    /**
     * Icons folder
     * 
     * Define the $icons_folder.
     * 
     * @since 1.0.0
     * 
     * @param string $icons_folder The absolute path to the icons folder.
     */
    $icons_subfolder = apply_filters('eld_socialmetas_icons_folder','/icons');
 
    if (!file_exists( WP_CONTENT_DIR.$icons_subfolder ))
        return;
    
    $icons_folder = WP_CONTENT_URL.$icons_subfolder;

    $og_site_name = get_bloginfo('name');
    $og_description = get_meta_description();
    
    /**
     * Default image for social media sharing (OpenGraph)
     * 
     * Define the default image to share on social media. Default to the site Icon.
     * 
     * @param string $og_image The absolute path to the image file.
     */
    $og_image = apply_filters('eld_socialmetas_og_image_default',$icons_folder.'/mstile-310x310.png');
    $og_title = get_bloginfo('name');
    $og_url = home_url();
    
    if (is_single() || is_page()) {
        $og_title = get_the_title();
        $og_url = get_permalink();
    //image
        if (has_post_thumbnail($post->ID)) {
            $image_src = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'full');
            $og_image = $image_src[0];
        }
    }elseif(false){ // add more cases
        
    }

    //Twitter
    if (defined('ELD_twittername')) :
        $twittername = ELD_twittername;
        $twitterdescription = twitter_description_trim($og_description);

echo <<<EOT
    <!-- Twitter card -->
        <meta name="twitter:card" content="summary" />
        <meta name="twitter:site" content="$twittername" />
        <meta name="twitter:title" content="$og_title" />
        <meta name="twitter:description" content="$twitterdescription" />
        <meta name="twitter:image" content="$og_image" />
EOT;
    endif;
    



echo <<<EOT
    <!-- Open Graph -->
        <meta property="og:site_name" content="$og_site_name"/>
        <meta property="og:title" content="$og_title"/>
        <meta property="og:description" content="$og_description"/>
        <meta property="og:url" content="$og_url"/>
        <meta property="og:image" content="$og_image"/>
    <!--icons-->
        <link rel="apple-touch-icon-precomposed" sizes="57x57" href="$icons_folder/apple-touch-icon-57x57.png" />
        <link rel="apple-touch-icon-precomposed" sizes="114x114" href="$icons_folder/apple-touch-icon-114x114.png" />
        <link rel="apple-touch-icon-precomposed" sizes="72x72" href="$icons_folder/apple-touch-icon-72x72.png" />
        <link rel="apple-touch-icon-precomposed" sizes="144x144" href="$icons_folder/apple-touch-icon-144x144.png" />
        <link rel="apple-touch-icon-precomposed" sizes="60x60" href="$icons_folder/apple-touch-icon-60x60.png" />
        <link rel="apple-touch-icon-precomposed" sizes="120x120" href="$icons_folder/apple-touch-icon-120x120.png" />
        <link rel="apple-touch-icon-precomposed" sizes="76x76" href="$icons_folder/apple-touch-icon-76x76.png" />
        <link rel="apple-touch-icon-precomposed" sizes="152x152" href="$icons_folder/apple-touch-icon-152x152.png" />
        <link rel="icon" type="image/png" href="$icons_folder/favicon-196x196.png" sizes="196x196" />
        <link rel="icon" type="image/png" href="$icons_folder/favicon-96x96.png" sizes="96x96" />
        <link rel="icon" type="image/png" href="$icons_folder/favicon-32x32.png" sizes="32x32" />
        <link rel="icon" type="image/png" href="$icons_folder/favicon-16x16.png" sizes="16x16" />
        <link rel="icon" type="image/png" href="$icons_folder/favicon-128.png" sizes="128x128" />
        <meta name="application-name" content="$og_site_name"/>
        <meta name="msapplication-TileColor" content="#FFFFFF" />
        <meta name="msapplication-TileImage" content="$icons_folder/mstile-144x144.png" />
        <meta name="msapplication-square70x70logo" content="$icons_folder/mstile-70x70.png" />
        <meta name="msapplication-square150x150logo" content="$icons_folder/mstile-150x150.png" />
        <meta name="msapplication-wide310x150logo" content="$icons_folder/mstile-310x150.png" />
        <meta name="msapplication-square310x310logo" content="$icons_folder/mstile-310x310.png" />
EOT;
}
add_action('wp_head', 'socialMetas', 20);

/**
 * Twitter description trim
 * 
 * Trims down the excerpt to 200 characters, which is the current limit for Twitter card description.
 * 
 * @since 1.0.0
 * @param string $str The excerpt to trim.
 * @return string
 */
function twitter_description_trim($str) {
    $nbchar = 200;
    $str = wp_strip_all_tags($str);
    $trim_str = mb_substr($str, 0, $nbchar);
    if ($trim_str !== $str) {
        $trim_str = mb_substr($trim_str, 0, strrpos($trim_str, ' '));
    }
    return $trim_str;
}
