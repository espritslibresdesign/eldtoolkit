<?php

class ELD_theme_helper {

    private $is = 'home';
    private $description = false;
    private $title = false;
    private $breadcrumb_items = array();
    private $queried_obj = false;
    private $queried_id = 0;
    private $transient_key = '';

    /**
     * The construct
     */
    public function __construct() {
        $this->setup();
        $this->get_transients();
    }

    /**
     * Setup function of the class
     */
    private function setup() {
        $this->_what_is();
        $this->_set_queried();
        add_action('registered_taxonomy', array($this, '_add_term_count_to_wp_taxonomies'), 5);
        $this->_add_term_count_to_wp_taxonomies('category');
        $this->_add_term_count_to_wp_taxonomies('post_tag');
    }

    /**
     * What is the current query
     * 
     * Sets the $this->is value
     */
    private function _what_is() {
        
        if (is_front_page()) {
            $this->is = 'front_page';
        } elseif (is_home()) {
            $this->is = 'home';
        } elseif (is_singular()) {
            $this->is = 'singular';
        } elseif (is_tax() || is_category() || is_tag()) {
            $this->is = 'term';
        } elseif (is_year()) {
            $this->is = 'year';
        } elseif (is_month()) {
            $this->is = 'month';
        } elseif (is_day()) {
            $this->is = 'day';
        } elseif (is_author()) {
            $this->is = 'author';
        } elseif (is_post_type_archive()) {
            $this->is = 'post_type_archive';
        } elseif (is_archive()) {
            $this->is = 'archive';
        } elseif (is_search()) {
            $this->is = 'search';
        } elseif (is_404()) {
            $this->is = '404';
        } else {
            $this->is = 'other';
        }
    }

    private function _set_queried() {
        $this->queried_obj = new stdClass();

        switch ($this->is) {
            case 'year':
                $this->queried_obj->unix = get_the_time('U');
                $this->queried_obj->date = array('Y' => get_the_time('Y'));
                $this->queried_id = get_the_time('Y');
                break;
            case 'month':
                $this->queried_obj->unix = get_the_time('U');
                $this->queried_obj->date = array(
                    'Y' => get_the_time('Y'),
                    'm' => get_the_time('m'),
                    'F' => get_the_time('F'));
                $this->queried_id = get_the_time('Y-m');
                break;
            case 'day':
                $this->queried_obj->unix = get_the_time('U');
                $this->queried_obj->date = array(
                    'Y' => get_the_time('Y'),
                    'm' => get_the_time('m'),
                    'F' => get_the_time('F'),
                    'j' => get_the_time('j'));
                $this->queried_id = get_the_time('Y-m-d');
                break;
            case 'search':
                global $wp_query;
                $this->queried_obj->s = get_search_query();
                $this->queried_obj->n = $wp_query->found_posts;
                $this->queried_id = sanitize_title(get_search_query());
                break;
            case 'author':
                global $author;
                $userdata = get_userdata($author);
                $this->queried_obj->display_name = $userdata->display_name;
                $this->queried_obj->description = $userdata->description;
                $this->queried_id = get_queried_object_id();
                break;
            case 'term':
                $this->queried_obj = get_queried_object();
                $taxonomy = get_taxonomy($this->queried_obj->taxonomy);
                $this->queried_obj->taxonomy_obj = $taxonomy;
                $this->queried_id = get_queried_object_id();
                break;
            case 'post_type_archive':
            case 'archive':
            case 'singular':
                $this->queried_obj = get_queried_object();
                $this->queried_id = get_queried_object_id();
                break;
            case 'front_page':
            case 'home':
            case '404':
            default:
                break;
        }
        $this->transient_key = $this->is . '_' . $this->queried_id;
    }

    private function get_transients() {

        // description
        //$this->description = get_transient('description_' . $this->transient_key);
        // title
        //$this->title = get_transient('title_' . $this->transient_key);
        // breadcrumb
        $this->breadcrumb_items = get_transient('breadcrumb_items_' . $this->transient_key);
    }

    /**
     * Alias to set_transient
     * 
     * Doesn't set transient if user is admin.
     * 
     * @param string $transient The name of the transient
     * @param mixed $value The value to store.
     * @param int $expiration number of seconds before expiration
     */
    private function set_transient($transient, $value, $expiration = null) {
        if (false === current_user_can('manage_options')) {
            set_transient($transient, $value, $expiration);
        }
    }

