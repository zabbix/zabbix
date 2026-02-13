<?php

declare(strict_types=1);

namespace Jose\Component\Encryption;

use InvalidArgumentException;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\Base64UrlSafe;
use Jose\Component\Core\Util\JsonConverter;
use Jose\Component\Core\Util\KeyChecker;
use Jose\Component\Encryption\Algorithm\ContentEncryptionAlgorithm;
use Jose\Component\Encryption\Algorithm\KeyEncryption\DirectEncryption;
use Jose\Component\Encryption\Algorithm\KeyEncryption\KeyAgreement;
use Jose\Component\Encryption\Algorithm\KeyEncryption\KeyAgreementWithKeyWrapping;
use Jose\Component\Encryption\Algorithm\KeyEncryption\KeyEncryption;
use Jose\Component\Encryption\Algorithm\KeyEncryption\KeyWrapping;
use Jose\Component\Encryption\Algorithm\KeyEncryptionAlgorithm;
use LogicException;
use RuntimeException;
use function array_key_exists;
use function count;
use function is_string;
use function sprintf;

class JWEBuilder
{
    protected ?JWK $senderKey = null;

    protected ?string $payload = null;

    protected ?string $aad = null;

    protected array $recipients = [];

    protected array $sharedProtectedHeader = [];

    protected array $sharedHeader = [];

    private ?string $keyManagementMode = null;

    private ?ContentEncryptionAlgorithm $contentEncryptionAlgorithm = null;

    private readonly AlgorithmManager $keyEncryptionAlgorithmManager;

    private readonly AlgorithmManager $contentEncryptionAlgorithmManager;

    public function __construct(AlgorithmManager $algorithmManager)
    {
        $keyEncryptionAlgorithms = [];
        $contentEncryptionAlgorithms = [];
        foreach ($algorithmManager->all() as $algorithm) {
            if ($algorithm instanceof KeyEncryptionAlgorithm) {
                $keyEncryptionAlgorithms[] = $algorithm;
            }
            if ($algorithm instanceof ContentEncryptionAlgorithm) {
                $contentEncryptionAlgorithms[] = $algorithm;
            }
        }
        $this->keyEncryptionAlgorithmManager = new AlgorithmManager($keyEncryptionAlgorithms);
        $this->contentEncryptionAlgorithmManager = new AlgorithmManager($contentEncryptionAlgorithms);
    }

    /**
     * Reset the current data.
     */
    public function create(): self
    {
        $this->senderKey = null;
        $this->payload = null;
        $this->aad = null;
        $this->recipients = [];
        $this->sharedProtectedHeader = [];
        $this->sharedHeader = [];
        $this->keyManagementMode = null;

        return $this;
    }

    /**
     * Returns the key encryption algorithm manager.
     */
    public function getKeyEncryptionAlgorithmManager(): AlgorithmManager
    {
        return $this->keyEncryptionAlgorithmManager;
    }

    /**
     * Returns the content encryption algorithm manager.
     */
    public function getContentEncryptionAlgorithmManager(): AlgorithmManager
    {
        return $this->contentEncryptionAlgorithmManager;
    }

    /**
     * Set the payload of the JWE to build.
     */
    public function withPayload(string $payload): self
    {
        $clone = clone $this;
        $clone->payload = $payload;

        return $clone;
    }

    /**
     * Set the Additional Authenticated Data of the JWE to build.
     */
    public function withAAD(?string $aad): self
    {
        $clone = clone $this;
        $clone->aad = $aad;

        return $clone;
    }

    /**
     * Set the shared protected header of the JWE to build.
     */
    public function withSharedProtectedHeader(array $sharedProtectedHeader): self
    {
        $this->checkDuplicatedHeaderParameters($sharedProtectedHeader, $this->sharedHeader);
        foreach ($this->recipients as $recipient) {
            $this->checkDuplicatedHeaderParameters($sharedProtectedHeader, $recipient->getHeader());
        }
        $clone = clone $this;
        $clone->sharedProtectedHeader = $sharedProtectedHeader;

        return $clone;
    }

