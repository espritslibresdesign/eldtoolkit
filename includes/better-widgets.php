<?php
// Exit if accessed directly
if (!defined('ABSPATH')) { exit; }

/**
 * Better widget_setup
 * @since 1.0
 * 
 * Improving basic WordPress widgets, with more options or more efficient markup
 */
function better_widgets_setup() {

    unregister_widget('WP_Widget_Recent_Posts');
    register_widget('Better_Widget_Recent_Posts');

    unregister_widget('WP_Widget_Categories');
    register_widget('Better_Widget_Categories');

    unregister_widget('WP_Widget_Search');
    register_widget('Better_Widget_Search');
    
    register_widget('Better_Widget_Related_Posts');
    
    register_widget('Better_Widget_TextNLink');
    register_widget('Better_Widget_Button');

}
add_action('widgets_init', 'better_widgets_setup');

/**
 *  Recent posts widget
 * 
 * @since 1.0.10
 */
class Better_Widget_Recent_Posts extends WP_Widget {

    function __construct() {
        $widget_ops = array(
            'classname' => 'widget_better_recent_posts',
            'description' => __('Les contenus les plus récents de votre site.', 'eld')
        );
        parent::__construct('better-recent-posts', __('Contenus récents', 'eld'), $widget_ops);
        $this->alt_option_name = 'widget_better_recent_posts';

        add_action('save_post', array(&$this, 'flush_widget_cache'));
        add_action('deleted_post', array(&$this, 'flush_widget_cache'));
        add_action('switch_theme', array(&$this, 'flush_widget_cache'));
    }

    function widget($args, $instance) {
        $cache = wp_cache_get('widget_better_recent_posts', 'widget');

        if (!is_array($cache))
            $cache = array();

        if (isset($cache[$args['widget_id']])) {
            echo $cache[$args['widget_id']];
            return;
        }

        ob_start();
        extract($args);

        $title = apply_filters('widget_title', empty($instance['title']) ? __('Articles récents', 'eld') : $instance['title'], $instance, $this->id_base);
        
        $number = absint($instance['number']) ? absint($instance['number']) : 10 ;
        $display_date = $instance['display_date'] ? true : false;
        $display_excerpt = $instance['display_excerpt'] ? true : false;
        $display_featureimg = $instance['display_featureimg'] ? true : false;

        $query = array('posts_per_page' => $number, 'no_found_rows' => true, 'post_status' => 'publish', 'ignore_sticky_posts' => true, 'suppress_filters' => false);

        $post_type = $instance['post_type'];
        if (post_type_exists($post_type)) {
            $query['post_type'] = $post_type;
        }
        if (isset($instance['taxonomy'], $instance['term']) && !empty($instance['taxonomy']) && !empty($instance['term'])) {

                $base_terms = explode(',', $instance['term']);
                $terms = array();
                foreach ($base_terms as $term) {
                    $terms[] = apply_filters('wpml_object_id',$term,$instance['taxonomy'],true);
            }
                $instance['term'] = $terms;

            $query['tax_query'] = array(array('taxonomy' => $instance['taxonomy'], 'field' => 'id', 'terms' => $instance['term']));
        }

        $pattern = apply_filters('bw_recent_post_pattern',
                  '%post_title%'
                . '%post_thumbnail%'
                . '<p>%post_date%%post_excerpt%<p>', $instance, $args);

        $r = new WP_Query($query);

        if ($r->have_posts()) :
            ?>
            <?php echo $before_widget; ?>
            <?php if ($title) echo $before_title . $title . $after_title; ?>
            <ul>
                <?php
                while ($r->have_posts()) : $r->the_post();
                
                    $search_n_replace = array(
                        '%post_url%' => get_the_permalink(),
                        '%post_title%' => '<a href="'.get_the_permalink().'" title="'.esc_attr( get_the_title() ).'" class="recent-post-title">' . get_the_title() . '</a>',
                        '%post_title_attr%' => esc_attr( get_the_title() ),
                        '%post_thumbnail%' => '',
                        '%post_date%' => '',
                        '%post_excerpt%' => ''
                    );
                    
                    if ($display_featureimg){
                        $search_n_replace['%post_thumbnail%'] = '<a class="recent-post-thumbnail" href="'.get_the_permalink().'" title="'.esc_attr( get_the_title() ).'">'.get_the_post_thumbnail(null, 'bw_featureimg_size').'</a>';
                    }
                    if ($display_date){
                        $search_n_replace['%post_date%'] = '<time datetime="' . get_the_date('c') . '" class="recent-post-date">' . get_the_date() . '</time> — ';
                    }
                    if ($display_excerpt){
                        $search_n_replace['%post_excerpt%'] = get_the_excerpt();
                    }
                    
                    $search_n_replace = apply_filters('bw_recent_post_search_n_replace',$search_n_replace,$r,$instance,$args);
                    
                    ?>
                    <li <?php post_class('recent-post-item') ?> >
                        <?php echo str_replace( array_keys($search_n_replace), array_values($search_n_replace), $pattern)  ?>
                    </li>
            <?php endwhile; ?>
            </ul>
            <?php echo $after_widget; ?>
            <?php
            // Reset the global $the_post as this query will have stomped on it
            wp_reset_postdata();

        endif;

        $cache[$args['widget_id']] = ob_get_flush();
        wp_cache_set('widget_better_recent_posts', $cache, 'widget');
    }

