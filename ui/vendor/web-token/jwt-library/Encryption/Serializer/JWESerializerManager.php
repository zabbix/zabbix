<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Serializer;

use InvalidArgumentException;
use Jose\Component\Encryption\JWE;
use function sprintf;

final class JWESerializerManager
{
    /**
     * @var JWESerializer[]
     */
    private array $serializers = [];

    /**
     * @param JWESerializer[] $serializers
     */
    public function __construct(iterable $serializers)
    {
        foreach ($serializers as $serializer) {
            $this->add($serializer);
        }
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
     * Converts a JWE into a string. Throws an exception if none of the serializer was able to convert the input.
     */
    public function serialize(string $name, JWE $jws, ?int $recipientIndex = null): string
    {
        if (! isset($this->serializers[$name])) {
            throw new InvalidArgumentException(sprintf('Unsupported serializer "%s".', $name));
        }

        return $this->serializers[$name]->serialize($jws, $recipientIndex);
    }

    /**
     * Loads data and return a JWE object. Throws an exception if none of the serializer was able to convert the input.
     *
     * @param string $input A string that represents a JWE
     * @param string|null $name the name of the serializer if the input is unserialized
     */
    public function unserialize(string $input, ?string &$name = null): JWE
    {
        foreach ($this->serializers as $serializer) {
            try {
                $jws = $serializer->unserialize($input);
                $name = $serializer->name();

                return $jws;
            } catch (InvalidArgumentException) {
                continue;
            }
        }

        throw new InvalidArgumentException('Unsupported input.');
    }

    /**
     * Adds a serializer to the manager.
     */
    private function add(JWESerializer $serializer): void
    {
        $this->serializers[$serializer->name()] = $serializer;
    }
}
