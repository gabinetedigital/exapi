<?php /* -*- Mode: php; c-basic-offset:4; -*- */
/* Copyright (C) 2011  Lincoln de Sousa <lincoln@comum.org>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */

/* This module holds all functions that exposes post attributes and post
 * related information. This is an attempt to improve the modularization
 * of this plugin.
 */


/**
 * Returns date and date in GMT format attributes from a post
 *
 * @since 0.1.1
 *
 * @param array $post is the post array returned by the wordpress
 * function `wp_get_recent_posts()'.
 */
function exapi_post_date($post) {
    $date = mysql2date('Ymd\TH:i:s', $post['post_date'], false);
    $date_gmt = mysql2date('Ymd\TH:i:s', $post['post_date_gmt'], false);
    return array(
        'date' => new IXR_Date($date),
        'date_gmt' => new IXR_Date($date_gmt)
    );
}


/**
 * Returns some attributes about the user who created a post
 *
 * @since 0.1.1
 *
 * @param array $post is a post returned by wp_get_recent_posts
 */
function exapi_post_author($post) {
    $data = get_userdata($post['post_author']);
    $author = array();
    $author['id'] = $post['post_author'];
    $author['username'] = $data->user_login;
    $author['display_name'] = $data->display_name;
    return $author;
}


/**
 * Returns the list of categories of a given post
 *
 * @since 0.1.1
 *
 * @param array $post is a post returned by wp_get_recent_posts()
 */
function exapi_post_categories($post) {
    $categories = array();
    $catids = wp_get_post_categories($post['ID']);
    foreach ($catids as $catid) {
        $cat = get_category($catid);
        $categories[] = array(
            'id' => $catid,
            'name' => $cat->name,
            'slug' => $cat->slug
        );
    }
    return $categories;
}


/**
 * Returns the list of tags of a given post
 *
 * @since 0.1.1
 *
 * @param array $post is a post returned by wp_get_recent_posts()
 */
function exapi_post_tags($post) {
    $tagnames = array();
    $tags = wp_get_post_tags($post['ID']);
    foreach ($tags as $tag)
        $tagnames[] = $tag->name;
    return implode(', ', $tagnames);
}


/**
 * Returns basic information about comments in a given post
 *
 * @since 0.1.1
 *
 * @param array $post is a post returned by wp_get_recent_posts()
 */
function exapi_post_comment_info($post) {
    return array(
        'count' => (int) get_comments_number($post['ID']),
        'allow_comments' => ($post['comment_status'] === 'open') ? 1 : 0,
        'allow_pings' => ($post['ping_status'] === 'open') ? 1 : 0,
    );
}


/**
 * Returns information about a specific thumbnail of a post with its
 * size and url
 *
 * @since 0.1.1
 *
 * @param array $post is a post returned by wp_get_recent_posts
 * @param string $size is the name of the size of the thumbnail, the
 *  default value is `full'.
 */
function exapi_post_thumb($post, $params) {
    $thumbs = array();

    // No thumbnails requested, let's get out
    if (!array_key_exists('thumbsizes', $params)) {
        return $thumbs;
    }

    // ok, let's handle the thumbnail request, but only if there's
    // something to handle, of course.
    $pid = $post['ID'];
    if (has_post_thumbnail($pid)) {
        foreach ($params['thumbsizes'] as $size) {
            $tid = get_post_thumbnail_id($pid);
            $info = wp_get_attachment_image_src($tid, $size);
            $thumbs[$size] = array();
            $thumbs[$size]['url'] = $info[0];
            $thumbs[$size]['width'] = $info[1];
            $thumbs[$size]['height'] = $info[2];
        }
    }
    return $thumbs;
}


/**
 * Returns the filtered content of a post
 *
 * @since 0.1.1
 *
 * @param array $post is a post returned by wp_get_recent_posts
 */
function exapi_post_content($post) {
    $content = $post['post_content'];
    $content = apply_filters('the_content', $content);
    return str_replace(']]>', ']]&gt;', $content);
}


/**
 * Returns the filtered excerpt of a post
 *
 * @since 0.1.1
 *
 * @param array $post is a post returned by wp_get_recent_posts
 */
function exapi_post_excerpt($post) {
    $excerpt = $post['post_excerpt'];
    if (empty($excerpt)) {
        $content = strip_tags(exapi_post_content($post));
        $divided = explode(' ', $content);
        $sliced = array_slice($divided, 0, 40);
        $excerpt = implode(' ', $sliced);
        if (sizeof($sliced) < sizeof($divided)) {
            $excerpt .= ' (...)';
        }
    }
    return $excerpt;
}
