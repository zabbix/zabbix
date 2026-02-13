<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm;

use Jose\Component\Core\Algorithm;

interface KeyEncryptionAlgorithm extends Algorithm
{
    public const MODE_DIRECT = 'dir';

    public const MODE_ENCRYPT = 'enc';

    public const MODE_WRAP = 'wrap';

    public const MODE_AGREEMENT = 'agree';

    /**
     * Returns the key management mode used by the key encryption algorithm.
     */
    public function getKeyManagementMode(): string;
}
