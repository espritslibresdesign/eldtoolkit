<?php
/**
 * Security.php
 * 
 * Functions and tasks to make WP more secure
 */
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Basic security actions and filters
 * 
 * Use basic actions and filters to change WP innerworking to make it more secure.
 * 
 * - Remove metatag generator to hide that the site is ran by WordPress
 * 
 * @since 1.0.0
 */
function eld_security() {
    if (!ELD_DO_RSS) {
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
    }
    remove_action('wp_head', 'rsd_link');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'index_rel_link');
    remove_action('wp_head', 'parent_post_rel_link', 10, 0);
    remove_action('wp_head', 'start_post_rel_link', 10, 0);
    remove_action('wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0);
    remove_action('wp_head', 'wp_shortlink_wp_head', 10, 0);
    remove_action('wp_head', 'wp_generator', 10);
    if (isset($GLOBALS['sitepress'])) {
        remove_action('wp_head', array($GLOBALS['sitepress'], 'meta_generator_tag'), 10);
    }
}

add_action('plugins_loaded', 'eld_security');

/**
 * Update User_ID on activate
 * 
 * On plugin activation, changes USER_IDs to high values
 * 
 * @since 1.0.0
 * @global object $wpdb
 * @see eld_activation() in eldplugin.php:34
 */
function eld_update_user_database() {
    global $wpdb;

    // if user id 1 doesn't exist, its probably been done before.
    $count = $wpdb->get_var("SELECT COUNT(id) FROM $wpdb->users WHERE id=1;");
    if ($count == 1) { // id 1 exists
        // choose random id for admin account (id=1)
        $admin_id = rand(1000, 2000);
        // update tables to increase 

        $last_autoincrement = $wpdb->get_var("SELECT `auto_increment` FROM INFORMATION_SCHEMA.TABLES WHERE table_name = '$wpdb->users' ");

        // choose random auto increment
        $step = rand(2000, 4000);
        $new_autoincrement = $step + $last_autoincrement;
        $zero_query = $wpdb->query("ALTER TABLE $wpdb->users AUTO_INCREMENT = $new_autoincrement;");

        // increase all user ids by $new_autoincrement;
        $first_query = $wpdb->query("UPDATE $wpdb->users AS u "
                . "LEFT JOIN $wpdb->usermeta AS m ON u.ID=m.user_id "
                . "LEFT JOIN $wpdb->posts AS p ON u.ID=p.post_author "
                . "LEFT JOIN $wpdb->comments AS c ON u.ID = c.user_id "
                . "SET u.ID = u.ID + $step, "
                . "m.user_id = m.user_id + $step, "
                . "p.post_author = p.post_author + $step, "
                . "c.user_id = c.user_id + $step "
                . "WHERE u.ID > 1");

        $second_query = $wpdb->query("UPDATE $wpdb->users AS u "
                . "LEFT JOIN $wpdb->usermeta AS m ON u.ID=m.user_id "
                . "LEFT JOIN $wpdb->posts AS p ON u.ID=p.post_author "
                . "LEFT JOIN $wpdb->comments AS c ON u.ID = c.user_id "
                . "SET u.ID = $admin_id, "
                . "m.user_id = $admin_id, "
                . "p.post_author = $admin_id, "
                . "c.user_id = $admin_id "
                . "WHERE u.ID = 1;");
    }
}

/**
 * Delete insecure files on activate
 * 
 * @since 1.0.0
 * @see function eld_activation()
 */
function eld_delete_insecure_files() {

    $files = array(
        ABSPATH . 'license.txt',
        ABSPATH . 'readme.html',
        ABSPATH . 'wp-admin/install.php',
        ABSPATH . 'wp-config-sample.php'
    );
    foreach ($files as $file) {
        if (file_exists($file)) {
            unlink($file);
        }
    }
}

/**
 * Write security to htaccess files
 * 
 * @see function eld_activation()
 */
