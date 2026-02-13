<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement\Analyzer;

use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\Base64UrlSafe;
use Override;
use SensitiveParameter;
use function count;
use function is_string;
use function strlen;

final readonly class ZxcvbnKeyAnalyzer implements KeyAnalyzer
{
    public const STRENGTH_VERY_WEAK = 0;

    public const STRENGTH_WEAK = 1;

    public const STRENGTH_MEDIUM = 2;

    public const STRENGTH_STRONG = 3;

    public const STRENGTH_VERY_STRONG = 4;

    #[Override]
    public function analyze(JWK $jwk, MessageBag $bag): void
    {
        if ($jwk->get('kty') !== 'oct') {
            return;
        }
        $k = $jwk->get('k');
        if (! is_string($k)) {
            $bag->add(Message::high('The key is not valid'));

            return;
        }
        $k = Base64UrlSafe::decodeNoPadding($k);
        $strength = self::estimateStrength($k);
        switch (true) {
            case $strength < 3:
                $bag->add(
                    Message::high(
                        'The octet string is weak and easily guessable. Please change your key as soon as possible.'
                    )
                );

                break;

            case $strength === 3:
                $bag->add(Message::medium('The octet string is safe, but a longer key is preferable.'));

                break;

            default:
                break;
        }
    }

    /**
     * Returns the estimated strength of a password.
     *
     * The higher the value, the stronger the password.
     *
     * @return self::STRENGTH_*
     */
    private static function estimateStrength(#[SensitiveParameter] string $password): int
    {
        if (! $length = strlen($password)) {
            return self::STRENGTH_VERY_WEAK;
        }
        $password = count_chars($password, 1);
        $chars = count($password);

        $control = $digit = $upper = $lower = $symbol = $other = 0;
        foreach ($password as $chr => $count) {
            match (true) {
                $chr < 32 || $chr === 127 => $control = 33,
                $chr >= 48 && $chr <= 57 => $digit = 10,
                $chr >= 65 && $chr <= 90 => $upper = 26,
                $chr >= 97 && $chr <= 122 => $lower = 26,
                $chr >= 128 => $other = 128,
                default => $symbol = 33,
            };
        }

        $pool = $lower + $upper + $digit + $symbol + $control + $other;
        $entropy = $chars * log($pool, 2) + ($length - $chars) * log($chars, 2);

        return match (true) {
            $entropy >= 120 => self::STRENGTH_VERY_STRONG,
            $entropy >= 100 => self::STRENGTH_STRONG,
            $entropy >= 80 => self::STRENGTH_MEDIUM,
            $entropy >= 60 => self::STRENGTH_WEAK,
            default => self::STRENGTH_VERY_WEAK,
        };
    }
}
