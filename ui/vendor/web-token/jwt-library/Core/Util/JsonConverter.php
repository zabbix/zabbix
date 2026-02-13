<?php

declare(strict_types=1);

namespace Jose\Component\Core\Util;

use InvalidArgumentException;
use Throwable;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;
use const JSON_UNESCAPED_UNICODE;

final readonly class JsonConverter
{
    public static function encode(mixed $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (Throwable $throwable) {
            throw new InvalidArgumentException('Invalid content.', $throwable->getCode(), $throwable);
        }
    }

    public static function decode(string $payload): mixed
    {
        try {
            return json_decode(
                $payload,
                true,
                512,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            );
        } catch (Throwable $throwable) {
            throw new InvalidArgumentException('Unsupported input.', $throwable->getCode(), $throwable);
        }
    }
}