    /**
     * Breadcrumb delete transient
     * 
     * @global object $wpdb
     */
    private function flush_transients() {
        global $wpdb;
        $transients = $wpdb->get_col(
                "SELECT option_name FROM $wpdb->options WHERE option_name LIKE '_transient_breadcrumb_items_%';"
        );
        foreach ($transients as $transient) {
            $t = str_replace('_transient_', '', $transient);
            delete_transient($t);
        }
    }

    public function get_breadcrumb() {
        if (is_front_page() || is_home()) {
            return;
        }
        if (!$this->breadcrumb_items) {
            $method = '_breadcrumb_' . $this->is;
            if (method_exists($this, $method)) {
                $this->breadcrumb_items = $this->$method();
            }
        }
        //home
        $home_item = array(
            'link' => home_url(),
            'classes' => 'breadcrumb-home',
            'name' => __('Accueil', 'eld')
        );
        array_unshift($this->breadcrumb_items, $home_item);
        $_breadcrumb = $this->_breadcrumb_output();
        return apply_filters('theme_helper_breadcrumb', $_breadcrumb);
    }

    private function set_breadcrumb() {
        
    }

    private function _breadcrumb_output() {
        $items = $this->breadcrumb_items;
        if (empty($items)) {
            return false;
        }

        $links = array();
        foreach ($items as $item) {
            if ($item['link']) {
                $links[] = '<a href="' . $item['link'] . '" class="breadcrumb-item ' . $item['classes'] . '" >' . $item['name'] . '</a>';
            } else {
                $links[] = '<span class="breadcrumb-item breadcrumb-current ' . $item['classes'] . '" >' . $item['name'] . '</a>';
            }
        }

        $output = implode('<span class="breadcrumb-sep">, </span>', $links);

        $output = '<nav class="breadcrumb" role="navigation" aria-label="' . __('Vous êtes ici:', 'eld') . '">' . $output . '</nav>';

        return $output;
    }

    private function _breadcrumb_singular() {

        $items = array();

        //itself
        $itself = array(
            'link' => false,
            'classes' => 'breadcrumb-post',
            'name' => ucfirst($this->queried_obj->post_title)
        );

        $post_type = $this->queried_obj->post_type;
        $post_type_obj = get_post_type_object($post_type);

        $ancestor_items = array();
        if ($post_type_obj->hierarchical) {
            $ancestors = get_ancestors($this->queried_id, $post_type, 'post_type');
            foreach ($ancestors as $ancestor) {
                array_unshift($ancestor_items, array(
                    'link' => get_permalink($ancestor),
                    'classes' => 'breadcrumb-post-ancestor',
                    'name' => get_the_title($ancestor)
                ));
            }
        } elseif ($chosen_tax = apply_filters("breadcrumb_tax_{$post_type}", false)) {
            global $wp_taxonomies;

            $terms = wp_get_post_terms($this->queried_id, $chosen_tax);
            $chosen_term = false;
            $ancestors = $temp_ancestors = array();


            if (false === is_wp_error($terms)) {

                if (1 === count($terms)) {

                    $chosen_term = $terms[0];
                    if (is_taxonomy_hierarchical($chosen_tax)) {
                        $ancestors = get_ancestors($chosen_term->term_id, $chosen_tax, 'taxonomy');
                    }
                } elseif (1 <= count($terms)) {

                    $current_count = 0;

                    if (is_taxonomy_hierarchical($chosen_tax)) {
                        // find the deepest
                        foreach ($terms as $term) {
                            if (0 !== $term->parent) {
                                $temp_ancestors = get_ancestors($term->term_id, $chosen_tax, 'taxonomy');
                                if ($current_count < count($ancestors)) {
                                    $chosen_term = $term;
                                    $ancestors = $temp_ancestors;
                                    $current_count = count($ancestors);
                                }
                            }
                        }
                    }
                    if (false === $chosen_term) {
                        // find the most popular
                        $current_count = 0;

                        foreach ($terms as $term) {
                            if ($current_count < $term->count) {
                                $chosen_term = $term;
                                $current_count = $term->count;
                            }
                        }
                    }
                }
                // add chosen term to ancestors array;
                array_push($ancestors, $chosen_term->term_id);
                foreach ($ancestors as $ancestor) {
                    $current_term = get_term($ancestor, $chosen_tax);
                    array_push($ancestor_items, array(
                        'link' => get_term_link($ancestor, $chosen_tax),
                        'classes' => 'breadcrumb-post-term-ancestor',
                        'name' => ucfirst($current_term->name)
                    ));
                }
            }
        }
        // post_type archive
        $post_type_archive_item = array();
        if ($post_type_obj->has_archive) {
            $post_type_archive_item = array(
                'link' => get_post_type_archive_link($post_type),
                'classes' => 'breadcrumb-posttype-archive',
                'name' => ucfirst($post_type_obj->label)
            );
        }

        if (!empty($post_type_archive_item)) {
            $items = array_merge($items, array($post_type_archive_item));
        }
        if (!empty($ancestor_items)) {
            $items = array_merge($items, $ancestor_items);
        }
        $items = array_merge($items, array($itself));

        $this->set_transient('breadcrumb_items_' . $this->transient_key, $items, DAY_IN_SECONDS);
        return $items;
    }