    /**
     * Set the shared header of the JWE to build.
     */
    public function withSharedHeader(array $sharedHeader): self
    {
        $this->checkDuplicatedHeaderParameters($this->sharedProtectedHeader, $sharedHeader);
        foreach ($this->recipients as $recipient) {
            $this->checkDuplicatedHeaderParameters($sharedHeader, $recipient->getHeader());
        }
        $clone = clone $this;
        $clone->sharedHeader = $sharedHeader;

        return $clone;
    }

    /**
     * Adds a recipient to the JWE to build.
     */
    public function addRecipient(JWK $recipientKey, array $recipientHeader = []): self
    {
        $this->checkDuplicatedHeaderParameters($this->sharedProtectedHeader, $recipientHeader);
        $this->checkDuplicatedHeaderParameters($this->sharedHeader, $recipientHeader);
        $clone = clone $this;
        $completeHeader = array_merge($clone->sharedHeader, $recipientHeader, $clone->sharedProtectedHeader);
        $clone->checkAndSetContentEncryptionAlgorithm($completeHeader);
        $keyEncryptionAlgorithm = $clone->getKeyEncryptionAlgorithm($completeHeader);
        if ($clone->keyManagementMode === null) {
            $clone->keyManagementMode = $keyEncryptionAlgorithm->getKeyManagementMode();
        } else {
            if (! $clone->areKeyManagementModesCompatible(
                $clone->keyManagementMode,
                $keyEncryptionAlgorithm->getKeyManagementMode()
            )) {
                throw new InvalidArgumentException('Foreign key management mode forbidden.');
            }
        }

        $clone->checkKey($keyEncryptionAlgorithm, $recipientKey);
        $clone->recipients[] = [
            'key' => $recipientKey,
            'header' => $recipientHeader,
            'key_encryption_algorithm' => $keyEncryptionAlgorithm,
        ];

        return $clone;
    }

    //TODO: Verify if the key is compatible with the key encryption algorithm like is done to the ECDH-ES
    /**
     * Set the sender JWK to be used instead of the internal generated JWK
     */
    public function withSenderKey(JWK $senderKey): self
    {
        $clone = clone $this;
        $completeHeader = array_merge($clone->sharedHeader, $clone->sharedProtectedHeader);
        $keyEncryptionAlgorithm = $clone->getKeyEncryptionAlgorithm($completeHeader);
        if ($clone->keyManagementMode === null) {
            $clone->keyManagementMode = $keyEncryptionAlgorithm->getKeyManagementMode();
        } else {
            if (! $clone->areKeyManagementModesCompatible(
                $clone->keyManagementMode,
                $keyEncryptionAlgorithm->getKeyManagementMode()
            )) {
                throw new InvalidArgumentException('Foreign key management mode forbidden.');
            }
        }
        $clone->checkKey($keyEncryptionAlgorithm, $senderKey);
        $clone->senderKey = $senderKey;

        return $clone;
    }

    /**
     * Builds the JWE.
     */
    public function build(): JWE
    {
        if ($this->payload === null) {
            throw new LogicException('Payload not set.');
        }
        if (count($this->recipients) === 0) {
            throw new LogicException('No recipient.');
        }

        $additionalHeader = [];
        $cek = $this->determineCEK($additionalHeader);

        $recipients = [];
        foreach ($this->recipients as $recipient) {
            $recipient = $this->processRecipient($recipient, $cek, $additionalHeader);
            $recipients[] = $recipient;
        }

        if ((is_countable($additionalHeader) ? count($additionalHeader) : 0) !== 0 && count($this->recipients) === 1) {
            $sharedProtectedHeader = array_merge($additionalHeader, $this->sharedProtectedHeader);
        } else {
            $sharedProtectedHeader = $this->sharedProtectedHeader;
        }
        $encodedSharedProtectedHeader = count($sharedProtectedHeader) === 0 ? '' : Base64UrlSafe::encodeUnpadded(
            JsonConverter::encode($sharedProtectedHeader)
        );

        [$ciphertext, $iv, $tag] = $this->encryptJWE($cek, $encodedSharedProtectedHeader);

        return new JWE(
            $ciphertext,
            $iv,
            $tag,
            $this->aad,
            $this->sharedHeader,
            $sharedProtectedHeader,
            $encodedSharedProtectedHeader,
            $recipients
        );
    }

