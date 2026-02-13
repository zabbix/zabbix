<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\KeyEncryption;

use Jose\Component\Encryption\Algorithm\KeyEncryption\Util\RSACrypt;
use Override;

final readonly class RSAOAEP256 extends RSA
{
    #[Override]
    public function getEncryptionMode(): int
    {
        return RSACrypt::ENCRYPTION_OAEP;
    }

    #[Override]
    public function getHashAlgorithm(): string
    {
        return 'sha256';
    }

    #[Override]
    public function name(): string
    {
        return 'RSA-OAEP-256';
    }
}