function eld_setup_security_htaccess() {

    $htaccess = get_home_path() . '.htaccess';

    $lines = array();
    //disallow directory browsing
    $lines[] = '# directory browsing';
    $lines[] = 'Options -Indexes';
    // protect wp-config.php
    $lines[] = '<Files "wp-config.php">';
    $lines[] = 'order allow,deny';
    $lines[] = 'deny from all';
    $lines[] = '</Files>';
    // protect all .hta(ccess) files
    $lines[] = '<Files ~ "^.*\.([Hh][Tt][Aa])">';
    $lines[] = 'order allow,deny';
    $lines[] = 'deny from all';
    $lines[] = 'satisfy all';
    $lines[] = '</Files>';
    // prevent hot linking
    $lines[] = '# Prevent image hotlinking script. Replace last URL with any image link you want.';
    $lines[] = 'RewriteEngine on';
    $lines[] = 'RewriteCond %{HTTP_REFERER} !^$';

    // get all domain
    if (defined('ELD_SITE_DOMAINS')) {
        $domains = explode('|', ELD_SITE_DOMAINS);
    } else {
        $domains = array($_SERVER['SERVER_NAME']);
    }

    foreach ($domains as $domain) {
        $lines[] = 'RewriteCond %{HTTP_REFERER} !^http(s)?://(www\.)?' . $domain . ' [NC]';
    }
    $lines[] = 'RewriteRule \.(jpg|jpeg|png|gif)$ - [NC,F,L]';
    //$lines[]  = '';

    insert_with_markers($htaccess, 'ELD_SECURITY', $lines);

    // WP-content
    $htaccess = WP_CONTENT_DIR . '/.htaccess';

    $lines = array();
    $lines[] = 'Order deny,allow';
    $lines[] = 'Deny from all';
    // allowed file types
    $file_types = eld_allowed_mime_types();

    $lines[] = '<Files ~ ".(' . implode('|', $file_types) . ')$">';
    $lines[] = 'Allow from all';
    $lines[] = '</Files>';
    //$lines[] = '';
    //$lines[] = '';

    insert_with_markers($htaccess, 'ELD_SECURITY', $lines);


    // WP-includes
    $htaccess = get_home_path() . WPINC . '/.htaccess';

    $lines = array();
    $lines[] = '# Block wp-includes folder and files';
    $lines[] = '<IfModule mod_rewrite.c>';
    $lines[] = 'RewriteEngine On';
    $lines[] = 'RewriteBase /';
    $lines[] = 'RewriteRule ^wp-admin/includes/ - [F,L]';
    $lines[] = 'RewriteRule !^wp-includes/ - [S=3]';
    $lines[] = 'RewriteRule ^wp-includes/[^/]+\.php$ - [F,L]';
    $lines[] = 'RewriteRule ^wp-includes/js/tinymce/langs/.+\.php - [F,L]';
    $lines[] = 'RewriteRule ^wp-includes/theme-compat/ - [F,L]';
    $lines[] = '</IfModule>';
    //$lines[] = '';
    //$lines[] = '';

    insert_with_markers($htaccess, 'ELD_SECURITY', $lines);


    // the END!
}

function eld_allowed_mime_types() {
    // allowed file types
    $allowed_mime_types = array_keys(get_allowed_mime_types());
    $fonts_types = array('svg', 'woff', 'woff2', 'ttf', 'eot');

    /**
     * .map sass complement information
     * .xsl XML stylesheet
     */
    $other_types = array('map', 'xsl');

    $file_types = array_merge($allowed_mime_types, $fonts_types, $other_types);
    return $file_types;
}

/**
 * Check user nicenames
 * 
 * In admin pages, checks the users database for nicename identical to username
 * and warn the administrator.
 * 
 * @since 1.0.0
 * @global object $wpdb
 * @uses filter admin_notices
 */