    private function _breadcrumb_term() {
        $taxonomy = $this->queried_obj->taxonomy_obj->name;
        $term_id = $this->queried_id;

        $post_types = $this->queried_obj->taxonomy_obj->object_type;

        $itself = array(
            'link' => false,
            'classes' => 'breadcrumb-term',
            'name' => ucfirst($this->queried_obj->name)
        );
        $ancestors = array();
        if ($this->queried_obj->taxonomy_obj->hierarchical) {
            if ($this->queried_obj->parent !== 0) {
                $ancestors = get_ancestors($term_id, $taxonomy, 'taxonomy');
            }
            $ancestor_items = array();
            foreach ($ancestors as $ancestor) {
                $current_term = get_term($ancestor, $taxonomy);
                array_unshift($ancestor_items, array(
                    'link' => get_term_link($ancestor, $taxonomy),
                    'classes' => 'breadcrumb-term-ancestor',
                    'name' => ucfirst($current_term->name)
                ));
            }
        }
        $items = array();
        if (!empty($ancestor_items)) {
            $items = array_merge($items, $ancestor_items);
        }

        // post type archive if single
        // post_type archive
        if (1 === count($post_types)) {
            $post_type = $post_types[0];
            $post_type_obj = get_post_type_object($post_type);
            $post_type_archive_item = array();
            if ($post_type_obj->has_archive) {
                $post_type_archive_item = array(
                    'link' => get_post_type_archive_link($post_type),
                    'classes' => 'breadcrumb-posttype-archive',
                    'name' => ucfirst($post_type_obj->label)
                );
            }
        }

        if (!empty($post_type_archive_item)) {
            $items = array_merge(array($post_type_archive_item), $items);
        }

        $items = array_merge($items, array($itself));
        $this->set_transient('breadcrumb_items_' . $this->transient_key, $items, DAY_IN_SECONDS);

        return $items;
    }

    private function _breadcrumb_year() {
        $items = array(
            array(
                'link' => false,
                'classes' => 'breadcrumb-year',
                'name' => sprintf(
                        __('<%1$s %2$s>Archive de </%1$s>%3$s', 'eld'), 'span', 'class="screen-reader-text"', get_the_time('Y')
                )
            )
        );
        return $items;
    }

    private function _breadcrumb_month() {
        $items = array(
            array(
                'link' => get_year_link(get_the_time('Y')),
                'classes' => 'breadcrumb-year',
                'name' => sprintf(
                        __('<%1$s %2$s>Archive de </%1$s>%3$s', 'eld'), 'span', 'class="screen-reader-text"', get_the_time('Y')
                )
            ),
            array(
                'link' => false,
                'classes' => 'breadcrumb-month',
                'name' => sprintf(
                        __('<%1$s %2$s>Archive de </%1$s>%3$s<%1$s %2$s> %4$s</%1$s>', 'eld'), 'span', 'class="screen-reader-text"', ucfirst(get_the_time('F')), get_the_time('Y')
                ),
            )
        );
        return $items;
    }

