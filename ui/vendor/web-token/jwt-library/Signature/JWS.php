<?php

declare(strict_types=1);

namespace Jose\Component\Signature;

use InvalidArgumentException;
use Jose\Component\Core\JWT;
use Override;
use function count;

/**
 * @see \Jose\Tests\Component\Signature\JWSTest
 */
class JWS implements JWT
{
    /**
     * @var Signature[]
     */
    private array $signatures = [];

    public function __construct(
        private readonly ?string $payload,
        private readonly ?string $encodedPayload = null,
        private readonly bool $isPayloadDetached = false
    ) {
    }

    #[Override]
    public function getPayload(): ?string
    {
        return $this->payload;
    }

    /**
     * Returns true if the payload is detached.
     */
    public function isPayloadDetached(): bool
    {
        return $this->isPayloadDetached;
    }

    /**
     * Returns the Base64Url encoded payload. If the payload is detached, this method returns null.
     */
    public function getEncodedPayload(): ?string
    {
        if ($this->isPayloadDetached() === true) {
            return null;
        }

        return $this->encodedPayload;
    }

    /**
     * Returns the signatures associated with the JWS.
     *
     * @return Signature[]
     */
    public function getSignatures(): array
    {
        return $this->signatures;
    }

    /**
     * Returns the signature at the given index.
     */
    public function getSignature(int $id): Signature
    {
        if (isset($this->signatures[$id])) {
            return $this->signatures[$id];
        }

        throw new InvalidArgumentException('The signature does not exist.');
    }

    /**
     * This method adds a signature to the JWS object. Its returns a new JWS object.
     *
     * @internal
     *
     * @param array{alg?: string, string?: mixed} $protectedHeader
     * @param array{alg?: string, string?: mixed} $header
     */
    public function addSignature(
        string $signature,
        array $protectedHeader,
        ?string $encodedProtectedHeader,
        array $header = []
    ): self {
        $jws = clone $this;
        $jws->signatures[] = new Signature($signature, $protectedHeader, $encodedProtectedHeader, $header);

        return $jws;
    }

    /**
     * Returns the number of signature associated with the JWS.
     */
    public function countSignatures(): int
    {
        return count($this->signatures);
    }

    /**
     * This method splits the JWS into a list of JWSs. It is only useful when the JWS contains more than one signature
     * (JSON General Serialization).
     *
     * @return JWS[]
     */
    public function split(): array
    {
        $result = [];
        foreach ($this->signatures as $signature) {
            $jws = new self($this->payload, $this->encodedPayload, $this->isPayloadDetached);
            $jws = $jws->addSignature(
                $signature->getSignature(),
                $signature->getProtectedHeader(),
                $signature->getEncodedProtectedHeader(),
                $signature->getHeader()
            );

            $result[] = $jws;
        }

        return $result;
    }
}
