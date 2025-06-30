<?php

namespace Facebook\WebDriver\Remote\Service;

use Facebook\WebDriver\Exception\Internal\IOException;
use Facebook\WebDriver\Exception\Internal\RuntimeException;
use Facebook\WebDriver\Net\URLChecker;
use Symfony\Component\Process\Process;

/**
 * Start local WebDriver service (when remote WebDriver server is not used).
 * This will start new process of respective browser driver and take care of its lifecycle.
 */
class DriverService
{
    /**
     * @var string
     */
    private $executable;

    /**
     * @var string
     */
    private $url;

    /**
     * @var array
     */
    private $args;

    /**
     * @var array
     */
    private $environment;

    /**
     * @var Process|null
     */
    private $process;

    /**
     * @param string $executable
     * @param int $port The given port the service should use.
     * @param array $args
     * @param array|null $environment Use the system environment if it is null
     */
    public function __construct($executable, $port, $args = [], $environment = null)
    {
        $this->setExecutable($executable);
        $this->url = sprintf('http://localhost:%d', $port);
        $this->args = $args;
        $this->environment = $environment ?: $_ENV;
    }

    /**
     * @return string
     */
    public function getURL()
    {
        return $this->url;
    }

    /**
     * @return DriverService
     */
    public function start()
    {
        if ($this->process !== null) {
            return $this;
        }

        $this->process = $this->createProcess();
        $this->process->start();

        $this->checkWasStarted($this->process);

        $checker = new URLChecker();
        $checker->waitUntilAvailable(20 * 1000, $this->url . '/status');

        return $this;
    }

    /**
     * @return DriverService
     */
    public function stop()
    {
        if ($this->process === null) {
            return $this;
        }

        $this->process->stop();
        $this->process = null;

        $checker = new URLChecker();
        $checker->waitUntilUnavailable(3 * 1000, $this->url . '/shutdown');

        return $this;
    }

    /**
     * @return bool
     */
    public function isRunning()
    {
        if ($this->process === null) {
            return false;
        }

        return $this->process->isRunning();
    }

    /**
     * @deprecated Has no effect. Will be removed in next major version. Executable is now checked
     * when calling setExecutable().
     * @param string $executable
     * @return string
     */
    protected static function checkExecutable($executable)
    {
        return $executable;
    }

    /**
     * @param string $executable
     * @throws IOException
     */
    protected function setExecutable($executable)
    {
        if ($this->isExecutable($executable)) {
            $this->executable = $executable;

            return;
        }

        throw IOException::forFileError(
            'File is not executable. Make sure the path is correct or use environment variable to specify'
            . ' location of the executable.',
            $executable
        );
    }

    /**
     * @param Process $process
     */
    protected function checkWasStarted($process)
    {
        usleep(10000); // wait 10ms, otherwise the asynchronous process failure may not yet be propagated

        if (!$process->isRunning()) {
            throw RuntimeException::forDriverError($process);
        }
    }

    private function createProcess(): Process
    {
        $commandLine = array_merge([$this->executable], $this->args);

        return new Process($commandLine, null, $this->environment);
    }

    /**
     * Check whether given file is executable directly or using system PATH
     */
    private function isExecutable(string $filename): bool
    {
        if (is_executable($filename)) {
            return true;
        }
        if ($filename !== basename($filename)) { // $filename is an absolute path, do no try to search it in PATH
            return false;
        }

        $paths = explode(PATH_SEPARATOR, getenv('PATH'));
        foreach ($paths as $path) {
            if (is_executable($path . DIRECTORY_SEPARATOR . $filename)) {
                return true;
            }
        }

        return false;
    }
}