function eld_check_nicenames() {
    global $wpdb;

    if (!current_user_can('edit_users'))
        return;

    $search_query = "SELECT ID FROM $wpdb->users WHERE user_nicename = user_login";
    $search_results = $wpdb->get_col($search_query);
    $nb_user = count($search_results);
    if ($nb_user) {
        if ($nb_user === 1) {
            $correction_url = admin_url('user-edit.php?user_id=' . $search_results[0]);
        } else {
            $correction_url = admin_url('users.php');
        }
        $str = __('<b>Risque de sécurité:</b>', 'eld') . ' ';
        $str.= sprintf(_n('un utilisateur possède un pseudonyme identique à son identifiant.', '%1$s utilisateurs possèdent des pseudonymes identiques à leur identifiants.', $nb_user, 'eld'), $nb_user) . ' ';

        $str.= '<a href="' . $correction_url . '">' . __('Corriger la situation immédiatement.') . '</a>';

        echo '<div class="error"><p>' . $str . ' </p></div>';
    }
}

add_action('admin_notices', 'eld_check_nicenames');

/**
 * Update user nicename
 * 
 * Create a unique nicename for user, different form user_login.
 * 
 * @since 1.0.0
 * @global object $wpdb
 * @param int $user_id
 * @return void
 * @uses filter profile_update (saving profile)
 * @uses filter user_register (saving new profile)
 */
function eld_update_nicename_from_nickname($user_id) {
    global $wpdb;
    $user = get_user_by('id', $user_id);

    if (strtolower($user->user_nicename) !== strtolower($user->user_login)) {
        // nicename is different
        return;
    } elseif ($user->nickname !== $user->user_login) {
        $new_nicename = sanitize_title($user->nickname);
    } elseif ($user->display_name !== $user->user_login) {
        $new_nicename = sanitize_title($user->display_name);
    } else {
        $new_nicename = 'user' . time();
    }
    if ($new_nicename) {
        $wpdb->query($wpdb->prepare(
                        "UPDATE $wpdb->users "
                        . "SET user_nicename='%s' "
                        . "WHERE ID=%d;", array(
                    $new_nicename,
                    $user_id
                        )
        ));
    }
}

add_action('profile_update', 'eld_update_nicename_from_nickname', 10, 2);
add_action('user_register', 'eld_update_nicename_from_nickname', 10, 2);

/**
 * INTERFACE    
 */

/**
 * Add security interface
 * 
 * Add the security inteface to Settings
 * 
 * @since 1.0.2
 */
function eld_add_security_interface() {
    add_options_page(__('Sécurité', 'eld'), __('Sécurité', 'eld'), 'manage_options', 'eld-security', 'eld_output_security_interface');
}

add_action('admin_menu', 'eld_add_security_interface');

/**
 * Enqueue admin security CSS and JS
 */
function eld_security_enqueue_head($hook) {
    if ($hook == 'settings_page_eld-security') {
        wp_enqueue_script('eld-security', TOOLKIT_URL . 'js/admin-security.js', array('jquery'), '1.0', true);
        wp_enqueue_style('eld-security', TOOLKIT_URL . 'css/admin-security.css');
    }
}

add_action('admin_enqueue_scripts', 'eld_security_enqueue_head');

/**
 * 
 */
function eld_search_ip_blacklist() {

    $response = array();

    $ip = filter_input(INPUT_POST,'ip', FILTER_VALIDATE_IP);
    if (empty($ip)) {
        $response['code'] = 'invalid';
        $response['message'] = __('L’IP est invalide.', 'eld');
    } else {
        $blacklist = get_option('_blacklist' . eld_get_ip($ip));
        if (false === $blacklist) {
            $response['code'] = 'missing';
            $response['message'] = __('L’IP n’est pas sur la liste noir.', 'eld');
        } else {
            $response['code'] = 'found';
            $response['message'] = __('L’IP est présent sur la liste noir.', 'eld');
        }
    }
    $return = json_encode($response);

    wp_die($return);
}

add_action('wp_ajax_search_ip_blacklist', 'eld_search_ip_blacklist');

/**
 * Output Security interface
 * 
 * @since 1.0.2
 * @global object $wpdb
 */
