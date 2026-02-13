<?php

declare(strict_types=1);

namespace Jose\Component\Signature\Serializer;

use InvalidArgumentException;
use Jose\Component\Core\Util\Base64UrlSafe;
use Jose\Component\Core\Util\JsonConverter;
use Jose\Component\Signature\JWS;
use LogicException;
use Override;
use function array_key_exists;
use function count;
use function is_array;
use function is_string;

final readonly class JSONGeneralSerializer extends Serializer
{
    public const NAME = 'jws_json_general';

    #[Override]
    public function displayName(): string
    {
        return 'JWS JSON General';
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function serialize(JWS $jws, ?int $signatureIndex = null): string
    {
        if ($jws->countSignatures() === 0) {
            throw new LogicException('No signature.');
        }

        $data = [];
        $this->checkPayloadEncoding($jws);

        if ($jws->isPayloadDetached() === false) {
            $data['payload'] = $jws->getEncodedPayload();
        }

        $data['signatures'] = [];
        foreach ($jws->getSignatures() as $signature) {
            $tmp = [
                'signature' => Base64UrlSafe::encodeUnpadded($signature->getSignature()),
            ];
            $values = [
                'protected' => $signature->getEncodedProtectedHeader(),
                'header' => $signature->getHeader(),
            ];

            foreach ($values as $key => $value) {
                if ((is_string($value) && $value !== '') || (is_array($value) && count($value) !== 0)) {
                    $tmp[$key] = $value;
                }
            }
            $data['signatures'][] = $tmp;
        }

        return JsonConverter::encode($data);
    }

    #[Override]
    public function unserialize(string $input): JWS
    {
        $data = JsonConverter::decode($input);
        if (! is_array($data)) {
            throw new InvalidArgumentException('Unsupported input.');
        }
        if (! isset($data['signatures'])) {
            throw new InvalidArgumentException('Unsupported input.');
        }

        $isPayloadEncoded = null;
        $rawPayload = $data['payload'] ?? null;
        $signatures = [];
        foreach ($data['signatures'] as $signature) {
            if (! isset($signature['signature'])) {
                throw new InvalidArgumentException('Unsupported input.');
            }
            [$encodedProtectedHeader, $protectedHeader, $header] = $this->processHeaders($signature);
            $signatures[] = [
                'signature' => Base64UrlSafe::decodeNoPadding($signature['signature']),
                'protected' => $protectedHeader,
                'encoded_protected' => $encodedProtectedHeader,
                'header' => $header,
            ];
            $isPayloadEncoded = $this->processIsPayloadEncoded($isPayloadEncoded, $protectedHeader);
        }

        $payload = $this->processPayload($rawPayload, $isPayloadEncoded);
        $jws = new JWS($payload, $rawPayload);
        foreach ($signatures as $signature) {
            $jws = $jws->addSignature(
                $signature['signature'],
                $signature['protected'],
                $signature['encoded_protected'],
                $signature['header']
            );
        }

        return $jws;
    }

    /**
     * @param array<string, mixed> $protectedHeader
     */
    private function processIsPayloadEncoded(?bool $isPayloadEncoded, array $protectedHeader): bool
    {
        if ($isPayloadEncoded === null) {
            return $this->isPayloadEncoded($protectedHeader);
        }
        if ($this->isPayloadEncoded($protectedHeader) !== $isPayloadEncoded) {
            throw new InvalidArgumentException('Foreign payload encoding detected.');
        }

        return $isPayloadEncoded;
    }

    /**
     * @param array{protected?: string, header?: array<string, mixed>} $signature
     * @return array<mixed>
     */
    private function processHeaders(array $signature): array
    {
        $encodedProtectedHeader = $signature['protected'] ?? null;
        $protectedHeader = $encodedProtectedHeader === null ? [] : JsonConverter::decode(
            Base64UrlSafe::decodeNoPadding($encodedProtectedHeader)
        );
        $header = array_key_exists('header', $signature) ? $signature['header'] : [];

        return [$encodedProtectedHeader, $protectedHeader, $header];
    }

    private function processPayload(?string $rawPayload, ?bool $isPayloadEncoded): ?string
    {
        if ($rawPayload === null) {
            return null;
        }

        return $isPayloadEncoded === false ? $rawPayload : Base64UrlSafe::decodeNoPadding($rawPayload);
    }

    private function checkPayloadEncoding(JWS $jws): void
    {
        if ($jws->isPayloadDetached()) {
            return;
        }
        $is_encoded = null;
        foreach ($jws->getSignatures() as $signature) {
            if ($is_encoded === null) {
                $is_encoded = $this->isPayloadEncoded($signature->getProtectedHeader());
            }
            if ($is_encoded !== $this->isPayloadEncoded($signature->getProtectedHeader())) {
                throw new LogicException('Foreign payload encoding detected.');
            }
        }
    }
}
