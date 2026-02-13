<?php

declare(strict_types=1);

namespace Jose\Component\Core\Util\Ecc;

use Brick\Math\BigInteger;
use Override;
use RuntimeException;
use Stringable;
use const STR_PAD_LEFT;

/**
 * @internal
 */
final readonly class Curve implements Stringable
{
    public function __construct(
        private int $size,
        private BigInteger $prime,
        private BigInteger $a,
        private BigInteger $b,
        private Point $generator
    ) {
    }

    #[Override]
    public function __toString(): string
    {
        return 'curve(' . Math::toString($this->getA()) . ', ' . Math::toString($this->getB()) . ', ' . Math::toString(
            $this->getPrime()
        ) . ')';
    }

    public function getA(): BigInteger
    {
        return $this->a;
    }

    public function getB(): BigInteger
    {
        return $this->b;
    }

    public function getPrime(): BigInteger
    {
        return $this->prime;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function getPoint(BigInteger $x, BigInteger $y, ?BigInteger $order = null): Point
    {
        if (! $this->contains($x, $y)) {
            throw new RuntimeException('Curve ' . $this->__toString() . ' does not contain point (' . Math::toString(
                $x
            ) . ', ' . Math::toString($y) . ')');
        }
        $point = Point::create($x, $y, $order);
        if ($order !== null) {
            $mul = $this->mul($point, $order);
            if (! $mul->isInfinity()) {
                throw new RuntimeException('SELF * ORDER MUST EQUAL INFINITY.');
            }
        }

        return $point;
    }

    public function getPublicKeyFrom(BigInteger $x, BigInteger $y): PublicKey
    {
        $zero = BigInteger::zero();
        if ($x->compareTo($zero) < 0 || $y->compareTo($zero) < 0 || $this->generator->getOrder()->compareTo(
            $x
        ) <= 0 || $this->generator->getOrder()
            ->compareTo($y) <= 0) {
            throw new RuntimeException('Generator point has x and y out of range.');
        }
        $point = $this->getPoint($x, $y);

        return new PublicKey($point);
    }

    public function contains(BigInteger $x, BigInteger $y): bool
    {
        return Math::equals(
            ModularArithmetic::sub(
                $y->power(2),
                Math::add(Math::add($x->power(3), $this->getA()->multipliedBy($x)), $this->getB()),
                $this->getPrime()
            ),
            BigInteger::zero()
        );
    }

    public function add(Point $one, Point $two): Point
    {
        if ($two->isInfinity()) {
            return clone $one;
        }

        if ($one->isInfinity()) {
            return clone $two;
        }

        if ($two->getX()->isEqualTo($one->getX())) {
            if ($two->getY()->isEqualTo($one->getY())) {
                return $this->getDouble($one);
            }

            return Point::infinity();
        }

        $slope = ModularArithmetic::div(
            $two->getY()
                ->minus($one->getY()),
            $two->getX()
                ->minus($one->getX()),
            $this->getPrime()
        );

        $xR = ModularArithmetic::sub($slope->power(2)->minus($one->getX()), $two->getX(), $this->getPrime());

        $yR = ModularArithmetic::sub(
            $slope->multipliedBy($one->getX()->minus($xR)),
            $one->getY(),
            $this->getPrime()
        );

        return $this->getPoint($xR, $yR, $one->getOrder());
    }

    public function mul(Point $one, BigInteger $n): Point
    {
        if ($one->isInfinity()) {
            return Point::infinity();
        }

        /** @var BigInteger $zero */
        $zero = BigInteger::zero();
        if ($one->getOrder()->compareTo($zero) > 0) {
            $n = $n->mod($one->getOrder());
        }

        if ($n->isEqualTo($zero)) {
            return Point::infinity();
        }

        /** @var Point[] $r */
        $r = [Point::infinity(), clone $one];

        $k = $this->getSize();
        $n1 = str_pad(Math::baseConvert(Math::toString($n), 10, 2), $k, '0', STR_PAD_LEFT);

        for ($i = 0; $i < $k; ++$i) {
            $j = $n1[$i];
            Point::cswap($r[0], $r[1], $j ^ 1);
            $r[0] = $this->add($r[0], $r[1]);
            $r[1] = $this->getDouble($r[1]);
            Point::cswap($r[0], $r[1], $j ^ 1);
        }

        $this->validate($r[0]);

        return $r[0];
    }

    public function cmp(self $other): int
    {
        $equalsA = $this->getA()
            ->isEqualTo($other->getA());
        $equalsB = $this->getB()
            ->isEqualTo($other->getB());
        $equalsPrime = $this->getPrime()
            ->isEqualTo($other->getPrime());
        $equal = $equalsA && $equalsB && $equalsPrime;

        return $equal ? 0 : 1;
    }

    public function equals(self $other): bool
    {
        return $this->cmp($other) === 0;
    }

    public function getDouble(Point $point): Point
    {
        if ($point->isInfinity()) {
            return Point::infinity();
        }

        $a = $this->getA();
        $threeX2 = BigInteger::of(3)->multipliedBy($point->getX()->power(2));

        $tangent = ModularArithmetic::div(
            $threeX2->plus($a),
            BigInteger::of(2)->multipliedBy($point->getY()),
            $this->getPrime()
        );

        $x3 = ModularArithmetic::sub(
            $tangent->power(2),
            BigInteger::of(2)->multipliedBy($point->getX()),
            $this->getPrime()
        );

        $y3 = ModularArithmetic::sub(
            $tangent->multipliedBy($point->getX()->minus($x3)),
            $point->getY(),
            $this->getPrime()
        );

        return $this->getPoint($x3, $y3, $point->getOrder());
    }

    public function createPrivateKey(): PrivateKey
    {
        return PrivateKey::create($this->generate());
    }

    public function createPublicKey(PrivateKey $privateKey): PublicKey
    {
        $point = $this->mul($this->generator, $privateKey->getSecret());

        return new PublicKey($point);
    }

    public function getGenerator(): Point
    {
        return $this->generator;
    }

    private function validate(Point $point): void
    {
        if (! $point->isInfinity() && ! $this->contains($point->getX(), $point->getY())) {
            throw new RuntimeException('Invalid point');
        }
    }

    private function generate(): BigInteger
    {
        $max = $this->generator->getOrder();
        $numBits = $this->bnNumBits($max);
        $numBytes = (int) ceil($numBits / 8);
        // Generate an integer of size >= $numBits
        $bytes = BigInteger::randomBits($numBytes);
        $mask = BigInteger::of(2)->power($numBits)->minus(1);

        return $bytes->and($mask);
    }

    /**
     * Returns the number of bits used to store this number. Non-significant upper bits are not counted.
     *
     * @see https://www.openssl.org/docs/crypto/BN_num_bytes.html
     */
    private function bnNumBits(BigInteger $x): int
    {
        $zero = BigInteger::of(0);
        if ($x->isEqualTo($zero)) {
            return 0;
        }
        $log2 = 0;
        while (! $x->isEqualTo($zero)) {
            $x = $x->shiftedRight(1);
            ++$log2;
        }

        return $log2;
    }
}
