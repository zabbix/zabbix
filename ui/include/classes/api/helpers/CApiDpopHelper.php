<?php declare(strict_types = 0);
/*
** Copyright (C) 2001-2026 Zabbix SIA
**
** This program is free software: you can redistribute it and/or modify it under the terms of
** the GNU Affero General Public License as published by the Free Software Foundation, version 3.
**
** This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
** without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
** See the GNU Affero General Public License for more details.
**
** You should have received a copy of the GNU Affero General Public License along with this program.
** If not, see <https://www.gnu.org/licenses/>.
**/

use Jose\Component\Signature\Serializer\CompactSerializer;
use Jose\Component\KeyManagement\JWKFactory;
use Jose\Component\Signature\Algorithm\ES256;
use Jose\Component\Signature\JWSVerifier;
use Jose\Component\Core\AlgorithmManager;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * Helper class containing methods for DPoP signature verification.
 */
class CApiDpopHelper {

	public const PUBLIC_KEY_PEM = '-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAE03irsjT9U8UHjfQWxRKmO7hRODMW
FVYsajjgjBE0FLCjNfOpwqeQUrXHxCTF4Vi/euCMvHNipe50AjfMIVJHag==
-----END PUBLIC KEY-----';

	private const IAT_DELAY = 2;
	private const IAT_EXP_MAX_DIFFERENCE = 60;

	public static function verifyDpopSignatureUsingJose(string $signature, string $pem, string $access_token,
			string $request_api_method): bool {
		// signature check section
		$serializer = new CompactSerializer();

		try {
			$jws = $serializer->unserialize($signature);
		}
		catch (Exception $e) {
			return false;
		}

		$jwk = JWKFactory::createFromKey($pem);

		$verifier = new JWSVerifier(new AlgorithmManager([new ES256()]));

		if (!$verifier->verifyWithKey($jws, $jwk, 0)) {
			return false;
		}

		// header check section
		$header = $jws->getSignature(0)->getProtectedHeader();

		$dpop_jwk = $jwk->toPublic()->all();

		if (!self::checkKid($header, $dpop_jwk)) {
			return false;
		}

		// payload check section
		$payload = json_decode($jws->getPayload(), true);

		if (!self::checkHtu($payload, $request_api_method)) {
			return false;
		}

		if (!self::chechAth($payload, $access_token)) {
			return false;
		}

		if (!self::checkIat($payload)) {
			return false;
		}

		if (!self::checkExp($payload)) {
			return false;
		}

		if (!self::checkJti($payload)) {
			return false;
		}

		return true;
	}

	public static function verifyDpopSignatureUsingFirebase(string $signature, string $pem, string $access_token,
			string $request_api_method): bool {
		try {
			JWT::decode($signature, new Key($pem, 'ES256'));
		}
		catch (Exception $e) {
			return false;
		}

		[$encoded_header, $encoded_payload] = explode('.', $signature);

		// header check section
		$header = json_decode(JWT::urlsafeB64Decode($encoded_header), true);

		if (!is_array($header) || !$header) {
			return false;
		}

		$dpop_jwk = self::getDpopJwkFromPem($pem);

		if (!self::checkKid($header, $dpop_jwk)) {
			return false;
		}

		// payload check section
		$payload = json_decode(JWT::urlsafeB64Decode($encoded_payload), true);

		if (!self::checkHtu($payload, $request_api_method)) {
			return false;
		}

		if (!self::chechAth($payload, $access_token)) {
			return false;
		}

		if (!self::checkIat($payload)) {
			return false;
		}

		if (!self::checkExp($payload)) {
			return false;
		}

		if (!self::checkJti($payload)) {
			return false;
		}

		return true;
	}

	private static function checkKid(array $header, array $dpop_jwk): bool {
		$expected_jwk_hash = JWT::urlsafeB64Encode(
			hash('sha256', json_encode($dpop_jwk, JSON_UNESCAPED_SLASHES), true)
		);

		return array_key_exists('kid', $header) && hash_equals($expected_jwk_hash, $header['kid']);
	}

	private static function checkHtu(array $payload, string $request_api_method): bool {
		return array_key_exists('htu', $payload) && $payload['htu'] === $request_api_method;
	}

	private static function chechAth(array $payload, string $access_token): bool {
		$expected_ath = JWT::urlsafeB64Encode(hash('sha256', $access_token, true));

		return array_key_exists('ath', $payload) && hash_equals($expected_ath, $payload['ath']);
	}

	private static function checkIat(array $payload): bool {
		if (!array_key_exists('iat', $payload)) {
			return false;
		}

		$iat = (int) $payload['iat'];
		$time = time();

		if ($iat > $time) {
			return false;
		}

		return $time - $iat <= self::IAT_DELAY;
	}

	private static function checkExp(array $payload): bool {
		if (!array_key_exists('exp', $payload)) {
			return false;
		}

		$iat = (int) $payload['iat'];
		$exp = (int) $payload['exp'];

		if ($iat > $exp) {
			return false;
		}

		if ($exp - $iat < self::IAT_EXP_MAX_DIFFERENCE) {
			return true;
		}

		return $exp > time();
	}

	private static function checkJti(array $payload): bool {
		if (!array_key_exists('jti', $payload)) {
			return false;
		}

		DBexecute('DELETE FROM dpop_jti_cache WHERE expires_at < '.time().';');

		return DBexecute(
			'INSERT INTO dpop_jti_cache (jti, expires_at)'.
				' VALUES ('.zbx_dbstr($payload['jti']).', '.zbx_dbstr($payload['exp']).');'
		);
	}

	private static function getDpopJwkFromPem(string $pem): array {
		$public_key = openssl_pkey_get_public($pem);
		$details = openssl_pkey_get_details($public_key);

		return [
			'kty' => 'EC',
			'crv' => 'P-256',
			'x' => JWT::urlsafeB64Encode($details['ec']['x']),
			'y' => JWT::urlsafeB64Encode($details['ec']['y'])
		];
	}
}