    private function checkAndSetContentEncryptionAlgorithm(array $completeHeader): void
    {
        $contentEncryptionAlgorithm = $this->getContentEncryptionAlgorithm($completeHeader);
        if ($this->contentEncryptionAlgorithm === null) {
            $this->contentEncryptionAlgorithm = $contentEncryptionAlgorithm;
        } elseif ($contentEncryptionAlgorithm->name() !== $this->contentEncryptionAlgorithm->name()) {
            throw new InvalidArgumentException('Inconsistent content encryption algorithm');
        }
    }

    private function processRecipient(array $recipient, string $cek, array &$additionalHeader): Recipient
    {
        $completeHeader = array_merge($this->sharedHeader, $recipient['header'], $this->sharedProtectedHeader);
        $keyEncryptionAlgorithm = $recipient['key_encryption_algorithm'];
        if (! $keyEncryptionAlgorithm instanceof KeyEncryptionAlgorithm) {
            throw new InvalidArgumentException('The key encryption algorithm is not valid');
        }
        $encryptedContentEncryptionKey = $this->getEncryptedKey(
            $completeHeader,
            $cek,
            $keyEncryptionAlgorithm,
            $additionalHeader,
            $recipient['key'],
            $recipient['sender_key'] ?? $this->senderKey ?? null
        );
        $recipientHeader = $recipient['header'];
        if ((is_countable($additionalHeader) ? count($additionalHeader) : 0) !== 0 && count($this->recipients) !== 1) {
            $recipientHeader = array_merge($recipientHeader, $additionalHeader);
            $additionalHeader = [];
        }

        return new Recipient($recipientHeader, $encryptedContentEncryptionKey);
    }

    private function encryptJWE(string $cek, string $encodedSharedProtectedHeader): array
    {
        if (! $this->contentEncryptionAlgorithm instanceof ContentEncryptionAlgorithm) {
            throw new InvalidArgumentException('The content encryption algorithm is not valid');
        }
        $iv_size = $this->contentEncryptionAlgorithm->getIVSize();
        $iv = $this->createIV($iv_size);
        $payload = $this->payload;
        $tag = null;
        $ciphertext = $this->contentEncryptionAlgorithm->encryptContent(
            $payload ?? '',
            $cek,
            $iv,
            $this->aad,
            $encodedSharedProtectedHeader,
            $tag
        );

        return [$ciphertext, $iv, $tag];
    }

    private function getEncryptedKey(
        array $completeHeader,
        string $cek,
        KeyEncryptionAlgorithm $keyEncryptionAlgorithm,
        array &$additionalHeader,
        JWK $recipientKey,
        ?JWK $senderKey
    ): ?string {
        if ($keyEncryptionAlgorithm instanceof KeyEncryption) {
            return $this->getEncryptedKeyFromKeyEncryptionAlgorithm(
                $completeHeader,
                $cek,
                $keyEncryptionAlgorithm,
                $recipientKey,
                $additionalHeader
            );
        }
        if ($keyEncryptionAlgorithm instanceof KeyWrapping) {
            return $this->getEncryptedKeyFromKeyWrappingAlgorithm(
                $completeHeader,
                $cek,
                $keyEncryptionAlgorithm,
                $recipientKey,
                $additionalHeader
            );
        }
        if ($keyEncryptionAlgorithm instanceof KeyAgreementWithKeyWrapping) {
            return $this->getEncryptedKeyFromKeyAgreementAndKeyWrappingAlgorithm(
                $completeHeader,
                $cek,
                $keyEncryptionAlgorithm,
                $additionalHeader,
                $recipientKey,
                $senderKey
            );
        }
        if ($keyEncryptionAlgorithm instanceof KeyAgreement) {
            return null;
        }
        if ($keyEncryptionAlgorithm instanceof DirectEncryption) {
            return null;
        }

        throw new InvalidArgumentException('Unsupported key encryption algorithm.');
    }