    function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['number'] = (int) $new_instance['number'];
        $instance['display_date'] = !empty($new_instance['display_date']) ? 1 : 0;
        $instance['display_excerpt'] = !empty($new_instance['display_excerpt']) ? 1 : 0;
        $instance['display_featureimg'] = !empty($new_instance['display_featureimg']) ? 1 : 0;
        $instance['post_type'] = strip_tags($new_instance['post_type']);
        $instance['taxonomy'] = strip_tags($new_instance['taxonomy']);
        $instance['term'] = ( preg_match('/(\d+)(,\s*\d+)*/', $new_instance['term']) ) ? $new_instance['term'] : '';

        $this->flush_widget_cache();

        $alloptions = wp_cache_get('alloptions', 'options');
        if (isset($alloptions['widget_better_recent_posts']))
            delete_option('widget_better_recent_posts');

        return $instance;
    }

    function flush_widget_cache() {
        wp_cache_delete('widget_better_recent_posts', 'widget');
    }

    function form($instance) {
        $title = isset($instance['title']) ? esc_attr($instance['title']) : '';
        $number = isset($instance['number']) ? absint($instance['number']) : 5;
        $display_date = isset($instance['display_date']) ? (bool) $instance['display_date'] : false;
        $display_excerpt = isset($instance['display_excerpt']) ? (bool) $instance['display_excerpt'] : false;
        $display_featureimg = isset($instance['display_featureimg']) ? (bool) $instance['display_featureimg'] : false;
        $post_type = isset($instance['post_type']) ? esc_attr($instance['post_type']) : '';
        $taxonomy = isset($instance['taxonomy']) ? esc_attr($instance['taxonomy']) : '';
        $term = isset($instance['term']) ? esc_attr($instance['term']) : '';

        $available_post_types = get_post_types(array('public' => true), 'objects');
        $available_taxonomies = get_taxonomies(array('public' => true, 'object_type' => array($post_type)), 'objects');
        $available_terms = get_terms($taxonomy);
        ?>
        <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Titre:', 'eld'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>

        <p><label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Nombre d’éléments à afficher:', 'eld'); ?></label>
            <input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>

        <p><input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('display_date'); ?>" name="<?php echo $this->get_field_name('display_date'); ?>"<?php checked($display_date); ?> /> <label for="<?php echo $this->get_field_id('display_date'); ?>"><?php _e('Afficher la date', 'eld'); ?></label></p>
        <p><input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('display_excerpt'); ?>" name="<?php echo $this->get_field_name('display_excerpt'); ?>"<?php checked($display_excerpt); ?> /> <label for="<?php echo $this->get_field_id('display_excerpt'); ?>"><?php _e('Afficher l’extrait', 'eld'); ?></label></p>
        <p><input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('display_featureimg'); ?>" name="<?php echo $this->get_field_name('display_featureimg'); ?>"<?php checked($display_featureimg); ?> /> <label for="<?php echo $this->get_field_id('display_featureimg'); ?>"><?php _e('Afficher l’image à la une', 'eld'); ?></label></p>

        <p>
            <label for="<?php echo $this->get_field_id('post_type'); ?>"><?php _e('Type de contenus:', 'eld'); ?></label>
            <select id="<?php echo $this->get_field_id('post_type'); ?>" name="<?php echo $this->get_field_name('post_type'); ?>">
                <option value="-1" >---</option>
        <?php foreach ($available_post_types as $pt => $pt_object): ?>
                    <option value="<?php echo $pt; ?>" <?php selected($pt, $post_type); ?> ><?php echo $pt_object->label ?></option>
        <?php endforeach; ?>
            </select>
        </p>
        <?php if (!empty($available_taxonomies)): ?>
            <p>
                <label for="<?php echo $this->get_field_id('taxonomy'); ?>"><?php _e('Restreindre à une taxonomie:', 'eld'); ?></label>
                <select id="<?php echo $this->get_field_id('taxonomy'); ?>" name="<?php echo $this->get_field_name('taxonomy'); ?>">
                    <option value="-1" >---</option>
            <?php foreach ($available_taxonomies as $tax => $tax_object): ?>
                        <option value="<?php echo $tax ?>" <?php selected($tax, $taxonomy); ?> ><?php echo $tax_object->label ?></option>
            <?php endforeach; ?>
                </select>
            </p>
        <?php
        endif;
        if (!is_wp_error($available_terms)):
            ?>
            <p>
                <label for="<?php echo $this->get_field_id('term'); ?>"><?php _e('Choisir le terme:', 'eld'); ?></label>
            <?php wp_dropdown_categories('show_option_none=---&name=' . $this->get_field_name('term') . '&id=' . $this->get_field_id('term') . '&selected=' . $term . '&taxonomy=' . $taxonomy) ?>
            </p>
        <?php endif; ?>
        <?php
    }

}

/**
 *  Recent posts widget
 * 
 * @since 1.0.10
 */
class Better_Widget_Related_Posts extends WP_Widget {

