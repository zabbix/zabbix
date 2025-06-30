<?php

namespace Facebook\WebDriver\Exception\Internal;

/**
 * @deprecated To be replaced with UnexpectedResponseException in 2.0
 */
class WebDriverCurlException extends UnexpectedResponseException
{
    public static function forCurlError(string $httpMethod, string $url, string $curlError, ?array $params): self
    {
        $message = sprintf('Curl error thrown for http %s to %s', $httpMethod, $url);

        if (!empty($params)) {
            $message .= sprintf(' with params: %s', json_encode($params, JSON_UNESCAPED_SLASHES));
        }

        $message .= "\n\n" . $curlError;

        return new self($message);
    }
}
