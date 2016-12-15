<?php

namespace Deirde\GitBackup {

    class GitBackup {

        private $config = array();
        private $output;
        private $time;

        /**
         * GitBackup constructor.
         * @param array $config
         * return null
         */
        public function __construct(array $config) {

            $this->config = $this->triggers($config);
            
            $this->time = microtime(true);

        }

        /**
         * Error handler
         * @param $errno as integer
         * @param $errstr as string
         * @param $errfile as string
         * @param $errline as integer
         * @return null
         */
        public function error($errno, $errstr, $errfile, $errline) {

            return null;

        }
        
        /**
         * Triggers
         * @param $config as array
         * @return array
         */
        private function triggers($config) {
            
            if (isset($config['root']) && 
                isset($config['trigger'])) {
            
                $directory = $config['root'];
                $fileSPLObjects = new \RecursiveDirectoryIterator($directory);
                $fileSPLObjects =  new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($directory),
                    \RecursiveIteratorIterator::CHILD_FIRST,
                    \RecursiveDirectoryIterator::SKIP_DOTS
                );
                try {
                    foreach($fileSPLObjects as $fullFileName => $fileSPLObject) {
                        if (strpos($fullFileName, '/.c9') === false && 
                            $fileSPLObject->getFilename() == $config['trigger']) {
                            $config['projects'][] = include($fullFileName);
                        }
                    }
                }
                catch (UnexpectedValueException $e) {
                    printf("Directory [%s] contained a directory we can not recurse into", $directory);
                }
                
            }
            
            return $config;
            
        }

        /**
         * Run!
         */
        public function run() {
            
            foreach ($this->config['projects'] as $project) {
                
                if (isset($project['git-ftp'])) {
                    $this->gitFtp($project['name'], $project['root'], 
                        $project['git-ftp']);
                    $this->gitFtpIgnore($project['root'], $project['name']);
                }

                if (isset($project['mysql'])) {
                    foreach ($project['mysql'] as $mysql) {
                        $this->dumpMySQL($project['name'], $project['root'],
                            $mysql['dir'], $mysql['psw'],
                            $mysql['uid'], $mysql['name'],
                            $mysql['date']);
                    }
                }

                if (isset($project['git'])) {

                    $this->gitIgnore($project['root'], $project['name']);
                    $this->readMe($project['root'], $project['name']);

                    $provider = array(
                        $project['git']['provider'] => 
                        $this->config['providers'][$project['git']['provider']]    
                    );
                    
                    $this->git($provider, $project['root'], $project['name'],
                        $provider['uid'], $provider['psw'],
                        $provider['folder'], $project['git']['branch']);

                }

                if (isset($this->config['to']) && $this->config['to'] != '') {
                    $this->mailer();
                }

            }

            $this->report('Executed in seconds > ' . 
                (round(microtime(true) - $this->time, 2)));

        }
        
        /**
         * Setup the gitFtp service.
         * @param $name as string
         * @param $root as string
         * @param $gitFtp as array
         * return null
         */
        private function gitFtp($name, $root, $gitFtp) {
            
            chdir($root . DIRECTORY_SEPARATOR);
            
            $commands[] = 'git config git-ftp.url ' . $gitFtp['url'];
            $commands[] = 'git config git-ftp.user ' . $gitFtp['user'];
            $commands[] = 'git config git-ftp.password ' . $gitFtp['password'];
            
            $output = $name . ' > ' . 'Git-ftp setup completed!';

            $this->output['report'][$name][] = $output;
            
            $this->report($output);
            
            $output = $name . ' > ' . 'git ftp pull on progress..';

            $this->output['report'][$name][] = $output;
            
            $this->report($output);
            
            $this->exec($name, $commands);
            
            exec('git ftp pull' . ' 2>&1', $output, $return_var);
            
            $output = $name . ' > ' . 'git ftp pull on completed!';

            $this->output['report'][$name][] = $output;
            
            $this->report($output);
            
        }

