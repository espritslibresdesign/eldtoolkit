<?php
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * System messages init
 */
function sm_init() {

    // initial setting configuration
    if (!$sm_settings = get_option('sm_settings')) {

        $sm_settings = array();
        $sm_settings['user_subject'] = __('[site_name]| Vos informations de connexion', 'eld');
        $sm_settings['user_body'] = __('Vous avez été inscrit au site [site_name].<br /><br />Votre nom d’utilisateur: [user_login]<br /><br />Configurez votre mot de passe à l’adresse suivante:<br />[set_pass_url]', 'eld');
        $sm_settings['admin_subject'] = __('[site_name]| Inscription d’un nouvel utilisateur', 'eld');
        $sm_settings['admin_body'] = __('Nouvel utilisateur sur votre site [site_name]<br /><br />Nom d’utilisateur: [user_login]<br />Courriel: [user_email]', 'eld');
        $sm_settings['admin_notify_user_id'] = array();
        $sm_settings['header_from_name'] = '[site_name]';
        $sm_settings['header_from_email'] = '[admin_email]';
        $sm_settings['header_reply_to'] = '[admin_email]';
        $sm_settings['header_send_as'] = 'html';
        $sm_settings['header_additional'] = '';
        $sm_settings['set_global_headers'] = 1;
        $sm_settings['attachment_url'] = '';
        $sm_settings['password_reminder_subject'] = __('[site_name]| Mot de passe oublié', 'eld');
        $sm_settings['password_reminder_body'] = __('Il a été demandé de réinitialiser votre mot de passe pour l’utilisateur [user_login] sur le site [site_name].<br /><br />Si vous n’avez pas fait cette demande, ignorez ce courriel et votre mot de passe demeurera inchangé.<br /><br />Pour réinitialiser votre mot de passe, visitez le lien suivant: [reset_pass_url]', 'eld');

        add_option('sm_settings', $sm_settings);
    }
    add_filter("retrieve_password_title", "sm_lost_password_title", 10, 3);
    add_filter("retrieve_password_message", "sm_lost_password_message", 10, 4);
}

add_action('init', 'sm_init');



/* * *****************************************************************************************
 * SETTING PAGE
 * 
 * @SINCE 1.0.6
 * @uses add_options_page
 */

function sm_settings_page_init() {
    add_options_page(__('Messages système', 'eld'), __('Messages système', 'eld'), 'manage_options', 'system_messages', 'system_messages_settings_page');
}

add_action('admin_menu', 'sm_settings_page_init');

/**
 *  System messages registering settings
 */
function sm_settings_setup() {

    register_setting('system_messages', 'sm_settings');

    add_settings_section('sm_settings_general', __('Options générales', 'eld'), 'sm_general_settings_output', 'system_messages');
    add_settings_field('sm_settings[header_from_email]', __('Courriel «De»', 'eld'), 'sm_settings_header_from_email_output', 'system_messages', 'sm_settings_general');
    add_settings_field('sm_settings[header_from_name]', __('Nom «De»', 'eld'), 'sm_settings_header_from_name_output', 'system_messages', 'sm_settings_general');

    add_settings_section('sm_settings_welcome', __('Courriel de bienvenue', 'eld'), 'sm_welcome_settings_output', 'system_messages');
    add_settings_field('sm_settings[user_subject]', __('Sujet', 'eld'), 'sm_settings_user_subject_output', 'system_messages', 'sm_settings_welcome');
    add_settings_field('sm_settings[user_body]', __('Message', 'eld'), 'sm_settings_user_body_output', 'system_messages', 'sm_settings_welcome');
    add_settings_field('sm_settings[header_reply_to]', __('Courriel «Répondre à»', 'eld'), 'sm_settings_header_reply_to_output', 'system_messages', 'sm_settings_welcome');
    add_settings_field('sm_settings[header_additional]', __('Entêtes supplémentaires', 'eld'), 'sm_settings_header_additional_output', 'system_messages', 'sm_settings_welcome');

    add_settings_section('sm_settings_notification', __('Notification de l’administrateur', 'eld'), 'sm_admin_settings_output', 'system_messages');
    add_settings_field('sm_settings[admin_subject]', __('Sujet', 'eld'), 'sm_settings_admin_subject_output', 'system_messages', 'sm_settings_notification');
    add_settings_field('sm_settings[admin_body]', __('Message', 'eld'), 'sm_settings_admin_body_output', 'system_messages', 'sm_settings_notification');
    add_settings_field('sm_settings[admin_notify_user_id]', __('Choix des administrateurs', 'eld'), 'sm_settings_admin_notify_user_id_output', 'system_messages', 'sm_settings_notification');

    add_settings_section('sm_settings_password_reminder', __('Rappel de mot de passe', 'eld'), 'sm_password_reminder_settings_output', 'system_messages');
    add_settings_field('sm_settings[password_reminder_subject]', __('Sujet', 'eld'), 'sm_settings_password_reminder_subject_output', 'system_messages', 'sm_settings_password_reminder');
    add_settings_field('sm_settings[password_reminder_body]', __('Message', 'eld'), 'sm_settings_password_reminder_body_output', 'system_messages', 'sm_settings_password_reminder');
}