    function __construct() {
        $widget_ops = array(
            'classname' => 'widget_better_related_posts',
            'description' => __('Les contenus similaires à l’article actuellement affiché.', 'eld')
        );
        parent::__construct('better-related-posts', __('Articles similaires', 'eld'), $widget_ops);
        $this->alt_option_name = 'widget_better_related_posts';

        add_action('save_post', array(&$this, 'flush_widget_cache'));
        add_action('deleted_post', array(&$this, 'flush_widget_cache'));
        add_action('switch_theme', array(&$this, 'flush_widget_cache'));
    }

    function widget($args, $instance) {
        $cache = wp_cache_get('widget_better_related_posts', 'widget');

        if (!is_array($cache))
            $cache = array();

        if (isset($cache[$args['widget_id']])) {
            echo $cache[$args['widget_id']];
            return;
        }

        ob_start();
        extract($args);

        $title = apply_filters('widget_title', empty($instance['title']) ? __('Articles récents', 'eld') : $instance['title'], $instance, $this->id_base);
        
        $number = absint($instance['number']) ? absint($instance['number']) : 10 ;
        $display_date = $instance['display_date'] ? true : false;
        $display_excerpt = $instance['display_excerpt'] ? true : false;
        $display_featureimg = $instance['display_featureimg'] ? true : false;

        $taxonomy = taxonomy_exists($instance['taxonomy']) ? $instance['taxonomy'] : 'post_tag';
        
        $query = array(
            'posts_per_page' => $number, 
            'no_found_rows' => true, 
            'post_status' => 'publish', 
            'ignore_sticky_posts' => true, 
            'suppress_filters' => false
        );

        $pattern = apply_filters('bw_recent_post_pattern',
                  '%post_title%'
                . '%post_thumbnail%'
                . '<p>%post_date%%post_excerpt%<p>', $instance, $args);

        $r = ELD_Related_Query($query,$taxonomy);

        if ($r->have_posts()) :
            ?>
            <?php echo $before_widget; ?>
            <?php if ($title) echo $before_title . $title . $after_title; ?>
            <ul>
                <?php
                while ($r->have_posts()) : $r->the_post();
                
                    $search_n_replace = array(
                        '%post_url%' => get_the_permalink(),
                        '%post_title%' => '<a href="'.get_the_permalink().'" title="'.esc_attr( get_the_title() ).'" class="recent-post-title">' . get_the_title() . '</a>',
                        '%post_title_attr%' => esc_attr( get_the_title() ),
                        '%post_thumbnail%' => '',
                        '%post_date%' => '',
                        '%post_excerpt%' => ''
                    );
                    
                    if ($display_featureimg){
                        $search_n_replace['%post_thumbnail%'] = '<a class="recent-post-thumbnail" href="'.get_the_permalink().'" title="'.esc_attr( get_the_title() ).'">'.get_the_post_thumbnail(null, 'bw_featureimg_size').'</a>';
                    }
                    if ($display_date){
                        $search_n_replace['%post_date%'] = '<time datetime="' . get_the_date('c') . '" class="recent-post-date">' . get_the_date() . '</time> — ';
                    }
                    if ($display_excerpt){
                        $search_n_replace['%post_excerpt%'] = get_the_excerpt();
                    }
                    
                    $search_n_replace = apply_filters('bw_recent_post_search_n_replace',$search_n_replace,$r,$instance,$args);
                    
                    ?>
                    <li <?php post_class('related-post-item') ?> >
                        <?php echo str_replace( array_keys($search_n_replace), array_values($search_n_replace), $pattern)  ?>
                    </li>
            <?php endwhile; ?>
            </ul>
            <?php echo $after_widget; ?>
            <?php
            // Reset the global $the_post as this query will have stomped on it
            wp_reset_postdata();

        endif;

        $cache[$args['widget_id']] = ob_get_flush();
        wp_cache_set('widget_better_related_posts', $cache, 'widget');
    }

    function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['number'] = (int) $new_instance['number'];
        $instance['display_date'] = !empty($new_instance['display_date']) ? 1 : 0;
        $instance['display_excerpt'] = !empty($new_instance['display_excerpt']) ? 1 : 0;
        $instance['display_featureimg'] = !empty($new_instance['display_featureimg']) ? 1 : 0;
        
        $instance['taxonomy'] = taxonomy_exists( $new_instance['taxonomy'] ) ? $new_instance['taxonomy'] : 'post_tag';

        $this->flush_widget_cache();

        $alloptions = wp_cache_get('alloptions', 'options');
        if (isset($alloptions['widget_better_related_posts']))
            delete_option('widget_better_related_posts');

