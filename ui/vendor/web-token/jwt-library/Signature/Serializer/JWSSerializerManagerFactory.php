<?php

declare(strict_types=1);

namespace Jose\Component\Signature\Serializer;

use InvalidArgumentException;
use function sprintf;

final class JWSSerializerManagerFactory
{
    /**
     * @var JWSSerializer[]
     */
    private array $serializers = [];

    /**
     * @param string[] $names
     */
    public function create(array $names): JWSSerializerManager
    {
        $serializers = [];
        foreach ($names as $name) {
            if (! isset($this->serializers[$name])) {
                throw new InvalidArgumentException(sprintf('Unsupported serializer "%s".', $name));
            }
            $serializers[] = $this->serializers[$name];
        }

        return new JWSSerializerManager($serializers);
    }

    /**
     * @return string[]
     */
    public function names(): array
    {
        return array_keys($this->serializers);
    }

    /**
     * @return JWSSerializer[]
     */
    public function all(): array
    {
        return $this->serializers;
    }

    public function add(JWSSerializer $serializer): void
    {
        $this->serializers[$serializer->name()] = $serializer;
    }
}
