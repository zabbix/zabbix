<?php

declare(strict_types=1);

namespace Jose\Component\Signature\Serializer;

use InvalidArgumentException;
use Jose\Component\Core\Util\Base64UrlSafe;
use Jose\Component\Core\Util\JsonConverter;
use Jose\Component\Signature\JWS;
use LogicException;
use Override;
use Throwable;
use function count;
use function is_array;
use function sprintf;

final readonly class CompactSerializer extends Serializer
{
    public const NAME = 'jws_compact';

    #[Override]
    public function displayName(): string
    {
        return 'JWS Compact';
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function serialize(JWS $jws, ?int $signatureIndex = null): string
    {
        if ($signatureIndex === null) {
            $signatureIndex = 0;
        }
        $signature = $jws->getSignature($signatureIndex);
        if (count($signature->getHeader()) !== 0) {
            throw new LogicException(
                'The signature contains unprotected header parameters and cannot be converted into compact JSON.'
            );
        }
        $isEmptyPayload = $jws->getEncodedPayload() === null || $jws->getEncodedPayload() === '';
        if (! $isEmptyPayload && ! $this->isPayloadEncoded($signature->getProtectedHeader())) {
            if (preg_match('/^[\x{20}-\x{2d}|\x{2f}-\x{7e}]*$/u', $jws->getPayload() ?? '') !== 1) {
                throw new LogicException('Unable to convert the JWS with non-encoded payload.');
            }
        }

        return sprintf(
            '%s.%s.%s',
            $signature->getEncodedProtectedHeader(),
            $jws->getEncodedPayload(),
            Base64UrlSafe::encodeUnpadded($signature->getSignature())
        );
    }

    #[Override]
    public function unserialize(string $input): JWS
    {
        $parts = explode('.', $input);
        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Unsupported input');
        }

        try {
            $encodedProtectedHeader = $parts[0];
            $protectedHeader = JsonConverter::decode(Base64UrlSafe::decodeNoPadding($parts[0]));
            if (! is_array($protectedHeader)) {
                throw new InvalidArgumentException('Bad protected header.');
            }
            $hasPayload = $parts[1] !== '';
            if (! $hasPayload) {
                $payload = null;
                $encodedPayload = null;
            } else {
                $encodedPayload = $parts[1];
                $payload = $this->isPayloadEncoded($protectedHeader) ? Base64UrlSafe::decodeNoPadding(
                    $encodedPayload
                ) : $encodedPayload;
            }
            $signature = Base64UrlSafe::decodeNoPadding($parts[2]);

            $jws = new JWS($payload, $encodedPayload, ! $hasPayload);

            return $jws->addSignature($signature, $protectedHeader, $encodedProtectedHeader);
        } catch (Throwable $throwable) {
            throw new InvalidArgumentException('Unsupported input', $throwable->getCode(), $throwable);
        }
    }
}
