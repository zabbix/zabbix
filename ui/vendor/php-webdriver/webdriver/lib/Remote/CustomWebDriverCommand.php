<?php

namespace Facebook\WebDriver\Remote;

use Facebook\WebDriver\Exception\Internal\LogicException;
use Facebook\WebDriver\Exception\WebDriverException;

class CustomWebDriverCommand extends WebDriverCommand
{
    public const METHOD_GET = 'GET';
    public const METHOD_POST = 'POST';

    /** @var string */
    private $customUrl;
    /** @var string */
    private $customMethod;

    /**
     * @param string $session_id
     * @param string $url
     * @param string $method
     */
    public function __construct($session_id, $url, $method, array $parameters)
    {
        $this->setCustomRequestParameters($url, $method);

        parent::__construct($session_id, DriverCommand::CUSTOM_COMMAND, $parameters);
    }

    /**
     * @throws WebDriverException
     * @return string
     */
    public function getCustomUrl()
    {
        if ($this->customUrl === null) {
            throw LogicException::forError('URL of custom command is not set');
        }

        return $this->customUrl;
    }

    /**
     * @throws WebDriverException
     * @return string
     */
    public function getCustomMethod()
    {
        if ($this->customMethod === null) {
            throw LogicException::forError('Method of custom command is not set');
        }

        return $this->customMethod;
    }

    /**
     * @param string $custom_url
     * @param string $custom_method
     * @throws WebDriverException
     */
    protected function setCustomRequestParameters($custom_url, $custom_method)
    {
        $allowedMethods = [static::METHOD_GET, static::METHOD_POST];
        if (!in_array($custom_method, $allowedMethods, true)) {
            throw LogicException::forError(
                sprintf(
                    'Invalid custom method "%s", must be one of [%s]',
                    $custom_method,
                    implode(', ', $allowedMethods)
                )
            );
        }
        $this->customMethod = $custom_method;

        if (mb_strpos($custom_url, '/') !== 0) {
            throw LogicException::forError(
                sprintf('URL of custom command has to start with / but is "%s"', $custom_url)
            );
        }
        $this->customUrl = $custom_url;
    }
}