    private function getEncryptedKeyFromKeyAgreementAndKeyWrappingAlgorithm(
        array $completeHeader,
        string $cek,
        KeyAgreementWithKeyWrapping $keyEncryptionAlgorithm,
        array &$additionalHeader,
        JWK $recipientKey,
        ?JWK $senderKey
    ): string {
        if ($this->contentEncryptionAlgorithm === null) {
            throw new InvalidArgumentException('Invalid content encryption algorithm');
        }

        return $keyEncryptionAlgorithm->wrapAgreementKey(
            $recipientKey,
            $senderKey,
            $cek,
            $this->contentEncryptionAlgorithm->getCEKSize(),
            $completeHeader,
            $additionalHeader
        );
    }

    private function getEncryptedKeyFromKeyEncryptionAlgorithm(
        array $completeHeader,
        string $cek,
        KeyEncryption $keyEncryptionAlgorithm,
        JWK $recipientKey,
        array &$additionalHeader
    ): string {
        return $keyEncryptionAlgorithm->encryptKey($recipientKey, $cek, $completeHeader, $additionalHeader);
    }

    private function getEncryptedKeyFromKeyWrappingAlgorithm(
        array $completeHeader,
        string $cek,
        KeyWrapping $keyEncryptionAlgorithm,
        JWK $recipientKey,
        array &$additionalHeader
    ): string {
        return $keyEncryptionAlgorithm->wrapKey($recipientKey, $cek, $completeHeader, $additionalHeader);
    }

    private function checkKey(KeyEncryptionAlgorithm $keyEncryptionAlgorithm, JWK $recipientKey): void
    {
        if ($this->contentEncryptionAlgorithm === null) {
            throw new InvalidArgumentException('Invalid content encryption algorithm');
        }

        KeyChecker::checkKeyUsage($recipientKey, 'encryption');
        if ($keyEncryptionAlgorithm->name() !== 'dir') {
            KeyChecker::checkKeyAlgorithm($recipientKey, $keyEncryptionAlgorithm->name());
        } else {
            KeyChecker::checkKeyAlgorithm($recipientKey, $this->contentEncryptionAlgorithm->name());
        }
    }

    private function determineCEK(array &$additionalHeader): string
    {
        if ($this->contentEncryptionAlgorithm === null) {
            throw new InvalidArgumentException('Invalid content encryption algorithm');
        }

        switch ($this->keyManagementMode) {
            case KeyEncryption::MODE_ENCRYPT:
            case KeyEncryption::MODE_WRAP:
                return $this->createCEK($this->contentEncryptionAlgorithm->getCEKSize());

            case KeyEncryption::MODE_AGREEMENT:
                if (count($this->recipients) !== 1) {
                    throw new LogicException(
                        'Unable to encrypt for multiple recipients using key agreement algorithms.'
                    );
                }
                $recipientKey = $this->recipients[0]['key'];
                $senderKey = $this->recipients[0]['sender_key'] ?? null;
                $algorithm = $this->recipients[0]['key_encryption_algorithm'];
                if (! $algorithm instanceof KeyAgreement) {
                    throw new InvalidArgumentException('Invalid content encryption algorithm');
                }
                $completeHeader = array_merge(
                    $this->sharedHeader,
                    $this->recipients[0]['header'],
                    $this->sharedProtectedHeader
                );

                return $algorithm->getAgreementKey(
                    $this->contentEncryptionAlgorithm->getCEKSize(),
                    $this->contentEncryptionAlgorithm->name(),
                    $recipientKey,
                    $senderKey,
                    $completeHeader,
                    $additionalHeader
                );

            case KeyEncryption::MODE_DIRECT:
                if (count($this->recipients) !== 1) {
                    throw new LogicException(
                        'Unable to encrypt for multiple recipients using key agreement algorithms.'
                    );
                }
                /** @var JWK $key */
                $key = $this->recipients[0]['key'];
                if ($key->get('kty') !== 'oct') {
                    throw new RuntimeException('Wrong key type.');
                }
                $k = $key->get('k');
                if (! is_string($k)) {
                    throw new RuntimeException('Invalid key.');
                }

                return Base64UrlSafe::decodeNoPadding($k);

            default:
                throw new InvalidArgumentException(sprintf(
                    'Unsupported key management mode "%s".',
                    $this->keyManagementMode
                ));
        }
    }