    private function _breadcrumb_day() {
        $items = array(
            array(
                'link' => get_year_link(get_the_time('Y')),
                'classes' => 'breadcrumb-year',
                'name' => sprintf(
                        __('<%1$s %2$s>Archive de </%1$s>%3$s', 'eld'), 'span', 'class="screen-reader-text"', get_the_time('Y')
                )
            ),
            array(
                'link' => get_month_link(get_the_time('Y'), get_the_time('m')),
                'classes' => 'breadcrumb-month',
                'name' => sprintf(
                        __('<%1$s %2$s>Archive de </%1$s>%3$s<%1$s %2$s> %4$s</%1$s>', 'eld'), 'span', 'class="screen-reader-text"', ucfirst(get_the_time('F')), get_the_time('Y')
                ),
            ),
            array(
                'link' => false,
                'classes' => 'breadcrumb-day',
                'name' => sprintf(
                        __('<%1$s %2$s>Archive du </%1$s>%3$s<%1$s %2$s> %4$s %5$s</%1$s>', 'eld'), 'span', 'class="screen-reader-text"', get_the_time('j'), get_the_time('F'), get_the_time('Y')
                )
            )
        );
        return $items;
    }

    private function _breadcrumb_author() {
        $items = array(
            array(
                'link' => false,
                'classes' => 'breadcrumb-author',
                'name' => $this->queried_obj->display_name
            )
        );
        return $items;
    }

    private function _breadcrumb_post_type_archive() {
        $items = array(
            array(
                'link' => false,
                'classes' => 'breadcrumb-post-type-archive',
                'name' => $this->queried_obj->label
            )
        );
        return $items;
    }

    private function _breadcrumb_archive() {
        $items = array(
            array(
                'link' => false,
                'classes' => 'breadcrumb-post-type-archive',
                'name' => $this->queried_obj->label
            )
        );
        return $items;
    }

    private function _breadcrumb_search() {
        $items = array(
            array(
                'link' => false,
                'classes' => 'breadcrumb-search',
                'name' => sprintf(
                        __('Recherche pour «%s»', 'eld'), get_search_query()
                )
            )
        );
        return $items;
    }

    private function _breadcrumb_404() {
        $items = array(
            array(
                'link' => false,
                'classes' => 'breadcrumb-404',
                'name' => __('Page non trouvée', 'eld')
            )
        );
        return $items;
    }

    public function get_title() {
        if (false === $this->title) {
            $method = '_title_' . $this->is;
            $this->title = $this->$method();
        }
        return apply_filters('theme_helper_title', $this->title, $this->queried_obj);
    }

    private function _title_singular() {
        return $this->queried_obj->post_title;
    }

    private function _title_term() {
        $_title = sprintf(
                /* translator: Theme helper Term title, %1$s term name, %2$s taxonomy name */
                apply_filters('theme_helper_title_pattern_term', __('%2$s «%1$s»', 'eld')), $this->queried_obj->name, $this->queried_obj->taxonomy_obj->labels->singular_name);
        return $_title;
    }

    private function _title_year() {
        $_title = sprintf(
                /* translator: Theme helper Year title, %1$s year */
                apply_filters('theme_helper_title_pattern_year', __('Archive de %1$s', 'eld')), $this->queried_id);
        return $_title;
    }

    private function _title_month() {
        $_title = sprintf(
                apply_filters('theme_helper_title_pattern_month', _x('Archive de %1$s', 'Month archive', 'eld')), date_i18n(
                        apply_filters('theme_helper_title_date_month', _x('F Y', 'Month archive date pattern', 'eld')), $this->queried_obj->unix));
        return $_title;
    }

    private function _title_day() {
        $_title = sprintf(
                apply_filters('theme_helper_title_pattern_day', _x('Archive du %1$s', 'Day archive', 'eld')), date_i18n(
                        apply_filters('theme_helper_title_date_day', _x('j F Y', 'Day archive date pattern', 'eld')), $this->queried_obj->unix));
        return $_title;
    }

    private function _title_author() {
        $_title = sprintf(
                apply_filters('theme_helper_title_pattern_author', _x('%s', 'Author archive', 'eld')), $this->queried_obj->display_name
        );
        return $_title;
    }

    private function _title_post_type_archive() {
        $_title = sprintf(
                apply_filters('theme_helper_title_pattern_posttype_archive', _x('%s', 'Post type archive', 'eld')), $this->queried_obj->label
        );
        return $_title;
    }

    private function _title_archive() {
        $_title = sprintf(
                apply_filters('theme_helper_title_pattern_posttype_archive', _x('%s', 'Post type archive', 'eld')), $this->queried_obj->label
        );
        return $_title;
    }

