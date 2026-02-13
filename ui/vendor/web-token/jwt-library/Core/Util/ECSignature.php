<?php

declare(strict_types=1);

namespace Jose\Component\Core\Util;

use InvalidArgumentException;
use function is_string;
use function strlen;
use const STR_PAD_LEFT;

/**
 * @internal
 */
final readonly class ECSignature
{
    private const ASN1_SEQUENCE = '30';

    private const ASN1_INTEGER = '02';

    private const ASN1_MAX_SINGLE_BYTE = 128;

    private const ASN1_LENGTH_2BYTES = '81';

    private const ASN1_BIG_INTEGER_LIMIT = '7f';

    private const ASN1_NEGATIVE_INTEGER = '00';

    private const BYTE_SIZE = 2;

    public static function toAsn1(string $signature, int $length): string
    {
        $signature = bin2hex($signature);

        if (self::octetLength($signature) !== $length) {
            throw new InvalidArgumentException('Invalid signature length.');
        }

        $pointR = self::preparePositiveInteger(substr($signature, 0, $length));
        $pointS = self::preparePositiveInteger(substr($signature, $length));

        $lengthR = self::octetLength($pointR);
        $lengthS = self::octetLength($pointS);

        $totalLength = $lengthR + $lengthS + self::BYTE_SIZE + self::BYTE_SIZE;
        $lengthPrefix = $totalLength > self::ASN1_MAX_SINGLE_BYTE ? self::ASN1_LENGTH_2BYTES : '';

        $bin = hex2bin(
            self::ASN1_SEQUENCE
            . $lengthPrefix . dechex($totalLength)
            . self::ASN1_INTEGER . dechex($lengthR) . $pointR
            . self::ASN1_INTEGER . dechex($lengthS) . $pointS
        );
        if (! is_string($bin)) {
            throw new InvalidArgumentException('Unable to parse the data');
        }

        return $bin;
    }

    public static function fromAsn1(string $signature, int $length): string
    {
        $message = bin2hex($signature);
        $position = 0;

        if (self::readAsn1Content($message, $position, self::BYTE_SIZE) !== self::ASN1_SEQUENCE) {
            throw new InvalidArgumentException('Invalid data. Should start with a sequence.');
        }

        if (self::readAsn1Content($message, $position, self::BYTE_SIZE) === self::ASN1_LENGTH_2BYTES) {
            $position += self::BYTE_SIZE;
        }

        $pointR = self::retrievePositiveInteger(self::readAsn1Integer($message, $position));
        $pointS = self::retrievePositiveInteger(self::readAsn1Integer($message, $position));

        $bin = hex2bin(
            str_pad($pointR, $length, '0', STR_PAD_LEFT) . str_pad($pointS, $length, '0', STR_PAD_LEFT)
        );
        if (! is_string($bin)) {
            throw new InvalidArgumentException('Unable to parse the data');
        }

        return $bin;
    }

    private static function octetLength(string $data): int
    {
        return (int) (strlen($data) / self::BYTE_SIZE);
    }

    private static function preparePositiveInteger(string $data): string
    {
        if (substr($data, 0, self::BYTE_SIZE) > self::ASN1_BIG_INTEGER_LIMIT) {
            return self::ASN1_NEGATIVE_INTEGER . $data;
        }

        while (str_starts_with($data, self::ASN1_NEGATIVE_INTEGER)
            && substr($data, 2, self::BYTE_SIZE) <= self::ASN1_BIG_INTEGER_LIMIT) {
            $data = substr($data, 2);
        }

        return $data;
    }

    private static function readAsn1Content(string $message, int &$position, int $length): string
    {
        $content = substr($message, $position, $length);
        $position += $length;

        return $content;
    }

    private static function readAsn1Integer(string $message, int &$position): string
    {
        if (self::readAsn1Content($message, $position, self::BYTE_SIZE) !== self::ASN1_INTEGER) {
            throw new InvalidArgumentException('Invalid data. Should contain an integer.');
        }

        $length = (int) hexdec(self::readAsn1Content($message, $position, self::BYTE_SIZE));

        return self::readAsn1Content($message, $position, $length * self::BYTE_SIZE);
    }

    private static function retrievePositiveInteger(string $data): string
    {
        while (str_starts_with($data, self::ASN1_NEGATIVE_INTEGER)
            && substr($data, 2, self::BYTE_SIZE) > self::ASN1_BIG_INTEGER_LIMIT) {
            $data = substr($data, 2);
        }

        return $data;
    }
}
