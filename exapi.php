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

/**
 * Extract query params from an associative array
 *
 * @param array $args Search parameters to be extracted and handled
 * @return array
 */
function _exapi_extract_params( $args ) {
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
        _exapi_extract_params( $args[3] ) : array( );

    // All methods in this API are being protected
    if ( !$user = wp_xmlrpc_server::login($username, $password) )
        return wp_xmlrpc_server::error;

    // Looking for the post list
    $posts_list = wp_get_recent_posts( $query );
    if ( !$posts_list )
        return array( );

    // Handling posts found
    $struct = array( );
    foreach ( $posts_list as $entry ) {
        if ( !current_user_can( 'edit_post', $entry['ID'] ) )
            continue;

        $pid = $entry['ID'];

        $post_date = mysql2date('Ymd\TH:i:s', $entry['post_date'], false);
        $post_date_gmt = mysql2date('Ymd\TH:i:s', $entry['post_date_gmt'], false);

        $categories = array();
        $catids = wp_get_post_categories( $pid );
        foreach ( $catids as $catid )
            $categories[] = get_cat_name( $catid );

        $tagnames = array();
        $tags = wp_get_post_tags( $pid );
        if ( !empty( $tags ) ) {
            foreach ( $tags as $tag ) {
                $tagnames[] = $tag->name;
            }
            $tagnames = implode( ', ', $tagnames );
        } else {
            $tagnames = '';
        }

        $post = get_extended( $entry['post_content'] );
        $link = post_permalink( $pid );

        // Get the post author info.
        $author = get_userdata( $entry['post_author'] );

        // Stuff about comments
        $allow_comments = ( $entry['comment_status'] === 'open') ? 1 : 0;
        $allow_pings = ( $entry['ping_status'] === 'open' ) ? 1 : 0;
        $comments_count = (int) get_comments_number( $pid );

        // Consider future posts as published
        if ( $entry['post_status'] === 'future' )
            $entry['post_status'] = 'publish';

        // Get post format
        $post_format = get_post_format( $pid );
        if ( empty( $post_format ) )
            $post_format = 'standard';

        // Post thumbnail
        $thumb = null;
        $thumb_info = has_post_thumbnail( $pid ) ?
            wp_get_attachment_image_src( get_post_thumbnail_id( $pid ), 'full' ) :
            null;
        if ( $thumb_info !== null ) {
            $thumb = array( );
            $thumb['url'] = $thumb_info[0];
            $thumb['width'] = $thumb_info[1];
            $thumb['height'] = $thumb_info[2];
        }

        // Applying filters against the post content
        $content = $entry['post_content'];
        $content = apply_filters('the_content', $content);
        $content = str_replace(']]>', ']]&gt;', $content);

        // Excerpt
        $excerpt = $entry['post_excerpt'];
        if ( empty( $excerpt ) ) {
            $divided = explode( ' ', $content );
            $sliced = array_slice( $divided, 0, 40 );
            $excerpt = implode( ' ', $sliced );
            if ( sizeof( $sliced ) < sizeof( $divided ) ) {
                $excerpt .= ' (...)';
            }
        }

        $struct[] = array(
            'dateCreated' => new IXR_Date($post_date),
            'userid' => $entry['post_author'],
            'postid' => (string) $pid,
            'description' => $post['main'],
            'title' => $entry['post_title'],
            'link' => $link,
            'permaLink' => $link,
            'content' => $content,
            'categories' => $categories,
            'mt_excerpt' => $entry['post_excerpt'],
            'mt_text_more' => $post['extended'],
            'mt_allow_comments' => $allow_comments,
            'mt_allow_pings' => $allow_pings,
            'mt_keywords' => $tagnames,
            'wp_slug' => $entry['post_name'],
            'wp_password' => $entry['post_password'],
            'wp_author_id' => $author->ID,
            'wp_author_display_name' => $author->display_name,
            'date_created_gmt' => new IXR_Date($post_date_gmt),
            'post_status' => $entry['post_status'],
            'custom_fields' => $wp_xmlrpc_server->get_custom_fields( $pid ),
            'wp_post_format' => $post_format,
            'thumb' => $thumb,
            'excerpt' => $excerpt,
            'comments_count' => $comments_count
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
