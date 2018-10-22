<?php

/**
 * related-posts.php
 * 
 * 
 */
// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function ELD_Related_Query($raw_query = array(), $related_tax = 'post_tag') {
    global $post;

    $default_query = array(
        'post_type' => 'post',
        'posts_per_page' => 5,
        'ignore_sticky_posts' => true,
        'suppress_filter' => false
    );
    $query = wp_parse_args($raw_query, $default_query);

    $query['post__not_in'] = isset($query['post__not_in']) || !empty($query['post__not_in']) ? $query['post__not_in'] : array();
    array_unshift($query['post__not_in'], $post->ID);

    $terms = wp_get_post_terms($post->ID, $related_tax, array('fields' => 'names', 'orderby' => 'count', 'order' => 'DESC'));

    if (empty($terms)) {
        return new WP_Query($query);
    }


    if (function_exists('relevanssi_do_query')) {
        $query['s'] = isset($query['s']) ? $query['s'] : '';
        $query['s'] .= implode(' ', $terms);
    } else {
        $query['tax_query'] = array(
            array(
                'taxonomy' => $related_tax,
                'field' => 'name',
                'terms' => $terms
            )
        );
    }

    $new_query = new WP_Query($query);

    if (function_exists('relevanssi_do_query')) {
        relevanssi_do_query($new_query);
    }

    return $new_query;
}
