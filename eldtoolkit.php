<?php

/*
  Plugin Name: Support EspritsLibres.design
  Plugin URI: http://EspritsLibres.design
  Description: Fonctionnalités de support et personnalisation
  Version: 1.1.10
  Author: Charles St-Pierre, en collaboration avec Esprits Libres Design
  Author URI: http://EspritsLibres.design
  Text Domain: eld
  Domain Path: /lang

  v.1.2.0
  Reconstruction des widgets
  Debug accomode plusieurs developpeurs en se basant sur des adresses IP
  Ajout de Date et Time format au wpml-config.xml
  Force WPML à ne pas scanner le code pour extraire les éléments textes

  Changelog
  v.1.1.10
  Activation fiable d’un mode maintenance 
  
  v.1.1.9
  Sécurité: Ajout de l’ajout automatique à la liste blanche.
  
  v.1.1.8
  Retrait des éditeurs de code, paramètrable
  Refonte code de securite.php
  Nouveau système de mise à jour

  v.1.1.7
  Correction de compatibilité avec Relevanssi
  Ajout de Related Posts (query et widget)
  Désactivation des Emojis
  Amélioration de compatibilité Messages Système
  Mise à jour compatibilité avec Google XML Sitemap

  v.1.1.6
  Widget Articles récents, ajout de filtre de pattern
  Amélioration de la sécurité

  v.1.1.5
  Ré-écriture de la config
  Securité: Ajout d’un paramètre pour appliquer le blocage des IPs
  Compresse et archive les logs

  v1.1.4
  Ajout TinyMCE Class clear
  Interface des Meta Descriptions pour les archives de contenus, l’accueil et le blogue.


  v1.1.3
  Ajout du support des descriptions pour les Pages

  v1.1.2
  Augmentation de la taille de l’image pour le tag OG:IMAGE

  v1.1.1
  Amélioration de TinyMCE

  v1.1.0
  Ajout du fil RSS des Communiqués
  Traductions anglaises complétés
  Correction de Bug htaccess
  Amélioration de l’interface de Sécurité
  Vérification de l’adresse IP sur le tableau de bord

  v1.0.9
  Correction Core
  Ajout du format woff2 au HTaccess
  Correction de Sécurité

  v1.0.8   Amélioration better widget
  Correction de WelcomeEmail qui interceptait tous les From

  v1.0.7   Retour de Welcome Email, maintenant Messages système
  Ajout WPML Config.xml pour gérer les traductions
  Uniformisation linguistique (fr_CA)

  v1.0.6   Correction de coquille

  v1.0.5   Ajout de l’élément de menu Archive de type de post
  Corrections de Notice PHP
  Amélioration de la gestion des configurations

  v1.0.4   Ajout de l’élément de menu Formulaire de recherche

  v1.0.3   Retrait de Welcome Email, code incompatible avec nouvelles versions de WP

  v1.0.2   Interface Sécurité, meilleur gestion des attaques

  v1.0.1   Corrections fonctionnalités de sécurité

  v1.0.0   Base. Ajout de gestion de courriel de bienvenue

 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

define('TOOLKIT_VERSION', '1.1.9');
define('TOOLKIT_URL', plugin_dir_url(__FILE__));
define('TOOLKIT_CONFIG', WP_CONTENT_DIR . '/eld-config.php');

function eld_include_files() {
    if (!file_exists(TOOLKIT_CONFIG)) {
        file_put_contents(TOOLKIT_CONFIG, eld_write_opening_config());
    }
    require_once TOOLKIT_CONFIG;

    require_once 'includes/security.php';

    require_once 'includes/debug.php';
    require_once 'includes/meta_descriptions.php';
    require_once 'includes/theme_helper.class.php';

    require_once 'includes/tinymce.php';
    require_once 'includes/system-messages.php';
    require_once 'includes/dashboardwidget.php';
    require_once 'includes/emailshield.php';
    require_once 'includes/upload-processor.php';

    require_once 'includes/menu-items.php';
    require_once 'includes/wpml-compatibility.php';

    require_once 'includes/related-posts.php';

    if (defined('ELD_DO_SOCIALMETAS') && ELD_DO_SOCIALMETAS) {
        require_once 'includes/socialmetas.php';
    }
    if (defined('ELD_DO_GEOTAGGING') && ELD_DO_GEOTAGGING) {
        require_once 'includes/geotagging.php';
    }
    if (defined('ELD_DO_WIDGETS') && ELD_DO_WIDGETS) {
        require_once 'includes/better-widgets.php';
    }
    if (defined('ELD_DISABLE_EMOJIS') && ELD_DISABLE_EMOJIS) {
        require_once 'includes/disable-emojis.php';
    }
}

eld_include_files();

/**
 * ELD Plugin Init
 * 
 * On plugins_loaded, initiate the plugin
 * 
 * - loads text domain
 * 
 * @see plugins_loaded
 */