        /**
         * File <.git-ftp-ignore> creator.
         * @param $root as string
         * @param $name as string
         * @return null
         */
        private function gitFtpIgnore($root, $name) {

            chdir(dirname($root . DIRECTORY_SEPARATOR));

            if (!file_exists($root . DIRECTORY_SEPARATOR . '.git-ftp-ignore')) {

                $commands[] = 'cp  ' . dirname(__FILE__) . DIRECTORY_SEPARATOR . 
                    '_git-ftp-ignore ' . $root . DIRECTORY_SEPARATOR .
                    '.git-ftp-ignore';

                $this->exec($name, $commands);

                $output = $name . ' > ' . '.git-ftp-ignore created!';

            } else {

                $output = $name . ' > ' . '.git-ftp-ignore already exists, skipped.';

            }

            $this->report($output);

            $this->output['report'][$name][] = $output;

        }

        /**
         * MySQL database dumper.
         * @param $name as string
         * @param $root as string
         * @param $dir as string
         * @param $psw as string
         * @param $uid as string
         * @param $db as string
         * @param $date as string
         * @return null
         */
        private function dumpMySQL($name, $root, $dir, $psw, $uid, $db, $date) {

            chdir($root . DIRECTORY_SEPARATOR);
            
            foreach (explode('/', $dir) as $_) {

                if (!file_exists($_ . DIRECTORY_SEPARATOR)) {
                    
                    mkdir($_ . DIRECTORY_SEPARATOR);

                }
                
                echo $_ . DIRECTORY_SEPARATOR;
                chdir($_ . DIRECTORY_SEPARATOR);

            }

            chdir($root . DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR);

            $this->report($name . ' > Dumping the database <' . $db . '>..');

            $commands[] = 'MYSQL_PWD=' . $psw;
            $commands[] = 'mysqldump -u ' . $uid . ' -p' . $psw . ' ' . $db . ' | gzip > ' . $root .
                DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . gethostname() .
                '.' . date($date) . '.' . $db . '.sql.gz';

            $this->exec($name, $commands);

            // The dump file is too small, may be failed.
            if (filesize($root .
                DIRECTORY_SEPARATOR . $dir . DIRECTORY_SEPARATOR . gethostname() .
                '.' . date($date) . '.' . $db . '.sql.gz') < 100) {
                $output = 'Attention! The database <' . $db . '> dump file is too small, may be failed.';
            } else {
                $output = 'The database <' . $db . '> has been dumped.';
            }

            $this->report($name . ' > ' . $output);

            $this->output['report'][$name][] = $output;

        }

        /**
         * File <.gitignore> creator.
         * @param $root as string
         * @param $name as string
         * @return null
         */
        private function gitIgnore($root, $name) {

            chdir(dirname($root . DIRECTORY_SEPARATOR));

            if (!file_exists($root . DIRECTORY_SEPARATOR . '.gitignore')) {

                $commands[] = 'cp  ' . dirname(__FILE__) . DIRECTORY_SEPARATOR .
                    '_gitignore ' . $root . DIRECTORY_SEPARATOR . '.gitignore';

                $this->exec($name, $commands);

                $output = $name . ' > ' . '.gitignore created!';

            } else {

                $output = $name . ' > ' . '.gitignore already exists, skipped.';

            }

            $this->report($output);

            $this->output['report'][$name][] = $output;

        }

        /**
         * File <README.md> creator.
         * @param $root as string
         * @param $name as string
         * @return null
         */
        private function readMe($root, $name) {

            chdir($root . DIRECTORY_SEPARATOR);

            if (!file_exists('readme.md')) {

                $lns = array(
                    '# ' . strtoupper($name) . ' #',
                    '@TODO'
                );
                $file = fopen('readme.md', 'w');
                $content = '';
                foreach ($lns as $ln) {
                    $content .= $ln  . "\r\n";
                }
                fwrite($file, $content);
                fclose($file);

                $output = $name . ' > ' . 'readme.md created!';

            } else {

                $output = $name . ' > ' . 'readme.md already exists, skipped.';

            }

            $this->report($output);

            $this->output['report'][$name][] = $output;

        }