        return $instance;
    }

    function flush_widget_cache() {
        wp_cache_delete('widget_better_related_posts', 'widget');
    }

    function form($instance) {
        $title = isset($instance['title']) ? esc_attr($instance['title']) : '';
        $number = isset($instance['number']) ? absint($instance['number']) : 5;
        $display_date = isset($instance['display_date']) ? (bool) $instance['display_date'] : false;
        $display_excerpt = isset($instance['display_excerpt']) ? (bool) $instance['display_excerpt'] : false;
        $display_featureimg = isset($instance['display_featureimg']) ? (bool) $instance['display_featureimg'] : false;
        
        $taxonomy = isset($instance['taxonomy']) ? esc_attr($instance['taxonomy']) : 'post_tag';

        $available_taxonomies = get_taxonomies(array('public' => true, 'object_type' => array('post')), 'objects');
        
        ?>
        <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Titre:', 'eld'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>

        <p><label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Nombre d’éléments à afficher:', 'eld'); ?></label>
            <input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>

        <p><input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('display_date'); ?>" name="<?php echo $this->get_field_name('display_date'); ?>"<?php checked($display_date); ?> /> <label for="<?php echo $this->get_field_id('display_date'); ?>"><?php _e('Afficher la date', 'eld'); ?></label></p>
        <p><input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('display_excerpt'); ?>" name="<?php echo $this->get_field_name('display_excerpt'); ?>"<?php checked($display_excerpt); ?> /> <label for="<?php echo $this->get_field_id('display_excerpt'); ?>"><?php _e('Afficher l’extrait', 'eld'); ?></label></p>
        <p><input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('display_featureimg'); ?>" name="<?php echo $this->get_field_name('display_featureimg'); ?>"<?php checked($display_featureimg); ?> /> <label for="<?php echo $this->get_field_id('display_featureimg'); ?>"><?php _e('Afficher l’image à la une', 'eld'); ?></label></p>

        <?php if (!empty($available_taxonomies)): ?>
            <p>
                <label for="<?php echo $this->get_field_id('taxonomy'); ?>"><?php _e('Pertinence basé sur quelle taxonomie:', 'eld'); ?></label>
                <select id="<?php echo $this->get_field_id('taxonomy'); ?>" name="<?php echo $this->get_field_name('taxonomy'); ?>">
            <?php foreach ($available_taxonomies as $tax => $tax_object): ?>
                        <option value="<?php echo $tax ?>" <?php selected($tax, $taxonomy); ?> ><?php echo $tax_object->label ?></option>
            <?php endforeach; ?>
                </select>
            </p>
        <?php
        endif;
    }

}

/**
 * Categories widget class
 *
 * @since 1.0.0
 */
class Better_Widget_Categories extends WP_Widget {

    function __construct() {
        $widget_ops = array(
            'classname' => 'widget_better_categories', 
            'description' => __('Une liste ou un menu déroulant des termes d’une taxonomie.', 'eld'));
        parent::__construct('better-categories', __('Taxonomies', 'eld'), $widget_ops);
    }

    function widget($args, $instance) {
        extract($args);

        $title = apply_filters('widget_title', empty($instance['title']) ? __('Catégories', 'eld') : $instance['title'], $instance, $this->id_base);
        $c = (!empty($instance['count'])) ? true : false;
        $h = (!empty($instance['hierarchical'])) ? true : false;

        $l = (!empty($instance['link_to_all'])) ? true : false;

        $n = ( absint($instance['max_nb']) ) ? absint($instance['max_nb']) : null;

        switch ($instance['orderby']) {
            case 'name':
                $orderby = 'name';
                $order = 'ASC';
                break;
            case 'count':
                $orderby = 'count';
                $order = 'DESC';
                break;
            default:
                $orderby = 'ID';
                $order = 'DESC';
                break;
        }

        $taxonomy = (!empty($instance['taxonomy'])) ? $instance['taxonomy'] : 'category';

        echo $before_widget;
        if ($title)
            echo $before_title . $title . $after_title;

        $cat_args = array('orderby' => $orderby, 'order' => $order, 'show_count' => $c, 'hierarchical' => $h, 'taxonomy' => $taxonomy, 'number' => $n);
        ?>
        <ul>
        <?php
        $cat_args['title_li'] = '';
        wp_list_categories(apply_filters('widget_categories_args', $cat_args));
        ?>
        </ul>
        <?php
        if ($l):
            $taxonomy = get_taxonomy($taxonomy);
            $url = get_bloginfo('url') . '/' . $taxonomy->rewrite['slug'] . '/';
            ?>
            <p><a href="<?php echo $url ?>"><?php printf(__('Voir %s', 'eld'), strtolower($taxonomy->labels->all_items)); ?></a></p>
            <?php
        endif;
        echo $after_widget;
    }

    function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $instance['title'] = strip_tags($new_instance['title']);
        $instance['count'] = !empty($new_instance['count']) ? 1 : 0;
        $instance['hierarchical'] = !empty($new_instance['hierarchical']) ? 1 : 0;
        $instance['link_to_all'] = !empty($new_instance['link_to_all']) ? 1 : 0;

        $instance['max_nb'] = ( absint($new_instance['max_nb']) ) ? absint($new_instance['max_nb']) : null;
        $instance['orderby'] = ( in_array($new_instance['orderby'], array('name', 'count', 'ID')) ) ? $new_instance['orderby'] : 'ID';


        $instance['taxonomy'] = !empty($new_instance['taxonomy']) ? $new_instance['taxonomy'] : 'category';

