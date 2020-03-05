<?php

if ( ! class_exists( 'version_st24_repository' ) ) {

    class version_st24_repository {

        /** @var  string */
        protected $repository;

        /** @var  string|NULL  @internal */
        protected $cwd;

        /**
         * @param  string
         * @throws GitException
         */
        public function __construct( $repository ) {
            if ( '.git' === basename( $repository ) ) {
                $repository = dirname( $repository );
            }

            $this->repository = realpath( $repository );

            if ( false === $this->repository ) {
                throw new GitException( "Repository '$repository' not found." );
            }
        }

        /**
         * Exists changes?
         * `git status` + magic
         *
         * @return bool
         * @throws GitException
         */
        public function hasChanges() {
            // Make sure the `git status` gets a refreshed look at the working tree.
            $this->begin()
                ->run( 'git update-index -q --refresh' )
                ->end();

            $output = $this->extractFromCommand( 'git status --porcelain' );

            return ! empty( $output );
        }

        /**
         * Returns repository config.
         *
         * @return string[]|NULL
         * @throws GitException
         */
        public function getConfig() {
            return $this->extractFromCommand( 'git config --list' );
        }

        /**
         * Returns list of all (local & remote) branches in repo.
         *
         * @return string[]|NULL  NULL => no branches
         * @throws GitException
         */
        public function getBranches() {
            return $this->extractFromCommand(
                'git branch -a',
                function( $value ) {
                    return trim( substr( $value, 1 ) );
                }
            );
        }

        /**
         * Gets name of current branch
         * `git branch` + magic
         *
         * @return string
         * @throws GitException
         */
        public function getCurrentBranchName()
        {
            try
            {
                $branch = $this->extractFromCommand('git branch -a', function($value) {
                    if ( isset($value[0]) && $value[0] === '*' ) {
                        return trim(substr($value, 1));
                    }

                    return FALSE;
                });

                if(is_array($branch))
                {
                    return $branch[0];
                }
            }
            catch(GitException $e) {}
            throw new GitException( 'Getting current branch name failed.' );
        }

        /**
         * Gets commits data
         * `git log` + magic
         *
         * @return string
         * @throws GitException
         */
        public function getCommits( $limit = 20 ) {
            try {
                $result = array();

                $commits = $this->extractFromCommand(
                    'git log --pretty=format:"%h #@# %ad #@# %s #@# %d #@# %an" --date=relative'
                );

                if ( is_array( $commits ) ) {
                    foreach ( $commits as $key => $commit ) {
                        if ( $limit <= $key ) {
                            continue;
                        }

                        $line     = array_map( 'trim', explode( '#@#', $commit ) );
                        $result[] = array(
                            'hash'   => $line[0] ?? '---',
                            'date'   => $line[1] ?? '---',
                            'name'   => $line[2] ?? '---',
                            'branch' => $line[3] ?? '---',
                            'author' => $line[4] ?? '---',
                        );
                    }
                }

            } catch ( Exception  $e ) {
                echo 'Getting commits data failed.';
            } finally {
                return $result;
            }
        }

        public function syncRepository( $version, $message = 'version ST24 auto sync', $author_email = 'unknow@titans24.com', $author_name = 'Version ST24' ) {

            $commit_info = ' <' . date( 'Ymd-His' ) . '> <' . $author_email . '> ' . $message;

            return $this->begin()
                ->run( 'git add .' )
                ->run( 'git commit -m "' . $commit_info . '"' )
                ->run( 'git push -u origin release/' . $version )
                ->end();
        }


        /**
         * @return self
         */
        protected function begin()
        {
            if($this->cwd === NULL)
            {
                $this->cwd = getcwd();
                chdir($this->repository);
            }

            return $this;
        }

        /**
         * Runs command.
         * 
         * @param  string|array
         * @return self
         * @throws GitException
         */
        protected function run( $cmd )
        {
            $args = func_get_args();
            $cmd  = self::processCommand( $args );
            exec( $cmd . ' 2>&1', $output, $ret );

            if( $ret !== 0 ) {
                throw new GitException("Command '$cmd' failed (exit-code $ret).", $ret);
            }

            return $this;
        }

        protected static function processCommand(array $args)
        {
            $cmd = array();

            $programName = array_shift($args);

            foreach($args as $arg)
            {
                if(is_array($arg))
                {
                    foreach($arg as $key => $value)
                    {
                        $_c = '';

                        if(is_string($key))
                        {
                            $_c = "$key ";
                        }

                        $cmd[] = $_c . escapeshellarg($value);
                    }
                }
                elseif(is_scalar($arg) && !is_bool($arg))
                {
                    $cmd[] = escapeshellarg($arg);
                }
            }

            return "$programName " . implode(' ', $cmd);
        }

        /**
         * @return self
         */
        protected function end()
        {
            if(is_string($this->cwd))
            {
                chdir($this->cwd);
            }

            $this->cwd = NULL;
            return $this;
        }

        /**
         * @param  string
         * @param  callback|NULL
         * @return string[]|NULL
         * @throws GitException
         */
        protected function extractFromCommand($cmd, $filter = NULL)
        {
            $output = array();
            $exitCode = NULL;

            $this->begin();
            exec("$cmd", $output, $exitCode);
            $this->end();

            if($exitCode !== 0 || !is_array($output))
            {
                throw new GitException("Command $cmd failed.");
            }

            if($filter !== NULL)
            {
                $newArray = array();

                foreach($output as $line)
                {
                    $value = $filter($line);

                    if($value === FALSE)
                    {
                        continue;
                    }

                    $newArray[] = $value;
                }

                $output = $newArray;
            }

            if(!isset($output[0])) // empty array
            {
                return NULL;
            }

            return $output;
        }

    }
}

class GitException extends \Exception
{
}
