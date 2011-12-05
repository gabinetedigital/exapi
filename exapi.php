<?php /* -*- Mode: php; c-basic-offset:4; -*- */
/* Copyright (C) 2011  Governo do Estado do Rio Grande do Sul
 * Copyright (C) 2011  Lincoln de Sousa <lincoln@comum.org>
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
        case 'numberposts':
            $query['numberposts'] = (int) $val;
            break;
        case 'category':
            $query['category'] = (int) $val;
            break;
        case 'category_name':
            $catObject = get_category_by_slug( $val );
            $query['category'] = $catObject->term_id;;
            break;
        case 'page':
            $query['paged'] = (int) $val;
            break;
        default:
            $query[$key] = $val;
        }
    }
    return $query;
}


/**
 * Extracts parameters needed for the getRecentPosts() method
 *
 * @since 0.1.1
 *
 * @param array $args arguments received by the getRecentPosts that must
 *  be extracted from the array that will be passed to the function that
 *  extracts query parameters.
 */
function _exapi_extract_params(&$args) {
    $wanted = array('thumbsizes');
    $found = array();
    foreach ($wanted as $key) {
        if (array_key_exists($key, &$args)) {
            $found[$key] = $args[$key];
            unset($args[$key]);
        }
    }
    return $found;
}


/**
 * A shortcut to log the user in before making any `wp_' call and escape
 * all the received arguments of an exposed method.
 *
 * @since 0.1.2
 *
 * @params array $args Arguments that will be escaped with a wordpress
 *  xmlrpc utility
 */
function _exapi_method_header(&$args) {
    // We don't like smart-ass people
    global $wp_xmlrpc_server;
    $wp_xmlrpc_server->escape( $args );

    // Reading the attribute list
    $username = array_shift($args);
    $password = array_shift($args);

    // All methods in this API are being protected
    if (!$user = $wp_xmlrpc_server->login($username, $password))
        return $wp_xmlrpc_server->error;
    return $args;
}


/**
 * Aggregates informations that every post returned by this API should
 * contain.
 *
 * @since 0.1.3
 *
 * @param array $post Is the base information returned by the `wp_' API
 *  used to create a new structure that this function returns.
 *
 * @param array $params is an array of parameters that can be used to
 *  customize the return of this function. Refer to the code of the
 *  `_exapi_extract_params()' function to know which values you can pass
 *  here.
 */
function _exapi_prepare_post($post, $params) {
    global $wp_xmlrpc_server;
    $pid = $post['ID'];
    $post_date = exapi_post_date($post);
    return array(
        'id' => (string) $pid,
        'title' => $post['post_title'],
        'slug' => $post['post_name'],
        'date' => $post_date,
        'link' => post_permalink($pid),
        'format' => (($f = get_post_format($post)) === '' ? 'standard' : $f),
        'author' => exapi_post_author($post),
        'categories' => exapi_post_categories($post),
        'tags' => exapi_post_tags($post),
        'comments' => exapi_post_comment_info($post),
        'thumbs' => exapi_post_thumb($post, $params),
        'excerpt' => exapi_post_excerpt($post),
        'content' => exapi_post_content($post),
        'post_status' => $post['post_status'],
        'custom_fields' => $wp_xmlrpc_server->get_custom_fields($pid),
    );
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
    if (!is_array($args = _exapi_method_header($args))) {
        return $args;
    }

    // This is a special one, here we find all parameters that will be
    // sent to the `query' param in wp_get_recent_posts()
    $params = $query = array();
    if (isset($args[0])) {
        $data = $args[0];
        $params = _exapi_extract_params($data);
        $query = _exapi_extract_query_params($data);
    }

    // Looking for the post list
    $posts_list = wp_get_recent_posts( $query );
    if ( !$posts_list )
        return array( );

    // Handling posts found
    $struct = array( );
    foreach ( $posts_list as $post ) {
        $struct[] = _exapi_prepare_post($post, $params);
    }
    return $struct;
}


/**
 * Returns all public informations about a post
 *
 * @since 0.1.3
 *
 * @param array $args An array containing a single element: the post id.
 */
