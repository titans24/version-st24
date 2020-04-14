<?php

/**
* Plugin Name: Version ST24
* Description: Version control for ST24.
* Version: 1.0.3
* Domain: st24
* Author: Titans24
* Author URI: http://titans24.com/
**/

if ( ! class_exists( 'version_st24' ) ) {

    class version_st24 {

        protected $repository;

        protected $dashboard;

        /**
         * Construct the plugin object
         */
        public function __construct() {
            // init
            $plugin        = plugin_basename(__FILE__);

            // 
            if( !is_admin() ) {
                return;
            }
            
            require_once dirname( __FILE__ ) . '/version-st24/includes/class-dashboard.php';
            require_once dirname( __FILE__ ) . '/version-st24/includes/class-repository.php';
            
            // init object
            $this->repository = new version_st24_repository( '../' );

            // https://wordpress.org/support/article/roles-and-capabilities/
            // http://rachievee.com/the-wordpress-hooks-firing-sequence/

            // plugin template
            add_action( 'admin_enqueue_scripts', array( $this, 'add_custom_admin_style' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'add_custom_admin_style' ) );

            // plugin page - add dashboard link
            add_filter( "plugin_action_links_$plugin", array( $this, 'add_plugin_link' ) );

            // admin notice & admin bar
            add_action( 'admin_notices', array( $this, 'show_admin_notice' ) );
            add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_link' ), 999 );

            // init dashboard
            $this->dashboard = new version_st24_dashboard();
        }




        public static function add_plugin_link( $links ) {
            // permisions
            if ( current_user_can( 'manage_options' ) ) {
                array_unshift( $links, '<a href="tools.php?page=version_st24">' . __( 'Dashboard', 'st24' ) . '</a>' );
            }

            return $links;
        }

        public static function add_admin_bar_link( $wp_admin_bar ) {
            // permisions
            if ( current_user_can( 'manage_options' ) ) {
            
                // message
                if ( true === $this->repository->hasChanges() ) {
                    $message_text  = __( 'Version ST24 running - needs synchronization', 'st24' );
                    $message_class = 'status-unsync';
                } else {
                    $message_text  = __( 'Version ST24 running - synchronized', 'st24' );
                    $message_class = 'status-sync';
                }

                $sync_info = get_transient( 'version_sync_action' );

                if ( 'started' === $sync_info ) {
                    $message_text  = __( 'Version ST24 running - in progress', 'st24' );
                    $message_class = 'status-unsync';
                }

                $wp_admin_bar->add_node(
                    array(
                        'id'     => 'editor-menu',
                        'title'	 => '<span class="ab-icon ' . $message_class . '"></span><span class="ab-label ' . $message_class . '">' . $message_text . '</span>',
                        'href'   => admin_url( 'tools.php?page=version_st24' ),
                        'parent' => 'top-secondary',
                    )
                );
            }
        }

        public static function add_custom_admin_style() {
            // permisions
            if ( current_user_can( 'manage_options' ) ) {

                wp_register_style( 'add_custom_wp_toolbar_css', plugin_dir_url( __FILE__ ) . 'version-st24/includes/style-admin.css', array(), false, 'screen' );
                wp_enqueue_style( 'add_custom_wp_toolbar_css' );
            }
        }

        public static function show_admin_notice() {
            // permisions
            if ( current_user_can( 'manage_options' ) ) {
                
                $version = @file_get_contents( './../VERSION' );
                if ( true !== is_string( $version ) ) {
                    ?>
                        <div class="notice notice-error">
                            <p><?php _e( 'VERSION file does not exist!', 'st24' ); ?></p>
                        </div>
                    <?php
                }

                $sync_info = get_transient( 'version_sync_action' );
                if ( 'started' === $sync_info ) {

                    // check sync result
                    if ( true === $this->repository->hasChanges() ) {
                        ?>
                            <div class="notice notice-warning is-dismissible">
                                <p><?php _e( 'Application sync in progress (~1min)', 'st24' ); ?></p>
                            </div>
                        <?php
                    } else {
                        set_transient( 'version_sync_action', 'finished', 60 );

                        ?>
                            <div class="notice notice-success is-dismissible">
                                <p><?php _e( 'Application is up to date. :)', 'st24' ); ?></p>
                            </div>
                        <?php
                    }
                }
            }
        }
    }
}

if ( class_exists( 'version_st24' ) ) {
    // instantiate the plugin class
    $version_st24 = new version_st24();
}