add_action('admin_init', 'sm_settings_setup');

/**
 * System messages test
 * 
 * @global WP_User $current_user Current user
 * @param string $passing_through Not Used, filter used to trigger action
 * @return string
 */
function system_messages_test($passing_through) {

    if (filter_input(INPUT_POST, 'sm_submit_and_test')) {
        global $current_user;
        wp_get_current_user();

        // Test User notification (admin and user)
        wp_new_user_notification($current_user->ID, null, 'both');

        // Test Lost password
        $subject = sm_lost_password_title('', $current_user->user_login, $current_user);
        $subject = mb_encode_mimeheader($subject,'UTF-8');
        
        $message = sm_lost_password_message('', '[the_key]', $current_user->user_login, $current_user);
        wp_mail($current_user->user_email, $subject, $message);

        add_settings_error('system_messages', 'message_sent', __('Messages tests envoyés.'), 'updated');
    }

    return $passing_through;
}

add_filter('wp_redirect', 'system_messages_test');

/**
 * Render System messages setting page
 */
function system_messages_settings_page() {

    if (!current_user_can('manage_options')) {
        /* Translator : 'You do not have sufficient permissions to manage options for this site.' */
        wp_die(__('Vous n’avez pas les droits suffisants pour accéder à cette page', 'eld'));
    }
    ?>
    <div class="wrap">
        <h1><?php _e('Messages système', 'eld'); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('system_messages');
            do_settings_sections('system_messages');
            ?>
            <p>
    <?php submit_button(__('Enregistrer les modifications et tester les messages', 'eld'), 'secondary', 'sm_submit_and_test', false); ?>
                &nbsp;
    <?php submit_button(null, 'primary', 'submit', false); ?>
            </p>
        </form>
    </div>
    <?php
}

function sm_general_settings_output() {
    //_e('These settings effect all of this plugin and, in some cases, all of your site.', 'eld');
    _e('Ces configurations affectent tous les courriels envoyés par le site.', 'eld');
}

function sm_settings_header_from_email_output() {
    $header_from_email = sm_get_option('header_from_email');
    echo '<input type="text" name="sm_settings[header_from_email]" id="sm_setting_header_from_email" value="' . $header_from_email . '"/>'
    . '<p class="description">'
    //. __('Global option change the from email address for all site emails', 'eld')
    . __('Change l’adresse d’origine de tous les courriels du site.', 'eld')
    . '</p>';
}

function sm_settings_header_from_name_output() {
    $header_from_name = sm_get_option('header_from_name');
    echo '<input type="text" name="sm_settings[header_from_name]" id="sm_setting_header_from_name" value="' . $header_from_name . '"/>'
    . '<p class="description">'
    //. __('Global option change the from name for all site emails', 'eld')
    . __('Change le nom d’origine de tous les courriels du site.', 'eld')
    . '</p>';
}

function sm_welcome_settings_output() {
    //_e('These settings are for the email sent to the new user on their signup.', 'eld');
    _e('Ces configurations s’appliquent aux courriels envoyés aux nouveaux utilisateurs.', 'eld');
}

function sm_settings_user_subject_output() {
    $user_subject = sm_get_option('user_subject');
    echo '<input class="widefat" type="text" name="sm_settings[user_subject]" id="sm_setting_user_subject" value="' . $user_subject . '"/>'
    . '<p class="description">'
    //. __('Subject line for the welcome email sent to the user.', 'eld')
    . __('Sujet du courriel envoyé au nouvel utilisateur.', 'eld')
    . '</p>';
}