function eld_output_security_interface() {

    // submitted form
    if ( wp_verify_nonce( filter_input(INPUT_POST,'_wpnonce'), 'eld-security-interface')) {
        
        $what = filter_input(INPUT_POST,'what');
        
        switch ($what) {
            case 'add_my_ip_whitelist':
                if ( false !== ( $new_whitelist = filter_input(INPUT_POST, 'my_ip', FILTER_VALIDATE_IP))) {
                    eld_add_to_whitelist($new_whitelist);
                }
                break;
            case 'add_ip_whitelist':
                if ( false !== ( $new_whitelist = filter_input(INPUT_POST, 'add_ip_whitelist', FILTER_VALIDATE_IP))) {
                    eld_add_to_whitelist($new_whitelist);
                }
                break;
            case 'add_ip_blacklist':
                if ( false !== ( $new_blacklist = filter_input(INPUT_POST, 'add_ip_blacklist', FILTER_VALIDATE_IP))) {
                    add_option('_blacklist' . eld_get_ip($new_blacklist), 1);
                    delete_option('_whitelist' . eld_get_ip($new_blacklist));
                }
                break;
            case 'delete_ip_blacklist':
                if ( false !== ( $the_ip = filter_input(INPUT_POST,'search_ip_blacklist', FILTER_VALIDATE_IP))) {
                    delete_option('_blacklist' . eld_get_ip($the_ip));
                    eld_gandalf_protocol_remove_ip($the_ip);
                }
                break;
            default:
                // we are removing something
                $the_list = filter_input( INPUT_POST, 'remove_list');
                $the_ip = filter_input( INPUT_POST, 'remove_ip', FILTER_VALIDATE_IP );
                
                if ( in_array( $the_list, array('black','white') ) && $the_list && $the_ip) {
                    delete_option('_' . $the_list . 'list' . eld_get_ip($the_ip));
                    eld_gandalf_protocol_remove_ip($the_ip);
                }

                break;
        }
    }


    global $wpdb;
    $whitelist = $wpdb->get_col("SELECT `option_value` FROM $wpdb->options WHERE `option_name` LIKE '_whitelist%';");
    $blacklist_count = $wpdb->get_var("SELECT count(*) FROM $wpdb->options WHERE `option_name` LIKE '_blacklist%';");
    ?>
    <div class="wrap">
        <h1 class="eld-security-title"><span class="dashicons dashicons-vault"></span><?php _e('Sécurité du site', 'eld') ?></h1>
        <form id="eld-security-form" action="options-general.php?page=eld-security" method="post">
            <?php wp_nonce_field('eld-security-interface') ?>
            <input type="hidden" id="remove_list" name="remove_list" value="" />
            <input type="hidden" id="remove_ip" name="remove_ip" value="" />
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><?php _e('Votre adresse IP', 'eld') ?></th>
                        <td>
                            <input type="text" value="<?php echo $_SERVER['REMOTE_ADDR'] ?>" readonly="readonly" name="my_ip" />
                            <button type="submit" name="what" value="add_my_ip_whitelist" class="button-primary"><?php _e('Ajouter à la liste blanche', 'eld'); ?></button>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Liste blanche', 'eld') ?></th>
                        <td>
                            <p><?php _e('Les adresses IP de la liste blanche ne subiront aucune vérification de sécurité.', 'eld') ?></p>
                            <p>
                                <input type="text" placeholder="###.###.###.###" name="add_ip_whitelist" />
                                <button type="submit" name="what" value="add_ip_whitelist" class="button-primary"><?php _e('Ajouter', 'eld'); ?></button>
                            </p>
                            <ul class="scroll-list">
                                <?php if (empty($whitelist)): ?>
                                    <li><?php _e('Aucune adresse IP dans la liste blanche.', 'eld') ?></li>
                                <?php endif; ?>
                                <?php foreach ($whitelist as $wip): ?>
                                    <li>
                                        <code class="security-ip-list-item"><?php echo $wip; ?></code>
                                        <a href="javascript:void(0);" data-list="white" data-ip="<?php echo $wip ?>" class="security-list-remove-item dashicons dashicons-trash"></a></li>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Liste noire', 'eld') ?></th>
                        <td>
                            <p><?php _e('Les adresses IP de la liste noire ne peuvent se connecter à l’admin et son passible d’un blocage complet à l’accès du site.', 'eld') ?></p>
                            <p><em><?php printf(__('Il y a présentement %s adresses IP sur la liste noir.', 'eld'), '<b>' . $blacklist_count . '</b>') ?></em></p>
                            <p>
                                <input type="text" placeholder="###.###.###.###" name="search_ip_blacklist" id="search_ip_blacklist" />
                                <button type="button" class="button-secondary" name="search_blacklist" value="search_blacklist" id="search_blacklist" ><?php _e('Chercher dans la liste noir', 'eld') ?></button>
                                <span id="search_blacklist_code" class="dashicons"></span><i id="search_blacklist_reponse"></i>
                                <button type="submit" class="button-primary hide-if-js" name="what" value="delete_ip_blacklist" id="delete_search_blacklist"><?php _e('Retirer de la liste noir', 'eld') ?></button>
                            </p>



                            <p>
                                <input type="text" placeholder="###.###.###.###" name="add_ip_blacklist" />
                                <button type="submit" name="what" value="add_ip_blacklist"  class="button-primary"><?php _e('Ajouter', 'eld'); ?></button>
                            </p>
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
    </div>
    <?php
}

