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


use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

/**
 * Helper class containing methods for DPoP signature verification.
 */
class CApiDpopHelper {

	private const IAT_DELAY = 2;
	private const IAT_EXP_MAX_DIFFERENCE = 60;

	private const SIGNATURE_ALGORITHM = 'ES256';

	public static function verifyDpopSignature(string $signature, string $encoded_jwk, string $access_token,
			string $request_api_method): bool {
		$jwk = json_decode($encoded_jwk, true);

		$key = JWK::parseKey($jwk, self::SIGNATURE_ALGORITHM);

		try {
			JWT::decode($signature, $key);
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

		if (!self::checkKid($header, $jwk)) {
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
		return array_key_exists('kid', $header) && hash_equals($dpop_jwk['kid'], $header['kid']);
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

		DBexecute('DELETE FROM dpop_jti_cache WHERE expires_at<'.time());

		if (DBfetch(DBselect('SELECT jti FROM dpop_jti_cache WHERE jti='.zbx_dbstr($payload['exp'])))) {
			return false;
		}

		return DBexecute(
			'INSERT INTO dpop_jti_cache (jti, expires_at)'.
				' VALUES ('.zbx_dbstr($payload['jti']).', '.zbx_dbstr($payload['exp']).')'
		);
	}

	public static function checkJwkIntegrity(array $jwk): bool {
		$key = JWK::parseKey($jwk, self::SIGNATURE_ALGORITHM);

		if (openssl_pkey_get_public($key->getKeyMaterial()) === false) {
			return false;
		}

		return true;
	}
}