function sm_settings_user_body_output() {
    $user_body = sm_get_option('user_body');
    $editor_setting = array(
        'textarea_name' => 'sm_settings[user_body]',
        'textarea_rows' => 5,
        'media_buttons' => false,
        'tinymce' => true,
        'teeny' => true,
        'wpautop' => false
    );
    wp_editor($user_body, 'sm_settings_user_body', $editor_setting);
    echo '<p class="description">'
    //. __('Body content for the welcome email sent to the user.', 'eld')
    . __('Message du courriel envoyé au nouvel utilisateur.', 'eld')
    . '</p>';
}

function sm_settings_header_reply_to_output() {
    $header_reply_to = sm_get_option('header_reply_to');
    echo '<input class="widefat" type="text" name="sm_settings[header_reply_to]" id="sm_setting_header_reply_to" value="' . $header_reply_to . '"/>'
    . '<p class="description">'
    . __('Optionnel. Adresse de réponse du courriel envoyé au nouvel utilisateur.', 'eld')
    //. __('Optional Header sent to change the reply to address for new user notification.', 'eld')
    . '</p>';
}

function sm_settings_header_additional_output() {
    $header_additional = sm_get_option('header_additional');
    echo '<textarea class="widefat" type="text" name="sm_settings[header_additional]" id="sm_setting_header_additional">' . $header_additional . '</textarea>';
    echo '<p class="description">'
    //. __('Optional field for advanced users to add more headers. Don’t forget to separate headers with \r\n.', 'eld')
    . __('Optionnel. Entêtes supplémentaires pour utilisateurs averties. Séparer chaque entêtes par \r\n.', 'eld')
    . '</p>';
}

function sm_admin_settings_output() {
    //_e('These settings are for the email sent to the admin on a new user signup.', 'eld');
    _e('Ces configurations s’appliquent aux courriels envoyés aux administrateurs lors de l’inscription de nouveaux utilisateurs.', 'eld');
}

function sm_settings_admin_subject_output() {
    $admin_subject = sm_get_option('admin_subject');
    echo '<input class="widefat" type="text" name="sm_settings[admin_subject]" id="sm_setting_admin_subject" value="' . $admin_subject . '"/>'
    . '<p class="description">'
    . __('Sujet du courriel envoyé aux administrateurs sélectionnés.', 'eld')
    //. __('Subject Line for the email sent to the admin user(s)', 'eld')
    . '</p>';
}

function sm_settings_admin_body_output() {
    $admin_body = sm_get_option('admin_body');
    $editor_setting = array(
        'textarea_name' => 'sm_settings[admin_body]',
        'textarea_rows' => 5,
        'media_buttons' => false,
        'tinymce' => true,
        'teeny' => true,
        'wpautop' => false
    );
    wp_editor($admin_body, 'sm_settings_admin_body', $editor_setting);
    echo '<p class="description">'
    . __('Message du courriel envoyé aux administrateurs sélectionnés.', 'eld')
    //. __('Body content for the email sent to the admin user(s).', 'eld')
    . '</p>';
}

function sm_settings_admin_notify_user_id_output() {
    $admin_notify_user_id = sm_get_option('admin_notify_user_id');
    if (!is_array($admin_notify_user_id)) {
        $admin_notify_user_id = explode(',', $admin_notify_user_id);
    }
    $admins = get_users(array('role' => 'Administrator'));

    echo '<p>';
    if (1 == count($admins)) {
        $admin = $admins[0];
        echo '<label for="admin_id_'
        . $admin->ID
        . '">'
        . '<input type="radio" id="admin_id_' . $admin->ID . '" name="sm_settings[admin_notify_user_id][]" value="'
        . $admin->ID
        . '" checked />'
        . $admin->display_name
        . '</label>';
    } else {
        foreach ($admins as $admin) {
            echo '<label for="admin_id_'
            . $admin->ID
            . '">'
            . '<input type="checkbox" id="admin_id_' . $admin->ID . '" name="sm_settings[admin_notify_user_id][]" value="'
            . $admin->ID
            . '"'
            . checked(in_array($admin->ID, $admin_notify_user_id), true, false)
            . ' />'
            . $admin->display_name
            . '</label> ';
        }
    }
    echo '</p>';
    echo '<p class="description">'
    . __('Sélectionnez les administrateurs qui recevront ce message.', 'eld')
    . '</p>';
}

