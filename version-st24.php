<?php

/**
* Plugin Name: Version ST24
* Description: Version control for ST24.
* Version: 1.0.6
* Domain: st24
* Author: Titans24
* Author URI: http://titans24.com/
**/

if ( ! class_exists( 'version_st24' ) ) {

    class version_st24 {

        protected $repository;
        protected $dashboard;

        private $version;

        /**
         * Plugin Object Structure
         */
        public function __construct() {
            // init
            $plugin   = plugin_basename(__FILE__);

            // Show only on STAGE and for Admin Users
            if ( !is_admin() || getenv('BLOG_PUBLIC') ) {
                return;
            }
            
            require_once dirname( __FILE__ ) . '/includes/class-dashboard.php';
            require_once dirname( __FILE__ ) . '/includes/class-repository.php';
            
            // Initialize Plugin Object
            $this->repository = new version_st24_repository( './' );

            // Plugin Template
            add_action( 'admin_enqueue_scripts', array( $this, 'add_custom_admin_style' ) );
            add_action( 'wp_enqueue_scripts', array( $this, 'add_custom_admin_style' ) );

            // Plugin Page - Add Dashboard Link
            add_filter( "plugin_action_links_$plugin", array( $this, 'add_plugin_link' ) );

            // Admin Notice & Admin Bar
            add_action( 'admin_notices', array( $this, 'show_admin_notice' ) );
            add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_link' ), 999 );

            // Initialize Dashboard
            $this->dashboard = new version_st24_dashboard();
        }

        public function add_plugin_link( $links ) {
            // Permisions
            if ( current_user_can( 'manage_options' ) ) {
                if ( $this->check_conditions() ) {
                    array_unshift( $links, '<a href="tools.php?page=version_st24">' . __( 'Dashboard', 'st24' ) . '</a>' );
                }
            }

            return $links;
        }

        public function add_admin_bar_link( $wp_admin_bar ) {
            // Permisions
            if ( current_user_can( 'manage_options' ) ) {

                if ( $this->check_conditions() ) {

                    // Message
                    if ( true === $this->repository->hasChanges() ) {
                        $message_text  = __( 'Version ST24 running - needs synchronization', 'st24' );
                        $message_class = 'status-unsync';
                    } else {
                        $message_text  = __( 'Version ST24 running - synchronized', 'st24' );
                        $message_class = 'status-sync';
                    }

                    // Message - in progress
                    $sync_info = get_transient( 'version_sync_action' );
                    if ( 'started' === $sync_info ) {
                        $message_text  = __( 'Version ST24 running - in progress', 'st24' );
                        $message_class = 'status-unsync';
                    }

                    // Bar Info
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
        }

        public function add_custom_admin_style() {
            // Permisions
            if ( current_user_can( 'manage_options' ) ) {

                wp_register_style( 'add_custom_wp_toolbar_css', plugin_dir_url( __FILE__ ) . 'includes/style-admin.css', array(), false, 'screen' );
                wp_enqueue_style( 'add_custom_wp_toolbar_css' );
            }
        }

        public function show_admin_notice() {
            // Permisions
            if ( current_user_can( 'manage_options' ) ) {
                if ( $this->check_conditions() ) {
                    $sync_info = get_transient( 'version_sync_action' );
                    if ( 'started' === $sync_info ) {
                        
                        // Check Sync Result
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
                } else {
                    if ( true !== $this->version_exist() ) {
                        ?>
                            <div class="notice notice-error">
                                <p><?php _e( 'Version ST24: VERSION file does not exist!', 'st24' ); ?></p>
                            </div>
                        <?php
                    } elseif ( true !==  $this->version_valid() ) {
                        ?>
                            <div class="notice notice-error">
                                <p><?php _e( 'Version ST24: VERSION is not valid!', 'st24' ); ?></p>
                            </div>
                        <?php
                    }
                }
            }
        }

        private function check_conditions() {
            return $this->version_exist() && $this->version_valid();
        }

        private function version_exist() {
            $this->version = @file_get_contents( './../VERSION' );

            return is_string( $this->version );
        }

        private function version_valid() {
            return preg_match( '/^(\d+\.)(\d+\.)(\d+)$/', $this->version );
        }
    }
}

if ( class_exists( 'version_st24' ) ) {
    // Initialize the Plugin Class
    $version_st24 = new version_st24();
}