        return $instance;
    }

    function form($instance) {
        //Defaults
        $instance = wp_parse_args((array) $instance, array('title' => ''));
        $title = esc_attr($instance['title']);
        $count = isset($instance['count']) ? (bool) $instance['count'] : false;
        $hierarchical = isset($instance['hierarchical']) ? (bool) $instance['hierarchical'] : false;
        $link_to_all = isset($instance['link_to_all']) ? $instance['link_to_all'] : false;
        $max_nb = isset($instance['max_nb']) ? $instance['max_nb'] : false;
        $orderby = isset($instance['orderby']) ? $instance['orderby'] : 'ID';

        $taxonomy = isset($instance['taxonomy']) ? $instance['taxonomy'] : 'category';

        $available_taxonomies = get_taxonomies(array('public' => true), 'objects');
        ?>
        <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Titre:', 'eld'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>

        <input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('count'); ?>" name="<?php echo $this->get_field_name('count'); ?>"<?php checked($count); ?> />
        <label for="<?php echo $this->get_field_id('count'); ?>"><?php _e('Afficher le nombre de termes', 'eld'); ?></label><br />

        <input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('hierarchical'); ?>" name="<?php echo $this->get_field_name('hierarchical'); ?>"<?php checked($hierarchical); ?> />
        <label for="<?php echo $this->get_field_id('hierarchical'); ?>"><?php _e('Afficher la hierarchie', 'eld'); ?></label></p>

        <p>
            <label for="<?php echo $this->get_field_id('max_nb'); ?>"><?php _e('Nombre de termes:', 'eld'); ?></label>
            <input id="<?php echo $this->get_field_id('max_nb'); ?>" name="<?php echo $this->get_field_name('max_nb'); ?>" type="text" size="2" value="<?php echo $max_nb; ?>" /><br />

            <label for="<?php echo $this->get_field_id('orderby'); ?>"><?php _e('Classé par:', 'eld'); ?></label>
            <select id="<?php echo $this->get_field_id('orderby'); ?>" name="<?php echo $this->get_field_name('orderby'); ?>" >
                <option value="ID" <?php selected('ID', $orderby) ?>><?php _e('ID du terme', 'eld') ?></option>
                <option value="name" <?php selected('name', $orderby) ?>><?php _e('Nom', 'eld') ?></option>
                <option value="count" <?php selected('count', $orderby) ?>><?php _e('Nombre', 'eld') ?></option>
            </select>
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('taxonomy'); ?>"><?php _e('Choisir la taxonomie', 'eld'); ?></label>
            <select id="<?php echo $this->get_field_id('taxonomy'); ?>" name="<?php echo $this->get_field_name('taxonomy'); ?>">
        <?php foreach ($available_taxonomies as $tax => $tax_object): ?>
                    <option value="<?php echo $tax ?>" <?php selected($tax, $taxonomy); ?> ><?php echo $tax_object->label ?></option>
        <?php endforeach; ?>
            </select>
        </p>

        <?php
    }

}

/**
 * Search widget class
 *
 * @since 1.0.0
 */
class Better_Widget_Search extends WP_Widget {

    function __construct() {
        $widget_ops = array('classname' => 'widget_search', 'description' => __("Un widget de recherche", 'eld'));
        parent::__construct('better-search', __('Recherche', 'eld'), $widget_ops);
    }

    function widget($args, $instance) {
        extract($args);
        $title = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title'], $instance, $this->id_base);
        $post_type = $instance['post_type'];

        echo $before_widget;
        if ($title)
            echo $before_title . $title . $after_title;
        $id = $this->id;
        ?><form class="search-form" role="search" method="get" id="form-<?php echo $id ?>" action="<?php echo home_url('/') ?>" ><?php
        ?><label class="screen-reader-text" for="s-<?php echo $id ?>"><?php _e('Recherche:', 'eld') ?></label><?php
        ?><input type="search" value="<?php echo get_search_query() ?>" name="s" id="s-<?php echo $id ?>" /><?php
                    if (count($post_type) > 1):
                        ?><span class="search_post_type_intro"><?php _ex('dans','Recherche... dans', 'eld'); ?></span><span class="search_post_type_list"><?php
                        foreach ($post_type as $pt):
                            if ($pt == 'any'):
                                ?><label class="search_post_type_item" for="post_type_any-<?php echo $id ?>"><input type="radio" id="post_type_any-<?php echo $id ?>" name="post_type" value="any" /> <?php _e('Tout le site', 'eld'); ?></label> <?php
                                else:
                                    $pt_object = get_post_type_object($pt);
                                    ?><label class="search_post_type_item" for="post_type_<?php echo $pt ?>-<?php echo $id ?>"><input type="radio" id="post_type_<?php echo $pt ?>-<?php echo $id ?>" name="post_type" value="<?php echo $pt ?>" /> <?php echo $pt_object->labels->name; ?></label> <?php
                            endif;
                        endforeach;
                        ?></span><?php
                elseif (count($post_type) == 1 && $post_type[0] !== 'any') :
                    ?><input type="hidden" value="<?php echo $post_type[0] ?>" name="post_type" id="post_type-<?php echo $id ?>" /><?php
        endif;
        ?><button type="submit" id="submit-<?php echo $id ?>" ><?php _e('Chercher', 'eld') ?></button><?php
        ?></form><?php
        echo $after_widget;
    }