/**
 * 
 * LOGIN LIMIT
 * 
 */

/**
 * Get IP
 * 
 * Simple function to return remote IP.
 * 
 * @since 1.0.0
 * @return string IP
 */
function eld_get_ip($ip = false) {
    if ($ip === false) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    $_ip = '_IP_' . str_replace('.', '_', $ip);
    return $_ip;
}

/**
 * Add IP to Whitelist
 * 
 * @since 1.0.2
 * @param IP Address $ip
 */
function eld_add_to_whitelist($ip = false) {
    if ($ip === false) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        $_ip = eld_get_ip($ip);
        add_option('_whitelist' . $_ip, $ip, false);
        
        //clear useless options
        delete_option('_successful_login' . $_ip);
        delete_option('_blacklist' . $_ip);
        delete_transient('four_oh_four' . $_ip);
        delete_transient('wait' . $_ip);
        delete_transient('attempt' . $_ip);
    
    }
}

/**
 * Remove IP from Whitelist
 *
 * @since 1.0.2
 * @param IP Address $ip
 */
function eld_remove_from_whitelist($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
        delete_option('_whitelist' . eld_get_ip($ip));
    }
}

/**
 * Is IP in whitelist
 * 
 * @param type $ip
 * @return boolean
 */
function eld_is_whitelist($ip = false) {
    $whitelist = get_option('_whitelist' . eld_get_ip($ip));
    if ($whitelist) {
        return true;
    }
    return false;
}

/**
 * Add filters and actions only if IP is not whitelisted
 */
if (ELD_DO_SECURITY && !eld_is_whitelist()) {
    add_filter('authenticate', 'eld_authenticate_limit_access', 30, 3);
    add_action('wp', 'eld_security_404', 100);
}

/**
 * 
 * Authenticate limit access
 * 
 * Run after WP official authentification procedure. 
 * If IP is blacklisted, blocs authentification whatever the result was.
 * Else, if authentification has failed, go through the attemps/wait/blacklist procedure.
 *  
 * @since 1.1.8
 * @param WP_User|WP_Error|null $user     WP_User or WP_Error object from a previous callback. Default null.
 * @param string                $username Username for authentication.
 * @param string                $password Password for authentication.
 * @return WP_User|WP_Error WP_User on success, WP_Error on failure.
 */
