<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement;

use Psr\Cache\CacheItemPoolInterface;
use RuntimeException;
use Symfony\Component\Cache\Adapter\NullAdapter;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use function assert;

/**
 * @see \Jose\Tests\Component\KeyManagement\UrlKeySetFactoryTest
 */
abstract class UrlKeySetFactory
{
    private CacheItemPoolInterface $cacheItemPool;

    private int $expiresAfter = 3600;

    public function __construct(
        private readonly HttpClientInterface $client,
    ) {
        $this->cacheItemPool = new NullAdapter();
    }

    /**
     * @deprecated since 4.1 and will be removed in 5.0. Please use the Http Client to cache the responses instead.
     */
    public function enabledCache(CacheItemPoolInterface $cacheItemPool, int $expiresAfter = 3600): void
    {
        $this->cacheItemPool = $cacheItemPool;
        $this->expiresAfter = $expiresAfter;
    }

    /**
     * @param array<string, string|string[]> $header
     */
    protected function getContent(string $url, array $header = []): string
    {
        $cacheKey = hash('xxh128', $url);
        $item = $this->cacheItemPool->getItem($cacheKey);
        if ($item->isHit()) {
            return $item->get();
        }

        $content = $this->client instanceof HttpClientInterface ? $this->sendSymfonyRequest(
            $url,
            $header
        ) : $this->sendPsrRequest($url, $header);
        $item = $this->cacheItemPool->getItem($cacheKey);
        $item->expiresAfter($this->expiresAfter);
        $item->set($content);
        $this->cacheItemPool->save($item);

        return $content;
    }

    /**
     * @param array<string, string|string[]> $header
     */
    private function sendSymfonyRequest(string $url, array $header = []): string
    {
        assert($this->client instanceof HttpClientInterface);
        $response = $this->client->request('GET', $url, [
            'headers' => $header,
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException('Unable to get the key set.', $response->getStatusCode());
        }

        return $response->getContent();
    }
}
