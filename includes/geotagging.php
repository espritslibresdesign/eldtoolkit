<?php
// Exit if accessed directly
if (!defined('ABSPATH')) { exit; }

/**
 * Outputs geotagging meta
 * 
 * Writes metatags for GEOTAGGING in the head based on configuration from wp-config.php.
 * 
 * @uses float ELD_MAIN_LAT Lattitude
 * @uses float ELD_MAIN_LONG Longitude
 * @uses string ELD_MAIN_PLACENAME ('Montréal, Québec, Canada')
 * @uses string ELD_MAIN_REGION ('ca-qc')
 * 
 * @return boolean
 */
function output_geotagging() {
    if (
            !defined('ELD_MAIN_LAT') || !defined('ELD_MAIN_LONG') || !defined('ELD_MAIN_PLACENAME') || !defined('ELD_MAIN_REGION')
    ) {
        return false;
    }
    $region = apply_filters('geotagging_region', ELD_MAIN_REGION);
    $placename = apply_filters('geotagging_placename', ELD_MAIN_PLACENAME);
    $lat = apply_filters('geotagging_lat', ELD_MAIN_LAT);
    $long = apply_filters('geotagging_long', ELD_MAIN_LONG);
    ?>
    <meta name="geo.region" content="<?php echo $region ?>">
    <meta name="geo.placename" content="<?php echo $placename ?>">
    <meta name="geo.position" content="<?php echo $lat . '; ' . $long; ?>">
    <meta name="ICBM" content="<?php echo $lat . ',' . $long; ?>">
    <?php
    return true;
}
add_action('wp_head', 'output_geotagging');
