<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Serializer;

use InvalidArgumentException;
use function sprintf;

final class JWESerializerManagerFactory
{
    /**
     * @var JWESerializer[]
     */
    private array $serializers = [];

    /**
     * Creates a serializer manager factory using the given serializers.
     *
     * @param string[] $names
     */
    public function create(array $names): JWESerializerManager
    {
        $serializers = [];
        foreach ($names as $name) {
            if (! isset($this->serializers[$name])) {
                throw new InvalidArgumentException(sprintf('Unsupported serializer "%s".', $name));
            }
            $serializers[] = $this->serializers[$name];
        }

        return new JWESerializerManager($serializers);
    }

    /**
     * Return the serializer names supported by the manager.
     *
     * @return string[]
     */
    public function names(): array
    {
        return array_keys($this->serializers);
    }

    /**
     * Returns all serializers supported by this factory.
     *
     * @return JWESerializer[]
     */
    public function all(): array
    {
        return $this->serializers;
    }

    /**
     * Adds a serializer to the manager.
     */
    public function add(JWESerializer $serializer): void
    {
        $this->serializers[$serializer->name()] = $serializer;
    }
}