    function form($instance) {
        $instance = wp_parse_args((array) $instance, array('title' => ''));
        $title = $instance['title'];
        $post_type = isset($instance['post_type']) ? $instance['post_type'] : array('any');

        $available_post_types = get_post_types(array('public' => true, 'exclude_from_search' => false), 'objects');
        ?>
        <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Titre:', 'eld'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></label></p>
        <p>
                <?php _e('Chercher dans quel type de contenu:', 'eld'); ?><br />
            <label for="<?php echo $this->get_field_id('post_type'); ?>_any"><input type="checkbox" id="<?php echo $this->get_field_id('post_type'); ?>_any" name="<?php echo $this->get_field_name('post_type'); ?>[]" value="any" <?php checked(in_array('any', $post_type)); ?> ><?php _e('Tout', 'eld') ?></label><br />
        <?php foreach ($available_post_types as $pt => $pt_object): ?>
                <label for="<?php echo $this->get_field_id('post_type'); ?>_<?php echo $pt; ?>"><input type="checkbox" id="<?php echo $this->get_field_id('post_type'); ?>_<?php echo $pt; ?>" name="<?php echo $this->get_field_name('post_type'); ?>[]" value="<?php echo $pt; ?>" <?php checked(in_array($pt, $post_type)); ?> ><?php echo $pt_object->label ?></label><br />
        <?php endforeach; ?>
        </p>

        <?php
    }

    function update($new_instance, $old_instance) {
        $instance = $old_instance;
        $new_instance = wp_parse_args((array) $new_instance, array('title' => ''));
        $instance['title'] = strip_tags($new_instance['title']);

        $instance['post_type'] = array();

        if (is_array($new_instance['post_type']) && !empty($new_instance['post_type'])) {
            foreach ($new_instance['post_type'] as $pt) {
                if (post_type_exists($pt)) {
                    $instance['post_type'][] = $pt;
                } elseif ($pt == 'any') {
                    $instance['post_type'][] = 'any';
                }
            }

            $instance['post_type'] = $new_instance['post_type'];
        } else {
            $instance['post_type'][] = 'any';
        }

        return $instance;
    }

}


/**
 * Preformated widget with a text followed by a link
 * 
 * @since 1.0.0
 */
class Better_Widget_TextNLink extends WP_Widget {

	function __construct() {
		$widget_ops = array('classname' => 'widget_textnlink', 'description' => __( 'Un texte, suivi d’un lien.','eld') );
		parent::__construct('widget-textnlink', __('Texte+lien','eld'), $widget_ops, array('width'=>400,'height'=>350) );
		$this->alt_option_name = 'widget_textnlink';

		add_action( 'save_post', array(&$this, 'flush_widget_cache') );
		add_action( 'deleted_post', array(&$this, 'flush_widget_cache') );
		add_action( 'switch_theme', array(&$this, 'flush_widget_cache') );
	}

	function widget($args, $instance) {
		$cache = wp_cache_get('widget_textnlink', 'widget');

		if ( !is_array($cache) )
			$cache = array();

		if ( isset($cache[$args['widget_id']]) ) {
			echo $cache[$args['widget_id']];
			return;
		}
		$wid = $this->id; //widget ID
		ob_start();
		extract($args);

		$title = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title'], $instance, $this->id_base);
		$box_id = $instance['box_id'];
		$intro = $instance['intro'];
		$link_text = $instance['link_text'];
		$url = $instance['url'];
		
		/* get translations */
		if (function_exists('icl_t')){
			$intro = icl_t('Widgets','Intro '.$wid,$intro);
			$link_text = icl_t('Widgets','Lien '.$wid,$link_text);
			$url = icl_t('Widgets','Url '.$wid,$url);
		}

		/* Before widget (defined by themes). */
		echo $before_widget;
		
		/* Display the widget title if one was input (before and after defined by themes). */
		if ( $title )
			echo $before_title . $title . $after_title;
		?>
		<div class="<?php echo $box_id; ?>">
			<div class="wtnl_intro">
				<?php echo $intro; ?>
			</div>
			<div class="wtnl_link">
				<a href="<?php echo $url; ?>">
					<?php echo $link_text; ?>
				</a>
			</div>
		</div>
		<?php
		/* After widget (defined by themes). */
		echo $after_widget;

		$cache[$args['widget_id']] = ob_get_flush();
		wp_cache_set('widget_textnlink', $cache, 'widget');
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['box_id'] = strip_tags($new_instance['box_id']);
		$instance['intro'] = wp_kses_data($new_instance['intro']);
		$instance['link_text'] = wp_kses_data($new_instance['link_text']);
		$instance['url'] = esc_url_raw($new_instance['url'],'http');

		/* register strings with WPML */
		if(function_exists('icl_register_string')){
			$wid = $this->id; //widget ID
			icl_register_string('Widgets','Intro '.$wid,$instance['intro']);
			icl_register_string('Widgets','Link '.$wid,$instance['link_text']);
			icl_register_string('Widgets','Url '.$wid,$instance['url']);
		}

		$this->flush_widget_cache();

		$alloptions = wp_cache_get( 'alloptions', 'options' );
		if ( isset($alloptions['widget_textnlink']) )
			delete_option('widget_textnlink');

		return $instance;
	}

	function flush_widget_cache() {
		wp_cache_delete('widget_textnlink', 'widget');
	}

