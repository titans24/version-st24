<?php


require WPMU_PLUGIN_DIR . '/version-st24/version-st24.php';
require WPMU_PLUGIN_DIR . '/aryo-activity-log/aryo-activity-log.php';


function disable_plugin_deactivation( $actions, $plugin_file, $plugin_data, $context ) {
    if ( array_key_exists( 'deactivate', $actions ) && in_array( $plugin_file, array(
        'worker/init.php',
        'wp-mail-smtp/wp_mail_smtp.php',
        'updraftplus/updraftplus.php',
    ))) {
        unset( $actions['deactivate'] );
    }

    return $actions;
}
add_filter( 'plugin_action_links', 'disable_plugin_deactivation', 10, 4 );


function cst_menu_order( $menu_order ) {
    global $menu;

    /* https://whiteleydesigns.com/editing-wordpress-admin-menus */

    $key = array_search( 'edit.php?post_type=acf-field-group', $menu_order, true );
    if ( $menu_order[ $key ] ) {
        unset( $key );
        $menu_order[] = 'edit.php?post_type=acf-field-group';    // move to the end of the menu
    }

    $key = array_search( 'activity_log_page', $menu_order, true );
    if ( $menu_order[ $key ] ) {
        unset( $key );
        $menu_order[] = 'activity_log_page';    // move to the end of the menu
    }

    foreach ( $menu as $key => $item ) {
        if ( 'activity_log_page' === $item[2] ) {
            if ( false === in_array( wp_get_current_user()->user_email, [ 'support@titans24.com', 'admin@titans24.com', 'admin@25wat.com' ], true ) ) {
                unset( $menu[$key] );
            }
            break;
        }
    }

    return $menu_order;
}
add_filter( 'custom_menu_order', '__return_true' );
add_filter( 'menu_order', 'cst_menu_order', 1 );


//Autoupdate themes & plugins
//add_filter( 'auto_update_theme', '__return_true' );
//add_filter( 'auto_update_plugin', '__return_true' );
//add_filter( 'auto_update_translation', '__return_true' );


function m_remove_script_version( $src ){
    $src = remove_query_arg( 'ver', $src );
    $src = remove_query_arg( 'v', $src );
    return $src;
}
add_filter( 'script_loader_src', 'm_remove_script_version', 15, 1 );
add_filter( 'style_loader_src', 'm_remove_script_version', 15, 1 );


function filter_media_comment_status( $open, $post_id ) {
    $post = get_post( $post_id );
    if( $post->post_type == 'attachment' /*|| $post->post_type == 'page'*/ ) {
        return false;
    }
    return $open;
}
add_filter( 'comments_open', 'filter_media_comment_status', 10 , 2 );
add_filter( 'pings_open',    'filter_media_comment_status', 10, 2 );


function no_self_ping( &$links ) {
    $home = get_option( 'home' );
    foreach ( $links as $l => $link ) if ( 0 === strpos( $link, $home ) ) unset($links[$l]);
}
add_action( 'pre_ping', 'no_self_ping' );


// SECURITY

/* Remove XMLRPC method (limit "bruteforce" attack)
*******************************************************************************/
function mmx_remove_xmlrpc_methods( $methods ) {
    unset( $methods['system.multicall'] );
    return $methods;
}
add_filter( 'xmlrpc_methods', 'mmx_remove_xmlrpc_methods');

add_filter('sanitize_file_name', 'remove_accents' ); // Sanitize file name accent on upload


// Filters for WP-API version 1.x
add_filter( 'json_enabled', '__return_false' );
add_filter( 'json_jsonp_enabled', '__return_false' );

// Filters for WP-API version 2.x
add_filter( 'rest_jsonp_enabled', '__return_false' );

// Remove REST API info from head and headers
remove_action( 'xmlrpc_rsd_apis', 'rest_output_rsd' );
remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
remove_action( 'template_redirect', 'rest_output_link_header', 11 );

remove_action('wp_head', 'rsd_link');
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'wp_shortlink_wp_head');
remove_action('wp_head', 'wp_generator');
add_filter('the_generator', '__return_empty_string');
add_filter('get_the_generator_html', '__return_empty_string');
add_filter('get_the_generator_xhtml', '__return_empty_string');
add_filter('get_the_generator_atom', '__return_empty_string');
add_filter('get_the_generator_rss2', '__return_empty_string');
add_filter('get_the_generator_comment', '__return_empty_string');



function disable_emojis() {
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'admin_print_styles', 'print_emoji_styles' );
    remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
    remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
    remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
    add_filter( 'tiny_mce_plugins', 'disable_emojis_tinymce' );
    add_filter( 'wp_resource_hints', 'disable_emojis_remove_dns_prefetch', 10, 2 );


}
add_action( 'init', 'disable_emojis' );

function disable_embeds_code_init() {
    remove_action('rest_api_init', 'wp_oembed_register_route' );        // Remove the REST API endpoint.
    add_filter( 'embed_oembed_discover', '__return_false' );            // Turn off oEmbed auto discovery.
    remove_filter( 'oembed_dataparse', 'wp_filter_oembed_result', 10 ); // Don't filter oEmbed results.
    remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );        // Remove oEmbed discovery links.
    remove_action( 'wp_head', 'wp_oembed_add_host_js' );                // Remove oEmbed JavaScript from the front-end and back-end.
    add_filter( 'tiny_mce_plugins', 'disable_embeds_tiny_mce_plugin' ); // Remove oEmbed JavaScript from the front-end and back-end.

    add_filter( 'rewrite_rules_array', 'disable_embeds_rewrites' );		        // Remove all embeds rewrite rules.
    remove_filter( 'pre_oembed_result', 'wp_filter_pre_oembed_result', 10 );    // Remove filter of the oEmbed result before any HTTP requests are made.
}
add_action( 'init', 'disable_embeds_code_init', 9999 );

function disable_embeds_tiny_mce_plugin($plugins) {
    return array_diff($plugins, array('wpembed'));
}

function disable_embeds_rewrites($rules) {
    foreach($rules as $rule => $rewrite) {
        if(false !== strpos($rewrite, 'embed=true')) {
            unset($rules[$rule]);
        }
    }
    return $rules;
}

function disable_emojis_tinymce( $plugins ) {
    if ( is_array( $plugins ) ) {
        return array_diff( $plugins, array( 'wpemoji' ) );
    }

    return array();
}

function disable_emojis_remove_dns_prefetch( $urls, $relation_type ) {
    if ( 'dns-prefetch' == $relation_type ) {
        // Strip out any URLs referencing the WordPress.org emoji location
        $emoji_svg_url_bit = 'https://s.w.org/images/core/emoji/';
        foreach ( $urls as $key => $url ) {
            if ( strpos( $url, $emoji_svg_url_bit ) !== false ) {
                unset( $urls[$key] );
            }
        }

    }

    return $urls;
}


