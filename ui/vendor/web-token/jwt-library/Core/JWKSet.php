<?php

declare(strict_types=1);

namespace Jose\Component\Core;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use JsonSerializable;
use Traversable;
use function array_key_exists;
use function count;
use function in_array;
use function is_array;
use function sprintf;
use const COUNT_NORMAL;
use const JSON_THROW_ON_ERROR;

class JWKSet implements Countable, IteratorAggregate, JsonSerializable
{
    private array $keys = [];

    /**
     * @param JWK[] $keys
     */
    public function __construct(array $keys)
    {
        foreach ($keys as $k => $key) {
            if (! $key instanceof JWK) {
                throw new InvalidArgumentException('Invalid list. Should only contains JWK objects');
            }

            if ($key->has('kid')) {
                unset($keys[$k]);
                $this->keys[$key->get('kid')] = $key;
            } else {
                $this->keys[] = $key;
            }
        }
    }

    /**
     * Creates a JWKSet object using the given values.
     */
    public static function createFromKeyData(array $data): self
    {
        if (! isset($data['keys'])) {
            throw new InvalidArgumentException('Invalid data.');
        }
        if (! is_array($data['keys'])) {
            throw new InvalidArgumentException('Invalid data.');
        }

        $jwkset = new self([]);
        foreach ($data['keys'] as $key) {
            $jwk = new JWK($key);
            if ($jwk->has('kid')) {
                $jwkset->keys[$jwk->get('kid')] = $jwk;
            } else {
                $jwkset->keys[] = $jwk;
            }
        }

        return $jwkset;
    }

    /**
     * Creates a JWKSet object using the given Json string.
     */
    public static function createFromJson(string $json): self
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($data)) {
            throw new InvalidArgumentException('Invalid argument.');
        }

        return self::createFromKeyData($data);
    }

    /**
     * Returns an array of keys stored in the key set.
     *
     * @return JWK[]
     */
    public function all(): array
    {
        return $this->keys;
    }

    /**
     * Add key to store in the key set. This method is immutable and will return a new object.
     */
    public function with(JWK $jwk): self
    {
        $clone = clone $this;

        if ($jwk->has('kid')) {
            $clone->keys[$jwk->get('kid')] = $jwk;
        } else {
            $clone->keys[] = $jwk;
        }

        return $clone;
    }

    /**
     * Remove key from the key set. This method is immutable and will return a new object.
     *
     * @param int|string $key Key to remove from the key set
     */
    public function without(int|string $key): self
    {
        if (! $this->has($key)) {
            return $this;
        }

        $clone = clone $this;
        unset($clone->keys[$key]);

        return $clone;
    }

    /**
     * Returns true if the key set contains a key with the given index.
     */
    public function has(int|string $index): bool
    {
        return array_key_exists($index, $this->keys);
    }

    /**
     * Returns the key with the given index. Throws an exception if the index is not present in the key store.
     */
    public function get(int|string $index): JWK
    {
        if (! $this->has($index)) {
            throw new InvalidArgumentException('Undefined index.');
        }

        return $this->keys[$index];
    }

    /**
     * Returns the values to be serialized.
     */
    public function jsonSerialize(): array
    {
        return [
            'keys' => array_values($this->keys),
        ];
    }

    /**
     * Returns the number of keys in the key set.
     *
     * @param int $mode
     */
    public function count($mode = COUNT_NORMAL): int
    {
        return count($this->keys, $mode);
    }

    /**
     * Try to find a key that fits on the selected requirements. Returns null if not found.
     *
     * @param string $type Must be 'sig' (signature) or 'enc' (encryption)
     * @param Algorithm|null $algorithm Specifies the algorithm to be used
     * @param array<string, mixed> $restrictions More restrictions such as 'kid' or 'kty'
     */
    public function selectKey(string $type, ?Algorithm $algorithm = null, array $restrictions = []): ?JWK
    {
        if (! in_array($type, ['enc', 'sig'], true)) {
            throw new InvalidArgumentException('Allowed key types are "sig" or "enc".');
        }

        $result = [];
        foreach ($this->keys as $key) {
            $ind = 0;

            $can_use = $this->canKeyBeUsedFor($type, $key);
            if ($can_use === false) {
                continue;
            }
            $ind += $can_use;

            $alg = $this->canKeyBeUsedWithAlgorithm($algorithm, $key);
            if ($alg === false) {
                continue;
            }
            $ind += $alg;

            if ($this->doesKeySatisfyRestrictions($restrictions, $key) === false) {
                continue;
            }

            $result[] = [
                'key' => $key,
                'ind' => $ind,
            ];
        }

        if (count($result) === 0) {
            return null;
        }

        usort($result, [$this, 'sortKeys']);

        return $result[0]['key'];
    }

    /**
     * Internal method only. Should not be used.
     *
     * @internal
     */
    public static function sortKeys(array $a, array $b): int
    {
        return $b['ind'] <=> $a['ind'];
    }

    /**
     * Internal method only. Should not be used.
     *
     * @internal
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->keys);
    }

    private function canKeyBeUsedFor(string $type, JWK $key): bool|int
    {
        if ($key->has('use')) {
            return $type === $key->get('use') ? 1 : false;
        }
        if ($key->has('key_ops')) {
            $key_ops = $key->get('key_ops');
            if (! is_array($key_ops)) {
                throw new InvalidArgumentException(
                    'Invalid key parameter "key_ops". Should be a list of key operations'
                );
            }

            return $type === self::convertKeyOpsToKeyUse($key_ops) ? 1 : false;
        }

        return 0;
    }

    private function canKeyBeUsedWithAlgorithm(?Algorithm $algorithm, JWK $key): bool|int
    {
        if ($algorithm === null) {
            return 0;
        }
        if (! in_array($key->get('kty'), $algorithm->allowedKeyTypes(), true)) {
            return false;
        }
        if ($key->has('alg')) {
            return $algorithm->name() === $key->get('alg') ? 2 : false;
        }

        return 1;
    }

    private function doesKeySatisfyRestrictions(array $restrictions, JWK $key): bool
    {
        foreach ($restrictions as $k => $v) {
            if (! $key->has($k) || $v !== $key->get($k)) {
                return false;
            }
        }

        return true;
    }

    private static function convertKeyOpsToKeyUse(array $key_ops): string
    {
        return match (true) {
            in_array('verify', $key_ops, true), in_array('sign', $key_ops, true) => 'sig',
            in_array('encrypt', $key_ops, true), in_array('decrypt', $key_ops, true), in_array(
                'wrapKey',
                $key_ops,
                true
            ), in_array(
                'unwrapKey',
                $key_ops,
                true
            ), in_array('deriveKey', $key_ops, true), in_array('deriveBits', $key_ops, true) => 'enc',
            default => throw new InvalidArgumentException(sprintf(
                'Unsupported key operation value "%s"',
                implode(', ', $key_ops)
            )),
        };
    }
}
