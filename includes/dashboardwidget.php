<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add a widget to the dashboard.
 *
 * This function is hooked into the 'wp_dashboard_setup' action below.
 */
function eld_add_dashboard_widgets() {

    wp_add_dashboard_widget(
            'eld_support_dashboard_widget', // Widget slug.
            __('Support EspritsLibres.design', 'eld'), // Title.
            'eld_support_dashboard_widget_function' // Display function.
    );
}

add_action('wp_dashboard_setup', 'eld_add_dashboard_widgets');

/**
 * Create the function to output the contents of our Dashboard Widget.
 */
function eld_support_dashboard_widget_function() {
    
    // IP WHITELIST
    $current_ip = $_SERVER['REMOTE_ADDR'];
    
    if ( current_user_can('manage_options') && false === get_option('_whitelist'.eld_get_ip($current_ip)) ){
        ?>
        <div style="padding-left:2em;position:relative;"><span class="dashicons dashicons-warning" style="position:absolute;left:0;color:red;font-size:1.5em;"></span>
        <p>
            <b><?php printf( __('Votre IP actuel (%s) n’est pas sur la liste blanche.','eld'),$current_ip) ?></b>
            <?php printf( __('Si cette ordinateur n’est pas public, et que vous l’utilisez fréquemment, <%s>ajoutez votre IP à la liste blanche<%s>.','eld'),'a href="'.admin_url('options-general.php?page=eld-security').'"','/a'); ?>
        </p>
        </div>
        <?php
    }
    
    
    // ELD RSS FEED
    $rss = fetch_feed('https://espritslibres.design/feed/');

    if (is_wp_error($rss) && is_admin()) {
        printf('<p class="error">' . __('<strong>Erreur RSS</strong>: %s', 'eld') . '</p>', $rss->get_error_message());
    } elseif (0 !== $rss->get_item_quantity()) {

        echo '<p><strong>' . __('Communiqués:', 'eld') . '</strong></p>';
        echo '<ul>';

        foreach ($rss->get_items(0, 5) as $item) {
            $publisher = '';
            $site_link = '';
            $link = '';
            $content = '';
            $date = '';
            $link = esc_url(strip_tags($item->get_link()));
            $title = esc_html($item->get_title());
            $content = $item->get_content();
            $excerpt = wp_html_excerpt($content, 250) . '… ';

            echo "<li><a class=\"rsswidget\" href=\"$link\" target=\"_blank\">$title</a>\n<div class=\"rssSummary\">$excerpt</div>\n";
        }

        echo '<ul>';
    }
    
    // GENERIC LINKS
    ?>
    <p><?php _e('Besoin d’un coup de main? Nous sommes toujours à votre disposition.', 'eld'); ?></p>
    <ul>
        <li><a href="https://espritslibres.teamwork.com" target="_blank"><?php _e('TeamWork', 'eld') ?></a></li>
        <li><a href="https://espritslibres.design/nous-joindre/" target="_blank"><?php _e('Demande de support', 'eld') ?></a></li>
    </ul>
    <?php
}