function eldplugin_init() {

    // loading text domain
    load_plugin_textdomain('eld', false, dirname(plugin_basename(__FILE__)) . '/lang/');

    //activating auto update from github
    if (is_admin()) {
        require 'includes/plugin-update-checker/plugin-update-checker.php';
        $myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
                        'https://github.com/espritslibresdesign/eldtoolkit/', __FILE__, 'eldtoolkit'
        );
        
        
    }
}

add_action('plugins_loaded', 'eldplugin_init', 2);

/**
 * Trigger plugins activation functions
 * 
 * Calls the different functions needed on activation
 * 
 * @see function eld_update_user_database() in includes/security.php
 */
function eld_activation() {
    eld_write_options_to_config();
    require_once 'includes/security.php';
    eld_update_user_database();
    eld_setup_security_htaccess();
    eld_delete_insecure_files();
}

/**
 * Write options to configuration file
 * 
 * @since 1.0.0
 * @update 1.1.5 Better writing of the config
 * @update 1.1.8 Add OPTION DISALLOW_FILE_EDIT
 */
function eld_write_options_to_config() {

    $lines = array();
    //$lines[] = '#comment';
    //$lines[] = 'define("CONSTANT",value);';

    $lines[] = '# Security';

    $lines[] = 'if (!defined(\'DISALLOW_FILE_EDIT\')):';
    $lines[] = 'define("DISALLOW_FILE_EDIT",' . (defined('DISALLOW_FILE_EDIT') ? bool2string(DISALLOW_FILE_EDIT) : 'true') . ');';
    $lines[] = 'endif;';
    $lines[] = 'define("ELD_DO_SECURITY",' . (defined('ELD_DO_SECURITY') ? bool2string(ELD_DO_SECURITY) : 'true') . ');';
    $lines[] = 'define("ELD_SECURITY_MAX_404",' . (defined('ELD_SECURITY_MAX_404') ? ELD_SECURITY_MAX_404 : '50') . ');';
    $lines[] = 'define("ELD_SECURITY_MAX_BLACKLIST",' . (defined('ELD_SECURITY_MAX_BLACKLIST') ? ELD_SECURITY_MAX_BLACKLIST : '50') . ');';
    $lines[] = 'define("ELD_SECURITY_LOGIN_TO_WHITELIST",' . (defined('ELD_SECURITY_LOGIN_TO_WHITELIST') ? ELD_SECURITY_LOGIN_TO_WHITELIST : '10') . ');';

    $lines[] = '# pipe separated list of parked domains';
    $lines[] = 'define("ELD_SITE_DOMAINS","' . (defined('ELD_SITE_DOMAINS') ? ELD_SITE_DOMAINS : $_SERVER['SERVER_NAME']) . '" );';

    $lines[] = 'define("ELD_DO_RSS",' . (defined('ELD_DO_RSS') ? bool2string(ELD_DO_RSS) : 'true') . ');';
    $lines[] = 'define("ELD_DO_WIDGETS",' . (defined('ELD_DO_WIDGETS') ? bool2string(ELD_DO_WIDGETS) : 'true') . ');';
    $lines[] = 'define("ELD_DO_GEOTAGGING",' . (defined('ELD_DO_GEOTAGGING') ? bool2string(ELD_DO_GEOTAGGING) : 'true') . ');';
    $lines[] = 'define("ELD_DO_SOCIALMETAS",' . (defined('ELD_DO_SOCIALMETAS') ? bool2string(ELD_DO_SOCIALMETAS) : 'true') . ');';
    $lines[] = 'define("ELD_DISABLE_EMOJIS",' . (defined('ELD_DISABLE_EMOJIS') ? bool2string(ELD_DISABLE_EMOJIS) : 'true') . ');';

    $lines[] = '# Developper’s email: Used to confirm identity of developper.';
    $lines[] = 'define("DEVELOPPER_EMAIL","' . (defined('DEVELOPPER_EMAIL') ? DEVELOPPER_EMAIL : 'parlez@charlesstpierre.com') . '");';

    $lines[] = '# Twitter account name: Define twitter account name for the site, or site owner';
    if (defined('ELD_twittername')) {
        $lines[] = 'define("ELD_twittername","' . ELD_twittername . '");';
    } else {
        $lines[] = '//define("ELD_twittername","@something");';
    }

    $lines[] = '# Google Site Verification: Code for Google Webmaster Tools';
    $lines[] = 'define("GOOGLE_SITE_VERIFICATION_CODE","' . (defined('GOOGLE_SITE_VERIFICATION_CODE') ? GOOGLE_SITE_VERIFICATION_CODE : 'WA3YXZIjfRgomaqTvXgvRBs0Q7OTwolSKU0pF2R8UH8') . '");';

    $lines[] = '# Microsoft ownership verification: Code for Bing Webmaster Tools';
    if (defined('MICROSOFT_OWNERSHIP_VERIFICATION_CODE')) {
        $lines[] = 'define("MICROSOFT_OWNERSHIP_VERIFICATION_CODE","' . MICROSOFT_OWNERSHIP_VERIFICATION_CODE . '");';
    } else {
        $lines[] = '//define("MICROSOFT_OWNERSHIP_VERIFICATION_CODE","code");';
    }

    $lines[] = '# Geocalisation: http://www.gps-coordinates.net for coordinates (2015-07)';
    $lines[] = 'define("ELD_MAIN_LAT","' . (defined('ELD_MAIN_LAT') ? ELD_MAIN_LAT : '45.5') . '");';
    $lines[] = 'define("ELD_MAIN_LONG","' . (defined('ELD_MAIN_LONG') ? ELD_MAIN_LONG : '-73.6') . '");';
    $lines[] = 'define("ELD_MAIN_PLACENAME","' . (defined('ELD_MAIN_PLACENAME') ? ELD_MAIN_PLACENAME : 'Montréal, Québec, Canada') . '");';
    $lines[] = 'define("ELD_MAIN_REGION","' . (defined('ELD_MAIN_REGION') ? ELD_MAIN_REGION : 'ca-qc') . '");';

    $lines[] = '# Pre-upload image processing: maximum size and quality';
    $lines[] = 'define("ELD_IMAGE_MAX_WIDTH",' . (defined('ELD_IMAGE_MAX_WIDTH') ? ELD_IMAGE_MAX_WIDTH : '3840') . ');';
    $lines[] = 'define("ELD_IMAGE_MAX_HEIGHT",' . (defined('ELD_IMAGE_MAX_HEIGHT') ? ELD_IMAGE_MAX_HEIGHT : '2160') . ');';
    $lines[] = 'define("ELD_IMAGE_QUALITY",' . (defined('ELD_IMAGE_QUALITY') ? ELD_IMAGE_QUALITY : '90') . ');';

    insert_with_markers(TOOLKIT_CONFIG, 'ConfigurationELDToolkit', $lines);
}

function eld_write_opening_config() {
    $opening = "<?php\n\n// Exit if accessed directly\nif (!defined('ABSPATH')) { exit; }\n\n";
    return $opening;
}

register_activation_hook(__FILE__, 'eld_activation');

/**
 * ELD Update Config on Update
 */
function eld_update_config() {
    if (1 === version_compare(TOOLKIT_VERSION, get_option('eld_toolkit_version'))) {
        eld_write_options_to_config();
        update_option('eld_toolkit_version', TOOLKIT_VERSION, true);
    }
}

add_action('upgrader_process_complete', 'eld_update_config');

function bool2string($val) {
    return var_export((bool) $val, true);
}
