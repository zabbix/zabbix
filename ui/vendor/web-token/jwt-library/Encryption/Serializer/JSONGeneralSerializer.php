<?php

declare(strict_types=1);

namespace Jose\Component\Encryption\Serializer;

use InvalidArgumentException;
use Jose\Component\Core\Util\Base64UrlSafe;
use Jose\Component\Core\Util\JsonConverter;
use Jose\Component\Encryption\JWE;
use Jose\Component\Encryption\Recipient;
use LogicException;
use Override;
use function array_key_exists;
use function count;
use function is_array;

final readonly class JSONGeneralSerializer implements JWESerializer
{
    public const NAME = 'jwe_json_general';

    #[Override]
    public function displayName(): string
    {
        return 'JWE JSON General';
    }

    #[Override]
    public function name(): string
    {
        return self::NAME;
    }

    #[Override]
    public function serialize(JWE $jwe, ?int $recipientIndex = null): string
    {
        if ($jwe->countRecipients() === 0) {
            throw new LogicException('No recipient.');
        }

        $data = [
            'ciphertext' => Base64UrlSafe::encodeUnpadded($jwe->getCiphertext() ?? ''),
            'iv' => Base64UrlSafe::encodeUnpadded($jwe->getIV() ?? ''),
            'tag' => Base64UrlSafe::encodeUnpadded($jwe->getTag() ?? ''),
        ];
        if ($jwe->getAAD() !== null) {
            $data['aad'] = Base64UrlSafe::encodeUnpadded($jwe->getAAD());
        }
        if (count($jwe->getSharedProtectedHeader()) !== 0) {
            $data['protected'] = $jwe->getEncodedSharedProtectedHeader();
        }
        if (count($jwe->getSharedHeader()) !== 0) {
            $data['unprotected'] = $jwe->getSharedHeader();
        }
        $data['recipients'] = [];
        foreach ($jwe->getRecipients() as $recipient) {
            $temp = [];
            if (count($recipient->getHeader()) !== 0) {
                $temp['header'] = $recipient->getHeader();
            }
            if ($recipient->getEncryptedKey() !== null) {
                $temp['encrypted_key'] = Base64UrlSafe::encodeUnpadded($recipient->getEncryptedKey());
            }
            $data['recipients'][] = $temp;
        }

        return JsonConverter::encode($data);
    }

    #[Override]
    public function unserialize(string $input): JWE
    {
        $data = JsonConverter::decode($input);
        if (! is_array($data)) {
            throw new InvalidArgumentException('Unsupported input.');
        }
        $this->checkData($data);

        $ciphertext = Base64UrlSafe::decodeNoPadding($data['ciphertext']);
        $iv = Base64UrlSafe::decodeNoPadding($data['iv']);
        $tag = Base64UrlSafe::decodeNoPadding($data['tag']);
        $aad = array_key_exists('aad', $data) ? Base64UrlSafe::decodeNoPadding($data['aad']) : null;
        [$encodedSharedProtectedHeader, $sharedProtectedHeader, $sharedHeader] = $this->processHeaders($data);
        $recipients = [];
        foreach ($data['recipients'] as $recipient) {
            [$encryptedKey, $header] = $this->processRecipient($recipient);
            $recipients[] = new Recipient($header, $encryptedKey);
        }

        return new JWE(
            $ciphertext,
            $iv,
            $tag,
            $aad,
            $sharedHeader,
            $sharedProtectedHeader,
            $encodedSharedProtectedHeader,
            $recipients
        );
    }

    private function checkData(?array $data): void
    {
        if ($data === null || ! isset($data['ciphertext']) || ! isset($data['recipients'])) {
            throw new InvalidArgumentException('Unsupported input.');
        }
    }

    private function processRecipient(array $recipient): array
    {
        $encryptedKey = array_key_exists('encrypted_key', $recipient) ? Base64UrlSafe::decodeNoPadding(
            $recipient['encrypted_key']
        ) : null;
        $header = array_key_exists('header', $recipient) ? $recipient['header'] : [];

        return [$encryptedKey, $header];
    }

    private function processHeaders(array $data): array
    {
        $encodedSharedProtectedHeader = array_key_exists('protected', $data) ? $data['protected'] : null;
        $sharedProtectedHeader = $encodedSharedProtectedHeader ? JsonConverter::decode(
            Base64UrlSafe::decodeNoPadding($encodedSharedProtectedHeader)
        ) : [];
        $sharedHeader = array_key_exists('unprotected', $data) ? $data['unprotected'] : [];

        return [$encodedSharedProtectedHeader, $sharedProtectedHeader, $sharedHeader];
    }
}
