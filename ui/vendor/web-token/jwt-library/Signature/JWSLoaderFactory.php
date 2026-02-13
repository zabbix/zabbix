<?php

declare(strict_types=1);

namespace Jose\Component\Signature;

use Jose\Component\Checker\HeaderCheckerManagerFactory;
use Jose\Component\Signature\Serializer\JWSSerializerManagerFactory;

class JWSLoaderFactory
{
    public function __construct(
        private readonly JWSSerializerManagerFactory $jwsSerializerManagerFactory,
        private readonly JWSVerifierFactory $jwsVerifierFactory,
        private readonly ?HeaderCheckerManagerFactory $headerCheckerManagerFactory
    ) {
    }

    /**
     * Creates a JWSLoader using the given serializer aliases, signature algorithm aliases and (optionally) the header
     * checker aliases.
     */
    /**
     * @param array<string> $serializers
     * @param array<string> $algorithms
     * @param array<string> $headerCheckers
     */
    public function create(array $serializers, array $algorithms, array $headerCheckers = []): JWSLoader
    {
        $serializerManager = $this->jwsSerializerManagerFactory->create($serializers);
        $jwsVerifier = $this->jwsVerifierFactory->create($algorithms);
        if ($this->headerCheckerManagerFactory !== null) {
            $headerCheckerManager = $this->headerCheckerManagerFactory->create($headerCheckers);
        } else {
            $headerCheckerManager = null;
        }

        return new JWSLoader($serializerManager, $jwsVerifier, $headerCheckerManager);
    }
}
