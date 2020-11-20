<?php

if ( ! class_exists( 'version_st24_dashboard' ) ) {

    class version_st24_dashboard {

        protected $repository;
        protected $message = 'stage app sync request';

        public function __construct() {
            // register actions
            add_action( 'admin_init', array( &$this, 'admin_init' ) );
            add_action( 'admin_menu', array( &$this, 'add_menu' ) );

            $this->repository = new version_st24_repository( '../' );
        }

        public function admin_init() {
            if ( isset( $_GET['sync'] ) ) {
                if ( true === filter_var( $_GET['sync'], FILTER_VALIDATE_BOOLEAN ) ) {

                    $version = @file_get_contents( './../VERSION' );

                    if ( $version ) {

                        $current_user = wp_get_current_user();

                        $user_email = strlen( $current_user->user_email ) ? $current_user->user_email : $current_user->display_login;
                        $user_name  = strlen( $current_user->user_nicename ) ? $current_user->user_nicename : $current_user->display_name;

                        $output = $this->repository->syncRepository( $version, $this->message, $user_email, $user_name );

                        set_transient( 'version_sync_action', 'started', 60 );

                        wp_redirect( 'tools.php?page=version_st24' );
                    }
                }
            }

            return false;
        }

        public function add_menu() {
            add_management_page(
                'Version ST24 Dashboard',
                'Version ST24',
                'manage_options',
                'version_st24',
                array( &$this, 'plugin_dashboard_page' )
            );
        }

        public function plugin_dashboard_page() {

            $repo_config    = $this->repository->getConfig();
            $current_branch = $this->repository->getCurrentBranchName();
            $branches       = $this->repository->getBranches();
            $commits_limit  = 20;
            $commits        = $this->repository->getCommits( $commits_limit );
            $status         = $this->repository->hasChanges();
            $release        = $this->repository->getRelease( $repo_config, $branches );
            $disabled       = $status ? '' : 'disabled';

            echo '<div class="wrap version-dashboard">';

            echo '<h1>Version ST24 / Dashboard</h1>';
            echo '<h4>Current branch: <span class="branch-name">' . $current_branch . '</span></h4>';
            echo '<div class="button-sync">';
            echo '<a href="tools.php?page=version_st24&sync=true" type="button" class="btn btn-primary w-200 ' . $disabled . '" ' . $disabled . '>SAVE</a>';
            echo '<br>*send your changes to application repository';
            echo '</div>';

            $table_col = array(
                '#',
                'HASH',
                'Date',
                'Author',
                'Commit',
                'Branch',
                'Details',
            );

            echo '<div style="overflow-x:auto;">';
            echo '<table class="table">';

            echo '<thead>';
            echo '<tr>';
            foreach ( $table_col as $key => $col ) {
                echo '<th>' . $col . '</th>';
            }
            echo '</tr>';
            echo '</head>';

            echo '<tbody>';

            $last_branch = '---';
            foreach ( $commits as $key => $commit ) {

                $branch = str_replace( 'branchname', $current_branch, $commit['branch'] );

                if ( false === strpos( $branch, 'HEAD' ) ) {
                    $branch = substr( $branch, strpos( $branch, ',' ) );
                    $branch = str_replace( array( '(', ')', ', ' ), '', $branch );
                } else {
                    $branch = str_replace( array( '(', ')' ), '', $branch );
                }

                if ( false !== strpos( $commit['name'], $this->message ) ) {
                    $branch = $release;
                }

                echo '<tr>';

                echo '<td class="center">' . ++$key . '</td>';
                echo '<td class="center">' . $commit['hash'] . '</td>';
                echo '<td class="date">' . $commit['date'] . '</td>';
                echo '<td class="author">' . $commit['author'] . '</td>';
                echo '<td class="commit">' . htmlentities( $commit['name'] ) . '</td>';
                echo '<td class="branch">' . $branch . '</td>';
                echo '<td class="details">' . $commit['files'] . '</td>';

                echo '<tr>';
            }
            echo '</tbody>';

            echo '</table>';
            echo '</div>';

            echo '<small>*last ' . $commits_limit . ' commits</small>';

            echo '<div>';
            echo '<h5>DEBUG log - last 20 lines</h5>';
            echo '<textarea id="debug_log" class="debug_log" placeholder="unknow">' . $this->tailCustom( ABSPATH . '/wp-content/debug.log', 20 ) . '</textarea>';
            echo '<button id="debug_button">take_focus</button>';
            echo '</div>';

            echo '<h5>Repository config</h5>';
            echo '<div class="repo-config">';

            foreach ( $repo_config as $param ) {
                echo '<div>' . $param . '</div>';
            }
            echo '</div>';

            echo '</div>';

            echo "<script>
                jQuery( document ).ready(function() {
                    debug_log = jQuery('#debug_log');
                    debug_but = jQuery('#debug_button');
                    debug_val = debug_log.val();
                    debug_log.val('').val(debug_val).focus();
                    debug_but.focus().addClass('d-none')
                });
            </script>";
        }

        /**
         * Slightly modified version of http://www.geekality.net/2011/05/28/php-tail-tackling-large-files/
         *
         * @author Torleif Berger, Lorenzo Stanco
         * @link http://stackoverflow.com/a/15025877/995958
         * @license http://creativecommons.org/licenses/by/3.0/
         */
        private function tailCustom( $filepath, $lines = 1, $adaptive = true ) {
            $f = @fopen( $filepath, 'rb' );
            if ( $f === false ) {
                return false;
            }

            if ( ! $adaptive ) {
                $buffer = 4096;
            } else {
                $buffer = ( $lines < 2 ? 64 : ( $lines < 10 ? 512 : 4096 ) );
            }

            fseek( $f, -1, SEEK_END );

            if ( fread( $f, 1 ) !== "\n" ) {
                $lines--;
            }

            $output = '';
            $chunk  = '';
            while ( ftell( $f ) > 0 && $lines >= 0 ) {
                $seek = min( ftell( $f ), $buffer );
                fseek( $f, -$seek, SEEK_CUR );
                $output = ( $chunk = fread( $f, $seek ) ) . $output;
                fseek( $f, -mb_strlen( $chunk, '8bit' ), SEEK_CUR );
                $lines -= substr_count( $chunk, "\n" );

            }

            while ( $lines++ < 0 ) {
                $output = substr( $output, strpos( $output, "\n" ) + 1 );

            }

            fclose( $f );

            return trim( $output );
        }

    }

}
