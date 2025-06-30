<?php

namespace Facebook\WebDriver\Remote;

class RemoteExecuteMethod implements ExecuteMethod
{
    /**
     * @var RemoteWebDriver
     */
    private $driver;

    public function __construct(RemoteWebDriver $driver)
    {
        $this->driver = $driver;
    }

    /**
     * @param string $command_name
     * @return mixed
     */
    public function execute($command_name, array $parameters = [])
    {
        return $this->driver->execute($command_name, $parameters);
    }
}
