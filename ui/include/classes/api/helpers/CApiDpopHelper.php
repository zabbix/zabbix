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
 * Helper class containing methods for DPoP signature and JWK integrity verifications.
 */
class CApiDpopHelper {

	private const TIME_SKEW = 2;
	private const TOKEN_LIFE_TIME = 60;

	private const SIGNATURE_ALGORITHM = 'ES256';

	public static function verifyDpopSignature(string $signature, string $encoded_jwk, string $kid,
			string $access_token, string $requested_api_method, int $check_time): bool {
		$jwk = json_decode($encoded_jwk, true);

		$key = JWK::parseKey($jwk, self::SIGNATURE_ALGORITHM);

		JWT::$timestamp = $check_time;
		JWT::$leeway = self::TIME_SKEW;

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

		if (!self::checkKid($header, $kid)) {
			return false;
		}

		// payload check section
		$payload = json_decode(JWT::urlsafeB64Decode($encoded_payload), true);

		if (!self::checkHtu($payload, $requested_api_method)) {
			return false;
		}

		if (!self::checkAth($payload, $access_token)) {
			return false;
		}

		if (!self::checkTokenLifeTime($payload, $check_time)) {
			return false;
		}

		if (!self::checkJti($payload, $check_time)) {
			return false;
		}

		return true;
	}

	private static function checkKid(array $header, string $kid): bool {
		return array_key_exists('kid', $header) && hash_equals($kid, $header['kid']);
	}

	private static function checkHtu(array $payload, string $requested_api_method): bool {
		// todo - use method get server_id
		$expected_htu = 'urn:zbx:server_id:'.$requested_api_method;

		return array_key_exists('htu', $payload) && hash_equals($expected_htu, $payload['htu']);
	}

	private static function checkAth(array $payload, string $access_token): bool {
		$expected_ath = JWT::urlsafeB64Encode(hash('sha256', $access_token, true));

		return array_key_exists('ath', $payload) && hash_equals($expected_ath, $payload['ath']);
	}

	private static function checkTokenLifeTime(array $payload, int $check_time): bool {
		if (!array_key_exists('iat', $payload) || !array_key_exists('exp', $payload)) {
			return false;
		}

		$iat = (int) $payload['iat'];
		$exp = (int) $payload['exp'];

		if ($iat > $exp || $iat > $check_time + self::TIME_SKEW) {
			return false;
		}

		if ($exp - $iat <= self::TOKEN_LIFE_TIME) {
			return $check_time <= $exp + self::TIME_SKEW;
		}

		return $check_time <= $iat + self::TOKEN_LIFE_TIME + self::TIME_SKEW;
	}

	private static function checkJti(array $payload, int $check_time): bool {
		if (!array_key_exists('jti', $payload)) {
			return false;
		}

		DBexecute('DELETE FROM dpop_jti_cache WHERE expires_at<'.$check_time);

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