function eld_authenticate_limit_access($user, $username, $password) {

    // if not trying to login pass through
    if (empty($username) && empty($password)) {
        return $user;
    }

    $_ip = eld_get_ip();

    // blacklist
    $blacklist = get_option('_blacklist' . $_ip);
    if ($blacklist) {

        $blacklist++;
        update_option('_blacklist' . $_ip, $blacklist);

        if (ELD_SECURITY_MAX_BLACKLIST < $blacklist) {
            eld_gandalf_protocol_add_ip();
        }
        return new WP_Error('blacklisted', __('Vous êtes sur la liste noir. Contactez l’administrateur du site.', 'eld'));
    }

    // wait
    $wait_login_obj = get_transient('wait' . $_ip);

    $mss_bad_cred = __('Mauvais identifiant ou mot de passe.', 'eld');
    $mss_attemps = array(
        __('Il vous reste 4 chances sur 5 de vous connecter. ', 'eld'),
        __('Il vous reste 3 chances sur 5 de vous connecter. <a href="%1$s">Mot de passe oublié?</a>', 'eld'),
        __('Il ne vous reste que 2 chances sur 5 de vous connecter. Nous vous suggerons fortement d’utiliser la fonction <a href="%1$s">Mot de passe oublié?</a>.', 'eld'),
        __('Il ne vous reste qu’une seule chance de vous connecter. Vous devrez attendre 5 minutes avant de réessayer. <a href="%1$s">Réinitialiser votre mot de passe.</a>', 'eld'),
        __('Vous avez atteint la limite de tentative de connexion. Vous devez maintenant attendre 5 minutes avant de réessayer. <a href="%1$s">Réinitialiser votre mot de passe.</a>', 'eld')
    );
    $mss_delay = __('Vous deviez attendre jusqu’à %1$s avant de tenter une connexion. Vous devez maintenant attendre %2$s minutes (jusqu’à %3$s) avant de tenter une connexion.', 'eld');
    $mss_blacklist = __('Vous deviez attendre jusqu’à %s avant de tenter une connexion. Vous êtes maintenant sur la liste noir. Contactez l’administrateur du site.', 'eld');

    if ($wait_login_obj !== false) {

        // if it exists, it means that user should not have tried to log in, it was too soon.
        $time_to_login = $wait_login_obj['time'];

        switch ($wait_login_obj['delay']) {
            case 5:
                $new_delay = 15;
                break;
            case 15:
                $new_delay = 60;
                break;
            case 60:
                delete_transient('wait' . $_ip);
                // blacklist the son of a gun
                update_option('_blacklist' . $_ip, 1);
                eld_security_log('login', 'new_blacklist');
                return new WP_Error(
                        'blacklisted', '<strong>' . $mss_bad_cred . '</strong><br />'
                        . sprintf(
                                $mss_blacklist, date_i18n(_x('H\hi', 'Heure et minute', 'eld'), $time_to_login)
                        )
                );
        }
        $new_time_to_login = $time_to_login + ( $new_delay * MINUTE_IN_SECONDS );

        set_transient('wait' . $_ip, array('delay' => $new_delay, 'time' => $new_time_to_login), $new_delay * MINUTE_IN_SECONDS);
        eld_security_log('login', 'wait_' . $new_delay);
        return new WP_Error(
                'wait_' . $new_delay, '<strong>' . $mss_bad_cred . '</strong><br />'
                . sprintf(
                        $mss_delay, date_i18n(_x('H\hi', 'Heure et minute', 'eld'), $time_to_login), $new_delay, date_i18n(_x('H\hi', 'Heure et minute', 'eld'), $new_time_to_login)
                )
        );
    }

    // LOGIN SUCCESSFUL
    
    // if already a WP_User, signon was a success, passthrough
    if ($user instanceof WP_User) {
        // delete transients
        delete_transient('attempt' . $_ip);
        delete_transient('wait' . $_ip);
        
        // log in successful login to whitelist IP
        $hashed_username = md5($user->user_login);
        $successful_login = get_option('_successful_login' . $_ip, array());
        if ( !in_array( $hashed_username, $successful_login) ){
            $successful_login[] = $hashed_username;
            update_option('_successful_login' . $_ip, $successful_login);
        }
        
        if ( count($successful_login) >= ELD_SECURITY_LOGIN_TO_WHITELIST ){
            eld_add_to_whitelist();
        }
        
        return $user;
    }
    
    
    // LOGIN FAILED
    
    // the login failed for some reason, start the Attempts procedure
    $attempts = intval( get_transient('attempt' . $_ip) );
    
    if ($attempts === 4) {
        delete_transient('attempt' . $_ip);
        set_transient('wait' . $_ip, array('delay' => 5, 'time' => time() + ( 5 * MINUTE_IN_SECONDS )), 5 * MINUTE_IN_SECONDS);
    } else {
        set_transient('attempt' . $_ip, $attempts + 1, HOUR_IN_SECONDS);
    }

    eld_security_log('login', 'attempt ' . $attempts);
    return new WP_Error(
            'attempt', '<strong>' . $mss_bad_cred . '</strong><br />'
            . sprintf(
                    $mss_attemps[$attempts], wp_lostpassword_url()
            )
    );
}

