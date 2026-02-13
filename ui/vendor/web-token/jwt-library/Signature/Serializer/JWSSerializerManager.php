<?php

declare(strict_types=1);

namespace Jose\Component\Signature\Serializer;

use InvalidArgumentException;
use Jose\Component\Signature\JWS;
use function sprintf;

final class JWSSerializerManager
{
    /**
     * @var JWSSerializer[]
     */
    private array $serializers = [];

    /**
     * @param JWSSerializer[] $serializers
     */
    public function __construct(iterable $serializers)
    {
        foreach ($serializers as $serializer) {
            $this->add($serializer);
        }
    }

    /**
     * @return string[]
     */
    public function list(): array
    {
        return array_keys($this->serializers);
    }

    /**
     * Converts a JWS into a string.
     */
    public function serialize(string $name, JWS $jws, ?int $signatureIndex = null): string
    {
        if (! isset($this->serializers[$name])) {
            throw new InvalidArgumentException(sprintf('Unsupported serializer "%s".', $name));
        }

        return $this->serializers[$name]->serialize($jws, $signatureIndex);
    }

    /**
     * Loads data and return a JWS object.
     *
     * @param string $input A string that represents a JWS
     * @param string|null $name the name of the serializer if the input is unserialized
     */
    public function unserialize(string $input, ?string &$name = null): JWS
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

    private function add(JWSSerializer $serializer): void
    {
        $this->serializers[$serializer->name()] = $serializer;
    }
}