function exapi_getPost($args) {
    if (!is_array($args = _exapi_method_header($args))) {
        return $args;
    } else {
        if (isset($args[0]))
            $params = _exapi_extract_params($args[0]);
        else
            $params = array();
        $post = get_post($args[1], ARRAY_A);
        return _exapi_prepare_post($post, $params);
    }
}


/**
 * Returns all public informations of a page given it's path
 *
 * @param array $args An array containing a single element: The page
 *  path. This path is composed by the slugs of pages hierarchically.
 */
function exapi_getPageByPath($args) {
    if (!is_array($args = _exapi_method_header($args)))
        return $args;
    if (!isset($args[1]))
        return null;
    if (($orig = get_page_by_path($args[1], ARRAY_A)) === null)
        return null;
    $params = isset($args[0]) ? _exapi_extract_params($args[0]) : array();
    $page = _exapi_prepare_post($orig, $params);
    foreach (array('format', 'categories', 'tags') as $key)
        unset($page[$key]);
    return $page;
}


/**
 * Returns an array with all information needed to build a tag cloud
 *
 * @since 0.1.2
 *
 * @param array $args Optional, override the default `wp_tag_cloud()'
 *  parameters
 */
function exapi_getTagCloud($args) {
    if (!is_array($args = _exapi_method_header($args))) {
        return $args;
    }

    // We can never echo in xmlrpc
    $args[0]['echo'] = false;

    // We can't handle any other format. The problem with other formats
    // is the address of a tag link. They point to the wordpress theme
    // and it breaks things when you're writting your own wp client.
    $args[0]['format'] = 'array';

    // Converting the html return in an array of arrays
    $tags = wp_tag_cloud($args[0]);
    if ($tags === null)
        $tags = array();

    $ret = array();
    foreach ($tags as $tag) {
        $found = array();
        preg_match_all(
            "/tag=([^\']+).+style=\'font-size:\s([^;]+).+\>([^<]+)/",
            $tag, $found);
        $ret[] = array(
            'slug' => $found[1][0],
            'size' => $found[2][0],
            'name' => $found[3][0]
        );
    }
    return $ret;
}

function exapi_getSidebar($args) {
    if (!is_array($args = _exapi_method_header($args))) {
        return $args;
    }

    ob_start();
    if (!dynamic_sidebar($args[0]['id'])) {
        $ret = new IXR_Error( 403,
                              __('Error: sidebar '.$args[0]['id'].' not found.'));
    } else {
        $ret = ob_get_contents();
    }
    ob_end_clean();
    return $ret;
}

function exapi_getPostsByCategory($args) {
    if (!is_array($args = _exapi_method_header($args))) {
        return $args;
    }
    $posts = array();
    global $post;
    query_posts('cat='.$args[0]['cat'].'&paged='.$args[0]['page']);
    while ( have_posts() ) {
        the_post();
        $posts[] = _exapi_prepare_post((array)$post, array());
    }

    global $wp_query;

    $pag =
        paginate_links(
                       array(
                             'base' => '/cat/'.$args[0]['cat'].'/%#%',
                             'format' => '?paged=%#%',
                             'current' => max( 1, get_query_var('paged') ),
                             'total' => $wp_query->max_num_pages
                             ));
    return array(
                 'posts' => $posts,
                 'pagination' => $pag);
}

function exapi_getArchivePosts($args) {
    if (!is_array($args = _exapi_method_header($args))) {
        return $args;
    }
    $posts = array();
    global $post;
    query_posts('m='.$args[0]['m'].'&paged='.$args[0]['page']);
    while ( have_posts() ) {
        the_post();
        $posts[] = _exapi_prepare_post((array)$post, array());
    }

    global $wp_query;

    $pag =
        paginate_links(
                       array(
                             'base' => '/cat/'.$args[0]['cat'].'/%#%',
                             'format' => '?paged=%#%',
                             'current' => max( 1, get_query_var('paged') ),
                             'total' => $wp_query->max_num_pages
                             ));
    return array(
                 'posts' => $posts,
                 'pagination' => $pag);
}

