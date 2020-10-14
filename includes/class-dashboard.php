<?php

if (!class_exists('version_st24_dashboard')) {

    class version_st24_dashboard
    {

        protected $repository;
        protected $message = 'stage app sync request';

        public function __construct()
        {
            // register actions
            add_action('admin_init', array(&$this, 'admin_init'));
            add_action('admin_menu', array(&$this, 'add_menu'));

            $this->repository = new version_st24_repository('../');
        }

        public function admin_init()
        {
            if (isset($_GET['sync'])) {
                if (true === filter_var($_GET['sync'], FILTER_VALIDATE_BOOLEAN)) {

                    $version = @file_get_contents('./../VERSION');

                    if ($version) {

                        $current_user = wp_get_current_user();

                        $user_email = strlen($current_user->user_email) ? $current_user->user_email : $current_user->display_login;
                        $user_name  = strlen($current_user->user_nicename) ? $current_user->user_nicename : $current_user->display_name;

                        $output = $this->repository->syncRepository($version, $this->message, $user_email, $user_name);

                        set_transient('version_sync_action', 'started', 60);

                        wp_redirect('tools.php?page=version_st24');
                    }
                }
            }

            return false;
        }

        public function add_menu()
        {
            add_management_page(
                'Version ST24 Dashboard',
                'Version ST24',
                'manage_options',
                'version_st24',
                array(&$this, 'plugin_dashboard_page')
            );
        }

        public function plugin_dashboard_page()
        {

            $repo_config    = $this->repository->getConfig();
            $current_branch = $this->repository->getCurrentBranchName();
            $branches       = $this->repository->getBranches();
            $commits_limit  = 20;
            $commits        = $this->repository->getCommits($commits_limit);
            $status         = $this->repository->hasChanges();
            $release        = $this->repository->getRelease($repo_config, $branches);
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
            foreach ($table_col as $key => $col) {
                echo '<th>' . $col . '</th>';
            }
            echo '</tr>';
            echo '</head>';

            echo '<tbody>';

            foreach ($commits as $key => $commit) {

                $branch = str_replace('branchname', $current_branch, $commit['branch']);

                if (false === strpos($branch, 'HEAD')) {
                    $branch = substr($branch, strpos($branch, ','));
                    $branch = str_replace(['(', ')', ', '], '', $branch);
                } else {
                    $branch = str_replace(['(', ')'], '', $branch);
                }

                if (false !== strpos($commit['name'], $this->message)) {
                    $branch = $release;
                }

                echo '<tr>';

                echo '<td class="center">'  . ++$key . '</td>';
                echo '<td class="center">'  . $commit['hash'] . '</td>';
                echo '<td class="date">'    . $commit['date'] . '</td>';
                echo '<td class="author">'  . $commit['author'] . '</td>';
                echo '<td class="commit">'  . htmlentities($commit['name']) . '</td>';
                echo '<td class="branch">'  . $branch . '</td>';
                echo '<td class="details">' . $commit['files'] . '</td>';

                echo '<tr>';
            }
            echo '</tbody>';

            echo '</table>';
            echo '</div>';

            echo '<small>*last ' . $commits_limit . ' commits</small>';

            echo '<h5>Repository config</h5>';
            echo '<div class="repo-config">';

            foreach ($repo_config as $param) {
                echo '<div>' . $param . '</div>';
            }
            echo '</div>';

            echo '</div>';
        }
    }
}