    private function _title_search() {
        $_title_pattern = _n_noop(
                apply_filters('theme_helper_title_pattern_search_singular', 'Un résultat de recherche pour «%1$s»'), apply_filters('theme_helper_title_pattern_search_plural', '%2$s résultats de recherche pour «%1$s»'), 'eld'
        );
        $_title = sprintf(
                translate_nooped_plural($_title_pattern, $this->queried_obj->n, 'eld'), $this->queried_obj->s, $this->queried_obj->n
        );
        return $_title;
    }

    private function _title_404() {
        $_title = apply_filters('theme_helper_title_pattern_404', __('Page non trouvée', 'eld'));
        return $_title;
    }

    private function _title_other() {
        return bloginfo('name');
    }

    public function set_title($_title) {
        if (!empty($_title)) {
            $this->title = $_title;
        }
    }

    public function get_description() {
        if (false === $this->description) {
            $method = '_description_' . $this->is;
            $this->description = $this->$method();
        }
        return apply_filters('theme_helper_description', $this->description, $this->queried_obj);
    }

    public function set_description($_description) {
        if (!empty($_description)) {
            $this->description = $_description;
        }
    }

    private function _description_front_page() {
        return get_option('_eld_meta_description_front_page',get_bloginfo('description'));
    }
    
    private function _description_home() {
        return get_option('_eld_meta_description_home',get_bloginfo('description'));
    }
    
    private function _description_singular() {
        if (!($_description = strip_tags($this->queried_obj->post_excerpt))) {
            $_description = wp_trim_words(strip_tags(strip_shortcodes($this->queried_obj->post_content)), apply_filters('excerpt_length', 55));
        }
        return $_description;
    }

    private function _description_term() {
        if ($_description = strip_tags($this->queried_obj->description)) {
            $_description = sprintf(
                    apply_filters('theme_helper_description_pattern_term', __('Les contenus dans %2$s «%1$s». %3$s', 'eld')), $this->queried_obj->name, $this->queried_obj->taxonomy_obj->labels->singular_name, get_bloginfo('description'));
        }
        return $_description;
    }

    private function _description_year() {
        $_description = sprintf(
                apply_filters('theme_helper_description_pattern_year', __('Archive de %1$s. %2$s', 'eld')), $this->queried_id, get_bloginfo('description'));
        return $_description;
    }

    private function _description_month() {
        $_description = sprintf(
                apply_filters('theme_helper_description_pattern_month', _x('Archive de %1$s. %2$s', 'Month archive', 'eld')), date_i18n(
                        apply_filters('theme_helper_title_date_month', _x('F Y', 'Month archive date pattern', 'eld')), $this->queried_obj->unix), get_bloginfo('description'));
        return $_description;
    }

    private function _description_day() {
        $_description = sprintf(
                apply_filters('theme_helper_description_pattern_day', _x('Archive du %1$s. %2$s', 'Day archive', 'eld')), date_i18n(
                        apply_filters('theme_helper_title_date_day', _x('j F Y', 'Day archive date pattern', 'eld')), $this->queried_obj->unix), get_bloginfo('description'));
        return $_description;
    }

    private function _description_author() {
        if ($this->queried_obj->description) {
            $_description = $this->queried_obj->description;
        } else {
            $_description = get_bloginfo('description');
        }
        $_description = $this->queried_obj->display_name . ' ' . $_description;
        return $_description;
    }

    private function _description_post_type_archive() {
        if ($this->queried_obj->description) {
            $_description = $this->queried_obj->description;
        } else {
            $_description = $this->queried_obj->labels->archives;
        }
        return $_description;
    }

    private function _description_archive() {
        if ($this->queried_obj->description) {
            $_description = $this->queried_obj->description;
        } else {
            $_description = get_bloginfo('description');
        }
        $_description = $this->queried_obj->label . ' ' . $_description;
        return $_description;
    }

    private function _description_search() {
        $_description_pattern = _n_noop(
                apply_filters('theme_helper_description_pattern_search_singular', 'Un résultat de recherche pour «%1$s»'), apply_filters('theme_helper_description_pattern_search_plural', '%2$s résultats de recherche pour «%1$s»'), 'eld'
        );
        $_description = sprintf(
                translate_nooped_plural($_description_pattern, $this->queried_obj->n, 'eld'), $this->queried_obj->s, $this->queried_obj->n
        );
        return $_description . '. ' . get_bloginfo('description');
    }