function sm_password_reminder_settings_output() {
    //_e('These settings are for the email sent to the admin on a new user signup.', 'eld');
    _e('Ces configurations s’appliquent aux courriels envoyés pour renouveller les mots de passe.', 'eld');
}

function sm_settings_password_reminder_subject_output() {
    $password_reminder_subject = sm_get_option('password_reminder_subject');
    echo '<input class="widefat" type="text" name="sm_settings[password_reminder_subject]" id="sm_setting_password_reminder_subject" value="' . $password_reminder_subject . '"/>'
    . '<p class="description">'
    . __('Sujet du courriel envoyé à l’utilisateur.', 'eld')
    . '</p>';
}

function sm_settings_password_reminder_body_output() {
    $password_reminder_body = sm_get_option('password_reminder_body');
    $editor_setting = array(
        'textarea_name' => 'sm_settings[password_reminder_body]',
        'textarea_rows' => 5,
        'media_buttons' => false,
        'tinymce' => true,
        'teeny' => true,
        'wpautop' => false
    );
    wp_editor($password_reminder_body, 'sm_settings_password_reminder_body', $editor_setting);
    echo '<p class="description">'
    . __('Message du courriel envoyé au nouvel utilisateur.', 'eld')
    . '</p>';
}



/** *****************************************************************************************
 *    MESSAGES
 * 
 */
if (!function_exists('wp_new_user_notification')) :

    /**
     * Email login credentials to a newly-registered user.
     *
     * A new user registration notification is also sent to admin email.
     *
     * @since 2.0.0
     * @since 4.3.0 The `$plaintext_pass` parameter was changed to `$notify`.
     * @since 4.3.1 The `$plaintext_pass` parameter was deprecated. `$notify` added as a third parameter.
     *
     * @global wpdb         $wpdb      WordPress database object for queries.
     * @global PasswordHash $wp_hasher Portable PHP password hashing framework instance.
     *
     * @param int    $user_id    User ID.
     * @param null   $deprecated Not used (argument deprecated).
     * @param string $notify     Optional. Type of notification that should happen. Accepts 'admin' or an empty
     *                           string (admin only), 'user', or 'both' (admin and user). Default empty.
     */
    function wp_new_user_notification($user_id, $deprecated = null, $notify = '') {
        if ($deprecated !== null) {
            _deprecated_argument(__FUNCTION__, '4.3.1');
        }
        
        global $wpdb, $wp_hasher;

        $settings = get_option('sm_settings');

        $user = get_userdata($user_id);

        foreach ($settings as $var => $string) {
            $$var = sm_placeholder_replace($string, array(), $user);
        }

        if ('user' !== $notify) {
            
            $admin_recipients = array();

            if (!is_array($admin_notify_user_id)) {
                $admin_notify_user_id = array($admin_notify_user_id);
            }
            foreach ($admin_notify_user_id as $admin_id) {
                $admin_id = intval($admin_id);
                $admin_data = get_userdata($admin_id);
                if (is_object($admin_data)) {
                    $admin_recipients[] = mb_encode_mimeheader($admin_data->display_name,'UTF-8') . ' <' . $admin_data->user_email . '>';
                }
            }
            // final failsafe
            if (empty($admin_notify_user_id)) {
                $admin_recipients[] = get_option('admin_email');
            }

            $admin_subject = mb_encode_mimeheader($admin_subject, 'UTF-8');
            
            $test = wp_mail($admin_recipients, $admin_subject, $admin_body, $header_additional);
            
        }


        // if notify admin
        // `$deprecated was pre-4.3 `$plaintext_pass`. An empty `$plaintext_pass` didn't sent a user notifcation.
        if ('admin' === $notify || ( empty($deprecated) && empty($notify) )) {
            return;
        }

        // Generate something random for a password reset key.
        $key = wp_generate_password(20, false);

        /** This action is documented in wp-login.php */
        do_action('retrieve_password_key', $user->user_login, $key);

        // Now insert the key, hashed, into the DB.
        if (empty($wp_hasher)) {
            require_once ABSPATH . WPINC . '/class-phpass.php';
            $wp_hasher = new PasswordHash(8, true);
        }
        $hashed = time() . ':' . $wp_hasher->HashPassword($key);
        $wpdb->update($wpdb->users, array('user_activation_key' => $hashed), array('user_login' => $user->user_login));

        $set_pass_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user->user_login), 'login');


        $user_body = str_replace('[set_pass_url]', $set_pass_url, $user_body);
        $user_subject = mb_encode_mimeheader($user_subject, 'UTF-8');

        
        
        $test = wp_mail($user->user_email, $user_subject, $user_body, $header_additional);

    }