        /**
         * Git backup process.
         * @param $root as string
         * @param $root as string
         * @param $name as string
         * @param $uid as string
         * @param $psw as String
         * @param null $folder as string
         * @param $branch as array
         * @return null
         */
        private function git($provider, $root, $name, $uid, $psw, 
            $folder = null, $branch) {
                
            if (!isset($branch['default'])) {
                $branch['default'] = 'master';
            }
            
            if (!isset($branch['backup'])) {
                $branch['backup'] = 'master';
            }
            
            chdir($root . DIRECTORY_SEPARATOR);

            $commands = array();
            
            if (key($provider) == 'bitbucket') {
                $commands[] = 'curl -k --user ' . $uid . ":" . $psw .
                ' https://api.bitbucket.org/1.0/repositories/ --data name=' .
                $name . " --data is_private='true' " . (($folder) ?
                    '--data owner=' . $folder : null);
            } elseif (key($provider) == 'github') {
                $commands[] = "curl -XPOST -H 'Authorization: token " . 
                    $provider['github']['token'] .
                    "' https://api.github.com/user/repos -d '{\"name\":\"" .
                    $name . "\",\"description\":\"\", \"private\": true}'";
            }
            
            $commands[] = 'git init';
            $commands[] = 'git branch ' . $branch['backup'];
            $commands[] = 'git checkout -b ' . $branch['backup'];
            $commands[] = 'git checkout ' . $branch['backup'];
            $commands[] = 'git add --all';
            $commands[] = 'git commit -m "GitBackup auto-update <' . 
                $branch['backup'] . '> branch"';
            
            if (key($provider) == 'bitbucket') {
                $commands[] = 'git remote add origin git@bitbucket.org:' . 
                    $folder . DIRECTORY_SEPARATOR . $name . '.git';
            } elseif (key($provider) == 'github') {
                $commands[] = 'git remote add origin git@github.com:' . 
                    $provider['github']['uid'] . DIRECTORY_SEPARATOR .
                        $name . '.git';
            }
            
            $commands[] = 'git push -u origin ' . $branch['backup'];
            $commands[] = 'git branch ' . $branch['default'];
            $commands[] = 'git checkout -b ' . $branch['default'];
            $commands[] = 'git checkout ' . $branch['default'];

            $this->output['report'][$name][] = $output;
    
            $output = $name . ' > ' . 'Git backup on progress..';

            $this->report($output);

            $this->output['report'][$name][] = $output;

            $this->exec($name, $commands);

            $output = $name . ' > ' . 'Git backup completed!';

        }

        /**
         * Executes the commands.
         * @param $name as string
         * @param $commands as string
         * @return void
         */
        private function exec($name, $commands) {

            foreach ($commands as $command) {

                if ($this->config['verbose'] === false) {
                    exec($command . ' 2>&1', $output, $return_var);
                    $this->output['commands'][$name][] = $output;
                } else {
                    echo $command . PHP_EOL;
                    exec($command);
                }

            }

        }

        /**
         * Line command output.
         * @param $output as string
         * @return null
         */
        private function report($output) {

            echo $output . PHP_EOL;

        }

        /**
         * Send e-mail report.
         * return null
         */
        private function mailer() {

            $output = '';
            $message = array();

            foreach ($this->output['report'] as $key => $value) {

                $output .= $key . ' > ' . json_encode($value);

                if (strpos($this->output['commands'][$key], 'warning') !== false ||
                    strpos($this->output['commands'][$key], 'error') !== false) {
                    $message[] = "Attention! Something's wrong on project: " .
                        $key;
                }

            }

            if (mail($this->config['to'], 'GitBackup summary',
                implode('<br/>', $message))) {
                $this->report('Summary sent to > ' . $this->config['to']);
            } else {
                $this->report('Unable to send the summary to > ' .
                    $this->config['to']);
            }

        }

    }

    $GitBackup = new GitBackup(require 'config.php');
    set_error_handler(array($GitBackup, 'error'));
    $GitBackup->run();

}

?>