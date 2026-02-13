<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Algorithm\KeyEncryption;

use Jose\Component\Core\JWK;
use Override;

abstract readonly class ECDHESAESKW extends AbstractECDHAESKW
{
    /**
     * @param array<string, mixed> $complete_header
     * @param array<string, mixed> $additional_header_values
     */
    #[Override]
    public function wrapAgreementKey(
        JWK $recipientKey,
        ?JWK $senderKey,
        string $cek,
        int $encryption_key_length,
        array $complete_header,
        array &$additional_header_values
    ): string {
        $ecdh_es = new ECDHES();
        $agreement_key = $ecdh_es->getAgreementKey(
            $this->getKeyLength(),
            $this->name(),
            $recipientKey->toPublic(),
            $senderKey,
            $complete_header,
            $additional_header_values
        );
        $wrapper = $this->getWrapper();

        return $wrapper::wrap($agreement_key, $cek);
    }

    /**
     * @param array<string, mixed> $complete_header
     */
    #[Override]
    public function unwrapAgreementKey(
        JWK $recipientKey,
        ?JWK $senderKey,
        string $encrypted_cek,
        int $encryption_key_length,
        array $complete_header
    ): string {
        $ecdh_es = new ECDHES();
        $agreement_key = $ecdh_es->getAgreementKey(
            $this->getKeyLength(),
            $this->name(),
            $recipientKey,
            $senderKey,
            $complete_header
        );
        $wrapper = $this->getWrapper();

        return $wrapper::unwrap($agreement_key, $encrypted_cek);
    }
}