endif;

function sm_lost_password_title($title, $user_login, $user_data) {
    $settings = get_option('sm_settings');

    if ($settings['password_reminder_subject']) {

        $title = sm_placeholder_replace($settings['password_reminder_subject'], array(), $user_data);
    }

    return $title;
}

/**
 * Prepare Lost Password message
 * 
 * @param string $message The saved message
 * @param string $key
 * @param string $user_login User login
 * @param WP_User $user_data WP User object
 * @return string
 */
function sm_lost_password_message($message, $key, $user_login, $user_data) {

    if (is_int($user_login)) {
        $user_info = get_user_by('id', $user_login);
        $user_login = $user_info->user_login;
    }

    $settings = get_option('sm_settings');

    if (trim($settings['password_reminder_body'])) {
        $reset_pass_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');
        $message = sm_placeholder_replace($settings['password_reminder_body'], array('[reset_pass_url]' => $reset_pass_url), $user_data);
    }

    return $message;
}

function sm_from_email($from_email) {

    if (0 === strpos('wordpress@', $from_email) && $new = sm_placeholder_replace(sm_get_option('header_from_email'), null, null)) {
        return $new;
    }
    return $from_email;
}

add_filter('wp_mail_from', 'sm_from_email', 1);

function sm_from_name($from_name) {
    if ('WordPress' === $from_name && $new = sm_placeholder_replace(sm_get_option('header_from_name'), null, null)) {
        return $new;
    }
    return $from_name;
}

add_filter('wp_mail_from_name', 'sm_from_name', 1);

function sm_mail_content_type($content_type) {
    return 'text/html';
}

add_filter('wp_mail_content_type', 'sm_mail_content_type', 1);


function sm_force_wp_mail_html($atts) {
    
    if ( $atts['message'] === strip_tags( $atts['message'] ) ) {
        $atts['message'] = nl2br($atts['message']);
    }
    
    return $atts;
}
add_filter('wp_mail','sm_force_wp_mail_html');

/* * *****************************************************************************************
 *    HELPERS
 * 
 */


/**
 * System message placeholder replacement
 * 
 * @global type $current_site
 * 
 * @param string    $string String marked with placeholders
 * @param array     $placeholders Array of placeholder=>value
 * @param WP_User   $user WP User object of current targeted user
 * @return string   The updated string
 */
function sm_placeholder_replace($string, $placeholders = array(), $user = false) {
    if (!is_string($string) || '' == trim($string)) {
        return $string;
    }

    $settings = get_option('sm_settings');

    if (is_multisite()) {
        global $current_site;
        $blogname = $current_site->site_name;
    } else {
        $blogname = get_option('blogname');
    }

    $default_placeholders = array(
        '[admin_email]' => get_option('admin_email'),
        '[site_name]' => wp_specialchars_decode($blogname, ENT_QUOTES),
        '[site_url]' => home_url(),
        '[login_url]' => wp_login_url(),
        '[lost_pass_url]' => wp_lostpassword_url(),
        //'[reset_pass_url]' => wp_lostpassword_url(),
        '[plaintext_password]' => '*****',
//        '[user_login]' => $user->user_login,
//        '[user_email]' => $user->user_email,
//        '[first_name]' => $user->first_name,
//        '[last_name]' => $user->last_name,
//        '[user_id]' => $user->ID,
        '[user_password]' => '*****',
        '[date]' => date_i18n(get_option('date_format')),
        '[time]' => date_i18n(get_option('time_format')),
        '[post_data]' => '<pre>' . print_r($_REQUEST, true) . '</pre>'
    );
    if (is_a($user, 'WP_User')) {
        $default_placeholders = array_merge($default_placeholders, array(
            '[user_login]' => $user->user_login,
            '[user_email]' => $user->user_email,
            '[first_name]' => $user->first_name,
            '[last_name]' => $user->last_name,
            '[user_id]' => $user->ID,
                ))
        ;
    }
    $placeholders = apply_filters('sm_placeholder_replace', wp_parse_args($placeholders, $default_placeholders));

    return str_replace(array_keys($placeholders), array_values($placeholders), $string);
}

function sm_get_option($option) {
    $options = get_option('sm_settings');
    $return = isset($options[$option]) ? $options[$option] : false;
    return $return;
}
