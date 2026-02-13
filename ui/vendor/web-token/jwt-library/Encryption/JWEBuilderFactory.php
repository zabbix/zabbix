<?php

declare(strict_types=1);

namespace Jose\Component\Encryption;

use Jose\Component\Core\AlgorithmManagerFactory;

class JWEBuilderFactory
{
    public function __construct(
        private readonly AlgorithmManagerFactory $algorithmManagerFactory,
    ) {
    }

    /**
     * @param string[] $encryptionAlgorithms
     */
    public function create(array $encryptionAlgorithms): JWEBuilder
    {
        $algorithmManager = $this->algorithmManagerFactory->create($encryptionAlgorithms);

        return new JWEBuilder($algorithmManager);
    }
}
