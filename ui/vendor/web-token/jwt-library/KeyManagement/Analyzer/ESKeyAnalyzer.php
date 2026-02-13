<?php

declare(strict_types=1);

namespace Jose\Component\KeyManagement\Analyzer;

use Brick\Math\BigInteger;
use Jose\Component\Core\JWK;
use Jose\Component\Core\Util\Base64UrlSafe;
use Jose\Component\Core\Util\Ecc\Curve;
use Override;
use function is_string;
use function sprintf;
use function strlen;

abstract readonly class ESKeyAnalyzer implements KeyAnalyzer
{
    #[Override]
    public function analyze(JWK $jwk, MessageBag $bag): void
    {
        if ($jwk->get('kty') !== 'EC') {
            return;
        }
        if (! $jwk->has('crv')) {
            $bag->add(Message::high('Invalid key. The components "crv" is missing.'));

            return;
        }
        if ($jwk->get('crv') !== $this->getCurveName()) {
            return;
        }
        $x = $jwk->get('x');
        if (! is_string($x)) {
            $bag->add(Message::high('Invalid key. The components "x" shall be a string.'));

            return;
        }
        $x = Base64UrlSafe::decodeNoPadding($x);
        $xLength = 8 * strlen($x);
        $y = $jwk->get('y');
        if (! is_string($y)) {
            $bag->add(Message::high('Invalid key. The components "y" shall be a string.'));

            return;
        }
        $y = Base64UrlSafe::decodeNoPadding($y);
        $yLength = 8 * strlen($y);
        if ($yLength !== $xLength || $yLength !== $this->getKeySize()) {
            $bag->add(
                Message::high(sprintf(
                    'Invalid key. The components "x" and "y" size shall be %d bits.',
                    $this->getKeySize()
                ))
            );
        }
        $xBI = BigInteger::fromBase(bin2hex($x), 16);
        $yBI = BigInteger::fromBase(bin2hex($y), 16);
        if (! $this->getCurve()->contains($xBI, $yBI)) {
            $bag->add(Message::high('Invalid key. The point is not on the curve.'));
        }
    }

    abstract protected function getAlgorithmName(): string;

    abstract protected function getCurveName(): string;

    abstract protected function getCurve(): Curve;

    abstract protected function getKeySize(): int;
}
