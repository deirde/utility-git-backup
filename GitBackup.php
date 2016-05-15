<?php

namespace Deirde\GitBackup {

    class GitBackup {

        private $config = array();
        private $output;
        private $time;

        /**
         * GitBackup constructor.
         * @param array $config
         */
        public function __construct(array $config) {

            $this->config = $config;

            $this->time = microtime(true);

        }

        /**
         * Error handler
         * @param $errno
         * @param $errstr
         * @param $errfile
         * @param $errline
         * @return null
         */
        public function error($errno, $errstr, $errfile, $errline) {

            return null;

        }

        /**
         * Run!
         */
        public function run() {

            foreach ($this->config['projects'] as $project) {

                if (isset($project['mysql'])) {
                    $this->dumpMySQL($project['name'], $project['root'],
                        $project['mysql']['dir'], $project['mysql']['psw'],
                        $project['mysql']['uid'], $project['mysql']['name'],
                        $project['mysql']['date']);
                }

                if (isset($project['git'])) {

                    $this->gitIgnore($project['root'], $project['name']);
                    $this->gitFtpIgnore($project['root'], $project['name']);
                    $this->readMe($project['root'], $project['name']);

                    $provider = $this->config['providers'][$project['git']['provider']];
                    $this->git($project['root'], $project['name'], $provider['uid'], $provider['psw'],
                        $provider['folder'], $project['git']['branch']);

                }

                if (isset($this->config['to']) && $this->config['to'] != '') {
                    $this->mailer();
                }

            }

            $this->report('Fully executed in seconds > ' . (round(microtime(true) - $this->time, 2)));

        }

        /**
         * MySQL database dumper.
         * @param $name
         * @param $root
         * @param $dir
         * @param $psw
         * @param $uid
         * @param $db
         * @param $date
         */
        public function dumpMySQL($name, $root, $dir, $psw, $uid, $db, $date) {

            chdir($root . DIRECTORY_SEPARATOR);

            foreach (explode('/', $dir) as $_) {

                if (!file_exists($_)) {

                    mkdir($_ . DIRECTORY_SEPARATOR);

                    chdir($_ . DIRECTORY_SEPARATOR);

                }

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
         * @param $root
         * @param $name
         */
        public function gitIgnore($root, $name) {

            chdir(dirname($root . DIRECTORY_SEPARATOR));

            if (!file_exists($root . DIRECTORY_SEPARATOR . '.gitignore')) {

                $commands[] = 'cp  ' . dirname(__FILE__) . DIRECTORY_SEPARATOR . '_gitignore ' .
                    $root . DIRECTORY_SEPARATOR . '.gitignore';

                $this->exec($name, $commands);

                $output = $name . ' > ' . '.gitignore created!';

            } else {

                $output = $name . ' > ' . '.gitignore already exists, skipped.';

            }

            $this->report($output);

            $this->output['report'][$name][] = $output;

        }

        /**
         * File <.git-ftp-ignore> creator.
         * @param $root
         * @param $name
         */
        public function gitFtpIgnore($root, $name) {

            chdir(dirname($root . DIRECTORY_SEPARATOR));

            if (!file_exists($root . DIRECTORY_SEPARATOR . '.git-ftp-ignore')) {

                $commands[] = 'cp  ' . dirname(__FILE__) . DIRECTORY_SEPARATOR . '_git-ftp-ignore ' .
                    $root . DIRECTORY_SEPARATOR . '.git-ftp-ignore';

                $this->exec($name, $commands);

                $output = $name . ' > ' . '.git-ftp-ignore created!';

            } else {

                $output = $name . ' > ' . '.git-ftp-ignore already exists, skipped.';

            }

            $this->report($output);

            $this->output['report'][$name][] = $output;

        }

        /**
         * File <README.md> creator.
         * @param $root
         * @param $name
         */
        public function readMe($root, $name) {

            chdir($root . DIRECTORY_SEPARATOR);

            if (!file_exists('README.md')) {

                $lns = array(
                    '# ' . strtoupper($name) . ' #',
                    '@TODO'
                );
                $file = fopen('README.md', 'w');
                $content = '';
                foreach ($lns as $ln) {
                    $content .= $ln  . "\r\n";
                }
                fwrite($file, $content);
                fclose($file);

                $output = $name . ' > ' . 'README.md created!';

            } else {

                $output = $name . ' > ' . 'README.md already exists, skipped.';

            }

            $this->report($output);

            $this->output['report'][$name][] = $output;

        }

        /**
         * Git backup process.
         * @param $root
         * @param $name
         * @param $uid
         * @param $psw
         * @param null $folder
         * @param $branch
         */
        public function git($root, $name, $uid, $psw, $folder = null, $branch) {

            chdir($root . DIRECTORY_SEPARATOR);

            $commands = array(

                'curl -k --user ' . $uid . ":" . $psw .
                ' https://api.bitbucket.org/1.0/repositories/ --data name=' . $name .
                " --data is_private='true' " . (($folder) ? '--data owner=' .
                    $folder : null),

                'git init',
                'git add --all',
                'git commit -m "GitBackup auto-update <' . $branch . '> branch"',
                'git remote add origin git@bitbucket.org:' . $folder . DIRECTORY_SEPARATOR . $name . '.git',
                'git branch ' . $branch,
                'git checkout ' . $branch,
                'git push -u origin ' . $branch,
            );

            $output = $name . ' > ' . 'Git backup on progress..';

            $this->report($output);

            $this->output['report'][$name][] = $output;

            $this->exec($name, $commands);

            $output = $name . ' > ' . 'Git backup completed!';

            $this->report($output);

            $this->output['report'][$name][] = $output;

        }

        /**
         * Executes the commands.
         * @param $commands
         */
        public function exec($name, $commands) {

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
         * @param $output
         */
        public function report($output) {

            echo $output . PHP_EOL;

        }

        /**
         * Send e-mail report.
         */
        public function mailer() {

            $output = '';
            $message = array();

            foreach ($this->output['report'] as $key => $value) {

                $output .= $key . ' > ' . json_encode($value);

                if (strpos($this->output['commands'][$key], 'warning') !== false ||
                    strpos($this->output['commands'][$key], 'error') !== false) {
                    $message[] = "Attention! Something's wrong on project: " . $key;
                }

            }

            if (mail($this->config['to'], 'GitBackup summary', implode('<br/>', $message))) {
                $this->report('Summary sent to > ' . $this->config['to']);
            } else {
                $this->report('Unable to send the summary to > ' . $this->config['to']);
            }

        }

    }

    $GitBackup = new GitBackup(require 'config.php');
    set_error_handler(array($GitBackup, 'error'));
    $GitBackup->run();

}

?>