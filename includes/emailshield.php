<?php
// Exit if accessed directly
if (!defined('ABSPATH')) { exit; }

/**
 * Adding Emailshield shortcode
 * 
 * On init, adds the shortcode mailto
 * 
 * @see add_shortcode()
 */
function init_emailshield(){
    add_shortcode('mailto','shortcode_emailshield');
}
add_action('init','init_emailshield');

/**
 * Enqueue emailshield javascript
 * 
 * On wp_enqueue_scripts, enqueue the emailshield javascript file
 * 
 * @uses jquery
 */
function enqueue_emailshield(){
    wp_enqueue_script('emailshield',plugins_url('/js/emailshield.js',dirname(__FILE__)),array('jquery'),'1.0',true);
}
add_action('wp_enqueue_scripts','enqueue_emailshield');


/**
 * Shortcode emailshield
 * 
 * Transform the shortcode mailto in a output to obfuscate email address from bot 
 * 
 * @since 1.0.0
 * @param array $atts
 * @return string HTML markup of email address
 */
function shortcode_emailshield($atts){
	$to = false;
	$email = false;
	if (count($atts)>1 || array_key_exists('email',$atts) ) {
		extract(shortcode_atts(array('email'=>false,'to'=>false),$atts));
	}else{
		$email = $atts[0];
	}
	$parts = array();
	
	$regexp = "/^([^0-9][A-z0-9_\-]+([.][A-z0-9_\-]+)*)[@]([A-z0-9_\-]+([.][A-z0-9_\-]+)*)[.]([A-z]{2,4})$/";
	preg_match($regexp,$email,$parts);
	if (!empty($parts)){
		if ($to){
			$to .= ': '; $class='emailto';
		}else{
			$class='email';
		}
		return sprintf('<span class="'.$class.'">%s%s ['._x('à','Bot protect @ symbole replacement','eld').'] %s ['._x('point','Bot protect dot symbole replacement','eld').'] %s</span>',$to,$parts[1],$parts[3],$parts[5]);
	}else{
		return $email;
	}
}