    private function areKeyManagementModesCompatible(string $current, string $new): bool
    {
        $agree = KeyEncryptionAlgorithm::MODE_AGREEMENT;
        $dir = KeyEncryptionAlgorithm::MODE_DIRECT;
        $enc = KeyEncryptionAlgorithm::MODE_ENCRYPT;
        $wrap = KeyEncryptionAlgorithm::MODE_WRAP;
        $supportedKeyManagementModeCombinations = [
            $enc . $enc => true,
            $enc . $wrap => true,
            $wrap . $enc => true,
            $wrap . $wrap => true,
            $agree . $agree => false,
            $agree . $dir => false,
            $agree . $enc => false,
            $agree . $wrap => false,
            $dir . $agree => false,
            $dir . $dir => false,
            $dir . $enc => false,
            $dir . $wrap => false,
            $enc . $agree => false,
            $enc . $dir => false,
            $wrap . $agree => false,
            $wrap . $dir => false,
        ];

        if (array_key_exists($current . $new, $supportedKeyManagementModeCombinations)) {
            return $supportedKeyManagementModeCombinations[$current . $new];
        }

        return false;
    }

    private function createCEK(int $size): string
    {
        return random_bytes($size / 8);
    }

    private function createIV(int $size): string
    {
        return random_bytes($size / 8);
    }

    private function getKeyEncryptionAlgorithm(array $completeHeader): KeyEncryptionAlgorithm
    {
        if (! isset($completeHeader['alg'])) {
            throw new InvalidArgumentException('Parameter "alg" is missing.');
        }
        $keyEncryptionAlgorithm = $this->keyEncryptionAlgorithmManager->get($completeHeader['alg']);
        if (! $keyEncryptionAlgorithm instanceof KeyEncryptionAlgorithm) {
            throw new InvalidArgumentException(sprintf(
                'The key encryption algorithm "%s" is not supported or not a key encryption algorithm instance.',
                $completeHeader['alg']
            ));
        }

        return $keyEncryptionAlgorithm;
    }

    private function getContentEncryptionAlgorithm(array $completeHeader): ContentEncryptionAlgorithm
    {
        if (! isset($completeHeader['enc'])) {
            throw new InvalidArgumentException('Parameter "enc" is missing.');
        }
        $contentEncryptionAlgorithm = $this->contentEncryptionAlgorithmManager->get($completeHeader['enc']);
        if (! $contentEncryptionAlgorithm instanceof ContentEncryptionAlgorithm) {
            throw new InvalidArgumentException(sprintf(
                'The content encryption algorithm "%s" is not supported or not a content encryption algorithm instance.',
                $completeHeader['enc']
            ));
        }

        return $contentEncryptionAlgorithm;
    }

    private function checkDuplicatedHeaderParameters(array $header1, array $header2): void
    {
        $inter = array_intersect_key($header1, $header2);
        if (count($inter) !== 0) {
            throw new InvalidArgumentException(sprintf(
                'The header contains duplicated entries: %s.',
                implode(', ', array_keys($inter))
            ));
        }
    }
}