    private function _description_404() {
        $_description = apply_filters('theme_helper_description_pattern_404', __('Page non trouvée', 'eld'));
        return $_description . '. ' . get_bloginfo('description');
    }

    private function _description_other() {
        return get_bloginfo('description');
    }

    /**
     * Add term count to $wp_taxonomies
     * 
     * Add term count to $wp_taxonomies at taxonomy registration
     * 
     * @global object $wp_taxonomies
     * @param string $taxonomy Taxonomy name
     */
    private function _add_term_count_to_wp_taxonomies($taxonomy) {

        global $wp_taxonomies;
        $term_count = wp_count_terms($taxonomy);
        $wp_taxonomies[$taxonomy]->term_count = $term_count;
    }

}

/**
 * Initialize the class
 * 
 * Hooked on wp, to be sure the main query is known.
 * 
 * @global ELD_theme_helper $eld_theme_helper
 */
function init_theme_helper() {
    global $eld_theme_helper;
    $eld_theme_helper = new ELD_theme_helper();
}

add_action('wp', 'init_theme_helper');

/**
 * Add excerpt to page
 */
function _add_excerpt_page_support() {
    add_post_type_support('page', 'excerpt');
}
add_action('init', '_add_excerpt_page_support');

/**
 * Outputs the breadcrumb, depending on what page we're on.
 * 
 * @see method ELD_theme_helper->get_breadcrumb()
 * 
 * @global ELD_theme_helper $eld_theme_helper
 */
function the_breadcrumb() {
    global $eld_theme_helper;
    echo $eld_theme_helper->get_breadcrumb();
}

/**
 * Returns the breadcrumb, depending on what page we're on.
 * 
 * @see method ELD_theme_helper->get_breadcrumb()
 * 
 * @global ELD_theme_helper $eld_theme_helper
 */
function get_breadcrumb($param) {
    global $eld_theme_helper;
    return $eld_theme_helper->get_breadcrumb($param);
}

/**
 * Outputs the computed description for meta tag, depending on what page we're on.
 * 
 * @see method ELD_theme_helper->get_description()
 * @global ELD_theme_helper $eld_theme_helper
 */
function the_meta_description() {
    global $eld_theme_helper;
    echo $eld_theme_helper->get_description();
}

/**
 * Returns the computed description for meta tag, depending on what page we're on.
 * 
 * @see method ELD_theme_helper->get_description()
 * @global ELD_theme_helper $eld_theme_helper
 */
function get_meta_description() {
    global $eld_theme_helper;
    return $eld_theme_helper->get_description();
}

/**
 * Outputs the computed page title, depending on what page we're on.
 * 
 * @see method ELD_theme_helper->get_title()
 * @global ELD_theme_helper $eld_theme_helper
 */
function the_page_title() {
    global $eld_theme_helper;
    echo $eld_theme_helper->get_title();
}

/**
 * Returns the computed page title, depending on what page we're on.
 * 
 * @see method ELD_theme_helper->get_title()
 * @global ELD_theme_helper $eld_theme_helper
 */
function get_page_title() {
    global $eld_theme_helper;
    return $eld_theme_helper->get_title();
}

// Adding hooks management
function _eld_meta_description() {
    $description = get_meta_description();
    if ($description) {
        echo '<meta name="description" content="' . $description . '" />';
    }
}

add_action('wp_head', '_eld_meta_description', 5);

/**
 * array2string_enumaration
 * 
 * Receive an array and return a gramatically correct enumeration localized string
 * 
 * @param array $array
 * @param array $args
 * @return string
 */
function array2string_enumaration($array, $m = 'AND') {

    if (!is_array($array)) {
        return $array;
    }
    if (1 === count($array)) {
        return $array[0];
    }

    $string = apply_filters('array2string_enumaration', $array);

    if (is_string($string)) {
        return $string;
    }

    switch ($m) {
        case 'OR':
            $marqueur = __('ou', 'eld');
            break;
        case 'AND':
        default:
            $marqueur = __('et', 'eld');
            break;
    }

    $last = array_pop($array);

    $string = implode(', ', $array) . ' ' . $marqueur . ' ' . $last;

    return $string;
}
