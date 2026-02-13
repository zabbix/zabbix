<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement;

use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\Core\Util\JsonConverter;
use Jose\Component\KeyManagement\KeyConverter\KeyConverter;
use RuntimeException;
use function assert;
use function is_array;
use function is_string;

class X5UFactory extends UrlKeySetFactory
{
    /**
     * This method will try to fetch the url a retrieve the key set. Throws an exception in case of failure.
     *
     * @param array<string, string|string[]> $header
     */
    public function loadFromUrl(string $url, array $header = []): JWKSet
    {
        $content = $this->getContent($url, $header);
        $data = JsonConverter::decode($content);
        if (! is_array($data)) {
            throw new RuntimeException('Invalid content.');
        }

        $keys = [];
        foreach ($data as $kid => $cert) {
            assert(is_string($cert), 'Invalid content.');
            if (! str_contains($cert, '-----BEGIN CERTIFICATE-----')) {
                $cert = '-----BEGIN CERTIFICATE-----' . "\n" . $cert . "\n" . '-----END CERTIFICATE-----';
            }
            $jwk = KeyConverter::loadKeyFromCertificate($cert);
            if (is_string($kid)) {
                $jwk['kid'] = $kid;
                $keys[$kid] = new JWK($jwk);
            } else {
                $keys[] = new JWK($jwk);
            }
        }

        return new JWKSet($keys);
    }
}