	function form( $instance ) {
		$title = isset($instance['title']) ? esc_attr($instance['title']) : '';
		$box_id = isset($instance['box_id']) ? esc_attr($instance['box_id']) : '';
		$intro = isset($instance['intro']) ? esc_attr($instance['intro']) : '';
		$link_text = isset($instance['link_text']) ? esc_attr($instance['link_text']) : '';
		$url = isset($instance['url']) ? esc_attr($instance['url']): '';
?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Titre','eld'); ?>:</label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
		</p>
		<!-- Widget Box ID: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'box_id' ); ?>"><?php _e( 'Classe de la boite','eld'); ?>:</label>
			<input id="<?php echo $this->get_field_id( 'box_id' ); ?>" name="<?php echo $this->get_field_name( 'box_id' ); ?>" value="<?php echo $box_id; ?>" />
		</p>
		<!-- Widget Intro: Textarea -->
		<p>
			<label for="<?php echo $this->get_field_id( 'intro' ); ?>"><?php _e( 'Le texte','eld'); ?>:</label><br />
			<textarea class="widefat" rows="16" cols="20" id="<?php echo $this->get_field_id( 'intro' ); ?>" name="<?php echo $this->get_field_name( 'intro' ); ?>" ><?php echo $intro; ?></textarea>
		</p>
		<!-- Widget Link text: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'link_text' ); ?>"><?php _e( 'Le texte du lien','eld'); ?>:</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'link_text' ); ?>" name="<?php echo $this->get_field_name( 'link_text' ); ?>" value="<?php echo $link_text; ?>" />
		</p>
		<!-- Widget Url: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'url' ); ?>"><?php _e( 'L’URL du lien','eld'); ?>:</label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'url' ); ?>" name="<?php echo $this->get_field_name( 'url' ); ?>" value="<?php echo $url; ?>" />
		</p>
<?php
	}
}

/**
 * Preformated widget with a clickable image in the background of some text
 * 
 * @since 1.0.0
 */
class Better_Widget_Button extends WP_Widget {

	function __construct() {
		$widget_ops = array('classname' => 'widget_button', 'description' => __( 'Image clicable, survolé d’une zone de texte.','eld') );
		parent::__construct('widget-button', __('Bouton image','eld'), $widget_ops, array('width'=>400,'height'=>350) );
		$this->alt_option_name = 'widget_button';

		add_action( 'save_post', array(&$this, 'flush_widget_cache') );
		add_action( 'deleted_post', array(&$this, 'flush_widget_cache') );
		add_action( 'switch_theme', array(&$this, 'flush_widget_cache') );
	}

	function widget($args, $instance) {
		$cache = wp_cache_get('widget_button', 'widget');

		if ( !is_array($cache) )
			$cache = array();

		if ( isset($cache[$args['widget_id']]) ) {
			echo $cache[$args['widget_id']];
			return;
		}
		$wid = $this->id; //widget ID

		ob_start();
		extract($args);

		$title = apply_filters('widget_title', empty($instance['title']) ? '' : $instance['title'], $instance, $this->id_base);
		$box_id = $instance['box_id'];
		$text = $instance['text'];
		$image = $instance['image'];
		$url = $instance['url'];

		$paddingTop = $instance['paddingTop'];
		$paddingLeft = $instance['paddingLeft'];
		$paddingRight = $instance['paddingRight'];
		$color = (!empty($instance['color'])) ? 'color:#'.$instance['color'].';' : '';
		$display_shadow = $instance['display_shadow'] ? true : false;
		
		
		/* get translations */
		if (function_exists('icl_t')){
			$text = icl_t('Widgets','Text '.$wid,$text);
			$url = icl_t('Widgets','Url '.$wid,$url);
		}
		
		if (is_numeric($url)){
			$url = get_permalink($url);
		}
		
		// the image
		$image_data = wp_get_attachment_image_src($image,'bw_button_size');
		if ( !$image_data )
			return;
		
		$backgroundImage = $image_data[0];
		$w = $image_data[1];
		$h = $image_data[2];
		$style = 'background-image:url('.$backgroundImage.');width:'.$w.'px;height:'.$h.'px;display:inline-block;';
		
		/* Before widget (defined by themes). */
		echo $before_widget;
		
		/* Display the widget title if one was input (before and after defined by themes). */
		if ( $title )
			echo $before_title . $title . $after_title;
		?>
		<div class="<?php echo $box_id; ?>">
			<a class="wb_link" href="<?php echo $url; ?>" style="<?php echo $style; ?>">
				<span class="wb_text<?php if ($display_shadow) echo ' wb_shadow'; ?>" style="padding:<?php echo $paddingTop.'px '.$paddingRight.'px 0 '.$paddingLeft.'px;'.$color ?>"><?php echo $text; ?></span>
			</a>
		</div>
		<?php
		/* After widget (defined by themes). */
		echo $after_widget;

		$cache[$args['widget_id']] = ob_get_flush();
		wp_cache_set('widget_button', $cache, 'widget');
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['box_id'] = strip_tags($new_instance['box_id']);
		$instance['text'] = wp_kses_data($new_instance['text']);
		$instance['image'] = absint($new_instance['image']);
		if ( is_numeric( $new_instance['url'] ) ){
			$instance['url'] = absint($new_instance['url']);
		}else{
			$instance['url'] = esc_url_raw($new_instance['url'],'http');
		}
		$instance['paddingTop'] = absint($new_instance['paddingTop']);
		$instance['paddingLeft'] = absint($new_instance['paddingLeft']);
		$instance['paddingRight'] = absint($new_instance['paddingRight']);

		$instance['color'] = (preg_match('/^([A-Fa-f0-9]{6}|[A-Fa-f0-9]{3})$/', $new_instance['color'])) ? $new_instance['color'] : '';
		$instance['display_shadow'] = !empty($new_instance['display_shadow']) ? 1 : 0;


		/* register strings with WPML */
		if(function_exists('icl_register_string')){
			$wid = $this->id; //widget ID
			wpml_register_string('Widgets','Text '.$wid,$instance['text']);
			wpml_register_string('Widgets','Url '.$wid,$instance['url']);
		}

		$this->flush_widget_cache();

		$alloptions = wp_cache_get( 'alloptions', 'options' );
		if ( isset($alloptions['widget_button']) )
			delete_option('widget_button');

		return $instance;
	}