/**
 * Add ELD error messages to login Shake
 * 
 * @since 1.1.8
 * @param array $shake_error_codes
 * @return array
 */
function eld_add_shake_error_codes($shake_error_codes) {
    $shake_error_codes = array_merge($shake_error_codes,array('wait_5','wait_15','wait_60','blacklisted','attempt'));
    return $shake_error_codes;
}
add_filter('shake_error_codes','eld_add_shake_error_codes');



/**
 * security 404
 * 
 * Checks and manages 404 errors for possible attacks
 * 
 * @global object $wpdb
 */
function eld_security_404() {

    if (is_main_query() && is_404()) {

        //not for media
        $uri = $_SERVER['REQUEST_URI'];
        $allowed_mime_types = implode('|',eld_allowed_mime_types());
        if ( preg_match('/^.*\.('.$allowed_mime_types.')$/i',$uri)) {
            wp_die( sprintf( __('Fichier «%s» manquant.','eld'), $uri), sprintf( __('Fichier «%s» manquant.','eld'), $uri), array('response'=>404));
        }

        // not for legitimate pages
        global $wpdb;
        // by id?
        if (preg_match('/(\?|&)p=(\d+)/', $uri, $matches)) {
            if (false !== get_post_status(intval($matches[2]))) {
                return;
            }
        }
        // by slug?
        if (preg_match('/\/([\w-%]+)\/$/', $uri, $matches)) {
            $post_name = esc_sql($matches[1]);
            $sql = "SELECT ID, post_name, post_status, post_type
                    FROM $wpdb->posts
                    WHERE post_name IN (%s)";

            $check = $wpdb->get_results($wpdb->prepare($sql, $post_name));
            if (!empty($check)) {
                return;
            }
        }


        $_ip = eld_get_ip();
        $blacklist = get_option('_blacklist' . $_ip);
        $four_oh_four = get_transient('four_oh_four' . $_ip);
        if ($blacklist) {
            // add blacklist
            $blacklist++;
            update_option('_blacklist' . $_ip, $blacklist);
            if (ELD_SECURITY_MAX_BLACKLIST < $blacklist) {
                eld_gandalf_protocol_add_ip();
            }
        } elseif ($four_oh_four > ELD_SECURITY_MAX_404) {
            //blacklist
            delete_transient('four_oh_four' . $_ip);
            update_option('_blacklist' . $_ip, 1);
            return;
        } else {
            $four_oh_four++;
            set_transient('four_oh_four' . $_ip, $four_oh_four, 20 * 60);
        }
        eld_security_log('four_oh_four');
    }
}

/**
 * Gandalf protocol Add IP
 * 
 * Add IP to the Gandalf queue
 * 
 * @since 1.0.2
 * @param IP address $ip
 * @uses function eld_gandalf_protocol_write_ips()
 * @return bool Success|Failure
 */
function eld_gandalf_protocol_add_ip($ip = false) {

    if (!$ip) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    // add the ip to gandalf queue option
    $queue = get_option('eld_gandalf_queue', array());

    if (!in_array($ip, $queue)) {
        $queue[] = $ip;

        if (count($queue < 5)) {
            eld_gandalf_protocol_write_ips($queue);
            $queue = array();
        }
        update_option('eld_gandalf_queue', $queue);
    }
    return true;
}

/**
 * Gandalf protocol Write IPs
 * 
 * Write IP to deny list of htaccess.
 * 
 * @param array $ips
 * @return bool Success|Failure
 */