function exapi_getPostsByTag($args) {
    if (!is_array($args = _exapi_method_header($args))) {
        return $args;
    }
    $posts = array();
    global $post;
    query_posts('tag='.$args[0]['tag'].'&paged='.$args[0]['page']);
    while ( have_posts() ) {
        the_post();
        $posts[] = _exapi_prepare_post((array)$post, array());
    }

    global $wp_query;

    $pag =
        paginate_links(
                       array(
                             'base' => '/tag/'.$args[0]['tag'].'/%#%',
                             'format' => '?paged=%#%',
                             'current' => max( 1, get_query_var('paged') ),
                             'total' => $wp_query->max_num_pages
                             ));

    return array(
                 'posts' => $posts,
                 'pagination' => $pag);
}

function exapi_getPosts($args) {
    if (!is_array($args = _exapi_method_header($args))) {
        return $args;
    }

    global $post;
    global $wp_query;

    $query = array_merge(
        $wp_query->query === null ? array() : $wp_query->query,
        _exapi_extract_query_params($args[0]));

    /* A small translation, query_posts does not expect the "category"
     * parameter, but the "cat" one */
    if (isset($query['category'])) {
        $query['cat'] = $query['category'];
        unset($query['category']);
    }

    $query['paged'] = $args[0]['page'];

    query_posts($query);

    $posts = array();
    while (have_posts()) {
        the_post();
        $posts[] = _exapi_prepare_post((array)$post, array());
    }

    global $wp_query;
    $pag = paginate_links(
        array(
            'base' => '/news/%#%', // you are not seeing this
            'format' => '?paged=%#%',
            'current' => max(1, $args[0]['page']),
            'total' => $wp_query->max_num_pages
        )
    );
    return array(
        'posts' => $posts,
        'pagination' => $pag
    );
}

function exapi_getComments($args) {
    global $wp_xmlrpc_server;
    array_unshift($args, 0);
    return  $wp_xmlrpc_server->wp_getComments($args);
}

function exapi_newComment($args) {
    array_unshift($args, 0);
    if (strlen($args[3]['content']) == 0) {
        return new IXR_Error( 403, __('Error: please type a comment.'));
    }

    $param = array(
                   0 => $args[0],
                   1 => $args[3]['username'],
                   2 => $args[3]['password'],
                   3 => $args[3]['post_id'],
                   4 => array(
                              'comment_parent' => 0,
                              'content' => $args[3]['content']));
    global $wp_xmlrpc_server;
    return $wp_xmlrpc_server->wp_newComment($param);
}

function exapi_search($args) {
    if (!is_array($args = _exapi_method_header($args))) {
        return $args;
    }
    $posts = array();
    global $post;
    query_posts('s='.$args[0]['s'].'&paged='.$args[0]['page']);
    while ( have_posts() ) {
        the_post();
        $posts[] = _exapi_prepare_post((array)$post, array());
    }
    global $wp_query;

    $pag =
        paginate_links(
                       array(
                             'base' => '/search/%#%?s=' . $args[0]['s'],
                             'format' => '?paged=%#%',
                             'current' => max( 1, get_query_var('paged') ),
                             'total' => $wp_query->max_num_pages
                             ));

    return array(
                 'posts' => $posts,
                 'pagination' => $pag);
}

function exapi_register_methods( $methods ) {
    $methods['exapi.getRecentPosts'] = 'exapi_getRecentPosts';
    $methods['exapi.getTagCloud'] = 'exapi_getTagCloud';
    $methods['exapi.getPost'] = 'exapi_getPost';
    $methods['exapi.getPageByPath'] = 'exapi_getPageByPath';
    $methods['exapi.getSidebar'] = 'exapi_getSidebar';
    $methods['exapi.getPostsByCategory'] = 'exapi_getPostsByCategory';
    $methods['exapi.getArchivePosts'] = 'exapi_getArchivePosts';
    $methods['exapi.getPostsByTag'] = 'exapi_getPostsByTag';
    $methods['exapi.getComments'] = 'exapi_getComments';
    $methods['exapi.newComment'] = 'exapi_newComment';
    $methods['exapi.getPosts'] = 'exapi_getPosts';
    $methods['exapi.search'] = 'exapi_search';
    return $methods;
}
add_filter( 'xmlrpc_methods', 'exapi_register_methods' );

?>
