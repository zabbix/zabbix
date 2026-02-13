<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement;

use Jose\Component\Core\JWKSet;
use Jose\Component\Core\Util\JsonConverter;
use RuntimeException;
use function is_array;

class JKUFactory extends UrlKeySetFactory
{
    /**
     * This method will try to fetch the url a retrieve the key set. Throws an exception in case of failure.
     */
    public function loadFromUrl(string $url, array $header = []): JWKSet
    {
        $content = $this->getContent($url, $header);
        $data = JsonConverter::decode($content);
        if (! is_array($data)) {
            throw new RuntimeException('Invalid content.');
        }

        return JWKSet::createFromKeyData($data);
    }
}
