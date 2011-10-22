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


/*
Plugin Name: exapi
Plugin URI: http://trac.gabinetedigital.rs.gov.br
Description: Extended XMLRPC API for wordpress
Version: 0.1.0
Author: Lincoln de Sousa <lincoln@gg.rs.gov.br>
Author URI: http://gabinetedigital.rs.gov.br
License: AGPL3
*/


include('exapi.post.php');


/**
 * Extract query params from an associative array to be passed to the
 * wordpress API function `wp_get_recent_posts()'
 *
 * @param array $args Search parameters to be extracted and handled
 * @return array
 */
function _exapi_extract_query_params( $args ) {
    $query = array ( );
    foreach ( $args as $key => $val ) {
        switch ( $key ) {
        case 'numposts':
            $query['numposts'] = (int) $val;
            break;
        case 'category':
            $query['category'] = (int) $val;
            break;
        case 'category_name':
            $catObject = get_category_by_slug( $val );
            $query['category'] = $catObject->term_id;;
            break;
        default:
            $query[$key] = $val;
        }
    }
    return $query;
}


/**
 *
 */
function _exapi_extract_params($args) {
}


/**
 * Retrieve a list with the most recent posts on the blog.
 *
 * The difference between this method and the blogger or metaWeblog API
 * is that we allow the API caller to pass some more query
 * parameters. For example, the category that the recent posts should
 * have.
 *
 * @since 0.1.1
 *
 * @param array $args Method parameters
 * @return array
 */
function exapi_getRecentPosts( $args ) {
    // We don't like smart-ass people
    global $wp_xmlrpc_server;
    $wp_xmlrpc_server->escape( $args );

    // Reading the attribute list
    $blog_ID = (int) $args[0];
    $username = $args[1];
    $password = $args[2];

    // This is a special one, here we find all parameters that will be
    // sent to the `query' param in wp_get_recent_posts()
    $query = isset( $args[3] ) ?
        _exapi_extract_query_params( $args[3] ) : array( );

    $params = array( 'thumbsize' => 'full' );

    // All methods in this API are being protected
    if ( !$user = wp_xmlrpc_server::login($username, $password) )
        return wp_xmlrpc_server::error;

    // Looking for the post list
    $posts_list = wp_get_recent_posts( $query );
    if ( !$posts_list )
        return array( );

    // Handling posts found
    $struct = array( );
    foreach ( $posts_list as $post ) {
        $pid = $post['ID'];
        $post_date = exapi_post_date($post);
        $struct[] = array(
            'postid' => (string) $pid,
            'title' => $post['post_title'],
            'slug' => $post['post_name'],
            'date' => $post_date,
            'link' => post_permalink($pid),
            'format' => (($f = get_post_format($post)) === '' ? 'standard' : $f),
            'author' => exapi_post_author($post),
            'categories' => exapi_post_categories($post),
            'tags' => exapi_post_tags($post),
            'comments' => exapi_post_comment_info($post),
            'thumbnail' => exapi_post_thumb($post, $params['thumbsize']),
            'excerpt' => exapi_post_excerpt($post),
            'content' => exapi_post_content($post),
            'post_status' => $post['post_status'],
            'custom_fields' => $wp_xmlrpc_server->get_custom_fields( $pid ),
        );
    }

    return $struct;
}


function exapi_register_methods( $methods ) {
    $methods['exapi.getRecentPosts'] = 'exapi_getRecentPosts';
    return $methods;
}
add_filter( 'xmlrpc_methods', 'exapi_register_methods' );

?>
