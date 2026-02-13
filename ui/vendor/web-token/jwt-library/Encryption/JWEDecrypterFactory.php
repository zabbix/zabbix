<?php

declare(strict_types=1);

namespace Jose\Component\Encryption;

use Jose\Component\Core\AlgorithmManagerFactory;

class JWEDecrypterFactory
{
    public function __construct(
        private readonly AlgorithmManagerFactory $algorithmManagerFactory,
    ) {
    }

    /**
     * @param string[] $encryptionAlgorithms
     */
    public function create(array $encryptionAlgorithms): JWEDecrypter
    {
        $algorithmManager = $this->algorithmManagerFactory->create($encryptionAlgorithms);

        return new JWEDecrypter($algorithmManager);
    }
}
