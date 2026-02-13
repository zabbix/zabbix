<?php

declare(strict_types=1);

namespace Jose\Component\Signature;

use InvalidArgumentException;
use Jose\Component\Core\Algorithm;
use Jose\Component\Core\AlgorithmManager;
use Jose\Component\Core\JWK;
use Jose\Component\Core\JWKSet;
use Jose\Component\Core\Util\Base64UrlSafe;
use Jose\Component\Core\Util\KeyChecker;
use Jose\Component\Signature\Algorithm\MacAlgorithm;
use Jose\Component\Signature\Algorithm\SignatureAlgorithm;
use Throwable;
use function sprintf;

class JWSVerifier
{
    public function __construct(
        private readonly AlgorithmManager $signatureAlgorithmManager
    ) {
    }

    /**
     * Returns the algorithm manager associated to the JWSVerifier.
     */
    public function getSignatureAlgorithmManager(): AlgorithmManager
    {
        return $this->signatureAlgorithmManager;
    }

    /**
     * This method will try to verify the JWS object using the given key and for the given signature. It returns true if
     * the signature is verified, otherwise false.
     *
     * @return bool true if the verification of the signature succeeded, else false
     */
    public function verifyWithKey(JWS $jws, JWK $jwk, int $signature, ?string $detachedPayload = null): bool
    {
        $jwkset = new JWKSet([$jwk]);

        return $this->verifyWithKeySet($jws, $jwkset, $signature, $detachedPayload);
    }

    /**
     * This method will try to verify the JWS object using the given key set and for the given signature. It returns
     * true if the signature is verified, otherwise false.
     *
     * @param JWS $jws A JWS object
     * @param JWKSet $jwkset The signature will be verified using keys in the key set
     * @param JWK $jwk The key used to verify the signature in case of success
     * @param string|null $detachedPayload If not null, the value must be the detached payload encoded in Base64 URL safe. If the input contains a payload, throws an exception.
     *
     * @return bool true if the verification of the signature succeeded, else false
     */
    public function verifyWithKeySet(
        JWS $jws,
        JWKSet $jwkset,
        int $signatureIndex,
        ?string $detachedPayload = null,
        ?JWK &$jwk = null
    ): bool {
        if ($jwkset->count() === 0) {
            throw new InvalidArgumentException('There is no key in the key set.');
        }
        if ($jws->countSignatures() === 0) {
            throw new InvalidArgumentException('The JWS does not contain any signature.');
        }
        $this->checkPayload($jws, $detachedPayload);
        $signature = $jws->getSignature($signatureIndex);

        return $this->verifySignature($jws, $jwkset, $signature, $detachedPayload, $jwk);
    }

    private function verifySignature(
        JWS $jws,
        JWKSet $jwkset,
        Signature $signature,
        ?string $detachedPayload = null,
        ?JWK &$successJwk = null
    ): bool {
        $input = $this->getInputToVerify($jws, $signature, $detachedPayload);
        $algorithm = $this->getAlgorithm($signature);
        foreach ($jwkset->all() as $jwk) {
            try {
                KeyChecker::checkKeyUsage($jwk, 'verification');
                KeyChecker::checkKeyAlgorithm($jwk, $algorithm->name());
                if ($algorithm->verify($jwk, $input, $signature->getSignature()) === true) {
                    $successJwk = $jwk;

                    return true;
                }
            } catch (Throwable) {
                //We do nothing, we continue with other keys
                continue;
            }
        }

        return false;
    }

    private function getInputToVerify(JWS $jws, Signature $signature, ?string $detachedPayload): string
    {
        $payload = $jws->getPayload();
        $isPayloadEmpty = $payload === null || $payload === '';
        $encodedProtectedHeader = $signature->getEncodedProtectedHeader() ?? '';
        $isPayloadBase64Encoded = ! $signature->hasProtectedHeaderParameter(
            'b64'
        ) || $signature->getProtectedHeaderParameter('b64') === true;
        $encodedPayload = $jws->getEncodedPayload();

        if ($isPayloadBase64Encoded && $encodedPayload !== null) {
            return sprintf('%s.%s', $encodedProtectedHeader, $encodedPayload);
        }

        $callable = $isPayloadBase64Encoded === true ? static fn (?string $p): string => Base64UrlSafe::encodeUnpadded(
            $p ?? ''
        )
            : static fn (?string $p): string => $p ?? '';

        $payloadToUse = $callable($isPayloadEmpty ? $detachedPayload : $payload);

        return sprintf('%s.%s', $encodedProtectedHeader, $payloadToUse);
    }

    private function checkPayload(JWS $jws, ?string $detachedPayload = null): void
    {
        $isPayloadEmpty = $this->isPayloadEmpty($jws->getPayload());
        if ($detachedPayload !== null && ! $isPayloadEmpty) {
            throw new InvalidArgumentException('A detached payload is set, but the JWS already has a payload.');
        }
        if ($isPayloadEmpty && $detachedPayload === null) {
            throw new InvalidArgumentException('The JWS has a detached payload, but no payload is provided.');
        }
    }

    /**
     * @return MacAlgorithm|SignatureAlgorithm
     */
    private function getAlgorithm(Signature $signature): Algorithm
    {
        $completeHeader = [...$signature->getProtectedHeader(), ...$signature->getHeader()];
        if (! isset($completeHeader['alg'])) {
            throw new InvalidArgumentException('No "alg" parameter set in the header.');
        }

        $algorithm = $this->signatureAlgorithmManager->get($completeHeader['alg']);
        if (! $algorithm instanceof SignatureAlgorithm && ! $algorithm instanceof MacAlgorithm) {
            throw new InvalidArgumentException(sprintf(
                'The algorithm "%s" is not supported or is not a signature or MAC algorithm.',
                $completeHeader['alg']
            ));
        }

        return $algorithm;
    }

    private function isPayloadEmpty(?string $payload): bool
    {
        return $payload === null || $payload === '';
    }
}