	function flush_widget_cache() {
		wp_cache_delete('widget_button', 'widget');
	}

	function form( $instance ) {
		$title = isset($instance['title']) ? esc_attr($instance['title']) : '';
		$box_id = isset($instance['box_id']) ? esc_attr($instance['box_id']) : '';
		$text = isset($instance['text']) ? esc_attr($instance['text']) : '';
		$image = isset($instance['image']) ? absint($instance['image']) : '';
		$url = isset($instance['url']) ? esc_attr($instance['url']): '';

		$paddingTop = isset($instance['paddingTop']) ? absint($instance['paddingTop']) : '0';
		$paddingLeft = isset($instance['paddingLeft']) ? absint($instance['paddingLeft']) : '0';
		$paddingRight = isset($instance['paddingRight']) ? absint($instance['paddingRight']) : '0';
		$color = isset($instance['color']) ? esc_attr($instance['color']) : '';
		$display_shadow = isset($instance['display_shadow']) ? (bool) $instance['display_shadow'] :false;

?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Titre:','eld'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" />
		</p>
		<!-- Widget Box ID: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'box_id' ); ?>"><?php _e( 'Classe de la boite','eld'); ?></label>
			<input id="<?php echo $this->get_field_id( 'box_id' ); ?>" name="<?php echo $this->get_field_name( 'box_id' ); ?>" value="<?php echo $box_id; ?>" />
		</p>
		<!-- Widget text: Textarea -->
		<p>
			<label for="<?php echo $this->get_field_id( 'text' ); ?>"><?php _e( 'Le texte','eld'); ?></label><br />
			<textarea class="widefat" rows="8" cols="20" id="<?php echo $this->get_field_id( 'text' ); ?>" name="<?php echo $this->get_field_name( 'text' ); ?>" ><?php echo $text; ?></textarea>
		</p>
		<!-- Widget Image: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'image' ); ?>"><?php _e( 'L’ID de l’image','eld'); ?></label>
			<input type="number" id="<?php echo $this->get_field_id( 'image' ); ?>" name="<?php echo $this->get_field_name( 'image' ); ?>" value="<?php echo $image; ?>" /> <b><?php _e('Requis.','eld'); ?></b><br />
			<em><?php _e('Les ID des images sont visibles dans la Liste des médias.','eld'); ?></em>
			
		</p>
		<!-- Widget Url: Text Input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'url' ); ?>"><?php _e( 'L’URL du lien','eld'); ?></label>
			<input class="widefat" id="<?php echo $this->get_field_id( 'url' ); ?>" name="<?php echo $this->get_field_name( 'url' ); ?>" value="<?php echo $url; ?>" /><br />
			<em><?php _e('Si l’URL est interne au site, n’écrire que le ID du contenu.','eld'); ?></em>
		</p>
		<p> <?php _e('Espace','eld') ?><br />
			<label for="<?php echo $this->get_field_id( 'paddingTop' ); ?>"><?php _e( 'Top','eld'); ?></label>
			<input type="number" id="<?php echo $this->get_field_id( 'paddingTop' ); ?>" name="<?php echo $this->get_field_name( 'paddingTop' ); ?>" value="<?php echo $paddingTop; ?>" size="2" maxlength="2" />px
			<label for="<?php echo $this->get_field_id( 'paddingLeft' ); ?>"><?php _e( 'Left','eld'); ?></label>
			<input type="number" id="<?php echo $this->get_field_id( 'paddingLeft' ); ?>" name="<?php echo $this->get_field_name( 'paddingLeft' ); ?>" value="<?php echo $paddingLeft; ?>"  size="2" maxlength="2"/>px
			<label for="<?php echo $this->get_field_id( 'paddingRight' ); ?>"><?php _e( 'Right','eld'); ?></label>
			<input type="number" id="<?php echo $this->get_field_id( 'paddingRight' ); ?>" name="<?php echo $this->get_field_name( 'paddingRight' ); ?>" value="<?php echo $paddingRight; ?>" size="2" maxlength="2" />px
		</p>
		<!-- Widget text color: Color input -->
		<p>
			<label for="<?php echo $this->get_field_id( 'color' ); ?>"><?php _e( 'Couleur du text','eld'); ?></label> 
			#<input type="color" id="<?php echo $this->get_field_id( 'color' ); ?>" name="<?php echo $this->get_field_name( 'color' ); ?>" value="<?php echo $color; ?>" size="6" maxlength="6" />
		</p>
		<p><input type="checkbox" class="checkbox" id="<?php echo $this->get_field_id('display_shadow'); ?>" name="<?php echo $this->get_field_name('display_shadow'); ?>"<?php checked( $display_shadow ); ?> /> <label for="<?php echo $this->get_field_id('display_shadow'); ?>"><?php _e( 'Ombre de texte?' ,'eld'); ?></label></p>

<?php
	}
}