function eld_gandalf_protocol_write_ips($ips = array()) {

    if (empty($ips))
        return false;

    require_once ABSPATH . 'wp-admin/includes/misc.php';

    $htaccess = ABSPATH . '.htaccess';
    $gandalf_list = extract_from_markers($htaccess, 'GANDALF');

    if (count($gandalf_list)) {
        $old_gandalf_denies = array_slice($gandalf_list, 2, count($gandalf_list) - 4);
    }

    $new_denies = array();
    for ($i = 0; $i < count($ips); $i++) {
        $new_denies[] = 'deny from ' . $ips[$i];
    }

    $update_gandalf_denies = array_unique(array_merge($new_denies, $old_gandalf_denies));

    $beginning = array(
        '<Limit GET POST>',
        'order allow,deny'
    );
    $end = array(
        'allow from all',
        '</Limit>'
    );

    $new_gandalf_list = array_merge($beginning, $update_gandalf_denies, $end);
    insert_with_markers($htaccess, 'GANDALF', $new_gandalf_list);
    return true;
}

/**
 * Gandalf protocol Remove IP
 * 
 * Remove IP address from htaccess file, and all options and transients
 * 
 * @param IP Address $ip
 * @return bool Success|Failure
 */
function eld_gandalf_protocol_remove_ip($ip = false) {

    if (!$ip)
        return false;

    $_ip = eld_get_ip($ip);

    delete_transient('four_oh_four' . $_ip);
    delete_transient('wait' . $_ip);
    delete_transient('attempt' . $_ip);
    delete_option('_blacklist' . $_ip, 1);

    require_once ABSPATH . 'wp-admin/includes/misc.php';

    $htaccess = ABSPATH . '.htaccess';
    $gandalf_list = extract_from_markers($htaccess, 'GANDALF');
    $gandalf_denies = array();

    if (count($gandalf_list)) {
        $gandalf_denies = array_slice($gandalf_list, 2, count($gandalf_list) - 4);
    }

    foreach ($gandalf_denies as $key => $deny) {
        if (($key = array_search('deny from ' . $ip, $gandalf_denies)) !== false) {
            unset($gandalf_denies[$key]);
        }
    }

    $beginning = array(
        '<Limit GET POST>',
        'order allow,deny'
    );
    $end = array(
        'allow from all',
        '</Limit>'
    );
    if (!empty($gandalf_denies)) {
        $new_gandalf_list = array_merge($beginning, $gandalf_denies, $end);
    } else {
        $new_gandalf_list = array();
    }
    insert_with_markers($htaccess, 'GANDALF', $new_gandalf_list);
    return true;
}

/**
 * Write security log
 * 
 * Write data to security log
 * 
 * @since 1.0.0
 * @param string $log Type of entry (four_oh_four, login, etc.)
 * @param string|array $data The actual data to write
 * @return bool Success|Failure
 */
function eld_security_log($log, $data = false) {

    if (empty($log)) {
        return false;
    }

    $array_log = array();

    $security_log = WP_CONTENT_DIR . '/security.log';

    $array_log = array($log);

    $array_log[] = '[' . date_i18n('c') . ']';

    $array_log[] = '[' . $_SERVER['REMOTE_ADDR'] . ']';

    if (!( $geo_info = get_transient('geo_info' . eld_get_ip()) )) {
        $geo_info = unserialize(file_get_contents('http://www.geoplugin.net/php.gp?ip=' . $_SERVER['REMOTE_ADDR']));
        set_transient('geo_info' . eld_get_ip(), $geo_info, DAY_IN_SECONDS);
    }

    $array_log[] = html_entity_decode('['
            . $geo_info['geoplugin_city']
            . ','
            . $geo_info['geoplugin_regionName']
            . ','
            . $geo_info['geoplugin_countryName']
            . ']', ENT_QUOTES, "utf-8");

    if ($log == 'four_oh_four') {
        $array_log[] = '[URI=' . $_SERVER['REQUEST_URI'] . ']';
    }

    if (is_array($data)) {
        $array_log = array_merge($array_log, $data);
    } elseif (false !== $data) {
        $array_log[] = $data;
    }

    error_log(implode(' ', $array_log) . "\n", 3, $security_log);
    return true;
}